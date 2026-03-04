<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify it
// under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful, but
// WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Web services for plagiarism_dixeo (pre-submission, grading, check/verify authorship).
 *
 * @package    plagiarism_dixeo
 * @author     Josemaria Bolanos <admin@mako.digital>
 * @copyright  2026 Dixeo.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'plagiarism_dixeo_mod_assign_pre_submission' => [
        'classname'     => 'plagiarism_dixeo\external\mod_assign_pre_submission',
        'methodname'    => 'execute',
        'classpath'     => '',
        'description'   => 'Get feedback on an assignment submission before submitting for evaluation (student pre-submission analysis).',
        'type'          => 'read',
        'ajax'          => true,
        'capabilities'  => 'plagiarism/dixeo:mod_assign_pre_submission',
        'loginrequired' => true,
    ],
    'plagiarism_dixeo_get_presubmission_status' => [
        'classname'     => 'plagiarism_dixeo\external\get_presubmission_status',
        'methodname'    => 'execute',
        'classpath'     => '',
        'description'   => 'Get pre-submission feedback status for current user (has_feedback and feedback_html for display).',
        'type'          => 'read',
        'ajax'          => true,
        'capabilities'  => 'plagiarism/dixeo:mod_assign_pre_submission',
        'loginrequired' => true,
    ],
    'plagiarism_dixeo_mod_assign_grade_submission' => [
        'classname'     => 'plagiarism_dixeo\external\mod_assign_grade_submission',
        'methodname'    => 'execute',
        'classpath'     => '',
        'description'   => 'Grade an assignment submission with AI to help the teacher (grader view). Teachers only.',
        'type'          => 'write',
        'ajax'          => true,
        'capabilities'  => 'plagiarism/dixeo:mod_assign_grade_submission',
        'loginrequired' => true,
    ],
    'plagiarism_dixeo_check_authorship' => [
        'classname'     => 'plagiarism_dixeo\external\check_authorship',
        'methodname'    => 'execute',
        'classpath'     => '',
        'description'   => 'Check authorship of submission text; returns score and optional quiz HTML.',
        'type'          => 'read',
        'ajax'          => true,
        'capabilities'  => 'plagiarism/dixeo:mod_assign_pre_submission',
        'loginrequired' => true,
    ],
    'plagiarism_dixeo_verify_authorship' => [
        'classname'     => 'plagiarism_dixeo\external\verify_authorship',
        'methodname'    => 'execute',
        'classpath'     => '',
        'description'   => 'Submit authorship quiz responses and save final confidence.',
        'type'          => 'write',
        'ajax'          => true,
        'capabilities'  => 'plagiarism/dixeo:mod_assign_pre_submission',
        'loginrequired' => true,
    ],
    'plagiarism_dixeo_get_verification_quiz' => [
        'classname'     => 'plagiarism_dixeo\external\get_verification_quiz',
        'methodname'    => 'execute',
        'classpath'     => '',
        'description'   => 'Get verification quiz for modal (no correct answers). May auto-submit if time expired.',
        'type'          => 'read',
        'ajax'          => true,
        'capabilities'  => 'plagiarism/dixeo:mod_assign_pre_submission',
        'loginrequired' => true,
    ],
    'plagiarism_dixeo_start_verification' => [
        'classname'     => 'plagiarism_dixeo\external\start_verification',
        'methodname'    => 'execute',
        'classpath'     => '',
        'description'   => 'Start verification test (status in progress, record test_started_at).',
        'type'          => 'write',
        'ajax'          => true,
        'capabilities'  => 'plagiarism/dixeo:mod_assign_pre_submission',
        'loginrequired' => true,
    ],
    'plagiarism_dixeo_save_verification_progress' => [
        'classname'     => 'plagiarism_dixeo\external\save_verification_progress',
        'methodname'    => 'execute',
        'classpath'     => '',
        'description'   => 'Save verification progress (responses + timings). Keeps status in progress.',
        'type'          => 'write',
        'ajax'          => true,
        'capabilities'  => 'plagiarism/dixeo:mod_assign_pre_submission',
        'loginrequired' => true,
    ],
];
