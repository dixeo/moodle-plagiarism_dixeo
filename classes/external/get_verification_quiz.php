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
 * External API: fetch the authorship verification quiz and its progress.
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
use plagiarism_dixeo\authorship\quiz_session;

/**
 * Returns the questions a student has to answer, along with how far they have got.
 *
 * Correct answers are stripped before the questions leave the server, so the quiz cannot be
 * completed by reading the response.
 */
class get_verification_quiz extends external_api {
    /**
     * Describe parameters for get_verification_quiz.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'record_id' => new external_value(PARAM_INT, 'Authorship record id.'),
        ]);
    }

    /**
     * Return the quiz and the answers saved so far.
     *
     * @param int $recordid Authorship record id.
     * @return array
     * @throws \moodle_exception
     */
    public static function execute(int $recordid): array {
        $params = self::validate_parameters(self::execute_parameters(), ['record_id' => $recordid]);

        $record = verification_access::require_own_open_quiz($params['record_id']);
        assign_access::require_student_analyze_from_authorship($record);

        $state = quiz_session::load($record);

        return verification_access::timing_payload($state, $record) + [
            'record_id' => (int) $record->id,
            'questions' => json_encode(quiz_session::get_public_questions($record)),
            'answers' => json_encode(quiz_session::get_answers($record)),
        ];
    }

    /**
     * Return structure for get_verification_quiz.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return verification_access::timing_structure([
            'record_id' => new external_value(PARAM_INT, 'Authorship record id.'),
            'questions' => new external_value(PARAM_RAW, 'Questions as JSON, without the correct answers.'),
            'answers' => new external_value(PARAM_RAW, 'Answers saved so far, as JSON.'),
        ]);
    }
}
