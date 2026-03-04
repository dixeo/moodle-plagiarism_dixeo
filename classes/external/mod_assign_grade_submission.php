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
 * External API: grade assign submission with AI (grader view).
 *
 * @package    plagiarism_dixeo
 * @author     Josemaria Bolanos <admin@mako.digital>
 * @copyright  2026 Dixeo.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo\external;

use context_module;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use plagiarism_dixeo\local\author_storage;
use plagiarism_dixeo\local\grade_feedback_storage;
use plagiarism_dixeo\modules\assign;

defined('MOODLE_INTERNAL') || die();

/**
 * Grade assign submission (grader view). Returns grading method, analysis, feedback, and optional grade/criteria.
 */
class mod_assign_grade_submission extends external_api {

    /**
     * Parameters for execute: course module id and user id being graded.
     *
     * @return \core_external\external_function_parameters
     */
    public static function execute_parameters(): \core_external\external_function_parameters {
        return new external_function_parameters([
            'id'     => new external_value(PARAM_INT, 'Course module ID (assign instance).'),
            'userid' => new external_value(PARAM_INT, 'User ID being graded.'),
        ]);
    }

    /**
     * Grade submission with AI (grader view). Uses itemid = cmid for authorship record lookup.
     *
     * @param int $id Course module ID (assign instance).
     * @param int $userid User ID being graded.
     * @return array Keys: gradingmethod, analysis, feedback; optionally grade (simple) or criteria (guide/rubric).
     * @throws \moodle_exception If capability or course module check fails.
     */
    public static function execute(int $id, int $userid): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'id'     => $id,
            'userid' => $userid,
        ]);

        $cm = get_coursemodule_from_id('assign', $params['id'], 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        require_capability('plagiarism/dixeo:mod_assign_grade_submission', $context);

        $assignmentcontext = assign::get_assignment_context($params['id']);
        $submissiondata = assign::get_submission_data($params['id'], $params['userid']);
        $submission = $submissiondata['submission'] ?? null;
        $submissionid = $submission ? (int) $submission->id : 0;
        if ($submissionid <= 0) {
            $submissionid = assign::get_latest_submission_id($params['id'], $params['userid']);
        }
        if ($submissionid > 0) {
            $existing = grade_feedback_storage::get_by_context(
                (int) $params['userid'],
                'mod_assign',
                'submission',
                $submissionid
            );
            if ($existing) {
                $out = grade_feedback_storage::record_to_result($existing);
                $out['feedback'] = assign::add_feedback_disclaimer($out['feedback']);
                return $out;
            }
        }
        $authorship = $submissionid > 0 ? author_storage::get_record_by_context(
            $params['userid'],
            'mod_assign',
            'submission',
            $submissionid
        ) : null;
        $result = assign::evaluate(
            $assignmentcontext,
            $submissiondata,
            assign::EVALUATION_MODE_GRADING,
            $authorship
        );

        if (!empty($result['error'])) {
            return [
                'gradingmethod' => $assignmentcontext['grading_method'] ?? 'simple',
                'analysis'      => $result['error'],
                'feedback'      => '',
            ];
        }

        if ($submissionid > 0) {
            $srcTime = ($submission && isset($submission->timemodified)) ? (int) $submission->timemodified : null;
            $srcAttempt = ($submission && isset($submission->attemptnumber)) ? (int) $submission->attemptnumber : null;
            grade_feedback_storage::create_record(
                (int) $params['userid'],
                'mod_assign',
                'submission',
                $submissionid,
                $result,
                $srcTime,
                $srcAttempt
            );
        }
        $result['feedback'] = assign::add_feedback_disclaimer($result['feedback']);
        return $result;
    }

    /**
     * Return value of execute: gradingmethod, analysis, feedback, optional grade/criteria.
     *
     * @return \core_external\external_single_structure
     */
    public static function execute_returns(): \core_external\external_single_structure {
        $criterion = new external_single_structure([
            'id'      => new external_value(PARAM_INT, 'Criterion DB id.'),
            'remark'  => new external_value(PARAM_TEXT, 'Remark for the student.'),
            'score'   => new external_value(PARAM_FLOAT, 'Score (marking guide only).', VALUE_OPTIONAL),
            'levelid' => new external_value(PARAM_INT, 'Level DB id (rubric only).', VALUE_OPTIONAL),
        ], 'Criterion fill.');
        return new external_single_structure([
            'gradingmethod' => new external_value(PARAM_ALPHA, 'One of: simple, guide, rubric.'),
            'analysis'      => new external_value(PARAM_RAW, 'Analysis text for the teacher.'),
            'feedback'      => new external_value(PARAM_RAW, 'Feedback HTML for the student.'),
            'grade'         => new external_value(PARAM_FLOAT, 'Numeric grade (simple only).', VALUE_OPTIONAL),
            'criteria'      => new external_multiple_structure($criterion, 'Criteria (guide/rubric only).', VALUE_OPTIONAL),
        ], 'Grading result for front-end injection.');
    }
}
