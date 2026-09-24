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
 * Plugin config gates, quiz form rendering, and objective grade helpers.
 */
class authorship {
    /** Confidence scale: minimum (0 = AI-generated). */
    public const CONFIDENCE_MIN = 0;

    /** Confidence scale: maximum (100 = 100% human-written). */
    public const CONFIDENCE_MAX = 100;

    /** Default confidence threshold (75%). Below this, quiz is required. */
    public const DEFAULT_CONFIDENCE_THRESHOLD = 75.0;

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
     * Whether authorship features are enabled.
     *
     * @return bool
     */
    public static function is_enabled(): bool {
        $value = get_config('plagiarism_dixeo', 'authorship_enabled');
        if ($value === false || $value === null) {
            return true;
        }
        return ((int) $value) !== 0;
    }

    /**
     * Confidence threshold (0–100) below which a quiz is required.
     *
     * @return float
     */
    public static function get_confidence_threshold(): float {
        $value = get_config('plagiarism_dixeo', 'authorship_threshold');
        $int = ($value !== false && $value !== null) ? (int) $value : (int) self::DEFAULT_CONFIDENCE_THRESHOLD;
        return (float) max(self::CONFIDENCE_MIN, min(self::CONFIDENCE_MAX, $int));
    }

    /**
     * Question counts per type from plugin settings (0–5 each).
     *
     * @return array{true_false: int, multiple_choice: int, open: int}
     */
    public static function get_question_counts(): array {
        $read = function ($key, $default) {
            $v = get_config('plagiarism_dixeo', $key);
            $n = ($v !== false && $v !== null) ? (int) $v : $default;
            return max(0, min(5, $n));
        };
        return [
            'true_false' => $read('authorship_count_true_false', self::DEFAULT_COUNT_TRUE_FALSE),
            'multiple_choice' => $read('authorship_count_multiple_choice', self::DEFAULT_COUNT_MULTIPLE_CHOICE),
            'open' => $read('authorship_count_open', self::DEFAULT_COUNT_OPEN),
        ];
    }

    /**
     * Whether the given confidence score requires a quiz.
     *
     * Threshold 0 = always require quiz, 100 = never require one. Between those bounds a quiz is
     * required when the score is below the threshold.
     *
     * @param float $score Confidence score (0–100).
     * @param float|null $threshold Optional threshold; uses config default if null.
     * @return bool
     */
    public static function is_quiz_required(float $score, ?float $threshold = null): bool {
        $threshold = $threshold ?? self::get_confidence_threshold();
        if ($threshold <= self::CONFIDENCE_MIN) {
            return true;
        }
        if ($threshold >= self::CONFIDENCE_MAX) {
            return false;
        }
        return $score < $threshold;
    }

    /**
     * Render the quiz as an HTML form.
     *
     * @param object|array $recordorquizdata DB record (with quiz_data) or decoded quiz array.
     * @param array $options form_action_url, submit_label, record_id, css_class.
     * @return string HTML fragment.
     */
    public static function render_quiz_form($recordorquizdata, array $options = []): string {
        $recordid = $options['record_id'] ?? null;
        $action = $options['form_action_url'] ?? '';
        $submitlabel = $options['submit_label'] ?? get_string('authorship_submit_quiz', 'plagiarism_dixeo');
        $cssclass = $options['css_class'] ?? 'plagiarism-dixeo-authorship-quiz';

        if (is_object($recordorquizdata) && isset($recordorquizdata->quiz_data)) {
            $quiz = is_string($recordorquizdata->quiz_data)
                ? json_decode($recordorquizdata->quiz_data, true)
                : $recordorquizdata->quiz_data;
            if ($recordid === null && isset($recordorquizdata->id)) {
                $recordid = (int) $recordorquizdata->id;
            }
        } else {
            $quiz = is_array($recordorquizdata) ? $recordorquizdata : ['questions' => []];
        }

        $questions = $quiz['questions'] ?? [];
        $html = [];
        $html[] = '<form class="' . s($cssclass) . '" method="post" action="' . s($action)
            . '" data-authorship-record-id="' . (int) $recordid . '">';
        $html[] = '<input type="hidden" name="authorship_record_id" value="' . (int) $recordid . '" />';

        foreach ($questions as $index => $q) {
            $html[] = self::render_quiz_question($q, $index);
        }

        $html[] = '<div class="authorship-quiz-actions"><button type="submit" class="btn btn-primary">'
            . s($submitlabel) . '</button></div>';
        $html[] = '</form>';
        return implode("\n", $html);
    }

    /**
     * Render a single quiz question (true/false, multiple_choice, open).
     *
     * @param array $question Must contain type, text; for multiple_choice, options array.
     * @param int $index Question index for name/id.
     * @return string HTML fragment.
     */
    private static function render_quiz_question(array $question, int $index): string {
        $type = $question['type'] ?? self::QUESTION_TYPE_OPEN;
        $text = $question['text'] ?? '';
        $id = $question['id'] ?? ('q' . $index);
        $name = 'authorship_q[' . s($id) . ']';
        $out = [];

        $out[] = '<div class="authorship-question authorship-question-' . s($type)
            . '" data-question-id="' . s($id) . '">';
        $out[] = '<label class="authorship-question-text">' . s($text) . '</label>';

        switch ($type) {
            case self::QUESTION_TYPE_TRUE_FALSE:
                $out[] = '<div class="authorship-options">';
                $out[] = '<label><input type="radio" name="' . $name . '" value="1" /> '
                    . get_string('true', 'plagiarism_dixeo') . '</label>';
                $out[] = '<label><input type="radio" name="' . $name . '" value="0" /> '
                    . get_string('false', 'plagiarism_dixeo') . '</label>';
                $out[] = '</div>';
                break;
            case self::QUESTION_TYPE_MULTIPLE_CHOICE:
                $opts = $question['options'] ?? [];
                $out[] = '<div class="authorship-options">';
                foreach ($opts as $optindex => $opt) {
                    $optval = is_array($opt) ? ($opt['value'] ?? $opt['text'] ?? $optindex) : $opt;
                    $opttext = is_array($opt) ? ($opt['text'] ?? (string) $optval) : (string) $opt;
                    $out[] = '<label><input type="radio" name="' . $name . '" value="' . s($optval) . '" /> '
                        . s($opttext) . '</label>';
                }
                $out[] = '</div>';
                break;
            case self::QUESTION_TYPE_OPEN:
            default:
                $out[] = '<textarea name="' . $name . '" rows="4" class="form-control"></textarea>';
                break;
        }

        $out[] = '</div>';
        return implode("\n", $out);
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
