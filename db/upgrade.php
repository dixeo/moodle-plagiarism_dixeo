<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Database upgrade steps for the Dixeo plagiarism plugin.
 *
 * @package    plagiarism_dixeo
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the plagiarism_dixeo plugin.
 *
 * @param int $oldversion The old version of the plugin.
 * @return bool True on success.
 */
function xmldb_plagiarism_dixeo_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026092500) {
        $table = new xmldb_table('plagiarism_dixeo_authorship');

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('component', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
        $table->add_field('area', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
        $table->add_field('itemid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('initial_confidence', XMLDB_TYPE_NUMBER, '5,2', null, XMLDB_NOTNULL);
        $table->add_field('quiz_data', XMLDB_TYPE_TEXT, null, null, null);
        $table->add_field('quiz_responses', XMLDB_TYPE_TEXT, null, null, null);
        $table->add_field('quiz_grade', XMLDB_TYPE_NUMBER, '5,2', null, null);
        $table->add_field('final_confidence', XMLDB_TYPE_NUMBER, '5,2', null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('user_component_itemid', XMLDB_KEY_UNIQUE, ['userid', 'component', 'itemid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026092500, 'plagiarism', 'dixeo');
    }

    if ($oldversion < 2026092501) {
        $authorship = new xmldb_table('plagiarism_dixeo_authorship');

        $newfields = [
            new xmldb_field('source_timemodified', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'itemid'),
            new xmldb_field('source_attempt', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'source_timemodified'),
            new xmldb_field('status', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, 'complete', 'source_attempt'),
            new xmldb_field('initial_explanation', XMLDB_TYPE_TEXT, null, null, null, null, null, 'initial_confidence'),
            new xmldb_field('response_timings', XMLDB_TYPE_TEXT, null, null, null, null, null, 'quiz_responses'),
            new xmldb_field('quiz_explanation', XMLDB_TYPE_TEXT, null, null, null, null, null, 'quiz_grade'),
            new xmldb_field('quiz_question_feedbacks', XMLDB_TYPE_TEXT, null, null, null, null, null, 'quiz_explanation'),
            new xmldb_field('final_explanation', XMLDB_TYPE_TEXT, null, null, null, null, null, 'final_confidence'),
        ];
        foreach ($newfields as $field) {
            if (!$dbman->field_exists($authorship, $field)) {
                $dbman->add_field($authorship, $field);
            }
        }

        // Pending records are created before the confidence score is known.
        $dbman->change_field_notnull(
            $authorship,
            new xmldb_field('initial_confidence', XMLDB_TYPE_NUMBER, '5,2', null, null, null, null, 'status')
        );

        $statusindex = new xmldb_index('status', XMLDB_INDEX_NOTUNIQUE, ['status']);
        if (!$dbman->index_exists($authorship, $statusindex)) {
            $dbman->add_index($authorship, $statusindex);
        }

        // Rows carrying an unanswered quiz predate the status column and are awaiting verification.
        $DB->set_field_select(
            'plagiarism_dixeo_authorship',
            'status',
            'verification_required',
            'quiz_data IS NOT NULL AND final_confidence IS NULL'
        );

        $presubmission = new xmldb_table('plagiarism_dixeo_presubmission_feedback');
        $presubmission->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $presubmission->add_field('component', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
        $presubmission->add_field('area', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
        $presubmission->add_field('itemid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $presubmission->add_field('source_timemodified', XMLDB_TYPE_INTEGER, '10', null, null);
        $presubmission->add_field('source_attempt', XMLDB_TYPE_INTEGER, '10', null, null);
        $presubmission->add_field('feedback', XMLDB_TYPE_TEXT, null, null, null);
        $presubmission->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $presubmission->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $presubmission->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $presubmission->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $presubmission->add_key('user_component_itemid', XMLDB_KEY_UNIQUE, ['userid', 'component', 'itemid']);
        if (!$dbman->table_exists($presubmission)) {
            $dbman->create_table($presubmission);
        }

        $gradefeedback = new xmldb_table('plagiarism_dixeo_grade_feedback');
        $gradefeedback->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $gradefeedback->add_field('component', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
        $gradefeedback->add_field('area', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
        $gradefeedback->add_field('itemid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $gradefeedback->add_field('source_timemodified', XMLDB_TYPE_INTEGER, '10', null, null);
        $gradefeedback->add_field('source_attempt', XMLDB_TYPE_INTEGER, '10', null, null);
        $gradefeedback->add_field('status', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, 'draft');
        $gradefeedback->add_field('gradingmethod', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL);
        $gradefeedback->add_field('analysis', XMLDB_TYPE_TEXT, null, null, null);
        $gradefeedback->add_field('feedback', XMLDB_TYPE_TEXT, null, null, null);
        $gradefeedback->add_field('grade', XMLDB_TYPE_NUMBER, '10,5', null, null);
        $gradefeedback->add_field('criteria', XMLDB_TYPE_TEXT, null, null, null);
        $gradefeedback->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $gradefeedback->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $gradefeedback->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL);
        $gradefeedback->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $gradefeedback->add_key('user_component_itemid', XMLDB_KEY_UNIQUE, ['userid', 'component', 'itemid']);
        if (!$dbman->table_exists($gradefeedback)) {
            $dbman->create_table($gradefeedback);
        }

        $config = new xmldb_table('plagiarism_dixeo_config');
        $config->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $config->add_field('cm', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $config->add_field('name', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
        $config->add_field('value', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
        $config->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $config->add_key('cm_name', XMLDB_KEY_UNIQUE, ['cm', 'name']);
        if (!$dbman->table_exists($config)) {
            $dbman->create_table($config);
        }

        upgrade_plugin_savepoint(true, 2026092501, 'plagiarism', 'dixeo');
    }

    if ($oldversion < 2026101900) {
        $wasenabled = (int) get_config('plagiarism_dixeo', 'enabled') === 1
            && (int) get_config('plagiarism_dixeo', 'enable_mod_assign') === 1;
        if ($wasenabled) {
            set_config('assignment_feedback_enabled', '1', 'plagiarism_dixeo');
            set_config('grading_enabled', '1', 'plagiarism_dixeo');
        }

        $obsolete = [
            'enabled',
            'enable_mod_assign',
            'authorship_count_true_false',
            'authorship_count_multiple_choice',
            'authorship_count_open',
            'quiz_total_time',
            'quiz_time_true_false',
            'quiz_time_multiple_choice',
            'quiz_time_open',
            'allow_backwards_navigation',
            'next_button_delay_seconds',
        ];
        foreach ($obsolete as $name) {
            unset_config($name, 'plagiarism_dixeo');
        }

        if ($dbman->table_exists('plagiarism_dixeo_config')) {
            $DB->delete_records('plagiarism_dixeo_config', ['name' => 'use_dixeo']);
        }

        upgrade_plugin_savepoint(true, 2026101900, 'plagiarism', 'dixeo');
    }

    return true;
}
