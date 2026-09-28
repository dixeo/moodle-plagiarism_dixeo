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
 * Site settings form tests for plagiarism_dixeo.
 *
 * @package    plagiarism_dixeo
 * @category   test
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/plagiarism/dixeo/classes/setup_form.php');

/**
 * Settings must default to off, reject out-of-range values and clear when unticked.
 *
 * @covers \plagiarism_dixeo_setup_form
 */
final class setup_form_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Run the form's validation over a full set of valid values with overrides applied.
     *
     * @param array $overrides Values replacing the valid defaults.
     * @return array Validation errors keyed by field name.
     */
    private function validate(array $overrides = []): array {
        $form = new \plagiarism_dixeo_setup_form();
        $data = array_merge(\plagiarism_dixeo_setup_form::get_config_fields(), $overrides);

        return $form->validation($data, []);
    }

    public function test_the_plugin_is_off_until_an_admin_turns_it_on(): void {
        $defaults = \plagiarism_dixeo_setup_form::get_config_fields();

        $this->assertSame(0, $defaults['enabled']);
        $this->assertSame(0, $defaults['enable_mod_assign']);
        $this->assertSame(0, $defaults['authorship_enabled']);
        $this->assertSame(0, $defaults['verification_test']);
        $this->assertSame('submit', $defaults['assign_trigger']);
        $this->assertSame('async', $defaults['evaluation_mode']);
    }

    public function test_evaluation_mode_is_accepted(): void {
        $this->assertArrayHasKey('evaluation_mode', \plagiarism_dixeo_setup_form::get_config_fields());
        $this->assertSame([], $this->validate(['evaluation_mode' => 'sync']));
        $this->assertSame([], $this->validate(['evaluation_mode' => 'async']));
        $this->assertArrayHasKey('evaluation_mode', $this->validate(['evaluation_mode' => 'whenever']));
    }

    public function test_valid_settings_pass_validation(): void {
        $this->assertSame([], $this->validate());
    }

    public function test_the_confidence_threshold_stays_within_the_scale(): void {
        $this->assertArrayHasKey('authorship_threshold', $this->validate(['authorship_threshold' => 101]));
        $this->assertArrayHasKey('authorship_threshold', $this->validate(['authorship_threshold' => -1]));
        $this->assertSame([], $this->validate(['authorship_threshold' => 0]));
        $this->assertSame([], $this->validate(['authorship_threshold' => 100]));
    }

    public function test_quiz_times_are_bounded(): void {
        $this->assertArrayHasKey('quiz_total_time', $this->validate(['quiz_total_time' => -1]));
        $this->assertArrayHasKey(
            'quiz_time_open',
            $this->validate(['quiz_time_open' => \plagiarism_dixeo_setup_form::MAX_QUIZ_TIME + 1])
        );
        $this->assertSame([], $this->validate(['quiz_total_time' => 0]));
    }

    public function test_the_next_button_delay_is_bounded(): void {
        $this->assertArrayHasKey('next_button_delay_seconds', $this->validate(['next_button_delay_seconds' => -1]));
        $this->assertArrayHasKey('next_button_delay_seconds', $this->validate(['next_button_delay_seconds' => 600]));
        $this->assertSame([], $this->validate(['next_button_delay_seconds' => 0]));
    }

    public function test_values_outside_a_settings_allowed_list_are_rejected(): void {
        $this->assertArrayHasKey('assign_trigger', $this->validate(['assign_trigger' => 'whenever']));
        $this->assertArrayHasKey('enabled', $this->validate(['enabled' => 2]));
        $this->assertArrayHasKey('authorship_count_open', $this->validate(['authorship_count_open' => 9]));
    }

    public function test_clearing_a_checkbox_stores_zero(): void {
        foreach (\plagiarism_dixeo_setup_form::get_config_fields() as $field => $default) {
            set_config($field, 1, 'plagiarism_dixeo');
        }

        // Stands in for settings.php handling a submission with every checkbox cleared.
        $submitted = (object) ['enabled' => 1, 'authorship_threshold' => 80];
        foreach (\plagiarism_dixeo_setup_form::get_config_fields() as $field => $default) {
            set_config($field, $submitted->$field ?? $default, 'plagiarism_dixeo');
        }

        $stored = get_config('plagiarism_dixeo');
        $this->assertSame('0', $stored->allow_backwards_navigation);
        $this->assertSame('0', $stored->enable_mod_assign);
        $this->assertSame('0', $stored->verification_test);
        $this->assertSame('1', $stored->enabled);
        $this->assertSame('80', $stored->authorship_threshold);
    }
}
