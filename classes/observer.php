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
use plagiarism_dixeo\grading\autograde_enabler;
use plagiarism_dixeo\task\analyze_submission;
use plagiarism_dixeo\task\stage_draft_grade;

/**
 * Turns assignment submission events into analysis work.
 *
 * These handlers only queue adhoc tasks, so the Dixeo service is never called inside the
 * student's request and a slow service cannot hold up a submission.
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
        if (!self::dixeo_enabled((int) $event->courseid)) {
            return;
        }
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
        if (!self::dixeo_enabled((int) $event->courseid)) {
            return;
        }
        $cmid = (int) $event->contextinstanceid;
        $courseid = (int) $event->courseid;
        $userid = (int) $event->userid;
        $submissionid = (int) $event->objectid;

        self::dispatch_analysis($cmid, $courseid, $userid, $submissionid, analysis_gate::TRIGGER_SUBMIT);

        if (analysis_gate::is_available() && analysis_gate::wants_draft_grade($cmid)) {
            self::dispatch(new stage_draft_grade(), $cmid, $courseid, $userid, $submissionid);
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

        self::delete_analysis($userid, $submissionid);
    }

    /**
     * A teacher removed a submission, so nothing Dixeo concluded about it may be kept.
     *
     * A team submission names no student, so every student's analysis of it is deleted.
     *
     * @param base $event mod_assign submission_removed event.
     */
    public static function submission_removed(base $event): void {
        $submissionid = (int) ($event->other['submissionid'] ?? $event->objectid);
        if ($submissionid <= 0) {
            return;
        }

        self::delete_analysis((int) $event->relateduserid, $submissionid);
    }

    /**
     * Track who switched automatic grading on for an assignment.
     *
     * Only a deliberate activity-level On from a real user acting for themselves is recorded.
     * Off, an inherited value, a copy of the Dixeo value onto a new assignment, a write made
     * while logged in as someone else, and a write with no user clear the record, so automatic
     * grading then keeps drafts.
     *
     * @param \local_dixeo\event\setting_updated $event Catalogue setting change.
     */
    public static function setting_updated(\local_dixeo\event\setting_updated $event): void {
        global $CFG;

        if (
            ($event->other['component'] ?? '') !== 'plagiarism_dixeo'
            || ($event->other['name'] ?? '') !== feature_settings::AUTO_GRADING
            || (int) $event->contextlevel !== CONTEXT_MODULE
        ) {
            return;
        }
        $context = \context::instance_by_id((int) $event->contextid, IGNORE_MISSING);
        if (!$context instanceof \context_module) {
            return;
        }
        $cmid = (int) $context->instanceid;

        $resolved = \local_dixeo\external\service_factory::get_settings_config()
            ->get('plagiarism_dixeo', feature_settings::AUTO_GRADING, $context);
        $userid = (int) $event->userid;
        $deliberate = $resolved->get_source() === \local_dixeo\settings\resolver::LAYER_ACTIVITY
            && $resolved->get_value() === '1'
            && empty($event->other['defaultcopy'])
            && $userid > 0
            && !isguestuser($userid)
            && !\core\session\manager::is_loggedinas();

        if ($deliberate) {
            autograde_enabler::record($cmid, $userid);
        } else {
            autograde_enabler::clear($cmid);
        }

        require_once($CFG->dirroot . '/plagiarism/dixeo/lib.php');
        \plagiarism_plugin_dixeo::purge_activity_config_cache($cmid);
    }

    /**
     * Delete the stored analysis of a submission.
     *
     * @param int $userid Student the analysis is about, or 0 for every student of the submission.
     * @param int $submissionid Assign submission id.
     */
    public static function delete_analysis(int $userid, int $submissionid): void {
        global $DB;

        if ($submissionid <= 0) {
            return;
        }
        if ($userid > 0) {
            author_storage::delete_by_context($userid, self::COMPONENT, self::AREA, $submissionid);
            presubmission_feedback_storage::delete_by_context($userid, self::COMPONENT, self::AREA, $submissionid);
            grade_feedback_storage::delete_by_context($userid, self::COMPONENT, self::AREA, $submissionid);
            return;
        }

        $conditions = ['component' => self::COMPONENT, 'area' => self::AREA, 'itemid' => $submissionid];
        foreach ([author_storage::TABLE, presubmission_feedback_storage::TABLE, grade_feedback_storage::TABLE] as $table) {
            $DB->delete_records($table, $conditions);
        }
    }

    /**
     * Whether Dixeo features run for the course on the submission event.
     *
     * @param int $courseid
     * @return bool
     */
    private static function dixeo_enabled(int $courseid): bool {
        global $CFG;
        require_once($CFG->dirroot . '/plagiarism/dixeo/lib.php');
        return \plagiarism_plugin_dixeo::enabled_for_course($courseid);
    }

    /**
     * Queue an analysis when this activity analyses on this trigger.
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

        self::dispatch(new analyze_submission(), $cmid, $courseid, $userid, $submissionid);
    }

    /**
     * Queue a task that runs as the student.
     *
     * @param \core\task\adhoc_task $task Task to queue.
     * @param int $cmid Assignment course module id.
     * @param int $courseid Course id.
     * @param int $userid Student user id.
     * @param int $submissionid Assign submission id.
     */
    private static function dispatch(
        \core\task\adhoc_task $task,
        int $cmid,
        int $courseid,
        int $userid,
        int $submissionid
    ): void {
        if ($cmid <= 0 || $courseid <= 0 || $userid <= 0 || $submissionid <= 0) {
            return;
        }
        // A student without the current terms is skipped; the retry sweep picks the work up once they accept.
        if (!(new \local_dixeo\service\consent_service())->may_process_for($userid, $courseid)) {
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

        // Saving a draft fires one event per submission plugin, so identical work is queued once.
        manager::queue_adhoc_task($task, true);
    }
}
