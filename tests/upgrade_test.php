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
 * Upgrade of the old plagiarism switches into the Dixeo catalogue.
 *
 * @package    plagiarism_dixeo
 * @category   test
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo;

/**
 * Sites that already analysed assignments keep that behaviour. New keys stay off otherwise.
 *
 * @covers ::xmldb_plagiarism_dixeo_upgrade
 */
final class upgrade_test extends \advanced_testcase {
    /**
     * Both feature switches are stored only when the old plugin and assignment switches were on.
     */
    public function test_upgrade_keeps_analysis_and_grading_when_both_old_switches_were_on(): void {
        global $CFG, $DB;

        $this->resetAfterTest();
        require_once($CFG->libdir . '/upgradelib.php');
        require_once($CFG->dirroot . '/plagiarism/dixeo/db/upgrade.php');

        set_config('version', 2026101899, 'plagiarism_dixeo');
        set_config('enabled', '1', 'plagiarism_dixeo');
        set_config('enable_mod_assign', '1', 'plagiarism_dixeo');
        set_config('quiz_total_time', '300', 'plagiarism_dixeo');
        set_config('show_initial_score', 'hide', 'plagiarism_dixeo');

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $DB->insert_record('plagiarism_dixeo_config', (object) [
            'cm' => (int) $assign->cmid,
            'name' => 'use_dixeo',
            'value' => '1',
        ]);
        $DB->insert_record('plagiarism_dixeo_config', (object) [
            'cm' => (int) $assign->cmid,
            'name' => 'enable_auto_grading',
            'value' => '1',
        ]);

        xmldb_plagiarism_dixeo_upgrade(2026101899);

        $this->assertSame('1', get_config('plagiarism_dixeo', 'assignment_feedback_enabled'));
        $this->assertSame('1', get_config('plagiarism_dixeo', 'grading_enabled'));
        $this->assertFalse(get_config('plagiarism_dixeo', 'enabled'));
        $this->assertFalse(get_config('plagiarism_dixeo', 'enable_mod_assign'));
        $this->assertFalse(get_config('plagiarism_dixeo', 'quiz_total_time'));
        $this->assertSame('hide', get_config('plagiarism_dixeo', 'show_initial_score'));
        $this->assertFalse($DB->record_exists('plagiarism_dixeo_config', ['name' => 'use_dixeo']));
        $this->assertTrue($DB->record_exists('plagiarism_dixeo_config', [
            'cm' => (int) $assign->cmid,
            'name' => 'enable_auto_grading',
            'value' => '1',
        ]));
    }

    /**
     * A site that had not enabled the plugin does not gain the new features.
     */
    public function test_upgrade_leaves_features_off_when_the_old_plugin_was_off(): void {
        global $CFG;

        $this->resetAfterTest();
        require_once($CFG->libdir . '/upgradelib.php');
        require_once($CFG->dirroot . '/plagiarism/dixeo/db/upgrade.php');
        set_config('version', 2026101899, 'plagiarism_dixeo');
        set_config('enabled', '0', 'plagiarism_dixeo');
        set_config('enable_mod_assign', '1', 'plagiarism_dixeo');

        xmldb_plagiarism_dixeo_upgrade(2026101899);

        $this->assertFalse(get_config('plagiarism_dixeo', 'assignment_feedback_enabled'));
        $this->assertFalse(get_config('plagiarism_dixeo', 'grading_enabled'));
        $this->assertFalse(get_config('plagiarism_dixeo', 'enabled'));
    }
}
