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
 * Privacy provider tests for plagiarism_dixeo.
 *
 * @package    plagiarism_dixeo
 * @category   test
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use plagiarism_dixeo\authorship\author_storage;
use plagiarism_dixeo\feedback\grade_feedback_storage;
use plagiarism_dixeo\feedback\presubmission_feedback_storage;
use plagiarism_dixeo\privacy\provider;

/**
 * Privacy API tests covering all three personal data tables at activity context.
 *
 * @covers \plagiarism_dixeo\privacy\provider
 */
final class privacy_provider_test extends \core_privacy\tests\provider_testcase {
    /** @var \stdClass Course holding both assignments. */
    private $course;

    /** @var \stdClass First assignment. */
    private $assign;

    /** @var \stdClass Second assignment, used to prove per-activity isolation. */
    private $otherassign;

    /** @var \context_module Context of the first assignment. */
    private $context;

    /** @var \context_module Context of the second assignment. */
    private $othercontext;

    /** @var \stdClass Student with data in both assignments. */
    private $student;

    /** @var \stdClass Second student with data in the first assignment. */
    private $classmate;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();

        $generator = $this->getDataGenerator();
        $this->course = $generator->create_course();
        $this->assign = $generator->create_module('assign', ['course' => $this->course->id]);
        $this->otherassign = $generator->create_module('assign', ['course' => $this->course->id]);

        $cm = get_coursemodule_from_instance('assign', $this->assign->id, 0, false, MUST_EXIST);
        $othercm = get_coursemodule_from_instance('assign', $this->otherassign->id, 0, false, MUST_EXIST);
        $this->context = \context_module::instance($cm->id);
        $this->othercontext = \context_module::instance($othercm->id);

        $this->student = $generator->create_and_enrol($this->course, 'student');
        $this->classmate = $generator->create_and_enrol($this->course, 'student');
    }

    /**
     * Create a submission and a row in each Dixeo table for it.
     *
     * @param int $assignid Assignment instance id.
     * @param int $userid Student user id.
     * @return int Submission id.
     */
    private function create_data(int $assignid, int $userid): int {
        global $DB;

        $now = time();
        $submissionid = (int) $DB->insert_record('assign_submission', (object) [
            'assignment' => $assignid,
            'userid' => $userid,
            'status' => 'draft',
            'attemptnumber' => 0,
            'latest' => 1,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);

        author_storage::create_record($userid, 'mod_assign', 'submission', $submissionid, [
            'initial_confidence' => 72.5,
            'initial_explanation' => 'Consistent with the student\'s earlier work.',
            'quiz_data' => ['questions' => [['id' => 'q1', 'type' => 'open', 'prompt' => 'Why?']]],
        ]);
        presubmission_feedback_storage::create_record(
            $userid,
            'mod_assign',
            'submission',
            $submissionid,
            '<p>Expand your conclusion.</p>'
        );
        grade_feedback_storage::create_record($userid, 'mod_assign', 'submission', $submissionid, [
            'gradingmethod' => 'simple',
            'analysis' => 'Meets most criteria.',
            'feedback' => '<p>Good work.</p>',
            'grade' => 80.0,
        ]);

        return $submissionid;
    }

    /**
     * Count rows across all three tables for a user.
     *
     * @param int $userid Student user id.
     * @return int
     */
    private function count_rows(int $userid): int {
        global $DB;

        $total = 0;
        foreach ([author_storage::TABLE, presubmission_feedback_storage::TABLE, grade_feedback_storage::TABLE] as $t) {
            $total += $DB->count_records($t, ['userid' => $userid]);
        }

        return $total;
    }

    public function test_get_metadata_declares_every_table_and_the_external_service(): void {
        $items = provider::get_metadata(new collection('plagiarism_dixeo'))->get_collection();
        $names = array_map(static fn($item) => $item->get_name(), $items);

        $this->assertContains(author_storage::TABLE, $names);
        $this->assertContains(presubmission_feedback_storage::TABLE, $names);
        $this->assertContains(grade_feedback_storage::TABLE, $names);
        $this->assertContains('dixeo', $names);
    }

    public function test_contexts_for_userid_are_activity_contexts(): void {
        $this->create_data((int) $this->assign->id, (int) $this->student->id);
        $this->create_data((int) $this->otherassign->id, (int) $this->student->id);
        $this->create_data((int) $this->assign->id, (int) $this->classmate->id);

        $contextids = provider::get_contexts_for_userid((int) $this->student->id)->get_contextids();

        sort($contextids);
        $expected = [$this->context->id, $this->othercontext->id];
        sort($expected);
        $this->assertEquals($expected, $contextids);
    }

    public function test_export_writes_all_three_tables(): void {
        $this->create_data((int) $this->assign->id, (int) $this->student->id);

        $approved = new approved_contextlist($this->student, 'plagiarism_dixeo', [$this->context->id]);
        provider::export_user_data($approved);

        $writer = writer::with_context($this->context);
        $this->assertTrue($writer->has_any_data());
        foreach (['privacy:path:authorship', 'privacy:path:presubmission', 'privacy:path:gradefeedback'] as $key) {
            $data = $writer->get_data([get_string($key, 'plagiarism_dixeo')]);
            $this->assertCount(1, $data->records);
        }
    }

    public function test_delete_for_user_leaves_other_users_and_activities(): void {
        $this->create_data((int) $this->assign->id, (int) $this->student->id);
        $this->create_data((int) $this->otherassign->id, (int) $this->student->id);
        $this->create_data((int) $this->assign->id, (int) $this->classmate->id);

        $approved = new approved_contextlist($this->student, 'plagiarism_dixeo', [$this->context->id]);
        provider::delete_data_for_user($approved);

        $this->assertEquals(3, $this->count_rows((int) $this->student->id));
        $this->assertEquals(3, $this->count_rows((int) $this->classmate->id));
    }

    public function test_get_users_in_context_and_delete_users(): void {
        $this->create_data((int) $this->assign->id, (int) $this->student->id);
        $this->create_data((int) $this->assign->id, (int) $this->classmate->id);

        $userlist = new userlist($this->context, 'plagiarism_dixeo');
        provider::get_users_in_context($userlist);
        $found = $userlist->get_userids();
        sort($found);
        $expected = [(int) $this->student->id, (int) $this->classmate->id];
        sort($expected);
        $this->assertEquals($expected, $found);

        $approved = new approved_userlist($this->context, 'plagiarism_dixeo', [(int) $this->student->id]);
        provider::delete_data_for_users($approved);

        $this->assertEquals(0, $this->count_rows((int) $this->student->id));
        $this->assertEquals(3, $this->count_rows((int) $this->classmate->id));
    }

    public function test_deleting_the_activity_purges_every_user(): void {
        $this->create_data((int) $this->assign->id, (int) $this->student->id);
        $this->create_data((int) $this->assign->id, (int) $this->classmate->id);
        $this->create_data((int) $this->otherassign->id, (int) $this->student->id);

        provider::delete_data_for_all_users_in_context($this->context);

        $this->assertEquals(3, $this->count_rows((int) $this->student->id));
        $this->assertEquals(0, $this->count_rows((int) $this->classmate->id));
    }

    public function test_user_context_is_ignored(): void {
        $this->create_data((int) $this->assign->id, (int) $this->student->id);
        $usercontext = \context_user::instance((int) $this->student->id);

        provider::delete_data_for_all_users_in_context($usercontext);

        $this->assertEquals(3, $this->count_rows((int) $this->student->id));
    }
}
