# Security and privacy evidence — plagiarism_dixeo

Evidence prepared for the MindFree plugin assessment, organised by the release checklist in
section 8 of the MindFree secure development kit v1.0. Every claim below points at a file and
function so it can be checked against the code.

Component: `plagiarism_dixeo`
Purpose: submits assignment work to the Dixeo service for coaching feedback, authorship
confidence scoring and proposed grades, and shows the results to students and teachers.

## Architecture and external integration

All traffic to the Dixeo service goes through `local_dixeo`. This plugin holds no endpoint
URL, no credential and no HTTP client of its own; it calls `local_dixeo\external\service_factory`
and implements `local_dixeo\service\assign_ux_persistence_interface` so the service layer can
persist results without knowing where they are stored.

| Concern | Owner |
| --- | --- |
| API transport, authentication, job polling | `local_dixeo` |
| Prompt construction and result mapping | `local_dixeo\service\assign_ux_service` |
| Tables, events, capabilities, UI | this plugin |

Dependencies: `local_dixeo` (declared in `version.php` under `$plugin->dependencies`). No
bundled third-party libraries, no runtime code download.

## 1. Authentication at every endpoint

| Entry point | Control |
| --- | --- |
| `settings.php` | `require_login()`, `admin_externalpage_setup('plagiarismdixeo')`, `require_capability('moodle/site:config', …)` |
| All web services | `loginrequired => true` in `db/services.php`, plus the per-function check below |
| `classes/task/*` | Run under cron; the queuing observer records the student in `set_userid()` |

The plugin exposes no other web-reachable script: `lib.php` and `version.php` are libraries.

## 2. Course and module access

`classes/external/assign_access.php` is the single place that resolves a course module and
establishes access:

- `require_student_analyze(int $cmid)` — loads the `assign` course module, calls
  `require_login($course, false, $cm)`, returns the module context.
- `require_teacher_grade(int $cmid)` — the same, then requires `mod/assign:grade`.
- `require_student_analyze_from_authorship(\stdClass $record)` — resolves the course module
  from the stored `assign_submission` row rather than from anything the caller sent, so a
  record id from another course cannot be redirected into this one.

Covered by `tests/assign_access_test.php` and `tests/external_authorisation_test.php`
(`test_pre_submission_refuses_a_course_module_from_another_course`).

## 3. Capability checks

| Action | Capability |
| --- | --- |
| Request analysis, sit the verification quiz | `plagiarism/dixeo:mod_assign_pre_submission` plus enrolment |
| Request grading assistance, read a draft grade | `plagiarism/dixeo:mod_assign_grade_submission` and `mod/assign:grade` |
| Change site settings | `moodle/site:config` |
| See another student's score in the panel | `mod/assign:grade`, checked in `plagiarism_plugin_dixeo::get_links()` |

`verification_access::require_own_open_quiz()` additionally requires that the caller owns the
quiz record and that the quiz has not already been completed — a teacher cannot sit a
student's quiz, and a student cannot reopen a finished one.

Unauthorised paths are tested per endpoint in `tests/external_authorisation_test.php`,
`tests/verification_externals_test.php` and `tests/draft_grading_test.php`.

## 4. Untrusted input

- No superglobal is read anywhere in the plugin.
- Web service parameters are declared with `PARAM_INT`, `PARAM_ALPHA` or `PARAM_RAW` and
  passed through `validate_parameters()`.
- JSON from the browser is decoded by `quiz_session::decode_payload()` with
  `JSON_THROW_ON_ERROR`, a 64 KB size cap and a depth limit, then checked for shape.
- `quiz_session::validate_answers()` discards answers for questions that were not asked and
  rejects non-scalar or over-long values; `validate_timings()` clamps reported times.
- Settings are validated against closed lists and numeric ranges in
  `plagiarism_dixeo_setup_form::validation()`; `get_allowed_values()` is the single source of
  the permitted values.
- Per-activity settings are saved only from validated form data in
  `plagiarism_dixeo_coursemodule_edit_post_actions()`, and each value is checked against
  `plagiarism_plugin_dixeo::get_activity_settings()` before it is stored.
- State-changing actions arrive either through Moodle forms (`moodleform`, which carries a
  sesskey) or through the AJAX web service layer, which requires a sesskey of its own. The
  plugin defines no bespoke GET action.

## 5. Output escaping

| Output | Treatment |
| --- | --- |
| Model prose stored by the plugin | `plagiarism_plugin_dixeo::format_explanation()` → `format_text(…, FORMAT_PLAIN, ['context' => …])` |
| Student disclosure | `format_text(…, FORMAT_MOODLE, ['context' => …])` in `print_disclosure()` |
| Live review and grading HTML | `format_text(…, FORMAT_HTML, ['context' => …])` in `get_assign_job_status` |
| Stored draft grade | `format_text(…, FORMAT_HTML, ['context' => …])` in `get_grade_draft::execute()` |
| Panel titles and labels | Escaped by Mustache (`{{title}}`) |

`templates/panels.mustache` and `templates/html_fragment.mustache` use a raw section only for
values that have already been through `format_text()`. `amd/src/panel.js` and the draft notice
in `amd/src/mod_assign_grade_submission.js` write text with `textContent`, never `innerHTML`.

Tested in `tests/panels_test.php` (markup in a stored explanation is escaped) and
`tests/draft_grading_test.php::test_markup_in_a_draft_is_purified_before_it_reaches_the_grader`.

## 6. Database access

Every write goes through the DML API with condition arrays and record objects, in
`classes/authorship/author_storage.php`, `classes/feedback/presubmission_feedback_storage.php`
and `classes/feedback/grade_feedback_storage.php`. No SQL string is assembled from caller
input. The only hand-written SQL is in `classes/privacy/provider.php`, where every value is a
named placeholder and user id lists are built with `$DB->get_in_or_equal()`.

Schema is declared in `db/install.xml`; upgrade steps in `db/upgrade.php` are guarded with
`field_exists()`, `table_exists()` and `index_exists()`.

## 7. Shell commands

The plugin executes no external process. There is no call to `exec`, `shell_exec`, `system`,
`passthru` or `proc_open`.

## 8. Logging

Six events in `classes/event/`, each carrying the module context and the student as
`relateduserid`:

| Event | Triggered from |
| --- | --- |
| `analysis_requested` | `task\analyze_submission::execute()` |
| `authorship_checked` | `task\analyze_submission::store_authorship()` |
| `verification_started` | `external\start_verification::execute()` |
| `verification_submitted` | `external\verify_authorship::execute()`, recording whether the quiz had expired |
| `grade_draft_created` | `task\stage_draft_grade::execute()` |
| `grade_draft_applied` | `observer::submission_graded()` |

Confidence scores are deliberately kept out of the event payloads: the log records that an
assessment happened and against whom, not what it concluded, so the standard log does not
become a second copy of the academic judgement. Scores live in the plugin's own tables, which
the Privacy API covers.

Covered by `tests/events_test.php`.

## 9. Personal data

Three plugin-owned tables, all keyed by `userid` and an `assign_submission` id:

| Table | Contents |
| --- | --- |
| `plagiarism_dixeo_authorship` | Confidence scores, verification quiz, the student's answers and timings |
| `plagiarism_dixeo_presubmission_feedback` | Coaching feedback shown before submitting |
| `plagiarism_dixeo_grade_feedback` | Proposed grade and feedback awaiting a teacher |

`plagiarism_dixeo_config` holds per-activity settings only and contains no personal data.

`classes/privacy/provider.php` implements `metadata\provider`, `core_userlist_provider` and
`plugin\provider`. It declares all three tables field by field, declares the Dixeo service as
an external transfer via `add_external_location_link()`, and maps records to **module**
contexts, so deleting an activity or a course purges them rather than leaving orphans.

Retention: a record is tied to the submission it describes and is deleted when the submission
is edited (`observer::submission_updated()`), when the activity is deleted, or when the user
is deleted through the Privacy API.

Covered by `tests/privacy_provider_test.php` (metadata, per-user export, per-user deletion,
per-context deletion, user lists).

## Secure defaults

| Setting | Default | Effect |
| --- | --- | --- |
| `enabled` | `0` | The plugin does nothing until an administrator switches it on |
| `enable_mod_assign` | `0` | Assignments are not covered until opted in |
| `use_dixeo` (per activity) | `0` | No Analyze UI, web services, panels, or automatic analysis unless the activity is opted in |
| `enable_auto_grading` (per activity) | `0` | No draft grade is prepared unless a teacher asks for one |
| `assign_trigger` | `submit` | Drafts are not sent on every keystroke-level save |

Automatic analysis additionally requires `$CFG->enableplagiarism`. All five conditions are
re-checked inside the task, not only when the event fires, so turning the plugin off stops
work that is already queued (`automation\analysis_gate`, exercised by `tests/automation_test.php`).

## Failure handling

Observers never call the API; they only queue an adhoc task, so an unavailable service cannot
block a student from submitting. Tasks write through the storage layer inside a transaction
and rethrow on failure so Moodle retries them and no partial state is committed
(`task\analyze_submission`, `task\stage_draft_grade`). Records that never reach a conclusion
are swept up by `task\retry_pending_analysis`.

Errors returned to the browser go through `local_dixeo\external\response_factory::safe_message()`,
which withholds the raw exception unless developer debugging is on
(`tests/external_safe_errors_test.php`).

## Automated verification

- `php local/codechecker/vendor/bin/phpcs --standard=moodle plagiarism/dixeo/` — clean.
- `php vendor/bin/phpunit --testsuite plagiarism_dixeo_testsuite` — 128 tests, 328 assertions.
