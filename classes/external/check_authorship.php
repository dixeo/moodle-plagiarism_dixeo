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
use local_dixeo\exception\consent_required_exception;
use local_dixeo\exception\job_quota_exceeded_exception;
use local_dixeo\external\response_factory;
use local_dixeo\external\service_factory;
use local_dixeo\service\assign_submission_reader;
use local_dixeo\service\assign_ux_service;
use plagiarism_dixeo\authorship\author_storage;
use plagiarism_dixeo\authorship\authorship;
use plagiarism_dixeo\authorship\authorship_persistence_adapter;
use plagiarism_dixeo\event\authorship_checked;
use plagiarism_dixeo\feature_settings;


/**
 * Authorship confidence (+ optional quiz) via Dixeo_API jobs.
 *
 * Questions are never returned here: the client starts the quiz clock with start_verification
 * and reads the questions from get_verification_quiz. Concurrent checks of one submission are
 * serialised by a lock, and the stored record is read only under it: a settled result is
 * returned as stored, an open quiz is resumed, and only otherwise are new jobs sent, within the
 * student's job quota for the assignment.
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

        assign_access::require_student_authorship($params['id']);
        $cm = get_coursemodule_from_id('assign', $params['id'], 0, false, MUST_EXIST);
        $courseid = (int) $cm->course;

        $threshold = (float) \plagiarism_plugin_dixeo::get_activity_config((int) $cm->id)[feature_settings::THRESHOLD];

        $modulecontext = \context_module::instance((int) $cm->id);
        if (!authorship::is_enabled($modulecontext)) {
            return self::result_payload(100.0, 0, '', 'completed', $courseid, $threshold);
        }

        try {
            $adapter = new authorship_persistence_adapter();
            $service = service_factory::get_assign_ux_service($adapter);
            $reader = new assign_submission_reader();
            $submissiondata = $reader->get_submission_data((int) $cm->id, (int) $USER->id, $courseid);
            $itemid = isset($submissiondata['submission']->id) ? (int) $submissiondata['submission']->id : 0;

            $lock = authorship::acquire_lock('authorship_submission_' . $itemid);
            if (!$lock) {
                $msg = get_string('assign_ux_result_missing', 'local_dixeo');
                return self::result_payload(50.0, 0, $msg, 'failed', $courseid, $threshold);
            }
            try {
                $existing = $itemid > 0
                    ? author_storage::get_record_by_context((int) $USER->id, 'mod_assign', 'submission', $itemid)
                    : null;
                if (authorship::is_resumable_record($existing)) {
                    return self::result_payload(
                        (float) ($existing->initial_confidence ?? 50.0),
                        (int) $existing->id,
                        '',
                        'completed',
                        $courseid,
                        $threshold
                    );
                }
                // A finished result is final for this submission: it is returned, never re-run.
                if ($existing && authorship::is_settled($existing)) {
                    return self::result_payload(
                        (float) ($existing->final_confidence ?? $existing->initial_confidence ?? 50.0),
                        0,
                        '',
                        'completed',
                        $courseid,
                        $threshold
                    );
                }

                return self::run_check($service, $cm, $courseid, $itemid, $threshold);
            } finally {
                $lock->release();
            }
        } catch (\required_capability_exception | \require_login_exception | consent_required_exception $e) {
            throw $e;
        } catch (job_quota_exceeded_exception $e) {
            return self::result_payload(50.0, 0, $e->getMessage(), 'failed', $courseid, $threshold);
        } catch (\Throwable $e) {
            $safe = response_factory::safe_message($e);
            return self::result_payload(50.0, 0, $safe, 'failed', $courseid, $threshold);
        }
    }

    /**
     * Run the confidence step, and the quiz step when required, then store the result.
     *
     * Caller holds the authorship_submission_<itemid> lock. When the quiz is on, the confidence
     * step reserves room in the job quota for the quiz step, so a student is not charged for a
     * confidence job whose quiz would then be refused.
     *
     * @param assign_ux_service $service Dixeo assign service.
     * @param \stdClass $cm Assignment course module.
     * @param int $courseid Course id.
     * @param int $itemid Submission id, or 0 when there is none to store against.
     * @param float $threshold Assignment confidence threshold.
     * @return array
     * @throws job_quota_exceeded_exception When the student's job quota for the assignment is used up.
     */
    private static function run_check(
        assign_ux_service $service,
        \stdClass $cm,
        int $courseid,
        int $itemid,
        float $threshold
    ): array {
        global $USER;

        $quizon = \plagiarism_dixeo\feature_settings::verification_quiz_on(\context_module::instance((int) $cm->id));
        $confidenceop = $service->submit_authorship_and_wait(
            $courseid,
            (int) $cm->id,
            (int) $USER->id,
            assign_ux_service::MODE_CONFIDENCE,
            quota: assign_access::student_job_quota($quizon ? 2 : 1)
        );
        if (!$confidenceop->completed) {
            $msg = get_string('assign_ux_result_missing', 'local_dixeo');
            return self::result_payload(50.0, 0, $msg, 'failed', $courseid, $threshold, $confidenceop->jobid);
        }

        $mapped = $service->map_authorship_result(assign_ux_service::MODE_CONFIDENCE, $confidenceop->result);
        if (isset($mapped['error'])) {
            return self::result_payload(50.0, 0, $mapped['error'], 'failed', $courseid, $threshold, $confidenceop->jobid);
        }

        $score = (float) $mapped['confidence'];
        $explanation = (string) ($mapped['explanation'] ?? '');
        $quizdata = null;
        $lastjobid = $confidenceop->jobid;

        if ($quizon && authorship::is_quiz_required($score, $threshold)) {
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
                ],
                quota: assign_access::student_job_quota()
            );
            if (!$quizop->completed) {
                $msg = get_string('assign_ux_result_missing', 'local_dixeo');
                return self::result_payload($score, 0, $msg, 'failed', $courseid, $threshold, $quizop->jobid);
            }

            $quizmapped = $service->map_authorship_result(assign_ux_service::MODE_QUIZ, $quizop->result);
            if (isset($quizmapped['error'])) {
                return self::result_payload($score, 0, $quizmapped['error'], 'failed', $courseid, $threshold, $quizop->jobid);
            }

            $quizdata = ['questions' => $quizmapped['questions']];
            $lastjobid = $quizop->jobid;
        }

        $record = null;
        if ($itemid > 0) {
            $record = $service->apply_authorship_confidence(
                (int) $USER->id,
                $itemid,
                $score,
                $quizdata,
                $explanation
            );
        }
        if ($record) {
            authorship_checked::create([
                'context' => \context_module::instance((int) $cm->id),
                'objectid' => (int) $record->id,
                'relateduserid' => (int) $USER->id,
                'other' => ['status' => (string) $record->status],
            ])->trigger();
        }

        $recordid = ($record && $quizdata) ? (int) $record->id : 0;

        return self::result_payload(
            $score,
            $recordid,
            '',
            'completed',
            $courseid,
            $threshold,
            $lastjobid
        );
    }

    /**
     * Build the WS result payload.
     *
     * @param float $score Confidence score.
     * @param int $recordid Record id of a quiz to sit, or 0.
     * @param string $errormessage Error message.
     * @param string $status Status.
     * @param int $courseid Course id.
     * @param float $threshold Assignment confidence threshold.
     * @param string $jobid Job id.
     * @return array
     */
    protected static function result_payload(
        float $score,
        int $recordid,
        string $errormessage,
        string $status,
        int $courseid,
        float $threshold,
        string $jobid = ''
    ): array {
        return [
            'score' => $score,
            'threshold' => $threshold,
            'record_id' => $recordid,
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
            'record_id' => new external_value(
                PARAM_INT,
                'Authorship record id when a verification quiz is waiting; its questions come from get_verification_quiz.'
            ),
            'jobid' => new external_value(PARAM_RAW, 'Last remote job id.'),
            'status' => new external_value(PARAM_ALPHA, 'Job/flow status.'),
            'progress' => new external_value(PARAM_INT, 'Progress 0–100.'),
            'courseid' => new external_value(PARAM_INT, 'Course id for polling.'),
            'errormessage' => new external_value(PARAM_TEXT, 'Client-safe error message when failed.'),
        ], 'Authorship check result.');
    }
}
