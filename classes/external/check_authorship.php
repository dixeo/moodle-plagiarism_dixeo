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
 * External API: check authorship of submission text before submit.
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
use local_dixeo\service\assign_submission_reader;
use local_dixeo\service\assign_ux_service;
use plagiarism_dixeo\authorship\authorship;
use plagiarism_dixeo\authorship\authorship_persistence_adapter;


/**
 * Authorship confidence (+ optional quiz) via Dixeo_API jobs.
 */
class check_authorship extends external_api {
    /**
     * Describe parameters for check_authorship.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'component' => new external_value(PARAM_COMPONENT, 'Component (e.g. mod_assign).'),
            'area' => new external_value(PARAM_ALPHAEXT, 'Area within the component (e.g. submission).'),
            'id' => new external_value(PARAM_INT, 'Course module id for mod_assign/submission.'),
        ]);
    }

    /**
     * Run authorship check for the current user's submission.
     *
     * @param string $component Component name.
     * @param string $area Area name.
     * @param int $id Course module id.
     * @return array
     * @throws \moodle_exception
     */
    public static function execute(string $component, string $area, int $id): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'component' => $component,
            'area' => $area,
            'id' => $id,
        ]);

        if ($params['component'] !== 'mod_assign' || $params['area'] !== 'submission') {
            throw new \moodle_exception('invalidparameter');
        }

        assign_access::require_student_analyze($params['id']);
        $cm = get_coursemodule_from_id('assign', $params['id'], 0, false, MUST_EXIST);
        $courseid = (int) $cm->course;

        if (!authorship::is_enabled()) {
            return self::result_payload(100.0, 0, '', '', 'completed', $courseid);
        }

        try {
            $adapter = new authorship_persistence_adapter();
            $service = service_factory::get_assign_ux_service($adapter);
            $reader = new assign_submission_reader();
            $submissiondata = $reader->get_submission_data((int) $cm->id, (int) $USER->id, $courseid);
            $itemid = isset($submissiondata['submission']->id) ? (int) $submissiondata['submission']->id : 0;

            $confidenceop = $service->submit_authorship_and_wait(
                $courseid,
                (int) $cm->id,
                (int) $USER->id,
                assign_ux_service::MODE_CONFIDENCE
            );
            if (!$confidenceop->completed) {
                $msg = get_string('assign_ux_result_missing', 'local_dixeo');
                return self::result_payload(50.0, 0, '', $msg, 'failed', $courseid, $confidenceop->jobid);
            }

            $mapped = $service->map_authorship_result(assign_ux_service::MODE_CONFIDENCE, $confidenceop->result);
            if (isset($mapped['error'])) {
                return self::result_payload(50.0, 0, '', $mapped['error'], 'failed', $courseid, $confidenceop->jobid);
            }

            $score = (float) $mapped['confidence'];
            if (!authorship::is_quiz_required($score)) {
                return self::result_payload(
                    $score,
                    0,
                    '',
                    '',
                    'completed',
                    $courseid,
                    $confidenceop->jobid
                );
            }

            $counts = authorship::get_question_counts();
            $quizop = $service->submit_authorship_and_wait(
                $courseid,
                (int) $cm->id,
                (int) $USER->id,
                assign_ux_service::MODE_QUIZ,
                [
                    'trueFalseCount' => $counts['true_false'],
                    'multipleChoiceCount' => $counts['multiple_choice'],
                    'openCount' => $counts['open'],
                    'initialConfidence' => $score,
                ]
            );
            if (!$quizop->completed) {
                $msg = get_string('assign_ux_result_missing', 'local_dixeo');
                return self::result_payload($score, 0, '', $msg, 'failed', $courseid, $quizop->jobid);
            }

            $quizmapped = $service->map_authorship_result(assign_ux_service::MODE_QUIZ, $quizop->result);
            if (isset($quizmapped['error'])) {
                return self::result_payload($score, 0, '', $quizmapped['error'], 'failed', $courseid, $quizop->jobid);
            }

            $quizdata = ['questions' => $quizmapped['questions']];
            $record = null;
            if ($itemid > 0) {
                $record = $service->apply_authorship_confidence(
                    (int) $USER->id,
                    $itemid,
                    $score,
                    $quizdata
                );
            }

            $quizhtml = '';
            $recordid = 0;
            if ($record) {
                $recordid = (int) $record->id;
                $quizhtml = authorship::render_quiz_form($record);
            }

            return self::result_payload(
                $score,
                $recordid,
                $quizhtml,
                '',
                'completed',
                $courseid,
                $quizop->jobid
            );
        } catch (\required_capability_exception | \require_login_exception $e) {
            throw $e;
        } catch (\Throwable $e) {
            $safe = response_factory::safe_message($e);
            return self::result_payload(50.0, 0, '', $safe, 'failed', $courseid);
        }
    }

    /**
     * Build the WS result payload.
     *
     * @param float $score Confidence score.
     * @param int $recordid Record id.
     * @param string $quizhtml Quiz HTML.
     * @param string $errormessage Error message.
     * @param string $status Status.
     * @param int $courseid Course id.
     * @param string $jobid Job id.
     * @return array
     */
    protected static function result_payload(
        float $score,
        int $recordid,
        string $quizhtml,
        string $errormessage,
        string $status,
        int $courseid,
        string $jobid = ''
    ): array {
        return [
            'score' => $score,
            'threshold' => (float) authorship::get_confidence_threshold(),
            'record_id' => $recordid,
            'quiz_html' => $quizhtml,
            'jobid' => $jobid,
            'status' => $status,
            'progress' => $status === 'completed' || $status === 'failed' ? 100 : 0,
            'courseid' => $courseid,
            'errormessage' => $errormessage,
        ];
    }

    /**
     * Return structure for check_authorship.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'score' => new external_value(PARAM_FLOAT, 'Authorship confidence 0-100.'),
            'threshold' => new external_value(PARAM_FLOAT, 'Threshold below which quiz is required.'),
            'record_id' => new external_value(PARAM_INT, 'Authorship record id if quiz was generated, else 0.'),
            'quiz_html' => new external_value(PARAM_RAW, 'Quiz form HTML if quiz required, else empty.'),
            'jobid' => new external_value(PARAM_RAW, 'Last remote job id.'),
            'status' => new external_value(PARAM_ALPHA, 'Job/flow status.'),
            'progress' => new external_value(PARAM_INT, 'Progress 0–100.'),
            'courseid' => new external_value(PARAM_INT, 'Course id for polling.'),
            'errormessage' => new external_value(PARAM_TEXT, 'Client-safe error message when failed.'),
        ], 'Authorship check result.');
    }
}
