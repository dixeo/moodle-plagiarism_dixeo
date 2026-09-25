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
 * Tests for opt-in automatic analysis of assignment submissions.
 *
 * @package    plagiarism_dixeo
 * @category   test
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo;

use plagiarism_dixeo\authorship\author_storage;
use plagiarism_dixeo\automation\analysis_gate;
use plagiarism_dixeo\feedback\grade_feedback_storage;
use plagiarism_dixeo\feedback\presubmission_feedback_storage;
use plagiarism_dixeo\task\analyze_submission;

/**
 * Automatic analysis stays off unless every switch is on, and only ever queues work.
 *
 * @covers \plagiarism_dixeo\automation\analysis_gate
 * @covers \plagiarism_dixeo\observer
 * @covers \plagiarism_dixeo\task\analyze_submission
 */
final class automation_test extends \advanced_testcase {
    /** @var \stdClass Course holding the assignment under test. */
    private $course;

    /** @var \stdClass Assignment used by the tests. */
    private $assign;

    /** @var \stdClass Student who owns the submission. */
    private $student;

    /** @var int Id of the student's submission. */
    private $submissionid;

    public function setUp(): void {
        global $CFG, $DB;

        parent::setUp();
        $this->resetAfterTest(true);

        $CFG->enableplagiarism = 1;
        set_config('enabled', 1, 'plagiarism_dixeo');
        set_config('enable_mod_assign', 1, 'plagiarism_dixeo');
        set_config('assign_trigger', analysis_gate::TRIGGER_SUBMIT, 'plagiarism_dixeo');

        $this->course = $this->getDataGenerator()->create_course();
        $this->assign = $this->getDataGenerator()->create_module('assign', ['course' => $this->course->id]);
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');

        $now = time();
        $this->submissionid = (int) $DB->insert_record('assign_submission', (object) [
            'assignment' => $this->assign->id,
            'userid' => $this->student->id,
            'timecreated' => $now,
            'timemodified' => $now,
            'status' => 'submitted',
            'groupid' => 0,
            'attemptnumber' => 0,
            'latest' => 1,
        ]);

        \plagiarism_plugin_dixeo::set_activity_config((int) $this->assign->cmid, ['use_dixeo' => '1']);
    }

    /**
     * Fire the event the given trigger corresponds to.
     *
     * @param string $trigger One of the analysis_gate TRIGGER_* constants.
     */
    private function fire_submission_event(string $trigger): void {
        $params = [
            'context' => \context_module::instance((int) $this->assign->cmid),
            'courseid' => (int) $this->course->id,
            'userid' => (int) $this->student->id,
            'objectid' => $this->submissionid,
            'other' => [
                'content' => 'Some work.',
                'pathnamehashes' => [],
                'submission_editable' => false,
            ],
        ];

        if ($trigger === analysis_gate::TRIGGER_UPLOAD) {
            \assignsubmission_onlinetext\event\assessable_uploaded::create($params)->trigger();
            return;
        }
        \mod_assign\event\assessable_submitted::create($params)->trigger();
    }

    /**
     * The assign instance behind the test activity.
     *
     * @return \assign
     */
    private function get_assign(): \assign {
        global $CFG;

        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $cm = get_coursemodule_from_id('assign', (int) $this->assign->cmid, 0, false, MUST_EXIST);

        return new \assign(\context_module::instance((int) $this->assign->cmid), $cm, $this->course);
    }

    /**
     * Adhoc analysis tasks currently on the queue.
     *
     * @return int
     */
    private function count_queued_tasks(): int {
        global $DB;

        return $DB->count_records('task_adhoc', ['classname' => '\\' . analyze_submission::class]);
    }

    public function test_submitting_queues_analysis_when_everything_is_switched_on(): void {
        $this->fire_submission_event(analysis_gate::TRIGGER_SUBMIT);

        $this->assertSame(1, $this->count_queued_tasks());
    }

    public function test_nothing_is_queued_when_the_activity_has_not_opted_in(): void {
        \plagiarism_plugin_dixeo::set_activity_config((int) $this->assign->cmid, ['use_dixeo' => '0']);

        $this->fire_submission_event(analysis_gate::TRIGGER_SUBMIT);

        $this->assertSame(0, $this->count_queued_tasks());
    }

    public function test_nothing_is_queued_when_plagiarism_is_off_site_wide(): void {
        global $CFG;
        $CFG->enableplagiarism = 0;

        $this->fire_submission_event(analysis_gate::TRIGGER_SUBMIT);

        $this->assertSame(0, $this->count_queued_tasks());
    }

    public function test_nothing_is_queued_when_the_plugin_is_disabled(): void {
        set_config('enabled', 0, 'plagiarism_dixeo');

        $this->fire_submission_event(analysis_gate::TRIGGER_SUBMIT);

        $this->assertSame(0, $this->count_queued_tasks());
    }

    public function test_nothing_is_queued_when_assignments_are_not_covered(): void {
        set_config('enable_mod_assign', 0, 'plagiarism_dixeo');

        $this->fire_submission_event(analysis_gate::TRIGGER_SUBMIT);

        $this->assertSame(0, $this->count_queued_tasks());
    }

    public function test_a_draft_upload_is_ignored_when_the_trigger_is_final_submit(): void {
        $this->fire_submission_event(analysis_gate::TRIGGER_UPLOAD);

        $this->assertSame(0, $this->count_queued_tasks());
    }

    public function test_a_draft_upload_queues_analysis_when_that_is_the_chosen_trigger(): void {
        set_config('assign_trigger', analysis_gate::TRIGGER_UPLOAD, 'plagiarism_dixeo');

        $this->fire_submission_event(analysis_gate::TRIGGER_UPLOAD);

        $this->assertSame(1, $this->count_queued_tasks());
    }

    public function test_the_same_submission_is_only_queued_once(): void {
        $this->fire_submission_event(analysis_gate::TRIGGER_SUBMIT);
        $this->fire_submission_event(analysis_gate::TRIGGER_SUBMIT);

        $this->assertSame(1, $this->count_queued_tasks());
    }

    public function test_the_queued_task_runs_as_the_student(): void {
        global $DB;

        $this->fire_submission_event(analysis_gate::TRIGGER_SUBMIT);

        $task = $DB->get_record('task_adhoc', ['classname' => '\\' . analyze_submission::class]);
        $this->assertSame((int) $this->student->id, (int) $task->userid);
        $this->assertSame('plagiarism_dixeo', $task->component);

        $data = json_decode($task->customdata, true);
        $this->assertSame((int) $this->assign->cmid, $data['cmid']);
        $this->assertSame($this->submissionid, $data['submissionid']);
    }

    public function test_editing_a_submission_discards_the_previous_analysis(): void {
        author_storage::create_record(
            (int) $this->student->id,
            'mod_assign',
            'submission',
            $this->submissionid,
            ['initial_confidence' => 80.0]
        );
        presubmission_feedback_storage::create_record(
            (int) $this->student->id,
            'mod_assign',
            'submission',
            $this->submissionid,
            '<p>Old feedback.</p>'
        );
        grade_feedback_storage::create_record(
            (int) $this->student->id,
            'mod_assign',
            'submission',
            $this->submissionid,
            ['grade' => 70.0, 'feedback' => 'Old grade.']
        );

        $event = \assignsubmission_onlinetext\event\submission_updated::create([
            'context' => \context_module::instance((int) $this->assign->cmid),
            'courseid' => (int) $this->course->id,
            'objectid' => $this->submissionid,
            'relateduserid' => (int) $this->student->id,
            'other' => [
                'submissionid' => $this->submissionid,
                'submissionattempt' => 0,
                'submissionstatus' => 'draft',
                'onlinetextwordcount' => 12,
                'groupid' => 0,
                'groupname' => '',
            ],
        ]);
        $event->set_assign($this->get_assign());
        $event->trigger();

        $this->assertNull(author_storage::get_record_by_context(
            (int) $this->student->id,
            'mod_assign',
            'submission',
            $this->submissionid
        ));
        $this->assertNull(presubmission_feedback_storage::get_by_context(
            (int) $this->student->id,
            'mod_assign',
            'submission',
            $this->submissionid
        ));
        $this->assertNull(grade_feedback_storage::get_by_context(
            (int) $this->student->id,
            'mod_assign',
            'submission',
            $this->submissionid
        ));
    }

    public function test_the_task_does_nothing_once_the_activity_has_opted_out(): void {
        $task = new analyze_submission();
        $task->set_custom_data([
            'cmid' => (int) $this->assign->cmid,
            'courseid' => (int) $this->course->id,
            'userid' => (int) $this->student->id,
            'submissionid' => $this->submissionid,
        ]);
        \plagiarism_plugin_dixeo::set_activity_config((int) $this->assign->cmid, ['use_dixeo' => '0']);

        $sink = $this->redirectEvents();
        ob_start();
        $task->execute();
        ob_end_clean();

        // No API call and no record: the task bailed out before doing any work.
        $this->assertSame([], $sink->get_events());
        $this->assertNull(author_storage::get_record_by_context(
            (int) $this->student->id,
            'mod_assign',
            'submission',
            $this->submissionid
        ));
    }

    public function test_the_task_does_nothing_when_the_submission_has_been_deleted(): void {
        global $DB;

        $task = new analyze_submission();
        $task->set_custom_data([
            'cmid' => (int) $this->assign->cmid,
            'courseid' => (int) $this->course->id,
            'userid' => (int) $this->student->id,
            'submissionid' => $this->submissionid,
        ]);
        $DB->delete_records('assign_submission', ['id' => $this->submissionid]);

        $sink = $this->redirectEvents();
        ob_start();
        $task->execute();
        ob_end_clean();

        $this->assertSame([], $sink->get_events());
    }
}
