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
 * Tests for get_assign_job_status access and safe errors.
 *
 * @package    plagiarism_dixeo
 * @category   test
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo;

use local_dixeo\dto\job_binding_metadata;
use local_dixeo\dto\job_status;
use local_dixeo\external\service_factory;
use local_dixeo\repository\job_repository;
use local_dixeo\service\assign_ux_service;
use local_dixeo\service\job_service;
use plagiarism_dixeo\authorship\authorship_persistence_adapter;
use plagiarism_dixeo\feedback\presubmission_feedback_storage;
use plagiarism_dixeo\external\get_assign_job_status;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/plagiarism/dixeo/lib.php');


/**
 * Poll endpoint: caps, course/cm boundary, safe client errors.
 *
 * @covers \plagiarism_dixeo\external\get_assign_job_status
 */
final class get_assign_job_status_test extends \advanced_testcase {
    protected function tearDown(): void {
        service_factory::reset();
        parent::tearDown();
    }

    /**
     * Turn Dixeo on for an assignment at site and activity level.
     *
     * @param int $cmid Course module id.
     */
    private function enable_dixeo(int $cmid): void {
        global $CFG;

        $CFG->enableplagiarism = 1;
        set_config('assignment_feedback_enabled', 1, 'plagiarism_dixeo');
        set_config('grading_enabled', 1, 'plagiarism_dixeo');
    }

    public function test_cross_course_cmid_rejected(): void {
        $this->resetAfterTest();

        $coursea = $this->getDataGenerator()->create_course();
        $courseb = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $coursea->id]);
        $student = $this->getDataGenerator()->create_and_enrol($coursea, 'student');
        $this->setUser($student);

        $this->enable_dixeo((int) $assign->cmid);

        $this->expectException(\moodle_exception::class);
        get_assign_job_status::execute('job-1', (int) $courseb->id, (int) $assign->cmid, 'review');
    }

    public function test_student_cannot_poll_grade_flow(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        get_assign_job_status::execute('job-1', (int) $course->id, (int) $assign->cmid, 'grade');
    }

    public function test_withholds_raw_exception_when_debugging_off(): void {
        $this->resetAfterTest();
        set_debugging(DEBUG_NONE);

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'assignsubmission_onlinetext_enabled' => 1,
        ]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $this->enable_dixeo((int) $assign->cmid);

        $mock = $this->createMock(assign_ux_service::class);
        $mock->expects($this->once())
            ->method('get_status_for_module')
            ->willThrowException(new \RuntimeException('internal job table leak'));
        service_factory::set_test_assign_ux_service($mock);

        $result = get_assign_job_status::execute(
            'job-x',
            (int) $course->id,
            (int) $assign->cmid,
            'review'
        );

        $this->assertSame('failed', $result['status']);
        $this->assertSame(get_string('error:unexpected', 'local_dixeo'), $result['errormessage']);
        $this->assertStringNotContainsString('job table leak', $result['errormessage']);
    }

    public function test_completed_review_returns_sanitized_html(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'assignsubmission_onlinetext_enabled' => 1,
        ]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);
        $this->enable_dixeo((int) $assign->cmid);

        $status = new job_status(
            jobid: 'job-done',
            type: 'assign_review',
            status: job_status::STATUS_COMPLETED,
            progress: 100,
            createdat: time(),
            result: ['feedbackHtml' => '<p>Coaching tip</p>'],
        );

        $mock = $this->createMock(assign_ux_service::class);
        $mock->expects($this->once())->method('get_status_for_module')->willReturn($status);
        $mock->expects($this->once())
            ->method('map_review_result')
            ->willReturn(['feedback_html' => '<p>Coaching tip</p>']);
        service_factory::set_test_assign_ux_service($mock);

        $result = get_assign_job_status::execute(
            'job-done',
            (int) $course->id,
            (int) $assign->cmid,
            'review'
        );

        $this->assertSame('completed', $result['status']);
        $this->assertStringContainsString('Coaching tip', $result['html']);
        $this->assertSame('', $result['errormessage']);
    }

    public function test_completed_review_is_stored_for_the_submission(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'assignsubmission_onlinetext_enabled' => 1,
        ]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);
        $this->enable_dixeo((int) $assign->cmid);

        $now = time();
        $submissionid = (int) $DB->insert_record('assign_submission', (object) [
            'assignment' => $assign->id,
            'userid' => $student->id,
            'status' => 'draft',
            'attemptnumber' => 0,
            'latest' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        $status = new job_status(
            jobid: 'job-store',
            type: 'assign_review',
            status: job_status::STATUS_COMPLETED,
            progress: 100,
            createdat: time(),
            result: ['feedback_html' => '<p>Kept after reload.</p>'],
        );

        $mock = $this->createMock(assign_ux_service::class);
        $mock->method('get_status_for_module')->willReturn($status);
        $mock->method('map_review_result')->willReturn(['feedback_html' => '<p>Kept after reload.</p>']);
        service_factory::set_test_assign_ux_service($mock);

        get_assign_job_status::execute('job-store', (int) $course->id, (int) $assign->cmid, 'review');

        $stored = \plagiarism_dixeo\feedback\presubmission_feedback_storage::get_by_context(
            (int) $student->id,
            'mod_assign',
            'submission',
            $submissionid
        );
        $this->assertNotNull($stored);
        $this->assertStringContainsString('Kept after reload.', $stored->feedback);
    }

    /**
     * A student with a submission in a fresh assignment with Dixeo on.
     *
     * @param \stdClass|null $course Course to use, or null for a new one.
     * @param \stdClass|null $student Enrolled student to use, or null for a new one.
     * @return array{course: \stdClass, assign: \stdClass, student: \stdClass, submissionid: int}
     */
    private function seed_submission(?\stdClass $course = null, ?\stdClass $student = null): array {
        global $DB;

        $course = $course ?? $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'assignsubmission_onlinetext_enabled' => 1,
        ]);
        $student = $student ?? $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->enable_dixeo((int) $assign->cmid);
        $submissionid = (int) $DB->insert_record('assign_submission', (object) [
            'assignment' => $assign->id,
            'userid' => $student->id,
            'status' => 'draft',
            'attemptnumber' => 0,
            'latest' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        return ['course' => $course, 'assign' => $assign, 'student' => $student, 'submissionid' => $submissionid];
    }

    public function test_a_review_job_cannot_be_stored_against_another_assignment(): void {
        global $DB;

        $this->resetAfterTest();
        $a = $this->seed_submission();
        $b = $this->seed_submission($a['course'], $a['student']);
        $this->setUser($a['student']);

        $repository = new job_repository();
        $repository->register(
            'job-from-a',
            (int) $a['course']->id,
            (int) $a['student']->id,
            'default',
            'assign_review',
            assign_ux_service::COMPONENT,
            job_binding_metadata::for_module('assign', (int) $a['assign']->cmid)
        );
        $job = $this->createMock(job_service::class);
        $job->method('get_job_repository')->willReturn($repository);
        $job->expects($this->never())->method('get_job_status');
        service_factory::set_test_assign_ux_service(
            new assign_ux_service($job, null, new authorship_persistence_adapter(), 'test-ns')
        );

        try {
            get_assign_job_status::execute('job-from-a', (int) $a['course']->id, (int) $b['assign']->cmid, 'review');
            $this->fail('A job started on another assignment must be refused.');
        } catch (\moodle_exception $e) {
            $this->assertSame('error:job_not_found', $e->errorcode);
        }
        $this->assertFalse($DB->record_exists(presubmission_feedback_storage::TABLE, ['itemid' => $b['submissionid']]));
    }

    public function test_feedback_is_not_stored_for_a_submission_edited_after_the_job_started(): void {
        global $DB;

        $this->resetAfterTest();
        $seed = $this->seed_submission();
        $this->setUser($seed['student']);
        $status = new job_status(
            jobid: 'job-old',
            type: 'assign_review',
            status: job_status::STATUS_COMPLETED,
            progress: 100,
            createdat: time() - 120,
            result: ['feedback_html' => '<p>About the old draft.</p>'],
        );
        $mock = $this->createMock(assign_ux_service::class);
        $mock->method('get_status_for_module')->willReturn($status);
        $mock->method('map_review_result')->willReturn(['feedback_html' => '<p>About the old draft.</p>']);
        service_factory::set_test_assign_ux_service($mock);

        $result = get_assign_job_status::execute('job-old', (int) $seed['course']->id, (int) $seed['assign']->cmid, 'review');

        $this->assertSame('completed', $result['status']);
        $this->assertFalse($DB->record_exists(presubmission_feedback_storage::TABLE, ['itemid' => $seed['submissionid']]));
    }

    public function test_a_failed_job_never_returns_the_provider_error_text(): void {
        $this->resetAfterTest();
        $seed = $this->seed_submission();
        $this->setUser($seed['student']);
        $status = new job_status(
            jobid: 'job-failed',
            type: 'assign_review',
            status: job_status::STATUS_FAILED,
            progress: 100,
            createdat: time(),
            errorcode: 'internal_error',
            errormessage: 'secret upstream text',
        );
        $mock = $this->createMock(assign_ux_service::class);
        $mock->method('get_status_for_module')->willReturn($status);
        service_factory::set_test_assign_ux_service($mock);

        $result = get_assign_job_status::execute('job-failed', (int) $seed['course']->id, (int) $seed['assign']->cmid, 'review');

        $this->assertDebuggingCalled();
        $this->assertSame('failed', $result['status']);
        $this->assertStringNotContainsString('secret upstream text', $result['errormessage']);
        $this->assertNotSame('', $result['errormessage']);
    }
}
