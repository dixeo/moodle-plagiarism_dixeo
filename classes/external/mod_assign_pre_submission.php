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
 * External API: pre-submission feedback (student Analyze step).
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
use local_dixeo\external\response_factory;
use local_dixeo\external\service_factory;
use plagiarism_dixeo\authorship\authorship_persistence_adapter;


/**
 * Submits an assign review job; client polls get_assign_job_status for the result.
 */
class mod_assign_pre_submission extends external_api {
    /**
     * Describe parameters for pre-submission Analyze.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Course module ID (assign instance).'),
        ]);
    }

    /**
     * Submit student Analyze job (async).
     *
     * @param int $id Course module ID.
     * @return array{jobid: string, status: string, progress: int, courseid: int, html: string, errormessage: string}
     * @throws \moodle_exception
     */
    public static function execute(int $id): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'id' => $id,
        ]);

        assign_access::require_student_analyze($params['id']);
        $cm = get_coursemodule_from_id('assign', $params['id'], 0, false, MUST_EXIST);
        $courseid = (int) $cm->course;

        try {
            $service = service_factory::get_assign_ux_service(new authorship_persistence_adapter());
            $operation = $service->submit_review($courseid, (int) $cm->id, (int) $USER->id);

            return [
                'jobid' => $operation->jobid,
                'status' => $operation->status ?? 'pending',
                'progress' => $operation->progress,
                'courseid' => $courseid,
                'html' => '',
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
                'html' => '',
                'errormessage' => $safe,
            ];
        }
    }

    /**
     * Return structure for pre-submission Analyze.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'jobid' => new external_value(PARAM_RAW, 'Remote job id.'),
            'status' => new external_value(PARAM_ALPHA, 'Job status (pending, processing, completed, failed).'),
            'progress' => new external_value(PARAM_INT, 'Progress 0–100.'),
            'courseid' => new external_value(PARAM_INT, 'Course id for polling.'),
            'html' => new external_value(PARAM_RAW, 'HTML fragment (empty until polled).'),
            'errormessage' => new external_value(PARAM_TEXT, 'Client-safe error message when failed.'),
        ]);
    }
}
