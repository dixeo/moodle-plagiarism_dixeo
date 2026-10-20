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
 * Tests for the server-side verification quiz clock and payload validation.
 *
 * @package    plagiarism_dixeo
 * @category   test
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo;

use plagiarism_dixeo\authorship\author_storage;
use plagiarism_dixeo\authorship\quiz_session;

/**
 * The quiz clock runs on the server and payloads from the browser are not trusted.
 *
 * @covers \plagiarism_dixeo\authorship\quiz_session
 */
final class quiz_session_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    /**
     * Create an authorship record carrying a two-question quiz.
     *
     * @param array $overrides Fields to override on the stored record.
     * @return \stdClass
     */
    private function create_quiz_record(array $overrides = []): \stdClass {
        $record = author_storage::create_record(7, 'mod_assign', 'submission', 42, [
            'initial_confidence' => 55.0,
            'quiz_data' => [
                'questions' => [
                    ['id' => 'q1', 'type' => 'true_false', 'text' => 'One?', 'correct' => 'true'],
                    ['id' => 'q2', 'type' => 'open', 'text' => 'Two?'],
                ],
            ],
        ]);

        if ($overrides) {
            author_storage::update_record((int) $record->id, $overrides);
            $record = author_storage::get_record((int) $record->id);
        }

        return $record;
    }

    public function test_an_empty_answer_map_is_stored_as_a_json_object(): void {
        $record = $this->create_quiz_record(['quiz_responses' => []]);

        $this->assertSame('{}', $record->quiz_responses);
    }

    public function test_correct_answers_never_leave_the_server(): void {
        $record = $this->create_quiz_record();

        $questions = quiz_session::get_public_questions($record);

        $this->assertCount(2, $questions);
        foreach ($questions as $question) {
            $this->assertArrayNotHasKey('correct', $question);
            $this->assertArrayHasKey('timelimit', $question);
        }
        $this->assertSame('q1', $questions[0]['id']);
    }

    public function test_starting_twice_resumes_rather_than_resets_the_clock(): void {
        $record = $this->create_quiz_record();

        [$first, $juststarted] = quiz_session::start($record);
        $this->assertTrue($juststarted);
        $this->assertGreaterThan(0, $first['started_at']);

        [$second, $again] = quiz_session::start($record);
        $this->assertFalse($again);
        $this->assertSame($first['started_at'], $second['started_at']);
        $this->assertSame($first['expires_at'], $second['expires_at']);
    }

    public function test_the_clock_keeps_running_while_the_page_is_closed(): void {
        $record = $this->create_quiz_record();
        quiz_session::start($record);

        // Reload the record as a fresh page request would.
        $reloaded = author_storage::get_record((int) $record->id);
        $state = quiz_session::load($reloaded);

        $this->assertSame(quiz_session::TOTAL_TIME_LIMIT, $state['expires_at'] - $state['started_at']);
        $this->assertLessThanOrEqual(quiz_session::TOTAL_TIME_LIMIT, quiz_session::remaining_seconds($state));
        $this->assertFalse(quiz_session::is_expired($state));
    }

    public function test_a_quiz_past_its_deadline_is_expired(): void {
        $record = $this->create_quiz_record();
        $state = ['started_at' => time() - 600, 'expires_at' => time() - 300, 'current_index' => 0, 'reported' => []];

        $this->assertTrue(quiz_session::is_expired($state));
        $this->assertSame(0, quiz_session::remaining_seconds($state));
    }

    public function test_the_quiz_uses_the_fixed_time_limit(): void {
        set_config('quiz_total_time', 0, 'plagiarism_dixeo');
        $record = $this->create_quiz_record();

        [$state] = quiz_session::start($record);

        $this->assertSame(quiz_session::TOTAL_TIME_LIMIT, $state['expires_at'] - $state['started_at']);
        $this->assertFalse(quiz_session::allows_backwards_navigation());
        $this->assertSame(0, quiz_session::get_next_button_delay());
    }

    public function test_progress_is_merged_so_a_partial_save_keeps_earlier_answers(): void {
        $record = $this->create_quiz_record();

        quiz_session::record_progress($record, ['q1' => 'true'], ['q1' => 12], 1);
        quiz_session::record_progress($record, ['q2' => 'Because.'], ['q2' => 30], 2);

        $reloaded = author_storage::get_record((int) $record->id);
        $this->assertSame(['q1' => 'true', 'q2' => 'Because.'], quiz_session::get_answers($reloaded));

        $state = quiz_session::load($reloaded);
        $this->assertSame(['q1' => 12, 'q2' => 30], $state['reported']);
        $this->assertSame(2, $state['current_index']);
        $this->assertSame(author_storage::STATUS_VERIFICATION_IN_PROGRESS, $reloaded->status);
    }

    public function test_answers_for_questions_that_were_not_asked_are_discarded(): void {
        $accepted = quiz_session::validate_answers(
            ['q1' => 'true', 'injected' => 'value'],
            ['q1', 'q2']
        );

        $this->assertSame(['q1' => 'true'], $accepted);
    }

    public function test_a_non_scalar_answer_is_rejected(): void {
        $this->expectException(\moodle_exception::class);
        quiz_session::validate_answers(['q1' => ['nested' => 'array']], ['q1']);
    }

    public function test_an_overlong_answer_is_rejected(): void {
        $this->expectException(\moodle_exception::class);
        quiz_session::validate_answers(
            ['q1' => str_repeat('a', quiz_session::MAX_ANSWER_LENGTH + 1)],
            ['q1']
        );
    }

    public function test_malformed_json_is_rejected(): void {
        $this->expectException(\moodle_exception::class);
        quiz_session::decode_payload('{"q1": ', 'responses');
    }

    public function test_a_json_scalar_is_not_accepted_as_a_payload(): void {
        $this->expectException(\moodle_exception::class);
        quiz_session::decode_payload('"just a string"', 'responses');
    }

    public function test_an_oversized_payload_is_rejected(): void {
        $this->expectException(\moodle_exception::class);
        quiz_session::decode_payload(str_repeat('a', quiz_session::MAX_PAYLOAD_BYTES + 1), 'responses');
    }

    public function test_an_empty_payload_decodes_to_nothing(): void {
        $this->assertSame([], quiz_session::decode_payload('', 'responses'));
        $this->assertSame([], quiz_session::decode_payload('{}', 'responses'));
    }

    public function test_reported_times_are_clamped_to_the_quiz_length(): void {
        $accepted = quiz_session::validate_timings(
            ['q1' => -5, 'q2' => 99999, 'q3' => 30, 'q1extra' => 'nonsense'],
            ['q1', 'q2', 'q3']
        );

        $this->assertSame(['q1' => 0, 'q2' => quiz_session::TOTAL_TIME_LIMIT, 'q3' => 30], $accepted);
    }

    public function test_removed_timer_settings_do_not_change_the_fixed_limits(): void {
        set_config('quiz_total_time', 999999, 'plagiarism_dixeo');
        set_config('next_button_delay_seconds', -4, 'plagiarism_dixeo');

        $this->assertSame(quiz_session::TOTAL_TIME_LIMIT, quiz_session::get_total_time_limit());
        $this->assertSame(0, quiz_session::get_next_button_delay());
    }

    public function test_starting_records_when_the_first_question_was_opened(): void {
        set_config('quiz_time_true_false', 60, 'plagiarism_dixeo');
        $record = $this->create_quiz_record();

        [$state] = quiz_session::start($record);

        $this->assertArrayHasKey('q1', $state['question_started_at']);
        $this->assertSame($state['started_at'], $state['question_started_at']['q1']);
        $this->assertLessThanOrEqual(60, quiz_session::question_remaining_seconds($state, $record));
    }

    public function test_reloading_does_not_reset_the_question_clock(): void {
        set_config('quiz_time_true_false', 60, 'plagiarism_dixeo');
        $record = $this->create_quiz_record();
        $opened = time() - 25;
        quiz_session::save($record, [
            'started_at' => $opened,
            'expires_at' => $opened + 300,
            'current_index' => 0,
            'reported' => [],
            'question_started_at' => ['q1' => $opened],
        ]);

        $reloaded = author_storage::get_record((int) $record->id);
        $state = quiz_session::load($reloaded);
        $remaining = quiz_session::question_remaining_seconds($state, $reloaded);

        $this->assertSame($opened, $state['question_started_at']['q1']);
        $this->assertGreaterThanOrEqual(30, $remaining);
        $this->assertLessThanOrEqual(35, $remaining);
    }

    public function test_landing_on_a_later_question_does_not_reset_earlier_start_times(): void {
        $record = $this->create_quiz_record();
        quiz_session::start($record);
        $first = quiz_session::load($record);

        quiz_session::record_progress($record, ['q1' => 'true'], ['q1' => 12], 1);
        $state = quiz_session::load(author_storage::get_record((int) $record->id));

        $this->assertSame($first['question_started_at']['q1'], $state['question_started_at']['q1']);
        $this->assertArrayHasKey('q2', $state['question_started_at']);
        $this->assertGreaterThan(0, $state['question_started_at']['q2']);
        $this->assertSame(1, $state['current_index']);
    }

    public function test_each_question_type_has_its_own_pacing_limit(): void {
        set_config('quiz_time_true_false', 15, 'plagiarism_dixeo');
        set_config('quiz_time_multiple_choice', 25, 'plagiarism_dixeo');
        set_config('quiz_time_open', 90, 'plagiarism_dixeo');

        $this->assertSame(quiz_session::QUESTION_TIME_TRUE_FALSE, quiz_session::get_question_time_limit('true_false'));
        $this->assertSame(
            quiz_session::QUESTION_TIME_MULTIPLE_CHOICE,
            quiz_session::get_question_time_limit('multiple_choice')
        );
        $this->assertSame(quiz_session::QUESTION_TIME_OPEN, quiz_session::get_question_time_limit('open'));
        $this->assertSame(quiz_session::QUESTION_TIME_OPEN, quiz_session::get_question_time_limit('unknown_type'));
    }
}
