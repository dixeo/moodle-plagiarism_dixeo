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
 * External API: grade assign submission with AI (grader view).
 *
 * @package    plagiarism_dixeo
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_dixeo\external\response_factory;
use local_dixeo\external\service_factory;
use local_dixeo\service\assign_submission_reader;
use plagiarism_dixeo\authorship\authorship_persistence_adapter;


/**
 * Submits an assign grade job; client polls get_assign_job_status for the result.
 */
class mod_assign_grade_submission extends external_api {
    /**
     * Describe parameters for grade Assist.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Course module ID (assign instance).'),
            'userid' => new external_value(PARAM_INT, 'User ID being graded.'),
        ]);
    }

    /**
     * Submit teacher Assist grading job (async).
     *
     * @param int $id Course module ID.
     * @param int $userid User ID being graded.
     * @return array
     * @throws \moodle_exception
     */
    public static function execute(int $id, int $userid): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'id' => $id,
            'userid' => $userid,
        ]);

        assign_access::require_teacher_grade($params['id']);
        $cm = get_coursemodule_from_id('assign', $params['id'], 0, false, MUST_EXIST);
        $courseid = (int) $cm->course;

        try {
            $reader = new assign_submission_reader();
            $submissiondata = $reader->get_submission_data((int) $cm->id, $params['userid'], $courseid);
            $submissionid = isset($submissiondata['submission']->id) ? (int) $submissiondata['submission']->id : 0;

            $adapter = new authorship_persistence_adapter();
            $authorship = $submissionid > 0
                ? $adapter->get_authorship_by_context($params['userid'], 'mod_assign', 'submission', $submissionid)
                : null;

            $service = service_factory::get_assign_ux_service($adapter);
            $operation = $service->submit_grade(
                $courseid,
                (int) $cm->id,
                $params['userid'],
                $authorship
            );

            return [
                'jobid' => $operation->jobid,
                'status' => $operation->status ?? 'pending',
                'progress' => $operation->progress,
                'courseid' => $courseid,
                'gradingmethod' => 'simple',
                'analysis' => '',
                'feedback' => '',
                'errormessage' => '',
            ];
        } catch (\required_capability_exception | \require_login_exception $e) {
            throw $e;
        } catch (\Throwable $e) {
            $safe = response_factory::safe_message($e);
            return [
                'jobid' => '',
                'status' => 'failed',
                'progress' => 0,
                'courseid' => $courseid,
                'gradingmethod' => 'simple',
                'analysis' => '',
                'feedback' => '',
                'errormessage' => $safe,
            ];
        }
    }

    /**
     * Return structure for grade Assist.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        $criterion = new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Criterion DB id.'),
            'remark' => new external_value(PARAM_TEXT, 'Remark for the student.'),
            'score' => new external_value(PARAM_FLOAT, 'Score (marking guide only).', VALUE_OPTIONAL),
            'levelid' => new external_value(PARAM_INT, 'Level DB id (rubric only).', VALUE_OPTIONAL),
        ], 'Criterion fill.');
        return new external_single_structure([
            'jobid' => new external_value(PARAM_RAW, 'Remote job id.'),
            'status' => new external_value(PARAM_ALPHA, 'Job status.'),
            'progress' => new external_value(PARAM_INT, 'Progress 0–100.'),
            'courseid' => new external_value(PARAM_INT, 'Course id for polling.'),
            'gradingmethod' => new external_value(PARAM_ALPHA, 'One of: simple, guide, rubric.'),
            'analysis' => new external_value(PARAM_RAW, 'Analysis text for the teacher.'),
            'feedback' => new external_value(PARAM_RAW, 'Feedback HTML for the student.'),
            'grade' => new external_value(PARAM_FLOAT, 'Numeric grade (simple only).', VALUE_OPTIONAL),
            'criteria' => new external_multiple_structure($criterion, 'Criteria (guide/rubric only).', VALUE_OPTIONAL),
            'errormessage' => new external_value(PARAM_TEXT, 'Client-safe error message when failed.'),
        ], 'Grading job submit / result envelope.');
    }
}
