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
 * Tests for removing Dixeo rows when their submission or assignment is removed.
 *
 * @package    plagiarism_dixeo
 * @category   test
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo;

use plagiarism_dixeo\authorship\author_storage;
use plagiarism_dixeo\feedback\grade_feedback_storage;
use plagiarism_dixeo\feedback\presubmission_feedback_storage;
use plagiarism_dixeo\grading\autograde_enabler;
use plagiarism_dixeo\task\cleanup_orphan_records;

/**
 * Analysis rows never outlive the submission or assignment they describe.
 *
 * @covers ::plagiarism_dixeo_pre_course_module_delete
 * @covers \plagiarism_dixeo\task\cleanup_orphan_records
 * @covers \plagiarism_dixeo\observer::submission_removed
 */
final class orphan_cleanup_test extends \advanced_testcase {
    /** @var string[] Tables holding analyses of submissions. */
    private const TABLES = [author_storage::TABLE, presubmission_feedback_storage::TABLE, grade_feedback_storage::TABLE];

    /** @var \stdClass Course. */
    private \stdClass $course;

    /** @var \stdClass Student. */
    private \stdClass $student;

    protected function setUp(): void {
        global $CFG;

        parent::setUp();
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/plagiarism/dixeo/lib.php');
        require_once($CFG->dirroot . '/course/lib.php');

        $this->course = $this->getDataGenerator()->create_course();
        $this->student = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
    }

    /**
     * Create an assignment.
     *
     * @return \stdClass Assign module record with cmid.
     */
    private function create_assign(): \stdClass {
        return $this->getDataGenerator()->create_module('assign', ['course' => $this->course->id]);
    }

    /**
     * Create a submission and a row in each analysis table for it.
     *
     * @param \stdClass $assign Assign module record.
     * @return int Submission id.
     */
    private function create_analysis(\stdClass $assign): int {
        global $DB;

        $userid = (int) $this->student->id;
        $submissionid = (int) $DB->insert_record('assign_submission', (object) [
            'assignment' => $assign->id,
            'userid' => $userid,
            'status' => 'submitted',
            'attemptnumber' => 0,
            'latest' => 1,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
        author_storage::create_record($userid, 'mod_assign', 'submission', $submissionid, ['initial_confidence' => 80.0]);
        presubmission_feedback_storage::create_record($userid, 'mod_assign', 'submission', $submissionid, '<p>Fine.</p>');
        grade_feedback_storage::create_record($userid, 'mod_assign', 'submission', $submissionid, [
            'gradingmethod' => 'simple',
            'grade' => 70.0,
        ]);

        return $submissionid;
    }

    /**
     * Number of analysis rows for a submission across the three tables.
     *
     * @param int $submissionid Submission id.
     * @return int
     */
    private function count_analysis(int $submissionid): int {
        global $DB;

        $total = 0;
        foreach (self::TABLES as $table) {
            $total += $DB->count_records($table, ['itemid' => $submissionid]);
        }
        return $total;
    }

    public function test_deleting_an_assignment_removes_its_rows_only(): void {
        $assign = $this->create_assign();
        $other = $this->create_assign();
        $submissionid = $this->create_analysis($assign);
        $otherid = $this->create_analysis($other);
        autograde_enabler::record((int) $assign->cmid, (int) get_admin()->id);
        autograde_enabler::record((int) $other->cmid, (int) get_admin()->id);

        course_delete_module((int) $assign->cmid);

        $this->assertSame(0, $this->count_analysis($submissionid));
        $this->assertSame(0, autograde_enabler::get_userid((int) $assign->cmid));
        $this->assertSame(3, $this->count_analysis($otherid));
        $this->assertSame((int) get_admin()->id, autograde_enabler::get_userid((int) $other->cmid));
    }

    public function test_sweep_removes_rows_left_by_a_deleted_course(): void {
        global $DB;

        $assign = $this->create_assign();
        $submissionid = $this->create_analysis($assign);
        autograde_enabler::record((int) $assign->cmid, (int) get_admin()->id);
        $keptcourse = $this->getDataGenerator()->create_course();
        $kept = $this->getDataGenerator()->create_module('assign', ['course' => $keptcourse->id]);
        $keptid = $this->create_analysis($kept);
        autograde_enabler::record((int) $kept->cmid, (int) get_admin()->id);

        delete_course($this->course, false);
        (new cleanup_orphan_records())->execute();

        $this->assertSame(0, $this->count_analysis($submissionid));
        $this->assertFalse($DB->record_exists(autograde_enabler::TABLE, ['cmid' => (int) $assign->cmid]));
        $this->assertSame(3, $this->count_analysis($keptid));
        $this->assertTrue($DB->record_exists(autograde_enabler::TABLE, ['cmid' => (int) $kept->cmid]));
    }

    public function test_sweep_removes_rows_whose_submission_is_gone(): void {
        global $DB;

        $assign = $this->create_assign();
        $gone = $this->create_analysis($assign);
        $DB->delete_records('assign_submission', ['id' => $gone]);
        $other = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->student = $other;
        $kept = $this->create_analysis($assign);

        (new cleanup_orphan_records())->execute();

        $this->assertSame(0, $this->count_analysis($gone));
        $this->assertSame(3, $this->count_analysis($kept));
    }

    public function test_removing_a_submission_deletes_its_analysis(): void {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $assign = $this->create_assign();
        $submissionid = $this->create_analysis($assign);
        $cm = get_coursemodule_from_id('assign', $assign->cmid, 0, false, MUST_EXIST);
        $instance = new \assign(\context_module::instance($cm->id), $cm, $this->course);
        $submission = $DB->get_record('assign_submission', ['id' => $submissionid], '*', MUST_EXIST);

        \mod_assign\event\submission_removed::create_from_submission($instance, $submission)->trigger();

        $this->assertSame(0, $this->count_analysis($submissionid));
    }
}
