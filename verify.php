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
 * Authorship verification quiz page. Renders the quiz and on submit calls backend then updates record.
 *
 * @package    plagiarism_dixeo
 * @author     Josemaria Bolanos <admin@mako.digital>
 * @copyright  2026 Dixeo.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once(__DIR__ . '/classes/local/author_storage.php');

$recordid = required_param('recordid', PARAM_INT);
$cmid = required_param('cmid', PARAM_INT);

$cm = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$context = context_module::instance($cm->id);

require_login($course, true, $cm);
require_capability('mod/assign:submit', $context);

$record = \plagiarism_dixeo\local\author_storage::get_record($recordid);
if (!$record || (int) $record->userid !== (int) $USER->id) {
    throw new \moodle_exception('invalidrecord', 'plagiarism_dixeo');
}
if ($record->status !== \plagiarism_dixeo\local\author_storage::STATUS_VERIFICATION_REQUIRED) {
    redirect(new moodle_url('/mod/assign/view.php', ['id' => $cmid]));
}

$url = new moodle_url('/plagiarism/dixeo/verify.php', ['recordid' => $recordid, 'cmid' => $cmid]);
$PAGE->set_url($url);
$PAGE->set_title(get_string('verification_required', 'plagiarism_dixeo'));
$PAGE->set_heading($course->fullname);

$isformsubmit = data_submitted() && optional_param('authorship_record_id', 0, PARAM_INT) === $recordid;
if ($isformsubmit) {
    require_sesskey();
    $raw = optional_param_array('authorship_q', [], PARAM_RAW);
    $responses = [];
    foreach ($raw as $qid => $val) {
        $responses[$qid] = $val;
    }
    $quizdata = $record->quiz_data;
    $initial = $record->initial_confidence !== null ? (float) $record->initial_confidence : null;
    $result = \plagiarism_dixeo\local\authorship::evaluate_verify($quizdata, $responses, $initial);
    \plagiarism_dixeo\local\author_storage::update_record($recordid, [
        'quiz_responses' => $responses,
        'final_confidence' => $result['final_confidence'],
        'quiz_grade' => $result['quiz_grade'],
        'quiz_explanation' => $result['quiz_explanation'] ?? '',
        'final_explanation' => $result['final_explanation'] ?? '',
    ]);
    redirect(new moodle_url('/mod/assign/view.php', ['id' => $cmid]),
        get_string('verification_complete', 'plagiarism_dixeo'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$quizhtml = \plagiarism_dixeo\local\authorship::render_quiz_form($record, [
    'form_action_url' => $url->out(false),
    'record_id'       => $record->id,
    'submit_label'    => get_string('submit_verification', 'plagiarism_dixeo'),
]);

// Inject sesskey into the form for CSRF protection (form is rendered without action URL context).
$sesskeyinput = '<input type="hidden" name="sesskey" value="' . sesskey() . '" />';
$quizhtml = str_replace(
    '<input type="hidden" name="authorship_record_id"',
    $sesskeyinput . "\n" . '<input type="hidden" name="authorship_record_id"',
    $quizhtml
);

echo $OUTPUT->header();
echo html_writer::tag('p', get_string('verification_required', 'plagiarism_dixeo'));
echo $quizhtml;
echo $OUTPUT->footer();
