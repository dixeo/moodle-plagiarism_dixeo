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
 * Decides whether a submission may be analysed automatically.
 *
 * @package    plagiarism_dixeo
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo\automation;

/**
 * The switches that all have to be on before Dixeo looks at a submission by itself.
 *
 * Automatic analysis sends student work to an external service, so it stays off unless
 * the master switch is on and this assignment's feedback switch is on. The gate is
 * consulted twice: when the event fires, and again when the queued task runs.
 */
class analysis_gate {
    /** @var string Analyse as soon as a draft is saved. */
    public const TRIGGER_UPLOAD = 'upload';

    /** @var string Analyse only when the student submits for grading. */
    public const TRIGGER_SUBMIT = 'submit';

    /** @var string Queue analysis for later. */
    public const MODE_ASYNC = 'async';

    /** @var string Run analysis in the observer. */
    public const MODE_SYNC = 'sync';

    /**
     * Whether the plagiarism subsystem and the Dixeo master switch allow analysis.
     *
     * The assignment's own feedback switch is checked separately.
     *
     * @return bool
     */
    public static function is_available(): bool {
        global $CFG;

        if (empty($CFG->enableplagiarism)) {
            return false;
        }

        return \local_dixeo\external\service_factory::get_settings_config()->enabled_for(\context_system::instance());
    }

    /**
     * The submission event Dixeo analyses on.
     *
     * @return string One of the TRIGGER_* constants.
     */
    public static function get_trigger(): string {
        $trigger = \plagiarism_dixeo\feature_settings::value(
            \plagiarism_dixeo\feature_settings::TRIGGER,
            \context_system::instance()
        );

        return $trigger === self::TRIGGER_UPLOAD ? self::TRIGGER_UPLOAD : self::TRIGGER_SUBMIT;
    }

    /**
     * Whether analysis runs in the observer or is queued.
     *
     * @return string One of the MODE_* constants.
     */
    public static function get_evaluation_mode(): string {
        $mode = \plagiarism_dixeo\feature_settings::value(
            \plagiarism_dixeo\feature_settings::EVALUATION_MODE,
            \context_system::instance()
        );

        return $mode === self::MODE_SYNC ? self::MODE_SYNC : self::MODE_ASYNC;
    }

    /**
     * Whether analysis should run before the observer returns.
     *
     * @return bool
     */
    public static function is_sync(): bool {
        return self::get_evaluation_mode() === self::MODE_SYNC;
    }

    /**
     * Whether a submission in this activity should be analysed on this trigger.
     *
     * @param int $cmid Assignment course module id.
     * @param string $trigger The trigger that fired.
     * @return bool
     */
    public static function should_analyse(int $cmid, string $trigger): bool {
        if ($cmid <= 0 || !self::is_available() || self::get_trigger() !== $trigger) {
            return false;
        }

        return self::is_enabled_for_activity($cmid);
    }

    /**
     * Whether assignment feedback is on for this assignment.
     *
     * @param int $cmid Assignment course module id.
     * @return bool
     */
    public static function is_enabled_for_activity(int $cmid): bool {
        global $CFG;

        require_once($CFG->dirroot . '/plagiarism/dixeo/lib.php');

        return \plagiarism_plugin_dixeo::is_enabled_for_activity($cmid);
    }

    /**
     * Whether the teacher has asked for a draft grade to be prepared for this activity.
     *
     * @param int $cmid Assignment course module id.
     * @return bool
     */
    public static function wants_draft_grade(int $cmid): bool {
        global $CFG;

        require_once($CFG->dirroot . '/plagiarism/dixeo/lib.php');

        if ($cmid <= 0 || !\plagiarism_dixeo\feature_settings::grading_on(\context_module::instance($cmid))) {
            return false;
        }

        $config = \plagiarism_plugin_dixeo::get_activity_config($cmid);

        return self::is_enabled_for_activity($cmid) && (int) ($config['enable_auto_grading'] ?? 0) === 1;
    }
}
