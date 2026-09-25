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
 * Dixeo plagiarism plugin library.
 *
 * @package    plagiarism_dixeo
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/plagiarism/lib.php');

/**
 * Dixeo plagiarism plugin.
 *
 * Dixeo presents its assignment analysis in the activity UI rather than in the core
 * plagiarism report area, so the inherited no-op hooks are the whole implementation.
 * The class must exist because Moodle instantiates it whenever plagiarism is enabled.
 */
class plagiarism_plugin_dixeo extends plagiarism_plugin {
    /** @var string Per-activity settings table. */
    public const CONFIG_TABLE = 'plagiarism_dixeo_config';

    /** @var string Score and explanation are withheld from the student. */
    public const VISIBILITY_HIDE = 'hide';

    /** @var string The student sees the score only. */
    public const VISIBILITY_SCORE = 'score';

    /** @var string The student sees the score and the explanation behind it. */
    public const VISIBILITY_SCOREANDFEEDBACK = 'scoreandfeedback';

    /**
     * Request-scoped cache of activity settings, keyed by course module id.
     *
     * @return \cache_application|\cache_session|\cache_store
     */
    private static function get_config_cache() {
        return \cache::make_from_params(\cache_store::MODE_REQUEST, 'plagiarism_dixeo', 'activityconfig');
    }

    /**
     * Per-activity settings and the values each one accepts.
     *
     * Both flags default to off so enabling Dixeo on an activity is always a deliberate act.
     *
     * @return array<string, array{default: string, values: string[]}>
     */
    public static function get_activity_settings(): array {
        $visibility = [self::VISIBILITY_HIDE, self::VISIBILITY_SCORE, self::VISIBILITY_SCOREANDFEEDBACK];

        return [
            'use_dixeo' => ['default' => '0', 'values' => ['0', '1']],
            'enable_auto_grading' => ['default' => '0', 'values' => ['0', '1']],
            'show_initial_score' => ['default' => self::VISIBILITY_SCORE, 'values' => $visibility],
            'show_test_score' => ['default' => self::VISIBILITY_SCORE, 'values' => $visibility],
            'show_final_score' => ['default' => self::VISIBILITY_SCORE, 'values' => $visibility],
        ];
    }

    /**
     * Site-level plugin settings.
     *
     * @return array|false Settings keyed by name, or false when the plugin is switched off.
     */
    public static function get_settings() {
        if (!get_config('plagiarism_dixeo', 'enabled')) {
            return false;
        }

        return (array) get_config('plagiarism_dixeo');
    }

    /**
     * Settings stored against a course module, with defaults filled in for anything unset.
     *
     * Results are held for the request because the grading table asks for them once per row.
     *
     * @param int $cmid Course module id.
     * @return array<string, string> Setting name => value.
     */
    public static function get_activity_config(int $cmid): array {
        global $DB;

        if ($cmid <= 0) {
            return [];
        }

        $cache = self::get_config_cache();
        $config = $cache->get($cmid);
        if (is_array($config)) {
            return $config;
        }

        $stored = $DB->get_records_menu(self::CONFIG_TABLE, ['cm' => $cmid], '', 'name, value');

        $config = [];
        foreach (self::get_activity_settings() as $name => $spec) {
            $value = isset($stored[$name]) ? (string) $stored[$name] : $spec['default'];
            $config[$name] = in_array($value, $spec['values'], true) ? $value : $spec['default'];
        }
        $cache->set($cmid, $config);

        return $config;
    }

    /**
     * Write settings for a course module, ignoring anything not in get_activity_settings().
     *
     * @param int $cmid Course module id.
     * @param array $values Setting name => value.
     */
    public static function set_activity_config(int $cmid, array $values): void {
        global $DB;

        if ($cmid <= 0) {
            return;
        }

        $existing = $DB->get_records(self::CONFIG_TABLE, ['cm' => $cmid], '', 'name, id');

        foreach (self::get_activity_settings() as $name => $spec) {
            if (!array_key_exists($name, $values)) {
                continue;
            }

            $value = (string) $values[$name];
            if (!in_array($value, $spec['values'], true)) {
                $value = $spec['default'];
            }

            if (isset($existing[$name])) {
                $DB->update_record(self::CONFIG_TABLE, (object) [
                    'id' => $existing[$name]->id,
                    'cm' => $cmid,
                    'name' => $name,
                    'value' => $value,
                ]);
            } else {
                $DB->insert_record(self::CONFIG_TABLE, (object) [
                    'cm' => $cmid,
                    'name' => $name,
                    'value' => $value,
                ]);
            }
        }

        self::get_config_cache()->delete($cmid);
    }

    /**
     * Whether Dixeo should act on a given assignment.
     *
     * @param int $cmid Course module id.
     * @return bool
     */
    public static function is_enabled_for_activity(int $cmid): bool {
        global $CFG;

        if (empty($CFG->enableplagiarism)) {
            return false;
        }

        $settings = self::get_settings();
        if (!$settings || empty($settings['enable_mod_assign'])) {
            return false;
        }

        $config = self::get_activity_config($cmid);

        return !empty($config['use_dixeo']) && $config['use_dixeo'] === '1';
    }
}

/**
 * Add the Dixeo section to the assignment settings form.
 *
 * @param moodleform_mod $formwrapper Form wrapper, exposing the course module being edited.
 * @param MoodleQuickForm $mform Form to add elements to.
 */
function plagiarism_dixeo_coursemodule_standard_elements($formwrapper, $mform) {
    global $CFG;

    if (!preg_match('/^mod_([^_]+)_mod_form$/', get_class($formwrapper), $matches) || $matches[1] !== 'assign') {
        return;
    }
    if ($mform->elementExists('use_dixeo')) {
        return;
    }

    $cm = $formwrapper->get_coursemodule();
    $config = plagiarism_plugin_dixeo::get_activity_config($cm ? (int) $cm->id : 0);
    $settings = plagiarism_plugin_dixeo::get_settings();
    $available = !empty($CFG->enableplagiarism) && $settings && !empty($settings['enable_mod_assign']);

    // Carry the stored values through the form untouched while Dixeo is unavailable, so
    // turning the plugin back on restores the activity to how it was configured.
    if (!$available) {
        foreach (plagiarism_plugin_dixeo::get_activity_settings() as $name => $spec) {
            $mform->addElement('hidden', $name, $config[$name] ?? $spec['default']);
            $mform->setType($name, PARAM_ALPHANUM);
        }
        return;
    }

    $yesno = ['0' => get_string('no'), '1' => get_string('yes')];
    $visibility = [
        plagiarism_plugin_dixeo::VISIBILITY_HIDE => get_string('visibility_hide', 'plagiarism_dixeo'),
        plagiarism_plugin_dixeo::VISIBILITY_SCORE => get_string('visibility_score', 'plagiarism_dixeo'),
        plagiarism_plugin_dixeo::VISIBILITY_SCOREANDFEEDBACK =>
            get_string('visibility_score_and_feedback', 'plagiarism_dixeo'),
    ];

    $mform->addElement('header', 'plagiarism_dixeo', get_string('pluginname', 'plagiarism_dixeo'));

    $elements = [
        'use_dixeo' => ['options' => $yesno, 'label' => 'use_dixeo'],
        'enable_auto_grading' => ['options' => $yesno, 'label' => 'enable_auto_grading'],
        'show_initial_score' => ['options' => $visibility, 'label' => 'display_initial_score_visibility'],
        'show_test_score' => ['options' => $visibility, 'label' => 'display_test_score_visibility'],
        'show_final_score' => ['options' => $visibility, 'label' => 'display_final_score_visibility'],
    ];

    foreach ($elements as $name => $element) {
        $mform->addElement('select', $name, get_string($element['label'], 'plagiarism_dixeo'), $element['options']);
        $mform->setType($name, PARAM_ALPHANUM);
        $mform->setDefault($name, $config[$name]);
        $mform->addHelpButton($name, $element['label'], 'plagiarism_dixeo');

        if ($name !== 'use_dixeo') {
            $mform->hideIf($name, 'use_dixeo', 'eq', '0');
        }
    }
}

/**
 * Select the stored Dixeo settings once the assignment form has its data.
 *
 * The values live outside the module record, so they are applied here rather than relying
 * on defaults surviving set_data().
 *
 * @param moodleform_mod $formwrapper Form wrapper, exposing the course module being edited.
 * @param MoodleQuickForm $mform Form holding the elements.
 */
function plagiarism_dixeo_coursemodule_definition_after_data($formwrapper, $mform) {
    if (!preg_match('/^mod_([^_]+)_mod_form$/', get_class($formwrapper), $matches) || $matches[1] !== 'assign') {
        return;
    }
    if ($mform->isSubmitted()) {
        return;
    }

    $cm = $formwrapper->get_coursemodule();
    if (!$cm) {
        return;
    }

    $config = plagiarism_plugin_dixeo::get_activity_config((int) $cm->id);
    foreach ($config as $name => $value) {
        if ($mform->elementExists($name)) {
            $mform->getElement($name)->setValue($value);
        }
    }
}

/**
 * Store the Dixeo settings submitted with an assignment.
 *
 * @param stdClass $data Validated form data.
 * @param stdClass $course Course the activity belongs to.
 * @return stdClass The unmodified form data.
 */
function plagiarism_dixeo_coursemodule_edit_post_actions($data, $course) {
    if (empty($data->coursemodule) || (isset($data->modulename) && $data->modulename !== 'assign')) {
        return $data;
    }

    $values = [];
    foreach (array_keys(plagiarism_plugin_dixeo::get_activity_settings()) as $name) {
        if (isset($data->$name)) {
            $values[$name] = (string) $data->$name;
        }
    }

    if ($values) {
        plagiarism_plugin_dixeo::set_activity_config((int) $data->coursemodule, $values);
    }

    return $data;
}
