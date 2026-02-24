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

namespace plagiarism_dixeo\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Process pending authorship records (when evaluation_mode = async).
 *
 * @package    plagiarism_dixeo
 * @author     Josemaria Bolanos <admin@mako.digital>
 * @copyright  2026 Dixeo.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_pending_authorship extends \core\task\scheduled_task {

    /**
     * Get task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_process_pending', 'plagiarism_dixeo');
    }

    /**
     * Execute: run backend for each pending record and update.
     */
    public function execute(): void {
        if (get_config('plagiarism_dixeo', 'evaluation_mode') !== \plagiarism_dixeo\observer::EVALUATION_MODE_ASYNC) {
            return;
        }

        $records = \plagiarism_dixeo\local\author_storage::get_pending_records(50);
        foreach ($records as $record) {
            $this->process_one($record);
        }
    }

    /**
     * Process one pending record: run initial evaluation and update record.
     *
     * @param \stdClass $record Authorship record with id, component, area, itemid, userid.
     * @return void
     */
    protected function process_one(\stdClass $record): void {
        $result = \plagiarism_dixeo\observer::call_backend_initial(
            $record->component,
            $record->area,
            (int) $record->itemid,
            (int) $record->userid
        );
        if ($result === null) {
            return;
        }
        \plagiarism_dixeo\local\author_storage::update_initial($record->id, [
            'initial_confidence' => $result['initial_confidence'],
            'initial_explanation' => $result['initial_explanation'] ?? '',
            'quiz_data' => $result['quiz_data'] ?? null,
        ]);
    }
}
