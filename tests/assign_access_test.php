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

use plagiarism_dixeo\external\assign_access;

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

    /**
     * Turn Dixeo on for an assignment at site and activity level.
     *
     * @param int $cmid Course module id.
     */
    private function enable_dixeo(int $cmid): void {
        global $CFG;

        $CFG->enableplagiarism = 1;
        set_config('enabled', 1, 'plagiarism_dixeo');
        set_config('enable_mod_assign', 1, 'plagiarism_dixeo');
        \plagiarism_plugin_dixeo::set_activity_config($cmid, ['use_dixeo' => '1']);
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

    public function test_student_cannot_analyze_when_the_activity_is_off(): void {
        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $this->enable_dixeo((int) $assign->cmid);
        \plagiarism_plugin_dixeo::set_activity_config((int) $assign->cmid, ['use_dixeo' => '0']);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('activity_not_enabled', 'plagiarism_dixeo'));
        assign_access::require_student_analyze((int) $assign->cmid);
    }

    public function test_teacher_cannot_grade_when_the_activity_is_off(): void {
        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $this->enable_dixeo((int) $assign->cmid);
        \plagiarism_plugin_dixeo::set_activity_config((int) $assign->cmid, ['use_dixeo' => '0']);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('activity_not_enabled', 'plagiarism_dixeo'));
        assign_access::require_teacher_grade((int) $assign->cmid);
    }

    public function test_teacher_can_grade_when_auto_grading_is_off(): void {
        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $this->enable_dixeo((int) $assign->cmid);
        \plagiarism_plugin_dixeo::set_activity_config((int) $assign->cmid, [
            'use_dixeo' => '1',
            'enable_auto_grading' => '0',
        ]);
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
}
