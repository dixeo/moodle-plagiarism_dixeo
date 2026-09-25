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
 * Site settings form for the Dixeo plagiarism plugin.
 *
 * @package    plagiarism_dixeo
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Authorship settings form.
 *
 * Plagiarism plugins render their settings on a standalone admin page rather than an
 * admin_settingpage, so the fields are defined here and persisted by settings.php.
 */
class plagiarism_dixeo_setup_form extends moodleform {
    /**
     * Field names this form owns, in the order they are stored.
     *
     * @return string[]
     */
    public static function get_config_fields(): array {
        return [
            'authorship_enabled',
            'authorship_threshold',
            'authorship_count_true_false',
            'authorship_count_multiple_choice',
            'authorship_count_open',
        ];
    }

    /**
     * Define the authorship settings fields.
     */
    public function definition() {
        $mform = $this->_form;

        $mform->addElement(
            'header',
            'authorship_settings_heading',
            get_string('authorship_settings_heading', 'plagiarism_dixeo')
        );
        $mform->setExpanded('authorship_settings_heading');

        $mform->addElement(
            'static',
            'authorship_settings_intro',
            '',
            get_string('authorship_settings_heading_desc', 'plagiarism_dixeo')
        );

        $mform->addElement(
            'advcheckbox',
            'authorship_enabled',
            get_string('authorship_enabled', 'plagiarism_dixeo'),
            get_string('authorship_enabled_desc', 'plagiarism_dixeo')
        );
        $mform->setType('authorship_enabled', PARAM_INT);
        $mform->setDefault('authorship_enabled', 1);

        $mform->addElement(
            'text',
            'authorship_threshold',
            get_string('authorship_threshold', 'plagiarism_dixeo')
        );
        $mform->setType('authorship_threshold', PARAM_INT);
        $mform->setDefault('authorship_threshold', \plagiarism_dixeo\authorship\authorship::DEFAULT_CONFIDENCE_THRESHOLD);
        $mform->addElement(
            'static',
            'authorship_threshold_info',
            '',
            get_string('authorship_threshold_desc', 'plagiarism_dixeo')
        );

        $counts = [];
        for ($i = 0; $i <= 5; $i++) {
            $counts[$i] = (string) $i;
        }

        foreach (['true_false', 'multiple_choice', 'open'] as $type) {
            $name = 'authorship_count_' . $type;
            $mform->addElement(
                'select',
                $name,
                get_string($name, 'plagiarism_dixeo'),
                $counts
            );
            $mform->setType($name, PARAM_INT);
            $mform->setDefault($name, 1);
            $mform->addElement(
                'static',
                $name . '_info',
                '',
                get_string($name . '_desc', 'plagiarism_dixeo')
            );
        }

        $this->add_action_buttons();
    }

    /**
     * Keep the threshold inside the documented 0-100 confidence scale.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array Validation errors keyed by field name.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $threshold = (int) ($data['authorship_threshold'] ?? 0);
        if (
            $threshold < \plagiarism_dixeo\authorship\authorship::CONFIDENCE_MIN
            || $threshold > \plagiarism_dixeo\authorship\authorship::CONFIDENCE_MAX
        ) {
            $errors['authorship_threshold'] = get_string('authorship_threshold_invalid', 'plagiarism_dixeo');
        }

        return $errors;
    }
}
