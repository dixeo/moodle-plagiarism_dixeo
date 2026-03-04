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
 * Storage for pre-submission feedback (table plagiarism_dixeo_presubmission_feedback).
 * One record per (user, component, area, itemid); stores AI feedback so the user cannot re-trigger for the same submission.
 *
 * @package    plagiarism_dixeo
 * @copyright  2026 Dixeo.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Persistence for pre-submission feedback records.
 */
class presubmission_feedback_storage {

    /**
     * Get a feedback record by context (user + component + area + itemid).
     *
     * @param int $userid User id.
     * @param string $component e.g. mod_assign.
     * @param string $area e.g. submission.
     * @param int $itemid Area-specific id (e.g. assign_submission.id).
     * @return \stdClass|null
     */
    public static function get_by_context(int $userid, string $component, string $area, int $itemid): ?\stdClass {
        global $DB;
        $record = $DB->get_record('plagiarism_dixeo_presubmission_feedback', [
            'userid'    => $userid,
            'component' => $component,
            'area'      => $area,
            'itemid'    => $itemid,
        ]);
        return $record ?: null;
    }

    /**
     * Create a pre-submission feedback record (one per submission attempt).
     *
     * @param int $userid User id.
     * @param string $component e.g. mod_assign.
     * @param string $area e.g. submission.
     * @param int $itemid Area-specific id (e.g. assign_submission.id).
     * @param string $feedback AI feedback HTML/text.
     * @param int|null $source_timemodified timemodified of the source record (e.g. assign_submission.timemodified).
     * @param int|null $source_attempt Attempt number when applicable (e.g. assign attemptnumber).
     * @return \stdClass|null Created record or null on failure.
     */
    public static function create_record(
        int $userid,
        string $component,
        string $area,
        int $itemid,
        string $feedback,
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
            'feedback'             => $feedback,
            'timecreated'          => $now,
            'timemodified'         => $now,
        ];

        $id = $DB->insert_record('plagiarism_dixeo_presubmission_feedback', $record);
        return $id ? $DB->get_record('plagiarism_dixeo_presubmission_feedback', ['id' => $id]) : null;
    }

    /**
     * Delete the pre-submission feedback record for a given context (e.g. when submission is edited).
     *
     * @param int $userid User id.
     * @param string $component e.g. mod_assign.
     * @param string $area e.g. submission.
     * @param int $itemid Area-specific id (e.g. assign_submission.id).
     * @return bool True if a record was deleted, false otherwise.
     */
    public static function delete_by_context(int $userid, string $component, string $area, int $itemid): bool {
        global $DB;
        $record = $DB->get_record('plagiarism_dixeo_presubmission_feedback', [
            'userid'    => $userid,
            'component' => $component,
            'area'      => $area,
            'itemid'    => $itemid,
        ]);
        if (!$record) {
            return false;
        }
        return $DB->delete_records('plagiarism_dixeo_presubmission_feedback', ['id' => $record->id]);
    }
}
