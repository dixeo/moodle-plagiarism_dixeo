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
 * Implements assign_ux_persistence_interface on top of author_storage.
 *
 * The interface carries only the fields local_dixeo needs, so this class is a translation
 * layer: status transitions and field normalisation stay in author_storage.
 */
class authorship_persistence_adapter implements assign_ux_persistence_interface {
    /** @var string Table name owned by this plugin. */
    public const TABLE = author_storage::TABLE;

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
        return author_storage::create_record($userid, $component, $area, $itemid, [
            'initial_confidence' => $initialconfidence,
            'quiz_data' => $quizdata,
        ]);
    }

    /**
     * Load an authorship record by primary key.
     *
     * @param int $recordid Record id.
     * @return \stdClass|null
     */
    public function get_authorship_record(int $recordid): ?\stdClass {
        return author_storage::get_record($recordid);
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
        return author_storage::get_record_by_context($userid, $component, $area, $itemid);
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
        $updated = author_storage::update_record($recordid, [
            'status' => author_storage::STATUS_COMPLETE,
            'quiz_responses' => $responses,
            'final_confidence' => $finalconfidence,
            'quiz_grade' => $quizgrade,
        ]);

        return $updated ? author_storage::get_record($recordid) : null;
    }
}
