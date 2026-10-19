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
 * Tests that check_authorship resumes an open quiz instead of starting another.
 *
 * @package    plagiarism_dixeo
 * @category   test
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo;

use local_dixeo\external\service_factory;
use local_dixeo\service\assign_ux_service;
use plagiarism_dixeo\authorship\author_storage;
use plagiarism_dixeo\authorship\authorship;
use plagiarism_dixeo\external\check_authorship;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/plagiarism/dixeo/lib.php');

/**
 * An in-progress verification must come back as the same record, without a new API job.
 *
 * @covers \plagiarism_dixeo\external\check_authorship
 * @covers \plagiarism_dixeo\authorship\authorship::find_resumable_record
 */
final class check_authorship_resume_test extends \advanced_testcase {
    protected function tearDown(): void {
        service_factory::reset();
        parent::tearDown();
    }

    /**
     * Build a student, an assignment, a submission and an open authorship quiz.
     *
     * @param string $status Authorship status to store.
     * @return array{assign: \stdClass, student: \stdClass, record: \stdClass}
     */
    private function seed_open_quiz(string $status): array {
        global $CFG, $DB;

        $this->resetAfterTest(true);

        $CFG->enableplagiarism = 1;
        set_config('assignment_feedback_enabled', 1, 'plagiarism_dixeo');
        set_config('grading_enabled', 1, 'plagiarism_dixeo');
        set_config('authorship_enabled', 1, 'plagiarism_dixeo');

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'assignsubmission_onlinetext_enabled' => 1,
        ]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $now = time();
        $submissionid = (int) $DB->insert_record('assign_submission', (object) [
            'assignment' => $assign->id,
            'userid' => $student->id,
            'timecreated' => $now,
            'timemodified' => $now,
            'status' => 'draft',
            'groupid' => 0,
            'attemptnumber' => 0,
            'latest' => 1,
        ]);

        $record = author_storage::create_record(
            (int) $student->id,
            'mod_assign',
            'submission',
            $submissionid,
            [
                'initial_confidence' => 42.0,
                'quiz_data' => [
                    'questions' => [
                        ['id' => 'q0', 'type' => authorship::QUESTION_TYPE_TRUE_FALSE, 'text' => 'Resume?'],
                    ],
                ],
            ]
        );
        author_storage::update_record((int) $record->id, [
            'status' => $status,
            'quiz_responses' => ['q0' => '1'],
            'response_timings' => [
                'started_at' => $now,
                'expires_at' => $now + 180,
                'current_index' => 0,
                'reported' => [],
            ],
        ]);

        return [
            'assign' => $assign,
            'student' => $student,
            'record' => author_storage::get_record((int) $record->id),
        ];
    }

    public function test_check_authorship_returns_the_open_quiz_without_calling_the_api(): void {
        $seed = $this->seed_open_quiz(author_storage::STATUS_VERIFICATION_IN_PROGRESS);
        $this->setUser($seed['student']);

        $mock = $this->createMock(assign_ux_service::class);
        $mock->expects($this->never())->method('submit_authorship_and_wait');
        service_factory::set_test_assign_ux_service($mock);

        $result = check_authorship::execute('mod_assign', 'submission', (int) $seed['assign']->cmid);

        $this->assertSame((int) $seed['record']->id, $result['record_id']);
        $this->assertSame(42.0, $result['score']);
        $this->assertSame('completed', $result['status']);
        $this->assertStringContainsString('authorship_record_id', $result['quiz_html']);
        $this->assertStringContainsString('Resume?', $result['quiz_html']);

        $stored = author_storage::get_record((int) $seed['record']->id);
        $this->assertNotNull($stored);
        $this->assertSame(author_storage::STATUS_VERIFICATION_IN_PROGRESS, $stored->status);
        $this->assertSame(['q0' => '1'], json_decode($stored->quiz_responses, true));
    }

    public function test_a_quiz_that_has_not_been_started_is_also_resumed(): void {
        $seed = $this->seed_open_quiz(author_storage::STATUS_VERIFICATION_REQUIRED);
        $this->setUser($seed['student']);

        $mock = $this->createMock(assign_ux_service::class);
        $mock->expects($this->never())->method('submit_authorship_and_wait');
        service_factory::set_test_assign_ux_service($mock);

        $result = check_authorship::execute('mod_assign', 'submission', (int) $seed['assign']->cmid);

        $this->assertSame((int) $seed['record']->id, $result['record_id']);
        $this->assertStringContainsString('Resume?', $result['quiz_html']);
    }
}
