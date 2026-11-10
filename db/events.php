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
 * Event observers for automatic submission analysis and its stored results.
 *
 * Handlers read configuration, keep the plugin's own rows in step and queue work.
 *
 * @package    plagiarism_dixeo
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname' => '\assignsubmission_onlinetext\event\assessable_uploaded',
        'callback' => '\plagiarism_dixeo\observer::submission_uploaded',
    ],
    [
        'eventname' => '\assignsubmission_file\event\assessable_uploaded',
        'callback' => '\plagiarism_dixeo\observer::submission_uploaded',
    ],
    [
        'eventname' => '\mod_assign\event\assessable_submitted',
        'callback' => '\plagiarism_dixeo\observer::submission_submitted',
    ],
    [
        'eventname' => '\assignsubmission_onlinetext\event\submission_updated',
        'callback' => '\plagiarism_dixeo\observer::submission_updated',
    ],
    [
        'eventname' => '\assignsubmission_file\event\submission_updated',
        'callback' => '\plagiarism_dixeo\observer::submission_updated',
    ],
    [
        'eventname' => '\mod_assign\event\submission_graded',
        'callback' => '\plagiarism_dixeo\observer::submission_graded',
    ],
    [
        'eventname' => '\mod_assign\event\submission_removed',
        'callback' => '\plagiarism_dixeo\observer::submission_removed',
    ],
    [
        'eventname' => '\local_dixeo\event\setting_updated',
        'callback' => '\plagiarism_dixeo\observer::setting_updated',
    ],
];
