<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Site settings page for the Dixeo plagiarism plugin.
 *
 * Plagiarism plugins are registered as an admin_externalpage, so this file renders the
 * settings form itself and stores values with set_config under the plagiarism_dixeo scope.
 *
 * @package    plagiarism_dixeo
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/classes/setup_form.php');

require_login();
admin_externalpage_setup('plagiarismdixeo');

require_capability('moodle/site:config', context_system::instance());

$mform = new plagiarism_dixeo_setup_form();

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/admin/plagiarism.php'));
}

if ($data = $mform->get_data()) {
    // Every field is written, so clearing a checkbox stores 0 rather than leaving the old value.
    foreach (plagiarism_dixeo_setup_form::get_config_fields() as $field => $default) {
        set_config($field, $data->$field ?? $default, 'plagiarism_dixeo');
    }
    redirect($PAGE->url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$mform->set_data(
    array_merge(plagiarism_dixeo_setup_form::get_config_fields(), (array) get_config('plagiarism_dixeo'))
);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'plagiarism_dixeo'));
$mform->display();
echo $OUTPUT->footer();
