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
 * Event fired when a student's verification quiz answers are evaluated.
 *
 * @package    plagiarism_dixeo
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo\event;

use plagiarism_dixeo\authorship\author_storage;

/**
 * A student's authorship verification quiz has been submitted and evaluated.
 *
 * Records whether the quiz was sent by the student or auto-submitted when the allowed time
 * ran out. Scores are not logged.
 */
class verification_submitted extends \core\event\base {
    /**
     * Set the event level, type and target table.
     */
    protected function init(): void {
        $this->data['crud'] = 'u';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = author_storage::TABLE;
    }

    /**
     * Event name shown in the log report.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('event_verification_submitted', 'plagiarism_dixeo');
    }

    /**
     * Human readable description for the log report.
     *
     * @return string
     */
    public function get_description(): string {
        $reason = !empty($this->other['expired']) ? 'after the allowed time expired' : 'within the allowed time';

        return "The user with id '{$this->userid}' submitted the authorship verification quiz {$reason} for the " .
            "evaluation with id '{$this->objectid}' in the assignment with course module id " .
            "'{$this->contextinstanceid}'.";
    }

    /**
     * The authorship table is plugin state and is not restored with a course.
     *
     * @return array
     */
    public static function get_objectid_mapping(): array {
        return ['db' => author_storage::TABLE, 'restore' => self::NOT_MAPPED];
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
    }
}
