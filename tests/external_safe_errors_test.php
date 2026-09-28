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
 * Safe client error coverage for assign UX externals.
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
use plagiarism_dixeo\authorship\authorship_persistence_adapter;
use plagiarism_dixeo\external\check_authorship;
use plagiarism_dixeo\external\mod_assign_grade_submission;
use plagiarism_dixeo\external\mod_assign_pre_submission;
use plagiarism_dixeo\external\verify_authorship;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/plagiarism/dixeo/lib.php');


/**
 * Ensures unexpected failures do not leak raw exception text to WS clients.
 *
 * @covers \plagiarism_dixeo\external\mod_assign_pre_submission
 * @covers \plagiarism_dixeo\external\mod_assign_grade_submission
 *
 * @covers \plagiarism_dixeo\external\check_authorship
 * @covers \plagiarism_dixeo\external\verify_authorship
 */
final class external_safe_errors_test extends \advanced_testcase {
    protected function tearDown(): void {
        service_factory::reset();
        parent::tearDown();
    }

    /**
     * Turn Dixeo on for an assignment at site and activity level.
     *
     * @param int $cmid Course module id.
     */
    private function enable_dixeo(int $cmid): void {
        global $CFG;

        $CFG->enableplagiarism = 1;
        set_config('enabled', 1, 'plagiarism_dixeo');
        set_config('enable_mod_assign', 1, 'plagiarism_dixeo');
        \plagiarism_plugin_dixeo::set_activity_config($cmid, ['use_dixeo' => '1']);
    }

    public function test_pre_submission_withholds_raw_exception_when_debugging_off(): void {
        $this->resetAfterTest();
        set_debugging(DEBUG_NONE);

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'assignsubmission_onlinetext_enabled' => 1,
        ]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);
        $this->enable_dixeo((int) $assign->cmid);

        $mock = $this->createMock(assign_ux_service::class);
        $mock->expects($this->once())
            ->method('submit_review')
            ->willThrowException(new \RuntimeException('SELECT * FROM secret_table failed on db-99'));
        service_factory::set_test_assign_ux_service($mock);

        $result = mod_assign_pre_submission::execute((int) $assign->cmid);

        $this->assertSame('failed', $result['status']);
        $this->assertSame(get_string('error:unexpected', 'local_dixeo'), $result['errormessage']);
        $this->assertStringNotContainsString('secret_table', $result['errormessage']);
    }

    public function test_grade_submission_withholds_raw_exception_when_debugging_off(): void {
        $this->resetAfterTest();
        set_debugging(DEBUG_NONE);

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'assignsubmission_onlinetext_enabled' => 1,
        ]);
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($teacher);
        $this->enable_dixeo((int) $assign->cmid);

        $mock = $this->createMock(assign_ux_service::class);
        $mock->expects($this->once())
            ->method('submit_grade')
            ->willThrowException(new \RuntimeException('upstream stack trace xyz'));
        service_factory::set_test_assign_ux_service($mock);

        $result = mod_assign_grade_submission::execute((int) $assign->cmid, (int) $student->id);

        $this->assertSame('failed', $result['status']);
        $this->assertSame(get_string('error:unexpected', 'local_dixeo'), $result['errormessage']);
        $this->assertStringNotContainsString('upstream stack trace', $result['errormessage']);
    }

    public function test_pre_submission_returns_pending_job(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'assignsubmission_onlinetext_enabled' => 1,
        ]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);
        $this->enable_dixeo((int) $assign->cmid);

        $mock = $this->createMock(assign_ux_service::class);
        $mock->expects($this->once())
            ->method('submit_review')
            ->willReturn(operation_result::pending('job-r1', 'pending', 0));
        service_factory::set_test_assign_ux_service($mock);

        $result = mod_assign_pre_submission::execute((int) $assign->cmid);

        $this->assertSame('pending', $result['status']);
        $this->assertSame('job-r1', $result['jobid']);
        $this->assertSame((int) $course->id, $result['courseid']);
        $this->assertSame('', $result['errormessage']);
    }

    public function test_check_authorship_withholds_raw_exception_when_debugging_off(): void {
        $this->resetAfterTest();
        set_debugging(DEBUG_NONE);
        set_config('authorship_enabled', 1, 'plagiarism_dixeo');

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'assignsubmission_onlinetext_enabled' => 1,
        ]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);
        $this->enable_dixeo((int) $assign->cmid);

        $mock = $this->createMock(assign_ux_service::class);
        $mock->expects($this->once())
            ->method('submit_authorship_and_wait')
            ->willThrowException(new \RuntimeException('openai upstream dump'));
        service_factory::set_test_assign_ux_service($mock);

        $result = check_authorship::execute('mod_assign', 'submission', (int) $assign->cmid);

        $this->assertSame('failed', $result['status']);
        $this->assertSame(get_string('error:unexpected', 'local_dixeo'), $result['errormessage']);
        $this->assertStringNotContainsString('openai upstream', $result['errormessage']);
    }

    public function test_verify_authorship_withholds_raw_exception_when_debugging_off(): void {
        global $DB;

        $this->resetAfterTest();
        set_debugging(DEBUG_NONE);

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'assignsubmission_onlinetext_enabled' => 1,
        ]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);
        $this->enable_dixeo((int) $assign->cmid);

        $now = time();
        $submissionid = $DB->insert_record('assign_submission', (object) [
            'assignment' => $assign->id,
            'userid' => $student->id,
            'timecreated' => $now,
            'timemodified' => $now,
            'status' => 'draft',
            'groupid' => 0,
            'attemptnumber' => 0,
            'latest' => 1,
        ]);

        $adapter = new authorship_persistence_adapter();
        $record = $adapter->create_authorship_record(
            (int) $student->id,
            'mod_assign',
            'submission',
            (int) $submissionid,
            40.0,
            ['questions' => []]
        );
        $this->assertNotNull($record);

        $mock = $this->createMock(assign_ux_service::class);
        $mock->expects($this->once())
            ->method('submit_authorship_and_wait')
            ->willThrowException(new \RuntimeException('final eval secret'));
        service_factory::set_test_assign_ux_service($mock);

        $result = verify_authorship::execute((int) $record->id, '{}');

        $this->assertSame('failed', $result['status']);
        $this->assertSame(get_string('error:unexpected', 'local_dixeo'), $result['errormessage']);
        $this->assertStringNotContainsString('final eval secret', $result['errormessage']);
    }
}
