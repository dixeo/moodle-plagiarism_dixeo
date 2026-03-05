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
 * Assign module support: context, submission data, and evaluation.
 *
 * Used by mod_assign_grade_submission and mod_assign_pre_submission to get assignment
 * metadata (name, intro, activity, grading method, criteria), user submission data,
 * and evaluation: AI-driven review (pre-submission) or grading (grader view).
 *
 * @package    plagiarism_dixeo
 * @author     Josemaria Bolanos <admin@mako.digital>
 * @copyright  2026 Dixeo.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo\modules;

use context_module;
use plagiarism_dixeo\ai\ai;
use plagiarism_dixeo\ai\result;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/grade/grading/lib.php');

/**
 * Assign module: assignment context, submission data, review (AI), and grading (AI).
 */
class assign {

    /** Evaluation mode: pre-submission AI review (student). */
    public const EVALUATION_MODE_REVIEW = 'review';

    /** Evaluation mode: grader view (AI grading). */
    public const EVALUATION_MODE_GRADING = 'grading';

    /**
     * Get assignment metadata and grading info (not Moodle context).
     *
     * @param int $cmid Course module ID (assign instance).
     * @return array name, intro, activity, grading_method, criteria (guide/rubric definitions).
     */
    public static function get_assignment_context(int $cmid): array {
        global $DB;

        $cm = get_coursemodule_from_id('assign', $cmid, 0, false, MUST_EXIST);
        $assign = $DB->get_record('assign', ['id' => $cm->instance], 'name,intro,introformat,activity,activityformat', MUST_EXIST);

        $context = context_module::instance($cm->id);
        $area = $DB->get_record('grading_areas', [
            'contextid' => $context->id,
            'component' => 'mod_assign',
            'areaname'  => 'submissions',
        ], 'id,activemethod', IGNORE_MISSING);

        $gradingmethod = 'simple';
        if ($area && !empty($area->activemethod)) {
            $gradingmethod = $area->activemethod;
        }

        $result = [
            'name'            => $assign->name,
            'intro'           => $assign->intro,
            'activity'        => $assign->activity ?? '',
            'grading_method'  => $gradingmethod,
            'assignmentid'    => (int) $cm->instance,
            'criteria'        => [],
        ];

        if (!$area || empty($area->id)) {
            return $result;
        }

        if ($gradingmethod === 'guide') {
            $definition = $DB->get_record('grading_definitions', [
                'areaid' => $area->id,
                'method' => 'guide',
            ], 'id', IGNORE_MISSING);
            if ($definition) {
                $rows = $DB->get_records('gradingform_guide_criteria', ['definitionid' => $definition->id], 'sortorder');
                foreach ($rows as $c) {
                    $result['criteria'][] = [
                        'id'          => (int) $c->id,
                        'shortname'   => $c->shortname,
                        'description' => $c->description ?? '',
                        'maxscore'    => (float) $c->maxscore,
                        'sortorder'   => (int) $c->sortorder,
                    ];
                }
            }
        } else if ($gradingmethod === 'rubric') {
            $definition = $DB->get_record('grading_definitions', [
                'areaid' => $area->id,
                'method' => 'rubric',
            ], 'id', IGNORE_MISSING);
            if ($definition) {
                $critrows = $DB->get_records('gradingform_rubric_criteria', ['definitionid' => $definition->id], 'sortorder');
                foreach ($critrows as $c) {
                    $levels = $DB->get_records('gradingform_rubric_levels', ['criterionid' => $c->id], 'score');
                    $leveldata = [];
                    foreach ($levels as $l) {
                        $leveldata[] = [
                            'id'         => (int) $l->id,
                            'score'      => (float) $l->score,
                            'definition' => $l->definition ?? '',
                        ];
                    }
                    $result['criteria'][] = [
                        'id'          => (int) $c->id,
                        'description' => $c->description ?? '',
                        'sortorder'   => (int) $c->sortorder,
                        'levels'      => $leveldata,
                    ];
                }
            }
        }

        return $result;
    }

    /** File extensions that the AI can read (for submission analysis/grading). */
    private const AI_READABLE_EXTENSIONS = ['pdf', 'txt'];

    /** Max size per file for upload to AI. 20MB. */
    private const UPLOAD_MAX_FILE_SIZE = 20 * 1024 * 1024;

    /** Max total size of files for upload to AI. 50MB. */
    private const UPLOAD_MAX_TOTAL_SIZE = 50 * 1024 * 1024;

    /**
     * Get enabled submission plugins for an assignment from assign_plugin_config.
     *
     * @param int $assignmentid Assign instance id.
     * @return array{onlinetext_enabled: bool, file_enabled: bool, file_filetypeslist: string}
     */
    public static function get_submission_plugins(int $assignmentid): array {
        global $DB;
        $rows = $DB->get_records('assign_plugin_config', [
            'assignment' => $assignmentid,
            'subtype'    => 'assignsubmission',
        ], 'id ASC');
        $onlinetext = false;
        $file = false;
        $filetypeslist = '';
        foreach ($rows as $row) {
            if ($row->plugin === 'onlinetext' && $row->name === 'enabled') {
                $onlinetext = !empty($row->value);
            }
            if ($row->plugin === 'file') {
                if ($row->name === 'enabled') {
                    $file = !empty($row->value);
                }
                if ($row->name === 'filetypeslist') {
                    $filetypeslist = (string) $row->value;
                }
            }
        }
        return [
            'onlinetext_enabled'   => $onlinetext,
            'file_enabled'         => $file,
            'file_filetypeslist'   => $filetypeslist,
        ];
    }

    /**
     * Validate that the assignment has at least one supported submission type (onlinetext or file).
     *
     * @param int $assignmentid Assign instance id.
     * @return string|null Error message or null if valid.
     */
    public static function validate_submission_plugins_for_analysis(int $assignmentid): ?string {
        $plugins = self::get_submission_plugins($assignmentid);
        if ($plugins['onlinetext_enabled'] || $plugins['file_enabled']) {
            return null;
        }
        return get_string('assign_submission_unsupported', 'plagiarism_dixeo');
    }

    /**
     * Get submission data for display and AI analysis (review/grading).
     *
     * Returns the latest submission for the user (or a specific submission) plus onlinetext,
     * submission files, and plugin config.
     *
     * @param int $cmid Course module ID (assign instance).
     * @param int $userid User ID (student for grading, current user for pre-submission).
     * @param int|null $submissionid If set, load this submission's content (must belong to userid); else latest.
     * @return array{submission: object|null, onlinetext: string, attempts: array, submission_plugins: array, submission_files: array}
     */
    public static function get_submission_data(int $cmid, int $userid, ?int $submissionid = null): array {
        global $DB;

        $assignmentid = (int) $DB->get_field('course_modules', 'instance', ['id' => $cmid], MUST_EXIST);
        $plugins = self::get_submission_plugins($assignmentid);

        if ($submissionid !== null && $submissionid > 0) {
            $submission = $DB->get_record('assign_submission', [
                'id'         => $submissionid,
                'assignment' => $assignmentid,
                'userid'     => $userid,
            ], '*', IGNORE_MISSING);
        } else {
            $submission = null;
        }
        if ($submission === null && $submissionid === null) {
            $submission = $DB->get_record_sql(
                'SELECT * FROM {assign_submission}
                 WHERE assignment = :assignmentid AND userid = :userid AND latest = 1
                 ORDER BY attemptnumber DESC',
                ['assignmentid' => $assignmentid, 'userid' => $userid],
                IGNORE_MISSING
            );
        }

        $attempts = $DB->get_records('assign_submission', [
            'assignment' => $assignmentid,
            'userid'     => $userid,
        ], 'attemptnumber ASC');

        $sid = $submission ? (int) $submission->id : null;
        $onlinetext = '';
        if ($sid && $plugins['onlinetext_enabled']) {
            $onlinetextrec = $DB->get_record('assignsubmission_onlinetext', [
                'assignment' => $assignmentid,
                'submission' => $sid,
            ], 'onlinetext', IGNORE_MISSING);
            if ($onlinetextrec) {
                $onlinetext = $onlinetextrec->onlinetext ?? '';
            }
        }

        $files = [];
        if ($sid && $plugins['file_enabled']) {
            $context = context_module::instance($cmid);
            $files = self::get_submission_files($context->id, $sid);
        }

        return [
            'submission'         => $submission,
            'onlinetext'         => $onlinetext,
            'attempts'           => array_values($attempts),
            'submission_plugins' => $plugins,
            'submission_files'   => $files,
        ];
    }

    /**
     * Get the latest submission id for a user in an assignment (for plagiarism display when submissionid not in linkarray).
     *
     * @param int $cmid Course module id.
     * @param int $userid User id.
     * @return int Submission id or 0 if none.
     */
    public static function get_latest_submission_id(int $cmid, int $userid): int {
        global $DB;
        $assignmentid = (int) $DB->get_field('course_modules', 'instance', ['id' => $cmid], IGNORE_MISSING);
        if ($assignmentid <= 0) {
            return 0;
        }
        $sub = $DB->get_record_sql(
            'SELECT id FROM {assign_submission}
             WHERE assignment = :aid AND userid = :uid AND latest = 1
             ORDER BY attemptnumber DESC',
            ['aid' => $assignmentid, 'uid' => $userid],
            IGNORE_MISSING
        );
        return $sub ? (int) $sub->id : 0;
    }

    /**
     * Get stored files for a submission (assignsubmission_file, filearea submission_files).
     *
     * @param int $contextid Module context ID.
     * @param int $submissionid Assign submission ID (itemid in file area).
     * @return \stored_file[] List of files (excluding directories).
     */
    public static function get_submission_files(int $contextid, int $submissionid): array {
        $fs = get_file_storage();
        $files = $fs->get_area_files(
            $contextid,
            'assignsubmission_file',
            'submission_files',
            $submissionid,
            'itemid, filepath, filename',
            false
        );
        return array_values($files);
    }

    /**
     * Validate submission files for upload to the AI (size and extension). Same pattern as local_edai generate_course.
     *
     * @param \stored_file[] $storedfiles Files from get_submission_files.
     * @return array{valid: \stored_file[], notreadable: string[]} Files that pass validation and list of filenames that were skipped (wrong type or too large).
     */
    public static function validate_submission_files_for_upload(array $storedfiles): array {
        $valid = [];
        $notreadable = [];
        $totalsize = 0;
        foreach ($storedfiles as $file) {
            if (!($file instanceof \stored_file) || $file->is_directory()) {
                continue;
            }
            $ext = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
            if (!in_array($ext, self::AI_READABLE_EXTENSIONS, true)) {
                $notreadable[] = $file->get_filename();
                continue;
            }
            $size = $file->get_filesize();
            if ($size > self::UPLOAD_MAX_FILE_SIZE) {
                $notreadable[] = $file->get_filename() . ' (exceeds ' . (self::UPLOAD_MAX_FILE_SIZE / (1024 * 1024)) . 'MB)';
                continue;
            }
            if ($totalsize + $size > self::UPLOAD_MAX_TOTAL_SIZE) {
                $notreadable[] = $file->get_filename() . ' (total upload limit exceeded)';
                continue;
            }
            $valid[] = $file;
            $totalsize += $size;
        }
        return ['valid' => $valid, 'notreadable' => $notreadable];
    }

    /**
     * AI review prompt: assistant teacher guiding the student (no direct answers).
     */
    private const REVIEW_SYSTEM_PROMPT = <<<EOT
You are an assistant teacher helping students improve their assignments before they submit. Keep a friendly, supportive tone. Do not give away the answers directly—guide the student toward better work without doing it for them.

Compare the student's submission with the assignment description and instructions provided below. Identify concrete opportunities for improvement.

Do not invent or guess the content of the submission. If you cannot access or read the actual submission (e.g. no text provided, file not available, or you only see a placeholder such as "content not extracted"), do not generate feedback, grades, or suggestions. Instead, respond clearly in valid HTML that you do not have enough information to complete the task, and ask the student to ensure their submission is properly uploaded or provided in a readable format.

When you do have access to the submission content, respond with:
1. A short overall assessment (one or two sentences) of how the submission aligns with the task.
2. A list of 5 or less actionable items the student can implement to strengthen their work.

Use valid HTML only: <p>, <ul>, <li>, <strong>, <em>. Do not use markdown. Keep the response concise and helpful.
EOT;

    /**
     * AI grading prompt: professional, objective evaluation for the teacher (also visible to the student).
     * Fair assessment of strengths and weaknesses; not always positive.
     */
    private const GRADING_SYSTEM_PROMPT = <<<EOT
You are an experienced teacher grading a student submission. Your feedback will be read by the teacher as a grading guide and will also be visible to the student. Be professional, objective, and fair.

Do not invent or hallucinate grades, scores, analysis, or feedback. If you cannot access or read the actual submission content (e.g. no text provided, file not available, or you only see a placeholder), do not generate a grade, criteria scores, or detailed assessment. Instead, respond with a JSON object where "analysis" and "feedback" clearly state that you do not have enough information to complete the grading, and ask that the submission be provided in a readable format. Use a neutral placeholder grade only if the response format requires it (e.g. grade 0 or omit criteria), and make it explicit in "analysis" that no real assessment was performed due to missing content.

When you do have access to the submission content, evaluate it against the assignment description, instructions, and grading criteria provided below. Consider both strengths and weaknesses. Do not be uniformly positive—give an honest assessment: praise what is done well and clearly identify what is missing, unclear, or below expectations. Be constructive but direct.

You must respond with a JSON object: "analysis" (string), "feedback" (HTML string for the student), and either "grade" (simple) or "criteria" (marking guide/rubric). For marking guide or rubric grading you must always include the "criteria" key: an array with exactly one object per criterion listed in the context, in the same order. Each criterion object must have "id" (the exact criterion ID from the context), "remark" (string), and either "score" (guide, within max) or "levelid" (rubric, exact level ID from the context). Do not omit "criteria" when the assignment uses a marking guide or rubric. Use valid HTML in feedback only: <p>, <ul>, <li>, <strong>, <em>. No markdown.
EOT;

    /** When authorship data is provided: instruct the AI to consider low confidence in grading. */
    private const GRADING_AUTHORSHIP_INSTRUCTION = <<<EOT

Authorship verification data is provided for this submission. It indicates how likely the text is to be human-written (0–100). Low initial_confidence or final_confidence suggests possible AI-generated or non-original content. You must take this into account: low authorship confidence should impact the grade negatively and be reflected in your analysis and feedback (e.g. lower scores, stronger emphasis on originality and academic integrity). Do not ignore authorship when it is provided.
EOT;

    /**
     * Run AI review (pre-submission feedback). When $uploadfiles is non-empty, uses file upload + Assistants API (same pattern as local_edai generate_course).
     *
     * @param array $context From get_assignment_context.
     * @param array $submissiondata From get_submission_data.
     * @param string|null $submissionblockoverride Pre-built submission block (e.g. from build_submission_block_for_ai) or null.
     * @param \stored_file[] $uploadfiles Optional list of files to upload to the AI (e.g. PDFs from submission). When non-empty, uses send_request_with_files.
     * @return result
     */
    public static function review_with_ai(array $context, array $submissiondata, ?string $submissionblockoverride = null, array $uploadfiles = []): result {
        $contextblock = self::format_context_for_review($context);
        $submissionblock = $submissionblockoverride !== null
            ? $submissionblockoverride
            : self::format_submission_for_review($submissiondata);

        $ai = new ai('Dixeo assignment review', [
            'assignname' => $context['name'] ?? '',
        ]);

        $submissionlabel = 'SUBMISSION:';
        if (!empty($uploadfiles)) {
            $submissionlabel .= "\nThe submission content is in the attached file(s). Use the file_search tool to retrieve and read the file(s), then base your feedback on that content.";
            // Do not include the "content not extracted" placeholder in the message—it instructs the model to refuse. List filenames only.
            $filenames = array_map(static function (\stored_file $f): string {
                return $f->get_filename();
            }, $uploadfiles);
            $submissionblock = 'Attached file(s): ' . implode(', ', $filenames) . '.';
        }
        $usertext = "CONTEXT:\n" . $contextblock . "\n\n" . $submissionlabel . "\n" . $submissionblock;

        if (!empty($uploadfiles)) {
            return $ai->send_request_with_files(
                self::REVIEW_SYSTEM_PROMPT,
                $usertext,
                $uploadfiles,
                null,
                null
            );
        }

        return $ai->send_request(
            self::REVIEW_SYSTEM_PROMPT,
            [
                'context'    => $contextblock,
                'submission' => $submissionblock,
            ],
            [],
            null,
            null
        );
    }

    /**
     * Format assignment context as plain text for the AI (name, intro, activity, grading, criteria).
     *
     * @param array $context
     * @return string
     */
    protected static function format_context_for_review(array $context): string {
        $lines = [];
        $lines[] = 'Assignment: ' . ($context['name'] ?? '');
        $lines[] = '';
        $lines[] = 'Description (intro):';
        $lines[] = strip_tags($context['intro'] ?? '');
        $lines[] = '';
        $lines[] = 'Instructions (activity):';
        $lines[] = strip_tags($context['activity'] ?? '');
        $lines[] = '';
        $lines[] = 'Grading method: ' . ($context['grading_method'] ?? 'simple');
        $criteria = $context['criteria'] ?? [];
        if (!empty($criteria)) {
            $lines[] = 'Criteria:';
            foreach ($criteria as $i => $c) {
                $short = $c['shortname'] ?? $c['description'] ?? ('Criterion ' . ($i + 1));
                $lines[] = '  - ' . $short;
            }
        }
        return implode("\n", $lines);
    }

    /**
     * Validate submission plugins and build submission content for the AI.
     * Returns content string and optional warning, or error.
     *
     * @param int $assignmentid Assign instance id.
     * @param array $submissiondata From get_submission_data (onlinetext, submission_files, submission_plugins).
     * @return array{content: string, warning: string|null}|array{error: string}
     */
    public static function build_submission_block_for_ai(int $assignmentid, array $submissiondata): array {
        $err = self::validate_submission_plugins_for_analysis($assignmentid);
        if ($err !== null) {
            return ['error' => $err];
        }

        $plugins = $submissiondata['submission_plugins'] ?? self::get_submission_plugins($assignmentid);
        $onlinetext = trim($submissiondata['onlinetext'] ?? '');
        $files = $submissiondata['submission_files'] ?? [];

        $parts = [];
        $notreadable = [];
        $readablecontent = '';

        if ($plugins['onlinetext_enabled'] && $onlinetext !== '') {
            $parts[] = strip_tags($onlinetext);
        }

        foreach ($files as $file) {
            if (!$file->is_directory()) {
                $ext = strtolower(pathinfo($file->get_filename(), PATHINFO_EXTENSION));
                if (!in_array($ext, self::AI_READABLE_EXTENSIONS, true)) {
                    $notreadable[] = $file->get_filename();
                    continue;
                }
                try {
                    $content = $file->get_content();
                    if ($ext === 'txt') {
                        $readablecontent .= "\n\n--- File: " . $file->get_filename() . " ---\n" . $content;
                    } else {
                        $readablecontent .= "\n\n[Attachment: " . $file->get_filename() . " (PDF - content not extracted in this version)]";
                    }
                } catch (\Throwable $e) {
                    $notreadable[] = $file->get_filename() . ' (' . $e->getMessage() . ')';
                }
            }
        }

        if ($plugins['file_enabled'] && !empty($files) && $readablecontent === '' && empty($parts)) {
            return ['error' => get_string('assign_submission_no_readable_files', 'plagiarism_dixeo')];
        }

        if ($readablecontent !== '') {
            $parts[] = trim($readablecontent);
        }

        $content = implode("\n\n", array_filter($parts));
        if ($content === '') {
            $content = '(No submission content yet.)';
        }

        $warning = null;
        if (!empty($notreadable)) {
            $warning = get_string('assign_submission_some_files_not_readable', 'plagiarism_dixeo', implode(', ', $notreadable));
        }

        return ['content' => $content, 'warning' => $warning];
    }

    /**
     * Format submission data for the AI (onlinetext only; use build_submission_block_for_ai for files and validation).
     *
     * @param array $submissiondata From get_submission_data (onlinetext, submission_files, submission_plugins).
     * @return string Plain text for the prompt.
     */
    protected static function format_submission_for_review(array $submissiondata): string {
        $text = $submissiondata['onlinetext'] ?? '';
        if (trim($text) === '') {
            return '(No submission text yet.)';
        }
        return strip_tags($text);
    }

    /**
     * Format assignment context for grading AI: name, intro, activity, grading method, and full criteria
     * (with IDs and, for rubric, level IDs) so the model can return matching JSON.
     *
     * @param array $context From get_assignment_context.
     * @return string
     */
    protected static function format_context_for_grading(array $context): string {
        $lines = [];
        $lines[] = 'Assignment: ' . ($context['name'] ?? '');
        $lines[] = '';
        $lines[] = 'Description (intro):';
        $lines[] = strip_tags($context['intro'] ?? '');
        $lines[] = '';
        $lines[] = 'Instructions (activity):';
        $lines[] = strip_tags($context['activity'] ?? '');
        $lines[] = '';
        $gradingmethod = $context['grading_method'] ?? 'simple';
        $lines[] = 'Grading method: ' . $gradingmethod;
        if ($gradingmethod === 'simple') {
            $lines[] = 'Return a numeric grade between 0 and 100 in the "grade" field.';
        }
        $lines[] = '';

        $criteria = $context['criteria'] ?? [];
        if (!empty($criteria)) {
            $lines[] = 'Your JSON response must include a "criteria" array with exactly ' . count($criteria) . ' object(s), one per criterion below. Use the exact "id" and (for rubric) "level id" numbers. These are database IDs and can be any positive integers (e.g. 47, 48), not 1-based positions.';
            foreach ($criteria as $i => $c) {
                $id = (int) ($c['id'] ?? $i);
                if ($gradingmethod === 'guide') {
                    $short = $c['shortname'] ?? $c['description'] ?? ('Criterion ' . ($i + 1));
                    $max = isset($c['maxscore']) ? (float) $c['maxscore'] : 0;
                    $lines[] = '  - id: ' . $id . ', shortname: ' . $short . ', maxscore: ' . $max . ', description: ' . ($c['description'] ?? '');
                } else {
                    $levels = $c['levels'] ?? [];
                    $lines[] = '  - id: ' . $id . ', description: ' . ($c['description'] ?? '');
                    foreach ($levels as $l) {
                        $lid = (int) ($l['id'] ?? 0);
                        $score = isset($l['score']) ? (float) $l['score'] : 0;
                        $def = $l['definition'] ?? '';
                        $lines[] = '    level id: ' . $lid . ', score: ' . $score . ', definition: ' . $def;
                    }
                }
            }
        }
        return implode("\n", $lines);
    }

    /**
     * Build the expected JSON schema for grading response (matches assignment criteria count and type).
     *
     * @param array $context From get_assignment_context.
     * @return array Schema for ai::send_request json_structure.
     */
    protected static function build_grading_json_structure(array $context): array {
        $base = [
            'type'       => 'object',
            'properties' => [
                'analysis' => ['type' => 'string'],
                'feedback' => ['type' => 'string'],
            ],
            'required'   => ['analysis', 'feedback'],
        ];
        $gradingmethod = $context['grading_method'] ?? 'simple';
        if ($gradingmethod === 'simple') {
            $base['properties']['grade'] = ['type' => 'number'];
            $base['required'][] = 'grade';
            return $base;
        }
        $criterionitem = [
            'type'       => 'object',
            'properties' => [
                'id'       => ['type' => 'integer'],
                'remark'   => ['type' => 'string'],
                'comment'  => ['type' => 'string'],
                'feedback' => ['type' => 'string'],
            ],
            'required'   => ['id'],
        ];
        if ($gradingmethod === 'guide') {
            $criterionitem['properties']['score'] = ['type' => 'number'];
            $criterionitem['required'][] = 'score';
        } else if ($gradingmethod === 'rubric') {
            $criterionitem['properties']['levelid'] = ['type' => 'integer'];
            $criterionitem['required'][] = 'levelid';
        }
        $base['properties']['criteria'] = [
            'type'  => 'array',
            'items' => $criterionitem,
        ];
        $base['required'][] = 'criteria';
        return $base;
    }

    /**
     * Grade submission with AI. When $uploadfiles is non-empty, uses file upload + Assistants API (same pattern as local_edai generate_course).
     *
     * @param array $context From get_assignment_context.
     * @param array $submissiondata From get_submission_data.
     * @param object|null $authorship Optional authorship record.
     * @param string|null $submissionblockoverride Pre-built submission block or null.
     * @param \stored_file[] $uploadfiles Optional files to upload to the AI; when non-empty, uses send_request_with_files.
     * @return result
     */
    public static function grade_with_ai(array $context, array $submissiondata, $authorship = null, ?string $submissionblockoverride = null, array $uploadfiles = []): result {
        $contextblock = self::format_context_for_grading($context);
        $submissionblock = $submissionblockoverride !== null
            ? $submissionblockoverride
            : self::format_submission_for_review($submissiondata);
        $jsonstructure = self::build_grading_json_structure($context);

        $prompt = self::GRADING_SYSTEM_PROMPT;
        if ($authorship) {
            $prompt .= self::GRADING_AUTHORSHIP_INSTRUCTION;
        }

        $data = [
            'context'    => $contextblock,
            'submission' => $submissionblock,
        ];
        if ($authorship) {
            $initial = isset($authorship->initial_confidence) ? (float) $authorship->initial_confidence : null;
            $quizgrade = isset($authorship->quiz_grade) ? (float) $authorship->quiz_grade : null;
            $final = isset($authorship->final_confidence) ? (float) $authorship->final_confidence : null;
            $lines = ['Initial confidence (0–100, human vs AI): ' . ($initial !== null ? round($initial, 1) : '—')];
            if ($quizgrade !== null || $final !== null) {
                $lines[] = 'Verification test score (0–100): ' . ($quizgrade !== null ? round($quizgrade, 1) : '—');
                $lines[] = 'Final confidence after test (0–100): ' . ($final !== null ? round($final, 1) : '—');
            }
            $data['authorship'] = implode("\n", $lines);
        }

        $submissionlabel = 'SUBMISSION:';
        if (!empty($uploadfiles)) {
            $submissionlabel .= "\nThe submission content is in the attached file(s). Use the file_search tool to retrieve and read the file(s), then base your grading on that content.";
            // Do not include the "content not extracted" placeholder—it instructs the model to refuse. List filenames only.
            $filenames = array_map(static function (\stored_file $f): string {
                return $f->get_filename();
            }, $uploadfiles);
            $data['submission'] = 'Attached file(s): ' . implode(', ', $filenames) . '.';
        }
        $usertext = "CONTEXT:\n" . $data['context'] . "\n\n" . $submissionlabel . "\n" . $data['submission'];
        if (!empty($data['authorship'])) {
            $usertext .= "\n\nAUTHORSHIP:\n" . $data['authorship'];
        }

        $ai = new ai('Dixeo assignment grading', [
            'assignname' => $context['name'] ?? '',
        ]);

        if (!empty($uploadfiles)) {
            return $ai->send_request_with_files(
                $prompt,
                $usertext,
                $uploadfiles,
                $jsonstructure,
                null
            );
        }

        return $ai->send_request(
            $prompt,
            $data,
            [],
            $jsonstructure,
            null
        );
    }

    /**
     * Validate AI grading result and map to the shape expected by evaluate (gradingmethod, analysis, feedback, grade?, criteria?).
     * Ensures criteria count matches, criterion IDs exist, guide scores in range, rubric levelids valid.
     *
     * @param array $context From get_assignment_context.
     * @param array $decoded Decoded JSON from grade_with_ai result->data.
     * @return array{gradingmethod: string, analysis: string, feedback: string, grade?: float, criteria?: array}|array{error: string}
     */
    protected static function validate_and_map_grading_result(array $context, array $decoded): array {
        $gradingmethod = $context['grading_method'] ?? 'simple';
        $result = [
            'gradingmethod' => $gradingmethod,
            'analysis'      => trim((string) ($decoded['analysis'] ?? '')),
            'feedback'      => trim((string) ($decoded['feedback'] ?? '')),
        ];
        $criteriactx = $context['criteria'] ?? [];
        $expectedcount = count($criteriactx);

        if ($gradingmethod === 'simple') {
            $grade = isset($decoded['grade']) ? (float) $decoded['grade'] : 0;
            $result['grade'] = max(0, min(100, $grade));
            return $result;
        }

        $rawcriteria = $decoded['criteria'] ?? [];
        if (!is_array($rawcriteria)) {
            $rawcriteria = [];
        }
        if (count($rawcriteria) !== $expectedcount) {
            if (count($rawcriteria) === 0 && $expectedcount > 0 && trim((string) ($decoded['analysis'] ?? '')) !== '') {
                // AI returned analysis/feedback but no criteria; fill default criteria so response is still usable.
                $mapped = [];
                foreach ($criteriactx as $c) {
                    $id = (int) $c['id'];
                    if ($gradingmethod === 'guide') {
                        $mapped[] = ['id' => $id, 'remark' => '', 'score' => 0];
                    } else {
                        $levelsordered = $c['levels'] ?? [];
                        $levelid = !empty($levelsordered) ? (int) $levelsordered[0]['id'] : 0;
                        $mapped[] = ['id' => $id, 'remark' => '', 'levelid' => $levelid];
                    }
                }
                $result['criteria'] = $mapped;
                return $result;
            }
            return ['error' => get_string('grading_error_criteria_count', 'plagiarism_dixeo',
                (object) ['got' => count($rawcriteria), 'expected' => $expectedcount])];
        }
        $idtoctx = [];
        foreach ($criteriactx as $c) {
            $idtoctx[(int) $c['id']] = $c;
        }
        $mapped = [];
        foreach ($rawcriteria as $idx => $item) {
            $id = isset($item['id']) ? (int) $item['id'] : 0;
            if (!isset($idtoctx[$id])) {
                // AI may return 1-based criterion index (1 = first, 2 = second, ...) instead of DB id.
                if (isset($criteriactx[$idx])) {
                    $id = (int) $criteriactx[$idx]['id'];
                } else {
                    return ['error' => get_string('grading_error_invalid_criterion', 'plagiarism_dixeo', $item['id'] ?? $idx)];
                }
            }
            $cctx = $idtoctx[$id];
            $remark = trim((string) ($item['remark'] ?? $item['comment'] ?? $item['feedback'] ?? $item['comments'] ?? ''));
            if ($gradingmethod === 'guide') {
                $maxscore = isset($cctx['maxscore']) ? (float) $cctx['maxscore'] : 0;
                $score = isset($item['score']) ? (float) $item['score'] : 0;
                $score = max(0, min($maxscore, $score));
                $mapped[] = ['id' => $id, 'remark' => $remark, 'score' => $score];
            } else {
                $levelsordered = $cctx['levels'] ?? [];
                $levelids = [];
                foreach ($levelsordered as $l) {
                    $levelids[(int) $l['id']] = true;
                }
                $levelid = isset($item['levelid']) ? (int) $item['levelid'] : 0;
                if (!isset($levelids[$levelid])) {
                    // AI may return 1-based level index (1 = first level, 2 = second, ...) instead of DB id.
                    $numlevels = count($levelsordered);
                    if ($numlevels > 0 && $levelid >= 1 && $levelid <= $numlevels) {
                        $levelid = (int) $levelsordered[$levelid - 1]['id'];
                    } else {
                        return ['error' => get_string('grading_error_invalid_level', 'plagiarism_dixeo',
                            (object) ['levelid' => $item['levelid'], 'criterionid' => $id])];
                    }
                }
                $mapped[] = ['id' => $id, 'remark' => $remark, 'levelid' => $levelid];
            }
        }
        $result['criteria'] = $mapped;
        return $result;
    }

    /**
     * Evaluate submission: dispatches by mode to review (AI) or grading (AI).
     *
     * @param array $context From get_assignment_context.
     * @param array $submissiondata From get_submission_data.
     * @param string $mode 'review' or 'grading'.
     * @param object|null $authorship Optional authorship record (for grading).
     * @return array For review: 'feedback_html' or 'error'. For grading: gradingmethod, analysis, feedback, grade/criteria.
     */
    public static function evaluate(array $context, array $submissiondata, string $mode, $authorship = null): array {
        if ($mode === self::EVALUATION_MODE_REVIEW) {
            return self::evaluate_review($context, $submissiondata);
        }
        if ($mode === self::EVALUATION_MODE_GRADING) {
            return self::evaluate_grading($context, $submissiondata, $authorship);
        }
        return ['error' => get_string('evaluation_mode_invalid', 'plagiarism_dixeo',
            (object) ['review' => self::EVALUATION_MODE_REVIEW, 'grading' => self::EVALUATION_MODE_GRADING])];
    }

    /**
     * Review mode: run AI review and return feedback_html or error.
     *
     * @param array $context
     * @param array $submissiondata
     * @return array{feedback_html?: string, error?: string}
     */
    protected static function evaluate_review(array $context, array $submissiondata): array {
        $assignmentid = (int) ($context['assignmentid'] ?? 0);
        $uploadfiles = [];
        if ($assignmentid > 0) {
            $block = self::build_submission_block_for_ai($assignmentid, $submissiondata);
            if (isset($block['error'])) {
                return ['error' => $block['error']];
            }
            $submissionblock = $block['content'];
            if (!empty($block['warning'])) {
                $submissionblock = $block['warning'] . "\n\n" . $submissionblock;
            }
            $files = $submissiondata['submission_files'] ?? [];
            $validated = self::validate_submission_files_for_upload($files);
            if (!empty($validated['valid'])) {
                $uploadfiles = $validated['valid'];
            }
        } else {
            $submissionblock = null;
        }
        try {
            $result = self::review_with_ai($context, $submissiondata, $submissionblock, $uploadfiles);
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
        if ($result->success) {
            return ['feedback_html' => $result->raw_content ?? ''];
        }
        return ['error' => $result->get_failure_message()];
    }

    /**
     * Grading mode: run AI grading and return structured result (analysis, feedback, grade/criteria) or error.
     *
     * @param array $context From get_assignment_context.
     * @param array $submissiondata From get_submission_data.
     * @param object|null $authorship Optional authorship record for grading and teacher block.
     * @return array{gradingmethod: string, analysis: string, feedback: string, grade?: float, criteria?: array}|array{error: string}
     */
    protected static function evaluate_grading(array $context, array $submissiondata, $authorship = null): array {
        $assignmentid = (int) ($context['assignmentid'] ?? 0);
        $submissionblockoverride = null;
        $uploadfiles = [];
        if ($assignmentid > 0) {
            $block = self::build_submission_block_for_ai($assignmentid, $submissiondata);
            if (isset($block['error'])) {
                return ['error' => $block['error']];
            }
            $submissionblockoverride = $block['content'];
            if (!empty($block['warning'])) {
                $submissionblockoverride = $block['warning'] . "\n\n" . $submissionblockoverride;
            }
            $files = $submissiondata['submission_files'] ?? [];
            $validated = self::validate_submission_files_for_upload($files);
            if (!empty($validated['valid'])) {
                $uploadfiles = $validated['valid'];
            }
        }
        try {
            $result = self::grade_with_ai($context, $submissiondata, $authorship, $submissionblockoverride, $uploadfiles);
        } catch (\Throwable $e) {
            return ['error' => $e->getMessage()];
        }
        if (!$result->success) {
            return ['error' => $result->get_failure_message()];
        }
        if (empty($result->data) || !is_array($result->data)) {
            return ['error' => get_string('grading_error_response_invalid', 'plagiarism_dixeo')];
        }
        $mapped = self::validate_and_map_grading_result($context, $result->data);
        if (isset($mapped['error'])) {
            return $mapped;
        }
        // Do not append authorship to grading analysis/feedback; it is displayed elsewhere.
        return $mapped;
    }

    /**
     * Return content and itemid for authorship check (get_{area}_authorship_content contract).
     * Id can be course module id (then latest submission is used) or assign_submission id (that attempt).
     *
     * @param int $id Course module id or assign_submission id.
     * @param int $userid User id.
     * @return array{text: string, itemid: int, files?: \stored_file[]}
     */
    public static function get_submission_authorship_content(int $id, int $userid): array {
        global $DB;

        $submissionid = null;
        $cmid = $id;
        $sub = $DB->get_record('assign_submission', ['id' => $id, 'userid' => $userid], '*', IGNORE_MISSING);
        if ($sub) {
            $submissionid = (int) $sub->id;
            $cm = get_coursemodule_from_instance('assign', (int) $sub->assignment, 0, false, IGNORE_MISSING);
            $cmid = $cm && isset($cm->id) ? (int) $cm->id : 0;
        }
        if ($cmid <= 0) {
            $cm = get_coursemodule_from_id('assign', $id, 0, false, IGNORE_MISSING);
            $cmid = $cm && isset($cm->id) ? (int) $cm->id : 0;
        }

        $data = self::get_submission_data($cmid, $userid, $submissionid);
        $sub = $data['submission'] ?? null;
        $itemid = ($sub && isset($sub->id)) ? (int) $sub->id : 0;

        $assignmentid = 0;
        if ($cmid > 0) {
            $cm = get_coursemodule_from_id('assign', $cmid, 0, false, IGNORE_MISSING);
            if ($cm && $cm->instance) {
                $assignmentid = (int) $cm->instance;
            }
        }

        if ($assignmentid <= 0) {
            return ['text' => (string) ($data['onlinetext'] ?? ''), 'itemid' => $itemid];
        }

        $block = self::build_submission_block_for_ai($assignmentid, $data);
        if (isset($block['error'])) {
            return ['text' => '', 'itemid' => $itemid];
        }

        $out = [
            'text'   => (string) ($block['content'] ?? ''),
            'itemid' => $itemid,
        ];
        $files = $data['submission_files'] ?? [];
        $validated = self::validate_submission_files_for_upload($files);
        if (!empty($validated['valid'])) {
            $out['files'] = $validated['valid'];
        }
        return $out;
    }

    /**
     * Append authorship verification block to teacher analysis (bold headings at the bottom).
     *
     * @param string $analysis Existing analysis HTML/text.
     * @param object|null $authorship Authorship record with initial_confidence, quiz_grade, final_confidence.
     * @return string Analysis with authorship block appended.
     */
    protected static function append_authorship_block_to_analysis(string $analysis, $authorship): string {
        if (!$authorship) {
            return $analysis;
        }
        $initial = isset($authorship->initial_confidence) ? (float) $authorship->initial_confidence : null;
        $quizgrade = isset($authorship->quiz_grade) ? (float) $authorship->quiz_grade : null;
        $final = isset($authorship->final_confidence) ? (float) $authorship->final_confidence : null;
        $hasquiz = $quizgrade !== null || $final !== null;

        $block = '<div style="margin-top: 1.5em; border-top: 1px solid #dee2e6; padding-top: 0.75em;">';
        $block .= '<p><strong>Authorship verification</strong></p>';
        $block .= '<p><strong>Initial score:</strong> ' . ($initial !== null ? round($initial, 1) . ' / 100' : '—') . '</p>';
        if ($hasquiz) {
            $block .= '<p><strong>Test score:</strong> ' . ($quizgrade !== null ? round($quizgrade, 1) . ' / 100' : '—') . '</p>';
            if (!empty($authorship->quiz_responses)) {
                $block .= \plagiarism_dixeo\local\authorship::render_quiz_results($authorship, []);
            } elseif (!empty($authorship->quiz_explanation)) {
                $block .= '<p><strong>Test explanation:</strong> ' . s(trim($authorship->quiz_explanation)) . '</p>';
            }
            $block .= '<p><strong>Final score:</strong> ' . ($final !== null ? round($final, 1) . ' / 100' : '—') . '</p>';
        }
        if (!empty($authorship->final_explanation)) {
            $block .= '<p><strong>Final explanation:</strong> ' . s(trim($authorship->final_explanation)) . '</p>';
        }
        $block .= '</div>';
        return $analysis . $block;
    }

    /**
     * Prepend the AI grading disclaimer to feedback comments (for teacher and student).
     *
     * @param string $feedback Raw feedback HTML from AI.
     * @return string Feedback with disclaimer prepended.
     */
    public static function add_feedback_disclaimer(string $feedback): string {
        $disclaimer = get_string('grading_ai_disclaimer', 'plagiarism_dixeo');
        $block = '<p class="plagiarism-dixeo-grading-disclaimer"><em>' . s($disclaimer) . '</em></p>';
        return $block . $feedback;
    }

    /**
     * Apply an AI grading result to the assignment (save grade and feedback to Moodle).
     * Used by automatic grading after submission. Requires mod/assign/locallib.php.
     *
     * @param int $cmid Course module id.
     * @param int $userid User id (student being graded).
     * @param int $submissionid Assign submission id.
     * @param array $result Keys: gradingmethod, feedback; optional grade, criteria.
     * @return bool True if save_grade succeeded.
     */
    public static function apply_grade_result(int $cmid, int $userid, int $submissionid, array $result): bool {
        global $CFG, $DB;

        $cm = get_coursemodule_from_id('assign', $cmid, 0, true, MUST_EXIST);
        $course = get_course($cm->course);
        $context = context_module::instance($cm->id);

        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $submission = $DB->get_record('assign_submission', [
            'id'      => $submissionid,
            'userid'  => $userid,
        ], 'attemptnumber', IGNORE_MISSING);
        if (!$submission) {
            return false;
        }

        $assign = new \assign($context, $cm, $course);

        $data = new \stdClass();
        $data->attemptnumber = (int) $submission->attemptnumber;
        $data->assignfeedbackcomments_editor = [
            'text'   => self::add_feedback_disclaimer((string) ($result['feedback'] ?? '')),
            'format' => FORMAT_HTML,
        ];

        if (($result['gradingmethod'] ?? '') === 'simple') {
            $data->grade = isset($result['grade']) ? (float) $result['grade'] : null;
        } else if (($result['gradingmethod'] ?? '') === 'guide' && !empty($result['criteria'])) {
            $data->advancedgrading = ['criteria' => []];
            foreach ($result['criteria'] as $c) {
                $id = (int) ($c['id'] ?? 0);
                $data->advancedgrading['criteria'][$id] = [
                    'score'  => (float) ($c['score'] ?? 0),
                    'remark' => (string) ($c['remark'] ?? ''),
                ];
            }
        } else if (($result['gradingmethod'] ?? '') === 'rubric' && !empty($result['criteria'])) {
            $data->advancedgrading = ['criteria' => []];
            foreach ($result['criteria'] as $c) {
                $id = (int) ($c['id'] ?? 0);
                $data->advancedgrading['criteria'][$id] = [
                    'levelid' => (int) ($c['levelid'] ?? 0),
                    'remark'  => (string) ($c['remark'] ?? ''),
                ];
            }
        }

        $data->sendstudentnotifications = true;

        try {
            $assign->save_grade($userid, $data);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
