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
 * Panel rendering tests for plagiarism_dixeo.
 *
 * @package    plagiarism_dixeo
 * @category   test
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo;

use plagiarism_dixeo\authorship\author_storage;
use plagiarism_dixeo\feedback\presubmission_feedback_storage;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/plagiarism/dixeo/lib.php');

/**
 * Panels must only reach people entitled to see them, and never echo model output unescaped.
 *
 * @covers \plagiarism_plugin_dixeo
 */
final class panels_test extends \advanced_testcase {
    /** @var \stdClass Course holding the assignment. */
    private $course;

    /** @var \stdClass Course module of the assignment. */
    private $cm;

    /** @var \stdClass Student who owns the submission. */
    private $student;

    /** @var \stdClass Another student in the same course. */
    private $classmate;

    /** @var \stdClass Teacher on the course. */
    private $teacher;

    /** @var int Submission the analysis is attached to. */
    private $submissionid;

    protected function setUp(): void {
        global $CFG;

        parent::setUp();
        $this->resetAfterTest();

        $CFG->enableplagiarism = 1;
        set_config('assignment_feedback_enabled', 1, 'plagiarism_dixeo');
        set_config('authorship_enabled', 1, 'plagiarism_dixeo');
        set_config('grading_enabled', 1, 'plagiarism_dixeo');

        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        $assign = $generator->create_module('assign', ['course' => $this->course->id]);
        $this->cm = get_coursemodule_from_instance('assign', $assign->id, 0, false, MUST_EXIST);

        $this->student = $generator->create_and_enrol($this->course, 'student');
        $this->classmate = $generator->create_and_enrol($this->course, 'student');
        $this->teacher = $generator->create_and_enrol($this->course, 'editingteacher');

        global $DB;
        $now = time();
        $this->submissionid = (int) $DB->insert_record('assign_submission', (object) [
            'assignment' => $assign->id,
            'userid' => $this->student->id,
            'status' => 'submitted',
            'attemptnumber' => 0,
            'latest' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Store a completed analysis for the student's submission.
     *
     * @param array $extra Fields to merge into the stored record.
     * @return \stdClass
     */
    private function store_record(array $extra = []): \stdClass {
        global $DB;

        $record = author_storage::create_record(
            (int) $this->student->id,
            'mod_assign',
            'submission',
            $this->submissionid,
            ['initial_confidence' => 82.5, 'initial_explanation' => 'Consistent with earlier work.']
        );

        if ($extra) {
            foreach ($extra as $field => $value) {
                $record->$field = $value;
            }
            $DB->update_record(author_storage::TABLE, $record);
        }

        return $record;
    }

    /**
     * Render the panels as a given user.
     *
     * @param \stdClass $viewer User to render as.
     * @param int|null $ownerid Whose submission is being viewed; defaults to the student.
     * @return string
     */
    private function render_as(\stdClass $viewer, ?int $ownerid = null): string {
        // Each call stands in for a separate page request.
        \cache::make_from_params(\cache_store::MODE_REQUEST, 'plagiarism_dixeo', 'activity')->purge();

        $this->setUser($viewer);
        $plugin = new \plagiarism_plugin_dixeo();

        return $plugin->get_links([
            'cmid' => (int) $this->cm->id,
            'userid' => $ownerid ?? (int) $this->student->id,
        ]);
    }

    public function test_student_sees_their_own_final_score(): void {
        $this->store_record();

        $html = $this->render_as($this->student);

        $this->assertStringContainsString('plagiarism-dixeo-panels', $html);
        $this->assertStringContainsString('82.5', $html);
    }

    public function test_a_classmate_sees_nothing(): void {
        $this->store_record();

        $this->assertSame('', $this->render_as($this->classmate));
    }

    public function test_a_teacher_sees_the_students_score(): void {
        $this->store_record();

        $html = $this->render_as($this->teacher);
        $this->assertStringContainsString('82.5', $html);
        $this->assertStringContainsString('Consistent with earlier work.', $html);
    }

    public function test_authorship_panel_renders_when_feedback_is_off(): void {
        $this->store_record();
        set_config('assignment_feedback_enabled', '0', 'plagiarism_dixeo');
        set_config('authorship_enabled', '1', 'plagiarism_dixeo');

        $this->assertStringContainsString('82.5', $this->render_as($this->student));
    }

    public function test_nothing_renders_when_feedback_and_authorship_are_off(): void {
        $this->store_record();
        set_config('assignment_feedback_enabled', '0', 'plagiarism_dixeo');
        set_config('authorship_enabled', '0', 'plagiarism_dixeo');

        $this->assertSame('', $this->render_as($this->student));
        $this->assertSame('', $this->render_as($this->teacher));
    }

    public function test_nothing_renders_when_the_site_setting_is_off(): void {
        $this->store_record();
        set_config('enabled', '0', 'local_dixeo');

        $this->assertSame('', $this->render_as($this->student));
    }

    public function test_hiding_the_final_score_hides_the_panel_from_the_student_only(): void {
        $this->store_record();
        \plagiarism_plugin_dixeo::set_activity_config((int) $this->cm->id, ['show_final_score' => 'hide']);

        $this->assertSame('', $this->render_as($this->student));
        $this->assertStringContainsString('82.5', $this->render_as($this->teacher));
    }

    public function test_draft_feedback_is_shown_to_the_student_and_teacher(): void {
        presubmission_feedback_storage::create_record(
            (int) $this->student->id,
            'mod_assign',
            'submission',
            $this->submissionid,
            '<p>Tighten the conclusion.</p>'
        );

        $this->assertStringContainsString('Tighten the conclusion.', $this->render_as($this->student));
        $this->assertStringContainsString('Tighten the conclusion.', $this->render_as($this->teacher));
        $this->assertSame('', $this->render_as($this->classmate));
    }

    public function test_the_explanation_is_withheld_unless_feedback_is_enabled(): void {
        $this->store_record();

        $this->assertStringNotContainsString('Consistent with earlier work', $this->render_as($this->student));

        \plagiarism_plugin_dixeo::set_activity_config((int) $this->cm->id, [
            'show_final_score' => 'scoreandfeedback',
        ]);
        $this->assertStringContainsString('Consistent with earlier work', $this->render_as($this->student));
    }

    public function test_markup_in_a_model_explanation_is_escaped(): void {
        $this->store_record(['final_explanation' => '<script>alert(1)</script><b>bold</b>']);
        \plagiarism_plugin_dixeo::set_activity_config((int) $this->cm->id, [
            'show_final_score' => 'scoreandfeedback',
        ]);

        $html = $this->render_as($this->teacher);

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<b>bold</b>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function test_markup_in_quiz_question_feedback_is_escaped(): void {
        $this->store_record([
            'quiz_grade' => 75.0,
            'quiz_explanation' => 'Answered most questions well.',
            'quiz_question_feedbacks' => json_encode(['<img src=x onerror=alert(1)>', 'Clear answer.']),
        ]);

        $html = $this->render_as($this->teacher);

        $this->assertStringNotContainsString('<img', $html);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $html);
        $this->assertStringContainsString('Clear answer.', $html);
        $this->assertStringContainsString('Answered most questions well.', $html);
    }

    public function test_a_pending_record_shows_a_status_badge_without_a_score(): void {
        author_storage::create_record(
            (int) $this->student->id,
            'mod_assign',
            'submission',
            $this->submissionid
        );

        $html = $this->render_as($this->student);

        $this->assertStringContainsString(get_string('status_pending_badge', 'plagiarism_dixeo'), $html);
    }

    public function test_only_the_student_gets_the_link_to_sit_the_verification_quiz(): void {
        $this->store_record([
            'status' => author_storage::STATUS_VERIFICATION_REQUIRED,
            'quiz_data' => json_encode(['questions' => [['id' => 'q1']]]),
            'final_confidence' => null,
        ]);

        $this->assertStringContainsString(
            'plagiarism-dixeo-open-verification-modal',
            $this->render_as($this->student)
        );
        $this->assertStringNotContainsString(
            'plagiarism-dixeo-open-verification-modal',
            $this->render_as($this->teacher)
        );
    }

    public function test_panels_render_once_per_submission_in_a_request(): void {
        $this->store_record();
        $this->setUser($this->student);
        $plugin = new \plagiarism_plugin_dixeo();
        $linkarray = ['cmid' => (int) $this->cm->id, 'userid' => (int) $this->student->id];

        $this->assertNotSame('', $plugin->get_links($linkarray));
        $this->assertSame('', $plugin->get_links($linkarray));
    }

    public function test_disclosure_is_shown_only_when_configured(): void {
        $plugin = new \plagiarism_plugin_dixeo();
        $this->setUser($this->student);

        $this->assertSame('', $plugin->print_disclosure((int) $this->cm->id));

        set_config('student_disclosure', 'Your work will be analysed.', 'plagiarism_dixeo');
        $this->assertStringContainsString(
            'Your work will be analysed.',
            $plugin->print_disclosure((int) $this->cm->id)
        );

        set_config('assignment_feedback_enabled', '0', 'plagiarism_dixeo');
        $this->assertSame('', $plugin->print_disclosure((int) $this->cm->id));
    }
}
