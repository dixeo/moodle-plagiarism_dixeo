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
 * Dixeo plagiarism plugin - main library.
 *
 * @package    plagiarism_dixeo
 * @author     Josemaria Bolanos <admin@mako.digital>
 * @copyright  2026 Dixeo.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/plagiarism/lib.php');
require_once(__DIR__ . '/classes/local/author_storage.php');

/**
 * Dixeo plagiarism plugin: authorship evaluation (event-driven).
 */
class plagiarism_plugin_dixeo extends plagiarism_plugin {

    /**
     * Plugin settings (enabled, enable_mod_assign, etc.).
     *
     * @return array|false Config array or false if disabled.
     */
    public static function get_settings() {
        if (!get_config('plagiarism_dixeo', 'enabled')) {
            return false;
        }
        return (array) get_config('plagiarism_dixeo');
    }

    /**
     * Get per-activity config for a course module.
     *
     * @param int $cmid Course module id.
     * @return array name => value
     */
    public static function get_activity_config(int $cmid): array {
        global $DB;
        if ($cmid <= 0) {
            return [];
        }
        $records = $DB->get_records_menu('plagiarism_dixeo_config', ['cm' => $cmid], '', 'name, value');
        return $records ?: [];
    }

    /**
     * Return links to show beside a submission (assign only in v1).
     *
     * @param array $linkarray Contains userid, cmid, content/file, course, etc.
     * @return string HTML fragment or empty string.
     */
    public function get_links($linkarray) {
        $settings = self::get_settings();
        if (!$settings || empty($settings['enable_mod_assign'])) {
            return '';
        }

        $cmid = (int) ($linkarray['cmid'] ?? 0);
        $userid = (int) ($linkarray['userid'] ?? 0);
        if ($cmid <= 0 || $userid <= 0) {
            return '';
        }

        $config = self::get_activity_config($cmid);
        if (empty($config['use_dixeo'])) {
            return '';
        }

        $itemid = (int) ($linkarray['submissionid'] ?? 0);
        if ($itemid <= 0) {
            $itemid = \plagiarism_dixeo\modules\assign::get_latest_submission_id($cmid, $userid);
        }
        $record = $itemid > 0
            ? \plagiarism_dixeo\local\author_storage::get_record_by_context($userid, 'mod_assign', 'submission', $itemid)
            : null;
        if ($record === null && $cmid > 0) {
            $record = \plagiarism_dixeo\local\author_storage::get_record_by_context($userid, 'mod_assign', 'submission', $cmid);
        }
        if ($record === null) {
            return '';
        }

        global $PAGE;
        $PAGE->requires->js_call_amd('plagiarism_dixeo/panel', 'init', []);
        return self::get_links_badges_html($record, $cmid, $config);
    }

    /**
     * Build panel HTML for a single authorship record (pending, verification required, or complete).
     * Renders via Mustache template; panel maximize/minimize is loaded via js_call_amd when panels are shown.
     *
     * @param \stdClass $record Authorship record.
     * @param int $cmid Course module id (for verify link).
     * @param array $config Activity config (show_initial_score, show_test_score, show_final_score).
     * @return string HTML fragment.
     */
    protected static function get_links_badges_html(\stdClass $record, int $cmid, array $config): string {
        global $OUTPUT;

        $context = \context_module::instance($cmid);
        $isteacher = has_capability('mod/assign:grade', $context);
        if ($isteacher) {
            $initialvis = $testvis = $finalvis = 'scoreandfeedback';
        } else {
            $initialvis = $config['show_initial_score'] ?? $config['show_score_to_student'] ?? 'score';
            $testvis = $config['show_test_score'] ?? $config['show_test_results_to_student'] ?? 'score';
            $finalvis = $config['show_final_score'] ?? $config['show_score_to_student'] ?? 'score';
        }
        $containerid = 'plagiarism-dixeo-panels-' . $cmid;

        if ($record->status === \plagiarism_dixeo\local\author_storage::STATUS_PENDING) {
            $panels = [
                self::build_panel_data(get_string('status_pending_badge', 'plagiarism_dixeo'), 'bg-warning text-dark', '', ''),
            ];
            return $OUTPUT->render_from_template('plagiarism_dixeo/panels', [
                'containerid' => $containerid,
                'panels'      => $panels,
            ]);
        }

        if ($record->status === \plagiarism_dixeo\local\author_storage::STATUS_VERIFICATION_REQUIRED) {
            $label = get_string('verification_required_badge', 'plagiarism_dixeo');
            $link = html_writer::tag('a', $label, [
                'class'           => 'plagiarism-dixeo-open-verification-modal text-white text-decoration-none btn btn-link p-0',
                'href'            => '#',
                'role'            => 'button',
                'data-record-id'  => (int) $record->id,
                'data-cmid'       => (int) $cmid,
            ]);
            $panels = [self::build_panel_data($link, 'bg-warning text-white', '', '')];
            return $OUTPUT->render_from_template('plagiarism_dixeo/panels', [
                'containerid' => $containerid,
                'panels'      => $panels,
            ]);
        }

        if ($record->status === \plagiarism_dixeo\local\author_storage::STATUS_VERIFICATION_IN_PROGRESS) {
            $label = get_string('verification_in_progress_badge', 'plagiarism_dixeo');
            $link = html_writer::tag('a', $label, [
                'class'           => 'plagiarism-dixeo-open-verification-modal text-white text-decoration-none btn btn-link p-0',
                'href'            => '#',
                'role'            => 'button',
                'data-record-id'  => (int) $record->id,
                'data-cmid'       => (int) $cmid,
            ]);
            $panels = [self::build_panel_data($link, 'bg-info text-white', '', '')];
            return $OUTPUT->render_from_template('plagiarism_dixeo/panels', [
                'containerid' => $containerid,
                'panels'      => $panels,
            ]);
        }

        if ($record->status !== \plagiarism_dixeo\local\author_storage::STATUS_COMPLETE) {
            return '';
        }

        $panelsdata = [];
        $panelindex = 0;
        $accordionid = 'plagiarism-dixeo-accordion-' . (int) $record->id;
        $hasquiz = $record->quiz_grade !== null;

        if (!$hasquiz) {
            $finalval = $record->final_confidence !== null ? round((float) $record->final_confidence, 1) : null;
            if ($finalvis === 'hide' || $finalval === null) {
                return '';
            }
            $finalexpl = ($finalvis === 'scoreandfeedback') ? self::get_record_explanation_for_display($record, 'final') : '';
            $panelsdata[] = self::build_panel_data(
                get_string('display_final_score', 'plagiarism_dixeo', $finalval),
                'bg-success text-white',
                $finalexpl,
                ''
            );
        } else {
            if ($initialvis !== 'hide' && $record->initial_confidence !== null) {
                $panelindex++;
                $initialval = round((float) $record->initial_confidence, 1);
                $initialexpl = ($initialvis === 'scoreandfeedback') ? self::get_record_explanation_for_display($record, 'initial') : '';
                $panelsdata[] = self::build_panel_data(
                    get_string('display_initial_score', 'plagiarism_dixeo', $initialval),
                    'bg-info text-white',
                    $initialexpl,
                    'plagiarism-dixeo-collapse-' . (int) $record->id . '-' . $panelindex
                );
            }
            if ($testvis !== 'hide') {
                $panelindex++;
                $gradeval = round((float) $record->quiz_grade, 1);
                $quizbody = '';
                if ($testvis === 'scoreandfeedback') {
                    $hasresponses = !empty($record->quiz_responses);
                    $quizbody = $hasresponses
                        ? \plagiarism_dixeo\local\authorship::render_quiz_results($record, [])
                        : self::get_record_explanation_for_display($record, 'quiz');
                }
                $panelsdata[] = self::build_panel_data(
                    get_string('display_test_score', 'plagiarism_dixeo', $gradeval),
                    'bg-primary text-white',
                    $quizbody,
                    'plagiarism-dixeo-collapse-' . (int) $record->id . '-' . $panelindex
                );
            }
            if ($finalvis !== 'hide' && $record->final_confidence !== null) {
                $panelindex++;
                $finalval = round((float) $record->final_confidence, 1);
                $finalexpl = ($finalvis === 'scoreandfeedback') ? self::get_record_explanation_for_display($record, 'final') : '';
                $panelsdata[] = self::build_panel_data(
                    get_string('display_final_score', 'plagiarism_dixeo', $finalval),
                    'bg-success text-white',
                    $finalexpl,
                    'plagiarism-dixeo-collapse-' . (int) $record->id . '-' . $panelindex
                );
            }
        }

        if ($panelsdata === []) {
            return '';
        }
        return $OUTPUT->render_from_template('plagiarism_dixeo/panels', [
            'containerid' => $containerid,
            'panels'      => $panelsdata,
        ]);
    }

    /**
     * Build one panel's template data (titlehtml, headerclass, hasbody, bodyhtml, collapsible, collapseid, maximizelabel).
     *
     * @param string $titlehtml Header content (plain text or link HTML).
     * @param string $headerclass Bootstrap classes for the header.
     * @param string $bodyhtml Explanation/body HTML; empty to omit body.
     * @param string $collapseid Unique id for collapse target; empty if not collapsible.
     * @return array Template data for one panel.
     */
    protected static function build_panel_data(string $titlehtml, string $headerclass, string $bodyhtml, string $collapseid = ''): array {
        $hasbody = $bodyhtml !== '';
        $collapsible = $hasbody && $collapseid !== '';
        $data = [
            'titlehtml'    => $titlehtml,
            'headerclass'  => $headerclass,
            'hasbody'      => $hasbody,
            'bodyhtml'     => $bodyhtml,
            'collapsible'  => $collapsible,
            'collapseid'   => $collapseid,
        ];
        if ($hasbody) {
            $data['maximizelabel'] = get_string('panel_maximize', 'plagiarism_dixeo');
        }
        return $data;
    }

    /**
     * Get sanitised explanation text for display in a panel (when visibility is score and feedback).
     *
     * @param \stdClass $record Authorship record.
     * @param string $which One of 'initial', 'quiz', 'final'.
     * @return string Sanitised HTML-safe string.
     */
    protected static function get_record_explanation_for_display(\stdClass $record, string $which = 'final'): string {
        if ($which === 'initial') {
            $raw = trim((string) ($record->initial_explanation ?? ''));
        } elseif ($which === 'quiz') {
            $raw = trim((string) ($record->quiz_explanation ?? ''));
        } else {
            $raw = trim((string) ($record->final_explanation ?? ''));
        }
        return $raw !== '' ? s($raw) : '';
    }

    /**
     * Print disclosure notifying users that submissions will be checked for authorship.
     *
     * @param int $cmid Course module id.
     * @return string HTML output or empty.
     */
    public function print_disclosure($cmid) {
        $settings = self::get_settings();
        if (!$settings || empty($settings['enable_mod_assign'])) {
            return '';
        }

        $config = self::get_activity_config($cmid);
        if (empty($config['use_dixeo'])) {
            return '';
        }

        $disclosure = get_config('plagiarism_dixeo', 'student_disclosure');
        if (trim((string) $disclosure) === '') {
            return '';
        }

        return html_writer::div(format_text($disclosure, FORMAT_MOODLE), 'plagiarism_dixeo_disclosure');
    }
}

/**
 * Add Dixeo settings to the assignment module form (per-activity use_dixeo).
 *
 * @param \MoodleQuickForm $formwrapper Form wrapper (provides get_coursemodule()).
 * @param \MoodleQuickForm $mform The form instance.
 */
function plagiarism_dixeo_coursemodule_standard_elements($formwrapper, $mform) {
    global $DB;

    if (!preg_match('/^mod_([^_]+)_mod_form$/', get_class($formwrapper), $matches)) {
        return;
    }
    if (('mod_' . $matches[1]) !== 'mod_assign') {
        return;
    }
    if ($mform->elementExists('use_dixeo')) {
        return;
    }

    $cmid = null;
    if ($cm = $formwrapper->get_coursemodule()) {
        $cmid = $cm->id;
    }
    $config = $cmid ? plagiarism_plugin_dixeo::get_activity_config($cmid) : [];
    $use = isset($config['use_dixeo']) ? (string) $config['use_dixeo'] : '0';
    $initialvis = $config['show_initial_score'] ?? $config['show_score_to_student'] ?? 'score';
    $testvis = $config['show_test_score'] ?? $config['show_test_results_to_student'] ?? 'score';
    $finalvis = $config['show_final_score'] ?? $config['show_score_to_student'] ?? 'score';

    $settings = plagiarism_plugin_dixeo::get_settings();
    $enabled = $settings && !empty($settings['enable_mod_assign']);

    $visibilityoptions = [
        'hide' => get_string('visibility_hide', 'plagiarism_dixeo'),
        'score' => get_string('visibility_score', 'plagiarism_dixeo'),
        'scoreandfeedback' => get_string('visibility_score_and_feedback', 'plagiarism_dixeo'),
    ];

    if ($enabled) {
        $mform->addElement('header', 'plagiarism_dixeo', get_string('pluginname', 'plagiarism_dixeo'));
        $mform->addElement('select', 'use_dixeo', get_string('use_dixeo', 'plagiarism_dixeo'), [
            '0' => get_string('no'),
            '1' => get_string('yes'),
        ]);
        $mform->setDefault('use_dixeo', $use);
        $mform->setType('use_dixeo', PARAM_ALPHANUM);
        $mform->addHelpButton('use_dixeo', 'use_dixeo', 'plagiarism_dixeo');

        $mform->addElement('select', 'dixeo_show_initial_score', get_string('display_initial_score_visibility', 'plagiarism_dixeo'), $visibilityoptions);
        $mform->setDefault('dixeo_show_initial_score', $initialvis);
        $mform->setType('dixeo_show_initial_score', PARAM_ALPHA);
        $mform->addHelpButton('dixeo_show_initial_score', 'display_initial_score_visibility', 'plagiarism_dixeo');
        $mform->hideIf('dixeo_show_initial_score', 'use_dixeo', 'eq', '0');

        $mform->addElement('select', 'dixeo_show_test_score', get_string('display_test_score_visibility', 'plagiarism_dixeo'), $visibilityoptions);
        $mform->setDefault('dixeo_show_test_score', $testvis);
        $mform->setType('dixeo_show_test_score', PARAM_ALPHA);
        $mform->addHelpButton('dixeo_show_test_score', 'display_test_score_visibility', 'plagiarism_dixeo');
        $mform->hideIf('dixeo_show_test_score', 'use_dixeo', 'eq', '0');

        $mform->addElement('select', 'dixeo_show_final_score', get_string('display_final_score_visibility', 'plagiarism_dixeo'), $visibilityoptions);
        $mform->setDefault('dixeo_show_final_score', $finalvis);
        $mform->setType('dixeo_show_final_score', PARAM_ALPHA);
        $mform->addHelpButton('dixeo_show_final_score', 'display_final_score_visibility', 'plagiarism_dixeo');
        $mform->hideIf('dixeo_show_final_score', 'use_dixeo', 'eq', '0');
    } else {
        $mform->addElement('hidden', 'use_dixeo', $use);
        $mform->setType('use_dixeo', PARAM_ALPHANUM);
        $mform->addElement('hidden', 'dixeo_show_initial_score', $initialvis);
        $mform->setType('dixeo_show_initial_score', PARAM_ALPHA);
        $mform->addElement('hidden', 'dixeo_show_test_score', $testvis);
        $mform->setType('dixeo_show_test_score', PARAM_ALPHA);
        $mform->addElement('hidden', 'dixeo_show_final_score', $finalvis);
        $mform->setType('dixeo_show_final_score', PARAM_ALPHA);
    }
}

/**
 * Add Dixeo form elements after data is set (fallback if standard_elements did not run).
 * Ensures elements exist when the form is displayed so they are present in the HTML and submitted.
 *
 * @param \moodleform_mod $formwrapper Form wrapper.
 * @param \MoodleQuickForm $mform The form instance.
 */
function plagiarism_dixeo_coursemodule_definition_after_data($formwrapper, $mform) {
    if (!$mform->elementExists('use_dixeo')) {
        plagiarism_dixeo_coursemodule_standard_elements($formwrapper, $mform);
    }
}

/**
 * Save Dixeo per-activity config when assignment is saved.
 * If form data does not contain our keys (e.g. callback did not run at definition time),
 * values are read from the request so save still works.
 *
 * @param \stdClass $data Form data (coursemodule, use_dixeo, dixeo_show_score_to_student, etc.)
 * @param \stdClass $course
 * @return \stdClass $data
 */
function plagiarism_dixeo_coursemodule_edit_post_actions($data, $course) {
    global $DB;

    if (empty($data->coursemodule) || (isset($data->modulename) && $data->modulename !== 'assign')) {
        return $data;
    }

    $cmid = (int) $data->coursemodule;
    $existing = $DB->get_records('plagiarism_dixeo_config', ['cm' => $cmid], 'name', 'name, id, value');
    $existingbykey = [];
    foreach ($existing as $row) {
        $existingbykey[$row->name] = $row;
    }

    $use = $data->use_dixeo ?? optional_param('use_dixeo', isset($existingbykey['use_dixeo']) ? $existingbykey['use_dixeo']->value : '0', PARAM_ALPHANUM);
    $initialvis = $data->dixeo_show_initial_score ?? optional_param('dixeo_show_initial_score', isset($existingbykey['show_initial_score']) ? $existingbykey['show_initial_score']->value : 'score', PARAM_ALPHA);
    $testvis = $data->dixeo_show_test_score ?? optional_param('dixeo_show_test_score', isset($existingbykey['show_test_score']) ? $existingbykey['show_test_score']->value : 'score', PARAM_ALPHA);
    $finalvis = $data->dixeo_show_final_score ?? optional_param('dixeo_show_final_score', isset($existingbykey['show_final_score']) ? $existingbykey['show_final_score']->value : 'score', PARAM_ALPHA);

    $tosave = [
        'use_dixeo' => (string) $use,
        'show_initial_score' => (string) $initialvis,
        'show_test_score' => (string) $testvis,
        'show_final_score' => (string) $finalvis,
    ];

    foreach ($tosave as $name => $value) {
        $record = (object) ['cm' => $cmid, 'name' => $name, 'value' => $value];
        if (isset($existingbykey[$name]) && !empty($existingbykey[$name]->id)) {
            $record->id = $existingbykey[$name]->id;
            $DB->update_record('plagiarism_dixeo_config', $record);
        } else {
            $DB->insert_record('plagiarism_dixeo_config', $record);
        }
    }

    $data->use_dixeo = $tosave['use_dixeo'];
    $data->dixeo_show_initial_score = $tosave['show_initial_score'];
    $data->dixeo_show_test_score = $tosave['show_test_score'];
    $data->dixeo_show_final_score = $tosave['show_final_score'];
    return $data;
}
