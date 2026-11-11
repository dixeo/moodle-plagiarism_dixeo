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
 * Tests for recording who switched automatic grading on.
 *
 * @package    plagiarism_dixeo
 * @category   test
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo;

use local_dixeo\external\service_factory;
use plagiarism_dixeo\grading\autograde_enabler;

/**
 * Only a deliberate activity-level On from a real user acting for themselves is recorded.
 *
 * @covers \plagiarism_dixeo\observer::setting_updated
 * @covers \plagiarism_dixeo\grading\autograde_enabler
 */
final class autograde_enabler_test extends \advanced_testcase {
    /** @var \stdClass Course. */
    private \stdClass $course;

    /** @var \stdClass Assign module record. */
    private \stdClass $assign;

    /** @var \context_module Assignment context. */
    private \context_module $context;

    /** @var \stdClass Teacher. */
    private \stdClass $teacher;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $this->course = $this->getDataGenerator()->create_course();
        $this->assign = $this->getDataGenerator()->create_module('assign', ['course' => $this->course->id]);
        $this->context = \context_module::instance($this->assign->cmid);
        $this->teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
    }

    /**
     * Store the assignment's own automatic grading value.
     *
     * @param string $value '1' or '0'.
     */
    private function set_activity_value(string $value): void {
        service_factory::get_settings_config()->set(
            'plagiarism_dixeo',
            'enable_auto_grading',
            $this->context,
            $value,
            false
        );
    }

    public function test_teacher_switching_it_on_is_recorded(): void {
        $this->setUser($this->teacher);

        $this->set_activity_value('1');

        $this->assertSame((int) $this->teacher->id, autograde_enabler::get_userid((int) $this->assign->cmid));
    }

    public function test_switching_it_off_clears_the_record(): void {
        $this->setUser($this->teacher);
        $this->set_activity_value('1');

        $this->set_activity_value('0');

        $this->assertSame(0, autograde_enabler::get_userid((int) $this->assign->cmid));
    }

    public function test_removing_the_assignment_value_clears_the_record(): void {
        global $DB;

        $this->setUser($this->teacher);
        $this->set_activity_value('1');
        $this->assertSame((int) $this->teacher->id, autograde_enabler::get_userid((int) $this->assign->cmid));

        $DB->delete_records('local_dixeo_setting', [
            'contextid' => $this->context->id,
            'component' => 'plagiarism_dixeo',
            'name' => 'enable_auto_grading',
        ]);
        \local_dixeo\event\setting_updated::create([
            'context' => $this->context,
            'other' => ['component' => 'plagiarism_dixeo', 'name' => 'enable_auto_grading'],
        ])->trigger();

        $this->assertSame(0, autograde_enabler::get_userid((int) $this->assign->cmid));
    }

    public function test_copying_the_dixeo_defaults_stores_no_automatic_grading(): void {
        global $DB;

        $this->setUser($this->teacher);
        $DB->delete_records('local_dixeo_setting', ['contextid' => $this->context->id]);

        service_factory::get_settings_config()->copy_activity_defaults($this->context);

        $this->assertFalse($DB->record_exists('local_dixeo_setting', [
            'contextid' => $this->context->id,
            'component' => 'plagiarism_dixeo',
            'name' => 'enable_auto_grading',
        ]));
        $this->assertSame('0', service_factory::get_settings_config()
            ->get('plagiarism_dixeo', 'enable_auto_grading', $this->context)->get_value());
        $this->assertSame(0, autograde_enabler::get_userid((int) $this->assign->cmid));
    }

    public function test_the_first_teacher_write_on_a_generated_assignment_is_recorded(): void {
        global $DB;

        $DB->delete_records('local_dixeo_setting', ['contextid' => $this->context->id]);
        service_factory::get_settings_config()->copy_activity_defaults($this->context);
        $this->setUser($this->teacher);

        $this->set_activity_value('1');

        $this->assertSame((int) $this->teacher->id, autograde_enabler::get_userid((int) $this->assign->cmid));
    }

    public function test_a_logged_in_as_write_records_nobody(): void {
        $this->setAdminUser();
        \core\session\manager::loginas((int) $this->teacher->id, \context_system::instance());

        $this->set_activity_value('1');

        $this->assertSame(0, autograde_enabler::get_userid((int) $this->assign->cmid));
    }

    public function test_a_write_without_a_user_records_nobody(): void {
        $this->setUser(null);

        $this->set_activity_value('1');

        $this->assertSame(0, autograde_enabler::get_userid((int) $this->assign->cmid));
    }

    public function test_other_settings_leave_the_record_alone(): void {
        $this->setUser($this->teacher);
        $this->set_activity_value('1');

        service_factory::get_settings_config()->set('plagiarism_dixeo', 'grading_enabled', $this->context, '0', false);

        $this->assertSame((int) $this->teacher->id, autograde_enabler::get_userid((int) $this->assign->cmid));
    }
}
