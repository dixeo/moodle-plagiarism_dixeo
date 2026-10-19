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
 * Language strings for the Dixeo plagiarism plugin.
 *
 * @package    plagiarism_dixeo
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['activity_not_enabled'] = 'Dixeo is not enabled for this assignment.';
$string['analysis_aria_label'] = 'AI analysis';
$string['analyze'] = 'Analyze';
$string['analyzing'] = 'Analyzing...';
$string['assign_trigger'] = 'When to analyse assignment submissions';
$string['assign_trigger_help'] = 'Analyse every draft the student uploads, or wait until they submit for grading. Analysing on upload gives faster feedback but uses more credit.';
$string['assign_trigger_submit'] = 'On final submit only';
$string['assign_trigger_upload'] = 'On every upload (draft)';
$string['assignment_feedback_enabled'] = 'Assignment feedback';
$string['assignment_feedback_enabled_help'] = 'Analyse assignment submissions and show students the disclosure. Each assignment keeps its own copy of this switch.';
$string['authorship_cancel'] = 'Cancel';
$string['authorship_confirm_and_submit'] = 'Confirm and submit';
$string['authorship_confirm_statement'] = 'I confirm that this text is from my own authorship';
$string['authorship_enabled'] = 'Enable authorship verification';
$string['authorship_enabled_desc'] = 'When enabled, submission text can be checked for authorship confidence. Below the threshold, a verification quiz may be required.';
$string['authorship_error_missing_record'] = 'Verification data is missing. Please cancel and try again.';
$string['authorship_quiz_load_error'] = 'Verification content could not be loaded.';
$string['authorship_quiz_modal_title'] = 'Verification required';
$string['authorship_submit_quiz'] = 'Submit verification';
$string['authorship_threshold'] = 'Confidence threshold';
$string['authorship_threshold_help'] = 'Score 0–100. If the initial confidence is below this value, a verification quiz is required. Use 0 to always require a quiz, 100 to never require one. Default: 75.';
$string['authorship_verifying'] = 'Verifying...';
$string['automation_authorship_failed'] = 'The authorship check could not be completed: {$a}';
$string['automation_authorship_incomplete'] = 'The authorship check did not finish in time (step: {$a}).';
$string['automation_grade_failed'] = 'The draft grade could not be prepared: {$a}';
$string['automation_grade_incomplete'] = 'The draft grade did not finish in time.';
$string['automation_review_failed'] = 'The submission review could not be completed: {$a}';
$string['automation_review_incomplete'] = 'The submission review did not finish in time.';
$string['close'] = 'Close';
$string['display_final_score'] = 'Final score: {$a}%';
$string['display_final_score_visibility_help'] = 'Show the final authorship score to the student. With "Display score and feedback", the explanation of how the initial score and the verification test combined into it is shown too. This is the only score shown when no verification test was generated.';
$string['display_initial_score'] = 'Initial score: {$a}%';
$string['display_initial_score_visibility_help'] = 'Show the initial authorship score, taken from reading the submitted content. With "Display score and feedback", the explanation behind the score is shown too. Only applies when a verification test was generated.';
$string['display_test_score'] = 'Test score: {$a}%';
$string['display_test_score_visibility_help'] = 'Show the verification test grade to the student. With "Display score and feedback", the explanation of how the test was graded is shown too. Only applies when a verification test was completed.';
$string['dixeo:mod_assign_grade_submission'] = 'Grade assignment with AI';
$string['dixeo:mod_assign_pre_submission'] = 'Get feedback on assignment before submitting';
$string['enable_auto_grading'] = 'Apply grades automatically';
$string['enable_auto_grading_help'] = 'When enabled, a grade and feedback are written to the gradebook automatically once a student submits. You can still change the grade afterwards. When disabled, nothing is written until you use the Grade button in the grader view.';
$string['evaluation_mode'] = 'Initial evaluation mode';
$string['evaluation_mode_async'] = 'Asynchronous (queued task)';
$string['evaluation_mode_help'] = 'Synchronous: run analysis when the student submits (may slow the page). Asynchronous: queue the work and process it in the background. A synchronous failure is logged and retried later rather than blocking the submission.';
$string['evaluation_mode_sync'] = 'Synchronous (in observer)';
$string['event_analysis_requested'] = 'Submission sent for analysis';
$string['event_authorship_checked'] = 'Authorship evaluation recorded';
$string['event_grade_draft_applied'] = 'Draft grade applied';
$string['event_grade_draft_created'] = 'Draft grade staged';
$string['event_verification_started'] = 'Authorship verification started';
$string['event_verification_submitted'] = 'Authorship verification submitted';
$string['false'] = 'False';
$string['grade_draft_notice'] = 'Dixeo has filled in a suggested grade and feedback below. Nothing has been saved: review the suggestion, change whatever you disagree with, and save the form yourself to record it.';
$string['grading_ai_disclaimer'] = 'This feedback was generated with AI.';
$string['grading_enabled'] = 'Grading';
$string['grading_enabled_help'] = 'Allow Assist Grade and automatic grades on assignments. Each assignment keeps its own copy of this switch.';
$string['grading_not_enabled'] = 'Grading is turned off for this assignment.';
$string['job_poll_timeout'] = 'The analysis is taking too long. Please try again.';
$string['panel_maximize'] = 'Maximize';
$string['panel_minimize'] = 'Minimize';
$string['pluginname'] = 'Dixeo';
$string['privacy:metadata:authorship'] = 'Stores authorship confidence checks and optional verification quiz data for assignment submissions.';
$string['privacy:metadata:authorship:area'] = 'Area within the component (for example submission).';
$string['privacy:metadata:authorship:component'] = 'Component the record relates to (for example mod_assign).';
$string['privacy:metadata:authorship:final_confidence'] = 'Final authorship confidence score after quiz evaluation, if any.';
$string['privacy:metadata:authorship:final_explanation'] = 'Explanation of the final authorship confidence score.';
$string['privacy:metadata:authorship:initial_confidence'] = 'Initial AI authorship confidence score (0–100).';
$string['privacy:metadata:authorship:initial_explanation'] = 'Explanation of the initial authorship confidence score.';
$string['privacy:metadata:authorship:itemid'] = 'Item id within the component (for example submission id).';
$string['privacy:metadata:authorship:quiz_data'] = 'Generated verification quiz questions stored as JSON.';
$string['privacy:metadata:authorship:quiz_explanation'] = 'Overall conclusion drawn from the verification quiz answers.';
$string['privacy:metadata:authorship:quiz_grade'] = 'Objective quiz grade derived from correct answers, if any.';
$string['privacy:metadata:authorship:quiz_question_feedbacks'] = 'Per-question feedback on the verification quiz answers, stored as JSON.';
$string['privacy:metadata:authorship:quiz_responses'] = 'Student answers to the verification quiz stored as JSON.';
$string['privacy:metadata:authorship:response_timings'] = 'When the verification quiz was started and how long each answer took.';
$string['privacy:metadata:authorship:status'] = 'Stage the authorship evaluation has reached.';
$string['privacy:metadata:authorship:timecreated'] = 'When the authorship record was created.';
$string['privacy:metadata:authorship:timemodified'] = 'When the authorship record was last updated.';
$string['privacy:metadata:authorship:userid'] = 'The student the authorship record belongs to.';
$string['privacy:metadata:dixeo'] = 'Submission content is sent to the Dixeo service to produce feedback, authorship confidence scores and proposed grades. No results are stored by the service beyond the duration of the request.';
$string['privacy:metadata:dixeo:activity_context'] = 'The activity name, instructions and grading criteria the submission is assessed against.';
$string['privacy:metadata:dixeo:quiz_responses'] = 'The student\'s answers to the authorship verification quiz.';
$string['privacy:metadata:dixeo:submission_content'] = 'The text and attached files the student submitted.';
$string['privacy:metadata:gradefeedback'] = 'Stores grades and feedback proposed for a submission, pending teacher confirmation.';
$string['privacy:metadata:gradefeedback:analysis'] = 'Assessment of the submission shown only to teachers.';
$string['privacy:metadata:gradefeedback:area'] = 'Area within the component (for example submission).';
$string['privacy:metadata:gradefeedback:component'] = 'Component the record relates to (for example mod_assign).';
$string['privacy:metadata:gradefeedback:criteria'] = 'Proposed marking guide or rubric scores and remarks, stored as JSON.';
$string['privacy:metadata:gradefeedback:feedback'] = 'Feedback proposed for the student.';
$string['privacy:metadata:gradefeedback:grade'] = 'Proposed numeric grade, for simple direct grading.';
$string['privacy:metadata:gradefeedback:gradingmethod'] = 'Grading method the proposal was produced for.';
$string['privacy:metadata:gradefeedback:itemid'] = 'Item id within the component (for example submission id).';
$string['privacy:metadata:gradefeedback:status'] = 'Whether a teacher has confirmed the proposal.';
$string['privacy:metadata:gradefeedback:timecreated'] = 'When the proposal was created.';
$string['privacy:metadata:gradefeedback:timemodified'] = 'When the proposal was last updated.';
$string['privacy:metadata:gradefeedback:userid'] = 'The student the proposed grade is for.';
$string['privacy:metadata:presubmission'] = 'Stores the feedback a student received on a draft before submitting it.';
$string['privacy:metadata:presubmission:area'] = 'Area within the component (for example submission).';
$string['privacy:metadata:presubmission:component'] = 'Component the record relates to (for example mod_assign).';
$string['privacy:metadata:presubmission:feedback'] = 'Feedback on the draft submission.';
$string['privacy:metadata:presubmission:itemid'] = 'Item id within the component (for example submission id).';
$string['privacy:metadata:presubmission:timecreated'] = 'When the feedback was created.';
$string['privacy:metadata:presubmission:timemodified'] = 'When the feedback was last updated.';
$string['privacy:metadata:presubmission:userid'] = 'The student the feedback belongs to.';
$string['privacy:path:authorship'] = 'Authorship verification';
$string['privacy:path:gradefeedback'] = 'Proposed grades';
$string['privacy:path:presubmission'] = 'Pre-submission feedback';
$string['question_locked'] = 'Question locked';
$string['question_locked_tooltip'] = 'This question cannot be edited';
$string['quiz_expired'] = 'Time is up. Your answers so far have been submitted.';
$string['quiz_not_available'] = 'This verification quiz is not available.';
$string['quiz_payload_invalid'] = 'The verification quiz sent unreadable data ({$a}). Please reload the page and try again.';
$string['quiz_payload_too_large'] = 'The verification quiz sent too much data ({$a}). Please shorten your answer.';
$string['quiz_time_remaining'] = 'Time remaining: {$a}';
$string['settings_category_assignment'] = 'Assignment';
$string['settings_category_plagiarism_dixeo'] = 'Plagiarism';
$string['settings_form_label'] = 'Opens the Dixeo settings for this assignment.';
$string['settings_subcategory_authorship'] = 'Authorship verification';
$string['settings_subcategory_feedback'] = 'Feedback';
$string['settings_subcategory_grading'] = 'Grading';
$string['show_final_score'] = 'Final score';
$string['show_final_score_hide'] = 'Hide from student';
$string['show_final_score_score'] = 'Display score';
$string['show_final_score_scoreandfeedback'] = 'Display score and feedback';
$string['show_initial_score'] = 'Initial score';
$string['show_initial_score_hide'] = 'Hide from student';
$string['show_initial_score_score'] = 'Display score';
$string['show_initial_score_scoreandfeedback'] = 'Display score and feedback';
$string['show_test_score'] = 'Test score';
$string['show_test_score_hide'] = 'Hide from student';
$string['show_test_score_score'] = 'Display score';
$string['show_test_score_scoreandfeedback'] = 'Display score and feedback';
$string['status_failed_badge'] = 'Analysis failed';
$string['status_pending_badge'] = 'Analysing...';
$string['student_disclosure'] = 'Student disclosure';
$string['student_disclosure_help'] = 'Text shown to students on the submission page. Use it to explain that their work will be analysed and what that involves. Leave it empty to show nothing.';
$string['task_analyze_submission'] = 'Analyse an assignment submission with Dixeo';
$string['task_retry_pending_analysis'] = 'Retry unfinished Dixeo submission analyses';
$string['task_stage_draft_grade'] = 'Prepare and apply a Dixeo grade';
$string['timer_question_tooltip'] = 'The next question will be shown when this timer ends.';
$string['timer_test_tooltip'] = 'The test will be submitted automatically when this timer ends.';
$string['true'] = 'True';
$string['verification_in_progress_badge'] = 'Verification in progress';
$string['verification_next'] = 'Next';
$string['verification_previous'] = 'Previous';
$string['verification_required_badge'] = 'Verification required';
$string['verification_test'] = 'Require a verification quiz';
$string['verification_test_help'] = 'When the initial confidence score falls below the threshold, generate a short quiz about the submission that the student must complete. Turn this off to record the score without ever asking the student anything.';
