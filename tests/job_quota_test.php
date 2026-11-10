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
 * Tests for the per-student limit on paid Dixeo jobs.
 *
 * @package    plagiarism_dixeo
 * @category   test
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo;

use local_dixeo\api\client;
use local_dixeo\api\job_poller;
use local_dixeo\dto\job_binding_metadata;
use local_dixeo\dto\job_quota;
use local_dixeo\dto\operation_result;
use local_dixeo\exception\job_quota_exceeded_exception;
use local_dixeo\external\service_factory;
use local_dixeo\repository\job_repository;
use local_dixeo\service\assign_ux_service;
use local_dixeo\service\job_service;
use plagiarism_dixeo\authorship\authorship_persistence_adapter;
use plagiarism_dixeo\external\assign_access;
use plagiarism_dixeo\external\check_authorship;
use plagiarism_dixeo\external\mod_assign_pre_submission;
use plagiarism_dixeo\feedback\presubmission_feedback_storage;

/**
 * A student cannot drain the site's Dixeo credits from one assignment.
 *
 * @covers \plagiarism_dixeo\external\assign_access::student_job_quota
 * @covers \plagiarism_dixeo\external\mod_assign_pre_submission
 * @covers \plagiarism_dixeo\external\check_authorship
 */
final class job_quota_test extends \advanced_testcase {
    /** @var \stdClass Course. */
    private \stdClass $course;

    /** @var \stdClass Assign module record. */
    private \stdClass $assign;

    /** @var \stdClass Student. */
    private \stdClass $student;

    /** @var \stdClass Latest submission. */
    private \stdClass $submission;

    /** @var string[] Endpoints posted to the mocked Dixeo API, in order. */
    private array $posted = [];

    protected function setUp(): void {
        global $CFG, $DB;

        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/plagiarism/dixeo/lib.php');
        // Locks taken in one process must exclude each other, which the database row factory guarantees.
        $CFG->lock_factory = '\\core\\lock\\db_record_lock_factory';
        $CFG->enableplagiarism = 1;
        set_config('assignment_feedback_enabled', 1, 'plagiarism_dixeo');
        set_config('authorship_enabled', 1, 'plagiarism_dixeo');

        $this->course = $this->getDataGenerator()->create_course();
        $this->assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $this->course->id,
            'assignsubmission_onlinetext_enabled' => 1,
        ]);
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->getDataGenerator()->get_plugin_generator('local_dixeo')
            ->create_terms_acceptance(['userid' => (int) $this->student->id]);

        $now = time();
        $id = $DB->insert_record('assign_submission', (object) [
            'assignment' => $this->assign->id,
            'userid' => $this->student->id,
            'timecreated' => $now,
            'timemodified' => $now,
            'status' => 'draft',
            'groupid' => 0,
            'attemptnumber' => 0,
            'latest' => 1,
        ]);
        $DB->insert_record('assignsubmission_onlinetext', (object) [
            'assignment' => $this->assign->id,
            'submission' => $id,
            'onlinetext' => '<p>My essay.</p>',
            'onlineformat' => FORMAT_HTML,
        ]);
        $this->submission = $DB->get_record('assign_submission', ['id' => $id]);
        $this->setUser($this->student);
    }

    protected function tearDown(): void {
        service_factory::reset();
        parent::tearDown();
    }

    /**
     * Use the real assign and job services over a mocked Dixeo API.
     *
     * Every POST is recorded and answered with a new job id. Polls complete at once: the
     * confidence step with a score that requires the verification quiz, the quiz step with
     * one question.
     */
    private function use_mocked_api(): void {
        $client = $this->createMock(client::class);
        $client->method('post')->willReturnCallback(function (string $endpoint): array {
            $this->posted[] = $endpoint;
            return ['id' => 'job-' . count($this->posted)];
        });
        $poller = $this->getMockBuilder(job_poller::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['poll'])
            ->getMock();
        $poller->method('poll')->willReturnCallback(function (string $jobid): operation_result {
            $endpoint = end($this->posted);
            if ($endpoint === assign_ux_service::ENDPOINT_AUTHORSHIP_QUIZ) {
                return operation_result::completed($jobid, [
                    'questions' => [['id' => 'q1', 'type' => 'true_false', 'text' => 'One?', 'correct' => 'true']],
                ]);
            }
            return operation_result::completed($jobid, ['confidence' => 5.0, 'explanation' => 'Unlike earlier work.']);
        });
        $job = new job_service($client, $poller, new job_repository());
        service_factory::set_test_assign_ux_service(
            new assign_ux_service($job, null, new authorship_persistence_adapter(), 'test-ns')
        );
    }

    /**
     * Record jobs the student already started on the assignment.
     *
     * @param int $count Number of jobs.
     */
    private function register_earlier_jobs(int $count): void {
        $repository = new job_repository();
        for ($i = 0; $i < $count; $i++) {
            $repository->register(
                'job-earlier-' . $i,
                (int) $this->course->id,
                (int) $this->student->id,
                'default',
                'assign_review',
                assign_ux_service::COMPONENT,
                job_binding_metadata::for_module('assign', (int) $this->assign->cmid)
            );
        }
    }

    public function test_analyze_stops_once_the_hourly_limit_is_reached(): void {
        $this->use_mocked_api();

        for ($i = 0; $i < assign_access::STUDENT_JOBS_PER_HOUR; $i++) {
            $result = mod_assign_pre_submission::execute((int) $this->assign->cmid);
            $this->assertSame('job-' . ($i + 1), $result['jobid']);
        }

        try {
            mod_assign_pre_submission::execute((int) $this->assign->cmid);
            $this->fail('The call over the limit must be refused.');
        } catch (job_quota_exceeded_exception $e) {
            $this->assertSame('error:job_quota_exceeded', $e->errorcode);
            $this->assertSame('local_dixeo', $e->module);
        }
        $this->assertCount(assign_access::STUDENT_JOBS_PER_HOUR, $this->posted);
    }

    public function test_analyze_posts_nothing_while_another_send_holds_the_quota(): void {
        $this->use_mocked_api();
        $held = \core\lock\lock_config::get_lock_factory(job_quota::LOCK_TYPE)->get_lock(
            job_quota::lock_key(assign_ux_service::COMPONENT, (int) $this->student->id, (int) $this->assign->cmid),
            0
        );
        $this->assertNotFalse($held);

        try {
            mod_assign_pre_submission::execute((int) $this->assign->cmid);
            $this->fail('A send while another holds the quota must be refused.');
        } catch (job_quota_exceeded_exception $e) {
            $this->assertSame('error:job_quota_exceeded', $e->errorcode);
        } finally {
            $held->release();
        }
        $this->assertSame([], $this->posted);
    }

    public function test_jobs_older_than_an_hour_do_not_count(): void {
        global $DB;

        $this->use_mocked_api();
        for ($i = 0; $i < assign_access::STUDENT_JOBS_PER_HOUR; $i++) {
            mod_assign_pre_submission::execute((int) $this->assign->cmid);
        }
        $DB->set_field('local_dixeo_jobs', 'timecreated', time() - HOURSECS - 1, ['jobid' => 'job-1']);

        $result = mod_assign_pre_submission::execute((int) $this->assign->cmid);

        $this->assertSame('pending', $result['status']);
        $this->assertCount(assign_access::STUDENT_JOBS_PER_HOUR + 1, $this->posted);
    }

    public function test_an_unchanged_submission_gets_its_stored_feedback_without_a_job(): void {
        presubmission_feedback_storage::create_record(
            (int) $this->student->id,
            'mod_assign',
            'submission',
            (int) $this->submission->id,
            '<p>Stored coaching.</p>',
            (int) $this->submission->timemodified,
            (int) $this->submission->attemptnumber
        );
        $mock = $this->createMock(assign_ux_service::class);
        $mock->expects($this->never())->method('submit_review');
        service_factory::set_test_assign_ux_service($mock);

        $result = mod_assign_pre_submission::execute((int) $this->assign->cmid);

        $this->assertSame('completed', $result['status']);
        $this->assertSame('', $result['jobid']);
        $this->assertStringContainsString('Stored coaching.', $result['html']);
    }

    public function test_a_changed_submission_is_analysed_again(): void {
        presubmission_feedback_storage::create_record(
            (int) $this->student->id,
            'mod_assign',
            'submission',
            (int) $this->submission->id,
            '<p>Old coaching.</p>',
            (int) $this->submission->timemodified - 60,
            (int) $this->submission->attemptnumber
        );
        $this->use_mocked_api();

        $result = mod_assign_pre_submission::execute((int) $this->assign->cmid);

        $this->assertSame('pending', $result['status']);
        $this->assertCount(1, $this->posted);
    }

    public function test_authorship_check_stops_once_the_hourly_limit_is_reached(): void {
        $this->register_earlier_jobs(assign_access::STUDENT_JOBS_PER_HOUR);
        $this->use_mocked_api();

        $result = check_authorship::execute('mod_assign', 'submission', (int) $this->assign->cmid);

        $this->assertSame('failed', $result['status']);
        $this->assertSame(get_string('error:job_quota_exceeded', 'local_dixeo'), $result['errormessage']);
        $this->assertSame([], $this->posted);
    }

    public function test_authorship_check_reserves_room_for_the_quiz(): void {
        set_config('verification_test', 1, 'plagiarism_dixeo');
        $this->register_earlier_jobs(assign_access::STUDENT_JOBS_PER_HOUR - 1);
        $this->use_mocked_api();

        $result = check_authorship::execute('mod_assign', 'submission', (int) $this->assign->cmid);

        $this->assertSame('failed', $result['status']);
        $this->assertSame(get_string('error:job_quota_exceeded', 'local_dixeo'), $result['errormessage']);
        $this->assertSame([], $this->posted);
    }

    public function test_authorship_check_sends_both_steps_when_they_fit(): void {
        set_config('verification_test', 1, 'plagiarism_dixeo');
        $this->register_earlier_jobs(assign_access::STUDENT_JOBS_PER_HOUR - 2);
        $this->use_mocked_api();

        $result = check_authorship::execute('mod_assign', 'submission', (int) $this->assign->cmid);

        $this->assertSame('completed', $result['status'], $result['errormessage']);
        $this->assertGreaterThan(0, $result['record_id']);
        $this->assertSame(
            [assign_ux_service::ENDPOINT_AUTHORSHIP_CONFIDENCE, assign_ux_service::ENDPOINT_AUTHORSHIP_QUIZ],
            $this->posted
        );
    }
}
