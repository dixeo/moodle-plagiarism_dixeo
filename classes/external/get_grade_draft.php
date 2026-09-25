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
 * External API: read the draft grade waiting for a teacher.
 *
 * @package    plagiarism_dixeo
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use plagiarism_dixeo\feedback\grade_feedback_storage;

/**
 * Returns a proposed grade so the grading form can be pre-filled with it.
 *
 * Only a user who can grade the activity may read a proposal, and reading one changes
 * nothing: the draft stays a draft until the teacher saves the form.
 */
class get_grade_draft extends external_api {
    /** @var string Component the stored proposal belongs to. */
    private const COMPONENT = 'mod_assign';

    /** @var string Area the stored proposal belongs to. */
    private const AREA = 'submission';

    /**
     * Describe parameters for get_grade_draft.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'id' => new external_value(PARAM_INT, 'Course module ID (assign instance).'),
            'userid' => new external_value(PARAM_INT, 'User ID being graded.'),
        ]);
    }

    /**
     * Return the draft grade held for a student, if there is one.
     *
     * @param int $id Course module id.
     * @param int $userid Student being graded.
     * @return array
     * @throws \moodle_exception
     */
    public static function execute(int $id, int $userid): array {
        global $DB;

        $params = self::validate_parameters(self::execute_parameters(), [
            'id' => $id,
            'userid' => $userid,
        ]);

        $context = assign_access::require_teacher_grade($params['id']);
        $cm = get_coursemodule_from_id('assign', $params['id'], 0, false, MUST_EXIST);

        $submissionid = (int) $DB->get_field(
            'assign_submission',
            'id',
            ['assignment' => (int) $cm->instance, 'userid' => $params['userid'], 'latest' => 1],
            IGNORE_MULTIPLE
        );

        $record = $submissionid > 0
            ? grade_feedback_storage::get_by_context($params['userid'], self::COMPONENT, self::AREA, $submissionid)
            : null;

        if (!$record || $record->status !== grade_feedback_storage::STATUS_DRAFT) {
            return self::empty_payload();
        }

        $result = grade_feedback_storage::record_to_result($record);
        // The proposal is model output, so it is purified here just as the live grading flow does.
        $options = ['context' => $context, 'para' => false];

        return [
            'hasdraft' => true,
            'gradingmethod' => $result['gradingmethod'],
            'analysis' => format_text($result['analysis'], FORMAT_HTML, $options),
            'feedback' => format_text($result['feedback'], FORMAT_HTML, $options),
            'grade' => $result['grade'] ?? null,
            'criteria' => $result['criteria'] ?? [],
            'timecreated' => (int) $record->timecreated,
        ];
    }

    /**
     * The response used when there is nothing waiting for the teacher.
     *
     * @return array
     */
    private static function empty_payload(): array {
        return [
            'hasdraft' => false,
            'gradingmethod' => grade_feedback_storage::METHOD_SIMPLE,
            'analysis' => '',
            'feedback' => '',
            'grade' => null,
            'criteria' => [],
            'timecreated' => 0,
        ];
    }

    /**
     * Return structure for get_grade_draft.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        $criterion = new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Criterion DB id.'),
            'remark' => new external_value(PARAM_TEXT, 'Remark for the student.'),
            'score' => new external_value(PARAM_FLOAT, 'Score (marking guide only).', VALUE_OPTIONAL),
            'levelid' => new external_value(PARAM_INT, 'Level DB id (rubric only).', VALUE_OPTIONAL),
        ], 'Criterion fill.');

        return new external_single_structure([
            'hasdraft' => new external_value(PARAM_BOOL, 'Whether a draft grade is waiting.'),
            'gradingmethod' => new external_value(PARAM_ALPHA, 'One of: simple, guide, rubric.'),
            'analysis' => new external_value(PARAM_RAW, 'Analysis text for the teacher.'),
            'feedback' => new external_value(PARAM_RAW, 'Feedback HTML for the student.'),
            'grade' => new external_value(PARAM_FLOAT, 'Numeric grade (simple only).', VALUE_OPTIONAL),
            'criteria' => new external_multiple_structure($criterion, 'Criteria (guide/rubric only).', VALUE_OPTIONAL),
            'timecreated' => new external_value(PARAM_INT, 'When the draft was prepared.'),
        ], 'Draft grade awaiting teacher confirmation.');
    }
}
