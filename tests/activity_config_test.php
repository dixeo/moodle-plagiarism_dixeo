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

    public function test_the_assignment_modal_lists_only_the_assignment_copy(): void {
        $this->setAdminUser();
        $rows = \local_dixeo\external\service_factory::get_settings_config()->get_layers(
            \context_module::instance((int) $this->cm->id)
        );
        $names = array_column($rows, 'name');
        sort($names);
        $this->assertSame([
            'assignment_feedback_enabled',
            'authorship_enabled',
            'authorship_threshold',
            'enable_auto_grading',
            'grading_enabled',
            'show_final_score',
            'show_initial_score',
            'show_test_score',
            'verification_test',
        ], $names);
    }

    public function test_an_unconfigured_activity_uses_the_shipped_defaults(): void {
        $config = \plagiarism_plugin_dixeo::get_activity_config((int) $this->cm->id);

        $this->assertSame('75', $config['authorship_threshold']);
        $this->assertSame('0', $config['enable_auto_grading']);
        $this->assertSame('score', $config['show_initial_score']);
        $this->assertSame('score', $config['show_test_score']);
        $this->assertSame('score', $config['show_final_score']);
        $this->assertArrayNotHasKey('use_dixeo', $config);
    }

    public function test_values_round_trip_and_replace_rather_than_duplicate(): void {
        global $DB;

        \plagiarism_plugin_dixeo::set_activity_config((int) $this->cm->id, [
            'enable_auto_grading' => '1',
            'show_initial_score' => 'hide',
        ]);
        \plagiarism_plugin_dixeo::set_activity_config((int) $this->cm->id, [
            'show_initial_score' => 'scoreandfeedback',
        ]);

        $config = \plagiarism_plugin_dixeo::get_activity_config((int) $this->cm->id);
        $this->assertSame('1', $config['enable_auto_grading']);
        $this->assertSame('scoreandfeedback', $config['show_initial_score']);
        $this->assertEquals(2, $DB->count_records('plagiarism_dixeo_config', ['cm' => $this->cm->id]));
    }

    public function test_values_outside_the_allowed_list_are_not_stored(): void {
        global $DB;

        \plagiarism_plugin_dixeo::set_activity_config((int) $this->cm->id, [
            'authorship_threshold' => '120',
            'show_final_score' => 'everything',
        ]);

        $this->assertFalse($DB->record_exists('plagiarism_dixeo_config', [
            'cm' => $this->cm->id,
            'name' => 'authorship_threshold',
        ]));
        $config = \plagiarism_plugin_dixeo::get_activity_config((int) $this->cm->id);
        $this->assertSame('75', $config['authorship_threshold']);
        $this->assertSame('score', $config['show_final_score']);
    }

    public function test_an_invalid_activity_threshold_follows_the_dixeo_default(): void {
        global $DB;

        $this->setAdminUser();
        $config = \local_dixeo\external\service_factory::get_settings_config();
        $config->set('plagiarism_dixeo', 'authorship_threshold', \context_system::instance(), '80');
        $DB->insert_record('local_dixeo_setting', (object) [
            'contextid' => \context_module::instance((int) $this->cm->id)->id,
            'component' => 'plagiarism_dixeo',
            'name' => 'authorship_threshold',
            'value' => '500',
        ]);

        $stored = \plagiarism_plugin_dixeo::get_activity_config((int) $this->cm->id);
        $this->assertSame('80', $stored['authorship_threshold']);
    }

    public function test_a_threshold_outside_zero_to_one_hundred_is_rejected(): void {
        $this->setAdminUser();

        $this->expectException(\moodle_exception::class);
        $this->expectExceptionMessage(get_string('settings_number_range', 'local_dixeo', (object) [
            'min' => 0,
            'max' => 100,
        ]));
        \local_dixeo\external\service_factory::get_settings_config()->set(
            'plagiarism_dixeo',
            'authorship_threshold',
            \context_module::instance((int) $this->cm->id),
            '500'
        );
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
            'name' => 'show_final_score',
            'value' => '<script>alert(1)</script>',
        ]);

        $config = \plagiarism_plugin_dixeo::get_activity_config((int) $this->cm->id);
        $this->assertSame('score', $config['show_final_score']);
    }

    public function test_activity_gating_requires_the_site_and_activity_switches(): void {
        global $CFG;

        $CFG->enableplagiarism = 1;
        set_config('assignment_feedback_enabled', 1, 'plagiarism_dixeo');
        set_config('grading_enabled', 1, 'plagiarism_dixeo');
        $this->assertTrue(\plagiarism_plugin_dixeo::is_enabled_for_activity((int) $this->cm->id));

        set_config('assignment_feedback_enabled', '0', 'plagiarism_dixeo');
        $this->assertFalse(\plagiarism_plugin_dixeo::is_enabled_for_activity((int) $this->cm->id));

        set_config('assignment_feedback_enabled', '1', 'plagiarism_dixeo');
        set_config('enabled', '0', 'local_dixeo');
        $this->assertFalse(\plagiarism_plugin_dixeo::is_enabled_for_activity((int) $this->cm->id));

        set_config('enabled', '1', 'local_dixeo');
        $CFG->enableplagiarism = 0;
        $this->assertFalse(\plagiarism_plugin_dixeo::is_enabled_for_activity((int) $this->cm->id));
    }

    public function test_a_saved_assignment_keeps_its_snapshot_after_the_dixeo_default_changes(): void {
        $this->setAdminUser();
        $config = \local_dixeo\external\service_factory::get_settings_config();
        $config->set('plagiarism_dixeo', 'authorship_threshold', \context_system::instance(), '80');

        $fresh = \plagiarism_plugin_dixeo::get_activity_config((int) $this->cm->id);
        $this->assertSame('80', $fresh['authorship_threshold']);

        \plagiarism_plugin_dixeo::set_activity_config((int) $this->cm->id, ['authorship_threshold' => '60']);
        $config->set('plagiarism_dixeo', 'authorship_threshold', \context_system::instance(), '90');

        $saved = \plagiarism_plugin_dixeo::get_activity_config((int) $this->cm->id);
        $this->assertSame('60', $saved['authorship_threshold']);
    }
}
