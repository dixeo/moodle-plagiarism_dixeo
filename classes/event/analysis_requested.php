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
 * Event fired when submission content is sent for analysis.
 *
 * @package    plagiarism_dixeo
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo\event;

/**
 * Submission content has been sent to the Dixeo service for analysis.
 *
 * Recorded whether the request was made by the student from the activity page or queued
 * automatically on submission, so the audit trail shows every outbound transfer.
 */
class analysis_requested extends \core\event\base {
    /**
     * Set the event level and type.
     */
    protected function init(): void {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
    }

    /**
     * Event name shown in the log report.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('event_analysis_requested', 'plagiarism_dixeo');
    }

    /**
     * Human readable description for the log report.
     *
     * @return string
     */
    public function get_description(): string {
        $trigger = $this->other['trigger'] ?? 'manual';

        return "The user with id '{$this->userid}' requested Dixeo analysis ('{$trigger}') of the " .
            "submission with id '{$this->other['itemid']}' belonging to the user with id '{$this->relateduserid}' " .
            "in the assignment with course module id '{$this->contextinstanceid}'.";
    }

    /**
     * Confirm the event carries the fields the description relies on.
     *
     * @throws \coding_exception When a required field is missing.
     */
    protected function validate_data(): void {
        parent::validate_data();

        if ($this->contextlevel != CONTEXT_MODULE) {
            throw new \coding_exception('Context level must be CONTEXT_MODULE.');
        }
        if (!isset($this->relateduserid)) {
            throw new \coding_exception('The \'relateduserid\' must be set.');
        }
        if (!isset($this->other['itemid'])) {
            throw new \coding_exception('The \'itemid\' value must be set in other.');
        }
    }
}
