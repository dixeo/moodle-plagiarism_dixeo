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
 * Tests for get_assign_job_status access and safe errors.
 *
 * @package    plagiarism_dixeo
 * @category   test
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo;

use local_dixeo\dto\job_status;
use local_dixeo\external\service_factory;
use local_dixeo\service\assign_ux_service;
use plagiarism_dixeo\external\get_assign_job_status;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/plagiarism/dixeo/lib.php');


/**
 * Poll endpoint: caps, course/cm boundary, safe client errors.
 *
 * @covers \plagiarism_dixeo\external\get_assign_job_status
 */
final class get_assign_job_status_test extends \advanced_testcase {
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

    public function test_cross_course_cmid_rejected(): void {
        $this->resetAfterTest();

        $coursea = $this->getDataGenerator()->create_course();
        $courseb = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $coursea->id]);
        $student = $this->getDataGenerator()->create_and_enrol($coursea, 'student');
        $this->setUser($student);

        $this->enable_dixeo((int) $assign->cmid);

        $this->expectException(\moodle_exception::class);
        get_assign_job_status::execute('job-1', (int) $courseb->id, (int) $assign->cmid, 'review');
    }

    public function test_student_cannot_poll_grade_flow(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);

        $this->expectException(\required_capability_exception::class);
        get_assign_job_status::execute('job-1', (int) $course->id, (int) $assign->cmid, 'grade');
    }

    public function test_withholds_raw_exception_when_debugging_off(): void {
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
            ->method('get_status')
            ->willThrowException(new \RuntimeException('internal job table leak'));
        service_factory::set_test_assign_ux_service($mock);

        $result = get_assign_job_status::execute(
            'job-x',
            (int) $course->id,
            (int) $assign->cmid,
            'review'
        );

        $this->assertSame('failed', $result['status']);
        $this->assertSame(get_string('error:unexpected', 'local_dixeo'), $result['errormessage']);
        $this->assertStringNotContainsString('job table leak', $result['errormessage']);
    }

    public function test_completed_review_returns_sanitized_html(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'assignsubmission_onlinetext_enabled' => 1,
        ]);
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student);
        $this->enable_dixeo((int) $assign->cmid);

        $status = new job_status(
            jobid: 'job-done',
            type: 'assign_review',
            status: job_status::STATUS_COMPLETED,
            progress: 100,
            createdat: time(),
            result: ['feedbackHtml' => '<p>Coaching tip</p>'],
        );

        $mock = $this->createMock(assign_ux_service::class);
        $mock->expects($this->once())->method('get_status')->willReturn($status);
        $mock->expects($this->once())
            ->method('map_review_result')
            ->willReturn(['feedback_html' => '<p>Coaching tip</p>']);
        service_factory::set_test_assign_ux_service($mock);

        $result = get_assign_job_status::execute(
            'job-done',
            (int) $course->id,
            (int) $assign->cmid,
            'review'
        );

        $this->assertSame('completed', $result['status']);
        $this->assertStringContainsString('Coaching tip', $result['html']);
        $this->assertSame('', $result['errormessage']);
    }
}
