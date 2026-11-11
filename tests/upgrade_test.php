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
 * Upgrade steps keep earlier behaviour, record evidence of past transfers and drop orphaned analyses.
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
        $this->create_legacy_config_table();

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
        $this->assertFalse($DB->get_manager()->table_exists('plagiarism_dixeo_config'));
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

        $this->assertNotSame('1', get_config('plagiarism_dixeo', 'assignment_feedback_enabled'));
        $this->assertNotSame('1', get_config('plagiarism_dixeo', 'grading_enabled'));
        $this->assertFalse(get_config('plagiarism_dixeo', 'enabled'));
    }

    /**
     * Stored analyses are evidence of past transfers to Dixeo, even when their submission is gone.
     */
    public function test_upgrade_records_transfer_evidence_from_stored_analyses(): void {
        global $CFG, $DB;

        $this->resetAfterTest();
        require_once($CFG->libdir . '/upgradelib.php');
        require_once($CFG->dirroot . '/plagiarism/dixeo/db/upgrade.php');
        set_config('version', 2026110999, 'plagiarism_dixeo');
        unset_config(\local_dixeo\privacy\remote_state::CONFIG_TRANSFERRED, 'local_dixeo');
        $student = $this->getDataGenerator()->create_user();
        $DB->insert_record('plagiarism_dixeo_presubmission_feedback', (object) [
            'userid' => $student->id,
            'component' => 'mod_assign',
            'area' => 'submission',
            'itemid' => 987654,
            'feedback' => '<p>Old</p>',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        xmldb_plagiarism_dixeo_upgrade(2026110999);

        $this->assertTrue(\local_dixeo\privacy\remote_state::has_transferred());
        $this->assertFalse($DB->record_exists('plagiarism_dixeo_presubmission_feedback', ['itemid' => 987654]));
    }

    /**
     * A site with no stored analysis gains no transfer evidence from this plugin.
     */
    public function test_upgrade_leaves_transfer_evidence_unset_without_stored_analyses(): void {
        global $CFG;

        $this->resetAfterTest();
        require_once($CFG->libdir . '/upgradelib.php');
        require_once($CFG->dirroot . '/plagiarism/dixeo/db/upgrade.php');
        set_config('version', 2026110999, 'plagiarism_dixeo');
        unset_config(\local_dixeo\privacy\remote_state::CONFIG_TRANSFERRED, 'local_dixeo');

        xmldb_plagiarism_dixeo_upgrade(2026110999);

        $this->assertFalse(\local_dixeo\privacy\remote_state::has_transferred());
    }

    /**
     * Analyses of existing submissions survive the orphan cleanup.
     */
    public function test_upgrade_keeps_analyses_of_existing_submissions(): void {
        global $CFG, $DB;

        $this->resetAfterTest();
        require_once($CFG->libdir . '/upgradelib.php');
        require_once($CFG->dirroot . '/plagiarism/dixeo/db/upgrade.php');
        set_config('version', 2026110999, 'plagiarism_dixeo');
        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $submissionid = $DB->insert_record('assign_submission', (object) [
            'assignment' => $assign->id,
            'userid' => $student->id,
            'status' => 'submitted',
            'attemptnumber' => 0,
            'latest' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        $DB->insert_record('plagiarism_dixeo_presubmission_feedback', (object) [
            'userid' => $student->id,
            'component' => 'mod_assign',
            'area' => 'submission',
            'itemid' => $submissionid,
            'feedback' => '<p>Kept</p>',
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        xmldb_plagiarism_dixeo_upgrade(2026110999);

        $this->assertTrue($DB->record_exists('plagiarism_dixeo_presubmission_feedback', ['itemid' => $submissionid]));
        $this->assertTrue($DB->get_manager()->table_exists('plagiarism_dixeo_autograde'));
    }

    /**
     * Legacy assignment rows become assignment values in the catalogue, unless one is already stored.
     */
    public function test_legacy_assignment_rows_become_catalogue_assignment_values(): void {
        global $CFG, $DB;

        $this->resetAfterTest();
        require_once($CFG->libdir . '/upgradelib.php');
        require_once($CFG->dirroot . '/plagiarism/dixeo/db/upgrade.php');
        set_config('version', 2026111099, 'plagiarism_dixeo');
        $this->create_legacy_config_table();
        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $context = \context_module::instance((int) $assign->cmid);
        $DB->delete_records('local_dixeo_setting', ['contextid' => $context->id]);
        $config = \local_dixeo\external\service_factory::get_settings_config();
        $config->set('plagiarism_dixeo', 'show_test_score', $context, 'scoreandfeedback', false);

        $legacy = [
            'authorship_threshold' => '40',
            'show_initial_score' => 'hide',
            'show_test_score' => 'hide',
            'show_final_score' => 'everything',
        ];
        foreach ($legacy as $name => $value) {
            $DB->insert_record('plagiarism_dixeo_config', (object) [
                'cm' => (int) $assign->cmid,
                'name' => $name,
                'value' => $value,
            ]);
        }

        xmldb_plagiarism_dixeo_upgrade(2026111099);

        $this->assertSame('40', $this->stored_value($context, 'authorship_threshold'));
        $this->assertSame('hide', $this->stored_value($context, 'show_initial_score'));
        $this->assertSame('scoreandfeedback', $this->stored_value($context, 'show_test_score'));
        $this->assertNull($this->stored_value($context, 'show_final_score'));
        $this->assertFalse($DB->get_manager()->table_exists('plagiarism_dixeo_config'));
    }

    /**
     * Legacy automatic grading is not copied, so no teacher is recorded for it.
     */
    public function test_legacy_automatic_grading_is_not_copied(): void {
        global $CFG, $DB;

        $this->resetAfterTest();
        require_once($CFG->libdir . '/upgradelib.php');
        require_once($CFG->dirroot . '/plagiarism/dixeo/db/upgrade.php');
        set_config('version', 2026111099, 'plagiarism_dixeo');
        $this->create_legacy_config_table();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $context = \context_module::instance((int) $assign->cmid);
        $DB->delete_records('local_dixeo_setting', ['contextid' => $context->id]);
        $DB->insert_record('plagiarism_dixeo_config', (object) [
            'cm' => (int) $assign->cmid,
            'name' => 'enable_auto_grading',
            'value' => '1',
        ]);

        xmldb_plagiarism_dixeo_upgrade(2026111099);

        $this->assertNull($this->stored_value($context, 'enable_auto_grading'));
        $this->assertFalse($DB->record_exists('plagiarism_dixeo_autograde', ['cmid' => (int) $assign->cmid]));
    }

    /**
     * Rows of a course module that no longer exists are dropped with the table.
     */
    public function test_legacy_rows_of_a_missing_module_are_ignored(): void {
        global $CFG, $DB;

        $this->resetAfterTest();
        require_once($CFG->libdir . '/upgradelib.php');
        require_once($CFG->dirroot . '/plagiarism/dixeo/db/upgrade.php');
        set_config('version', 2026111099, 'plagiarism_dixeo');
        $this->create_legacy_config_table();
        $before = $DB->count_records('local_dixeo_setting');
        $DB->insert_record('plagiarism_dixeo_config', (object) [
            'cm' => 987654,
            'name' => 'authorship_threshold',
            'value' => '40',
        ]);

        xmldb_plagiarism_dixeo_upgrade(2026111099);

        $this->assertSame($before, $DB->count_records('local_dixeo_setting'));
        $this->assertFalse($DB->get_manager()->table_exists('plagiarism_dixeo_config'));
    }

    /**
     * Create the legacy per-assignment settings table as the 2026092501 step defined it.
     */
    private function create_legacy_config_table(): void {
        global $DB;

        $dbman = $DB->get_manager();
        $table = new \xmldb_table('plagiarism_dixeo_config');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('cm', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
        $table->add_field('value', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('cm_name', XMLDB_KEY_UNIQUE, ['cm', 'name']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
    }

    /**
     * Value stored in the catalogue for an assignment, or null when it has none.
     *
     * @param \context_module $context Assignment context.
     * @param string $name Catalogue key.
     * @return string|null
     */
    private function stored_value(\context_module $context, string $name): ?string {
        global $DB;

        $value = $DB->get_field('local_dixeo_setting', 'value', [
            'contextid' => $context->id,
            'component' => 'plagiarism_dixeo',
            'name' => $name,
        ]);
        return $value === false ? null : (string) $value;
    }
}
