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
 * Storage layer for plagiarism_dixeo authorship records (table plagiarism_dixeo_authorship).
 *
 * @package    plagiarism_dixeo
 * @author     Josemaria Bolanos <admin@mako.digital>
 * @copyright  2026 Dixeo.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Persistence for authorship records: create, read by id/context, update (initial result or verification).
 */
class author_storage {

    /** Status: record created, evaluation not yet run. */
    public const STATUS_PENDING = 'pending';

    /** Status: evaluation complete, no verification test required. */
    public const STATUS_COMPLETE = 'complete';

    /** Status: verification test required before submission. */
    public const STATUS_VERIFICATION_REQUIRED = 'verification_required';

    /**
     * Create an authorship record (pending or from initial backend response).
     *
     * @param int $userid User id.
     * @param string $component e.g. mod_assign.
     * @param string $area e.g. submission.
     * @param int $itemid Area-specific id (e.g. cmid for assign).
     * @param array $frombackend Optional: initial_confidence, initial_explanation, quiz_data (JSON string or array),
     *                            source_timemodified, source_attempt (from source record; module-specific).
     * @return \stdClass|null Created record or null on failure.
     */
    public static function create_record(int $userid, string $component, string $area, int $itemid, array $frombackend = []): ?\stdClass {
        global $DB;

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
            'final_confidence' => null,
            'final_explanation' => null,
            'timecreated' => $now,
            'timemodified' => $now,
        ];

        if (array_key_exists('source_timemodified', $frombackend) && $frombackend['source_timemodified'] !== null) {
            $record->source_timemodified = (int) $frombackend['source_timemodified'];
        }
        if (array_key_exists('source_attempt', $frombackend) && $frombackend['source_attempt'] !== null) {
            $record->source_attempt = (int) $frombackend['source_attempt'];
        }
        if (array_key_exists('initial_confidence', $frombackend)) {
            $record->initial_confidence = (float) ($frombackend['initial_confidence'] ?? 0);
        }
        if (array_key_exists('initial_explanation', $frombackend)) {
            $record->initial_explanation = (string) ($frombackend['initial_explanation'] ?? '');
        }
        if (array_key_exists('quiz_data', $frombackend)) {
            $record->quiz_data = $frombackend['quiz_data'] === null
                ? null
                : (is_string($frombackend['quiz_data'])
                    ? $frombackend['quiz_data']
                    : json_encode($frombackend['quiz_data']));
        }
        $hasquiz = $record->quiz_data !== null && $record->quiz_data !== '';
        if (!$hasquiz && $record->initial_confidence !== null) {
            $record->final_confidence = $record->initial_confidence;
            $record->final_explanation = $record->initial_explanation;
        }
        $record->status = $hasquiz
            ? self::STATUS_VERIFICATION_REQUIRED
            : ($record->initial_confidence !== null ? self::STATUS_COMPLETE : self::STATUS_PENDING);

        $id = $DB->insert_record('plagiarism_dixeo_authorship', $record);
        return $id ? $DB->get_record('plagiarism_dixeo_authorship', ['id' => $id]) : null;
    }

    /**
     * Get an authorship record by id.
     *
     * @param int $id Record id.
     * @return \stdClass|null
     */
    public static function get_record(int $id): ?\stdClass {
        global $DB;
        $record = $DB->get_record('plagiarism_dixeo_authorship', ['id' => $id]);
        return $record ?: null;
    }

    /**
     * Get an authorship record by user and context (component, area, itemid).
     *
     * @param int $userid User id.
     * @param string $component e.g. mod_assign.
     * @param string $area e.g. submission.
     * @param int $itemid Area-specific id.
     * @return \stdClass|null
     */
    public static function get_record_by_context(int $userid, string $component, string $area, int $itemid): ?\stdClass {
        global $DB;
        $record = $DB->get_record('plagiarism_dixeo_authorship', [
            'userid' => $userid,
            'component' => $component,
            'area' => $area,
            'itemid' => $itemid,
        ]);
        return $record ?: null;
    }

    /**
     * Update record after verification (quiz submission).
     *
     * @param int $id Record id.
     * @param array $data Keys: quiz_responses (JSON string or array), response_timings (optional),
     *                    final_confidence, quiz_grade, final_explanation.
     * @return bool Success.
     */
    public static function update_record(int $id, array $data): bool {
        global $DB;

        $record = $DB->get_record('plagiarism_dixeo_authorship', ['id' => $id]);
        if (!$record) {
            return false;
        }

        $record->timemodified = time();
        $record->status = self::STATUS_COMPLETE;

        if (isset($data['quiz_responses'])) {
            $record->quiz_responses = is_string($data['quiz_responses'])
                ? $data['quiz_responses'] : json_encode($data['quiz_responses']);
        }
        if (isset($data['response_timings'])) {
            $record->response_timings = is_string($data['response_timings'])
                ? $data['response_timings'] : json_encode($data['response_timings']);
        }
        if (isset($data['final_confidence'])) {
            $record->final_confidence = (float) $data['final_confidence'];
        }
        if (array_key_exists('quiz_grade', $data)) {
            $record->quiz_grade = $data['quiz_grade'] === null ? null : (float) $data['quiz_grade'];
        }
        if (isset($data['quiz_explanation'])) {
            $record->quiz_explanation = $data['quiz_explanation'];
        }
        if (isset($data['final_explanation'])) {
            $record->final_explanation = $data['final_explanation'];
        }

        return $DB->update_record('plagiarism_dixeo_authorship', $record);
    }

    /**
     * Update an existing record with initial evaluation result (e.g. from async task).
     *
     * @param int $id Record id.
     * @param array $data Keys: initial_confidence, initial_explanation, quiz_data (string or array),
     *                    source_timemodified, source_attempt (optional).
     * @return bool Success.
     */
    public static function update_initial(int $id, array $data): bool {
        global $DB;

        $record = $DB->get_record('plagiarism_dixeo_authorship', ['id' => $id]);
        if (!$record) {
            return false;
        }

        $record->timemodified = time();
        if (array_key_exists('source_timemodified', $data) && $data['source_timemodified'] !== null) {
            $record->source_timemodified = (int) $data['source_timemodified'];
        }
        if (array_key_exists('source_attempt', $data) && $data['source_attempt'] !== null) {
            $record->source_attempt = (int) $data['source_attempt'];
        }
        if (isset($data['initial_confidence'])) {
            $record->initial_confidence = (float) $data['initial_confidence'];
        }
        if (isset($data['initial_explanation'])) {
            $record->initial_explanation = $data['initial_explanation'];
        }
        if (array_key_exists('quiz_data', $data)) {
            $record->quiz_data = $data['quiz_data'] === null ? null : (
                is_string($data['quiz_data']) ? $data['quiz_data'] : json_encode($data['quiz_data'])
            );
        }
        $hasquiz = $record->quiz_data !== null && $record->quiz_data !== '';
        if (!$hasquiz && $record->initial_confidence !== null) {
            $record->final_confidence = $record->initial_confidence;
            $record->final_explanation = $record->initial_explanation;
        }
        $record->status = $hasquiz
            ? self::STATUS_VERIFICATION_REQUIRED
            : ($record->initial_confidence !== null ? self::STATUS_COMPLETE : self::STATUS_PENDING);

        return $DB->update_record('plagiarism_dixeo_authorship', $record);
    }

    /**
     * Get pending records for the scheduled task (evaluation_mode = async).
     *
     * @param int $limit Max records to return.
     * @return \stdClass[]
     */
    public static function get_pending_records(int $limit = 50): array {
        global $DB;
        $records = $DB->get_records(
            'plagiarism_dixeo_authorship',
            ['status' => self::STATUS_PENDING],
            'timecreated ASC',
            '*',
            0,
            $limit
        );
        return $records ? array_values($records) : [];
    }
}
