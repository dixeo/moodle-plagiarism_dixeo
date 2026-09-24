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
 * Privacy API for plagiarism_dixeo authorship records.
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

/**
 * Export and delete authorship confidence / quiz data stored for students.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /** @var string Authorship persistence table. */
    public const TABLE = 'plagiarism_dixeo_authorship';

    /**
     * Describe stored authorship fields.
     *
     * @param collection $collection Metadata collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            self::TABLE,
            [
                'userid' => 'privacy:metadata:authorship:userid',
                'component' => 'privacy:metadata:authorship:component',
                'area' => 'privacy:metadata:authorship:area',
                'itemid' => 'privacy:metadata:authorship:itemid',
                'initial_confidence' => 'privacy:metadata:authorship:initial_confidence',
                'quiz_data' => 'privacy:metadata:authorship:quiz_data',
                'quiz_responses' => 'privacy:metadata:authorship:quiz_responses',
                'quiz_grade' => 'privacy:metadata:authorship:quiz_grade',
                'final_confidence' => 'privacy:metadata:authorship:final_confidence',
                'timecreated' => 'privacy:metadata:authorship:timecreated',
                'timemodified' => 'privacy:metadata:authorship:timemodified',
            ],
            'privacy:metadata:authorship'
        );

        return $collection;
    }

    /**
     * List user contexts that hold authorship rows for the user.
     *
     * @param int $userid User id.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        global $DB;

        $contextlist = new contextlist();
        if (!$DB->record_exists(self::TABLE, ['userid' => $userid])) {
            return $contextlist;
        }

        \context_user::instance($userid);
        $contextlist->add_from_sql(
            'SELECT id FROM {context} WHERE contextlevel = :contextlevel AND instanceid = :userid',
            [
                'contextlevel' => CONTEXT_USER,
                'userid' => $userid,
            ]
        );

        return $contextlist;
    }

    /**
     * List users with authorship data in a user context.
     *
     * @param userlist $userlist User list for a context.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_USER) {
            return;
        }

        $userlist->add_from_sql(
            'userid',
            'SELECT userid FROM {' . self::TABLE . '} WHERE userid = :userid',
            ['userid' => (int) $context->instanceid]
        );
    }

    /**
     * Export authorship records for the approved user contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        if ($contextlist->count() === 0) {
            return;
        }

        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel !== CONTEXT_USER || (int) $context->instanceid !== $userid) {
                continue;
            }

            $records = $DB->get_records(self::TABLE, ['userid' => $userid], 'id ASC');
            if (!$records) {
                continue;
            }

            $exportdata = [];
            foreach ($records as $record) {
                $exportdata[] = (object) [
                    'component' => $record->component,
                    'area' => $record->area,
                    'itemid' => (int) $record->itemid,
                    'initial_confidence' => $record->initial_confidence,
                    'quiz_data' => $record->quiz_data,
                    'quiz_responses' => $record->quiz_responses,
                    'quiz_grade' => $record->quiz_grade,
                    'final_confidence' => $record->final_confidence,
                    'timecreated' => transform::datetime((int) $record->timecreated),
                    'timemodified' => transform::datetime((int) $record->timemodified),
                ];
            }

            writer::with_context($context)->export_data(
                [get_string('privacy:path:authorship', 'plagiarism_dixeo')],
                (object) ['records' => $exportdata]
            );
        }
    }

    /**
     * Delete all authorship data in a user context.
     *
     * @param \context $context Context.
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if ($context->contextlevel !== CONTEXT_USER) {
            return;
        }

        $DB->delete_records(self::TABLE, ['userid' => (int) $context->instanceid]);
    }

    /**
     * Delete authorship data for one user in approved contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = (int) $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel === CONTEXT_USER && (int) $context->instanceid === $userid) {
                $DB->delete_records(self::TABLE, ['userid' => $userid]);
                return;
            }
        }
    }

    /**
     * Delete authorship data for users in a user context.
     *
     * @param approved_userlist $userlist Approved users.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if ($context->contextlevel !== CONTEXT_USER) {
            return;
        }

        $userids = $userlist->get_userids();
        if (!$userids) {
            return;
        }

        // User context is always for a single user; only delete if that user is approved.
        $contextuserid = (int) $context->instanceid;
        if (in_array($contextuserid, $userids, true)) {
            $DB->delete_records(self::TABLE, ['userid' => $contextuserid]);
        }
    }
}
