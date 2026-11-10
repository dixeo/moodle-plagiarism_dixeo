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
 * Event tests for plagiarism_dixeo.
 *
 * @package    plagiarism_dixeo
 * @category   test
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo;

use local_dixeo\dto\operation_result;
use local_dixeo\external\service_factory;
use local_dixeo\service\assign_ux_service;
use plagiarism_dixeo\authorship\author_storage;
use plagiarism_dixeo\event\analysis_requested;
use plagiarism_dixeo\event\authorship_checked;
use plagiarism_dixeo\external\check_authorship;
use plagiarism_dixeo\external\mod_assign_grade_submission;
use plagiarism_dixeo\external\mod_assign_pre_submission;

/**
 * Every event must be triggerable, describe itself and stay free of scores.
 *
 * @covers \plagiarism_dixeo\event\analysis_requested
 * @covers \plagiarism_dixeo\event\authorship_checked
 * @covers \plagiarism_dixeo\event\grade_draft_applied
 * @covers \plagiarism_dixeo\event\grade_draft_created
 * @covers \plagiarism_dixeo\event\verification_started
 * @covers \plagiarism_dixeo\event\verification_submitted
 * @covers \plagiarism_dixeo\external\mod_assign_pre_submission
 * @covers \plagiarism_dixeo\external\mod_assign_grade_submission
 * @covers \plagiarism_dixeo\external\check_authorship
 */
final class events_test extends \advanced_testcase {
    /** @var \context_module Context the events are raised in. */
    private $context;

    /** @var \stdClass Student the events relate to. */
    private $student;

    /** @var \stdClass Course holding the assignment. */
    private $course;

    /** @var \stdClass Assignment the events are raised in. */
    private $assign;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $assign = $generator->create_module('assign', [
            'course' => $course->id,
            'assignsubmission_onlinetext_enabled' => 1,
        ]);
        $cm = get_coursemodule_from_instance('assign', $assign->id, 0, false, MUST_EXIST);

        $this->course = $course;
        $this->assign = $assign;
        $this->context = \context_module::instance($cm->id);
        $this->student = $generator->create_and_enrol($course, 'student');
        $this->setAdminUser();
    }

    protected function tearDown(): void {
        service_factory::reset();
        parent::tearDown();
    }

    /**
     * Switch Dixeo features on and give the student a submission.
     *
     * @return int Submission id.
     */
    private function enable_and_submit(): int {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/plagiarism/dixeo/lib.php');
        $CFG->enableplagiarism = 1;
        set_config('assignment_feedback_enabled', 1, 'plagiarism_dixeo');
        set_config('authorship_enabled', 1, 'plagiarism_dixeo');
        set_config('grading_enabled', 1, 'plagiarism_dixeo');

        return (int) $DB->insert_record('assign_submission', (object) [
            'assignment' => $this->assign->id,
            'userid' => $this->student->id,
            'status' => 'draft',
            'attemptnumber' => 0,
            'latest' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Events of one class captured while running a callable.
     *
     * @param string $class Event class.
     * @param callable $call Code to run.
     * @return \core\event\base[]
     */
    private function capture(string $class, callable $call): array {
        $sink = $this->redirectEvents();
        $call();
        $events = array_values(array_filter($sink->get_events(), static fn($event): bool => $event instanceof $class));
        $sink->close();
        return $events;
    }

    public function test_student_analyze_logs_the_request(): void {
        $submissionid = $this->enable_and_submit();
        $mock = $this->createMock(assign_ux_service::class);
        $mock->method('submit_review')->willReturn(operation_result::pending('job-review', 'pending', 0));
        service_factory::set_test_assign_ux_service($mock);
        $this->setUser($this->student);

        $events = $this->capture(
            analysis_requested::class,
            fn() => mod_assign_pre_submission::execute((int) $this->assign->cmid)
        );

        $this->assertCount(1, $events);
        $this->assertSame((int) $this->student->id, (int) $events[0]->relateduserid);
        $this->assertSame($submissionid, $events[0]->other['itemid']);
        $this->assertSame('manual', $events[0]->other['trigger']);
    }

    public function test_teacher_assist_logs_the_request_against_the_student(): void {
        $submissionid = $this->enable_and_submit();
        $teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $mock = $this->createMock(assign_ux_service::class);
        $mock->method('submit_grade')->willReturn(operation_result::pending('job-grade', 'pending', 0));
        service_factory::set_test_assign_ux_service($mock);
        $this->setUser($teacher);

        $events = $this->capture(
            analysis_requested::class,
            fn() => mod_assign_grade_submission::execute((int) $this->assign->cmid, (int) $this->student->id)
        );

        $this->assertCount(1, $events);
        $this->assertSame((int) $teacher->id, (int) $events[0]->userid);
        $this->assertSame((int) $this->student->id, (int) $events[0]->relateduserid);
        $this->assertSame($submissionid, $events[0]->other['itemid']);
    }

    public function test_authorship_check_logs_the_stored_result(): void {
        $submissionid = $this->enable_and_submit();
        $record = author_storage::create_record(
            (int) $this->student->id,
            'mod_assign',
            'submission',
            $submissionid,
            ['initial_confidence' => 90.0]
        );
        author_storage::update_record((int) $record->id, ['status' => author_storage::STATUS_FAILED]);
        $mock = $this->createMock(assign_ux_service::class);
        $mock->method('submit_authorship_and_wait')
            ->willReturn(new operation_result(true, 'job-confidence', ['confidence' => 90]));
        $mock->method('map_authorship_result')->willReturn(['confidence' => 90.0, 'explanation' => '']);
        $mock->method('apply_authorship_confidence')->willReturn(author_storage::get_record((int) $record->id));
        service_factory::set_test_assign_ux_service($mock);
        $this->setUser($this->student);

        $events = $this->capture(
            authorship_checked::class,
            fn() => check_authorship::execute('mod_assign', 'submission', (int) $this->assign->cmid)
        );

        $this->assertCount(1, $events);
        $this->assertSame((int) $record->id, (int) $events[0]->objectid);
        $this->assertSame((int) $this->student->id, (int) $events[0]->relateduserid);
    }

    /**
     * Event class mapped to the extra data it requires.
     *
     * @return array<string, array>
     */
    public static function event_provider(): array {
        return [
            'analysis_requested' => [
                \plagiarism_dixeo\event\analysis_requested::class,
                ['itemid' => 55, 'trigger' => 'submit'],
                false,
            ],
            'authorship_checked' => [
                \plagiarism_dixeo\event\authorship_checked::class,
                ['status' => 'verification_required'],
                true,
            ],
            'verification_started' => [
                \plagiarism_dixeo\event\verification_started::class,
                [],
                true,
            ],
            'verification_submitted' => [
                \plagiarism_dixeo\event\verification_submitted::class,
                ['expired' => true],
                true,
            ],
            'grade_draft_created' => [
                \plagiarism_dixeo\event\grade_draft_created::class,
                [],
                true,
            ],
            'grade_draft_applied' => [
                \plagiarism_dixeo\event\grade_draft_applied::class,
                [],
                true,
            ],
        ];
    }

    /**
     * Each event reaches the log with its context, related user and description intact.
     *
     * @dataProvider event_provider
     * @param string $class Event class under test.
     * @param array $other Extra data the event requires.
     * @param bool $hasobject Whether the event targets a stored record.
     */
    public function test_event_is_triggerable_and_described(string $class, array $other, bool $hasobject): void {
        $params = [
            'context' => $this->context,
            'relateduserid' => (int) $this->student->id,
            'other' => $other,
        ];
        if ($hasobject) {
            $params['objectid'] = 42;
        }

        $sink = $this->redirectEvents();
        $class::create($params)->trigger();
        $events = $sink->get_events();

        $this->assertCount(1, $events);
        $event = reset($events);
        $this->assertInstanceOf($class, $event);
        $this->assertEquals($this->context->id, $event->contextid);
        $this->assertEquals((int) $this->student->id, $event->relateduserid);
        $this->assertNotEmpty($event->get_description());
        $this->assertNotEmpty($class::get_name());
    }

    public function test_events_require_a_module_context(): void {
        $this->expectException(\coding_exception::class);

        \plagiarism_dixeo\event\verification_started::create([
            'context' => \context_user::instance((int) $this->student->id),
            'relateduserid' => (int) $this->student->id,
            'objectid' => 42,
        ])->trigger();
    }

    public function test_events_require_a_related_user(): void {
        $this->expectException(\coding_exception::class);

        \plagiarism_dixeo\event\authorship_checked::create([
            'context' => $this->context,
            'objectid' => 42,
            'other' => ['status' => 'complete'],
        ])->trigger();
    }

    public function test_descriptions_do_not_leak_confidence_scores(): void {
        $event = \plagiarism_dixeo\event\authorship_checked::create([
            'context' => $this->context,
            'relateduserid' => (int) $this->student->id,
            'objectid' => 42,
            'other' => ['status' => 'complete'],
        ]);

        $this->assertStringNotContainsString('confidence', strtolower($event->get_description()));
        $this->assertArrayNotHasKey('initial_confidence', $event->other);
        $this->assertArrayNotHasKey('final_confidence', $event->other);
    }
}
