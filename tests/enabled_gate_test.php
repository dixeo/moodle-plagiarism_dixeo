<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Plagiarism visibility when Dixeo is off.
 *
 * @package    plagiarism_dixeo
 * @category   test
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo;

use plagiarism_dixeo\authorship\author_storage;
use plagiarism_dixeo\hook\output;
use plagiarism_dixeo\task\analyze_submission;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/plagiarism/dixeo/lib.php');

/**
 * Scores, settings, and submission analysis stay out when Dixeo is off.
 *
 * @package    plagiarism_dixeo
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \plagiarism_plugin_dixeo::get_links
 * @covers     \plagiarism_plugin_dixeo::print_disclosure
 * @covers     \plagiarism_plugin_dixeo::enabled_for_course
 * @covers     ::plagiarism_dixeo_coursemodule_standard_elements
 * @covers     \plagiarism_dixeo\observer::submission_submitted
 * @covers     \plagiarism_dixeo\hook\output::amd_calls_for_url
 */
final class enabled_gate_test extends \advanced_testcase {
    /**
     * Panels and the student disclosure are omitted while the course is off.
     */
    public function test_links_and_disclosure_are_empty_when_the_course_is_off(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course, $cm, $student, $submissionid] = $this->assignment_with_analysis();
        set_config('student_disclosure', 'Your work will be analysed.', 'plagiarism_dixeo');
        author_storage::create_record(
            (int) $student->id,
            'mod_assign',
            'submission',
            $submissionid,
            ['initial_confidence' => 82.5, 'initial_explanation' => 'Consistent with earlier work.']
        );
        $plugin = new \plagiarism_plugin_dixeo();
        $links = ['cmid' => (int) $cm->id, 'userid' => (int) $student->id, 'submissionid' => $submissionid];

        \local_dixeo\external\service_factory::get_settings_config()->set(
            'local_dixeo',
            'enabled',
            \context_course::instance($course->id),
            '0'
        );
        $this->setUser($student);
        $this->assertSame('', $plugin->get_links($links));
        $this->assertSame('', $plugin->print_disclosure((int) $cm->id));

        $this->setAdminUser();
        \local_dixeo\external\service_factory::get_settings_config()->set(
            'local_dixeo',
            'enabled',
            \context_course::instance($course->id),
            '1'
        );
        $this->setUser($student);
        $this->assertStringContainsString('82.5', $plugin->get_links($links));
        $this->assertStringContainsString('Your work will be analysed.', $plugin->print_disclosure((int) $cm->id));
    }

    /**
     * A course stored as On does not show the disclosure while the Dixeo layer is off.
     */
    public function test_disclosure_stays_empty_when_dixeo_is_off(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        [$course, $cm] = $this->assignment_with_analysis();
        set_config('student_disclosure', 'Your work will be analysed.', 'plagiarism_dixeo');
        $config = \local_dixeo\external\service_factory::get_settings_config();
        $config->set('local_dixeo', 'enabled', \context_system::instance(), '0');
        $config->set('local_dixeo', 'enabled', \context_course::instance($course->id), '1');

        $plugin = new \plagiarism_plugin_dixeo();
        $this->assertSame('', $plugin->print_disclosure((int) $cm->id));
    }

    /**
     * The assignment form keeps the settings button when the course master switch is off.
     */
    public function test_assignment_form_keeps_the_settings_button_when_the_course_is_off(): void {
        global $CFG, $PAGE;

        $this->resetAfterTest();
        $this->setAdminUser();
        require_once($CFG->dirroot . '/mod/assign/mod_form.php');
        [$course, $cm] = $this->assignment_with_analysis();
        \local_dixeo\external\service_factory::get_settings_config()->set(
            'local_dixeo',
            'enabled',
            \context_course::instance($course->id),
            '0'
        );

        $page = new \moodle_page();
        $page->set_context(\context_course::instance($course->id));
        $page->set_course($course);
        $page->set_url(new \moodle_url('/course/modedit.php', ['update' => $cm->id]));
        $PAGE = $page;
        $GLOBALS['COURSE'] = $course;

        $form = $this->assignment_form($course, $cm);
        $property = new \ReflectionProperty($form, '_form');
        $property->setAccessible(true);
        $quickform = $property->getValue($form);
        $this->assertTrue($quickform->elementExists('plagiarism_dixeo'));
        $this->assertTrue($quickform->elementExists('plagiarism_dixeo_settings_intro'));
        $intro = $quickform->getElement('plagiarism_dixeo_settings_intro');
        $this->assertStringContainsString(get_string('settings_form_label', 'plagiarism_dixeo'), $intro->toHtml());
        $this->assertTrue($quickform->elementExists('plagiarism_dixeo_settings_open'));
        $button = $quickform->getElement('plagiarism_dixeo_settings_open');
        $this->assertSame('feature:assignment', $button->getAttribute('data-initial'));
        $this->assertSame((string) \context_module::instance($cm->id)->id, $button->getAttribute('data-contextid'));
        $this->assertNotEmpty($button->getAttribute('data-activity-name'));
        $this->assertNotEmpty($button->getAttribute('data-course-name'));
        $this->assertFalse($quickform->elementExists('enable_auto_grading'));
        $this->assertFalse($quickform->elementExists('authorship_threshold'));
    }

    /**
     * A submission does not queue analysis, and the assign page loads no scripts, while the course is off.
     */
    public function test_submission_does_not_start_analysis_when_the_course_is_off(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();
        [$course, $cm, $student, $submissionid] = $this->assignment_with_analysis();
        \local_dixeo\external\service_factory::get_settings_config()->set(
            'local_dixeo',
            'enabled',
            \context_course::instance($course->id),
            '0'
        );

        $url = new \moodle_url('/mod/assign/view.php', ['id' => $cm->id]);
        $this->assertSame([], output::amd_calls_for_url($url));

        \mod_assign\event\assessable_submitted::create([
            'context' => \context_module::instance($cm->id),
            'courseid' => $course->id,
            'userid' => $student->id,
            'objectid' => $submissionid,
            'other' => ['submission_editable' => false],
        ])->trigger();
        $this->assertSame(0, $DB->count_records('task_adhoc', ['classname' => '\\' . analyze_submission::class]));
    }

    /**
     * Assignment with Dixeo analysis enabled and one submitted attempt.
     *
     * @return array{0: \stdClass, 1: \stdClass, 2: \stdClass, 3: int}
     */
    private function assignment_with_analysis(): array {
        global $CFG, $DB;

        $CFG->enableplagiarism = 1;
        set_config('assignment_feedback_enabled', 1, 'plagiarism_dixeo');
        set_config('authorship_enabled', 1, 'plagiarism_dixeo');
        set_config('grading_enabled', 1, 'plagiarism_dixeo');
        set_config('assign_trigger', 'submit', 'plagiarism_dixeo');

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('assign', $assign->id, $course->id, false, MUST_EXIST);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $now = time();
        $submissionid = (int) $DB->insert_record('assign_submission', (object) [
            'assignment' => $assign->id,
            'userid' => $student->id,
            'timecreated' => $now,
            'timemodified' => $now,
            'status' => 'submitted',
            'groupid' => 0,
            'attemptnumber' => 0,
            'latest' => 1,
        ]);

        return [$course, $cm, $student, $submissionid];
    }

    /**
     * Assignment settings form for the course module.
     *
     * @param \stdClass $course
     * @param \stdClass $cm
     * @return \mod_assign_mod_form
     */
    private function assignment_form(\stdClass $course, \stdClass $cm): \mod_assign_mod_form {
        global $DB;

        $current = $DB->get_record('assign', ['id' => $cm->instance], '*', MUST_EXIST);
        $current->coursemodule = $cm->id;
        $current->instance = $cm->instance;
        $current->modulename = 'assign';
        return new \mod_assign_mod_form($current, 0, $cm, $course);
    }
}
