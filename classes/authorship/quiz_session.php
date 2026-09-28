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
 * Server-side state of an authorship verification quiz.
 *
 * @package    plagiarism_dixeo
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo\authorship;

/**
 * Owns the clock and the saved answers for a verification quiz.
 *
 * The deadline is computed from a start time recorded on the server, so closing the page
 * does not stop or reset it, and answers arriving after the deadline are refused. Times
 * reported by the browser are stored for the evaluation but never decide whether the quiz
 * is still open.
 */
class quiz_session {
    /** @var int Largest accepted JSON payload from the browser, in bytes. */
    public const MAX_PAYLOAD_BYTES = 65536;

    /** @var int Largest accepted answer to a single question, in characters. */
    public const MAX_ANSWER_LENGTH = 5000;

    /** @var int Largest accepted number of questions in one payload. */
    public const MAX_QUESTIONS = 50;

    /** @var int Grace period allowed for a request that was in flight as the clock ran out. */
    public const EXPIRY_GRACE_SECONDS = 5;

    /** @var int Longest accepted quiz time limit, in seconds. */
    public const MAX_TIME_LIMIT = 2 * HOURSECS;

    /** @var int Longest accepted delay on the next button, in seconds. */
    public const MAX_NEXT_BUTTON_DELAY = 60;

    /**
     * Decode a JSON payload sent by the browser.
     *
     * @param string $json Raw payload.
     * @param string $field Field name, used in the error message.
     * @return array Decoded payload, always an array.
     * @throws \moodle_exception When the payload is too large or is not a JSON object.
     */
    public static function decode_payload(string $json, string $field): array {
        if ($json === '') {
            return [];
        }
        if (strlen($json) > self::MAX_PAYLOAD_BYTES) {
            throw new \moodle_exception('quiz_payload_too_large', 'plagiarism_dixeo', '', $field);
        }

        try {
            $decoded = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \moodle_exception('quiz_payload_invalid', 'plagiarism_dixeo', '', $field);
        }

        if (!is_array($decoded)) {
            throw new \moodle_exception('quiz_payload_invalid', 'plagiarism_dixeo', '', $field);
        }
        if (count($decoded) > self::MAX_QUESTIONS) {
            throw new \moodle_exception('quiz_payload_too_large', 'plagiarism_dixeo', '', $field);
        }

        return $decoded;
    }

    /**
     * Check a set of answers against the questions actually asked.
     *
     * @param array $answers Decoded answers, keyed by question id.
     * @param array $questionids Ids of the questions in this quiz.
     * @return array Accepted answers, keyed by question id.
     * @throws \moodle_exception When an answer is not a scalar or is too long.
     */
    public static function validate_answers(array $answers, array $questionids): array {
        $accepted = [];

        foreach ($answers as $questionid => $answer) {
            if (!in_array((string) $questionid, $questionids, true)) {
                continue;
            }
            if (is_bool($answer) || is_int($answer) || is_float($answer)) {
                $answer = (string) $answer;
            }
            if (!is_string($answer)) {
                throw new \moodle_exception('quiz_payload_invalid', 'plagiarism_dixeo', '', 'responses');
            }
            if (\core_text::strlen($answer) > self::MAX_ANSWER_LENGTH) {
                throw new \moodle_exception('quiz_payload_too_large', 'plagiarism_dixeo', '', 'responses');
            }
            $accepted[(string) $questionid] = $answer;
        }

        return $accepted;
    }

    /**
     * Check the per-question times reported by the browser.
     *
     * @param array $timings Decoded timings, keyed by question id.
     * @param array $questionids Ids of the questions in this quiz.
     * @return array<string, int> Accepted times in seconds, keyed by question id.
     */
    public static function validate_timings(array $timings, array $questionids): array {
        $accepted = [];
        $max = self::get_total_time_limit() ?: self::MAX_TIME_LIMIT;

        foreach ($timings as $questionid => $seconds) {
            if (!in_array((string) $questionid, $questionids, true) || !is_numeric($seconds)) {
                continue;
            }
            $accepted[(string) $questionid] = (int) max(0, min((int) $seconds, $max));
        }

        return $accepted;
    }

    /**
     * Read the timing state held against a record.
     *
     * @param \stdClass $record Authorship record.
     * @return array State with started_at, expires_at, current_index and reported times.
     */
    public static function load(\stdClass $record): array {
        $state = json_decode((string) ($record->response_timings ?? ''), true);
        if (!is_array($state)) {
            $state = [];
        }

        return [
            'started_at' => isset($state['started_at']) ? (int) $state['started_at'] : 0,
            'expires_at' => isset($state['expires_at']) ? (int) $state['expires_at'] : 0,
            'current_index' => isset($state['current_index']) ? (int) $state['current_index'] : 0,
            'reported' => isset($state['reported']) && is_array($state['reported']) ? $state['reported'] : [],
            'question_started_at' => self::normalize_question_started_at($state['question_started_at'] ?? []),
        ];
    }

    /**
     * Start the clock, or return the state of a quiz already under way.
     *
     * @param \stdClass $record Authorship record.
     * @return array{0: array, 1: bool} The state and whether this call started the quiz.
     */
    public static function start(\stdClass $record): array {
        $state = self::load($record);
        if ($state['started_at'] > 0) {
            return [$state, false];
        }

        $now = time();
        $total = self::get_total_time_limit();
        $state['started_at'] = $now;
        $state['expires_at'] = $total > 0 ? $now + $total : 0;
        $state = self::ensure_question_started($record, $state, (int) $state['current_index'], $now);

        self::save($record, $state);

        return [$state, true];
    }

    /**
     * Store the timing state against a record.
     *
     * @param \stdClass $record Authorship record.
     * @param array $state State to store.
     */
    public static function save(\stdClass $record, array $state): void {
        author_storage::update_record((int) $record->id, [
            'status' => $record->status,
            'response_timings' => $state,
        ]);
        $record->response_timings = json_encode($state);
    }

    /**
     * Seconds left before the quiz closes.
     *
     * @param array $state State from load().
     * @return int|null Seconds remaining, or null when there is no time limit.
     */
    public static function remaining_seconds(array $state): ?int {
        if (empty($state['expires_at'])) {
            return null;
        }

        return max(0, (int) $state['expires_at'] - time());
    }

    /**
     * Seconds left on the question the student is currently answering.
     *
     * Remaining time is derived from the server timestamp recorded when they first landed on
     * that question, so reloading the page does not give the time back.
     *
     * @param array $state State from load().
     * @param \stdClass $record Authorship record, used to read the question's time limit.
     * @return int|null Seconds remaining, or null when that question has no time limit.
     */
    public static function question_remaining_seconds(array $state, \stdClass $record): ?int {
        $questions = self::get_public_questions($record);
        $index = (int) ($state['current_index'] ?? 0);
        $question = $questions[$index] ?? null;
        if ($question === null) {
            return null;
        }

        $limit = (int) ($question['timelimit'] ?? 0);
        if ($limit <= 0) {
            return null;
        }

        $started = (int) ($state['question_started_at'][(string) $question['id']] ?? 0);
        if ($started <= 0) {
            return $limit;
        }

        return max(0, $started + $limit - time());
    }

    /**
     * Record the first time the student lands on a question, without resetting a later visit.
     *
     * @param \stdClass $record Authorship record.
     * @param array $state State from load().
     * @param int $index Question index in the quiz.
     * @param int|null $now Unix timestamp to store, or null to use time().
     * @return array Updated state.
     */
    public static function ensure_question_started(
        \stdClass $record,
        array $state,
        int $index,
        ?int $now = null
    ): array {
        $ids = self::get_question_ids($record);
        $questionid = $ids[$index] ?? null;
        if ($questionid === null) {
            return $state;
        }

        if (!isset($state['question_started_at']) || !is_array($state['question_started_at'])) {
            $state['question_started_at'] = [];
        }
        if (empty($state['question_started_at'][$questionid])) {
            $state['question_started_at'][$questionid] = $now ?? time();
        }

        return $state;
    }

    /**
     * Whether the quiz has closed.
     *
     * A short grace period covers a request that set off before the deadline.
     *
     * @param array $state State from load().
     * @return bool
     */
    public static function is_expired(array $state): bool {
        if (empty($state['expires_at']) || empty($state['started_at'])) {
            return false;
        }

        return time() > (int) $state['expires_at'] + self::EXPIRY_GRACE_SECONDS;
    }

    /**
     * The questions of a quiz with the correct answers removed.
     *
     * @param \stdClass $record Authorship record.
     * @return array Questions safe to send to the student, each with its own time limit.
     */
    public static function get_public_questions(\stdClass $record): array {
        $public = [];

        foreach (self::get_questions($record) as $index => $question) {
            if (!is_array($question)) {
                continue;
            }
            unset($question['correct'], $question['answer'], $question['explanation']);
            $question['id'] = (string) ($question['id'] ?? ('q' . $index));
            $question['type'] = (string) ($question['type'] ?? authorship::QUESTION_TYPE_OPEN);
            $question['timelimit'] = self::get_question_time_limit($question['type']);
            $public[] = $question;
        }

        return $public;
    }

    /**
     * The stored questions of a quiz, correct answers included.
     *
     * @param \stdClass $record Authorship record.
     * @return array
     */
    public static function get_questions(\stdClass $record): array {
        $quiz = json_decode((string) ($record->quiz_data ?? ''), true);

        return is_array($quiz) && is_array($quiz['questions'] ?? null) ? $quiz['questions'] : [];
    }

    /**
     * The ids of the questions in a quiz.
     *
     * @param \stdClass $record Authorship record.
     * @return string[]
     */
    public static function get_question_ids(\stdClass $record): array {
        $ids = [];
        foreach (self::get_questions($record) as $index => $question) {
            $ids[] = (string) ($question['id'] ?? ('q' . $index));
        }

        return $ids;
    }

    /**
     * The answers saved so far.
     *
     * @param \stdClass $record Authorship record.
     * @return array<string, string>
     */
    public static function get_answers(\stdClass $record): array {
        $answers = json_decode((string) ($record->quiz_responses ?? ''), true);

        return is_array($answers) ? $answers : [];
    }

    /**
     * Merge newly submitted answers and reported times into what is already saved.
     *
     * @param \stdClass $record Authorship record.
     * @param array $answers Accepted answers, keyed by question id.
     * @param array $timings Accepted times in seconds, keyed by question id.
     * @param int|null $currentindex Question the student is on, if the browser reported one.
     * @return array The updated timing state.
     */
    public static function record_progress(
        \stdClass $record,
        array $answers,
        array $timings,
        ?int $currentindex = null
    ): array {
        $state = self::load($record);
        $state['reported'] = array_merge($state['reported'], $timings);
        if ($currentindex !== null) {
            $state['current_index'] = max(0, $currentindex);
        }
        $state = self::ensure_question_started($record, $state, (int) $state['current_index']);

        $merged = array_merge(self::get_answers($record), $answers);

        author_storage::update_record((int) $record->id, [
            'status' => author_storage::STATUS_VERIFICATION_IN_PROGRESS,
            'quiz_responses' => $merged,
            'response_timings' => $state,
        ]);
        $record->quiz_responses = json_encode($merged);
        $record->response_timings = json_encode($state);
        $record->status = author_storage::STATUS_VERIFICATION_IN_PROGRESS;

        return $state;
    }

    /**
     * Total time allowed for the whole quiz.
     *
     * @return int Seconds, or 0 for no limit.
     */
    public static function get_total_time_limit(): int {
        return self::get_time_setting('quiz_total_time', 180);
    }

    /**
     * Time allowed for one question of a given type.
     *
     * This paces the student and is recorded against the answer; the quiz closing is
     * governed by the total time.
     *
     * @param string $type One of the authorship question types.
     * @return int Seconds, or 0 for no limit.
     */
    public static function get_question_time_limit(string $type): int {
        $settings = [
            authorship::QUESTION_TYPE_TRUE_FALSE => ['quiz_time_true_false', 60],
            authorship::QUESTION_TYPE_MULTIPLE_CHOICE => ['quiz_time_multiple_choice', 60],
            authorship::QUESTION_TYPE_OPEN => ['quiz_time_open', 180],
        ];

        [$name, $default] = $settings[$type] ?? $settings[authorship::QUESTION_TYPE_OPEN];

        return self::get_time_setting($name, $default);
    }

    /**
     * Whether students may return to questions they have already answered.
     *
     * @return bool
     */
    public static function allows_backwards_navigation(): bool {
        return (int) get_config('plagiarism_dixeo', 'allow_backwards_navigation') === 1;
    }

    /**
     * How long the next button stays disabled after a click.
     *
     * @return int Seconds.
     */
    public static function get_next_button_delay(): int {
        $value = get_config('plagiarism_dixeo', 'next_button_delay_seconds');
        $seconds = ($value === false || $value === null) ? 3 : (int) $value;

        return max(0, min($seconds, self::MAX_NEXT_BUTTON_DELAY));
    }

    /**
     * Accept only numeric per-question start times, keyed by question id.
     *
     * @param mixed $raw Value from stored JSON.
     * @return array<string, int>
     */
    private static function normalize_question_started_at($raw): array {
        if (!is_array($raw)) {
            return [];
        }

        $accepted = [];
        foreach ($raw as $questionid => $started) {
            if (!is_numeric($started)) {
                continue;
            }
            $accepted[(string) $questionid] = (int) $started;
        }

        return $accepted;
    }

    /**
     * Read a duration setting, clamped to the range the settings form accepts.
     *
     * @param string $name Setting name.
     * @param int $default Value to use when the setting has never been saved.
     * @return int Seconds.
     */
    private static function get_time_setting(string $name, int $default): int {
        $value = get_config('plagiarism_dixeo', $name);
        $seconds = ($value === false || $value === null) ? $default : (int) $value;

        return max(0, min($seconds, self::MAX_TIME_LIMIT));
    }
}
