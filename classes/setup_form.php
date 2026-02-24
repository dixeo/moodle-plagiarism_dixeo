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
 * Setup form for plagiarism_dixeo (Urkund-style: plain field names, load/save via get_config/set_config).
 *
 * @package   plagiarism_dixeo
 * @author    Josemaria Bolanos <admin@mako.digital>
 * @copyright 2026 Dixeo.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class plagiarism_dixeo_setup_form extends moodleform {

    public function definition() {
        $mform = $this->_form;

        $mform->addElement('header', 'plagiarism_dixeo', get_string('plagiarism_settings_heading', 'plagiarism_dixeo'));
        $mform->setExpanded('plagiarism_dixeo', true);
        $mform->addElement('static', 'plagiarism_dixeo_desc', '', get_string('plagiarism_settings_heading_desc', 'plagiarism_dixeo'));

        $mform->addElement('checkbox', 'enabled', get_string('enable_help', 'plagiarism_dixeo'));
        $mform->setDefault('enabled', 0);

        $mform->addElement('textarea', 'student_disclosure', get_string('student_disclosure', 'plagiarism_dixeo'),
            ['rows' => 4, 'cols' => 60]);
        $mform->addHelpButton('student_disclosure', 'student_disclosure', 'plagiarism_dixeo');
        $mform->setType('student_disclosure', PARAM_TEXT);
        $mform->setDefault('student_disclosure', get_string('student_disclosure', 'plagiarism_dixeo'));

        $mform->addElement('checkbox', 'enable_mod_assign', get_string('enable_mod_assign', 'plagiarism_dixeo'));
        $mform->setDefault('enable_mod_assign', 1);

        $mform->addElement('select', 'assign_trigger', get_string('assign_trigger', 'plagiarism_dixeo'), [
            'upload' => get_string('assign_trigger_upload', 'plagiarism_dixeo'),
            'submit' => get_string('assign_trigger_submit', 'plagiarism_dixeo'),
        ]);
        $mform->addHelpButton('assign_trigger', 'assign_trigger', 'plagiarism_dixeo');
        $mform->setDefault('assign_trigger', 'submit');

        $mform->addElement('select', 'evaluation_mode', get_string('evaluation_mode', 'plagiarism_dixeo'), [
            'sync'  => get_string('evaluation_mode_sync', 'plagiarism_dixeo'),
            'async' => get_string('evaluation_mode_async', 'plagiarism_dixeo'),
        ]);
        $mform->addHelpButton('evaluation_mode', 'evaluation_mode', 'plagiarism_dixeo');
        $mform->setDefault('evaluation_mode', 'async');

        $mform->addElement('header', 'authorship_verification', get_string('authorship_settings_heading', 'plagiarism_dixeo'));
        $mform->setExpanded('authorship_verification', true);
        $mform->addElement('static', 'authorship_verification_desc', '', get_string('authorship_settings_heading_desc', 'plagiarism_dixeo'));

        $mform->addElement('checkbox', 'verification_test', get_string('verification_test', 'plagiarism_dixeo'));
        $mform->addHelpButton('verification_test', 'verification_test', 'plagiarism_dixeo');
        $mform->setDefault('verification_test', 1);

        $mform->addElement('text', 'authorship_threshold', get_string('authorship_threshold', 'plagiarism_dixeo'));
        $mform->addHelpButton('authorship_threshold', 'authorship_threshold', 'plagiarism_dixeo');
        $mform->setType('authorship_threshold', PARAM_INT);
        $mform->setDefault('authorship_threshold', 75);

        $questioncountchoices = [1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5'];
        $mform->addElement('select', 'authorship_count_true_false', get_string('authorship_count_true_false', 'plagiarism_dixeo'), $questioncountchoices);
        $mform->addHelpButton('authorship_count_true_false', 'authorship_count_true_false', 'plagiarism_dixeo');
        $mform->setDefault('authorship_count_true_false', 1);

        $questioncountchoiceswithzero = [0 => '0'] + $questioncountchoices;
        $mform->addElement('select', 'authorship_count_multiple_choice', get_string('authorship_count_multiple_choice', 'plagiarism_dixeo'), $questioncountchoiceswithzero);
        $mform->addHelpButton('authorship_count_multiple_choice', 'authorship_count_multiple_choice', 'plagiarism_dixeo');
        $mform->setDefault('authorship_count_multiple_choice', 1);

        $mform->addElement('select', 'authorship_count_open', get_string('authorship_count_open', 'plagiarism_dixeo'), $questioncountchoiceswithzero);
        $mform->addHelpButton('authorship_count_open', 'authorship_count_open', 'plagiarism_dixeo');
        $mform->setDefault('authorship_count_open', 1);

        $this->add_action_buttons(true);
    }
}
