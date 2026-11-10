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
 * External API: start or resume the authorship verification quiz.
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
use plagiarism_dixeo\authorship\author_storage;
use plagiarism_dixeo\authorship\authorship;
use plagiarism_dixeo\authorship\quiz_session;
use plagiarism_dixeo\event\verification_started;

/**
 * Starts the server-side clock for a verification quiz.
 *
 * Calling this again for a quiz already under way resumes it rather than restarting it, so
 * a student who reloads the page does not get their time back. The start runs under the same
 * per-record lock as saving and evaluating; while another request holds it, the current timing
 * is returned and nothing is written.
 */
class start_verification extends external_api {
    /**
     * Describe parameters for start_verification.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'record_id' => new external_value(PARAM_INT, 'Authorship record id.'),
        ]);
    }

    /**
     * Start or resume the quiz for the current user.
     *
     * @param int $recordid Authorship record id.
     * @return array
     * @throws \moodle_exception
     */
    public static function execute(int $recordid): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), ['record_id' => $recordid]);

        $record = verification_access::require_own_open_quiz($params['record_id']);
        $context = assign_access::require_student_authorship_from_record($record);

        $lock = authorship::acquire_lock('authorship_' . (int) $record->id);
        if (!$lock) {
            // A save or an evaluation is running in another session: report, write nothing.
            $fresh = verification_access::require_own_open_quiz((int) $record->id);
            return verification_access::timing_payload(quiz_session::load($fresh), $fresh);
        }
        try {
            $fresh = verification_access::require_own_open_quiz((int) $record->id);
            [$state, $juststarted] = quiz_session::start($fresh);

            if ($juststarted) {
                $DB->set_field_select(
                    author_storage::TABLE,
                    'status',
                    author_storage::STATUS_VERIFICATION_IN_PROGRESS,
                    'id = :id AND status = :required',
                    ['id' => (int) $fresh->id, 'required' => author_storage::STATUS_VERIFICATION_REQUIRED]
                );
                verification_started::create([
                    'context' => $context,
                    'objectid' => (int) $fresh->id,
                    'relateduserid' => (int) $fresh->userid,
                ])->trigger();
            }

            return verification_access::timing_payload($state, $fresh);
        } finally {
            $lock->release();
        }
    }

    /**
     * Return structure for start_verification.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return verification_access::timing_structure();
    }
}
