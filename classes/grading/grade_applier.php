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
 * Writes a staged Dixeo grade into the assignment gradebook.
 *
 * @package    plagiarism_dixeo
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo\grading;

use plagiarism_dixeo\feedback\grade_feedback_storage;

/**
 * Thin wrapper around \assign::save_grade for simple, guide and rubric results.
 *
 * The plugin table keeps the proposal; this class is the only place that writes it into Moodle.
 * The grade is written as the current user, who must be able to grade: automatic grades run
 * in a task that cron sets up as the teacher who switched automatic grading on.
 */
class grade_applier {
    /**
     * Prepend the AI grading disclaimer to feedback comments.
     *
     * @param string $feedback Raw feedback HTML.
     * @return string Feedback with the disclaimer prepended.
     */
    public static function add_feedback_disclaimer(string $feedback): string {
        $disclaimer = get_string('grading_ai_disclaimer', 'plagiarism_dixeo');
        $block = '<p class="plagiarism-dixeo-grading-disclaimer"><em>' . s($disclaimer) . '</em></p>';

        return $block . $feedback;
    }

    /**
     * Apply a staged grading result to the assignment as the current user.
     *
     * @param int $cmid Course module id.
     * @param int $userid Student being graded.
     * @param int $submissionid Assign submission id.
     * @param array $result Keys: gradingmethod, feedback; optional grade, criteria.
     * @return bool True if the grade was saved; false when the current user cannot grade or the work is gone.
     */
    public static function apply(int $cmid, int $userid, int $submissionid, array $result): bool {
        global $CFG, $DB;

        $cm = get_coursemodule_from_id('assign', $cmid, 0, true, IGNORE_MISSING);
        if (!$cm) {
            return false;
        }

        $context = \context_module::instance($cm->id);
        if (!has_capability('mod/assign:grade', $context)) {
            return false;
        }
        $course = get_course($cm->course);

        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $submission = $DB->get_record('assign_submission', [
            'id' => $submissionid,
            'userid' => $userid,
        ], 'attemptnumber', IGNORE_MISSING);
        if (!$submission) {
            return false;
        }

        $assign = new \assign($context, $cm, $course);

        $data = new \stdClass();
        $data->attemptnumber = (int) $submission->attemptnumber;
        $data->assignfeedbackcomments_editor = [
            'text' => self::add_feedback_disclaimer((string) ($result['feedback'] ?? '')),
            'format' => FORMAT_HTML,
        ];

        $method = (string) ($result['gradingmethod'] ?? grade_feedback_storage::METHOD_SIMPLE);
        if ($method === grade_feedback_storage::METHOD_SIMPLE) {
            $data->grade = isset($result['grade']) ? (float) $result['grade'] : null;
        } else if ($method === grade_feedback_storage::METHOD_GUIDE && !empty($result['criteria'])) {
            $data->advancedgrading = ['criteria' => []];
            foreach ($result['criteria'] as $criterion) {
                $id = (int) ($criterion['id'] ?? 0);
                $data->advancedgrading['criteria'][$id] = [
                    'score' => (float) ($criterion['score'] ?? 0),
                    'remark' => (string) ($criterion['remark'] ?? ''),
                ];
            }
        } else if ($method === grade_feedback_storage::METHOD_RUBRIC && !empty($result['criteria'])) {
            $data->advancedgrading = ['criteria' => []];
            foreach ($result['criteria'] as $criterion) {
                $id = (int) ($criterion['id'] ?? 0);
                $data->advancedgrading['criteria'][$id] = [
                    'levelid' => (int) ($criterion['levelid'] ?? 0),
                    'remark' => (string) ($criterion['remark'] ?? ''),
                ];
            }
        }

        $data->sendstudentnotifications = true;

        $assign->save_grade($userid, $data);
        return true;
    }

    /**
     * The teacher automatic grades for this assignment are recorded against, while they still qualify.
     *
     * That is the user who switched automatic grading on for the assignment. They must still
     * exist, be neither deleted nor suspended, and hold mod/assign:grade on the assignment.
     *
     * @param int $cmid Assignment course module id.
     * @return int User id, or 0 when nobody qualifies and a draft must be kept for a teacher.
     */
    public static function resolve_grader_id(int $cmid): int {
        $userid = autograde_enabler::get_userid($cmid);
        if ($userid <= 0) {
            return 0;
        }
        $user = \core_user::get_user($userid, 'id,deleted,suspended', IGNORE_MISSING);
        if (!$user || !empty($user->deleted) || !empty($user->suspended)) {
            return 0;
        }
        $context = \context_module::instance($cmid, IGNORE_MISSING);
        if (!$context || !has_capability('mod/assign:grade', $context, $userid)) {
            return 0;
        }
        return $userid;
    }
}
