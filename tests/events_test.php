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

/**
 * Every event must be triggerable, describe itself and stay free of scores.
 *
 * @covers \plagiarism_dixeo\event\analysis_requested
 * @covers \plagiarism_dixeo\event\authorship_checked
 * @covers \plagiarism_dixeo\event\grade_draft_applied
 * @covers \plagiarism_dixeo\event\grade_draft_created
 * @covers \plagiarism_dixeo\event\verification_started
 * @covers \plagiarism_dixeo\event\verification_submitted
 */
final class events_test extends \advanced_testcase {
    /** @var \context_module Context the events are raised in. */
    private $context;

    /** @var \stdClass Student the events relate to. */
    private $student;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $course = $generator->create_course();
        $assign = $generator->create_module('assign', ['course' => $course->id]);
        $cm = get_coursemodule_from_instance('assign', $assign->id, 0, false, MUST_EXIST);

        $this->context = \context_module::instance($cm->id);
        $this->student = $generator->create_and_enrol($course, 'student');
        $this->setAdminUser();
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
