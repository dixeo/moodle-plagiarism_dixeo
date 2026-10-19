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

namespace plagiarism_dixeo;

/**
 * Reads plagiarism feature switches and defaults from the Dixeo settings catalogue.
 *
 * A module context reads that assignment's copy, or the Dixeo default when the assignment
 * has none yet. Callers that need the master switch as well use
 * {@see \plagiarism_plugin_dixeo::enabled_for_course()}.
 *
 * @package    plagiarism_dixeo
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class feature_settings {
    /** @var string Catalogue component. */
    public const COMPONENT = 'plagiarism_dixeo';

    /** @var string Assignment feedback On/Off. */
    public const ASSIGNMENT_FEEDBACK = 'assignment_feedback_enabled';

    /** @var string Authorship verification On/Off. */
    public const AUTHORSHIP = 'authorship_enabled';

    /** @var string Verification quiz On/Off. */
    public const VERIFICATION_QUIZ = 'verification_test';

    /** @var string Grading On/Off. */
    public const GRADING = 'grading_enabled';

    /** @var string When automatic analysis runs. Dixeo level only. */
    public const TRIGGER = 'assign_trigger';

    /** @var string Sync or queued analysis. Dixeo level only. */
    public const EVALUATION_MODE = 'evaluation_mode';

    /** @var string Student disclosure text. Dixeo level only. */
    public const DISCLOSURE = 'student_disclosure';

    /** @var string Confidence threshold. A missing assignment snapshot uses the Dixeo default. */
    public const THRESHOLD = 'authorship_threshold';

    /** @var string Automatic grade application. A missing assignment snapshot uses the Dixeo default. */
    public const AUTO_GRADING = 'enable_auto_grading';

    /**
     * Effective catalogue value at $context.
     *
     * @param string $name Catalogue key.
     * @param \context $context System context for the Dixeo default, or a module context for one assignment.
     * @return string
     */
    public static function value(string $name, \context $context): string {
        return \local_dixeo\external\service_factory::get_settings_config()
            ->get(self::COMPONENT, $name, self::read_context($context))
            ->get_value();
    }

    /**
     * Whether a bool catalogue value is On at $context.
     *
     * @param string $name Catalogue key.
     * @param \context $context System context for the Dixeo default, or a module context.
     * @return bool
     */
    public static function is_on(string $name, \context $context): bool {
        return self::value($name, $context) === '1';
    }

    /**
     * Whether assignment feedback runs at $context.
     *
     * @param \context $context System context for the Dixeo default, or a module context.
     * @return bool
     */
    public static function assignment_feedback_on(\context $context): bool {
        return self::is_on(self::ASSIGNMENT_FEEDBACK, $context);
    }

    /**
     * Whether authorship verification runs at $context.
     *
     * @param \context $context System context for the Dixeo default, or a module context.
     * @return bool
     */
    public static function authorship_on(\context $context): bool {
        return self::is_on(self::AUTHORSHIP, $context);
    }

    /**
     * Whether a low confidence score can require a verification quiz.
     *
     * The quiz stays off unless authorship verification is on as well.
     *
     * @param \context $context System context for the Dixeo default, or a module context.
     * @return bool
     */
    public static function verification_quiz_on(\context $context): bool {
        return self::authorship_on($context) && self::is_on(self::VERIFICATION_QUIZ, $context);
    }

    /**
     * Whether Assist Grade and automatic grades are available at $context.
     *
     * @param \context $context System context for the Dixeo default, or a module context.
     * @return bool
     */
    public static function grading_on(\context $context): bool {
        return self::is_on(self::GRADING, $context);
    }

    /**
     * Course context for an assignment, or null when the module is missing.
     *
     * @param int $cmid Assignment course module id.
     * @return \context_course|null
     */
    public static function course_context_for_cm(int $cmid): ?\context_course {
        if ($cmid <= 0) {
            return null;
        }
        $cm = get_coursemodule_from_id('assign', $cmid, 0, false, IGNORE_MISSING);
        if (!$cm) {
            return null;
        }
        return \context_course::instance((int) $cm->course);
    }

    /**
     * System context for the Dixeo default, or the module context for one assignment.
     *
     * @param \context $context Requested context.
     * @return \context
     */
    private static function read_context(\context $context): \context {
        if ($context->contextlevel == CONTEXT_SYSTEM || $context->contextlevel == CONTEXT_MODULE) {
            return $context;
        }
        return \context_system::instance();
    }
}
