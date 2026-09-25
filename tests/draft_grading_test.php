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
 * Tests for teacher-confirmed draft grading.
 *
 * @package    plagiarism_dixeo
 * @category   test
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo;

use plagiarism_dixeo\automation\analysis_gate;
use plagiarism_dixeo\external\get_grade_draft;
use plagiarism_dixeo\feedback\grade_feedback_storage;
use plagiarism_dixeo\task\stage_draft_grade;

/**
 * A proposed grade is held for a teacher and never written to the gradebook by itself.
 *
 * @covers \plagiarism_dixeo\external\get_grade_draft
 * @covers \plagiarism_dixeo\feedback\grade_feedback_storage
 * @covers \plagiarism_dixeo\observer::submission_graded
 * @covers \plagiarism_dixeo\task\stage_draft_grade
 */
final class draft_grading_test extends \advanced_testcase {
    /** @var \stdClass Course holding the assignment under test. */
    private $course;

    /** @var \stdClass Assignment used by the tests. */
    private $assign;

    /** @var \stdClass Student being graded. */
    private $student;

    /** @var \stdClass Teacher doing the grading. */
    private $teacher;

    /** @var int Id of the student's submission. */
    private $submissionid;

    public function setUp(): void {
        global $CFG, $DB;

        parent::setUp();
        $this->resetAfterTest(true);

        $CFG->enableplagiarism = 1;
        set_config('enabled', 1, 'plagiarism_dixeo');
        set_config('enable_mod_assign', 1, 'plagiarism_dixeo');
        set_config('assign_trigger', analysis_gate::TRIGGER_SUBMIT, 'plagiarism_dixeo');

        $this->course = $this->getDataGenerator()->create_course();
        $this->assign = $this->getDataGenerator()->create_module('assign', ['course' => $this->course->id]);
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');

        $now = time();
        $this->submissionid = (int) $DB->insert_record('assign_submission', (object) [
            'assignment' => $this->assign->id,
            'userid' => $this->student->id,
            'timecreated' => $now,
            'timemodified' => $now,
            'status' => 'submitted',
            'groupid' => 0,
            'attemptnumber' => 0,
            'latest' => 1,
        ]);

        \plagiarism_plugin_dixeo::set_activity_config((int) $this->assign->cmid, [
            'use_dixeo' => '1',
            'enable_auto_grading' => '1',
        ]);
    }

    /**
     * Store a draft grade as the task would.
     *
     * @param array $result Grading result to store.
     * @return \stdClass
     */
    private function store_draft(array $result = []): \stdClass {
        return grade_feedback_storage::create_record(
            (int) $this->student->id,
            'mod_assign',
            'submission',
            $this->submissionid,
            $result + [
                'gradingmethod' => grade_feedback_storage::METHOD_SIMPLE,
                'analysis' => 'The argument is well structured.',
                'feedback' => '<p>Good work.</p>',
                'grade' => 72.0,
            ]
        );
    }

    /**
     * Fire the event a student submitting for grading would.
     */
    private function fire_submitted(): void {
        \mod_assign\event\assessable_submitted::create([
            'context' => \context_module::instance((int) $this->assign->cmid),
            'courseid' => (int) $this->course->id,
            'userid' => (int) $this->student->id,
            'objectid' => $this->submissionid,
            'other' => ['submission_editable' => false],
        ])->trigger();
    }

    public function test_submitting_queues_a_draft_grade_when_the_teacher_asked_for_one(): void {
        global $DB;

        $this->fire_submitted();

        $this->assertSame(1, $DB->count_records('task_adhoc', ['classname' => '\\' . stage_draft_grade::class]));
    }

    public function test_no_draft_is_prepared_unless_the_activity_opted_in(): void {
        global $DB;

        \plagiarism_plugin_dixeo::set_activity_config((int) $this->assign->cmid, [
            'use_dixeo' => '1',
            'enable_auto_grading' => '0',
        ]);

        $this->fire_submitted();

        $this->assertSame(0, $DB->count_records('task_adhoc', ['classname' => '\\' . stage_draft_grade::class]));
    }

    public function test_a_stored_draft_never_reaches_the_gradebook(): void {
        global $DB;

        $this->store_draft();

        $this->assertFalse($DB->record_exists('assign_grades', ['assignment' => $this->assign->id]));
        $grades = grade_get_grades(
            (int) $this->course->id,
            'mod',
            'assign',
            (int) $this->assign->id,
            [(int) $this->student->id]
        );
        $this->assertNull($grades->items[0]->grades[(int) $this->student->id]->grade ?? null);
    }

    public function test_the_task_does_nothing_once_draft_grading_is_switched_off(): void {
        \plagiarism_plugin_dixeo::set_activity_config((int) $this->assign->cmid, [
            'use_dixeo' => '1',
            'enable_auto_grading' => '0',
        ]);

        $task = new stage_draft_grade();
        $task->set_custom_data([
            'cmid' => (int) $this->assign->cmid,
            'courseid' => (int) $this->course->id,
            'userid' => (int) $this->student->id,
            'submissionid' => $this->submissionid,
        ]);

        $sink = $this->redirectEvents();
        ob_start();
        $task->execute();
        ob_end_clean();

        $this->assertSame([], $sink->get_events());
        $this->assertNull(grade_feedback_storage::get_by_context(
            (int) $this->student->id,
            'mod_assign',
            'submission',
            $this->submissionid
        ));
    }

    public function test_a_teacher_sees_the_draft(): void {
        $this->store_draft();
        $this->setUser($this->teacher);

        $result = get_grade_draft::execute((int) $this->assign->cmid, (int) $this->student->id);

        $this->assertTrue($result['hasdraft']);
        $this->assertSame(grade_feedback_storage::METHOD_SIMPLE, $result['gradingmethod']);
        $this->assertEqualsWithDelta(72.0, $result['grade'], 0.001);
        $this->assertStringContainsString('Good work.', $result['feedback']);
    }

    public function test_markup_in_a_draft_is_purified_before_it_reaches_the_grader(): void {
        $this->store_draft([
            'analysis' => '<p>Solid<script>alert(1)</script></p>',
            'feedback' => '<p onclick="alert(2)">Well argued.</p>',
        ]);
        $this->setUser($this->teacher);

        $result = get_grade_draft::execute((int) $this->assign->cmid, (int) $this->student->id);

        $this->assertStringNotContainsString('<script', $result['analysis']);
        $this->assertStringNotContainsString('alert(1)', $result['analysis']);
        $this->assertStringContainsString('Solid', $result['analysis']);
        $this->assertStringNotContainsString('onclick', $result['feedback']);
        $this->assertStringContainsString('Well argued.', $result['feedback']);
    }

    public function test_a_teacher_sees_nothing_when_no_draft_was_prepared(): void {
        $this->setUser($this->teacher);

        $result = get_grade_draft::execute((int) $this->assign->cmid, (int) $this->student->id);

        $this->assertFalse($result['hasdraft']);
        $this->assertSame('', $result['feedback']);
        $this->assertNull($result['grade']);
    }

    public function test_a_student_cannot_read_their_own_draft_grade(): void {
        $this->store_draft();
        $this->setUser($this->student);

        $this->expectException(\required_capability_exception::class);
        get_grade_draft::execute((int) $this->assign->cmid, (int) $this->student->id);
    }

    public function test_a_teacher_from_another_course_cannot_read_the_draft(): void {
        $this->store_draft();
        $othercourse = $this->getDataGenerator()->create_course();
        $outsider = $this->getDataGenerator()->create_and_enrol($othercourse, 'editingteacher');
        $this->setUser($outsider);

        $this->expectException(\moodle_exception::class);
        get_grade_draft::execute((int) $this->assign->cmid, (int) $this->student->id);
    }

    public function test_a_draft_stops_being_offered_once_the_teacher_has_graded(): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $this->store_draft();
        $this->setUser($this->teacher);

        $cm = get_coursemodule_from_id('assign', (int) $this->assign->cmid, 0, false, MUST_EXIST);
        $assign = new \assign(\context_module::instance((int) $this->assign->cmid), $cm, $this->course);
        $now = time();
        $gradeid = $DB->insert_record('assign_grades', (object) [
            'assignment' => (int) $this->assign->id,
            'userid' => (int) $this->student->id,
            'timecreated' => $now,
            'timemodified' => $now,
            'grader' => (int) $this->teacher->id,
            'grade' => 65.0,
            'attemptnumber' => 0,
        ]);
        $grade = $DB->get_record('assign_grades', ['id' => $gradeid], '*', MUST_EXIST);

        \mod_assign\event\submission_graded::create_from_grade($assign, $grade)->trigger();

        $stored = grade_feedback_storage::get_by_context(
            (int) $this->student->id,
            'mod_assign',
            'submission',
            $this->submissionid
        );
        $this->assertSame(grade_feedback_storage::STATUS_APPLIED, $stored->status);

        $result = get_grade_draft::execute((int) $this->assign->cmid, (int) $this->student->id);
        $this->assertFalse($result['hasdraft']);
    }

    public function test_applying_a_draft_is_recorded_in_the_log(): void {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $draft = $this->store_draft();
        $this->setUser($this->teacher);

        $cm = get_coursemodule_from_id('assign', (int) $this->assign->cmid, 0, false, MUST_EXIST);
        $assign = new \assign(\context_module::instance((int) $this->assign->cmid), $cm, $this->course);
        $now = time();
        $gradeid = $DB->insert_record('assign_grades', (object) [
            'assignment' => (int) $this->assign->id,
            'userid' => (int) $this->student->id,
            'timecreated' => $now,
            'timemodified' => $now,
            'grader' => (int) $this->teacher->id,
            'grade' => 65.0,
            'attemptnumber' => 0,
        ]);
        $grade = $DB->get_record('assign_grades', ['id' => $gradeid], '*', MUST_EXIST);
        $graded = \mod_assign\event\submission_graded::create_from_grade($assign, $grade);

        // Events are captured rather than dispatched here, so the observer is invoked directly.
        $sink = $this->redirectEvents();
        observer::submission_graded($graded);
        $events = $sink->get_events();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(\plagiarism_dixeo\event\grade_draft_applied::class, $events[0]);
        $this->assertSame((int) $draft->id, (int) $events[0]->objectid);
        $this->assertSame((int) $this->student->id, (int) $events[0]->relateduserid);
    }
}
