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

$string['analysis_aria_label'] = 'AI analysis';
$string['analyze'] = 'Analyze';
$string['analyzing'] = 'Analyzing...';
$string['authorship_cancel'] = 'Cancel';
$string['authorship_confirm_and_submit'] = 'Confirm and submit';
$string['authorship_confirm_statement'] = 'I confirm that this text is from my own authorship';
$string['authorship_count_multiple_choice'] = 'Number of multiple choice questions';
$string['authorship_count_multiple_choice_desc'] = 'How many multiple choice questions to generate in the verification quiz (0–5).';
$string['authorship_count_open'] = 'Number of open questions';
$string['authorship_count_open_desc'] = 'How many open (free text) questions to generate in the verification quiz (0–5).';
$string['authorship_count_true_false'] = 'Number of true/false questions';
$string['authorship_count_true_false_desc'] = 'How many true/false questions to generate in the verification quiz (0–5).';
$string['authorship_enabled'] = 'Enable authorship verification';
$string['authorship_enabled_desc'] = 'When enabled, submission text can be checked for authorship confidence. Below the threshold, a verification quiz may be required.';
$string['authorship_error_missing_record'] = 'Verification data is missing. Please cancel and try again.';
$string['authorship_quiz_load_error'] = 'Verification content could not be loaded.';
$string['authorship_quiz_modal_title'] = 'Verification required';
$string['authorship_settings_heading'] = 'Authorship verification';
$string['authorship_settings_heading_desc'] = 'Configure when and how authorship verification (confidence score and optional quiz) is applied to submissions.';
$string['authorship_submit_quiz'] = 'Submit verification';
$string['authorship_threshold'] = 'Confidence threshold';
$string['authorship_threshold_desc'] = 'Score 0–100. If the initial confidence is below this value, a verification quiz is required. Use 0 to always require a quiz, 100 to never require one. Default: 75.';
$string['authorship_threshold_invalid'] = 'Enter a confidence threshold between 0 and 100.';
$string['authorship_verifying'] = 'Verifying...';
$string['close'] = 'Close';
$string['display_final_score_visibility'] = 'Final score';
$string['display_final_score_visibility_help'] = 'Show the final authorship score to the student. With "Display score and feedback", the explanation of how the initial score and the verification test combined into it is shown too. This is the only score shown when no verification test was generated.';
$string['display_initial_score_visibility'] = 'Initial score';
$string['display_initial_score_visibility_help'] = 'Show the initial authorship score, taken from reading the submitted content. With "Display score and feedback", the explanation behind the score is shown too. Only applies when a verification test was generated.';
$string['display_test_score_visibility'] = 'Test score';
$string['display_test_score_visibility_help'] = 'Show the verification test grade to the student. With "Display score and feedback", the explanation of how the test was graded is shown too. Only applies when a verification test was completed.';
$string['dixeo:mod_assign_grade_submission'] = 'Grade assignment with AI';
$string['dixeo:mod_assign_pre_submission'] = 'Get feedback on assignment before submitting';
$string['enable_auto_grading'] = 'Prepare draft grades automatically';
$string['enable_auto_grading_help'] = 'When enabled, a grade and feedback are prepared automatically once a student submits, and wait in the grading form for you to review. Nothing is written to the gradebook until you check the proposal and save the grade yourself.';
$string['event_analysis_requested'] = 'Submission sent for analysis';
$string['event_authorship_checked'] = 'Authorship evaluation recorded';
$string['event_grade_draft_applied'] = 'Draft grade applied';
$string['event_grade_draft_created'] = 'Draft grade staged';
$string['event_verification_started'] = 'Authorship verification started';
$string['event_verification_submitted'] = 'Authorship verification submitted';
$string['false'] = 'False';
$string['job_poll_timeout'] = 'The analysis is taking too long. Please try again.';
$string['mod_assign_grade_submission'] = 'Grade assignment with AI';
$string['mod_assign_grade_submission_help'] = 'Allows the user to use AI-assisted grading when viewing a student\'s submission in the grader view. Intended for teachers only.';
$string['mod_assign_pre_submission'] = 'Get feedback on assignment before submitting';
$string['mod_assign_pre_submission_help'] = 'Allows the user to receive feedback on their assignment submission before they submit it for evaluation (Analyze step).';
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
$string['true'] = 'True';
$string['use_dixeo'] = 'Enable Dixeo for this activity';
$string['use_dixeo_help'] = 'When enabled, submissions to this assignment are analysed by Dixeo. Students can request feedback on a draft before submitting, and submissions are evaluated for authorship. If a verification test is required, the student must complete it before the submission is considered complete.';
$string['visibility_hide'] = 'Hide from student';
$string['visibility_score'] = 'Display score';
$string['visibility_score_and_feedback'] = 'Display score and feedback';
