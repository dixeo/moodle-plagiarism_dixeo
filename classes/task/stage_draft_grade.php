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
use plagiarism_dixeo\grading\grade_applier;

/**
 * Prepares a proposed grade and hands it to the teacher automatic grades are recorded against.
 *
 * The task runs as the student, like the analysis task, so the send, its credits and the
 * consent check belong to the student whose work it is. The proposal is stored in the plugin
 * table, then {@see apply_draft_grade} writes it to the gradebook as the teacher who switched
 * automatic grading on. When no such teacher qualifies, the draft is kept for a teacher.
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
        global $CFG, $DB;

        $data = (array) $this->get_custom_data();
        $cmid = (int) ($data['cmid'] ?? 0);
        $courseid = (int) ($data['courseid'] ?? 0);
        $userid = (int) ($data['userid'] ?? 0);
        $submissionid = (int) ($data['submissionid'] ?? 0);

        if ($cmid <= 0 || $courseid <= 0 || $userid <= 0 || $submissionid <= 0) {
            mtrace('plagiarism_dixeo: incomplete task data, no draft grade prepared.');
            return;
        }

        require_once($CFG->dirroot . '/plagiarism/dixeo/lib.php');
        if (!\plagiarism_plugin_dixeo::enabled_for_course($courseid)) {
            mtrace('plagiarism_dixeo: Dixeo is off for this course, skipping draft grade.');
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

        $reader = new assign_submission_reader();
        $assignmentcontext = $reader->get_assignment_context($cmid, $courseid);

        $service = service_factory::get_assign_ux_service(new authorship_persistence_adapter());
        $operation = $service->submit_grade_and_wait($courseid, $cmid, $userid);
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

        $graderid = analysis_gate::wants_draft_grade($cmid) ? grade_applier::resolve_grader_id($cmid) : 0;
        if ($graderid > 0) {
            $apply = new apply_draft_grade();
            $apply->set_component('plagiarism_dixeo');
            $apply->set_userid($graderid);
            $apply->set_custom_data([
                'cmid' => $cmid,
                'courseid' => $courseid,
                'userid' => $userid,
                'submissionid' => $submissionid,
                'draftid' => (int) $record->id,
            ]);
            \core\task\manager::queue_adhoc_task($apply);
        } else {
            mtrace("plagiarism_dixeo: no teacher is recorded for automatic grades, draft kept for submission $submissionid.");
        }

        grade_draft_created::create([
            'context' => \context_module::instance($cmid),
            'objectid' => (int) $record->id,
            'relateduserid' => $userid,
        ])->trigger();
    }
}
