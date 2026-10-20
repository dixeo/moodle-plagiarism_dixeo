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
 * Module-context capability checks for assign UX externals.
 *
 * @package    plagiarism_dixeo
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo\external;

use context_module;
use core_external\external_api;


/**
 * Shared access helpers for assign Analyze / authorship web services.
 */
final class assign_access {
    /**
     * Validate module context and require student Analyze capabilities.
     *
     * @param int $cmid Assign course module id.
     * @return context_module
     * @throws \moodle_exception
     */
    public static function require_student_analyze(int $cmid): context_module {
        $cm = get_coursemodule_from_id('assign', $cmid, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        external_api::validate_context($context);
        require_capability('plagiarism/dixeo:mod_assign_pre_submission', $context);
        require_capability('mod/assign:submit', $context);
        self::require_activity_enabled($cmid);
        return $context;
    }

    /**
     * Validate module context and require the authorship verification switch.
     *
     * Assignment feedback is not consulted. A student can sit the verification quiz
     * while feedback for this assignment is off.
     *
     * @param int $cmid Assign course module id.
     * @return context_module
     * @throws \moodle_exception
     */
    public static function require_student_authorship(int $cmid): context_module {
        $cm = get_coursemodule_from_id('assign', $cmid, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        external_api::validate_context($context);
        require_capability('plagiarism/dixeo:mod_assign_pre_submission', $context);
        require_capability('mod/assign:submit', $context);
        self::require_authorship_enabled($cmid);
        return $context;
    }

    /**
     * Validate module context and require teacher Assist grading capabilities.
     *
     * @param int $cmid Assign course module id.
     * @return context_module
     * @throws \moodle_exception
     */
    public static function require_teacher_grade(int $cmid): context_module {
        $cm = get_coursemodule_from_id('assign', $cmid, 0, false, MUST_EXIST);
        $context = context_module::instance($cm->id);
        external_api::validate_context($context);
        require_capability('plagiarism/dixeo:mod_assign_grade_submission', $context);
        require_capability('mod/assign:grade', $context);
        self::require_activity_enabled($cmid);
        self::require_grading_enabled($cmid);
        return $context;
    }

    /**
     * Refuse when the site or this assignment has Dixeo switched off.
     *
     * Capability checks run first so an outsider still sees a login or capability error
     * rather than a setting message for an activity they cannot reach.
     *
     * @param int $cmid Assign course module id.
     * @throws \moodle_exception
     */
    public static function require_activity_enabled(int $cmid): void {
        global $CFG;

        require_once($CFG->dirroot . '/plagiarism/dixeo/lib.php');

        if (!\plagiarism_plugin_dixeo::is_enabled_for_activity($cmid)) {
            throw new \moodle_exception('activity_not_enabled', 'plagiarism_dixeo');
        }
    }

    /**
     * Refuse when authorship verification is off for this assignment.
     *
     * @param int $cmid Assign course module id.
     * @throws \moodle_exception
     */
    public static function require_authorship_enabled(int $cmid): void {
        global $CFG;

        require_once($CFG->dirroot . '/plagiarism/dixeo/lib.php');

        if (!\plagiarism_plugin_dixeo::authorship_enabled_for_activity($cmid)) {
            throw new \moodle_exception('activity_not_enabled', 'plagiarism_dixeo');
        }
    }

    /**
     * Refuse Assist Grade when this assignment's grading switch is off.
     *
     * @param int $cmid Assign course module id.
     * @throws \moodle_exception
     */
    public static function require_grading_enabled(int $cmid): void {
        if ($cmid <= 0 || !\plagiarism_dixeo\feature_settings::grading_on(\context_module::instance($cmid))) {
            throw new \moodle_exception('grading_not_enabled', 'plagiarism_dixeo');
        }
    }

    /**
     * Resolve module context from an authorship record and require student Analyze caps.
     *
     * @param \stdClass $record Authorship row (component, area, itemid).
     * @return context_module
     * @throws \moodle_exception
     */
    public static function require_student_analyze_from_authorship(\stdClass $record): context_module {
        global $DB;

        if (($record->component ?? '') !== 'mod_assign' || ($record->area ?? '') !== 'submission') {
            throw new \moodle_exception('invalidparameter');
        }

        $submission = $DB->get_record(
            'assign_submission',
            ['id' => (int) $record->itemid],
            'id,assignment',
            MUST_EXIST
        );
        $cm = get_coursemodule_from_instance('assign', (int) $submission->assignment, 0, false, MUST_EXIST);
        return self::require_student_analyze((int) $cm->id);
    }

    /**
     * Resolve module context from an authorship record and require the verification switch.
     *
     * @param \stdClass $record Authorship row (component, area, itemid).
     * @return context_module
     * @throws \moodle_exception
     */
    public static function require_student_authorship_from_record(\stdClass $record): context_module {
        global $DB;

        if (($record->component ?? '') !== 'mod_assign' || ($record->area ?? '') !== 'submission') {
            throw new \moodle_exception('invalidparameter');
        }

        $submission = $DB->get_record(
            'assign_submission',
            ['id' => (int) $record->itemid],
            'id,assignment',
            MUST_EXIST
        );
        $cm = get_coursemodule_from_instance('assign', (int) $submission->assignment, 0, false, MUST_EXIST);
        return self::require_student_authorship((int) $cm->id);
    }
}
