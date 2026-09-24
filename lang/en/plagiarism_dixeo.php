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
$string['dixeo:mod_assign_grade_submission'] = 'Grade assignment with AI';
$string['dixeo:mod_assign_pre_submission'] = 'Get feedback on assignment before submitting';
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
$string['privacy:metadata:authorship:initial_confidence'] = 'Initial AI authorship confidence score (0–100).';
$string['privacy:metadata:authorship:itemid'] = 'Item id within the component (for example submission id).';
$string['privacy:metadata:authorship:quiz_data'] = 'Generated verification quiz questions stored as JSON.';
$string['privacy:metadata:authorship:quiz_grade'] = 'Objective quiz grade derived from correct answers, if any.';
$string['privacy:metadata:authorship:quiz_responses'] = 'Student answers to the verification quiz stored as JSON.';
$string['privacy:metadata:authorship:timecreated'] = 'When the authorship record was created.';
$string['privacy:metadata:authorship:timemodified'] = 'When the authorship record was last updated.';
$string['privacy:metadata:authorship:userid'] = 'The student the authorship record belongs to.';
$string['privacy:path:authorship'] = 'Authorship verification';
$string['true'] = 'True';
