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
 * Hook callbacks for head output (URL-based AMD script injection).
 *
 * @package    plagiarism_dixeo
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo\hook;

/**
 * Injects assign Analyze AMD modules on matching assign view URLs.
 */
class output {
    /**
     * Match type: path contains the pattern (substring).
     */
    private const MATCH_CONTAINS = 'contains';

    /**
     * Match type: path equals the pattern exactly.
     */
    private const MATCH_EXACT = 'exact';

    /**
     * Match type: pattern is a PCRE regex matched against the path.
     */
    private const MATCH_REGEX = 'regex';

    /**
     * URL path patterns and AMD modules to load on matching assign view URLs.
     *
     * Each entry: pattern, match type, optional query_param, amd [module, method, args].
     *
     * A query_param value may be a single accepted value or a list of accepted values. Missing
     * parameters compare as an empty string, so '' matches URLs where the parameter is absent or
     * left at its default (mod/assign/view.php sets an empty action on the submission page).
     *
     * @var list<array{
     *     pattern: string,
     *     match?: string,
     *     query_param?: array<string, string|list<string>>,
     *     amd: array{0: string, 1: string, 2: array}
     * }>
     */
    private static $urlscriptmap = [
        [
            'pattern'     => 'mod/assign/view.php',
            'match'      => self::MATCH_CONTAINS,
            'query_param' => ['action' => ['', 'view']],
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
     * AMD calls that belong on this assign URL, or none when Dixeo is switched off.
     *
     * @param \moodle_url $url Current page URL.
     * @return list<array{0: string, 1: string, 2: array}>
     */
    public static function amd_calls_for_url(\moodle_url $url): array {
        global $CFG;

        require_once($CFG->dirroot . '/plagiarism/dixeo/lib.php');

        $cmid = (int) ($url->get_param('id') ?? 0);
        $cm = $cmid > 0 ? get_coursemodule_from_id('', $cmid, 0, false, IGNORE_MISSING) : false;
        if (!$cm || !\plagiarism_plugin_dixeo::enabled_for_course((int) $cm->course)) {
            return [];
        }
        if (!\plagiarism_plugin_dixeo::is_enabled_for_activity($cmid)) {
            return [];
        }

        $path = $url->get_path();
        $gradingon = \plagiarism_dixeo\feature_settings::grading_on(\context_module::instance($cmid));
        $calls = [];
        foreach (self::$urlscriptmap as $entry) {
            if (!self::url_matches($path, $url, $entry)) {
                continue;
            }
            $module = $entry['amd'][0];
            if ($module === 'plagiarism_dixeo/mod_assign_grade_submission' && !$gradingon) {
                continue;
            }
            $calls[] = $entry['amd'];
        }

        return $calls;
    }

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

        foreach (self::amd_calls_for_url($page->url) as $amdconfig) {
            $page->requires->js_call_amd($amdconfig[0], $amdconfig[1], $amdconfig[2]);
        }
    }

    /**
     * Whether a mapped entry applies to this path and query string.
     *
     * @param string $path URL path.
     * @param \moodle_url $url Full URL, for query parameters.
     * @param array $entry Map row.
     * @return bool
     */
    private static function url_matches(string $path, \moodle_url $url, array $entry): bool {
        $pattern = $entry['pattern'];
        $matchtype = $entry['match'] ?? self::MATCH_CONTAINS;

        $matched = false;
        if ($matchtype === self::MATCH_EXACT) {
            $matched = ($path === $pattern || $path === '/' . ltrim($pattern, '/'));
        } else if ($matchtype === self::MATCH_REGEX) {
            $matched = (preg_match($pattern, $path) === 1);
        } else {
            $matched = (strpos($path, $pattern) !== false);
        }

        if (!$matched) {
            return false;
        }

        $queryparam = $entry['query_param'] ?? null;
        if ($queryparam === null) {
            return true;
        }

        foreach ($queryparam as $name => $value) {
            $actual = (string) ($url->get_param($name) ?? '');
            $accepted = array_map('strval', is_array($value) ? $value : [$value]);
            if (!in_array($actual, $accepted, true)) {
                return false;
            }
        }

        return true;
    }
}
