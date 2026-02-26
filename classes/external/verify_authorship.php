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
 * External API: verify authorship by submitting quiz responses and saving final score.
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
 * Verify authorship: submit quiz responses, evaluate, save via author_storage.
 */
class verify_authorship extends external_api {

    /**
     * Parameters: authorship record id and responses (JSON object).
     *
     * @return \core_external\external_function_parameters
     */
    public static function execute_parameters(): \core_external\external_function_parameters {
        return new external_function_parameters([
            'record_id' => new external_value(PARAM_INT, 'Authorship record id.'),
            'responses' => new external_value(PARAM_RAW, 'JSON object of question id -> answer.', VALUE_DEFAULT, '{}'),
        ]);
    }

    /**
     * Submit quiz responses; evaluate and save final confidence via author_storage.
     *
     * @param int $recordid Authorship record id.
     * @param string $responses JSON object keyed by question id.
     * @return array{success: bool, final_confidence: float}
     * @throws \moodle_exception If capability check fails.
     */
    public static function execute(int $recordid, string $responses = '{}'): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'record_id' => $recordid,
            'responses' => $responses,
        ]);

        $context = context_user::instance($USER->id);
        require_capability('plagiarism/dixeo:mod_assign_pre_submission', $context);

        $decoded = json_decode($params['responses'], true);
        if (!is_array($decoded)) {
            $decoded = [];
        }

        $record = author_storage::get_record($params['record_id']);
        if (!$record || (int) $record->userid !== (int) $USER->id) {
            return ['success' => false, 'final_confidence' => 0.0];
        }

        $result = authorship::evaluate_verify(
            $record->quiz_data,
            $decoded,
            $record->initial_confidence !== null ? (float) $record->initial_confidence : null
        );
        author_storage::update_record($params['record_id'], [
            'quiz_responses' => $decoded,
            'final_confidence' => $result['final_confidence'],
            'quiz_grade' => $result['quiz_grade'],
            'quiz_explanation' => $result['quiz_explanation'] ?? '',
            'quiz_question_feedbacks' => $result['quiz_question_feedbacks'] ?? [],
            'final_explanation' => $result['final_explanation'] ?? '',
        ]);

        return [
            'success'          => true,
            'final_confidence' => (float) $result['final_confidence'],
        ];
    }

    /**
     * Return value: success, final_confidence.
     *
     * @return \core_external\external_single_structure
     */
    public static function execute_returns(): \core_external\external_single_structure {
        return new external_single_structure([
            'success'          => new external_value(PARAM_BOOL, 'Whether verification was saved.'),
            'final_confidence' => new external_value(PARAM_FLOAT, 'Final confidence score 0-100.'),
        ], 'Verify authorship result.');
    }
}
