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
 * Event observers for automatic submission analysis.
 *
 * @package    plagiarism_dixeo
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo;

use core\event\base;
use core\task\manager;
use plagiarism_dixeo\authorship\author_storage;
use plagiarism_dixeo\automation\analysis_gate;
use plagiarism_dixeo\event\grade_draft_applied;
use plagiarism_dixeo\feedback\grade_feedback_storage;
use plagiarism_dixeo\feedback\presubmission_feedback_storage;
use plagiarism_dixeo\task\analyze_submission;
use plagiarism_dixeo\task\stage_draft_grade;

/**
 * Turns assignment submission events into analysis work.
 *
 * In asynchronous mode these handlers only queue an adhoc task, so a slow service cannot
 * hold up a submission. In synchronous mode they run the same task immediately; a failure
 * is logged and a pending record is left for retry_pending_analysis rather than thrown
 * back into Moodle's submit flow.
 */
class observer {
    /** @var string Component the stored analysis belongs to. */
    private const COMPONENT = 'mod_assign';

    /** @var string Area the stored analysis belongs to. */
    private const AREA = 'submission';

    /**
     * A student saved a draft submission.
     *
     * @param base $event Submission plugin assessable_uploaded event.
     */
    public static function submission_uploaded(base $event): void {
        self::dispatch_analysis(
            (int) $event->contextinstanceid,
            (int) $event->courseid,
            (int) $event->userid,
            (int) $event->objectid,
            analysis_gate::TRIGGER_UPLOAD
        );
    }

    /**
     * A student submitted their work for grading.
     *
     * @param base $event mod_assign assessable_submitted event.
     */
    public static function submission_submitted(base $event): void {
        $cmid = (int) $event->contextinstanceid;
        $courseid = (int) $event->courseid;
        $userid = (int) $event->userid;
        $submissionid = (int) $event->objectid;

        self::dispatch_analysis($cmid, $courseid, $userid, $submissionid, analysis_gate::TRIGGER_SUBMIT);

        if (analysis_gate::is_available() && analysis_gate::wants_draft_grade($cmid)) {
            self::dispatch(new stage_draft_grade(), $cmid, $courseid, $userid, $submissionid, false);
        }
    }

    /**
     * A teacher saved a grade, so any proposal they were shown has now been acted on.
     *
     * @param base $event mod_assign submission_graded event.
     */
    public static function submission_graded(base $event): void {
        global $DB;

        $userid = (int) $event->relateduserid;
        $cmid = (int) $event->contextinstanceid;
        if ($userid <= 0 || $cmid <= 0) {
            return;
        }

        $cm = get_coursemodule_from_id('assign', $cmid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return;
        }

        $submissionid = (int) $DB->get_field(
            'assign_submission',
            'id',
            ['assignment' => (int) $cm->instance, 'userid' => $userid, 'latest' => 1],
            IGNORE_MULTIPLE
        );
        if ($submissionid <= 0) {
            return;
        }

        $draft = grade_feedback_storage::get_by_context($userid, self::COMPONENT, self::AREA, $submissionid);
        if (!$draft || $draft->status !== grade_feedback_storage::STATUS_DRAFT) {
            return;
        }

        grade_feedback_storage::mark_applied($userid, self::COMPONENT, self::AREA, $submissionid);

        grade_draft_applied::create([
            'context' => \context_module::instance($cmid),
            'objectid' => (int) $draft->id,
            'relateduserid' => $userid,
        ])->trigger();
    }

    /**
     * A submission changed, so anything Dixeo concluded about the previous version is stale.
     *
     * @param base $event Submission plugin submission_updated event.
     */
    public static function submission_updated(base $event): void {
        $submissionid = (int) ($event->other['submissionid'] ?? 0);
        $userid = (int) ($event->relateduserid ?: $event->userid);
        if ($submissionid <= 0 || $userid <= 0) {
            return;
        }

        author_storage::delete_by_context($userid, self::COMPONENT, self::AREA, $submissionid);
        presubmission_feedback_storage::delete_by_context($userid, self::COMPONENT, self::AREA, $submissionid);
        grade_feedback_storage::delete_by_context($userid, self::COMPONENT, self::AREA, $submissionid);
    }

    /**
     * Run or queue an analysis depending on evaluation_mode.
     *
     * @param int $cmid Assignment course module id.
     * @param int $courseid Course id.
     * @param int $userid Student user id.
     * @param int $submissionid Assign submission id.
     * @param string $trigger The trigger that fired.
     */
    private static function dispatch_analysis(
        int $cmid,
        int $courseid,
        int $userid,
        int $submissionid,
        string $trigger
    ): void {
        if (!analysis_gate::should_analyse($cmid, $trigger)) {
            return;
        }

        self::dispatch(new analyze_submission(), $cmid, $courseid, $userid, $submissionid, true);
    }

    /**
     * Queue the task, or run it now when evaluation_mode is synchronous.
     *
     * @param \core\task\adhoc_task $task Task to run.
     * @param int $cmid Assignment course module id.
     * @param int $courseid Course id.
     * @param int $userid Student user id.
     * @param int $submissionid Assign submission id.
     * @param bool $leavepending On a sync failure, store a pending authorship record for retry.
     */
    private static function dispatch(
        \core\task\adhoc_task $task,
        int $cmid,
        int $courseid,
        int $userid,
        int $submissionid,
        bool $leavepending
    ): void {
        if ($cmid <= 0 || $courseid <= 0 || $userid <= 0 || $submissionid <= 0) {
            return;
        }

        $task->set_component('plagiarism_dixeo');
        $task->set_userid($userid);
        $task->set_custom_data([
            'cmid' => $cmid,
            'courseid' => $courseid,
            'userid' => $userid,
            'submissionid' => $submissionid,
        ]);

        if (!analysis_gate::is_sync()) {
            // Saving a draft fires one event per submission plugin, so identical work is queued once.
            manager::queue_adhoc_task($task, true);
            return;
        }

        try {
            $task->execute();
        } catch (\Throwable $e) {
            mtrace('plagiarism_dixeo: synchronous evaluation failed: ' . $e->getMessage());
            if ($leavepending) {
                self::leave_pending_analysis($userid, $submissionid);
            }
        }
    }

    /**
     * Ensure a pending authorship row exists so retry_pending_analysis can pick the submission up.
     *
     * @param int $userid Student user id.
     * @param int $submissionid Assign submission id.
     */
    private static function leave_pending_analysis(int $userid, int $submissionid): void {
        $existing = author_storage::get_record_by_context($userid, self::COMPONENT, self::AREA, $submissionid);
        if ($existing) {
            return;
        }
        author_storage::create_record($userid, self::COMPONENT, self::AREA, $submissionid);
    }
}
