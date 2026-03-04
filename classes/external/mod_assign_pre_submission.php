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
 * External API: pre-submission feedback (student Analyze step).
 *
 * @package    plagiarism_dixeo
 * @author     Josemaria Bolanos <admin@mako.digital>
 * @copyright  2026 Dixeo.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo\external;

use context_system;
use context_user;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use plagiarism_dixeo\local\presubmission_feedback_storage;
use plagiarism_dixeo\modules\assign;

defined('MOODLE_INTERNAL') || die();

/**
 * Pre-submission feedback (student Analyze step). Returns HTML feedback from AI review or an error message.
 */
class mod_assign_pre_submission extends external_api {

    /**
     * Parameters for execute: course module id only.
     *
     * @return \core_external\external_function_parameters
     */
    public static function execute_parameters(): \core_external\external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Course module ID (assign instance).'),
        ]);
    }

    /**
     * Return pre-submission feedback (HTML) so the student can improve before submitting.
     *
     * @param int $id Course module ID (assign instance).
     * @return string HTML fragment to insert after the submission header.
     * @throws \moodle_exception If capability or course module check fails.
     */
    public static function execute(int $id): string {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), ['id' => $id]);

        $context = context_user::instance($USER->id);
        require_capability('plagiarism/dixeo:mod_assign_pre_submission', $context);

        if ($params['id'] <= 0) {
            return self::feedback_error_html(get_string('assign_pre_submission_context_required', 'plagiarism_dixeo'));
        }

        get_coursemodule_from_id('assign', $params['id'], 0, false, MUST_EXIST);
        $assignmentcontext = assign::get_assignment_context($params['id']);
        $submissiondata = assign::get_submission_data($params['id'], $USER->id);

        $submission = $submissiondata['submission'] ?? null;
        $itemid = $submission ? (int) $submission->id : 0;
        if ($itemid <= 0) {
            return self::feedback_error_html(get_string('assign_pre_submission_context_required', 'plagiarism_dixeo'));
        }

        $existing = presubmission_feedback_storage::get_by_context(
            (int) $USER->id,
            'mod_assign',
            'submission',
            $itemid
        );
        if ($existing && trim((string) ($existing->feedback ?? '')) !== '') {
            return self::feedback_html_wrapper((string) $existing->feedback);
        }

        $evaluation = assign::evaluate(
            $assignmentcontext,
            $submissiondata,
            assign::EVALUATION_MODE_REVIEW
        );

        if (!empty($evaluation['error'])) {
            return self::feedback_error_html($evaluation['error']);
        }

        $feedbackhtml = $evaluation['feedback_html'] ?? '';
        presubmission_feedback_storage::create_record(
            (int) $USER->id,
            'mod_assign',
            'submission',
            $itemid,
            $feedbackhtml,
            isset($submission->timemodified) ? (int) $submission->timemodified : null,
            isset($submission->attemptnumber) ? (int) $submission->attemptnumber : null
        );

        return self::feedback_html_wrapper($feedbackhtml);
    }

    /**
     * Wrap AI feedback content in a styled container.
     *
     * @param string $html Raw HTML from the AI.
     * @return string
     */
    public static function feedback_html_wrapper(string $html): string {
        $context = context_system::instance();
        $clean = format_text($html, FORMAT_HTML, [
            'para'    => true,
            'filter'  => false,
            'context' => $context,
        ]);
        return '<div class="alert alert-info alert-block fade in alert-dismissible plagiarism-dixeo-review-feedback" role="alert">' .
            $clean .
            '</div>';
    }

    /**
     * Error message when review fails.
     *
     * @param string $message
     * @return string
     */
    protected static function feedback_error_html(string $message): string {
        return '<div class="alert alert-warning alert-block fade in alert-dismissible" role="alert">' .
            get_string('pluginname', 'plagiarism_dixeo') . ': ' . s($message) .
            '</div>';
    }

    /**
     * Return value of execute: HTML fragment.
     *
     * @return \core_external\external_value
     */
    public static function execute_returns(): \core_external\external_value {
        return new external_value(PARAM_RAW, 'HTML fragment for the analysis result.');
    }
}
