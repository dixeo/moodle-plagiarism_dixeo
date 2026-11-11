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
 * Assignment settings resolve to the stored assignment value or the Dixeo default.
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

    public function test_the_stored_assignment_value_is_returned(): void {
        $this->setAdminUser();
        $context = \context_module::instance((int) $this->cm->id);
        $config = \local_dixeo\external\service_factory::get_settings_config();
        $config->set('plagiarism_dixeo', 'show_initial_score', \context_system::instance(), 'hide');
        $config->set('plagiarism_dixeo', 'show_initial_score', $context, 'scoreandfeedback', false);
        $config->set('plagiarism_dixeo', 'authorship_threshold', $context, '40', false);

        $stored = \plagiarism_plugin_dixeo::get_activity_config((int) $this->cm->id);
        $this->assertSame('scoreandfeedback', $stored['show_initial_score']);
        $this->assertSame('40', $stored['authorship_threshold']);
    }

    public function test_an_assignment_without_a_stored_value_follows_the_dixeo_default(): void {
        global $DB;

        $this->setAdminUser();
        $context = \context_module::instance((int) $this->cm->id);
        $DB->delete_records('local_dixeo_setting', ['contextid' => $context->id]);
        $config = \local_dixeo\external\service_factory::get_settings_config();
        $config->set('plagiarism_dixeo', 'authorship_threshold', \context_system::instance(), '80');
        $config->set('plagiarism_dixeo', 'show_test_score', \context_system::instance(), 'hide');

        $resolved = \plagiarism_plugin_dixeo::get_activity_config((int) $this->cm->id);
        $this->assertSame('80', $resolved['authorship_threshold']);
        $this->assertSame('hide', $resolved['show_test_score']);
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

        $config->set('plagiarism_dixeo', 'authorship_threshold', \context_module::instance((int) $this->cm->id), '60', false);
        $config->set('plagiarism_dixeo', 'authorship_threshold', \context_system::instance(), '90');
        \plagiarism_plugin_dixeo::purge_activity_config_cache((int) $this->cm->id);

        $saved = \plagiarism_plugin_dixeo::get_activity_config((int) $this->cm->id);
        $this->assertSame('60', $saved['authorship_threshold']);
    }
}
