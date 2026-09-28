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
 * Shared authorisation and payload shape for the verification quiz endpoints.
 *
 * @package    plagiarism_dixeo
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo\external;

use core_external\external_single_structure;
use core_external\external_value;
use plagiarism_dixeo\authorship\author_storage;
use plagiarism_dixeo\authorship\quiz_session;

/**
 * Guards the verification quiz endpoints and describes their common response.
 *
 * A quiz belongs to exactly one student and can only be sat once, so every endpoint checks
 * both that the caller owns the record and that the quiz is still open.
 */
class verification_access {
    /**
     * Load a verification quiz the current user is entitled to sit.
     *
     * @param int $recordid Authorship record id.
     * @return \stdClass The authorship record.
     * @throws \moodle_exception When the record is missing, belongs to someone else, or is finished.
     */
    public static function require_own_open_quiz(int $recordid): \stdClass {
        global $USER;

        $record = author_storage::get_record($recordid);
        if (!$record || (int) $record->userid !== (int) $USER->id) {
            throw new \moodle_exception('quiz_not_available', 'plagiarism_dixeo');
        }

        $open = [
            author_storage::STATUS_VERIFICATION_REQUIRED,
            author_storage::STATUS_VERIFICATION_IN_PROGRESS,
        ];
        if (!in_array($record->status, $open, true) || empty($record->quiz_data)) {
            throw new \moodle_exception('quiz_not_available', 'plagiarism_dixeo');
        }

        return $record;
    }

    /**
     * Build the timing fields every verification endpoint returns.
     *
     * @param array $state Timing state from quiz_session::load().
     * @param \stdClass|null $record Authorship record, used for per-question remaining time.
     * @return array
     */
    public static function timing_payload(array $state, ?\stdClass $record = null): array {
        $remaining = quiz_session::remaining_seconds($state);
        $questionremaining = -1;
        if ($record !== null) {
            $qremaining = quiz_session::question_remaining_seconds($state, $record);
            $questionremaining = $qremaining === null ? -1 : $qremaining;
        }

        return [
            'started_at' => (int) $state['started_at'],
            'expires_at' => (int) $state['expires_at'],
            'remaining' => $remaining ?? -1,
            'question_remaining' => $questionremaining,
            'expired' => quiz_session::is_expired($state),
            'current_index' => (int) $state['current_index'],
            'allow_backwards' => quiz_session::allows_backwards_navigation(),
            'next_button_delay' => quiz_session::get_next_button_delay(),
        ];
    }

    /**
     * Describe the timing fields every verification endpoint returns.
     *
     * @param array $extra Additional fields to merge into the structure.
     * @return external_single_structure
     */
    public static function timing_structure(array $extra = []): external_single_structure {
        return new external_single_structure(array_merge([
            'started_at' => new external_value(PARAM_INT, 'When the quiz clock started, as a unix timestamp.'),
            'expires_at' => new external_value(PARAM_INT, 'When the quiz closes, or 0 when there is no limit.'),
            'remaining' => new external_value(PARAM_INT, 'Seconds left, or -1 when there is no limit.'),
            'question_remaining' => new external_value(
                PARAM_INT,
                'Seconds left on the current question, or -1 when there is no limit.'
            ),
            'expired' => new external_value(PARAM_BOOL, 'Whether the quiz has closed.'),
            'current_index' => new external_value(PARAM_INT, 'Question the student had reached.'),
            'allow_backwards' => new external_value(PARAM_BOOL, 'Whether earlier questions can be revisited.'),
            'next_button_delay' => new external_value(PARAM_INT, 'Seconds the next button stays disabled.'),
        ], $extra), 'Verification quiz timing.');
    }
}
