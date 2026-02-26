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

        return self::get_links_badges_html($record, $cmid, $config);
    }

    /**
     * Build badge HTML for a single authorship record (pending, verification required, or complete).
     *
     * @param \stdClass $record Authorship record.
     * @param int $cmid Course module id (for verify link).
     * @param array $config Activity config (show_score_to_student, show_test_results_to_student).
     * @return string HTML fragment.
     */
    protected static function get_links_badges_html(\stdClass $record, int $cmid, array $config): string {
        $scorevis = $config['show_score_to_student'] ?? 'score';
        $testvis = $config['show_test_results_to_student'] ?? 'score';
        $containerclass = 'plagiarism-dixeo-panels mt-2';

        if ($record->status === \plagiarism_dixeo\local\author_storage::STATUS_PENDING) {
            $label = get_string('status_pending_badge', 'plagiarism_dixeo');
            return html_writer::div(
                self::build_panel_card($label, 'bg-warning text-dark', ''),
                $containerclass
            );
        }

        if ($record->status === \plagiarism_dixeo\local\author_storage::STATUS_VERIFICATION_REQUIRED) {
            $url = new \moodle_url('/plagiarism/dixeo/verify.php', ['recordid' => $record->id, 'cmid' => $cmid]);
            $label = get_string('verification_required_badge', 'plagiarism_dixeo');
            $headercontent = html_writer::link($url, $label, ['class' => 'text-white text-decoration-none']);
            return html_writer::div(
                self::build_panel_card($headercontent, 'bg-primary text-white', ''),
                $containerclass
            );
        }

        if ($record->status !== \plagiarism_dixeo\local\author_storage::STATUS_COMPLETE) {
            return '';
        }

        $panels = [];
        $hasconfidence = $record->final_confidence !== null || $record->initial_confidence !== null;
        $score = $record->final_confidence !== null
            ? round((float) $record->final_confidence, 1)
            : ($record->initial_confidence !== null ? round((float) $record->initial_confidence, 1) : null);
        $scoreexplanation = ($scorevis === 'scoreandfeedback') ? self::get_record_explanation_for_display($record) : '';

        if ($record->quiz_grade === null && $hasconfidence) {
            if ($scorevis === 'hide') {
                $panels[] = self::build_panel_card(
                    get_string('ready_for_submission_badge_plain', 'plagiarism_dixeo'),
                    'bg-success text-white',
                    ''
                );
            } else {
                $panels[] = self::build_panel_card(
                    get_string('ready_for_submission_badge', 'plagiarism_dixeo', $score ?? 0),
                    'bg-success text-white',
                    $scoreexplanation
                );
            }
        } else {
            if ($scorevis !== 'hide' && $hasconfidence) {
                $panels[] = self::build_panel_card(
                    get_string('authorship_score_badge', 'plagiarism_dixeo', $score ?? 0),
                    'bg-info text-white',
                    $scoreexplanation
                );
            }
            if ($testvis !== 'hide' && $record->final_confidence !== null && $record->quiz_grade !== null) {
                $grade = round((float) $record->quiz_grade, 1);
                $quizexplanation = ($testvis === 'scoreandfeedback') ? self::get_record_explanation_for_display($record) : '';
                $panels[] = self::build_panel_card(
                    get_string('quiz_grade_badge', 'plagiarism_dixeo', $grade),
                    'bg-success text-white',
                    $quizexplanation
                );
            }
        }

        return $panels !== [] ? html_writer::div(implode('', $panels), $containerclass) : '';
    }

    /**
     * Build a Bootstrap panel (card) with optional body for explanation.
     *
     * @param string $headercontent HTML for the panel header (title/link).
     * @param string $headerclass Bootstrap classes for the header (e.g. bg-success text-white).
     * @param string $bodyhtml Sanitised explanation HTML for the card body; empty to omit body.
     * @return string HTML fragment (card with card-header and optional card-body).
     */
    protected static function build_panel_card(string $headercontent, string $headerclass, string $bodyhtml): string {
        $inner = html_writer::div($headercontent, 'card-header ' . $headerclass);
        if ($bodyhtml !== '') {
            $id = uniqid('plagiarism-dixeo-panel-');

            $attributes = [
                'data-toggle' => 'collapse',
                'data-target' => '#' . $id,
                'aria-expanded' => 'false',
                'aria-controls' => $id,
                'style' => 'cursor: pointer;',
            ];

            $inner = html_writer::div($headercontent, 'card-header ' . $headerclass, $attributes);
            $inner .= html_writer::div(
                html_writer::div(
                    html_writer::tag('p', $bodyhtml, ['class' => 'card-text']),
                    'card-body',
                ),
                'collapse',
                ['id' => $id]
            );
        }

        return html_writer::div($inner, 'card plagiarism-dixeo-panel m-2');
    }

    /**
     * Get sanitised explanation text for display below the badge (when visibility is score and feedback).
     *
     * @param \stdClass $record Authorship record (initial_explanation, final_explanation).
     * @return string Sanitised HTML-safe string.
     */
    protected static function get_record_explanation_for_display(\stdClass $record): string {
        $raw = $record->final_explanation !== null && trim((string) $record->final_explanation) !== ''
            ? trim((string) $record->final_explanation)
            : trim((string) ($record->initial_explanation ?? ''));
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
    $scorevis = $config['show_score_to_student'] ?? 'score';
    $testvis = $config['show_test_results_to_student'] ?? 'score';

    $settings = plagiarism_plugin_dixeo::get_settings();
    $enabled = $settings && !empty($settings['enable_mod_assign']);

    if ($enabled) {
        $mform->addElement('header', 'plagiarism_dixeo', get_string('pluginname', 'plagiarism_dixeo'));
        $mform->addElement('select', 'use_dixeo', get_string('use_dixeo', 'plagiarism_dixeo'), [
            '0' => get_string('no'),
            '1' => get_string('yes'),
        ]);
        $mform->setDefault('use_dixeo', $use);
        $mform->setType('use_dixeo', PARAM_ALPHANUM);
        $mform->addHelpButton('use_dixeo', 'use_dixeo', 'plagiarism_dixeo');

        $visibilityoptions = [
            'hide' => get_string('visibility_hide', 'plagiarism_dixeo'),
            'score' => get_string('visibility_score', 'plagiarism_dixeo'),
            'scoreandfeedback' => get_string('visibility_score_and_feedback', 'plagiarism_dixeo'),
        ];
        $mform->addElement('select', 'dixeo_show_score_to_student',
            get_string('authorship_score_visibility', 'plagiarism_dixeo'), $visibilityoptions);
        $mform->setDefault('dixeo_show_score_to_student', $scorevis);
        $mform->setType('dixeo_show_score_to_student', PARAM_ALPHA);
        $mform->addHelpButton('dixeo_show_score_to_student', 'authorship_score_visibility', 'plagiarism_dixeo');
        $mform->hideIf('dixeo_show_score_to_student', 'use_dixeo', 'eq', '0');

        $mform->addElement('select', 'dixeo_show_test_results_to_student',
            get_string('verification_test_score_visibility', 'plagiarism_dixeo'), $visibilityoptions);
        $mform->setDefault('dixeo_show_test_results_to_student', $testvis);
        $mform->setType('dixeo_show_test_results_to_student', PARAM_ALPHA);
        $mform->addHelpButton('dixeo_show_test_results_to_student', 'verification_test_score_visibility', 'plagiarism_dixeo');
        $mform->hideIf('dixeo_show_test_results_to_student', 'use_dixeo', 'eq', '0');
    } else {
        // Plugin disabled: add as hidden so they are always in the form and submitted (like Urkund).
        $mform->addElement('hidden', 'use_dixeo', $use);
        $mform->setType('use_dixeo', PARAM_ALPHANUM);
        $mform->addElement('hidden', 'dixeo_show_score_to_student', $scorevis);
        $mform->setType('dixeo_show_score_to_student', PARAM_ALPHA);
        $mform->addElement('hidden', 'dixeo_show_test_results_to_student', $testvis);
        $mform->setType('dixeo_show_test_results_to_student', PARAM_ALPHA);
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

    // Use $data when present; otherwise read from request (form may not have included our elements at definition time).
    $use = $data->use_dixeo ?? optional_param('use_dixeo', isset($existingbykey['use_dixeo']) ? $existingbykey['use_dixeo']->value : '0', PARAM_ALPHANUM);
    $scorevis = $data->dixeo_show_score_to_student ?? optional_param('dixeo_show_score_to_student', isset($existingbykey['show_score_to_student']) ? $existingbykey['show_score_to_student']->value : 'score', PARAM_ALPHA);
    $testvis = $data->dixeo_show_test_results_to_student ?? optional_param('dixeo_show_test_results_to_student', isset($existingbykey['show_test_results_to_student']) ? $existingbykey['show_test_results_to_student']->value : 'score', PARAM_ALPHA);

    $tosave = [
        'use_dixeo' => (string) $use,
        'show_score_to_student' => (string) $scorevis,
        'show_test_results_to_student' => (string) $testvis,
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
    $data->dixeo_show_score_to_student = $tosave['show_score_to_student'];
    $data->dixeo_show_test_results_to_student = $tosave['show_test_results_to_student'];
    return $data;
}
