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

use local_dixeo\dto\operation_result;
use local_dixeo\external\service_factory;
use local_dixeo\service\assign_ux_service;
use plagiarism_dixeo\authorship\author_storage;
use plagiarism_dixeo\authorship\authorship;
use plagiarism_dixeo\automation\analysis_gate;
use plagiarism_dixeo\feedback\grade_feedback_storage;
use plagiarism_dixeo\feedback\presubmission_feedback_storage;
use plagiarism_dixeo\task\analyze_submission;
use plagiarism_dixeo\task\stage_draft_grade;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/plagiarism/dixeo/tests/fixtures/race_lock_factory.php');

/**
 * Automatic analysis stays off unless every switch is on, and the observer only queues work.
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
        set_config('assignment_feedback_enabled', 1, 'plagiarism_dixeo');
        set_config('grading_enabled', 1, 'plagiarism_dixeo');
        set_config('assign_trigger', analysis_gate::TRIGGER_SUBMIT, 'plagiarism_dixeo');

        $this->course = $this->getDataGenerator()->create_course();
        $this->assign = $this->getDataGenerator()->create_module('assign', ['course' => $this->course->id]);
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->getDataGenerator()->get_plugin_generator('local_dixeo')
            ->create_terms_acceptance(['userid' => (int) $this->student->id]);

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
    }

    protected function tearDown(): void {
        \plagiarism_dixeo\tests\race_lock_factory::before_lock(null, null);
        service_factory::reset();
        parent::tearDown();
    }

    /**
     * Create an authorship record whose check has not settled yet, so the task would run it.
     *
     * @return \stdClass
     */
    private function create_pending_record(): \stdClass {
        set_config('authorship_enabled', 1, 'plagiarism_dixeo');
        $record = author_storage::create_record(
            (int) $this->student->id,
            'mod_assign',
            'submission',
            $this->submissionid,
            ['initial_confidence' => 20.0]
        );
        $this->assertNotNull($record);
        author_storage::update_record((int) $record->id, ['status' => author_storage::STATUS_PENDING]);
        return author_storage::get_record((int) $record->id);
    }

    /**
     * Run the task's authorship step for the test submission with a service that must send nothing.
     */
    private function store_authorship_without_sends(): void {
        $service = $this->getMockBuilder(assign_ux_service::class)
            ->disableOriginalConstructor()
            ->getMock();
        $service->expects($this->never())->method('submit_authorship_and_wait');

        $method = new \ReflectionMethod(analyze_submission::class, 'store_authorship');
        $method->setAccessible(true);
        ob_start();
        try {
            $method->invoke(
                new analyze_submission(),
                $service,
                \context_module::instance((int) $this->assign->cmid),
                (object) [
                    'id' => $this->submissionid,
                    'timemodified' => time(),
                    'attemptnumber' => 0,
                ],
                (int) $this->course->id,
                (int) $this->assign->cmid,
                (int) $this->student->id
            );
        } finally {
            ob_end_clean();
        }
    }

    /**
     * Run the analysis task for the test submission as the student.
     */
    private function run_analysis_task(): void {
        $task = new analyze_submission();
        $task->set_custom_data([
            'cmid' => (int) $this->assign->cmid,
            'courseid' => (int) $this->course->id,
            'userid' => (int) $this->student->id,
            'submissionid' => $this->submissionid,
        ]);
        $this->setUser($this->student);
        ob_start();
        try {
            $task->execute();
        } finally {
            ob_end_clean();
        }
    }

    /**
     * Authorship record stored for the test submission, or null.
     *
     * @return \stdClass|null
     */
    private function stored_authorship(): ?\stdClass {
        return author_storage::get_record_by_context(
            (int) $this->student->id,
            'mod_assign',
            'submission',
            $this->submissionid
        );
    }

    /**
     * Whether coaching feedback is stored for the test submission.
     *
     * @return bool
     */
    private function has_stored_feedback(): bool {
        global $DB;

        return $DB->record_exists(presubmission_feedback_storage::TABLE, ['itemid' => $this->submissionid]);
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

    public function test_nothing_is_queued_when_the_assignment_turns_feedback_off(): void {
        service_factory::get_settings_config()->set(
            'plagiarism_dixeo',
            'assignment_feedback_enabled',
            \context_module::instance((int) $this->assign->cmid),
            '0',
            false
        );

        $this->fire_submission_event(analysis_gate::TRIGGER_SUBMIT);

        $this->assertSame(0, $this->count_queued_tasks());
    }

    public function test_authorship_alone_queues_the_analysis(): void {
        set_config('assignment_feedback_enabled', '0', 'plagiarism_dixeo');
        set_config('authorship_enabled', '1', 'plagiarism_dixeo');

        $this->fire_submission_event(analysis_gate::TRIGGER_SUBMIT);

        $this->assertSame(1, $this->count_queued_tasks());
    }

    public function test_authorship_alone_stores_authorship_without_a_review(): void {
        set_config('assignment_feedback_enabled', '0', 'plagiarism_dixeo');
        set_config('authorship_enabled', '1', 'plagiarism_dixeo');
        $service = $this->createMock(assign_ux_service::class);
        $service->expects($this->never())->method('submit_review_and_wait');
        $service->expects($this->once())
            ->method('submit_authorship_and_wait')
            ->willReturn(operation_result::completed('job-confidence', []));
        $service->method('map_authorship_result')->willReturn(['confidence' => 90.0, 'explanation' => 'Consistent.']);
        service_factory::set_test_assign_ux_service($service);

        $this->run_analysis_task();

        $this->assertNotNull($this->stored_authorship());
        $this->assertFalse($this->has_stored_feedback());
    }

    public function test_feedback_alone_stores_the_review_without_authorship(): void {
        set_config('authorship_enabled', '0', 'plagiarism_dixeo');
        $service = $this->createMock(assign_ux_service::class);
        $service->expects($this->never())->method('submit_authorship_and_wait');
        $service->expects($this->once())
            ->method('submit_review_and_wait')
            ->willReturn(operation_result::completed('job-review', []));
        $service->method('map_review_result')->willReturn(['feedback_html' => '<p>Coaching.</p>']);
        service_factory::set_test_assign_ux_service($service);

        $this->run_analysis_task();

        $this->assertNull($this->stored_authorship());
        $this->assertTrue($this->has_stored_feedback());
    }

    public function test_nothing_is_queued_when_plagiarism_is_off_site_wide(): void {
        global $CFG;
        $CFG->enableplagiarism = 0;

        $this->fire_submission_event(analysis_gate::TRIGGER_SUBMIT);

        $this->assertSame(0, $this->count_queued_tasks());
    }

    public function test_nothing_is_queued_when_the_plugin_is_disabled(): void {
        set_config('enabled', '0', 'local_dixeo');

        $this->fire_submission_event(analysis_gate::TRIGGER_SUBMIT);

        $this->assertSame(0, $this->count_queued_tasks());
    }

    public function test_nothing_is_queued_when_the_dixeo_feedback_default_is_off(): void {
        set_config('assignment_feedback_enabled', '0', 'plagiarism_dixeo');

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
        set_config('assignment_feedback_enabled', '0', 'plagiarism_dixeo');

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

    public function test_a_finished_quiz_is_not_replaced_when_analysis_runs_again(): void {
        $created = author_storage::create_record(
            (int) $this->student->id,
            'mod_assign',
            'submission',
            $this->submissionid,
            [
                'initial_confidence' => 20.0,
                'quiz_data' => ['questions' => [['id' => 'q0', 'type' => 'true_false', 'text' => 'One?']]],
            ]
        );
        $this->assertNotNull($created);
        author_storage::update_record((int) $created->id, [
            'status' => author_storage::STATUS_COMPLETE,
            'final_confidence' => 15.0,
            'quiz_grade' => 33.33,
        ]);

        $service = $this->getMockBuilder(\local_dixeo\service\assign_ux_service::class)
            ->disableOriginalConstructor()
            ->getMock();
        $service->expects($this->never())->method('submit_authorship_and_wait');

        $task = new analyze_submission();
        $method = new \ReflectionMethod(analyze_submission::class, 'store_authorship');
        $method->setAccessible(true);
        ob_start();
        $method->invoke(
            $task,
            $service,
            \context_module::instance((int) $this->assign->cmid),
            (object) [
                'id' => $this->submissionid,
                'timemodified' => time(),
                'attemptnumber' => 0,
            ],
            (int) $this->course->id,
            (int) $this->assign->cmid,
            (int) $this->student->id
        );
        ob_end_clean();

        $stored = author_storage::get_record_by_context(
            (int) $this->student->id,
            'mod_assign',
            'submission',
            $this->submissionid
        );
        $this->assertNotNull($stored);
        $this->assertSame((int) $created->id, (int) $stored->id);
        $this->assertSame(author_storage::STATUS_COMPLETE, $stored->status);
        $this->assertEqualsWithDelta(15.0, (float) $stored->final_confidence, 0.01);
    }

    public function test_the_task_sends_nothing_while_a_check_of_the_submission_is_running(): void {
        global $CFG;

        // Locks taken in one process must exclude each other, which the database row factory guarantees.
        $CFG->lock_factory = '\\core\\lock\\db_record_lock_factory';
        $pending = $this->create_pending_record();
        $held = authorship::acquire_lock('authorship_submission_' . $this->submissionid);
        $this->assertNotNull($held);

        try {
            $this->store_authorship_without_sends();
        } finally {
            $held->release();
        }

        $stored = author_storage::get_record((int) $pending->id);
        $this->assertNotNull($stored);
        $this->assertSame(author_storage::STATUS_PENDING, $stored->status);
        $this->assertSame($pending->timemodified, $stored->timemodified);
    }

    public function test_the_task_decides_on_the_record_read_under_the_submission_lock(): void {
        global $CFG;

        $pending = $this->create_pending_record();
        $CFG->lock_factory = '\\' . \plagiarism_dixeo\tests\race_lock_factory::class;
        \plagiarism_dixeo\tests\race_lock_factory::before_lock(
            'authorship_submission_' . $this->submissionid,
            fn() => author_storage::update_record((int) $pending->id, [
                'status' => author_storage::STATUS_COMPLETE,
                'final_confidence' => 15.0,
            ])
        );

        $this->store_authorship_without_sends();

        $stored = author_storage::get_record((int) $pending->id);
        $this->assertNotNull($stored);
        $this->assertSame(author_storage::STATUS_COMPLETE, $stored->status);
        $this->assertEqualsWithDelta(15.0, (float) $stored->final_confidence, 0.01);
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

    public function test_the_observer_only_queues_and_prints_nothing(): void {
        global $DB, $USER;

        $grading = \context_module::instance((int) $this->assign->cmid);
        \local_dixeo\external\service_factory::get_settings_config()->set(
            'plagiarism_dixeo',
            'enable_auto_grading',
            \context_module::instance((int) $this->assign->cmid),
            '1',
            false
        );
        $this->assertTrue(analysis_gate::wants_draft_grade((int) $grading->instanceid));
        $mock = $this->createMock(assign_ux_service::class);
        $mock->expects($this->never())->method($this->anything());
        service_factory::set_test_assign_ux_service($mock);
        $this->setUser($this->student);
        $before = (int) $USER->id;

        $this->expectOutputString('');
        $this->fire_submission_event(analysis_gate::TRIGGER_SUBMIT);

        $this->assertSame($before, (int) $USER->id);
        $this->assertSame(1, $this->count_queued_tasks());
        $this->assertSame(1, $DB->count_records('task_adhoc', ['classname' => '\\' . stage_draft_grade::class]));
        $this->assertNull(presubmission_feedback_storage::get_by_context(
            (int) $this->student->id,
            'mod_assign',
            'submission',
            $this->submissionid
        ));
    }
}
