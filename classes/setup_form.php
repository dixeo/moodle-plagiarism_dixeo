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
 * Site settings form for the Dixeo plagiarism plugin.
 *
 * @package    plagiarism_dixeo
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

use plagiarism_dixeo\authorship\authorship;
use plagiarism_dixeo\authorship\quiz_session;

/**
 * Site settings form.
 *
 * Plagiarism plugins render their settings on a standalone admin page rather than an
 * admin_settingpage, so the fields are defined here and persisted by settings.php.
 */
class plagiarism_dixeo_setup_form extends moodleform {
    /** @var string Analyse every draft upload. */
    public const TRIGGER_UPLOAD = 'upload';

    /** @var string Analyse only when the student submits for grading. */
    public const TRIGGER_SUBMIT = 'submit';

    /** @var int Longest accepted quiz time limit, in seconds. */
    public const MAX_QUIZ_TIME = quiz_session::MAX_TIME_LIMIT;

    /** @var int Longest accepted delay on the next button, in seconds. */
    public const MAX_NEXT_BUTTON_DELAY = quiz_session::MAX_NEXT_BUTTON_DELAY;

    /**
     * Settings this form owns, mapped to the value stored when the field is absent.
     *
     * Every field is written on save, including the checkboxes, so clearing one takes effect.
     *
     * @return array<string, string|int>
     */
    public static function get_config_fields(): array {
        return [
            'enabled' => 0,
            'student_disclosure' => '',
            'enable_mod_assign' => 0,
            'assign_trigger' => self::TRIGGER_SUBMIT,
            'evaluation_mode' => 'async',
            'authorship_enabled' => 0,
            'verification_test' => 0,
            'authorship_threshold' => (int) authorship::DEFAULT_CONFIDENCE_THRESHOLD,
            'authorship_count_true_false' => authorship::DEFAULT_COUNT_TRUE_FALSE,
            'authorship_count_multiple_choice' => authorship::DEFAULT_COUNT_MULTIPLE_CHOICE,
            'authorship_count_open' => authorship::DEFAULT_COUNT_OPEN,
            'quiz_total_time' => 180,
            'quiz_time_true_false' => 60,
            'quiz_time_multiple_choice' => 60,
            'quiz_time_open' => 180,
            'allow_backwards_navigation' => 0,
            'next_button_delay_seconds' => 3,
        ];
    }

    /**
     * Values accepted by each setting that is limited to a fixed list.
     *
     * @return array<string, array>
     */
    public static function get_allowed_values(): array {
        return [
            'enabled' => [0, 1],
            'enable_mod_assign' => [0, 1],
            'assign_trigger' => [self::TRIGGER_UPLOAD, self::TRIGGER_SUBMIT],
            'evaluation_mode' => ['async', 'sync'],
            'authorship_enabled' => [0, 1],
            'verification_test' => [0, 1],
            'allow_backwards_navigation' => [0, 1],
            'authorship_count_true_false' => [0, 1, 2, 3, 4, 5],
            'authorship_count_multiple_choice' => [0, 1, 2, 3, 4, 5],
            'authorship_count_open' => [0, 1, 2, 3, 4, 5],
        ];
    }

    /**
     * Define the settings fields.
     */
    public function definition() {
        $mform = $this->_form;

        $this->add_plugin_settings($mform);
        $this->add_authorship_settings($mform);
        $this->add_quiz_timing_settings($mform);

        $this->add_action_buttons();
    }

    /**
     * Fields controlling whether the plugin runs at all and on what.
     *
     * @param MoodleQuickForm $mform Form to add elements to.
     */
    private function add_plugin_settings(MoodleQuickForm $mform): void {
        $mform->addElement(
            'header',
            'plagiarism_settings_heading',
            get_string('plagiarism_settings_heading', 'plagiarism_dixeo')
        );
        $mform->setExpanded('plagiarism_settings_heading');
        $mform->addElement(
            'static',
            'plagiarism_settings_intro',
            '',
            get_string('plagiarism_settings_heading_desc', 'plagiarism_dixeo')
        );

        $mform->addElement('advcheckbox', 'enabled', get_string('enabled', 'plagiarism_dixeo'));
        $mform->setType('enabled', PARAM_INT);
        $mform->addHelpButton('enabled', 'enabled', 'plagiarism_dixeo');

        $mform->addElement('advcheckbox', 'enable_mod_assign', get_string('enable_mod_assign', 'plagiarism_dixeo'));
        $mform->setType('enable_mod_assign', PARAM_INT);
        $mform->addHelpButton('enable_mod_assign', 'enable_mod_assign', 'plagiarism_dixeo');
        $mform->hideIf('enable_mod_assign', 'enabled', 'eq', 0);

        $mform->addElement('select', 'assign_trigger', get_string('assign_trigger', 'plagiarism_dixeo'), [
            self::TRIGGER_UPLOAD => get_string('assign_trigger_upload', 'plagiarism_dixeo'),
            self::TRIGGER_SUBMIT => get_string('assign_trigger_submit', 'plagiarism_dixeo'),
        ]);
        $mform->setType('assign_trigger', PARAM_ALPHA);
        $mform->addHelpButton('assign_trigger', 'assign_trigger', 'plagiarism_dixeo');
        $mform->hideIf('assign_trigger', 'enable_mod_assign', 'eq', 0);

        $mform->addElement('select', 'evaluation_mode', get_string('evaluation_mode', 'plagiarism_dixeo'), [
            'async' => get_string('evaluation_mode_async', 'plagiarism_dixeo'),
            'sync' => get_string('evaluation_mode_sync', 'plagiarism_dixeo'),
        ]);
        $mform->setType('evaluation_mode', PARAM_ALPHA);
        $mform->addHelpButton('evaluation_mode', 'evaluation_mode', 'plagiarism_dixeo');
        $mform->hideIf('evaluation_mode', 'enable_mod_assign', 'eq', 0);

        $mform->addElement(
            'textarea',
            'student_disclosure',
            get_string('student_disclosure', 'plagiarism_dixeo'),
            ['rows' => 4, 'cols' => 60]
        );
        $mform->setType('student_disclosure', PARAM_TEXT);
        $mform->addHelpButton('student_disclosure', 'student_disclosure', 'plagiarism_dixeo');
        $mform->hideIf('student_disclosure', 'enabled', 'eq', 0);
    }

    /**
     * Fields controlling the authorship confidence check and the quiz it can require.
     *
     * @param MoodleQuickForm $mform Form to add elements to.
     */
    private function add_authorship_settings(MoodleQuickForm $mform): void {
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

        $mform->addElement('advcheckbox', 'verification_test', get_string('verification_test', 'plagiarism_dixeo'));
        $mform->setType('verification_test', PARAM_INT);
        $mform->addHelpButton('verification_test', 'verification_test', 'plagiarism_dixeo');
        $mform->hideIf('verification_test', 'authorship_enabled', 'eq', 0);

        $mform->addElement('text', 'authorship_threshold', get_string('authorship_threshold', 'plagiarism_dixeo'));
        $mform->setType('authorship_threshold', PARAM_INT);
        $mform->addHelpButton('authorship_threshold', 'authorship_threshold', 'plagiarism_dixeo');
        $mform->hideIf('authorship_threshold', 'verification_test', 'eq', 0);

        $counts = [];
        for ($i = 0; $i <= 5; $i++) {
            $counts[$i] = (string) $i;
        }

        foreach (['true_false', 'multiple_choice', 'open'] as $type) {
            $name = 'authorship_count_' . $type;
            $mform->addElement('select', $name, get_string($name, 'plagiarism_dixeo'), $counts);
            $mform->setType($name, PARAM_INT);
            $mform->addHelpButton($name, $name, 'plagiarism_dixeo');
            $mform->hideIf($name, 'verification_test', 'eq', 0);
        }
    }

    /**
     * Fields controlling how long a student has to answer and how they move between questions.
     *
     * @param MoodleQuickForm $mform Form to add elements to.
     */
    private function add_quiz_timing_settings(MoodleQuickForm $mform): void {
        $mform->addElement(
            'header',
            'verification_quiz_timers_heading',
            get_string('verification_quiz_timers_heading', 'plagiarism_dixeo')
        );
        $mform->addElement(
            'static',
            'verification_quiz_timers_intro',
            '',
            get_string('verification_quiz_timers_desc', 'plagiarism_dixeo')
        );

        foreach (['quiz_total_time', 'quiz_time_true_false', 'quiz_time_multiple_choice', 'quiz_time_open'] as $name) {
            $mform->addElement('duration', $name, get_string($name, 'plagiarism_dixeo'));
            $mform->setType($name, PARAM_INT);
            $mform->addHelpButton($name, $name, 'plagiarism_dixeo');
            $mform->hideIf($name, 'verification_test', 'eq', 0);
        }

        $mform->addElement(
            'advcheckbox',
            'allow_backwards_navigation',
            get_string('allow_backwards_navigation', 'plagiarism_dixeo')
        );
        $mform->setType('allow_backwards_navigation', PARAM_INT);
        $mform->addHelpButton('allow_backwards_navigation', 'allow_backwards_navigation', 'plagiarism_dixeo');
        $mform->hideIf('allow_backwards_navigation', 'verification_test', 'eq', 0);

        $mform->addElement(
            'text',
            'next_button_delay_seconds',
            get_string('next_button_delay_seconds', 'plagiarism_dixeo')
        );
        $mform->setType('next_button_delay_seconds', PARAM_INT);
        $mform->addHelpButton('next_button_delay_seconds', 'next_button_delay_seconds', 'plagiarism_dixeo');
        $mform->hideIf('next_button_delay_seconds', 'allow_backwards_navigation', 'eq', 1);
    }

    /**
     * Reject values outside the range or list each setting accepts.
     *
     * @param array $data Submitted data.
     * @param array $files Submitted files.
     * @return array Validation errors keyed by field name.
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        $threshold = (int) ($data['authorship_threshold'] ?? 0);
        if ($threshold < authorship::CONFIDENCE_MIN || $threshold > authorship::CONFIDENCE_MAX) {
            $errors['authorship_threshold'] = get_string('authorship_threshold_invalid', 'plagiarism_dixeo');
        }

        foreach (['quiz_total_time', 'quiz_time_true_false', 'quiz_time_multiple_choice', 'quiz_time_open'] as $name) {
            $seconds = (int) ($data[$name] ?? 0);
            if ($seconds < 0 || $seconds > self::MAX_QUIZ_TIME) {
                $errors[$name] = get_string('quiz_time_invalid', 'plagiarism_dixeo', format_time(self::MAX_QUIZ_TIME));
            }
        }

        $delay = (int) ($data['next_button_delay_seconds'] ?? 0);
        if ($delay < 0 || $delay > self::MAX_NEXT_BUTTON_DELAY) {
            $errors['next_button_delay_seconds'] = get_string(
                'next_button_delay_invalid',
                'plagiarism_dixeo',
                self::MAX_NEXT_BUTTON_DELAY
            );
        }

        foreach (self::get_allowed_values() as $name => $allowed) {
            if (!array_key_exists($name, $data)) {
                continue;
            }
            $value = is_numeric($data[$name]) ? (int) $data[$name] : (string) $data[$name];
            if (!in_array($value, $allowed, true)) {
                $errors[$name] = get_string('setting_value_invalid', 'plagiarism_dixeo');
            }
        }

        return $errors;
    }
}
