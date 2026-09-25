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
 * Per-activity configuration tests for plagiarism_dixeo.
 *
 * @package    plagiarism_dixeo
 * @category   test
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/plagiarism/dixeo/lib.php');

/**
 * Activity settings must default to off and reject values outside their allowed list.
 *
 * @covers \plagiarism_plugin_dixeo
 * @covers ::plagiarism_dixeo_coursemodule_edit_post_actions
 */
final class activity_config_test extends \advanced_testcase {
    /** @var \stdClass Course module of the assignment under test. */
    private $cm;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $this->cm = get_coursemodule_from_instance('assign', $assign->id, 0, false, MUST_EXIST);
    }

    public function test_an_unconfigured_activity_is_off_by_default(): void {
        $config = \plagiarism_plugin_dixeo::get_activity_config((int) $this->cm->id);

        $this->assertSame('0', $config['use_dixeo']);
        $this->assertSame('0', $config['enable_auto_grading']);
        $this->assertSame('score', $config['show_initial_score']);
        $this->assertSame('score', $config['show_test_score']);
        $this->assertSame('score', $config['show_final_score']);
    }

    public function test_values_round_trip_and_replace_rather_than_duplicate(): void {
        global $DB;

        \plagiarism_plugin_dixeo::set_activity_config((int) $this->cm->id, [
            'use_dixeo' => '1',
            'enable_auto_grading' => '1',
            'show_initial_score' => 'hide',
        ]);
        \plagiarism_plugin_dixeo::set_activity_config((int) $this->cm->id, [
            'use_dixeo' => '1',
            'show_initial_score' => 'scoreandfeedback',
        ]);

        $config = \plagiarism_plugin_dixeo::get_activity_config((int) $this->cm->id);
        $this->assertSame('1', $config['use_dixeo']);
        $this->assertSame('1', $config['enable_auto_grading']);
        $this->assertSame('scoreandfeedback', $config['show_initial_score']);
        $this->assertEquals(3, $DB->count_records('plagiarism_dixeo_config', ['cm' => $this->cm->id]));
    }

    public function test_values_outside_the_allowed_list_fall_back_to_the_default(): void {
        \plagiarism_plugin_dixeo::set_activity_config((int) $this->cm->id, [
            'use_dixeo' => '7',
            'show_final_score' => 'everything',
        ]);

        $config = \plagiarism_plugin_dixeo::get_activity_config((int) $this->cm->id);
        $this->assertSame('0', $config['use_dixeo']);
        $this->assertSame('score', $config['show_final_score']);
    }

    public function test_unknown_setting_names_are_not_stored(): void {
        global $DB;

        \plagiarism_plugin_dixeo::set_activity_config((int) $this->cm->id, ['evaluation_mode' => 'sync']);

        $this->assertFalse($DB->record_exists('plagiarism_dixeo_config', ['name' => 'evaluation_mode']));
    }

    public function test_a_row_tampered_with_directly_is_ignored(): void {
        global $DB;

        $DB->insert_record('plagiarism_dixeo_config', (object) [
            'cm' => (int) $this->cm->id,
            'name' => 'use_dixeo',
            'value' => '<script>alert(1)</script>',
        ]);

        $config = \plagiarism_plugin_dixeo::get_activity_config((int) $this->cm->id);
        $this->assertSame('0', $config['use_dixeo']);
    }

    public function test_post_actions_saves_only_known_fields_from_validated_data(): void {
        $data = (object) [
            'coursemodule' => (int) $this->cm->id,
            'modulename' => 'assign',
            'use_dixeo' => '1',
            'show_test_score' => 'hide',
            'name' => 'Not a Dixeo setting',
        ];

        plagiarism_dixeo_coursemodule_edit_post_actions($data, (object) ['id' => $this->cm->course]);

        $config = \plagiarism_plugin_dixeo::get_activity_config((int) $this->cm->id);
        $this->assertSame('1', $config['use_dixeo']);
        $this->assertSame('hide', $config['show_test_score']);
        $this->assertSame('0', $config['enable_auto_grading']);
    }

    public function test_post_actions_ignores_modules_other_than_assign(): void {
        global $DB;

        $data = (object) [
            'coursemodule' => (int) $this->cm->id,
            'modulename' => 'forum',
            'use_dixeo' => '1',
        ];

        plagiarism_dixeo_coursemodule_edit_post_actions($data, (object) ['id' => $this->cm->course]);

        $this->assertFalse($DB->record_exists('plagiarism_dixeo_config', ['cm' => $this->cm->id]));
    }

    public function test_activity_gating_requires_the_site_and_activity_switches(): void {
        global $CFG;

        \plagiarism_plugin_dixeo::set_activity_config((int) $this->cm->id, ['use_dixeo' => '1']);

        $CFG->enableplagiarism = 1;
        set_config('enabled', 1, 'plagiarism_dixeo');
        set_config('enable_mod_assign', 1, 'plagiarism_dixeo');
        $this->assertTrue(\plagiarism_plugin_dixeo::is_enabled_for_activity((int) $this->cm->id));

        set_config('enable_mod_assign', 0, 'plagiarism_dixeo');
        $this->assertFalse(\plagiarism_plugin_dixeo::is_enabled_for_activity((int) $this->cm->id));

        set_config('enable_mod_assign', 1, 'plagiarism_dixeo');
        set_config('enabled', 0, 'plagiarism_dixeo');
        $this->assertFalse(\plagiarism_plugin_dixeo::is_enabled_for_activity((int) $this->cm->id));

        set_config('enabled', 1, 'plagiarism_dixeo');
        $CFG->enableplagiarism = 0;
        $this->assertFalse(\plagiarism_plugin_dixeo::is_enabled_for_activity((int) $this->cm->id));
    }

    public function test_activity_gating_is_off_when_the_activity_opts_out(): void {
        global $CFG;

        $CFG->enableplagiarism = 1;
        set_config('enabled', 1, 'plagiarism_dixeo');
        set_config('enable_mod_assign', 1, 'plagiarism_dixeo');

        $this->assertFalse(\plagiarism_plugin_dixeo::is_enabled_for_activity((int) $this->cm->id));
    }
}
