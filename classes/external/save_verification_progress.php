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
 * External API: save verification progress (responses + timings). Keeps status in progress.
 *
 * @package    plagiarism_dixeo
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

defined('MOODLE_INTERNAL') || die();

class save_verification_progress extends external_api {

    public static function execute_parameters() {
        return new external_function_parameters([
            'record_id' => new external_value(PARAM_INT, 'Authorship record id.'),
            'responses' => new external_value(PARAM_RAW, 'JSON object question id -> answer.', VALUE_DEFAULT, '{}'),
            'response_timings' => new external_value(PARAM_RAW, 'JSON object with test_started_at, current_index, questions.', VALUE_DEFAULT, '{}'),
        ]);
    }

    public static function execute($recordid, $responses = '{}', $response_timings = '{}') {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), [
            'record_id' => $recordid,
            'responses' => $responses,
            'response_timings' => $response_timings,
        ]);
        $context = context_user::instance($USER->id);
        require_capability('plagiarism/dixeo:mod_assign_pre_submission', $context);
        $record = author_storage::get_record($params['record_id']);
        if (!$record || (int) $record->userid !== (int) $USER->id) {
            return ['success' => false];
        }
        if ($record->status !== author_storage::STATUS_VERIFICATION_IN_PROGRESS) {
            return ['success' => false];
        }
        $decoded = json_decode($params['responses'], true);
        if (!is_array($decoded)) {
            $decoded = [];
        }
        $timings = json_decode($params['response_timings'], true);
        if (!is_array($timings)) {
            $timings = [];
        }
        author_storage::update_record($params['record_id'], [
            'status' => author_storage::STATUS_VERIFICATION_IN_PROGRESS,
            'quiz_responses' => $decoded,
            'response_timings' => $timings,
        ]);
        return ['success' => true];
    }

    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Saved.'),
        ], 'Save progress.');
    }
}
