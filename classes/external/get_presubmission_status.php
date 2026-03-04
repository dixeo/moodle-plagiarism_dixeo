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
 * External API: get pre-submission feedback status for current user (has existing feedback + HTML).
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
use plagiarism_dixeo\local\presubmission_feedback_storage;
use plagiarism_dixeo\modules\assign;

defined('MOODLE_INTERNAL') || die();

/**
 * Get whether the current submission already has stored feedback (so UI shows feedback instead of Analyze button).
 */
class get_presubmission_status extends external_api {

    /**
     * Parameters: course module id (assign instance).
     *
     * @return \core_external\external_function_parameters
     */
    public static function execute_parameters(): \core_external\external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Course module ID (assign instance).'),
        ]);
    }

    /**
     * Return has_feedback and optional feedback_html for the current user's submission.
     *
     * @param int $id Course module ID.
     * @return array{has_feedback: bool, feedback_html: string}
     */
    public static function execute(int $id): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), ['id' => $id]);

        $context = context_user::instance($USER->id);
        require_capability('plagiarism/dixeo:mod_assign_pre_submission', $context);

        $result = [
            'has_feedback'  => false,
            'feedback_html' => '',
        ];

        if ($params['id'] <= 0) {
            return $result;
        }

        try {
            get_coursemodule_from_id('assign', $params['id'], 0, false, MUST_EXIST);
        } catch (\moodle_exception $e) {
            return $result;
        }

        $submissiondata = assign::get_submission_data($params['id'], $USER->id);
        $submission = $submissiondata['submission'] ?? null;
        $itemid = $submission ? (int) $submission->id : 0;
        if ($itemid <= 0) {
            return $result;
        }

        $existing = presubmission_feedback_storage::get_by_context(
            (int) $USER->id,
            'mod_assign',
            'submission',
            $itemid
        );
        if (!$existing || trim((string) ($existing->feedback ?? '')) === '') {
            return $result;
        }

        $result['has_feedback'] = true;
        $result['feedback_html'] = mod_assign_pre_submission::feedback_html_wrapper((string) $existing->feedback);
        return $result;
    }

    /**
     * Return value: has_feedback, feedback_html.
     *
     * @return \core_external\external_single_structure
     */
    public static function execute_returns(): \core_external\external_single_structure {
        return new external_single_structure([
            'has_feedback'  => new external_value(PARAM_BOOL, 'Whether stored feedback exists for this submission.'),
            'feedback_html' => new external_value(PARAM_RAW, 'Wrapped HTML to display when has_feedback is true.'),
        ], 'Pre-submission status.');
    }
}
