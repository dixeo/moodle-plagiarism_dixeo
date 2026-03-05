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

namespace plagiarism_dixeo\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Ad-hoc task: run AI grading and apply grade to assignment after student submission.
 * Queued when assessable_submitted fires and the activity has "Enable automatic grading" set.
 *
 * @package    plagiarism_dixeo
 * @copyright  2026 Dixeo.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class apply_auto_grade extends \core\task\adhoc_task {

    /**
     * Execute: load config, run grading, store result, apply to assignment.
     */
    public function execute(): void {
        global $DB;

        $data = $this->get_custom_data();
        $cmid = (int) ($data->cmid ?? 0);
        $userid = (int) ($data->userid ?? 0);
        $submissionid = (int) ($data->submissionid ?? 0);
        if ($cmid <= 0 || $userid <= 0 || $submissionid <= 0) {
            return;
        }

        $cm = get_coursemodule_from_id('assign', $cmid, 0, false, IGNORE_MISSING);
        if (!$cm || $cm->modname !== 'assign') {
            return;
        }

        $submissionexists = $DB->record_exists('assign_submission', [
            'id'         => $submissionid,
            'userid'     => $userid,
            'assignment' => (int) $cm->instance,
        ]);
        if (!$submissionexists) {
            return;
        }

        if (empty($GLOBALS['CFG']->enableplagiarism) || !get_config('plagiarism_dixeo', 'enabled')) {
            return;
        }
        if (!get_config('plagiarism_dixeo', 'enable_mod_assign')) {
            return;
        }

        $config = $DB->get_records_menu('plagiarism_dixeo_config', ['cm' => $cmid], '', 'name, value');
        if (empty($config['use_dixeo']) || empty($config['enable_auto_grading'])) {
            return;
        }

        $assignmentcontext = \plagiarism_dixeo\modules\assign::get_assignment_context($cmid);
        $submissiondata = \plagiarism_dixeo\modules\assign::get_submission_data($cmid, $userid, $submissionid);
        $submission = $submissiondata['submission'] ?? null;
        if (!$submission) {
            return;
        }

        $authorship = \plagiarism_dixeo\local\author_storage::get_record_by_context(
            $userid,
            'mod_assign',
            'submission',
            $submissionid
        );

        $result = \plagiarism_dixeo\modules\assign::evaluate(
            $assignmentcontext,
            $submissiondata,
            \plagiarism_dixeo\modules\assign::EVALUATION_MODE_GRADING,
            $authorship
        );

        if (!empty($result['error'])) {
            return;
        }

        $srcTime = isset($submission->timemodified) ? (int) $submission->timemodified : null;
        $srcAttempt = isset($submission->attemptnumber) ? (int) $submission->attemptnumber : null;
        $existing = \plagiarism_dixeo\local\grade_feedback_storage::get_by_context(
            $userid,
            'mod_assign',
            'submission',
            $submissionid
        );
        if ($existing) {
            \plagiarism_dixeo\local\grade_feedback_storage::update_record(
                (int) $existing->id,
                $result,
                $srcTime,
                $srcAttempt
            );
        } else {
            \plagiarism_dixeo\local\grade_feedback_storage::create_record(
                $userid,
                'mod_assign',
                'submission',
                $submissionid,
                $result,
                $srcTime,
                $srcAttempt
            );
        }

        try {
            \plagiarism_dixeo\modules\assign::apply_grade_result($cmid, $userid, $submissionid, $result);
        } catch (\Throwable $e) {
            mtrace('apply_auto_grade: apply_grade_result exception: ' . $e->getMessage(), '');
            mtrace('apply_auto_grade: trace: ' . $e->getTraceAsString(), '');
            throw $e;
        }
    }

    /**
     * Queue this task for a submission (call when assessable_submitted fires and auto-grading is enabled).
     *
     * @param int $cmid Course module id.
     * @param int $userid Student user id.
     * @param int $submissionid Assign submission id.
     */
    public static function queue(int $cmid, int $userid, int $submissionid): void {
        $task = new self();
        $task->set_custom_data((object) [
            'cmid'          => $cmid,
            'userid'        => $userid,
            'submissionid'  => $submissionid,
        ]);
        \core\task\manager::queue_adhoc_task($task, true);
    }
}
