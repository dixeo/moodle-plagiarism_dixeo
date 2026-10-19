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
 * Tests that assign Analyze AMD is injected only when Dixeo is enabled for the activity.
 *
 * @package    plagiarism_dixeo
 * @category   test
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo;

use plagiarism_dixeo\hook\output;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/plagiarism/dixeo/lib.php');

/**
 * Student feedback and teacher grading scripts must not load on opted-out assignments.
 *
 * @covers \plagiarism_dixeo\hook\output
 */
final class hook_output_test extends \advanced_testcase {
    /** @var \stdClass Assignment course module. */
    private $assign;

    protected function setUp(): void {
        global $CFG;

        parent::setUp();
        $this->resetAfterTest();

        $CFG->enableplagiarism = 1;
        set_config('assignment_feedback_enabled', 1, 'plagiarism_dixeo');
        set_config('grading_enabled', 1, 'plagiarism_dixeo');

        $course = $this->getDataGenerator()->create_course();
        $this->assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
    }

    /**
     * Build an assign view URL.
     *
     * @param array $params Query parameters in addition to id.
     * @return \moodle_url
     */
    private function assign_url(array $params = []): \moodle_url {
        return new \moodle_url('/mod/assign/view.php', ['id' => (int) $this->assign->cmid] + $params);
    }

    /**
     * Module names that would be injected for a URL.
     *
     * @param \moodle_url $url Page URL.
     * @return string[]
     */
    private function modules_for(\moodle_url $url): array {
        return array_column(output::amd_calls_for_url($url), 0);
    }

    public function test_submission_page_loads_pre_submission_when_the_activity_is_on(): void {
        $this->assertSame(
            ['plagiarism_dixeo/mod_assign_pre_submission'],
            $this->modules_for($this->assign_url())
        );
        $this->assertSame(
            ['plagiarism_dixeo/mod_assign_pre_submission'],
            $this->modules_for($this->assign_url(['action' => 'view']))
        );
    }

    public function test_grader_loads_grade_submission_when_the_activity_is_on(): void {
        $this->assertSame(
            ['plagiarism_dixeo/mod_assign_grade_submission'],
            $this->modules_for($this->assign_url(['action' => 'grader']))
        );
    }

    public function test_no_scripts_when_the_assignment_turns_feedback_off(): void {
        \local_dixeo\external\service_factory::get_settings_config()->set(
            'plagiarism_dixeo',
            'assignment_feedback_enabled',
            \context_module::instance((int) $this->assign->cmid),
            '0',
            false
        );

        $this->assertSame([], $this->modules_for($this->assign_url()));
        $this->assertSame([], $this->modules_for($this->assign_url(['action' => 'grader'])));
    }

    public function test_grader_is_omitted_when_grading_is_off(): void {
        set_config('grading_enabled', '0', 'plagiarism_dixeo');

        $this->assertSame(
            ['plagiarism_dixeo/mod_assign_pre_submission'],
            $this->modules_for($this->assign_url())
        );
        $this->assertSame([], $this->modules_for($this->assign_url(['action' => 'grader'])));
    }

    public function test_no_scripts_when_the_dixeo_feedback_default_is_off(): void {
        set_config('assignment_feedback_enabled', '0', 'plagiarism_dixeo');

        $this->assertSame([], $this->modules_for($this->assign_url()));
        $this->assertSame([], $this->modules_for($this->assign_url(['action' => 'grader'])));
    }

    public function test_no_scripts_when_the_plugin_is_disabled(): void {
        set_config('enabled', '0', 'local_dixeo');

        $this->assertSame([], $this->modules_for($this->assign_url(['action' => 'grader'])));
    }

    public function test_grading_table_does_not_load_analyze_scripts(): void {
        $this->assertSame([], $this->modules_for($this->assign_url(['action' => 'grading'])));
    }
}
