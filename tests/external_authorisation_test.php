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
 * Unauthorised-path tests for every web service the plugin exposes.
 *
 * @package    plagiarism_dixeo
 * @category   test
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo;

use plagiarism_dixeo\authorship\author_storage;
use plagiarism_dixeo\external\check_authorship;
use plagiarism_dixeo\external\get_grade_draft;
use plagiarism_dixeo\external\mod_assign_grade_submission;
use plagiarism_dixeo\external\mod_assign_pre_submission;
use plagiarism_dixeo\external\verify_authorship;

/**
 * Every endpoint refuses callers who lack the session, enrolment or capability it requires.
 *
 * These are the paths an attacker would take: guessing an id, reusing a capability from
 * another course, or asking for someone else's data from inside the same course.
 *
 * @covers \plagiarism_dixeo\external\assign_access
 * @covers \plagiarism_dixeo\external\check_authorship
 * @covers \plagiarism_dixeo\external\get_grade_draft
 * @covers \plagiarism_dixeo\external\mod_assign_grade_submission
 * @covers \plagiarism_dixeo\external\mod_assign_pre_submission
 * @covers \plagiarism_dixeo\external\verify_authorship
 */
final class external_authorisation_test extends \advanced_testcase {
    /** @var \stdClass Course holding the assignment under test. */
    private $course;

    /** @var \stdClass Assignment used by the tests. */
    private $assign;

    /** @var \stdClass Student enrolled on the course. */
    private $student;

    /** @var \stdClass Teacher enrolled on the course. */
    private $teacher;

    /** @var \stdClass User with no connection to the course. */
    private $outsider;

    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);

        $this->course = $this->getDataGenerator()->create_course();
        $this->assign = $this->getDataGenerator()->create_module('assign', ['course' => $this->course->id]);
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->teacher = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');
        $this->outsider = $this->getDataGenerator()->create_user();
    }

    /**
     * Create a submission for a user in the test assignment.
     *
     * @param int $userid Owner of the submission.
     * @return int Submission id.
     */
    private function create_submission(int $userid): int {
        global $DB;

        $now = time();

        return (int) $DB->insert_record('assign_submission', (object) [
            'assignment' => $this->assign->id,
            'userid' => $userid,
            'timecreated' => $now,
            'timemodified' => $now,
            'status' => 'submitted',
            'groupid' => 0,
            'attemptnumber' => 0,
            'latest' => 1,
        ]);
    }

    public function test_pre_submission_refuses_a_user_outside_the_course(): void {
        $this->setUser($this->outsider);

        $this->expectException(\require_login_exception::class);
        mod_assign_pre_submission::execute((int) $this->assign->cmid);
    }

    public function test_pre_submission_refuses_a_course_module_from_another_course(): void {
        $othercourse = $this->getDataGenerator()->create_course();
        $otherassign = $this->getDataGenerator()->create_module('assign', ['course' => $othercourse->id]);
        $this->setUser($this->student);

        $this->expectException(\require_login_exception::class);
        mod_assign_pre_submission::execute((int) $otherassign->cmid);
    }

    public function test_grade_submission_refuses_a_student(): void {
        $this->setUser($this->student);

        $this->expectException(\required_capability_exception::class);
        mod_assign_grade_submission::execute((int) $this->assign->cmid, (int) $this->student->id);
    }

    public function test_grade_submission_refuses_a_teacher_from_another_course(): void {
        $othercourse = $this->getDataGenerator()->create_course();
        $outsideteacher = $this->getDataGenerator()->create_and_enrol($othercourse, 'editingteacher');
        $this->setUser($outsideteacher);

        $this->expectException(\moodle_exception::class);
        mod_assign_grade_submission::execute((int) $this->assign->cmid, (int) $this->student->id);
    }

    public function test_check_authorship_refuses_a_user_outside_the_course(): void {
        $this->setUser($this->outsider);

        $this->expectException(\require_login_exception::class);
        check_authorship::execute('mod_assign', 'submission', (int) $this->assign->cmid);
    }

    public function test_verify_authorship_refuses_another_students_record(): void {
        $owner = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $submissionid = $this->create_submission((int) $owner->id);
        $record = author_storage::create_record(
            (int) $owner->id,
            'mod_assign',
            'submission',
            $submissionid,
            [
                'initial_confidence' => 30.0,
                'quiz_data' => ['questions' => [['id' => 'q1', 'type' => 'true_false', 'text' => 'One?']]],
            ]
        );
        $this->setUser($this->student);

        $result = verify_authorship::execute((int) $record->id, json_encode(['q1' => 'true']));

        $this->assertFalse($result['success']);
        $this->assertSame('failed', $result['status']);

        // The other student's record is untouched.
        $stored = author_storage::get_record((int) $record->id);
        $this->assertNull($stored->quiz_responses);
        $this->assertSame(author_storage::STATUS_VERIFICATION_REQUIRED, $stored->status);
    }

    public function test_verify_authorship_refuses_an_unknown_record(): void {
        $this->setUser($this->student);

        $result = verify_authorship::execute(999999, '{}');

        $this->assertFalse($result['success']);
        $this->assertSame('failed', $result['status']);
    }

    public function test_grade_draft_refuses_a_user_outside_the_course(): void {
        $this->setUser($this->outsider);

        $this->expectException(\require_login_exception::class);
        get_grade_draft::execute((int) $this->assign->cmid, (int) $this->student->id);
    }

    public function test_a_teacher_may_reach_the_endpoints_their_role_allows(): void {
        $this->setUser($this->teacher);

        $result = get_grade_draft::execute((int) $this->assign->cmid, (int) $this->student->id);

        $this->assertFalse($result['hasdraft']);
    }
}
