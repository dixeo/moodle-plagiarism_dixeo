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
 * Adhoc task that writes a stored draft grade to the gradebook as the teacher.
 *
 * @package    plagiarism_dixeo
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo\task;

use plagiarism_dixeo\automation\analysis_gate;
use plagiarism_dixeo\feedback\grade_feedback_storage;
use plagiarism_dixeo\grading\grade_applier;

/**
 * Applies an automatic grade as the teacher who switched automatic grading on.
 *
 * The task is queued with that teacher as its user, so cron sets them up and the grade, its
 * history and the student notification name them. It sends nothing to Dixeo. Nothing is
 * written unless that teacher still qualifies and the draft still matches the submission;
 * otherwise the draft stays for a teacher.
 */
class apply_draft_grade extends \core\task\adhoc_task {
    /** @var string Component the stored proposal belongs to. */
    private const COMPONENT = 'mod_assign';

    /** @var string Area the stored proposal belongs to. */
    private const AREA = 'submission';

    /**
     * Human readable task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_apply_draft_grade', 'plagiarism_dixeo');
    }

    /**
     * Write the draft named in the task data.
     */
    public function execute(): void {
        global $DB, $USER;

        $data = (array) $this->get_custom_data();
        $cmid = (int) ($data['cmid'] ?? 0);
        $userid = (int) ($data['userid'] ?? 0);
        $submissionid = (int) ($data['submissionid'] ?? 0);
        $draftid = (int) ($data['draftid'] ?? 0);
        if ($cmid <= 0 || $userid <= 0 || $submissionid <= 0 || $draftid <= 0) {
            mtrace('plagiarism_dixeo: incomplete task data, no grade applied.');
            return;
        }

        if (!analysis_gate::is_available() || !analysis_gate::wants_draft_grade($cmid)) {
            mtrace("plagiarism_dixeo: automatic grading is switched off for course module $cmid, draft kept.");
            return;
        }

        // The teacher may have changed, lost the capability or been suspended since the task was queued.
        if ((int) $USER->id !== grade_applier::resolve_grader_id($cmid)) {
            mtrace("plagiarism_dixeo: the recorded teacher no longer grades course module $cmid automatically, draft kept.");
            return;
        }

        $draft = $DB->get_record(grade_feedback_storage::TABLE, ['id' => $draftid]);
        if (
            !$draft
            || $draft->status !== grade_feedback_storage::STATUS_DRAFT
            || (int) $draft->userid !== $userid
            || (int) $draft->itemid !== $submissionid
            || $draft->component !== self::COMPONENT
            || $draft->area !== self::AREA
        ) {
            mtrace("plagiarism_dixeo: draft $draftid is no longer waiting, nothing applied.");
            return;
        }

        $submission = $DB->get_record('assign_submission', ['id' => $submissionid, 'userid' => $userid], 'id,timemodified');
        if (!$submission || (int) $submission->timemodified !== (int) $draft->source_timemodified) {
            mtrace("plagiarism_dixeo: submission $submissionid changed since draft $draftid was prepared, nothing applied.");
            return;
        }

        if (grade_applier::apply($cmid, $userid, $submissionid, grade_feedback_storage::record_to_result($draft))) {
            grade_feedback_storage::mark_applied($userid, self::COMPONENT, self::AREA, $submissionid);
        }
    }
}
