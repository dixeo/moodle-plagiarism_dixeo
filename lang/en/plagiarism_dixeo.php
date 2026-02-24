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
 * Language strings for Dixeo plagiarism plugin.
 *
 * @package    plagiarism_dixeo
 * @author     Josemaria Bolanos <admin@mako.digital>
 * @copyright  2026 Dixeo.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Dixeo';
$string['dixeo'] = 'Dixeo';
$string['enable_help'] = 'Enable Dixeo Plagiarism';
$string['use_dixeo'] = 'Enable Dixeo authorship for this activity';
$string['use_dixeo_help'] = 'When enabled, submissions are evaluated for authorship. If a verification test is required, the student must complete it before the submission is considered complete.';
$string['student_disclosure'] = 'Student disclosure';
$string['student_disclosure_help'] = 'This text is shown to students when they submit. It should explain that their work may be checked for authorship.';
$string['enable_mod_assign'] = 'Enable for assignments';
$string['assign_trigger'] = 'When to run authorship (assignments)';
$string['assign_trigger_help'] = 'Run evaluation on every draft upload, or only when the student clicks Submit for grading.';
$string['assign_trigger_upload'] = 'On every upload (draft)';
$string['assign_trigger_submit'] = 'On final submit only';
$string['evaluation_mode'] = 'Initial evaluation mode';
$string['evaluation_mode_help'] = 'Synchronous: run evaluation when the event fires (may slow the page). Asynchronous: queue and process in a scheduled task.';
$string['evaluation_mode_sync'] = 'Synchronous (in observer)';
$string['evaluation_mode_async'] = 'Asynchronous (scheduled task)';
$string['verification_test'] = 'Verification test';
$string['verification_test_help'] = 'When on, if initial confidence is below the threshold, a verification quiz is generated and the student must complete it.';
$string['status_pending'] = 'Authorship check in progress…';
$string['status_pending_badge'] = 'Pending...';
$string['verification_required'] = 'Complete authorship verification';
$string['verification_required_badge'] = 'Verification required';
$string['authorship_score'] = 'Authorship: {$a}%';
$string['authorship_score_badge'] = 'Score {$a}%';
$string['ready_for_submission_badge'] = 'Ready for submission: {$a}%';
$string['ready_for_submission_badge_plain'] = 'Ready for submission';
$string['quiz_grade'] = 'Verification: {$a}%';
$string['quiz_grade_badge'] = 'Quiz {$a}%';
$string['task_process_pending'] = 'Process pending Dixeo authorship evaluations';
$string['verification_complete'] = 'Verification complete. You can now submit your assignment.';
$string['submit_verification'] = 'Submit verification';
$string['invalidrecord'] = 'Invalid or inaccessible authorship record.';
$string['authorship_score_visibility'] = 'Authorship score';
$string['authorship_score_visibility_help'] = 'Whether to show the authorship confidence score to the student. "Display score and feedback" adds a tooltip with the AI explanation.';
$string['verification_test_score_visibility'] = 'Verification test score';
$string['verification_test_score_visibility_help'] = 'Whether to show the verification test result to the student. "Display score and feedback" adds a tooltip with the AI explanation.';
$string['visibility_hide'] = 'Hide from student';
$string['visibility_score'] = 'Display score';
$string['visibility_score_and_feedback'] = 'Display score and feedback';
$string['authorship_no_content'] = 'No submission content to evaluate.';
$string['authorship_explanation_fallback'] = 'Score assigned based on content analysis.';

// Authorship verification.
$string['plagiarism_settings_heading'] = 'Plagiarism settings';
$string['plagiarism_settings_heading_desc'] = 'Configure the plagiarism settings for the Dixeo plugin.';
$string['authorship_settings_heading'] = 'Authorship verification';
$string['authorship_settings_heading_desc'] = 'Configure when and how authorship verification (confidence score and optional quiz) is applied to submissions.';
$string['authorship_threshold'] = 'Confidence threshold';
$string['authorship_threshold_desc'] = 'Score 0–100. If the initial confidence is below this value, a verification quiz is required. Use 0 to always require a quiz, 100 to never require one. Default: 75.';
$string['authorship_threshold_help'] = 'Score 0–100. If the initial confidence is below this value, a verification quiz is required. Use 0 to always require a quiz, 100 to never require one. Default: 75.';
$string['authorship_count_true_false'] = 'Number of true/false questions';
$string['authorship_count_true_false_desc'] = 'How many true/false questions to generate in the verification quiz (1–5). At least one is required so the test has a question.';
$string['authorship_count_true_false_help'] = 'How many true/false questions to generate in the verification quiz (1–5). Default: 1.';
$string['authorship_count_multiple_choice'] = 'Number of multiple choice questions';
$string['authorship_count_multiple_choice_desc'] = 'How many multiple choice questions to generate in the verification quiz (0–5).';
$string['authorship_count_multiple_choice_help'] = 'How many multiple choice questions to generate in the verification quiz (0–5).';
$string['authorship_count_open'] = 'Number of open questions';
$string['authorship_count_open_desc'] = 'How many open (free text) questions to generate in the verification quiz (0–5).';
$string['authorship_count_open_help'] = 'How many open (free text) questions to generate in the verification quiz (0–5).';
$string['assign_file_not_indexed'] = 'The uploaded file(s) could not be indexed for analysis (the service may not support this file type or the file may be unreadable). Please try a different file or format.';
$string['true'] = 'True';
$string['false'] = 'False';
$string['authorship_submit_quiz'] = 'Submit verification';

// Assign module (pre-submission, grading).
$string['analyze'] = 'Analyze';
$string['analyzing'] = 'Analyzing...';
$string['mod_assign_pre_submission'] = 'Get feedback on assignment before submitting';
$string['mod_assign_pre_submission_help'] = 'Allows the user to receive feedback on their assignment submission before they submit it for evaluation (Analyze step).';
$string['assign_pre_submission_context_required'] = 'Assignment context is required to analyze your submission.';
$string['mod_assign_grade_submission'] = 'Grade assignment with AI';
$string['mod_assign_grade_submission_help'] = 'Allows the user to use AI-assisted grading when viewing a student\'s submission in the grader view. Intended for teachers only.';
$string['assign_submission_unsupported'] = 'Only online text and file submissions are supported for analysis. This assignment does not have either enabled.';
$string['assign_submission_no_readable_files'] = 'No analyzable files were found. Supported formats: PDF, plain text (.txt). Please submit files in one of these formats.';
$string['assign_submission_some_files_not_readable'] = 'The following file(s) could not be analyzed (unsupported format or read error): {$a}.';
$string['evaluation_mode_invalid'] = 'Invalid evaluation mode. Use "{$a->review}" or "{$a->grading}".';
$string['grading_error_criteria_count'] = 'Grading response criteria count does not match assignment (got {$a->got}, expected {$a->expected}).';
$string['grading_error_invalid_criterion'] = 'Grading response contains invalid criterion id: {$a}.';
$string['grading_error_invalid_level'] = 'Grading response contains invalid level id {$a->levelid} for criterion {$a->criterionid}.';
$string['authorship_confidence_low'] = 'Additional verification required';
$string['authorship_confidence_label'] = 'Authorship confidence: {$a}/100';
$string['authorship_verifying'] = 'Verifying...';
$string['authorship_confirm_statement'] = 'I confirm that this text is from my own authorship';
$string['authorship_quiz_modal_title'] = 'Verification required';
$string['authorship_confirm_and_submit'] = 'Confirm and submit';
$string['authorship_cancel'] = 'Cancel';
$string['authorship_error_missing_record'] = 'Verification data is missing. Please cancel and try again.';
