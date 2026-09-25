<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Privacy API for plagiarism_dixeo.
 *
 * @package    plagiarism_dixeo
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use plagiarism_dixeo\authorship\author_storage;
use plagiarism_dixeo\feedback\grade_feedback_storage;
use plagiarism_dixeo\feedback\presubmission_feedback_storage;

/**
 * Exports and deletes the analysis data Dixeo stores against assignment submissions.
 *
 * Every record is anchored to a submission, so all data is reported at the context of the
 * activity it belongs to. Deleting a course or an assignment therefore purges it.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /** @var string Authorship persistence table. */
    public const TABLE = author_storage::TABLE;

    /** @var string Component every stored record belongs to. */
    private const COMPONENT = 'mod_assign';

    /** @var string Area every stored record belongs to. */
    private const AREA = 'submission';

    /**
     * Tables holding personal data, mapped to the lang key naming their export path.
     *
     * @return array<string, string>
     */
    private static function get_tables(): array {
        return [
            author_storage::TABLE => 'privacy:path:authorship',
            presubmission_feedback_storage::TABLE => 'privacy:path:presubmission',
            grade_feedback_storage::TABLE => 'privacy:path:gradefeedback',
        ];
    }

    /**
     * Describe the stored fields and the analysis sent to the Dixeo service.
     *
     * @param collection $collection Metadata collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            author_storage::TABLE,
            [
                'userid' => 'privacy:metadata:authorship:userid',
                'component' => 'privacy:metadata:authorship:component',
                'area' => 'privacy:metadata:authorship:area',
                'itemid' => 'privacy:metadata:authorship:itemid',
                'status' => 'privacy:metadata:authorship:status',
                'initial_confidence' => 'privacy:metadata:authorship:initial_confidence',
                'initial_explanation' => 'privacy:metadata:authorship:initial_explanation',
                'quiz_data' => 'privacy:metadata:authorship:quiz_data',
                'quiz_responses' => 'privacy:metadata:authorship:quiz_responses',
                'response_timings' => 'privacy:metadata:authorship:response_timings',
                'quiz_grade' => 'privacy:metadata:authorship:quiz_grade',
                'quiz_explanation' => 'privacy:metadata:authorship:quiz_explanation',
                'quiz_question_feedbacks' => 'privacy:metadata:authorship:quiz_question_feedbacks',
                'final_confidence' => 'privacy:metadata:authorship:final_confidence',
                'final_explanation' => 'privacy:metadata:authorship:final_explanation',
                'timecreated' => 'privacy:metadata:authorship:timecreated',
                'timemodified' => 'privacy:metadata:authorship:timemodified',
            ],
            'privacy:metadata:authorship'
        );

        $collection->add_database_table(
            presubmission_feedback_storage::TABLE,
            [
                'userid' => 'privacy:metadata:presubmission:userid',
                'component' => 'privacy:metadata:presubmission:component',
                'area' => 'privacy:metadata:presubmission:area',
                'itemid' => 'privacy:metadata:presubmission:itemid',
                'feedback' => 'privacy:metadata:presubmission:feedback',
                'timecreated' => 'privacy:metadata:presubmission:timecreated',
                'timemodified' => 'privacy:metadata:presubmission:timemodified',
            ],
            'privacy:metadata:presubmission'
        );

        $collection->add_database_table(
            grade_feedback_storage::TABLE,
            [
                'userid' => 'privacy:metadata:gradefeedback:userid',
                'component' => 'privacy:metadata:gradefeedback:component',
                'area' => 'privacy:metadata:gradefeedback:area',
                'itemid' => 'privacy:metadata:gradefeedback:itemid',
                'status' => 'privacy:metadata:gradefeedback:status',
                'gradingmethod' => 'privacy:metadata:gradefeedback:gradingmethod',
                'analysis' => 'privacy:metadata:gradefeedback:analysis',
                'feedback' => 'privacy:metadata:gradefeedback:feedback',
                'grade' => 'privacy:metadata:gradefeedback:grade',
                'criteria' => 'privacy:metadata:gradefeedback:criteria',
                'timecreated' => 'privacy:metadata:gradefeedback:timecreated',
                'timemodified' => 'privacy:metadata:gradefeedback:timemodified',
            ],
            'privacy:metadata:gradefeedback'
        );

        $collection->add_external_location_link(
            'dixeo',
            [
                'submission_content' => 'privacy:metadata:dixeo:submission_content',
                'quiz_responses' => 'privacy:metadata:dixeo:quiz_responses',
                'activity_context' => 'privacy:metadata:dixeo:activity_context',
            ],
            'privacy:metadata:dixeo'
        );

        return $collection;
    }

    /**
     * List the activity contexts holding data for the user.
     *
     * @param int $userid User id.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        foreach (array_keys(self::get_tables()) as $table) {
            $contextlist->add_from_sql(
                "SELECT ctx.id
                   FROM {context} ctx
                   JOIN {course_modules} cm ON cm.id = ctx.instanceid
                   JOIN {modules} m ON m.id = cm.module AND m.name = :modname
                   JOIN {assign_submission} asub ON asub.assignment = cm.instance
                   JOIN {" . $table . "} dx ON dx.itemid = asub.id
                  WHERE ctx.contextlevel = :contextlevel
                        AND dx.userid = :userid
                        AND dx.component = :component
                        AND dx.area = :area",
                [
                    'modname' => 'assign',
                    'contextlevel' => CONTEXT_MODULE,
                    'userid' => $userid,
                    'component' => self::COMPONENT,
                    'area' => self::AREA,
                ]
            );
        }

        return $contextlist;
    }

    /**
     * List the users holding data in an activity context.
     *
     * @param userlist $userlist User list for a context.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        $assignid = self::get_assign_instance($context);
        if ($assignid === null) {
            return;
        }

        foreach (array_keys(self::get_tables()) as $table) {
            $userlist->add_from_sql(
                'userid',
                "SELECT dx.userid
                   FROM {" . $table . "} dx
                   JOIN {assign_submission} asub ON asub.id = dx.itemid
                  WHERE asub.assignment = :assignid
                        AND dx.component = :component
                        AND dx.area = :area",
                [
                    'assignid' => $assignid,
                    'component' => self::COMPONENT,
                    'area' => self::AREA,
                ]
            );
        }
    }

    /**
     * Export the user's analysis data for each approved activity context.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        $userid = (int) $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            $assignid = self::get_assign_instance($context);
            if ($assignid === null) {
                continue;
            }

            foreach (self::get_tables() as $table => $pathkey) {
                $records = self::get_records($table, $assignid, [$userid]);
                if (!$records) {
                    continue;
                }

                $exportdata = [];
                foreach ($records as $record) {
                    $exportdata[] = self::export_record($record);
                }

                writer::with_context($context)->export_data(
                    [get_string($pathkey, 'plagiarism_dixeo')],
                    (object) ['records' => $exportdata]
                );
            }
        }
    }

    /**
     * Delete every user's analysis data for an activity.
     *
     * @param \context $context Context being purged.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        $assignid = self::get_assign_instance($context);
        if ($assignid === null) {
            return;
        }

        self::delete_records($assignid, []);
    }

    /**
     * Delete one user's analysis data in each approved activity context.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        $userid = (int) $contextlist->get_user()->id;

        foreach ($contextlist->get_contexts() as $context) {
            $assignid = self::get_assign_instance($context);
            if ($assignid === null) {
                continue;
            }

            self::delete_records($assignid, [$userid]);
        }
    }

    /**
     * Delete the approved users' analysis data in an activity context.
     *
     * @param approved_userlist $userlist Approved users.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        $assignid = self::get_assign_instance($userlist->get_context());
        if ($assignid === null) {
            return;
        }

        $userids = $userlist->get_userids();
        if (!$userids) {
            return;
        }

        self::delete_records($assignid, $userids);
    }

    /**
     * Resolve the assignment instance an activity context belongs to.
     *
     * @param \context $context Context to resolve.
     * @return int|null Assignment instance id, or null if the context is not an assignment.
     */
    private static function get_assign_instance(\context $context): ?int {
        if ($context->contextlevel != CONTEXT_MODULE) {
            return null;
        }

        $cm = get_coursemodule_from_id('assign', $context->instanceid, 0, false, IGNORE_MISSING);

        return $cm ? (int) $cm->instance : null;
    }

    /**
     * Build the where clause selecting records for an assignment and, optionally, given users.
     *
     * @param int $assignid Assignment instance id.
     * @param int[] $userids Users to restrict to, or an empty array for all users.
     * @return array{0: string, 1: array} Select clause and parameters.
     */
    private static function get_select(int $assignid, array $userids): array {
        global $DB;

        $select = 'component = :component AND area = :area
                   AND itemid IN (SELECT id FROM {assign_submission} WHERE assignment = :assignid)';
        $params = [
            'component' => self::COMPONENT,
            'area' => self::AREA,
            'assignid' => $assignid,
        ];

        if ($userids) {
            [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
            $select .= " AND userid $insql";
            $params += $inparams;
        }

        return [$select, $params];
    }

    /**
     * Fetch records for an assignment and, optionally, given users.
     *
     * @param string $table Table to read.
     * @param int $assignid Assignment instance id.
     * @param int[] $userids Users to restrict to, or an empty array for all users.
     * @return \stdClass[]
     */
    private static function get_records(string $table, int $assignid, array $userids): array {
        global $DB;

        [$select, $params] = self::get_select($assignid, $userids);

        return $DB->get_records_select($table, $select, $params, 'id ASC');
    }

    /**
     * Delete records from every table for an assignment and, optionally, given users.
     *
     * @param int $assignid Assignment instance id.
     * @param int[] $userids Users to restrict to, or an empty array for all users.
     */
    private static function delete_records(int $assignid, array $userids): void {
        global $DB;

        [$select, $params] = self::get_select($assignid, $userids);

        foreach (array_keys(self::get_tables()) as $table) {
            $DB->delete_records_select($table, $select, $params);
        }
    }

    /**
     * Shape one stored record for export, omitting internal ids.
     *
     * @param \stdClass $record Stored record.
     * @return \stdClass
     */
    private static function export_record(\stdClass $record): \stdClass {
        $skip = ['id', 'userid', 'component', 'area', 'itemid'];

        $exported = [];
        foreach ((array) $record as $field => $value) {
            if (in_array($field, $skip, true)) {
                continue;
            }
            $exported[$field] = in_array($field, ['timecreated', 'timemodified'], true)
                ? transform::datetime((int) $value)
                : $value;
        }
        $exported['itemid'] = (int) $record->itemid;

        return (object) $exported;
    }
}
