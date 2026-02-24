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
 * Admin settings for plagiarism_dixeo.
 * Uses a custom form and get_config/set_config like plagiarism_urkund.
 *
 * @package    plagiarism_dixeo
 * @author     Josemaria Bolanos <admin@mako.digital>
 * @copyright  2026 Dixeo.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once(__DIR__ . '/classes/setup_form.php');

require_login();
admin_externalpage_setup('plagiarismdixeo');

$context = context_system::instance();
require_capability('moodle/site:config', $context);

$mform = new plagiarism_dixeo_setup_form();

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/admin/plagiarism.php'));
}

if ($data = $mform->get_data()) {
    // Checkboxes: when unchecked they are not in $data.
    if (!isset($data->enabled)) {
        $data->enabled = 0;
    }
    if (!isset($data->enable_mod_assign)) {
        $data->enable_mod_assign = 0;
    }
    if (!isset($data->verification_test)) {
        $data->verification_test = 0;
    }

    $configfields = [
        'enabled',
        'student_disclosure',
        'enable_mod_assign',
        'assign_trigger',
        'evaluation_mode',
        'verification_test',
        'authorship_threshold',
        'authorship_count_true_false',
        'authorship_count_multiple_choice',
        'authorship_count_open',
    ];
    foreach ($configfields as $field) {
        if (!isset($data->$field)) {
            continue;
        }
        $value = $data->$field;
        if (is_string($value)) {
            $value = trim($value);
        }
        set_config($field, $value, 'plagiarism_dixeo');
    }
    redirect($PAGE->url, get_string('changessaved'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Load current config and pass to form (same as Urkund).
$config = (array) get_config('plagiarism_dixeo');
$mform->set_data($config);

echo $OUTPUT->header();
echo $OUTPUT->box_start('generalbox boxaligncenter', 'intro');
$mform->display();
echo $OUTPUT->box_end();
echo $OUTPUT->footer();
