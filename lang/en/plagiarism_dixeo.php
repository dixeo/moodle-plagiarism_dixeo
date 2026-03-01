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
$string['verification_in_progress_badge'] = 'Verification in progress';
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
$string['authorship_score_visibility_help'] = 'Whether to show the authorship confidence score to the student.';
$string['verification_test_score_visibility'] = 'Verification test score';
$string['verification_test_score_visibility_help'] = 'Whether to show the verification test result to the student.';
$string['display_initial_score'] = 'Initial score: {$a}%';
$string['display_initial_score_visibility'] = 'Initial score';
$string['display_initial_score_visibility_help'] = 'Show the initial authorship score (from reading the content). When "Display score and feedback" is selected, the explanation for this score is shown. Only applies when a verification test was generated.';
$string['display_test_score'] = 'Test score: {$a}%';
$string['display_test_score_visibility'] = 'Test score';
$string['display_test_score_visibility_help'] = 'Show the verification test grade. When "Display score and feedback" is selected, the explanation of how the test was graded is shown. Only applies when a verification test was completed.';
$string['display_final_score'] = 'Final score: {$a}%';
$string['display_final_score_visibility'] = 'Final score';
$string['display_final_score_visibility_help'] = 'Show the final authorship score. When "Display score and feedback" is selected, the explanation of how the initial score and test affected the final score is shown. Always shown when no test was generated (as the only score).';
$string['visibility_hide'] = 'Hide from student';
$string['visibility_score'] = 'Display score';
$string['visibility_score_and_feedback'] = 'Display score and feedback';
$string['panel_maximize'] = 'Maximize';
$string['panel_minimize'] = 'Minimize';
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

// Verification quiz timers and navigation (global settings).
$string['verification_quiz_timers_heading'] = 'Verification quiz timers and navigation';
$string['verification_quiz_timers_desc'] = 'Total time and per-question time limits for the verification test. Set to 0 for unlimited. Time spent is always recorded and sent to the AI for evaluation.';
$string['quiz_total_time'] = 'Total quiz time';
$string['quiz_total_time_help'] = 'Maximum time for the entire quiz. 0 = unlimited. Default: 3 minutes.';
$string['quiz_time_true_false'] = 'Time per true/false question';
$string['quiz_time_true_false_help'] = 'Maximum time per true/false question. 0 = unlimited. Default: 1 minute.';
$string['quiz_time_multiple_choice'] = 'Time per multiple choice question';
$string['quiz_time_multiple_choice_help'] = 'Maximum time per multiple choice question. 0 = unlimited. Default: 1 minute.';
$string['quiz_time_open'] = 'Time per open question';
$string['quiz_time_open_help'] = 'Maximum time per open (free text) question. 0 = unlimited. Default: 3 minutes.';
$string['allow_backwards_navigation'] = 'Allow backwards navigation';
$string['allow_backwards_navigation_help'] = 'When disabled, the user cannot return to previous questions after moving forward. Next button is disabled for a few seconds to avoid double-clicks.';
$string['next_button_delay_seconds'] = 'Next button delay (seconds)';
$string['next_button_delay_seconds_help'] = 'Seconds to disable the Next button after clicking (when backwards navigation is disabled). Default: 3.';

// Verification modal instructions (fragments; combined based on settings).
$string['verification_instructions_heading'] = 'Before you start';
$string['verification_instructions_intro'] = 'This short quiz helps confirm that the work you submitted is your own. You will answer a few questions based on your submission. Please read the rules below:';
$string['verification_instructions_total_time'] = 'Total time: {$a}';
$string['verification_instructions_total_time_unlimited'] = 'No overall time limit';
$string['verification_instructions_question_times'] = 'Time per question — True/false: {$a->tf}; Multiple choice: {$a->mc}; Open answer: {$a->open}';
$string['verification_instructions_no_backwards'] = 'You cannot return to previous questions once you have moved on';
$string['verification_instructions_start'] = 'Start';
$string['verification_instructions_minutes'] = '{$a} minutes';
$string['verification_instructions_seconds'] = '{$a} seconds';
$string['verification_instructions_unlimited'] = 'Unlimited';
$string['verification_timer_remaining'] = 'Time remaining: {$a}';
$string['verification_question_timer_remaining'] = 'Question time: {$a}';
$string['verification_previous'] = 'Previous';
$string['verification_next'] = 'Next';
$string['verification_submit'] = 'Submit';
$string['verification_submitting'] = 'Submitting...';
$string['verification_test_submitted'] = 'Test submitted';
$string['timer_test_tooltip'] = 'The test will be submitted automatically when this timer ends.';
$string['timer_question_tooltip'] = 'The next question will be shown when this timer ends.';
$string['question_locked'] = 'Question locked';
$string['question_locked_tooltip'] = 'This question cannot be edited';
$string['verification_test_submitted_message'] = 'Your verification test has been submitted. You can close this window.';

$string['assign_file_not_indexed'] = 'The uploaded file(s) could not be indexed for analysis (the service may not support this file type or the file may be unreadable). Please try a different file or format.';
$string['true'] = 'True';
$string['false'] = 'False';
$string['answer_correct'] = 'Correct';
$string['answer_incorrect'] = 'Incorrect';
$string['test_results_general'] = 'General feedback';
$string['back_to_assignment'] = 'Back to assignment';
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
$string['error_no_quiz_available'] = 'No verification quiz available.';
$string['error_verification_content_failed'] = 'Verification content could not be loaded.';
$string['verification_close'] = 'Close';

// Grading and AI errors (user-visible).
$string['grading_error_response_invalid'] = 'Grading response missing or invalid.';
$string['error_unknown'] = 'Unknown error';
$string['error_request_failed'] = 'Request failed';
$string['error_no_files_to_upload'] = 'No files to upload';
$string['error_failed_copy_file'] = 'Failed to copy file to temp';
$string['error_no_valid_files'] = 'No valid files';
$string['error_response_not_json'] = 'Response is not valid JSON';
$string['error_expected_json_object'] = 'Expected a JSON object';
$string['error_response_structure'] = 'Response does not match expected JSON structure';
$string['error_request_with_files_failed'] = 'Request with files failed';
$string['error_api_returned_error'] = 'API returned an error';
$string['error_invalid_api_response'] = 'Invalid API response';
$string['error_empty_response_content'] = 'Empty or missing response content';
