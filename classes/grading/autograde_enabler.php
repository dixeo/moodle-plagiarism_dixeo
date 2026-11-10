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
 * Who switched automatic grading on for an assignment.
 *
 * @package    plagiarism_dixeo
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo\grading;

/**
 * Storage for the user automatic grades are recorded against, one row per assignment.
 *
 * A row exists only while the assignment's own enable_auto_grading value is On and was set by a
 * real user acting for themselves. Without a row, automatic grading keeps a draft for a teacher.
 */
class autograde_enabler {
    /** @var string Table name. */
    public const TABLE = 'plagiarism_dixeo_autograde';

    /**
     * Record the user who switched automatic grading on for an assignment.
     *
     * @param int $cmid Assignment course module id.
     * @param int $userid User who switched it on.
     */
    public static function record(int $cmid, int $userid): void {
        global $DB;

        $existing = $DB->get_record(self::TABLE, ['cmid' => $cmid], 'id');
        $row = (object) ['cmid' => $cmid, 'userid' => $userid, 'timemodified' => time()];
        if ($existing) {
            $row->id = $existing->id;
            $DB->update_record(self::TABLE, $row);
            return;
        }
        $DB->insert_record(self::TABLE, $row);
    }

    /**
     * Forget who switched automatic grading on for an assignment.
     *
     * @param int $cmid Assignment course module id.
     */
    public static function clear(int $cmid): void {
        global $DB;

        $DB->delete_records(self::TABLE, ['cmid' => $cmid]);
    }

    /**
     * The recorded user for an assignment.
     *
     * @param int $cmid Assignment course module id.
     * @return int User id, or 0 when nobody is recorded.
     */
    public static function get_userid(int $cmid): int {
        global $DB;

        return (int) $DB->get_field(self::TABLE, 'userid', ['cmid' => $cmid]);
    }
}
