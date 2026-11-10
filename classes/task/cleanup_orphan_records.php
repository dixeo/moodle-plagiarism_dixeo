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
 * Scheduled task that removes analysis rows left behind by deleted work.
 *
 * @package    plagiarism_dixeo
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo\task;

use plagiarism_dixeo\authorship\author_storage;
use plagiarism_dixeo\feedback\grade_feedback_storage;
use plagiarism_dixeo\feedback\presubmission_feedback_storage;
use plagiarism_dixeo\grading\autograde_enabler;

/**
 * Deletes analyses whose submission is gone and automatic grading records whose assignment is gone.
 *
 * Course deletion and course reset remove submissions without any hook this plugin can use,
 * and the Privacy API reaches analysis rows only through their submission, so rows left
 * behind would be kept for ever and missed by export and erasure.
 */
class cleanup_orphan_records extends \core\task\scheduled_task {
    /**
     * Human readable task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_cleanup_orphan_records', 'plagiarism_dixeo');
    }

    /**
     * Delete the orphaned rows.
     */
    public function execute(): void {
        global $DB;

        $tables = [author_storage::TABLE, presubmission_feedback_storage::TABLE, grade_feedback_storage::TABLE];
        foreach ($tables as $table) {
            $DB->delete_records_select(
                $table,
                "component = :component AND area = :area
                 AND NOT EXISTS (SELECT 1 FROM {assign_submission} s WHERE s.id = itemid)",
                ['component' => 'mod_assign', 'area' => 'submission']
            );
        }

        $DB->delete_records_select(
            autograde_enabler::TABLE,
            'NOT EXISTS (SELECT 1 FROM {course_modules} cm WHERE cm.id = cmid)'
        );
    }
}
