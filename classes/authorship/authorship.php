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
 * Authorship settings helpers and quiz HTML/grade utilities.
 *
 * @package    plagiarism_dixeo
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo\authorship;


/**
 * Plugin config gates, record state, locking and objective grade helpers.
 */
class authorship {
    /** Confidence scale: minimum (0 = AI-generated). */
    public const CONFIDENCE_MIN = 0;

    /** Confidence scale: maximum (100 = 100% human-written). */
    public const CONFIDENCE_MAX = 100;

    /** Question type: true/false. */
    public const QUESTION_TYPE_TRUE_FALSE = 'true_false';

    /** Question type: multiple choice (single, 4 options by default). */
    public const QUESTION_TYPE_MULTIPLE_CHOICE = 'multiple_choice';

    /** Question type: open (free text). */
    public const QUESTION_TYPE_OPEN = 'open';

    /** Default number of true/false questions. */
    public const DEFAULT_COUNT_TRUE_FALSE = 1;

    /** Default number of multiple choice questions (4 options). */
    public const DEFAULT_COUNT_MULTIPLE_CHOICE = 1;

    /** Default number of open questions. */
    public const DEFAULT_COUNT_OPEN = 1;

    /**
     * Whether authorship verification is on.
     *
     * A context inside a course uses that course. With no context, the Dixeo layer is used.
     *
     * @param \context|null $context Course, activity, or system context.
     * @return bool
     */
    public static function is_enabled(?\context $context = null): bool {
        return \plagiarism_dixeo\feature_settings::authorship_on($context ?? \context_system::instance());
    }

    /**
     * Question counts per type.
     *
     * These are fixed so the quiz shape can be adjusted in code without a setting.
     *
     * @return array{true_false: int, multiple_choice: int, open: int}
     */
    public static function get_question_counts(): array {
        return [
            'true_false' => self::DEFAULT_COUNT_TRUE_FALSE,
            'multiple_choice' => self::DEFAULT_COUNT_MULTIPLE_CHOICE,
            'open' => self::DEFAULT_COUNT_OPEN,
        ];
    }

    /**
     * Whether the given confidence score requires a quiz.
     *
     * A quiz is required when the score is below the threshold, so 0 never requires one.
     *
     * @param float $score Confidence score (0–100).
     * @param float $threshold Assignment confidence threshold (0–100).
     * @return bool
     */
    public static function is_quiz_required(float $score, float $threshold): bool {
        return $score < $threshold;
    }

    /**
     * Whether an authorship record still has a quiz the student can sit or resume.
     *
     * @param \stdClass|null $record Authorship record, or null when none exists.
     * @return bool
     */
    public static function is_resumable_record(?\stdClass $record): bool {
        if ($record === null) {
            return false;
        }

        $open = [
            author_storage::STATUS_VERIFICATION_REQUIRED,
            author_storage::STATUS_VERIFICATION_IN_PROGRESS,
        ];
        if (!in_array($record->status, $open, true)) {
            return false;
        }

        $questions = quiz_session::get_questions($record);

        return $questions !== [];
    }

    /**
     * Whether the student already has a quiz or a final score for this submission.
     *
     * A settled record is never replaced by a new check: only a pending or failed one is.
     *
     * @param \stdClass $record Authorship row.
     * @return bool
     */
    public static function is_settled(\stdClass $record): bool {
        $status = (string) ($record->status ?? '');
        return !in_array($status, [author_storage::STATUS_PENDING, author_storage::STATUS_FAILED], true);
    }

    /**
     * Take a lock that serialises work on one authorship record or submission, without waiting.
     *
     * @param string $key Lock key.
     * @return \core\lock\lock|null The lock, or null when another request holds it.
     */
    public static function acquire_lock(string $key): ?\core\lock\lock {
        $lock = \core\lock\lock_config::get_lock_factory('plagiarism_dixeo')->get_lock($key, 0);
        return $lock ?: null;
    }

    /**
     * Objective grade (0–100) from correct answers for true_false and multiple_choice only.
     *
     * @param object $record Authorship record with quiz_data (questions may have "correct" key).
     * @param array $responses Question id => user answer.
     * @return float|null Grade 0–100 or null if no gradeable questions.
     */
    public static function compute_quiz_grade(object $record, array $responses): ?float {
        $quiz = is_string($record->quiz_data ?? '')
            ? json_decode($record->quiz_data, true)
            : ($record->quiz_data ?? []);
        $questions = $quiz['questions'] ?? [];
        $total = 0;
        $correct = 0;
        foreach ($questions as $q) {
            if (!isset($q['correct'])) {
                continue;
            }
            $id = $q['id'] ?? null;
            if ($id === null || $id === '') {
                continue;
            }
            $total++;
            $user = $responses[$id] ?? $responses[(string) $id] ?? null;
            $expected = $q['correct'];
            $type = $q['type'] ?? self::QUESTION_TYPE_OPEN;
            $isright = false;
            if ($type === self::QUESTION_TYPE_TRUE_FALSE) {
                $normalized = $user === true || $user === 1 || $user === '1' ? 1 : 0;
                $isright = ($normalized === (int) $expected);
            } else if ($type === self::QUESTION_TYPE_MULTIPLE_CHOICE) {
                $isright = ((string) $user === (string) $expected);
            }
            if ($isright) {
                $correct++;
            }
        }
        if ($total === 0) {
            return null;
        }
        return round(($correct / $total) * 100, 2);
    }
}
