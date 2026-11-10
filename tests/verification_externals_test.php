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
 * Tests for the authorship verification quiz endpoints.
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
use plagiarism_dixeo\authorship\quiz_session;
use plagiarism_dixeo\external\check_authorship;
use plagiarism_dixeo\external\get_verification_quiz;
use plagiarism_dixeo\external\save_verification_progress;
use plagiarism_dixeo\external\start_verification;
use plagiarism_dixeo\external\verify_authorship;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/plagiarism/dixeo/lib.php');
require_once($CFG->dirroot . '/plagiarism/dixeo/tests/fixtures/race_lock_factory.php');

/**
 * The verification endpoints only serve the student who owns the quiz, and only while it is open.
 *
 * @covers \plagiarism_dixeo\external\start_verification
 * @covers \plagiarism_dixeo\external\get_verification_quiz
 * @covers \plagiarism_dixeo\external\save_verification_progress
 * @covers \plagiarism_dixeo\external\verification_access
 * @covers \plagiarism_dixeo\external\verify_authorship
 * @covers \plagiarism_dixeo\external\check_authorship
 * @covers \plagiarism_dixeo\authorship\quiz_session::record_progress
 * @covers \plagiarism_dixeo\authorship\quiz_session::accepted_answers
 */
final class verification_externals_test extends \advanced_testcase {
    /** @var \stdClass Course holding the assignment under test. */
    private $course;

    /** @var \stdClass Assignment the quiz belongs to. */
    private $assign;

    /** @var \stdClass Student who owns the quiz. */
    private $student;

    /** @var \stdClass Authorship record carrying the quiz. */
    private $record;

    public function setUp(): void {
        global $CFG, $DB;

        parent::setUp();
        $this->resetAfterTest(true);

        $CFG->enableplagiarism = 1;
        set_config('assignment_feedback_enabled', 1, 'plagiarism_dixeo');
        set_config('authorship_enabled', 1, 'plagiarism_dixeo');
        set_config('grading_enabled', 1, 'plagiarism_dixeo');

        $this->course = $this->getDataGenerator()->create_course();
        $this->assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $this->course->id,
            'assignsubmission_onlinetext_enabled' => 1,
        ]);
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->getDataGenerator()->get_plugin_generator('local_dixeo')
            ->create_terms_acceptance(['userid' => (int) $this->student->id]);

        $now = time();
        $submissionid = $DB->insert_record('assign_submission', (object) [
            'assignment' => $this->assign->id,
            'userid' => $this->student->id,
            'timecreated' => $now,
            'timemodified' => $now,
            'status' => 'draft',
            'groupid' => 0,
            'attemptnumber' => 0,
            'latest' => 1,
        ]);

        $this->record = author_storage::create_record(
            (int) $this->student->id,
            'mod_assign',
            'submission',
            (int) $submissionid,
            [
                'initial_confidence' => 40.0,
                'quiz_data' => [
                    'questions' => [
                        ['id' => 'q1', 'type' => 'true_false', 'text' => 'One?', 'correct' => 'true'],
                        ['id' => 'q2', 'type' => 'open', 'text' => 'Two?'],
                    ],
                ],
            ]
        );
    }

    protected function tearDown(): void {
        \plagiarism_dixeo\tests\race_lock_factory::before_lock(null, null);
        service_factory::reset();
        parent::tearDown();
    }

    /**
     * Use database row locks, which exclude each other within one process.
     *
     * @param callable|null $beforelock Change made by "another session" just before this quiz's record lock is taken.
     */
    private function use_row_locks(?callable $beforelock = null): void {
        global $CFG;

        if ($beforelock === null) {
            $CFG->lock_factory = '\\core\\lock\\db_record_lock_factory';
            return;
        }
        $CFG->lock_factory = '\\' . \plagiarism_dixeo\tests\race_lock_factory::class;
        \plagiarism_dixeo\tests\race_lock_factory::before_lock('authorship_' . (int) $this->record->id, $beforelock);
    }

    /**
     * Mark the quiz finished, as a final evaluation in another session would.
     */
    private function complete_quiz(): void {
        author_storage::update_record((int) $this->record->id, [
            'status' => author_storage::STATUS_COMPLETE,
            'final_confidence' => 70.0,
        ]);
    }

    /**
     * Use a service that must not be asked for anything.
     */
    private function forbid_sends(): void {
        $mock = $this->createMock(assign_ux_service::class);
        $mock->expects($this->never())->method('submit_authorship_and_wait');
        $mock->expects($this->never())->method('submit_authorship');
        service_factory::set_test_assign_ux_service($mock);
    }

    /**
     * Use a service whose final evaluation returns the given confidence.
     *
     * @param float $confidence Final confidence.
     */
    private function mock_final_evaluation(float $confidence): void {
        $mock = $this->createMock(assign_ux_service::class);
        $mock->expects($this->once())
            ->method('submit_authorship_and_wait')
            ->willReturn(new operation_result(true, 'job-final', ['final_confidence' => $confidence]));
        $mock->method('map_authorship_result')->willReturn([
            'final_confidence' => $confidence,
            'grade' => null,
            'explanation' => '',
            'quiz_explanation' => '',
            'question_feedbacks' => [],
        ]);
        $mock->method('apply_authorship_final')->willReturnCallback(
            function (int $recordid, array $responses, float $final): \stdClass {
                author_storage::update_record($recordid, [
                    'status' => author_storage::STATUS_COMPLETE,
                    'quiz_responses' => $responses,
                    'final_confidence' => $final,
                ]);
                return author_storage::get_record($recordid);
            }
        );
        service_factory::set_test_assign_ux_service($mock);
    }

    /**
     * Move the quiz deadline into the past.
     */
    private function expire_quiz(): void {
        $record = author_storage::get_record((int) $this->record->id);
        quiz_session::save($record, [
            'started_at' => time() - 600,
            'expires_at' => time() - 300,
            'current_index' => 0,
            'reported' => [],
        ]);
    }

    public function test_starting_the_quiz_records_the_deadline_and_the_event(): void {
        $this->setUser($this->student);

        $sink = $this->redirectEvents();
        $result = start_verification::execute((int) $this->record->id);
        $events = $sink->get_events();

        $this->assertFalse($result['expired']);
        $this->assertSame(quiz_session::TOTAL_TIME_LIMIT, $result['expires_at'] - $result['started_at']);
        $this->assertGreaterThan(0, $result['remaining']);
        $this->assertGreaterThan(0, $result['question_remaining']);

        $started = array_filter($events, fn($event) => $event instanceof \plagiarism_dixeo\event\verification_started);
        $this->assertCount(1, $started);

        $stored = author_storage::get_record((int) $this->record->id);
        $this->assertSame(author_storage::STATUS_VERIFICATION_IN_PROGRESS, $stored->status);
    }

    public function test_reloading_the_page_does_not_give_the_student_more_time(): void {
        $this->setUser($this->student);

        $first = start_verification::execute((int) $this->record->id);
        $second = start_verification::execute((int) $this->record->id);

        $this->assertSame($first['expires_at'], $second['expires_at']);
    }

    public function test_the_quiz_is_returned_without_its_correct_answers(): void {
        $this->setUser($this->student);
        start_verification::execute((int) $this->record->id);

        $result = get_verification_quiz::execute((int) $this->record->id);
        $questions = json_decode($result['questions'], true);

        $this->assertCount(2, $questions);
        $this->assertStringNotContainsString('correct', $result['questions']);
        $this->assertArrayNotHasKey('correct', $questions[0]);
        $this->assertSame('{}', $result['answers']);
    }

    public function test_saved_answers_come_back_so_the_student_can_resume(): void {
        $this->setUser($this->student);
        start_verification::execute((int) $this->record->id);

        save_verification_progress::execute(
            (int) $this->record->id,
            json_encode(['q1' => 'true']),
            json_encode(['q1' => 8]),
            1
        );

        $result = get_verification_quiz::execute((int) $this->record->id);

        $this->assertSame(['q1' => 'true'], json_decode($result['answers'], true));
        $this->assertSame(1, $result['current_index']);
        $this->assertGreaterThan(0, $result['remaining']);
        $this->assertGreaterThan(0, $result['question_remaining']);
    }

    public function test_saving_progress_on_a_later_question_starts_its_clock(): void {
        $this->setUser($this->student);
        start_verification::execute((int) $this->record->id);

        save_verification_progress::execute(
            (int) $this->record->id,
            json_encode(['q1' => 'true']),
            json_encode(['q1' => 8]),
            1
        );

        $result = get_verification_quiz::execute((int) $this->record->id);
        $stored = author_storage::get_record((int) $this->record->id);
        $state = quiz_session::load($stored);

        $this->assertSame(1, $result['current_index']);
        $this->assertArrayHasKey('q1', $state['question_started_at']);
        $this->assertArrayHasKey('q2', $state['question_started_at']);
        $this->assertGreaterThan(0, $state['question_started_at']['q2']);
    }

    public function test_nothing_is_saved_once_the_deadline_has_passed(): void {
        $this->setUser($this->student);
        start_verification::execute((int) $this->record->id);
        $this->expire_quiz();

        $result = save_verification_progress::execute(
            (int) $this->record->id,
            json_encode(['q1' => 'true'])
        );

        $this->assertTrue($result['expired']);
        $this->assertFalse($result['saved']);
        $this->assertSame(0, $result['remaining']);

        $stored = author_storage::get_record((int) $this->record->id);
        $this->assertSame([], quiz_session::get_answers($stored));
    }

    public function test_another_student_cannot_open_the_quiz(): void {
        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->setUser($other);

        $this->expectException(\moodle_exception::class);
        get_verification_quiz::execute((int) $this->record->id);
    }

    public function test_a_teacher_cannot_open_a_students_quiz(): void {
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->setUser($teacher);

        $this->expectException(\moodle_exception::class);
        start_verification::execute((int) $this->record->id);
    }

    public function test_a_user_outside_the_course_cannot_open_the_quiz(): void {
        $outsider = $this->getDataGenerator()->create_user();
        $this->setUser($outsider);

        $this->expectException(\moodle_exception::class);
        get_verification_quiz::execute((int) $this->record->id);
    }

    public function test_an_unknown_record_is_refused(): void {
        $this->setUser($this->student);

        $this->expectException(\moodle_exception::class);
        start_verification::execute((int) $this->record->id + 1000);
    }

    public function test_a_finished_quiz_cannot_be_reopened(): void {
        $this->setUser($this->student);
        author_storage::update_record((int) $this->record->id, [
            'status' => author_storage::STATUS_COMPLETE,
        ]);

        $this->expectException(\moodle_exception::class);
        start_verification::execute((int) $this->record->id);
    }

    public function test_malformed_answers_are_refused(): void {
        $this->setUser($this->student);
        start_verification::execute((int) $this->record->id);

        $this->expectException(\moodle_exception::class);
        save_verification_progress::execute((int) $this->record->id, '{"q1": ');
    }

    public function test_answers_cannot_be_saved_before_the_clock_starts(): void {
        $this->setUser($this->student);

        try {
            save_verification_progress::execute((int) $this->record->id, json_encode(['q1' => 'true']));
            $this->fail('Answers before the quiz starts must be refused.');
        } catch (\moodle_exception $e) {
            $this->assertSame('quiz_not_started', $e->errorcode);
        }
        $this->assertSame([], quiz_session::get_answers(author_storage::get_record((int) $this->record->id)));
    }

    public function test_a_quiz_cannot_be_evaluated_before_the_clock_starts(): void {
        $this->setUser($this->student);
        $this->forbid_sends();

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('quiz_not_started', 'plagiarism_dixeo'));
        verify_authorship::execute((int) $this->record->id, json_encode(['q1' => 'true', 'q2' => 'Mine']));
    }

    public function test_the_questions_are_not_served_before_the_clock_starts(): void {
        $this->setUser($this->student);

        $result = null;
        try {
            $result = get_verification_quiz::execute((int) $this->record->id);
            $this->fail('The questions must not be served before the quiz starts.');
        } catch (\moodle_exception $e) {
            $this->assertSame('quiz_not_started', $e->errorcode);
        }
        $this->assertNull($result);

        $stored = author_storage::get_record((int) $this->record->id);
        $this->assertSame(0, quiz_session::load($stored)['started_at']);
        $this->assertSame(author_storage::STATUS_VERIFICATION_REQUIRED, $stored->status);
    }

    public function test_the_student_cannot_go_back_to_an_earlier_question(): void {
        $this->setUser($this->student);
        start_verification::execute((int) $this->record->id);
        save_verification_progress::execute((int) $this->record->id, json_encode(['q1' => 'true']), '{}', 1);

        $result = save_verification_progress::execute(
            (int) $this->record->id,
            json_encode(['q1' => 'false', 'q2' => 'Second']),
            '{}',
            0
        );

        $this->assertSame(1, $result['current_index']);
        $stored = author_storage::get_record((int) $this->record->id);
        $this->assertSame(['q1' => 'true', 'q2' => 'Second'], quiz_session::get_answers($stored));
    }

    public function test_an_index_past_the_last_question_is_ignored(): void {
        $this->setUser($this->student);
        start_verification::execute((int) $this->record->id);

        $result = save_verification_progress::execute((int) $this->record->id, '{}', '{}', 7);

        $this->assertSame(0, $result['current_index']);
    }

    public function test_an_answer_after_the_question_time_limit_is_ignored(): void {
        $this->setUser($this->student);
        start_verification::execute((int) $this->record->id);
        $record = author_storage::get_record((int) $this->record->id);
        $state = quiz_session::load($record);
        $state['question_started_at']['q1'] = time() - quiz_session::QUESTION_TIME_TRUE_FALSE
            - quiz_session::EXPIRY_GRACE_SECONDS - 1;
        quiz_session::save($record, $state);

        save_verification_progress::execute((int) $this->record->id, json_encode(['q1' => 'true']), '{}', 0);

        $this->assertSame([], quiz_session::get_answers(author_storage::get_record((int) $this->record->id)));
    }

    public function test_a_finished_quiz_cannot_be_evaluated_again(): void {
        $this->setUser($this->student);
        start_verification::execute((int) $this->record->id);
        $this->mock_final_evaluation(80.0);
        verify_authorship::execute((int) $this->record->id, json_encode(['q1' => 'true']));
        $this->assertSame(80.0, (float) author_storage::get_record((int) $this->record->id)->final_confidence);

        $this->forbid_sends();
        try {
            verify_authorship::execute((int) $this->record->id, json_encode(['q1' => 'false']));
            $this->fail('A finished quiz must not be evaluated again.');
        } catch (\moodle_exception $e) {
            $this->assertSame('quiz_not_available', $e->errorcode);
        }
        $this->assertSame(80.0, (float) author_storage::get_record((int) $this->record->id)->final_confidence);
    }

    public function test_a_quiz_being_evaluated_elsewhere_is_refused(): void {
        global $CFG;

        // Locks taken in one process must exclude each other, which the database row factory guarantees.
        $CFG->lock_factory = '\\core\\lock\\db_record_lock_factory';
        $this->setUser($this->student);
        start_verification::execute((int) $this->record->id);
        $this->forbid_sends();
        $held = authorship::acquire_lock('authorship_' . (int) $this->record->id);
        $this->assertNotNull($held);

        try {
            verify_authorship::execute((int) $this->record->id, json_encode(['q1' => 'true']));
            $this->fail('A second evaluation of the same quiz must be refused.');
        } catch (\moodle_exception $e) {
            $this->assertSame('quiz_not_available', $e->errorcode);
        } finally {
            $held->release();
        }
    }

    public function test_a_progress_write_from_a_stale_copy_keeps_a_finished_status(): void {
        $this->setUser($this->student);
        start_verification::execute((int) $this->record->id);
        $stale = author_storage::get_record((int) $this->record->id);
        $this->complete_quiz();

        quiz_session::record_progress($stale, ['q1' => 'true'], ['q1' => 4], 0);

        $stored = author_storage::get_record((int) $this->record->id);
        $this->assertSame(author_storage::STATUS_COMPLETE, $stored->status);
        $this->assertSame(70.0, (float) $stored->final_confidence);
    }

    public function test_progress_is_not_saved_while_the_quiz_is_locked_elsewhere(): void {
        $this->use_row_locks();
        $this->setUser($this->student);
        start_verification::execute((int) $this->record->id);
        $before = author_storage::get_record((int) $this->record->id);
        $held = authorship::acquire_lock('authorship_' . (int) $this->record->id);
        $this->assertNotNull($held);

        try {
            $result = save_verification_progress::execute(
                (int) $this->record->id,
                json_encode(['q1' => 'true']),
                json_encode(['q1' => 4]),
                1
            );
        } finally {
            $held->release();
        }

        $this->assertFalse($result['saved']);
        $after = author_storage::get_record((int) $this->record->id);
        $this->assertSame($before->quiz_responses, $after->quiz_responses);
        $this->assertSame($before->response_timings, $after->response_timings);
        $this->assertSame($before->timemodified, $after->timemodified);
    }

    public function test_progress_is_refused_when_the_quiz_finishes_before_the_lock(): void {
        $this->setUser($this->student);
        start_verification::execute((int) $this->record->id);
        $this->use_row_locks(fn() => $this->complete_quiz());

        try {
            save_verification_progress::execute((int) $this->record->id, json_encode(['q1' => 'true']), '{}', 1);
            $this->fail('A quiz finished by another session must not take more answers.');
        } catch (\moodle_exception $e) {
            $this->assertSame('quiz_not_available', $e->errorcode);
        }

        $stored = author_storage::get_record((int) $this->record->id);
        $this->assertSame(author_storage::STATUS_COMPLETE, $stored->status);
        $this->assertSame([], quiz_session::get_answers($stored));
    }

    public function test_the_quiz_is_not_started_while_it_is_locked_elsewhere(): void {
        $this->use_row_locks();
        $this->setUser($this->student);
        $held = authorship::acquire_lock('authorship_' . (int) $this->record->id);
        $this->assertNotNull($held);

        $sink = $this->redirectEvents();
        try {
            $result = start_verification::execute((int) $this->record->id);
        } finally {
            $held->release();
        }
        $events = $sink->get_events();

        $this->assertSame(0, $result['started_at']);
        $stored = author_storage::get_record((int) $this->record->id);
        $this->assertSame(0, quiz_session::load($stored)['started_at']);
        $this->assertSame(author_storage::STATUS_VERIFICATION_REQUIRED, $stored->status);
        $started = array_filter($events, fn($event) => $event instanceof \plagiarism_dixeo\event\verification_started);
        $this->assertCount(0, $started);
    }

    public function test_a_quiz_finished_before_the_start_lock_is_not_reopened(): void {
        $this->setUser($this->student);
        $this->use_row_locks(fn() => $this->complete_quiz());

        $sink = $this->redirectEvents();
        try {
            start_verification::execute((int) $this->record->id);
            $this->fail('A quiz finished by another session must not be started.');
        } catch (\moodle_exception $e) {
            $this->assertSame('quiz_not_available', $e->errorcode);
        }
        $events = $sink->get_events();

        $stored = author_storage::get_record((int) $this->record->id);
        $this->assertSame(author_storage::STATUS_COMPLETE, $stored->status);
        $this->assertSame(0, quiz_session::load($stored)['started_at']);
        $started = array_filter($events, fn($event) => $event instanceof \plagiarism_dixeo\event\verification_started);
        $this->assertCount(0, $started);
    }

    public function test_a_settled_result_is_returned_without_a_new_check(): void {
        author_storage::update_record((int) $this->record->id, [
            'status' => author_storage::STATUS_COMPLETE,
            'final_confidence' => 35.0,
        ]);
        $this->setUser($this->student);
        $this->forbid_sends();

        $result = check_authorship::execute('mod_assign', 'submission', (int) $this->assign->cmid);

        $this->assertSame('completed', $result['status']);
        $this->assertSame(35.0, $result['score']);
        $this->assertSame(0, $result['record_id']);
        $this->assertSame(35.0, (float) author_storage::get_record((int) $this->record->id)->final_confidence);
    }

    public function test_a_check_already_running_for_the_submission_sends_nothing(): void {
        global $CFG;

        // Locks taken in one process must exclude each other, which the database row factory guarantees.
        $CFG->lock_factory = '\\core\\lock\\db_record_lock_factory';
        author_storage::update_record((int) $this->record->id, ['status' => author_storage::STATUS_FAILED]);
        $this->setUser($this->student);
        $this->forbid_sends();
        $held = authorship::acquire_lock('authorship_submission_' . (int) $this->record->itemid);
        $this->assertNotNull($held);

        try {
            $result = check_authorship::execute('mod_assign', 'submission', (int) $this->assign->cmid);
        } finally {
            $held->release();
        }

        $this->assertSame('failed', $result['status']);
        $this->assertSame(get_string('assign_ux_result_missing', 'local_dixeo'), $result['errormessage']);
    }
}
