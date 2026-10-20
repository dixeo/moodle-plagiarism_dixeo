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
 * Persistence for authorship evaluation records.
 *
 * @package    plagiarism_dixeo
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo\authorship;

/**
 * Reads and writes plagiarism_dixeo_authorship.
 *
 * This is the only class that writes to the table; the persistence adapter consumed by
 * local_dixeo delegates here so the status transitions stay in one place.
 */
class author_storage {
    /** @var string Table owned by this class. */
    public const TABLE = 'plagiarism_dixeo_authorship';

    /** @var string Record created, evaluation not yet run. */
    public const STATUS_PENDING = 'pending';

    /** @var string Evaluation complete, no verification quiz required. */
    public const STATUS_COMPLETE = 'complete';

    /** @var string Confidence below threshold: a verification quiz must be passed. */
    public const STATUS_VERIFICATION_REQUIRED = 'verification_required';

    /** @var string The student has started the verification quiz but not submitted it. */
    public const STATUS_VERIFICATION_IN_PROGRESS = 'verification_in_progress';

    /** @var string Evaluation could not be completed; eligible for retry. */
    public const STATUS_FAILED = 'failed';

    /**
     * Create an authorship record, replacing any previous evaluation for the same context.
     *
     * Passing an empty $result creates a pending record for a queued task to fill in.
     *
     * @param int $userid Student user id.
     * @param string $component Component name, e.g. mod_assign.
     * @param string $area Area name, e.g. submission.
     * @param int $itemid Area-specific id, e.g. assign_submission.id.
     * @param array $result Optional initial_confidence, initial_explanation, quiz_data,
     *                      source_timemodified, source_attempt.
     * @return \stdClass|null Stored record, or null if the insert failed.
     */
    public static function create_record(
        int $userid,
        string $component,
        string $area,
        int $itemid,
        array $result = []
    ): ?\stdClass {
        global $DB;

        self::delete_by_context($userid, $component, $area, $itemid);

        $now = time();
        $record = (object) [
            'userid' => $userid,
            'component' => $component,
            'area' => $area,
            'itemid' => $itemid,
            'source_timemodified' => null,
            'source_attempt' => null,
            'status' => self::STATUS_PENDING,
            'initial_confidence' => null,
            'initial_explanation' => null,
            'quiz_data' => null,
            'quiz_responses' => null,
            'response_timings' => null,
            'quiz_grade' => null,
            'quiz_explanation' => null,
            'quiz_question_feedbacks' => null,
            'final_confidence' => null,
            'final_explanation' => null,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        self::apply_initial($record, $result);

        $id = $DB->insert_record(self::TABLE, $record);
        return $id ? $DB->get_record(self::TABLE, ['id' => $id]) : null;
    }

    /**
     * Load a record by primary key.
     *
     * @param int $id Record id.
     * @return \stdClass|null
     */
    public static function get_record(int $id): ?\stdClass {
        global $DB;

        if ($id <= 0) {
            return null;
        }
        return $DB->get_record(self::TABLE, ['id' => $id]) ?: null;
    }

    /**
     * Load a record by student and submission context.
     *
     * @param int $userid Student user id.
     * @param string $component Component name.
     * @param string $area Area name.
     * @param int $itemid Area-specific id.
     * @return \stdClass|null
     */
    public static function get_record_by_context(
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
     * Delete the record for a submission context, e.g. when the submission is edited.
     *
     * @param int $userid Student user id.
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
     * Store the initial evaluation result against an existing pending record.
     *
     * @param int $id Record id.
     * @param array $result Keys initial_confidence, initial_explanation, quiz_data,
     *                      source_timemodified, source_attempt.
     * @return bool Success.
     */
    public static function update_initial(int $id, array $result): bool {
        global $DB;

        $record = self::get_record($id);
        if (!$record) {
            return false;
        }

        $record->timemodified = time();
        self::apply_initial($record, $result);

        return $DB->update_record(self::TABLE, $record);
    }

    /**
     * Store verification progress or the final quiz result.
     *
     * The status moves to complete automatically once a final confidence is recorded, unless
     * the caller sets one explicitly.
     *
     * @param int $id Record id.
     * @param array $data Keys status, quiz_responses, response_timings, final_confidence,
     *                    quiz_grade, quiz_explanation, quiz_question_feedbacks, final_explanation.
     * @return bool Success.
     */
    public static function update_record(int $id, array $data): bool {
        global $DB;

        $record = self::get_record($id);
        if (!$record) {
            return false;
        }

        $record->timemodified = time();
        if (array_key_exists('status', $data)) {
            $record->status = (string) $data['status'];
        } else if (isset($data['final_confidence']) || array_key_exists('quiz_grade', $data)) {
            $record->status = self::STATUS_COMPLETE;
        }

        foreach (['quiz_responses', 'response_timings', 'quiz_question_feedbacks'] as $jsonfield) {
            if (!isset($data[$jsonfield])) {
                continue;
            }
            if (is_string($data[$jsonfield])) {
                $record->$jsonfield = $data[$jsonfield];
                continue;
            }
            // Answers are a map. An empty list would be sent back as "[]", which the
            // browser cannot store question-id answers on.
            $flags = $jsonfield === 'quiz_responses' ? JSON_FORCE_OBJECT : 0;
            $record->$jsonfield = json_encode($data[$jsonfield], $flags);
        }
        if (isset($data['final_confidence'])) {
            $record->final_confidence = (float) $data['final_confidence'];
        }
        if (array_key_exists('quiz_grade', $data)) {
            $record->quiz_grade = $data['quiz_grade'] === null ? null : (float) $data['quiz_grade'];
        }
        foreach (['quiz_explanation', 'final_explanation'] as $textfield) {
            if (isset($data[$textfield])) {
                $record->$textfield = (string) $data[$textfield];
            }
        }

        return $DB->update_record(self::TABLE, $record);
    }

    /**
     * Records whose evaluation never completed, oldest first.
     *
     * @param int $limit Maximum number of records to return.
     * @return \stdClass[]
     */
    public static function get_pending_records(int $limit = 50): array {
        global $DB;

        [$insql, $params] = $DB->get_in_or_equal([self::STATUS_PENDING, self::STATUS_FAILED], SQL_PARAMS_NAMED);
        $records = $DB->get_records_select(
            self::TABLE,
            "status $insql",
            $params,
            'timecreated ASC',
            '*',
            0,
            $limit
        );

        return array_values($records);
    }

    /**
     * Apply an initial evaluation result to a record and derive its status.
     *
     * With no quiz to sit, the initial score is also the final score.
     *
     * @param \stdClass $record Record to mutate.
     * @param array $result Initial evaluation fields.
     */
    private static function apply_initial(\stdClass $record, array $result): void {
        foreach (['source_timemodified', 'source_attempt'] as $sourcefield) {
            if (isset($result[$sourcefield])) {
                $record->$sourcefield = (int) $result[$sourcefield];
            }
        }
        if (isset($result['initial_confidence'])) {
            $record->initial_confidence = (float) $result['initial_confidence'];
        }
        if (isset($result['initial_explanation'])) {
            $record->initial_explanation = (string) $result['initial_explanation'];
        }
        if (array_key_exists('quiz_data', $result)) {
            $record->quiz_data = self::normalise_quiz_data($result['quiz_data']);
        }

        $hasquiz = !empty($record->quiz_data);
        if ($hasquiz) {
            // The final score is only known once the quiz has been sat.
            $record->final_confidence = null;
            $record->final_explanation = null;
            $record->status = self::STATUS_VERIFICATION_REQUIRED;
        } else {
            $record->final_confidence = $record->initial_confidence;
            $record->final_explanation = $record->initial_explanation;
            $record->status = $record->initial_confidence !== null
                ? self::STATUS_COMPLETE
                : self::STATUS_PENDING;
        }
    }

    /**
     * Encode quiz data for storage, treating a quiz with no questions as no quiz at all.
     *
     * @param mixed $quizdata Array or JSON string as returned by the API, or null.
     * @return string|null JSON, or null when there is nothing to answer.
     */
    private static function normalise_quiz_data($quizdata): ?string {
        if ($quizdata === null || $quizdata === '') {
            return null;
        }

        $decoded = is_string($quizdata) ? json_decode($quizdata, true) : $quizdata;
        if (!is_array($decoded) || empty($decoded['questions']) || !is_array($decoded['questions'])) {
            return null;
        }

        return json_encode($decoded);
    }
}
