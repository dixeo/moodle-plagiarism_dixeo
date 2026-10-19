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
 * Catalogue rules for the three plagiarism features.
 *
 * @package    plagiarism_dixeo
 * @category   test
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo;

use local_dixeo\external\service_factory;
use plagiarism_dixeo\automation\analysis_gate;
use plagiarism_dixeo\external\assign_access;

/**
 * An assignment copy is independent of the Dixeo default, and grading gates auto-apply.
 *
 * @covers \plagiarism_dixeo\feature_settings
 * @covers \plagiarism_dixeo\automation\analysis_gate::wants_draft_grade
 */
final class feature_settings_test extends \advanced_testcase {
    /**
     * A saved assignment switch stays as it was after the Dixeo default changes.
     */
    public function test_an_assignment_keeps_its_switch_after_the_dixeo_default_changes(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $module = \context_module::instance((int) $assign->cmid);
        $config = service_factory::get_settings_config();

        set_config('assignment_feedback_enabled', '0', 'plagiarism_dixeo');
        $config->set('plagiarism_dixeo', 'assignment_feedback_enabled', $module, '1', false);

        $this->assertTrue(feature_settings::assignment_feedback_on($module));
        $this->assertFalse(feature_settings::assignment_feedback_on(\context_system::instance()));
    }

    /**
     * An assignment with no saved copy follows the Dixeo default, and a course cannot store one.
     */
    public function test_an_unpopulated_assignment_follows_the_dixeo_default(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $module = \context_module::instance((int) $assign->cmid);
        $config = service_factory::get_settings_config();

        set_config('authorship_enabled', '0', 'plagiarism_dixeo');
        $this->assertFalse(feature_settings::authorship_on($module));

        set_config('authorship_enabled', '1', 'plagiarism_dixeo');
        $this->assertTrue(feature_settings::authorship_on($module));
        $this->assertTrue(feature_settings::authorship_on(\context_system::instance()));

        $this->expectException(\coding_exception::class);
        $config->set('plagiarism_dixeo', 'authorship_enabled', \context_course::instance($course->id), '0', false);
    }

    /**
     * The verification quiz does nothing unless authorship is on as well.
     */
    public function test_the_verification_quiz_requires_authorship(): void {
        $this->resetAfterTest();
        $system = \context_system::instance();

        set_config('verification_test', '1', 'plagiarism_dixeo');
        $this->assertFalse(feature_settings::verification_quiz_on($system));

        set_config('authorship_enabled', '1', 'plagiarism_dixeo');
        $this->assertTrue(feature_settings::verification_quiz_on($system));
    }

    /**
     * An assignment with no grading copy follows a Dixeo Off default.
     */
    public function test_grading_off_blocks_auto_apply_and_assist_grade(): void {
        global $CFG;

        $this->resetAfterTest();
        $this->setAdminUser();
        $CFG->enableplagiarism = 1;
        set_config('assignment_feedback_enabled', '1', 'plagiarism_dixeo');
        set_config('grading_enabled', '0', 'plagiarism_dixeo');

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        \plagiarism_plugin_dixeo::set_activity_config((int) $assign->cmid, ['enable_auto_grading' => '1']);

        $this->assertFalse(analysis_gate::wants_draft_grade((int) $assign->cmid));

        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $this->setUser($teacher);
        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('grading_not_enabled', 'plagiarism_dixeo'));
        assign_access::require_teacher_grade((int) $assign->cmid);
    }
}
