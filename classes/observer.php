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

namespace plagiarism_dixeo;

defined('MOODLE_INTERNAL') || die();

/**
 * Event observers for plagiarism_dixeo (assign submission events).
 *
 * @package    plagiarism_dixeo
 * @author     Josemaria Bolanos <admin@mako.digital>
 * @copyright  2026 Dixeo.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {

    /** Assign trigger: run on every upload (draft). */
    public const ASSIGN_TRIGGER_UPLOAD = 'upload';

    /** Assign trigger: run on final submit only. */
    public const ASSIGN_TRIGGER_SUBMIT = 'submit';

    /** Evaluation mode: run in observer (synchronous). */
    public const EVALUATION_MODE_SYNC = 'sync';

    /** Evaluation mode: queue and run in scheduled task (asynchronous). */
    public const EVALUATION_MODE_ASYNC = 'async';

    /** Component name for assign module authorship context. */
    private const COMPONENT_ASSIGN = 'mod_assign';

    /** Area name for submission authorship context. */
    private const AREA_SUBMISSION = 'submission';

    /**
     * Handle assign file upload event.
     *
     * @param \assignsubmission_file\event\assessable_uploaded $event The event.
     * @return void
     */
    public static function assign_file_uploaded(\assignsubmission_file\event\assessable_uploaded $event): void {
        $trigger = get_config('plagiarism_dixeo', 'assign_trigger');
        if ($trigger !== self::ASSIGN_TRIGGER_UPLOAD) {
            return;
        }
        self::handle_assign_event($event);
    }

    /**
     * Handle assign onlinetext upload event.
     *
     * @param \assignsubmission_onlinetext\event\assessable_uploaded $event The event.
     * @return void
     */
    public static function assign_onlinetext_uploaded(\assignsubmission_onlinetext\event\assessable_uploaded $event): void {
        $trigger = get_config('plagiarism_dixeo', 'assign_trigger');
        if ($trigger !== self::ASSIGN_TRIGGER_UPLOAD) {
            return;
        }
        self::handle_assign_event($event);
    }

    /**
     * Handle assign submitted (final submit) event.
     *
     * @param \mod_assign\event\assessable_submitted $event The event.
     * @return void
     */
    public static function assign_submitted(\mod_assign\event\assessable_submitted $event): void {
        $trigger = get_config('plagiarism_dixeo', 'assign_trigger');
        if ($trigger !== self::ASSIGN_TRIGGER_SUBMIT) {
            return;
        }
        self::handle_assign_event($event);
    }

    /**
     * Handle assign submission updated (content edited). Clear authorship so new content is re-evaluated.
     * Skip deleting when status is 'submitted' so we do not wipe the record right after assessable_submitted.
     *
     * @param \mod_assign\event\submission_updated $event The event.
     * @return void
     */
    public static function assign_submission_updated(\mod_assign\event\submission_updated $event): void {
        if (empty($GLOBALS['CFG']->enableplagiarism) || !get_config('plagiarism_dixeo', 'enabled')) {
            return;
        }
        if (!get_config('plagiarism_dixeo', 'enable_mod_assign')) {
            return;
        }
        $eventdata = $event->get_data();
        $other = $eventdata['other'] ?? [];
        $status = isset($other['submissionstatus']) ? (string) $other['submissionstatus'] : '';
        if ($status === 'submitted') {
            return;
        }
        $userid = (int) ($eventdata['relateduserid'] ?? $eventdata['userid'] ?? 0);
        $submissionid = (int) ($other['submissionid'] ?? $eventdata['objectid'] ?? 0);
        if ($userid <= 0 || $submissionid <= 0) {
            return;
        }
        \plagiarism_dixeo\local\author_storage::delete_by_context(
            $userid,
            self::COMPONENT_ASSIGN,
            self::AREA_SUBMISSION,
            $submissionid
        );
    }

    /**
     * Common handler for assign events: check plugin/activity enabled, then create pending or run sync.
     *
     * @param \core\event\base $event The event (must provide contextinstanceid and userid).
     * @return void
     */
    protected static function handle_assign_event(\core\event\base $event): void {
        global $CFG, $DB;

        if (empty($CFG->enableplagiarism)) {
            return;
        }
        if (!get_config('plagiarism_dixeo', 'enabled')) {
            return;
        }
        if (!get_config('plagiarism_dixeo', 'enable_mod_assign')) {
            return;
        }

        $eventdata = $event->get_data();
        $cmid = (int) ($eventdata['contextinstanceid'] ?? 0);
        $userid = (int) ($eventdata['userid'] ?? 0);
        if ($cmid <= 0 || $userid <= 0) {
            return;
        }

        if (!$DB->record_exists('course_modules', ['id' => $cmid])) {
            return;
        }

        $config = $DB->get_records_menu('plagiarism_dixeo_config', ['cm' => $cmid], '', 'name, value');
        if (empty($config['use_dixeo'])) {
            return;
        }

        $component = self::COMPONENT_ASSIGN;
        $area = self::AREA_SUBMISSION;
        $submissionid = (int) ($eventdata['objectid'] ?? 0);
        if ($submissionid <= 0) {
            return;
        }
        $submission = $DB->get_record('assign_submission', [
            'id' => $submissionid,
            'userid' => $userid,
        ], 'id, timemodified, attemptnumber', IGNORE_MISSING);
        if (!$submission) {
            return;
        }
        $itemid = (int) $submission->id;

        // Clear any existing authorship record so evaluation is for current content only.
        \plagiarism_dixeo\local\author_storage::delete_by_context($userid, $component, $area, $itemid);
        $submeta = [
            'source_timemodified' => (int) $submission->timemodified,
            'source_attempt' => (int) $submission->attemptnumber,
        ];

        $mode = get_config('plagiarism_dixeo', 'evaluation_mode');
        if ($mode === self::EVALUATION_MODE_ASYNC) {
            $existing = \plagiarism_dixeo\local\author_storage::get_record_by_context($userid, $component, $area, $itemid);
            if (!$existing) {
                \plagiarism_dixeo\local\author_storage::create_record($userid, $component, $area, $itemid, $submeta);
            }
            return;
        }

        // Sync: call backend and store result.
        $result = self::call_backend_initial($component, $area, $itemid, $userid);
        if ($result === null) {
            $existing = \plagiarism_dixeo\local\author_storage::get_record_by_context($userid, $component, $area, $itemid);
            if (!$existing) {
                \plagiarism_dixeo\local\author_storage::create_record($userid, $component, $area, $itemid, $submeta);
            }
            return;
        }

        $payload = array_merge($submeta, [
            'initial_confidence' => $result['initial_confidence'],
            'initial_explanation' => $result['initial_explanation'] ?? '',
            'quiz_data' => $result['quiz_data'] ?? null,
        ]);
        $existing = \plagiarism_dixeo\local\author_storage::get_record_by_context($userid, $component, $area, $itemid);
        if ($existing) {
            \plagiarism_dixeo\local\author_storage::update_initial($existing->id, $payload);
        } else {
            \plagiarism_dixeo\local\author_storage::create_record($userid, $component, $area, $itemid, $payload);
        }
    }

    /**
     * Run initial authorship evaluation (no DB writes; caller persists result via author_storage).
     *
     * @param string $component Component e.g. mod_assign.
     * @param string $area Area e.g. submission.
     * @param int $itemid Context item id (e.g. cmid for assign).
     * @param int $userid User whose submission to evaluate.
     * @return array|null Keys initial_confidence, initial_explanation, quiz_data; null on failure.
     */
    public static function call_backend_initial(string $component, string $area, int $itemid, int $userid): ?array {
        try {
            return \plagiarism_dixeo\local\authorship::evaluate_initial($component, $area, $itemid, $userid);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
