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
 * Hook callbacks for head output (AMD script injection on assign view/grader).
 *
 * @package    plagiarism_dixeo
 * @author     Josemaria Bolanos <admin@mako.digital>
 * @copyright  2026 Dixeo.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo\hook;

/**
 * Hook callbacks for head output (URL-based AMD script injection).
 */
class output {

    /** Match type: path contains the pattern (substring). */
    private const MATCH_CONTAINS = 'contains';

    /** Match type: path equals the pattern exactly. */
    private const MATCH_EXACT = 'exact';

    /** Match type: pattern is a PCRE regex matched against the path. */
    private const MATCH_REGEX = 'regex';

    /**
     * URL path patterns and the AMD module to load when the current URL matches.
     *
     * @var list<array{pattern: string, match?: string, query_param?: array<string, string>, amd: array}>
     */
    private static $url_script_map = [
        [
            'pattern'     => 'mod/assign/view.php',
            'match'      => self::MATCH_CONTAINS,
            'query_param' => ['action' => 'view'],
            'amd'        => ['plagiarism_dixeo/mod_assign_pre_submission', 'init', []],
        ],
        [
            'pattern'     => 'mod/assign/view.php',
            'match'      => self::MATCH_CONTAINS,
            'query_param' => ['action' => 'grader'],
            'amd'        => ['plagiarism_dixeo/mod_assign_grade_submission', 'init', []],
        ],
    ];

    /**
     * Add scripts when head HTML is generated, based on the current URL.
     *
     * @param \core\hook\output\before_standard_head_html_generation $hook The hook.
     */
    public static function before_standard_head_html_generation(
        \core\hook\output\before_standard_head_html_generation $hook
    ): void {
        $renderer = $hook->renderer;
        if (!$renderer) {
            return;
        }
        $page = $renderer->get_page();
        if (!$page || !$page->url || !$page->requires) {
            return;
        }
        $path = $page->url->get_path();

        foreach (self::$url_script_map as $entry) {
            $pattern = $entry['pattern'];
            $matchtype = $entry['match'] ?? self::MATCH_CONTAINS;
            $amdconfig = $entry['amd'];
            $queryparam = $entry['query_param'] ?? null;

            $matched = false;
            if ($matchtype === self::MATCH_EXACT) {
                $matched = ($path === $pattern || $path === '/' . ltrim($pattern, '/'));
            } else if ($matchtype === self::MATCH_REGEX) {
                $matched = (preg_match($pattern, $path) === 1);
            } else {
                $matched = (strpos($path, $pattern) !== false);
            }

            if ($matched && $queryparam !== null) {
                foreach ($queryparam as $name => $value) {
                    if ($page->url->get_param($name) != $value) {
                        $matched = false;
                        break;
                    }
                }
            }

            if ($matched) {
                $page->requires->js_call_amd($amdconfig[0], $amdconfig[1], $amdconfig[2]);
            }
        }
    }
}
