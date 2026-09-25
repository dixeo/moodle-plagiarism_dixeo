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
 * Persistence for proposed grades awaiting teacher confirmation.
 *
 * @package    plagiarism_dixeo
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo\feedback;

/**
 * Reads and writes plagiarism_dixeo_grade_feedback.
 *
 * Rows here are proposals only. Nothing in this class touches the gradebook: a grade
 * reaches a student only when a teacher saves the grading form themselves.
 */
class grade_feedback_storage {
    /** @var string Table owned by this class. */
    public const TABLE = 'plagiarism_dixeo_grade_feedback';

    /** @var string Proposal generated, not yet confirmed by a teacher. */
    public const STATUS_DRAFT = 'draft';

    /** @var string A teacher has saved a grade for this submission. */
    public const STATUS_APPLIED = 'applied';

    /** @var string Simple direct grading. */
    public const METHOD_SIMPLE = 'simple';

    /** @var string Marking guide. */
    public const METHOD_GUIDE = 'guide';

    /** @var string Rubric. */
    public const METHOD_RUBRIC = 'rubric';

    /**
     * Load the proposal stored for a submission context.
     *
     * @param int $userid Student being graded.
     * @param string $component Component name, e.g. mod_assign.
     * @param string $area Area name, e.g. submission.
     * @param int $itemid Area-specific id, e.g. assign_submission.id.
     * @return \stdClass|null
     */
    public static function get_by_context(
        int $userid,
        string $component,
        string $area,
        int $itemid
    ): ?\stdClass {
        global $DB;

        if ($itemid <= 0) {
            return null;
        }
        return $DB->get_record(self::TABLE, [
            'userid' => $userid,
            'component' => $component,
            'area' => $area,
            'itemid' => $itemid,
        ]) ?: null;
    }

    /**
     * Store a proposal for a submission context, replacing anything held for that attempt.
     *
     * @param int $userid Student being graded.
     * @param string $component Component name.
     * @param string $area Area name.
     * @param int $itemid Area-specific id.
     * @param array $result Keys gradingmethod, analysis, feedback and optionally grade, criteria.
     * @param int|null $sourcetimemodified timemodified of the source submission.
     * @param int|null $sourceattempt Attempt number of the source submission.
     * @return \stdClass|null Stored record, or null if the insert failed.
     */
    public static function create_record(
        int $userid,
        string $component,
        string $area,
        int $itemid,
        array $result,
        ?int $sourcetimemodified = null,
        ?int $sourceattempt = null
    ): ?\stdClass {
        global $DB;

        self::delete_by_context($userid, $component, $area, $itemid);

        $now = time();
        $record = (object) [
            'userid' => $userid,
            'component' => $component,
            'area' => $area,
            'itemid' => $itemid,
            'source_timemodified' => $sourcetimemodified,
            'source_attempt' => $sourceattempt,
            'status' => self::STATUS_DRAFT,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        self::apply_result($record, $result);

        $id = $DB->insert_record(self::TABLE, $record);
        return $id ? $DB->get_record(self::TABLE, ['id' => $id]) : null;
    }

    /**
     * Replace the proposal held against an existing record.
     *
     * @param int $id Record id.
     * @param array $result Keys gradingmethod, analysis, feedback and optionally grade, criteria.
     * @param int|null $sourcetimemodified timemodified of the source submission.
     * @param int|null $sourceattempt Attempt number of the source submission.
     * @return bool Success.
     */
    public static function update_record(
        int $id,
        array $result,
        ?int $sourcetimemodified = null,
        ?int $sourceattempt = null
    ): bool {
        global $DB;

        $record = $DB->get_record(self::TABLE, ['id' => $id]);
        if (!$record) {
            return false;
        }

        $record->timemodified = time();
        $record->status = self::STATUS_DRAFT;
        if ($sourcetimemodified !== null) {
            $record->source_timemodified = $sourcetimemodified;
        }
        if ($sourceattempt !== null) {
            $record->source_attempt = $sourceattempt;
        }
        self::apply_result($record, $result);

        return $DB->update_record(self::TABLE, $record);
    }

    /**
     * Mark the proposal for a submission context as confirmed by a teacher.
     *
     * @param int $userid Student being graded.
     * @param string $component Component name.
     * @param string $area Area name.
     * @param int $itemid Area-specific id.
     * @return bool True if a draft existed and was marked applied.
     */
    public static function mark_applied(
        int $userid,
        string $component,
        string $area,
        int $itemid
    ): bool {
        global $DB;

        $record = self::get_by_context($userid, $component, $area, $itemid);
        if (!$record || $record->status === self::STATUS_APPLIED) {
            return false;
        }

        $record->status = self::STATUS_APPLIED;
        $record->timemodified = time();

        return $DB->update_record(self::TABLE, $record);
    }

    /**
     * Delete the proposal held for a submission context.
     *
     * @param int $userid Student being graded.
     * @param string $component Component name.
     * @param string $area Area name.
     * @param int $itemid Area-specific id.
     * @return bool True if a record existed and was deleted.
     */
    public static function delete_by_context(
        int $userid,
        string $component,
        string $area,
        int $itemid
    ): bool {
        global $DB;

        $conditions = [
            'userid' => $userid,
            'component' => $component,
            'area' => $area,
            'itemid' => $itemid,
        ];
        if (!$DB->record_exists(self::TABLE, $conditions)) {
            return false;
        }
        return $DB->delete_records(self::TABLE, $conditions);
    }

    /**
     * Convert a stored record back into the grading result shape the grader UI consumes.
     *
     * @param \stdClass $record As returned by get_by_context().
     * @return array Keys gradingmethod, analysis, feedback and, per method, grade or criteria.
     */
    public static function record_to_result(\stdClass $record): array {
        $result = [
            'gradingmethod' => (string) ($record->gradingmethod ?? self::METHOD_SIMPLE),
            'analysis' => (string) ($record->analysis ?? ''),
            'feedback' => (string) ($record->feedback ?? ''),
        ];

        if (isset($record->grade) && $record->grade !== '') {
            $result['grade'] = (float) $record->grade;
        }

        $criteria = null;
        if (!empty($record->criteria)) {
            $decoded = json_decode($record->criteria, true);
            $criteria = is_array($decoded) ? $decoded : null;
        }
        if ($criteria !== null) {
            $result['criteria'] = $criteria;
        } else if (in_array($result['gradingmethod'], [self::METHOD_GUIDE, self::METHOD_RUBRIC], true)) {
            $result['criteria'] = [];
        }

        return $result;
    }

    /**
     * Copy a grading result onto a record.
     *
     * @param \stdClass $record Record to mutate.
     * @param array $result Grading result from the API.
     */
    private static function apply_result(\stdClass $record, array $result): void {
        $method = (string) ($result['gradingmethod'] ?? self::METHOD_SIMPLE);
        $known = [self::METHOD_SIMPLE, self::METHOD_GUIDE, self::METHOD_RUBRIC];

        $record->gradingmethod = in_array($method, $known, true) ? $method : self::METHOD_SIMPLE;
        $record->analysis = (string) ($result['analysis'] ?? '');
        $record->feedback = (string) ($result['feedback'] ?? '');
        $record->grade = isset($result['grade']) ? (float) $result['grade'] : null;
        $record->criteria = isset($result['criteria']) && is_array($result['criteria'])
            ? json_encode($result['criteria'])
            : null;
    }
}
