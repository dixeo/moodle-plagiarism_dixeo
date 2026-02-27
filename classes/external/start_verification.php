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
 * External API: start verification test (set status in progress, record test_started_at).
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

class start_verification extends external_api {

    public static function execute_parameters() {
        return new external_function_parameters([
            'record_id' => new external_value(PARAM_INT, 'Authorship record id.'),
        ]);
    }

    public static function execute($recordid) {
        global $USER;
        $params = self::validate_parameters(self::execute_parameters(), ['record_id' => $recordid]);
        $context = context_user::instance($USER->id);
        require_capability('plagiarism/dixeo:mod_assign_pre_submission', $context);
        $record = author_storage::get_record($params['record_id']);
        if (!$record || (int) $record->userid !== (int) $USER->id) {
            return ['success' => false, 'test_started_at' => 0];
        }
        if ($record->status !== author_storage::STATUS_VERIFICATION_REQUIRED) {
            return ['success' => false, 'test_started_at' => 0];
        }
        $now = time();
        $timings = ['test_started_at' => $now, 'current_index' => 0, 'questions' => []];
        author_storage::update_record($params['record_id'], [
            'status' => author_storage::STATUS_VERIFICATION_IN_PROGRESS,
            'response_timings' => $timings,
            'quiz_responses' => [],
        ]);
        return ['success' => true, 'test_started_at' => $now];
    }

    public static function execute_returns() {
        return new external_single_structure([
            'success' => new external_value(PARAM_BOOL, 'Started.'),
            'test_started_at' => new external_value(PARAM_INT, 'Unix timestamp.'),
        ], 'Start verification.');
    }
}
