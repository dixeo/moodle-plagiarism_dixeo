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
 * Tests for assign UX external access helpers.
 *
 * @package    plagiarism_dixeo
 * @category   test
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo;

use local_dixeo\external\service_factory;
use local_dixeo\service\assign_ux_service;
use plagiarism_dixeo\external\assign_access;
use plagiarism_dixeo\external\get_grade_draft;
use plagiarism_dixeo\external\mod_assign_grade_submission;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/plagiarism/dixeo/lib.php');

/**
 * Capability / context checks for assign UX externals.
 *
 * @covers \plagiarism_dixeo\external\assign_access
 */
final class assign_access_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    protected function tearDown(): void {
        service_factory::reset();
        parent::tearDown();
    }

    /**
     * A separate-groups assignment, a non-editing teacher in group A and a student in group B.
     *
     * @return array{course: \stdClass, assign: \stdClass, teacher: \stdClass, peer: \stdClass, student: \stdClass}
     */
    private function separate_groups(): array {
        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $assign = $generator->create_module('assign', ['course' => $course->id, 'groupmode' => SEPARATEGROUPS]);
        $this->enable_dixeo((int) $assign->cmid);
        $teacher = $generator->create_and_enrol($course, 'teacher');
        $peer = $generator->create_and_enrol($course, 'student');
        $student = $generator->create_and_enrol($course, 'student');
        $groupa = $generator->create_group(['courseid' => $course->id]);
        $groupb = $generator->create_group(['courseid' => $course->id]);
        $generator->create_group_member(['groupid' => $groupa->id, 'userid' => $teacher->id]);
        $generator->create_group_member(['groupid' => $groupa->id, 'userid' => $peer->id]);
        $generator->create_group_member(['groupid' => $groupb->id, 'userid' => $student->id]);
        $mock = $this->createMock(assign_ux_service::class);
        $mock->expects($this->never())->method('submit_grade');
        service_factory::set_test_assign_ux_service($mock);

        return ['course' => $course, 'assign' => $assign, 'teacher' => $teacher, 'peer' => $peer, 'student' => $student];
    }

    /**
     * Turn Dixeo on for an assignment at site and activity level.
     *
     * @param int $cmid Course module id.
     */
    private function enable_dixeo(int $cmid): void {
        global $CFG;

        $CFG->enableplagiarism = 1;
        set_config('assignment_feedback_enabled', 1, 'plagiarism_dixeo');
        set_config('grading_enabled', 1, 'plagiarism_dixeo');
    }

    public function test_student_can_analyze_own_module(): void {
        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $this->enable_dixeo((int) $assign->cmid);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $context = assign_access::require_student_analyze((int) $assign->cmid);
        $this->assertInstanceOf(\context_module::class, $context);
        $this->assertSame((int) $assign->cmid, (int) $context->instanceid);
    }

    public function test_student_cannot_grade(): void {
        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        assign_access::require_teacher_grade((int) $assign->cmid);
    }

    public function test_teacher_can_grade(): void {
        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $this->enable_dixeo((int) $assign->cmid);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $context = assign_access::require_teacher_grade((int) $assign->cmid);
        $this->assertInstanceOf(\context_module::class, $context);
    }

    public function test_student_can_verify_when_feedback_is_off(): void {
        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $this->enable_dixeo((int) $assign->cmid);
        set_config('assignment_feedback_enabled', '0', 'plagiarism_dixeo');
        set_config('authorship_enabled', '1', 'plagiarism_dixeo');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $context = assign_access::require_student_authorship((int) $assign->cmid);
        $this->assertSame((int) $assign->cmid, (int) $context->instanceid);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('activity_not_enabled', 'plagiarism_dixeo'));
        assign_access::require_student_analyze((int) $assign->cmid);
    }

    public function test_student_cannot_analyze_when_the_activity_is_off(): void {
        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $this->enable_dixeo((int) $assign->cmid);
        set_config('assignment_feedback_enabled', '0', 'plagiarism_dixeo');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('activity_not_enabled', 'plagiarism_dixeo'));
        assign_access::require_student_analyze((int) $assign->cmid);
    }

    public function test_teacher_can_grade_when_feedback_is_off(): void {
        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $this->enable_dixeo((int) $assign->cmid);
        set_config('assignment_feedback_enabled', '0', 'plagiarism_dixeo');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $context = assign_access::require_teacher_grade((int) $assign->cmid);
        $this->assertInstanceOf(\context_module::class, $context);
    }

    public function test_teacher_cannot_grade_when_grading_is_off(): void {
        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $this->enable_dixeo((int) $assign->cmid);
        set_config('grading_enabled', '0', 'plagiarism_dixeo');
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('grading_not_enabled', 'plagiarism_dixeo'));
        assign_access::require_teacher_grade((int) $assign->cmid);
    }

    public function test_teacher_cannot_grade_while_the_course_is_off(): void {
        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $this->enable_dixeo((int) $assign->cmid);
        $this->setAdminUser();
        \local_dixeo\external\service_factory::get_settings_config()->set(
            'local_dixeo',
            'enabled',
            \context_course::instance($course->id),
            '0'
        );
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('grading_not_enabled', 'plagiarism_dixeo'));
        assign_access::require_teacher_grade((int) $assign->cmid);
    }

    public function test_teacher_can_grade_when_auto_grading_is_off(): void {
        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $this->enable_dixeo((int) $assign->cmid);
        \local_dixeo\external\service_factory::get_settings_config()->set(
            'plagiarism_dixeo',
            'enable_auto_grading',
            \context_module::instance((int) $assign->cmid),
            '0',
            false
        );
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $context = assign_access::require_teacher_grade((int) $assign->cmid);
        $this->assertInstanceOf(\context_module::class, $context);
    }

    public function test_unenrolled_user_cannot_analyze(): void {
        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $outsider = $this->getDataGenerator()->create_user();
        $this->setUser($outsider);

        $this->expectException(\require_login_exception::class);
        assign_access::require_student_analyze((int) $assign->cmid);
    }

    public function test_require_from_authorship_resolves_module(): void {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $this->enable_dixeo((int) $assign->cmid);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $now = time();
        $submissionid = $DB->insert_record('assign_submission', (object) [
            'assignment' => $assign->id,
            'userid' => $student->id,
            'timecreated' => $now,
            'timemodified' => $now,
            'status' => 'draft',
            'groupid' => 0,
            'attemptnumber' => 0,
            'latest' => 1,
        ]);

        $record = (object) [
            'component' => 'mod_assign',
            'area' => 'submission',
            'itemid' => $submissionid,
            'userid' => $student->id,
        ];

        $context = assign_access::require_student_analyze_from_authorship($record);
        $this->assertSame((int) $assign->cmid, (int) $context->instanceid);
    }

    public function test_teacher_may_grade_a_student_of_their_own_group(): void {
        $seed = $this->separate_groups();
        $this->setUser($seed['teacher']);

        $context = assign_access::require_teacher_grade_for_user((int) $seed['assign']->cmid, (int) $seed['peer']->id);

        $this->assertSame((int) $seed['assign']->cmid, (int) $context->instanceid);
    }

    public function test_grade_assist_refuses_a_student_of_another_separate_group(): void {
        $seed = $this->separate_groups();
        $this->setUser($seed['teacher']);

        $this->expectException(\required_capability_exception::class);
        mod_assign_grade_submission::execute((int) $seed['assign']->cmid, (int) $seed['student']->id);
    }

    public function test_grade_draft_refuses_a_student_of_another_separate_group(): void {
        $seed = $this->separate_groups();
        $this->setUser($seed['teacher']);

        $this->expectException(\required_capability_exception::class);
        get_grade_draft::execute((int) $seed['assign']->cmid, (int) $seed['student']->id);
    }

    public function test_teacher_cannot_target_a_user_who_is_not_a_participant(): void {
        $seed = $this->separate_groups();
        $outsider = $this->getDataGenerator()->create_user();
        $this->setUser($seed['teacher']);

        try {
            mod_assign_grade_submission::execute((int) $seed['assign']->cmid, (int) $outsider->id);
            $this->fail('A user outside the course must be refused.');
        } catch (\required_capability_exception $e) {
            $this->assertInstanceOf(\required_capability_exception::class, $e);
        }

        $this->expectException(\required_capability_exception::class);
        get_grade_draft::execute((int) $seed['assign']->cmid, (int) $outsider->id);
    }
}
