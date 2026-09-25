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
 * External API: verify authorship by submitting quiz responses.
 *
 * @package    plagiarism_dixeo
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_dixeo\external\response_factory;
use local_dixeo\external\service_factory;
use local_dixeo\service\assign_ux_service;
use plagiarism_dixeo\authorship\authorship;
use plagiarism_dixeo\authorship\authorship_persistence_adapter;


/**
 * Final authorship evaluation via Dixeo_API, then persistence.
 */
class verify_authorship extends external_api {
    /**
     * Describe parameters for verify_authorship.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'record_id' => new external_value(PARAM_INT, 'Authorship record id.'),
            'responses' => new external_value(PARAM_RAW, 'JSON object of question id -> answer.', VALUE_DEFAULT, '{}'),
        ]);
    }

    /**
     * Submit quiz responses and save final confidence.
     *
     * @param int $recordid Authorship record id.
     * @param string $responses JSON object keyed by question id.
     * @return array
     * @throws \moodle_exception
     */
    public static function execute(int $recordid, string $responses = '{}'): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'record_id' => $recordid,
            'responses' => $responses,
        ]);

        $adapter = new authorship_persistence_adapter();
        $record = $adapter->get_authorship_record($params['record_id']);
        if (!$record || (int) $record->userid !== (int) $USER->id) {
            return self::payload(false, 0.0, '', 'failed', 0, '');
        }

        assign_access::require_student_analyze_from_authorship($record);
        $cm = null;
        $courseid = 0;
        try {
            // Re-resolve course for response / jobs.
            global $DB;
            $submission = $DB->get_record(
                'assign_submission',
                ['id' => (int) $record->itemid],
                'id,assignment',
                MUST_EXIST
            );
            $cm = get_coursemodule_from_instance('assign', (int) $submission->assignment, 0, false, MUST_EXIST);
            $courseid = (int) $cm->course;
        } catch (\Throwable $e) {
            return self::payload(false, 0.0, '', 'failed', 0, response_factory::safe_message($e));
        }

        $decoded = json_decode($params['responses'], true);
        if (!is_array($decoded)) {
            $decoded = [];
        }

        $quiz = $record->quiz_data ?? [];
        if (is_string($quiz)) {
            $quiz = json_decode($quiz, true);
        }
        $questions = is_array($quiz) ? ($quiz['questions'] ?? []) : [];

        try {
            $service = service_factory::get_assign_ux_service($adapter);
            $operation = $service->submit_authorship_and_wait(
                $courseid,
                (int) $cm->id,
                (int) $USER->id,
                assign_ux_service::MODE_FINAL,
                [
                    'initialConfidence' => (float) ($record->initial_confidence ?? 0),
                    'questions' => $questions,
                    'answers' => $decoded,
                ]
            );

            if (!$operation->completed) {
                return self::payload(
                    false,
                    0.0,
                    $operation->jobid,
                    'failed',
                    $courseid,
                    get_string('assign_ux_result_missing', 'local_dixeo')
                );
            }

            $mapped = $service->map_authorship_result(assign_ux_service::MODE_FINAL, $operation->result);
            if (isset($mapped['error'])) {
                return self::payload(false, 0.0, $operation->jobid, 'failed', $courseid, $mapped['error']);
            }

            $final = (float) $mapped['final_confidence'];
            $grade = $mapped['grade'] ?? authorship::compute_quiz_grade($record, $decoded);
            $updated = $service->apply_authorship_final((int) $record->id, $decoded, $final, $grade);
            if (!$updated) {
                return self::payload(false, 0.0, $operation->jobid, 'failed', $courseid, '');
            }

            return self::payload(
                true,
                (float) ($updated->final_confidence ?? $final),
                $operation->jobid,
                'completed',
                $courseid,
                ''
            );
        } catch (\required_capability_exception | \require_login_exception $e) {
            throw $e;
        } catch (\Throwable $e) {
            return self::payload(false, 0.0, '', 'failed', $courseid, response_factory::safe_message($e));
        }
    }

    /**
     * Build the WS result payload.
     *
     * @param bool $success Success flag.
     * @param float $finalconfidence Final confidence.
     * @param string $jobid Job id.
     * @param string $status Status.
     * @param int $courseid Course id.
     * @param string $errormessage Error message.
     * @return array
     */
    protected static function payload(
        bool $success,
        float $finalconfidence,
        string $jobid,
        string $status,
        int $courseid,
        string $errormessage
    ): array {
        return [
            'success' => $success,
            'final_confidence' => $finalconfidence,
            'jobid' => $jobid,
            'status' => $status,
            'progress' => ($status === 'completed' || $status === 'failed') ? 100 : 0,
            'courseid' => $courseid,
            'errormessage' => $errormessage,
        ];
    }

    /**
     * Return structure for verify_authorship.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Whether verification was saved.'),
            'final_confidence' => new external_value(PARAM_FLOAT, 'Final confidence score 0-100.'),
            'jobid' => new external_value(PARAM_RAW, 'Remote job id.'),
            'status' => new external_value(PARAM_ALPHA, 'Job/flow status.'),
            'progress' => new external_value(PARAM_INT, 'Progress 0–100.'),
            'courseid' => new external_value(PARAM_INT, 'Course id for polling.'),
            'errormessage' => new external_value(PARAM_TEXT, 'Client-safe error message when failed.'),
        ], 'Verify authorship result.');
    }
}
