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
 * Adhoc task that analyses one assignment submission.
 *
 * @package    plagiarism_dixeo
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo\task;

use local_dixeo\external\service_factory;
use local_dixeo\service\assign_ux_service;
use plagiarism_dixeo\authorship\author_storage;
use plagiarism_dixeo\authorship\authorship;
use plagiarism_dixeo\authorship\authorship_persistence_adapter;
use plagiarism_dixeo\automation\analysis_gate;
use plagiarism_dixeo\event\analysis_requested;
use plagiarism_dixeo\event\authorship_checked;
use plagiarism_dixeo\feedback\presubmission_feedback_storage;

/**
 * Runs the Dixeo review and authorship checks for a submission outside the student's request.
 *
 * Failures are rethrown so Moodle retries the task with its usual backoff; the analysis is
 * rerun from scratch on retry rather than resumed, because a partial result would be
 * misleading to both student and teacher.
 */
class analyze_submission extends \core\task\adhoc_task {
    /** @var string Component the stored analysis belongs to. */
    private const COMPONENT = 'mod_assign';

    /** @var string Area the stored analysis belongs to. */
    private const AREA = 'submission';

    /**
     * Human readable task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_analyze_submission', 'plagiarism_dixeo');
    }

    /**
     * Analyse the submission named in the task data.
     *
     * @throws \moodle_exception When the analysis cannot be completed.
     */
    public function execute(): void {
        global $DB;

        $data = (array) $this->get_custom_data();
        $cmid = (int) ($data['cmid'] ?? 0);
        $courseid = (int) ($data['courseid'] ?? 0);
        $userid = (int) ($data['userid'] ?? 0);
        $submissionid = (int) ($data['submissionid'] ?? 0);

        if ($cmid <= 0 || $courseid <= 0 || $userid <= 0 || $submissionid <= 0) {
            mtrace('plagiarism_dixeo: incomplete task data, nothing to analyse.');
            return;
        }

        // An administrator or teacher may have opted out since the submission was made.
        if (!analysis_gate::is_available() || !analysis_gate::is_enabled_for_activity($cmid)) {
            mtrace("plagiarism_dixeo: analysis is switched off for course module $cmid, skipping.");
            return;
        }

        $submission = $DB->get_record(
            'assign_submission',
            ['id' => $submissionid, 'userid' => $userid],
            'id,timemodified,attemptnumber'
        );
        if (!$submission) {
            mtrace("plagiarism_dixeo: submission $submissionid no longer exists, skipping.");
            return;
        }

        $context = \context_module::instance($cmid);
        analysis_requested::create([
            'context' => $context,
            'relateduserid' => $userid,
            'other' => ['itemid' => $submissionid],
        ])->trigger();

        $service = service_factory::get_assign_ux_service(new authorship_persistence_adapter());

        $this->store_review($service, $context, $submission, $courseid, $cmid, $userid);
        $this->store_authorship($service, $context, $submission, $courseid, $cmid, $userid);
    }

    /**
     * Run the pre-submission review and store the feedback it produced.
     *
     * @param assign_ux_service $service Dixeo assign service.
     * @param \context_module $context Activity context.
     * @param \stdClass $submission Submission being analysed.
     * @param int $courseid Course id.
     * @param int $cmid Assignment course module id.
     * @param int $userid Student user id.
     * @throws \moodle_exception When the review does not complete.
     */
    private function store_review(
        assign_ux_service $service,
        \context_module $context,
        \stdClass $submission,
        int $courseid,
        int $cmid,
        int $userid
    ): void {
        global $DB;

        $operation = $service->submit_review_and_wait($courseid, $cmid, $userid);
        if (!$operation->completed) {
            throw new \moodle_exception('automation_review_incomplete', 'plagiarism_dixeo');
        }

        $mapped = $service->map_review_result($operation->result);
        if (isset($mapped['error'])) {
            throw new \moodle_exception('automation_review_failed', 'plagiarism_dixeo', '', $mapped['error']);
        }

        $transaction = $DB->start_delegated_transaction();
        presubmission_feedback_storage::create_record(
            $userid,
            self::COMPONENT,
            self::AREA,
            (int) $submission->id,
            $mapped['feedback_html'],
            (int) $submission->timemodified,
            (int) $submission->attemptnumber
        );
        $transaction->allow_commit();
    }

    /**
     * Run the authorship check and store its result, including any verification quiz.
     *
     * @param assign_ux_service $service Dixeo assign service.
     * @param \context_module $context Activity context.
     * @param \stdClass $submission Submission being analysed.
     * @param int $courseid Course id.
     * @param int $cmid Assignment course module id.
     * @param int $userid Student user id.
     * @throws \moodle_exception When the authorship check does not complete.
     */
    private function store_authorship(
        assign_ux_service $service,
        \context_module $context,
        \stdClass $submission,
        int $courseid,
        int $cmid,
        int $userid
    ): void {
        global $DB;

        if (!authorship::is_enabled()) {
            return;
        }

        $existing = author_storage::get_record_by_context($userid, self::COMPONENT, self::AREA, (int) $submission->id);
        if ($existing && self::authorship_already_settled($existing)) {
            mtrace("plagiarism_dixeo: authorship already recorded for submission {$submission->id}, skipping.");
            return;
        }

        $confidence = $this->run_authorship_step($service, $courseid, $cmid, $userid, assign_ux_service::MODE_CONFIDENCE);
        $score = (float) $confidence['confidence'];

        $quizdata = null;
        if (authorship::is_quiz_required($score) && self::is_verification_test_enabled()) {
            $counts = authorship::get_question_counts();
            $quiz = $this->run_authorship_step(
                $service,
                $courseid,
                $cmid,
                $userid,
                assign_ux_service::MODE_QUIZ,
                [
                    'initialConfidence' => $score,
                    'trueFalseCount' => $counts['true_false'],
                    'multipleChoiceCount' => $counts['multiple_choice'],
                    'openCount' => $counts['open'],
                ]
            );
            $quizdata = ['questions' => $quiz['questions']];
        }

        $transaction = $DB->start_delegated_transaction();
        $record = author_storage::create_record($userid, self::COMPONENT, self::AREA, (int) $submission->id, [
            'initial_confidence' => $score,
            'initial_explanation' => (string) ($confidence['explanation'] ?? ''),
            'quiz_data' => $quizdata,
            'source_timemodified' => (int) $submission->timemodified,
            'source_attempt' => (int) $submission->attemptnumber,
        ]);
        if (!$record) {
            throw new \moodle_exception('automation_authorship_failed', 'plagiarism_dixeo');
        }
        $transaction->allow_commit();

        authorship_checked::create([
            'context' => $context,
            'objectid' => (int) $record->id,
            'relateduserid' => $userid,
            'other' => ['status' => $record->status],
        ])->trigger();
    }

    /**
     * Run one authorship step and return its mapped result.
     *
     * @param assign_ux_service $service Dixeo assign service.
     * @param int $courseid Course id.
     * @param int $cmid Assignment course module id.
     * @param int $userid Student user id.
     * @param string $mode Authorship mode.
     * @param array $options Mode-specific options.
     * @return array Mapped result.
     * @throws \moodle_exception When the step does not complete.
     */
    private function run_authorship_step(
        assign_ux_service $service,
        int $courseid,
        int $cmid,
        int $userid,
        string $mode,
        array $options = []
    ): array {
        $operation = $service->submit_authorship_and_wait($courseid, $cmid, $userid, $mode, $options);
        if (!$operation->completed) {
            throw new \moodle_exception('automation_authorship_incomplete', 'plagiarism_dixeo', '', $mode);
        }

        $mapped = $service->map_authorship_result($mode, $operation->result);
        if (isset($mapped['error'])) {
            throw new \moodle_exception('automation_authorship_failed', 'plagiarism_dixeo', '', $mapped['error']);
        }

        return $mapped;
    }

    /**
     * Whether a verification quiz may be set when confidence is low.
     *
     * @return bool
     */
    private static function is_verification_test_enabled(): bool {
        return (int) get_config('plagiarism_dixeo', 'verification_test') === 1;
    }

    /**
     * Whether the student already has a quiz or a final score for this submission.
     *
     * @param \stdClass $record Authorship row.
     * @return bool
     */
    private static function authorship_already_settled(\stdClass $record): bool {
        $status = (string) ($record->status ?? '');
        return !in_array($status, [author_storage::STATUS_PENDING, author_storage::STATUS_FAILED], true);
    }
}
