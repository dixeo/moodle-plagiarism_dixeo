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
 * Presentation of the Analyze feedback shown to a student.
 *
 * @package    plagiarism_dixeo
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo\feedback;

/**
 * Renders Analyze feedback the same way whether it was just computed or read back from storage.
 */
class review_feedback {
    /**
     * Purified feedback in the alert the submission page displays.
     *
     * @param string $feedback Feedback HTML from the API.
     * @param \context $context Assignment context.
     * @return string HTML fragment.
     */
    public static function html(string $feedback, \context $context): string {
        $clean = format_text($feedback, FORMAT_HTML, [
            'para' => true,
            'filter' => false,
            'context' => $context,
        ]);
        $classes = 'alert alert-info alert-block fade in alert-dismissible plagiarism-dixeo-review-feedback';

        return '<div class="' . $classes . '" role="alert">' . $clean . '</div>';
    }
}
