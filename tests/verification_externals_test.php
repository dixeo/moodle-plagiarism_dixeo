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

use plagiarism_dixeo\authorship\author_storage;
use plagiarism_dixeo\authorship\quiz_session;
use plagiarism_dixeo\external\get_verification_quiz;
use plagiarism_dixeo\external\save_verification_progress;
use plagiarism_dixeo\external\start_verification;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/plagiarism/dixeo/lib.php');

/**
 * The verification endpoints only serve the student who owns the quiz, and only while it is open.
 *
 * @covers \plagiarism_dixeo\external\start_verification
 * @covers \plagiarism_dixeo\external\get_verification_quiz
 * @covers \plagiarism_dixeo\external\save_verification_progress
 * @covers \plagiarism_dixeo\external\verification_access
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
        set_config('grading_enabled', 1, 'plagiarism_dixeo');

        $this->course = $this->getDataGenerator()->create_course();
        $this->assign = $this->getDataGenerator()->create_module('assign', ['course' => $this->course->id]);
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');

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
}
