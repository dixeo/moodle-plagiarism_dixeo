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
 * Dixeo defaults copied onto assignments created by the module generator.
 *
 * @package    plagiarism_dixeo
 * @category   test
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo;

use local_dixeo\dsl\actions\create_module_action;
use local_dixeo\dsl\value_resolver;

/**
 * A generated assignment keeps the Dixeo values from the moment it is created.
 *
 * @covers \local_dixeo\settings\config::copy_activity_defaults
 * @covers \local_dixeo\dsl\actions\create_module_action
 */
final class module_generator_defaults_test extends \advanced_testcase {
    public function test_a_generated_assignment_keeps_the_dixeo_values_from_creation(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $config = \local_dixeo\external\service_factory::get_settings_config();
        $system = \context_system::instance();
        $config->set('plagiarism_dixeo', 'assignment_feedback_enabled', $system, '1');
        $config->set('plagiarism_dixeo', 'authorship_threshold', $system, '80');
        $config->set('plagiarism_dixeo', 'show_final_score', $system, 'hide');
        $config->set('plagiarism_dixeo', 'grading_enabled', $system, '1');
        $config->set('plagiarism_dixeo', 'enable_auto_grading', $system, '1');
        $config->set('plagiarism_dixeo', 'assign_trigger', $system, 'upload');

        $course = $this->getDataGenerator()->create_course();
        $cmid = $this->create_generated_module($course, 'assign', 'Generated assignment');
        $modulecontext = \context_module::instance($cmid);

        $config->set('plagiarism_dixeo', 'assignment_feedback_enabled', $system, '0');
        $config->set('plagiarism_dixeo', 'authorship_threshold', $system, '60');
        $config->set('plagiarism_dixeo', 'show_final_score', $system, 'score');
        $config->set('plagiarism_dixeo', 'show_initial_score', $system, 'hide');
        $config->set('plagiarism_dixeo', 'grading_enabled', $system, '0');

        $this->assertSame('1', $this->stored($modulecontext->id, 'assignment_feedback_enabled'));
        $this->assertSame('80', $this->stored($modulecontext->id, 'authorship_threshold'));
        $this->assertSame('hide', $this->stored($modulecontext->id, 'show_final_score'));
        $this->assertSame('score', $this->stored($modulecontext->id, 'show_initial_score'));
        $this->assertSame('1', $this->stored($modulecontext->id, 'grading_enabled'));
        $this->assertSame('1', $this->stored($modulecontext->id, 'enable_auto_grading'));
        $this->assertSame('0', $this->stored($modulecontext->id, 'authorship_enabled'));
        $this->assertFalse($this->stored($modulecontext->id, 'assign_trigger'));
        $this->assertFalse($this->stored($modulecontext->id, 'evaluation_mode'));
        $this->assertFalse($this->stored($modulecontext->id, 'student_disclosure'));

        $activity = \plagiarism_plugin_dixeo::get_activity_config($cmid);
        $this->assertSame('80', $activity['authorship_threshold']);
        $this->assertSame('hide', $activity['show_final_score']);
        $this->assertSame('score', $activity['show_initial_score']);
        $this->assertSame('1', $activity['enable_auto_grading']);
        $this->assertTrue(feature_settings::assignment_feedback_on($modulecontext));
        $this->assertTrue(feature_settings::grading_on($modulecontext));
        $this->assertSame('activity', $config->get('plagiarism_dixeo', 'authorship_threshold', $modulecontext)->get_source());

        $manual = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $manualcontext = \context_module::instance((int) $manual->cmid);
        $this->assertFalse($this->stored($manualcontext->id, 'authorship_threshold'));
        $this->assertFalse($this->stored($manualcontext->id, 'assignment_feedback_enabled'));
        $manualconfig = \plagiarism_plugin_dixeo::get_activity_config((int) $manual->cmid);
        $this->assertSame('60', $manualconfig['authorship_threshold']);
        $this->assertSame('score', $manualconfig['show_final_score']);
        $this->assertFalse(feature_settings::assignment_feedback_on($manualcontext));
    }

    public function test_a_generated_page_does_not_store_assignment_settings(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $config = \local_dixeo\external\service_factory::get_settings_config();
        $config->set('plagiarism_dixeo', 'assignment_feedback_enabled', \context_system::instance(), '1');

        $course = $this->getDataGenerator()->create_course();
        $cmid = $this->create_generated_module($course, 'page', 'Generated page');

        $this->assertFalse($this->stored(\context_module::instance($cmid)->id, 'assignment_feedback_enabled'));
    }

    /**
     * Stored snapshot for one setting, or false when the module has no row.
     *
     * @param int $contextid Module context id.
     * @param string $name Catalogue key.
     * @return string|false
     */
    private function stored(int $contextid, string $name): string|false {
        global $DB;
        return $DB->get_field('local_dixeo_setting', 'value', [
            'contextid' => $contextid,
            'component' => 'plagiarism_dixeo',
            'name' => $name,
        ]);
    }

    /**
     * Create one activity the way the module generator does.
     *
     * @param \stdClass $course Course.
     * @param string $modulename Module type.
     * @param string $name Activity name.
     * @return int Course module id.
     */
    private function create_generated_module(\stdClass $course, string $modulename, string $name): int {
        global $DB, $USER;

        $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => 0], '*', MUST_EXIST);
        $resolver = new value_resolver([
            'name' => $name,
            'intro' => '<p>Intro</p>',
            'activity' => '<p>Submit a PDF.</p>',
            'content' => '<p>Page body</p>',
        ], [], [
            'courseid' => (int) $course->id,
            'sectionid' => (int) $section->id,
            'sectionnum' => (int) $section->section,
            'modulename' => $modulename,
            'userid' => (int) $USER->id,
        ]);
        $fields = [
            'name' => ['source' => '$.name'],
            'intro' => ['source' => '$.intro'],
        ];
        if ($modulename === 'assign') {
            $fields['activity'] = ['source' => '$.activity'];
        }
        if ($modulename === 'page') {
            $fields['content'] = ['source' => '$.content'];
        }
        $created = (new create_module_action())->execute(['fields' => $fields], $resolver);
        return (int) $created['cmid'];
    }
}
