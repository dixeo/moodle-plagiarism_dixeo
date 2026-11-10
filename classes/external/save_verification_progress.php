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
 * External API: save progress through the authorship verification quiz.
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
use plagiarism_dixeo\authorship\authorship;
use plagiarism_dixeo\authorship\quiz_session;

/**
 * Stores answers as the student works through the quiz.
 *
 * Progress is saved so a reload does not lose answers. Nothing is accepted before the clock
 * has been started, and once it has run out the answers already stored are what gets evaluated.
 * A save runs under the same per-record lock as the start and the final evaluation, on a copy
 * of the record read under that lock; while another request holds it, nothing is saved.
 */
class save_verification_progress extends external_api {
    /**
     * Describe parameters for save_verification_progress.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'record_id' => new external_value(PARAM_INT, 'Authorship record id.'),
            'responses' => new external_value(PARAM_RAW, 'Answers so far as a JSON object.', VALUE_DEFAULT, '{}'),
            'timings' => new external_value(
                PARAM_RAW,
                'Seconds spent per question as a JSON object.',
                VALUE_DEFAULT,
                '{}'
            ),
            'current_index' => new external_value(PARAM_INT, 'Question the student is on.', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Save the student's progress.
     *
     * @param int $recordid Authorship record id.
     * @param string $responses Answers as a JSON object keyed by question id.
     * @param string $timings Seconds per question as a JSON object keyed by question id.
     * @param int $currentindex Question the student is on.
     * @return array
     * @throws \moodle_exception
     */
    public static function execute(
        int $recordid,
        string $responses = '{}',
        string $timings = '{}',
        int $currentindex = 0
    ): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'record_id' => $recordid,
            'responses' => $responses,
            'timings' => $timings,
            'current_index' => $currentindex,
        ]);

        $record = verification_access::require_own_open_quiz($params['record_id']);
        assign_access::require_student_authorship_from_record($record);

        $state = quiz_session::load($record);
        verification_access::require_started($state);
        if (quiz_session::is_expired($state)) {
            return verification_access::timing_payload($state, $record) + ['saved' => false];
        }

        $responses = quiz_session::decode_payload($params['responses'], 'responses');
        $timings = quiz_session::decode_payload($params['timings'], 'timings');

        $lock = authorship::acquire_lock('authorship_' . (int) $record->id);
        if (!$lock) {
            // The quiz is being started or evaluated in another session.
            return verification_access::timing_payload($state, $record) + ['saved' => false];
        }
        try {
            $fresh = verification_access::require_own_open_quiz((int) $record->id);
            $state = quiz_session::load($fresh);
            verification_access::require_started($state);
            if (quiz_session::is_expired($state)) {
                return verification_access::timing_payload($state, $fresh) + ['saved' => false];
            }

            $questionids = quiz_session::get_question_ids($fresh);
            $answers = quiz_session::validate_answers($responses, $questionids);
            $reported = quiz_session::validate_timings($timings, $questionids);

            $state = quiz_session::record_progress($fresh, $answers, $reported, $params['current_index']);

            return verification_access::timing_payload($state, $fresh) + ['saved' => true];
        } finally {
            $lock->release();
        }
    }

    /**
     * Return structure for save_verification_progress.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return verification_access::timing_structure([
            'saved' => new external_value(PARAM_BOOL, 'Whether the answers were stored.'),
        ]);
    }
}
