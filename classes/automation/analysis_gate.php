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
 * Automatic analysis sends student work to an external service, so it stays off unless a
 * site administrator has enabled the plugin and a teacher has opted this activity in. The
 * gate is consulted twice: when the event fires, and again when the queued task runs, since
 * an administrator may have turned the plugin off in between.
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
     * Whether automatic analysis of assignments is switched on for the site.
     *
     * @return bool
     */
    public static function is_available(): bool {
        global $CFG;

        if (empty($CFG->enableplagiarism)) {
            return false;
        }

        return (int) get_config('plagiarism_dixeo', 'enabled') === 1
            && (int) get_config('plagiarism_dixeo', 'enable_mod_assign') === 1;
    }

    /**
     * The submission event an administrator has chosen to analyse on.
     *
     * @return string One of the TRIGGER_* constants.
     */
    public static function get_trigger(): string {
        $trigger = (string) get_config('plagiarism_dixeo', 'assign_trigger');

        return $trigger === self::TRIGGER_UPLOAD ? self::TRIGGER_UPLOAD : self::TRIGGER_SUBMIT;
    }

    /**
     * Whether analysis runs in the observer or is queued for later.
     *
     * @return string One of the MODE_* constants.
     */
    public static function get_evaluation_mode(): string {
        $mode = (string) get_config('plagiarism_dixeo', 'evaluation_mode');

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
     * Whether the teacher has opted this activity in to Dixeo.
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

        $config = \plagiarism_plugin_dixeo::get_activity_config($cmid);

        return self::is_enabled_for_activity($cmid) && (int) ($config['enable_auto_grading'] ?? 0) === 1;
    }
}
