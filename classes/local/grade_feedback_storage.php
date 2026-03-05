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

/**
 * Storage for AI grading result per submission (table plagiarism_dixeo_grade_feedback).
 * One record per (user, component, area, itemid); reset on submission_updated so grading matches current content.
 *
 * @package    plagiarism_dixeo
 * @copyright  2026 Dixeo.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Persistence for grade feedback records (no authorship in analysis/feedback).
 */
class grade_feedback_storage {

    /**
     * Get a grade feedback record by context (user + component + area + itemid).
     *
     * @param int $userid User id (student being graded).
     * @param string $component e.g. mod_assign.
     * @param string $area e.g. submission.
     * @param int $itemid Area-specific id (e.g. assign_submission.id).
     * @return \stdClass|null
     */
    public static function get_by_context(int $userid, string $component, string $area, int $itemid): ?\stdClass {
        global $DB;
        $record = $DB->get_record('plagiarism_dixeo_grade_feedback', [
            'userid'    => $userid,
            'component' => $component,
            'area'      => $area,
            'itemid'    => $itemid,
        ]);
        return $record ?: null;
    }

    /**
     * Create a grade feedback record from the grading result (no authorship in analysis/feedback).
     *
     * @param int $userid User id (student being graded).
     * @param string $component e.g. mod_assign.
     * @param string $area e.g. submission.
     * @param int $itemid Area-specific id (e.g. assign_submission.id).
     * @param array $result Keys: gradingmethod, analysis, feedback; optional grade, criteria (array).
     * @param int|null $source_timemodified
     * @param int|null $source_attempt
     * @return \stdClass|null Created record or null on failure.
     */
    public static function create_record(
        int $userid,
        string $component,
        string $area,
        int $itemid,
        array $result,
        ?int $source_timemodified = null,
        ?int $source_attempt = null
    ): ?\stdClass {
        global $DB;

        $now = time();
        $record = (object) [
            'userid'               => $userid,
            'component'            => $component,
            'area'                 => $area,
            'itemid'               => $itemid,
            'source_timemodified'  => $source_timemodified,
            'source_attempt'       => $source_attempt,
            'gradingmethod'        => $result['gradingmethod'] ?? 'simple',
            'analysis'             => $result['analysis'] ?? '',
            'feedback'             => $result['feedback'] ?? '',
            'grade'                => isset($result['grade']) ? (float) $result['grade'] : null,
            'criteria'             => null,
            'timecreated'          => $now,
            'timemodified'         => $now,
        ];
        if (isset($result['criteria']) && is_array($result['criteria'])) {
            $record->criteria = json_encode($result['criteria']);
        }

        $id = $DB->insert_record('plagiarism_dixeo_grade_feedback', $record);
        return $id ? $DB->get_record('plagiarism_dixeo_grade_feedback', ['id' => $id]) : null;
    }

    /**
     * Update an existing grade feedback record with a new result (e.g. task retry or duplicate queue).
     *
     * @param int $id Record id.
     * @param array $result Keys: gradingmethod, analysis, feedback; optional grade, criteria (array).
     * @param int|null $source_timemodified
     * @param int|null $source_attempt
     * @return bool Success.
     */
    public static function update_record(
        int $id,
        array $result,
        ?int $source_timemodified = null,
        ?int $source_attempt = null
    ): bool {
        global $DB;
        $record = $DB->get_record('plagiarism_dixeo_grade_feedback', ['id' => $id]);
        if (!$record) {
            return false;
        }
        $record->timemodified = time();
        $record->gradingmethod = $result['gradingmethod'] ?? 'simple';
        $record->analysis = $result['analysis'] ?? '';
        $record->feedback = $result['feedback'] ?? '';
        $record->grade = isset($result['grade']) ? (float) $result['grade'] : null;
        $record->criteria = null;
        if (isset($result['criteria']) && is_array($result['criteria'])) {
            $record->criteria = json_encode($result['criteria']);
        }
        if ($source_timemodified !== null) {
            $record->source_timemodified = $source_timemodified;
        }
        if ($source_attempt !== null) {
            $record->source_attempt = $source_attempt;
        }
        return $DB->update_record('plagiarism_dixeo_grade_feedback', $record);
    }

    /**
     * Delete the grade feedback record for a given context (e.g. when submission is updated).
     *
     * @param int $userid User id.
     * @param string $component e.g. mod_assign.
     * @param string $area e.g. submission.
     * @param int $itemid Area-specific id (e.g. assign_submission.id).
     * @return bool True if a record was deleted, false otherwise.
     */
    public static function delete_by_context(int $userid, string $component, string $area, int $itemid): bool {
        global $DB;
        $record = $DB->get_record('plagiarism_dixeo_grade_feedback', [
            'userid'    => $userid,
            'component' => $component,
            'area'      => $area,
            'itemid'    => $itemid,
        ]);
        if (!$record) {
            return false;
        }
        return $DB->delete_records('plagiarism_dixeo_grade_feedback', ['id' => $record->id]);
    }

    /**
     * Convert a stored record to the API result shape (gradingmethod, analysis, feedback, grade?, criteria?).
     *
     * @param \stdClass $record From get_by_context.
     * @return array
     */
    public static function record_to_result(\stdClass $record): array {
        $criteria = null;
        if (!empty($record->criteria)) {
            $decoded = json_decode($record->criteria, true);
            $criteria = is_array($decoded) ? $decoded : null;
        }
        $out = [
            'gradingmethod' => (string) ($record->gradingmethod ?? 'simple'),
            'analysis'      => (string) ($record->analysis ?? ''),
            'feedback'      => (string) ($record->feedback ?? ''),
        ];
        if (isset($record->grade) && $record->grade !== null && $record->grade !== '') {
            $out['grade'] = (float) $record->grade;
        }
        $method = $out['gradingmethod'];
        if ($method === 'guide' || $method === 'rubric') {
            $out['criteria'] = is_array($criteria) ? $criteria : [];
        } else if ($criteria !== null) {
            $out['criteria'] = $criteria;
        }
        return $out;
    }
}
