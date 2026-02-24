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
 * External API: check authorship of submission text before submit.
 *
 * @package    plagiarism_dixeo
 * @author     Josemaria Bolanos <admin@mako.digital>
 * @copyright  2026 Dixeo.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo\external;

use context_user;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use plagiarism_dixeo\local\authorship;

defined('MOODLE_INTERNAL') || die();

/**
 * Check authorship: returns score, threshold, optional record_id and quiz_html.
 */
class check_authorship extends external_api {

    /**
     * Parameters: component, area, and area-specific id (e.g. mod_assign / submission / cmid).
     *
     * @return \core_external\external_function_parameters
     */
    public static function execute_parameters(): \core_external\external_function_parameters {
        return new external_function_parameters([
            'component' => new external_value(PARAM_COMPONENT, 'Component (e.g. mod_assign).'),
            'area'      => new external_value(PARAM_ALPHAEXT, 'Area within the component (e.g. submission).'),
            'id'        => new external_value(PARAM_INT, 'Area-specific id (e.g. course module id for mod_assign/submission).'),
        ]);
    }

    /**
     * Run authorship check on the content resolved by (component, area, id); return score, threshold, and quiz if required.
     *
     * @param string $component e.g. 'mod_assign'
     * @param string $area e.g. 'submission'
     * @param int $id Area-specific id (e.g. course module id so submission for current user is resolved)
     * @return array{score: float, threshold: float, record_id: int, quiz_html: string}
     * @throws \moodle_exception If capability check fails.
     */
    public static function execute(string $component, string $area, int $id): array {
        global $USER;

        $params = self::validate_parameters(self::execute_parameters(), [
            'component' => $component,
            'area'      => $area,
            'id'        => $id,
        ]);

        $context = context_user::instance($USER->id);
        require_capability('plagiarism/dixeo:mod_assign_pre_submission', $context);

        if ($params['id'] <= 0) {
            return self::empty_result();
        }

        $content = authorship::get_content_for_check(
            $params['component'],
            $params['area'],
            $params['id']
        );

        $contextarr = [
            'userid'    => (int) $USER->id,
            'component' => $params['component'],
            'area'      => $params['area'],
            'itemid'    => $content['itemid'],
        ];
        if (!empty($content['files']) && is_array($content['files'])) {
            $contextarr['uploadfiles'] = $content['files'];
        }
        $result = authorship::check($content['text'], $contextarr);

        $recordid = 0;
        $quizhtml = '';
        if (isset($result['record']) && $result['record'] !== null && !empty($result['record']->id)) {
            $recordid = (int) $result['record']->id;
            $quizhtml = $result['quiz_html'] ?? '';
        }

        return [
            'score'     => (float) $result['score'],
            'threshold' => (float) authorship::get_confidence_threshold(),
            'record_id' => $recordid,
            'quiz_html' => $quizhtml,
        ];
    }

    /**
     * Empty result when id invalid (no quiz, score 100).
     *
     * @return array
     */
    protected static function empty_result(): array {
        return [
            'score'     => 100.0,
            'threshold' => (float) authorship::get_confidence_threshold(),
            'record_id' => 0,
            'quiz_html' => '',
        ];
    }

    /**
     * Return value: score, threshold, record_id, quiz_html.
     *
     * @return \core_external\external_single_structure
     */
    public static function execute_returns(): \core_external\external_single_structure {
        return new external_single_structure([
            'score'     => new external_value(PARAM_FLOAT, 'Authorship confidence 0-100.'),
            'threshold' => new external_value(PARAM_FLOAT, 'Threshold below which quiz is required.'),
            'record_id' => new external_value(PARAM_INT, 'Authorship record id if quiz was generated, else 0.'),
            'quiz_html' => new external_value(PARAM_RAW, 'Quiz form HTML if quiz required, else empty.'),
        ], 'Authorship check result.');
    }
}
