<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify it
// under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful, but
// WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Dixeo plagiarism plugin database upgrade.
 *
 * @package    plagiarism_dixeo
 * @copyright  2026 Dixeo.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade plagiarism_dixeo database.
 *
 * @param int $oldversion Version we are upgrading from.
 * @return bool True on success.
 */
function xmldb_plagiarism_dixeo_upgrade(int $oldversion): bool {
    global $DB;

    if ($oldversion < 2025022701) {
        $table = new xmldb_table('plagiarism_dixeo_authorship');
        $field = new xmldb_field('quiz_explanation', XMLDB_TYPE_TEXT, null, null, null, null, null, 'quiz_grade');
        if (!$DB->get_manager()->field_exists($table, $field)) {
            $DB->get_manager()->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2025022701, 'plagiarism', 'dixeo');
    }

    if ($oldversion < 2026022601) {
        $table = new xmldb_table('plagiarism_dixeo_authorship');
        $field = new xmldb_field('quiz_question_feedbacks', XMLDB_TYPE_TEXT, null, null, null, null, null, 'quiz_explanation');
        if (!$DB->get_manager()->field_exists($table, $field)) {
            $DB->get_manager()->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026022601, 'plagiarism', 'dixeo');
    }

    return true;
}
