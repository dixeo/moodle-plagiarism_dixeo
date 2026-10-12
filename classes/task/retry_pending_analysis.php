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
 * Scheduled task that picks up analyses which never finished.
 *
 * @package    plagiarism_dixeo
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo\task;

use core\task\manager;
use plagiarism_dixeo\authorship\author_storage;
use plagiarism_dixeo\automation\analysis_gate;

/**
 * Requeues submissions whose analysis was left unfinished.
 *
 * An adhoc task that exhausts its retries, or a site that was down when the queue was
 * drained, can leave a record stuck without a score. This sweeps those up rather than
 * leaving the student with a panel that never resolves.
 */
class retry_pending_analysis extends \core\task\scheduled_task {
    /** @var int How many stuck records to requeue in one run. */
    private const BATCH_SIZE = 20;

    /** @var int How long a record must have been stuck before it is retried, in seconds. */
    private const MIN_AGE = 10 * MINSECS;

    /** @var int How long after creation a record stops being retried, in seconds. */
    private const MAX_AGE = 2 * DAYSECS;

    /**
     * Human readable task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_retry_pending_analysis', 'plagiarism_dixeo');
    }

    /**
     * Requeue analysis for records that never reached a conclusion.
     */
    public function execute(): void {
        global $CFG, $DB;

        if (!analysis_gate::is_available()) {
            return;
        }

        require_once($CFG->dirroot . '/plagiarism/dixeo/lib.php');
        $now = time();
        $requeued = 0;

        foreach (author_storage::get_pending_records(self::BATCH_SIZE) as $record) {
            $age = $now - (int) $record->timecreated;
            if ($age < self::MIN_AGE || $age > self::MAX_AGE) {
                continue;
            }

            $submission = $DB->get_record(
                'assign_submission',
                ['id' => (int) $record->itemid],
                'id,assignment'
            );
            if (!$submission) {
                continue;
            }

            $cm = get_coursemodule_from_instance('assign', (int) $submission->assignment, 0, false, IGNORE_MISSING);
            if (!$cm || !\plagiarism_plugin_dixeo::enabled_for_course((int) $cm->course)) {
                continue;
            }
            if (!analysis_gate::is_enabled_for_activity((int) $cm->id)) {
                continue;
            }

            $task = new analyze_submission();
            $task->set_component('plagiarism_dixeo');
            $task->set_userid((int) $record->userid);
            $task->set_custom_data([
                'cmid' => (int) $cm->id,
                'courseid' => (int) $cm->course,
                'userid' => (int) $record->userid,
                'submissionid' => (int) $record->itemid,
            ]);
            manager::queue_adhoc_task($task, true);
            $requeued++;
        }

        if ($requeued > 0) {
            mtrace("plagiarism_dixeo: requeued $requeued unfinished submission analyses.");
        }
    }
}
