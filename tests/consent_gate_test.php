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
 * Tests for the Terms of Use gate on every path that sends student work.
 *
 * @package    plagiarism_dixeo
 * @category   test
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo;

use local_dixeo\exception\consent_required_exception;
use local_dixeo\external\service_factory;
use local_dixeo\service\assign_ux_service;
use local_dixeo\service\consent_service;
use local_dixeo\service\job_service;
use plagiarism_dixeo\authorship\author_storage;
use plagiarism_dixeo\authorship\authorship_persistence_adapter;
use plagiarism_dixeo\automation\analysis_gate;
use plagiarism_dixeo\external\check_authorship;
use plagiarism_dixeo\external\mod_assign_grade_submission;
use plagiarism_dixeo\external\mod_assign_pre_submission;
use plagiarism_dixeo\external\verify_authorship;
use plagiarism_dixeo\task\analyze_submission;
use plagiarism_dixeo\task\retry_pending_analysis;
use plagiarism_dixeo\task\stage_draft_grade;

/**
 * Student work reaches Dixeo only while the people concerned have accepted the current terms.
 *
 * @covers \plagiarism_dixeo\external\mod_assign_pre_submission
 * @covers \plagiarism_dixeo\external\check_authorship
 * @covers \plagiarism_dixeo\external\verify_authorship
 * @covers \plagiarism_dixeo\external\mod_assign_grade_submission
 * @covers \plagiarism_dixeo\observer
 * @covers \plagiarism_dixeo\task\analyze_submission
 * @covers \plagiarism_dixeo\task\stage_draft_grade
 * @covers \plagiarism_dixeo\task\retry_pending_analysis
 */
final class consent_gate_test extends \advanced_testcase {
    /** @var \stdClass Course. */
    private \stdClass $course;

    /** @var \stdClass Assign module record. */
    private \stdClass $assign;

    /** @var \stdClass Student. */
    private \stdClass $student;

    /** @var \stdClass Teacher. */
    private \stdClass $teacher;

    /** @var int Student's submission id. */
    private int $submissionid;

    protected function setUp(): void {
        global $CFG, $DB;

        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/plagiarism/dixeo/lib.php');

        $CFG->enableplagiarism = 1;
        set_config('assignment_feedback_enabled', 1, 'plagiarism_dixeo');
        set_config('authorship_enabled', 1, 'plagiarism_dixeo');
        set_config('verification_test', 1, 'plagiarism_dixeo');
        set_config('grading_enabled', 1, 'plagiarism_dixeo');
        set_config('assign_trigger', analysis_gate::TRIGGER_SUBMIT, 'plagiarism_dixeo');

        $this->course = $this->getDataGenerator()->create_course();
        $this->assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $this->course->id,
            'assignsubmission_onlinetext_enabled' => 1,
        ]);
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');

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
        $DB->insert_record('assignsubmission_onlinetext', (object) [
            'assignment' => $this->assign->id,
            'submission' => $this->submissionid,
            'onlinetext' => '<p>My essay.</p>',
            'onlineformat' => FORMAT_HTML,
        ]);
        \local_dixeo\external\service_factory::get_settings_config()->set(
            'plagiarism_dixeo',
            'enable_auto_grading',
            \context_module::instance((int) $this->assign->cmid),
            '1',
            false
        );
    }

    protected function tearDown(): void {
        service_factory::reset();
        parent::tearDown();
    }

    /**
     * Use the real assign service over a job service that must never send.
     */
    private function forbid_sends(): void {
        $job = $this->createMock(job_service::class);
        $job->expects($this->never())->method('submit_job');
        $job->expects($this->never())->method('submit_and_wait');
        service_factory::set_test_assign_ux_service(
            new assign_ux_service($job, null, new authorship_persistence_adapter(), 'test-ns')
        );
    }

    /**
     * Record acceptance of the current terms.
     *
     * @param \stdClass $user User.
     */
    private function accept(\stdClass $user): void {
        $this->getDataGenerator()->get_plugin_generator('local_dixeo')
            ->create_terms_acceptance(['userid' => (int) $user->id]);
    }

    /**
     * Withdraw acceptance as the user would.
     *
     * @param \stdClass $user User.
     */
    private function withdraw(\stdClass $user): void {
        $this->setUser($user);
        (new consent_service())->withdraw_terms((int) $user->id);
        $this->setUser(null);
    }

    /**
     * Run a task as the given user, swallowing its trace output.
     *
     * @param \core\task\adhoc_task $task Task.
     * @param \stdClass $user User the task runs as.
     * @return string Trace output.
     */
    private function run_task(\core\task\adhoc_task $task, \stdClass $user): string {
        $task->set_custom_data([
            'cmid' => (int) $this->assign->cmid,
            'courseid' => (int) $this->course->id,
            'userid' => (int) $this->student->id,
            'submissionid' => $this->submissionid,
        ]);
        $this->setUser($user);
        ob_start();
        try {
            $task->execute();
        } finally {
            $output = (string) ob_get_clean();
        }
        return $output;
    }

    public function test_pre_submission_refuses_a_student_without_terms(): void {
        $this->forbid_sends();
        $this->setUser($this->student);

        $this->expectException(consent_required_exception::class);
        mod_assign_pre_submission::execute((int) $this->assign->cmid);
    }

    public function test_check_authorship_refuses_a_student_without_terms(): void {
        $this->forbid_sends();
        $this->setUser($this->student);

        $this->expectException(consent_required_exception::class);
        check_authorship::execute('mod_assign', 'submission', (int) $this->assign->cmid);
    }

    public function test_verify_authorship_refuses_a_student_without_terms_and_changes_nothing(): void {
        $record = author_storage::create_record(
            (int) $this->student->id,
            'mod_assign',
            'submission',
            $this->submissionid,
            [
                'initial_confidence' => 40.0,
                'quiz_data' => ['questions' => [['id' => 'q0', 'type' => 'open', 'text' => 'Why?']]],
            ]
        );
        $this->forbid_sends();
        $this->setUser($this->student);

        try {
            verify_authorship::execute((int) $record->id, '{"q0":"Because"}');
            $this->fail('A student without current terms must be refused.');
        } catch (consent_required_exception $e) {
            $this->assertSame('error:consentrequired', $e->errorcode);
        }
        $after = author_storage::get_record((int) $record->id);
        $this->assertSame($record->status, $after->status);
        $this->assertNull($after->quiz_responses);
    }

    public function test_grade_assist_refuses_a_teacher_without_terms(): void {
        $this->accept($this->student);
        $this->forbid_sends();
        $this->setUser($this->teacher);

        $this->expectException(consent_required_exception::class);
        mod_assign_grade_submission::execute((int) $this->assign->cmid, (int) $this->student->id);
    }

    public function test_grade_assist_names_the_student_who_has_not_accepted(): void {
        $this->accept($this->teacher);
        $this->forbid_sends();
        $this->setUser($this->teacher);

        try {
            mod_assign_grade_submission::execute((int) $this->assign->cmid, (int) $this->student->id);
            $this->fail('A student without current terms must stop Assist.');
        } catch (consent_required_exception $e) {
            $this->assertSame('error:consent_subject_not_current', $e->errorcode);
        }
    }

    public function test_the_observer_queues_nothing_for_a_student_without_terms(): void {
        global $DB;

        \mod_assign\event\assessable_submitted::create([
            'context' => \context_module::instance((int) $this->assign->cmid),
            'courseid' => (int) $this->course->id,
            'userid' => (int) $this->student->id,
            'objectid' => $this->submissionid,
            'other' => ['submission_editable' => false],
        ])->trigger();

        $this->assertSame(0, $DB->count_records('task_adhoc', ['component' => 'plagiarism_dixeo']));
    }

    public function test_the_retry_sweep_skips_a_student_without_terms_until_they_accept(): void {
        global $DB;

        $pending = author_storage::create_record((int) $this->student->id, 'mod_assign', 'submission', $this->submissionid);
        $DB->set_field(author_storage::TABLE, 'timecreated', time() - HOURSECS, ['id' => $pending->id]);

        ob_start();
        (new retry_pending_analysis())->execute();
        ob_end_clean();
        $this->assertSame(0, $DB->count_records('task_adhoc', ['classname' => '\\' . analyze_submission::class]));

        $this->accept($this->student);
        ob_start();
        (new retry_pending_analysis())->execute();
        ob_end_clean();
        $this->assertSame(1, $DB->count_records('task_adhoc', ['classname' => '\\' . analyze_submission::class]));
    }

    public function test_analysis_sends_nothing_when_terms_were_withdrawn_after_queueing(): void {
        $this->accept($this->student);
        $this->withdraw($this->student);
        $this->forbid_sends();

        $output = $this->run_task(new analyze_submission(), $this->student);

        $this->assertStringContainsString('terms not current', $output);

        $this->assertNull(author_storage::get_record_by_context(
            (int) $this->student->id,
            'mod_assign',
            'submission',
            $this->submissionid
        ));
    }

    public function test_draft_grading_sends_nothing_when_terms_were_withdrawn_after_queueing(): void {
        global $DB;

        $this->accept($this->student);
        $this->withdraw($this->student);
        $this->forbid_sends();

        $output = $this->run_task(new stage_draft_grade(), $this->student);

        $this->assertStringContainsString('terms not current', $output);

        $this->assertSame(0, $DB->count_records('plagiarism_dixeo_grade_feedback'));
    }
}
