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
     * Request-scoped store for activity settings and render state.
     *
     * @return \cache_application|\cache_session|\cache_store
     */
    private static function get_request_cache() {
        return \cache::make_from_params(\cache_store::MODE_REQUEST, 'plagiarism_dixeo', 'activity');
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

        $cache = self::get_request_cache();
        $cachekey = 'config_' . $cmid;
        $config = $cache->get($cachekey);
        if (is_array($config)) {
            return $config;
        }

        $stored = $DB->get_records_menu(self::CONFIG_TABLE, ['cm' => $cmid], '', 'name, value');

        $config = [];
        foreach (self::get_activity_settings() as $name => $spec) {
            $value = isset($stored[$name]) ? (string) $stored[$name] : $spec['default'];
            $config[$name] = in_array($value, $spec['values'], true) ? $value : $spec['default'];
        }
        $cache->set($cachekey, $config);

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

        self::get_request_cache()->delete('config_' . $cmid);
    }

    /**
     * Panels shown beside a submission in the activity and grader views.
     *
     * Returns nothing unless the viewer is the student the submission belongs to or holds
     * mod/assign:grade on the activity: a score is a judgement about a named person.
     *
     * @param array $linkarray Core supplies at least cmid and userid.
     * @return string HTML fragment, or an empty string when there is nothing to show.
     */
    public function get_links($linkarray) {
        global $PAGE, $USER;

        $cmid = (int) ($linkarray['cmid'] ?? 0);
        $userid = (int) ($linkarray['userid'] ?? 0);
        if ($cmid <= 0 || $userid <= 0 || !self::is_enabled_for_activity($cmid)) {
            return '';
        }

        $cm = get_coursemodule_from_id('assign', $cmid, 0, false, IGNORE_MISSING);
        if (!$cm || !self::enabled_for_course((int) $cm->course)) {
            return '';
        }
        $context = context_module::instance($cm->id);

        $isteacher = has_capability('mod/assign:grade', $context);
        if ($userid !== (int) $USER->id && !$isteacher) {
            return '';
        }
        if ($userid === (int) $USER->id && !$isteacher && !is_enrolled($context, $userid)) {
            return '';
        }

        $itemid = (int) ($linkarray['submissionid'] ?? 0);
        if ($itemid <= 0) {
            $itemid = self::get_latest_submission_id((int) $cm->instance, $userid);
        }
        if ($itemid <= 0) {
            return '';
        }

        // A submission with several files calls this once per file; show the panels once.
        $rendered = self::get_request_cache();
        $renderkey = 'rendered_' . $cmid . '_' . $userid;
        if ($rendered->get($renderkey)) {
            return '';
        }

        $record = \plagiarism_dixeo\authorship\author_storage::get_record_by_context(
            $userid,
            'mod_assign',
            'submission',
            $itemid
        );

        $html = self::format_presubmission_feedback($userid, $itemid, $context);
        if ($record !== null) {
            $html .= self::get_links_badges_html($record, $context, self::get_activity_config($cmid), $isteacher);
        }
        if ($html === '') {
            return '';
        }

        $rendered->set($renderkey, true);
        $PAGE->requires->js_call_amd('plagiarism_dixeo/panel', 'init', []);

        return $html;
    }

    /**
     * Notify students that their submission will be analysed.
     *
     * @param int $cmid Course module id.
     * @return string HTML fragment, or an empty string when nothing is configured.
     */
    public function print_disclosure($cmid) {
        $cm = get_coursemodule_from_id('', (int) $cmid, 0, false, IGNORE_MISSING);
        if ($cm && !self::enabled_for_course((int) $cm->course)) {
            return '';
        }
        if (!self::is_enabled_for_activity((int) $cmid)) {
            return '';
        }

        $disclosure = (string) get_config('plagiarism_dixeo', 'student_disclosure');
        if (trim($disclosure) === '') {
            return '';
        }

        $context = context_module::instance((int) $cmid);

        return html_writer::div(
            format_text($disclosure, FORMAT_MOODLE, ['context' => $context]),
            'plagiarism-dixeo-disclosure'
        );
    }

    /**
     * Render the panels describing one authorship record.
     *
     * @param stdClass $record Authorship record.
     * @param context_module $context Activity the submission belongs to.
     * @param array $config Activity settings.
     * @param bool $isteacher Whether the viewer may grade, and so sees everything.
     * @return string HTML fragment, or an empty string when every panel is hidden.
     */
    protected static function get_links_badges_html(
        stdClass $record,
        context_module $context,
        array $config,
        bool $isteacher
    ): string {
        $storage = \plagiarism_dixeo\authorship\author_storage::class;
        $cmid = (int) $context->instanceid;

        $waiting = [
            $storage::STATUS_PENDING => ['status_pending_badge', 'bg-warning text-dark', false],
            $storage::STATUS_FAILED => ['status_failed_badge', 'bg-danger text-white', false],
            $storage::STATUS_VERIFICATION_REQUIRED => ['verification_required_badge', 'bg-warning text-dark', true],
            $storage::STATUS_VERIFICATION_IN_PROGRESS => ['verification_in_progress_badge', 'bg-info text-white', true],
        ];

        if (isset($waiting[$record->status])) {
            [$stringkey, $headerclass, $islink] = $waiting[$record->status];
            $panel = self::build_panel_data(get_string($stringkey, 'plagiarism_dixeo'), $headerclass);
            // Only the student sitting the test can open it.
            if ($islink && !$isteacher) {
                $panel['haslink'] = true;
                $panel['recordid'] = (int) $record->id;
                $panel['cmid'] = $cmid;
            }

            return self::render_panels($cmid, [$panel]);
        }

        if ($record->status !== $storage::STATUS_COMPLETE) {
            return '';
        }

        // Teachers see every score together with the reasoning behind it.
        $visibility = $isteacher
            ? array_fill_keys(['initial', 'test', 'final'], self::VISIBILITY_SCOREANDFEEDBACK)
            : [
                'initial' => $config['show_initial_score'],
                'test' => $config['show_test_score'],
                'final' => $config['show_final_score'],
            ];

        $hasquiz = $record->quiz_grade !== null;
        $panels = [];

        if ($hasquiz && $visibility['initial'] !== self::VISIBILITY_HIDE && $record->initial_confidence !== null) {
            $panels[] = self::build_score_panel(
                'display_initial_score',
                (float) $record->initial_confidence,
                'bg-info text-white',
                $visibility['initial'] === self::VISIBILITY_SCOREANDFEEDBACK
                    ? self::format_explanation($record->initial_explanation, $context)
                    : ''
            );
        }

        if ($hasquiz && $visibility['test'] !== self::VISIBILITY_HIDE) {
            $panels[] = self::build_score_panel(
                'display_test_score',
                (float) $record->quiz_grade,
                'bg-primary text-white',
                $visibility['test'] === self::VISIBILITY_SCOREANDFEEDBACK
                    ? self::format_quiz_feedback($record, $context)
                    : ''
            );
        }

        if ($visibility['final'] !== self::VISIBILITY_HIDE && $record->final_confidence !== null) {
            $panels[] = self::build_score_panel(
                'display_final_score',
                (float) $record->final_confidence,
                'bg-success text-white',
                $visibility['final'] === self::VISIBILITY_SCOREANDFEEDBACK
                    ? self::format_explanation($record->final_explanation, $context)
                    : ''
            );
        }

        if (!$panels) {
            return '';
        }

        // Panels with feedback start open so the explanation is visible; they can still be collapsed.
        if (count($panels) > 1) {
            foreach ($panels as $index => $panel) {
                if ($panel['hasbody']) {
                    $panels[$index]['collapsible'] = true;
                    $panels[$index]['collapseid'] = 'plagiarism-dixeo-collapse-' . (int) $record->id . '-' . $index;
                }
            }
        }

        return self::render_panels($cmid, $panels);
    }

    /**
     * Render a set of panels through the template.
     *
     * @param int $cmid Course module id, used to make the container id unique on the page.
     * @param array $panels Panel data from build_panel_data().
     * @return string HTML fragment.
     */
    protected static function render_panels(int $cmid, array $panels): string {
        global $OUTPUT;

        return $OUTPUT->render_from_template('plagiarism_dixeo/panels', [
            'containerid' => 'plagiarism-dixeo-panels-' . $cmid,
            'panels' => array_values($panels),
        ]);
    }

    /**
     * Build a panel showing one score.
     *
     * @param string $stringkey Language string naming the score, taking the value as {$a}.
     * @param float $score Score to show, 0–100.
     * @param string $headerclass Bootstrap classes for the header.
     * @param string $body Body HTML, already passed through format_text(); empty for no body.
     * @return array Panel data.
     */
    protected static function build_score_panel(
        string $stringkey,
        float $score,
        string $headerclass,
        string $body
    ): array {
        return self::build_panel_data(
            get_string($stringkey, 'plagiarism_dixeo', round($score, 1)),
            $headerclass,
            $body
        );
    }

    /**
     * Build the template data for one panel.
     *
     * The title is plain text and is escaped by the template. The body is the only field
     * rendered as HTML, so callers must pass it through format_text() first.
     *
     * @param string $title Header text.
     * @param string $headerclass Bootstrap classes for the header.
     * @param string $body Body HTML, already passed through format_text(); empty for no body.
     * @return array Panel data.
     */
    protected static function build_panel_data(string $title, string $headerclass, string $body = ''): array {
        $hasbody = trim($body) !== '';

        return [
            'title' => $title,
            'headerclass' => $headerclass,
            'haslink' => false,
            'recordid' => 0,
            'cmid' => 0,
            'hasbody' => $hasbody,
            'body' => $body,
            'collapsible' => false,
            'collapseid' => '',
            'maximizelabel' => $hasbody ? get_string('panel_maximize', 'plagiarism_dixeo') : '',
        ];
    }

    /**
     * Prepare a model-written explanation for display.
     *
     * Explanations are prose, so they are rendered as plain text: this escapes any markup
     * the model produced rather than trusting it.
     *
     * @param string|null $explanation Stored explanation.
     * @param context_module $context Activity the text belongs to.
     * @return string Safe HTML, or an empty string when there is nothing to show.
     */
    protected static function format_explanation(?string $explanation, context_module $context): string {
        $explanation = trim((string) $explanation);
        if ($explanation === '') {
            return '';
        }

        return format_text($explanation, FORMAT_PLAIN, ['context' => $context]);
    }

    /**
     * Draft coaching feedback stored from Analyze, shown beside the submission.
     *
     * @param int $userid Student the feedback belongs to.
     * @param int $itemid Assign submission id.
     * @param context_module $context Activity context.
     * @return string HTML fragment, or an empty string when nothing is stored.
     */
    protected static function format_presubmission_feedback(int $userid, int $itemid, context_module $context): string {
        $record = \plagiarism_dixeo\feedback\presubmission_feedback_storage::get_by_context(
            $userid,
            'mod_assign',
            'submission',
            $itemid
        );
        $feedback = trim((string) ($record->feedback ?? ''));
        if ($feedback === '') {
            return '';
        }

        $clean = format_text($feedback, FORMAT_HTML, [
            'para' => true,
            'filter' => false,
            'context' => $context,
        ]);
        if (trim($clean) === '') {
            return '';
        }

        return html_writer::div($clean, 'alert alert-info alert-block plagiarism-dixeo-review-feedback mt-2', [
            'role' => 'alert',
        ]);
    }

    /**
     * Prepare the verification quiz conclusion and per-question notes for display.
     *
     * @param stdClass $record Authorship record.
     * @param context_module $context Activity the text belongs to.
     * @return string Safe HTML, or an empty string when there is nothing to show.
     */
    protected static function format_quiz_feedback(stdClass $record, context_module $context): string {
        $parts = [];

        $conclusion = self::format_explanation($record->quiz_explanation ?? null, $context);
        if ($conclusion !== '') {
            $parts[] = html_writer::div($conclusion, 'plagiarism-dixeo-quiz-conclusion');
        }

        $feedbacks = json_decode((string) ($record->quiz_question_feedbacks ?? ''), true);
        if (is_array($feedbacks)) {
            $items = [];
            foreach ($feedbacks as $feedback) {
                if (!is_scalar($feedback)) {
                    continue;
                }
                $line = self::format_explanation((string) $feedback, $context);
                if ($line !== '') {
                    $items[] = html_writer::tag('li', $line);
                }
            }
            if ($items) {
                $parts[] = html_writer::tag('ul', implode('', $items), [
                    'class' => 'plagiarism-dixeo-quiz-feedback-list',
                ]);
            }
        }

        return implode('', $parts);
    }

    /**
     * Find the submission a student's analysis is attached to.
     *
     * @param int $assignid Assignment instance id.
     * @param int $userid Student user id.
     * @return int Submission id, or 0 when the student has not submitted.
     */
    protected static function get_latest_submission_id(int $assignid, int $userid): int {
        global $DB;

        $submission = $DB->get_record(
            'assign_submission',
            ['assignment' => $assignid, 'userid' => $userid, 'latest' => 1],
            'id',
            IGNORE_MULTIPLE
        );

        return $submission ? (int) $submission->id : 0;
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

    /**
     * Whether Dixeo features run in the course an activity belongs to.
     *
     * A course id outside a course follows the Dixeo layer.
     *
     * @param int $courseid
     * @return bool
     */
    public static function enabled_for_course(int $courseid): bool {
        $context = $courseid > SITEID
            ? \context_course::instance($courseid)
            : \context_system::instance();
        return \local_dixeo\external\service_factory::get_settings_config()->enabled_for($context);
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
    // Plagiarism settings are a Dixeo feature, so the form adds none while Dixeo is off.
    $course = $formwrapper->get_course();
    if (!plagiarism_plugin_dixeo::enabled_for_course((int) ($course->id ?? 0))) {
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
    $course = $formwrapper->get_course();
    if (!plagiarism_plugin_dixeo::enabled_for_course((int) ($course->id ?? 0))) {
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
