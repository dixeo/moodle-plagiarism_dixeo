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
 * Persistence for pre-submission coaching feedback.
 *
 * @package    plagiarism_dixeo
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo\feedback;

/**
 * Reads and writes plagiarism_dixeo_presubmission_feedback.
 *
 * One row per submission attempt: the feedback a student receives before submitting is
 * kept so it survives a page reload, and is discarded when the submission changes.
 */
class presubmission_feedback_storage {
    /** @var string Table owned by this class. */
    public const TABLE = 'plagiarism_dixeo_presubmission_feedback';

    /**
     * Load the feedback stored for a submission context.
     *
     * @param int $userid Student user id.
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
     * Store feedback for a submission context, replacing anything held for that attempt.
     *
     * @param int $userid Student user id.
     * @param string $component Component name.
     * @param string $area Area name.
     * @param int $itemid Area-specific id.
     * @param string $feedback Feedback HTML as returned by the API.
     * @param int|null $sourcetimemodified timemodified of the source submission.
     * @param int|null $sourceattempt Attempt number of the source submission.
     * @return \stdClass|null Stored record, or null if the insert failed.
     */
    public static function create_record(
        int $userid,
        string $component,
        string $area,
        int $itemid,
        string $feedback,
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
            'feedback' => $feedback,
            'timecreated' => $now,
            'timemodified' => $now,
        ];

        $id = $DB->insert_record(self::TABLE, $record);
        return $id ? $DB->get_record(self::TABLE, ['id' => $id]) : null;
    }

    /**
     * Delete the feedback held for a submission context.
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
}
