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
 * Poll an assign UX job with module-scoped capabilities.
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
use local_dixeo\dto\job_status;
use local_dixeo\external\response_factory;
use local_dixeo\external\service_factory;
use local_dixeo\service\assign_submission_reader;
use plagiarism_dixeo\authorship\authorship_persistence_adapter;


/**
 * Returns assign UX job status and mapped result for review or grade flows.
 *
 * Uses assign module capabilities instead of local/dixeo:generate so students can poll.
 */
class get_assign_job_status extends external_api {
    /** @var string Student review flow. */
    public const FLOW_REVIEW = 'review';

    /** @var string Teacher grade flow. */
    public const FLOW_GRADE = 'grade';

    /**
     * Describe parameters for job status poll.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'jobid' => new external_value(PARAM_RAW, 'Remote job UUID.'),
            'courseid' => new external_value(PARAM_INT, 'Course id.'),
            'cmid' => new external_value(PARAM_INT, 'Assign course module id.'),
            'flow' => new external_value(PARAM_ALPHA, 'review or grade.'),
        ]);
    }

    /**
     * Poll assign UX job status for review or grade flow.
     *
     * @param string $jobid Job id.
     * @param int $courseid Course id.
     * @param int $cmid Cm id.
     * @param string $flow review|grade.
     * @return array
     * @throws \moodle_exception
     */
    public static function execute(string $jobid, int $courseid, int $cmid, string $flow): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'jobid' => $jobid,
            'courseid' => $courseid,
            'cmid' => $cmid,
            'flow' => $flow,
        ]);

        $flow = strtolower($params['flow']);
        if ($flow === self::FLOW_REVIEW) {
            $modcontext = assign_access::require_student_analyze($params['cmid']);
        } else if ($flow === self::FLOW_GRADE) {
            $modcontext = assign_access::require_teacher_grade($params['cmid']);
        } else {
            throw new \moodle_exception('invalidparameter');
        }

        $cm = get_coursemodule_from_id('assign', $params['cmid'], 0, false, MUST_EXIST);
        if ((int) $cm->course !== (int) $params['courseid']) {
            throw new \moodle_exception('invalidcoursemodule');
        }

        try {
            $service = service_factory::get_assign_ux_service(new authorship_persistence_adapter());
            $status = $service->get_status($params['jobid'], $params['courseid'], (int) $USER->id);

            if ($status->status === job_status::STATUS_PENDING || $status->status === job_status::STATUS_PROCESSING) {
                return self::pending_payload($params['jobid'], $status, $params['courseid'], $flow);
            }

            if ($status->status === job_status::STATUS_FAILED) {
                $msg = $status->errormessage ?: get_string('assign_ux_result_missing', 'local_dixeo');
                return self::failed_payload($params['jobid'], $params['courseid'], $flow, $msg);
            }

            if ($flow === self::FLOW_REVIEW) {
                return self::completed_review($params['jobid'], $status, $params['courseid'], $modcontext, $service);
            }
            return self::completed_grade($params['jobid'], $status, $params['courseid'], (int) $cm->id, $service);
        } catch (\required_capability_exception | \require_login_exception $e) {
            throw $e;
        } catch (\Throwable $e) {
            $safe = response_factory::safe_message($e);
            return self::failed_payload($params['jobid'], $params['courseid'], $flow, $safe);
        }
    }

    /**
     * Pending/processing response shape.
     *
     * @param string $jobid Job id.
     * @param job_status $status Status.
     * @param int $courseid Course id.
     * @param string $flow Flow.
     * @return array
     */
    protected static function pending_payload(string $jobid, job_status $status, int $courseid, string $flow): array {
        $base = [
            'jobid' => $jobid,
            'status' => $status->status,
            'progress' => $status->progress,
            'courseid' => $courseid,
            'errormessage' => '',
        ];
        if ($flow === self::FLOW_REVIEW) {
            $base['html'] = '';
            return $base;
        }
        return array_merge($base, [
            'gradingmethod' => 'simple',
            'analysis' => '',
            'feedback' => '',
        ]);
    }

    /**
     * Failed response shape.
     *
     * @param string $jobid Job id.
     * @param int $courseid Course id.
     * @param string $flow Flow.
     * @param string $message Error message.
     * @return array
     */
    protected static function failed_payload(string $jobid, int $courseid, string $flow, string $message): array {
        if ($flow === self::FLOW_REVIEW) {
            return [
                'jobid' => $jobid,
                'status' => 'failed',
                'progress' => 100,
                'courseid' => $courseid,
                'html' => '',
                'errormessage' => $message,
            ];
        }
        return [
            'jobid' => $jobid,
            'status' => 'failed',
            'progress' => 100,
            'courseid' => $courseid,
            'gradingmethod' => 'simple',
            'analysis' => '',
            'feedback' => '',
            'errormessage' => $message,
        ];
    }

    /**
     * Map a completed review job into the WS payload.
     *
     * @param string $jobid Job id.
     * @param job_status $status Status.
     * @param int $courseid Course id.
     * @param \context $modcontext Module context.
     * @param \local_dixeo\service\assign_ux_service $service Service.
     * @return array
     */
    protected static function completed_review(
        string $jobid,
        job_status $status,
        int $courseid,
        \context $modcontext,
        $service
    ): array {
        $mapped = $service->map_review_result($status->result);
        if (isset($mapped['error'])) {
            return self::failed_payload($jobid, $courseid, self::FLOW_REVIEW, $mapped['error']);
        }
        $clean = format_text($mapped['feedback_html'] ?? '', FORMAT_HTML, [
            'para' => true,
            'filter' => false,
            'context' => $modcontext,
        ]);
        $classes = 'alert alert-info alert-block fade in alert-dismissible plagiarism-dixeo-review-feedback';
        $html = '<div class="' . $classes . '" role="alert">' . $clean . '</div>';
        return [
            'jobid' => $jobid,
            'status' => 'completed',
            'progress' => 100,
            'courseid' => $courseid,
            'html' => $html,
            'errormessage' => '',
        ];
    }

    /**
     * Map a completed grade job into the WS payload.
     *
     * @param string $jobid Job id.
     * @param job_status $status Status.
     * @param int $courseid Course id.
     * @param int $cmid Cm id.
     * @param \local_dixeo\service\assign_ux_service $service Service.
     * @return array
     */
    protected static function completed_grade(
        string $jobid,
        job_status $status,
        int $courseid,
        int $cmid,
        $service
    ): array {
        $reader = new assign_submission_reader();
        $assignmentcontext = $reader->get_assignment_context($cmid, $courseid);
        $mapped = $service->map_grade_result($assignmentcontext, $status->result);
        if (isset($mapped['error'])) {
            return self::failed_payload($jobid, $courseid, self::FLOW_GRADE, $mapped['error']);
        }

        $modcontext = \context_module::instance($cmid);
        $analysis = format_text($mapped['analysis'] ?? '', FORMAT_HTML, [
            'para' => false,
            'filter' => false,
            'context' => $modcontext,
        ]);
        $feedback = format_text($mapped['feedback'] ?? '', FORMAT_HTML, [
            'para' => false,
            'filter' => false,
            'context' => $modcontext,
        ]);

        $out = [
            'jobid' => $jobid,
            'status' => 'completed',
            'progress' => 100,
            'courseid' => $courseid,
            'gradingmethod' => $mapped['gradingmethod'],
            'analysis' => $analysis,
            'feedback' => $feedback,
            'errormessage' => '',
        ];
        if (isset($mapped['grade'])) {
            $out['grade'] = $mapped['grade'];
        }
        if (isset($mapped['criteria'])) {
            $out['criteria'] = $mapped['criteria'];
        }
        return $out;
    }

    /**
     * Return structure for job status poll.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        $criterion = new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Criterion DB id.'),
            'remark' => new external_value(PARAM_TEXT, 'Remark.'),
            'score' => new external_value(PARAM_FLOAT, 'Score (guide).', VALUE_OPTIONAL),
            'levelid' => new external_value(PARAM_INT, 'Level id (rubric).', VALUE_OPTIONAL),
        ], 'Criterion', VALUE_OPTIONAL);

        return new external_single_structure([
            'jobid' => new external_value(PARAM_RAW, 'Remote job id.'),
            'status' => new external_value(PARAM_ALPHA, 'Job status.'),
            'progress' => new external_value(PARAM_INT, 'Progress 0–100.'),
            'courseid' => new external_value(PARAM_INT, 'Course id.'),
            'html' => new external_value(PARAM_RAW, 'Review HTML (review flow).', VALUE_OPTIONAL),
            'gradingmethod' => new external_value(PARAM_ALPHA, 'Grading method (grade flow).', VALUE_OPTIONAL),
            'analysis' => new external_value(PARAM_RAW, 'Analysis HTML (grade flow).', VALUE_OPTIONAL),
            'feedback' => new external_value(PARAM_RAW, 'Feedback HTML (grade flow).', VALUE_OPTIONAL),
            'grade' => new external_value(PARAM_FLOAT, 'Simple grade.', VALUE_OPTIONAL),
            'criteria' => new external_multiple_structure($criterion, 'Criteria.', VALUE_OPTIONAL),
            'errormessage' => new external_value(PARAM_TEXT, 'Client-safe error message.'),
        ]);
    }
}
