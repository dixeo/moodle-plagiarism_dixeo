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
 * Adhoc task that prepares a draft grade for a teacher to review.
 *
 * @package    plagiarism_dixeo
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo\task;

use local_dixeo\external\service_factory;
use local_dixeo\service\assign_submission_reader;
use plagiarism_dixeo\authorship\authorship_persistence_adapter;
use plagiarism_dixeo\automation\analysis_gate;
use plagiarism_dixeo\event\grade_draft_created;
use plagiarism_dixeo\feedback\grade_feedback_storage;

/**
 * Prepares a proposed grade and holds it for the teacher.
 *
 * Nothing this task writes reaches the student. The proposal sits in the plugin's own table
 * until a teacher opens the grading form, reviews it and saves it themselves, so the
 * gradebook only ever records a decision a person made.
 */
class stage_draft_grade extends \core\task\adhoc_task {
    /** @var string Component the stored proposal belongs to. */
    private const COMPONENT = 'mod_assign';

    /** @var string Area the stored proposal belongs to. */
    private const AREA = 'submission';

    /**
     * Human readable task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_stage_draft_grade', 'plagiarism_dixeo');
    }

    /**
     * Prepare the draft grade named in the task data.
     *
     * @throws \moodle_exception When the grading job cannot be completed.
     */
    public function execute(): void {
        global $DB;

        $data = (array) $this->get_custom_data();
        $cmid = (int) ($data['cmid'] ?? 0);
        $courseid = (int) ($data['courseid'] ?? 0);
        $userid = (int) ($data['userid'] ?? 0);
        $submissionid = (int) ($data['submissionid'] ?? 0);

        if ($cmid <= 0 || $courseid <= 0 || $userid <= 0 || $submissionid <= 0) {
            mtrace('plagiarism_dixeo: incomplete task data, no draft grade prepared.');
            return;
        }

        if (!analysis_gate::is_available() || !analysis_gate::wants_draft_grade($cmid)) {
            mtrace("plagiarism_dixeo: draft grading is switched off for course module $cmid, skipping.");
            return;
        }

        $submission = $DB->get_record(
            'assign_submission',
            ['id' => $submissionid, 'userid' => $userid],
            'id,timemodified,attemptnumber'
        );
        if (!$submission) {
            mtrace("plagiarism_dixeo: submission $submissionid no longer exists, skipping.");
            return;
        }

        $adapter = new authorship_persistence_adapter();
        $authorship = $adapter->get_authorship_by_context($userid, self::COMPONENT, self::AREA, $submissionid);

        $reader = new assign_submission_reader();
        $assignmentcontext = $reader->get_assignment_context($cmid, $courseid);

        $service = service_factory::get_assign_ux_service($adapter);
        $operation = $service->submit_grade_and_wait($courseid, $cmid, $userid, $authorship);
        if (!$operation->completed) {
            throw new \moodle_exception('automation_grade_incomplete', 'plagiarism_dixeo');
        }

        $mapped = $service->map_grade_result($assignmentcontext, $operation->result);
        if (isset($mapped['error'])) {
            throw new \moodle_exception('automation_grade_failed', 'plagiarism_dixeo', '', $mapped['error']);
        }

        $transaction = $DB->start_delegated_transaction();
        $record = grade_feedback_storage::create_record(
            $userid,
            self::COMPONENT,
            self::AREA,
            $submissionid,
            $mapped,
            (int) $submission->timemodified,
            (int) $submission->attemptnumber
        );
        if (!$record) {
            throw new \moodle_exception('automation_grade_failed', 'plagiarism_dixeo', '', '');
        }
        $transaction->allow_commit();

        grade_draft_created::create([
            'context' => \context_module::instance($cmid),
            'objectid' => (int) $record->id,
            'relateduserid' => $userid,
        ])->trigger();
    }
}
