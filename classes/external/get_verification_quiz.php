<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify it
// under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful, but
// WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * External API: get verification quiz for modal (no correct answers). May auto-submit if time expired.
 *
 * @package    plagiarism_dixeo
 * @author     Josemaria Bolanos <admin@mako.digital>
 * @copyright  2026 Dixeo.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo\external;

use context_user;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use plagiarism_dixeo\local\author_storage;
use plagiarism_dixeo\local\authorship;

defined('MOODLE_INTERNAL') || die();

/**
 * External API: get verification quiz for modal (questions and settings; no correct answers).
 * May auto-submit if total time has expired.
 */
class get_verification_quiz extends external_api {

    /**
     * Parameter description.
     *
     * @return \core_external\external_function_parameters
     */
    public static function execute_parameters(): \core_external\external_function_parameters {
        return new external_function_parameters([
            'record_id' => new external_value(PARAM_INT, 'Authorship record id.'),
        ]);
    }

    /**
     * Return quiz data, settings, instructions and optional state for the verification modal.
     *
     * @param int $recordid Authorship record id.
     * @return array{submitted: bool, questions: string, settings: string, instructions: string, state: string|null}
     */
    public static function execute(int $recordid): array {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), ['record_id' => $recordid]);
        $context = context_user::instance($USER->id);
        require_capability('plagiarism/dixeo:mod_assign_pre_submission', $context);
        $record = author_storage::get_record($params['record_id']);
        if (!$record || (int) $record->userid !== (int) $USER->id) {
            return self::empty_response();
        }
        if ($record->status !== author_storage::STATUS_VERIFICATION_REQUIRED &&
            $record->status !== author_storage::STATUS_VERIFICATION_IN_PROGRESS) {
            return self::empty_response();
        }
        $quizdata = $record->quiz_data;
        if ($quizdata === null || $quizdata === '') {
            return self::empty_response();
        }
        $settings = authorship::get_verification_settings();
        $instructions = authorship::build_verification_instructions($settings);
        $quiz_safe = authorship::get_quiz_for_display($quizdata);
        $questions = $quiz_safe['questions'] ?? [];
        $total_time_sec = $settings['total_time_sec'] > 0 ? $settings['total_time_sec'] : 0;
        $state = null;
        if ($record->status === author_storage::STATUS_VERIFICATION_IN_PROGRESS) {
            $timings = is_string($record->response_timings ?? '') ? json_decode($record->response_timings, true) : ($record->response_timings ?? []);
            if (!is_array($timings)) {
                $timings = [];
            }
            $started_at = (int) ($timings['test_started_at'] ?? 0);
            $now = time();
            if ($total_time_sec > 0 && ($now - $started_at) >= $total_time_sec) {
                $responses = is_string($record->quiz_responses ?? '') ? json_decode($record->quiz_responses, true) : ($record->quiz_responses ?? []);
                if (!is_array($responses)) {
                    $responses = [];
                }
                $timings['test_ended_at'] = $now;
                $result = authorship::evaluate_verify($quizdata, $responses,
                    $record->initial_confidence !== null ? (float) $record->initial_confidence : null, $timings);
                author_storage::update_record($params['record_id'], [
                    'quiz_responses' => $responses,
                    'response_timings' => $timings,
                    'final_confidence' => $result['final_confidence'],
                    'quiz_grade' => $result['quiz_grade'],
                    'quiz_explanation' => $result['quiz_explanation'] ?? '',
                    'quiz_question_feedbacks' => $result['quiz_question_feedbacks'] ?? [],
                    'final_explanation' => $result['final_explanation'] ?? '',
                ]);
                return [
                    'submitted' => true,
                    'questions' => json_encode($questions),
                    'settings' => json_encode([
                        'total_time_sec' => $total_time_sec,
                        'time_true_false_sec' => $settings['time_true_false_sec'],
                        'time_multiple_choice_sec' => $settings['time_multiple_choice_sec'],
                        'time_open_sec' => $settings['time_open_sec'],
                        'allow_backwards' => $settings['allow_backwards'],
                        'next_button_delay_sec' => $settings['next_button_delay_sec'],
                    ]),
                    'instructions' => json_encode($instructions),
                    'state' => null,
                ];
            }
            $responses = is_string($record->quiz_responses ?? '') ? json_decode($record->quiz_responses, true) : ($record->quiz_responses ?? []);
            if (!is_array($responses)) {
                $responses = [];
            }
            $state = [
                'test_started_at' => $started_at,
                'quiz_responses' => $responses,
                'response_timings' => $timings,
                'current_question_index' => (int) ($timings['current_index'] ?? 0),
            ];
        }
        return [
            'submitted' => false,
            'questions' => json_encode($questions),
            'settings' => json_encode([
                'total_time_sec' => $total_time_sec,
                'time_true_false_sec' => $settings['time_true_false_sec'],
                'time_multiple_choice_sec' => $settings['time_multiple_choice_sec'],
                'time_open_sec' => $settings['time_open_sec'],
                'allow_backwards' => $settings['allow_backwards'],
                'next_button_delay_sec' => $settings['next_button_delay_sec'],
            ]),
            'instructions' => json_encode($instructions),
            'state' => $state !== null ? json_encode($state) : null,
        ];
    }

    /**
     * Empty response when record is invalid or not in a quiz state.
     *
     * @return array{submitted: bool, questions: string, settings: string, instructions: string, state: null}
     */
    protected static function empty_response(): array {
        return [
            'submitted' => false,
            'questions' => '[]',
            'settings' => '{}',
            'instructions' => json_encode(['heading' => '', 'intro' => '', 'lines' => []]),
            'state' => null,
        ];
    }

    /**
     * Return value description.
     *
     * @return \core_external\external_single_structure
     */
    public static function execute_returns(): \core_external\external_single_structure {
        return new external_single_structure([
            'submitted' => new external_value(PARAM_BOOL, 'True if auto-submitted.'),
            'questions' => new external_value(PARAM_RAW, 'JSON questions.'),
            'settings' => new external_value(PARAM_RAW, 'JSON settings.'),
            'instructions' => new external_value(PARAM_RAW, 'JSON instructions.'),
            'state' => new external_value(PARAM_RAW, 'JSON state or null.', VALUE_OPTIONAL, null),
        ], 'Verification quiz.');
    }
}
