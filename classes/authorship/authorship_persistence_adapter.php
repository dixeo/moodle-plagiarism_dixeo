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
 * Adapter: assign UX authorship persistence over plagiarism_dixeo_authorship.
 *
 * @package    plagiarism_dixeo
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo\authorship;

use local_dixeo\service\assign_ux_persistence_interface;

/**
 * Implements assign_ux_persistence_interface against the UX authorship table.
 */
class authorship_persistence_adapter implements assign_ux_persistence_interface {
    /** @var string Table name owned by this plugin. */
    public const TABLE = 'plagiarism_dixeo_authorship';

    /**
     * Replace any existing row for (userid, component, area, itemid), then insert.
     *
     * @param int $userid Student user id.
     * @param string $component Component name (e.g. mod_assign).
     * @param string $area Area name (e.g. submission).
     * @param int $itemid Item id (e.g. assign_submission id).
     * @param float $initialconfidence Initial human-authorship confidence 0–100.
     * @param array|null $quizdata Decoded quiz structure to store, or null.
     * @return \stdClass|null Record including id, or null on failure.
     */
    public function create_authorship_record(
        int $userid,
        string $component,
        string $area,
        int $itemid,
        float $initialconfidence,
        ?array $quizdata = null
    ): ?\stdClass {
        global $DB;

        $DB->delete_records(self::TABLE, [
            'userid' => $userid,
            'component' => $component,
            'area' => $area,
            'itemid' => $itemid,
        ]);

        $now = time();
        $record = (object) [
            'component' => $component,
            'area' => $area,
            'itemid' => $itemid,
            'initial_confidence' => $initialconfidence,
            'quiz_data' => $quizdata !== null ? json_encode($quizdata) : null,
            'quiz_responses' => null,
            'quiz_grade' => null,
            'final_confidence' => null,
            'userid' => $userid,
            'timecreated' => $now,
            'timemodified' => $now,
        ];

        $id = $DB->insert_record(self::TABLE, $record);
        if ($id === false) {
            return null;
        }
        $record->id = (int) $id;
        return $record;
    }

    /**
     * Load an authorship record by primary key.
     *
     * @param int $recordid Record id.
     * @return \stdClass|null
     */
    public function get_authorship_record(int $recordid): ?\stdClass {
        global $DB;

        if ($recordid <= 0) {
            return null;
        }
        $record = $DB->get_record(self::TABLE, ['id' => $recordid]);
        return $record ?: null;
    }

    /**
     * Load an authorship record by student + component context.
     *
     * @param int $userid Student user id.
     * @param string $component Component name.
     * @param string $area Area name.
     * @param int $itemid Item id.
     * @return \stdClass|null
     */
    public function get_authorship_by_context(
        int $userid,
        string $component,
        string $area,
        int $itemid
    ): ?\stdClass {
        global $DB;

        if ($itemid <= 0) {
            return null;
        }
        $record = $DB->get_record(self::TABLE, [
            'userid' => $userid,
            'component' => $component,
            'area' => $area,
            'itemid' => $itemid,
        ]);
        return $record ?: null;
    }

    /**
     * Persist quiz answers and evaluation results.
     *
     * @param int $recordid Record id.
     * @param array $responses Question id => answer.
     * @param float $finalconfidence Final confidence 0–100.
     * @param float|null $quizgrade Objective quiz grade 0–100, or null.
     * @return \stdClass|null Updated record, or null if missing / update failed.
     */
    public function save_quiz_result(
        int $recordid,
        array $responses,
        float $finalconfidence,
        ?float $quizgrade
    ): ?\stdClass {
        global $DB;

        $record = $DB->get_record(self::TABLE, ['id' => $recordid]);
        if (!$record) {
            return null;
        }

        $record->quiz_responses = json_encode($responses);
        $record->final_confidence = $finalconfidence;
        $record->quiz_grade = $quizgrade;
        $record->timemodified = time();
        $DB->update_record(self::TABLE, $record);

        return $record;
    }
}
