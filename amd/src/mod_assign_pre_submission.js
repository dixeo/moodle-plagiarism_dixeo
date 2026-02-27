/**
 * Mod assign pre-submission: adds an "Analyze" step on assign view page (mod/assign/view.php?id=…&action=view).
 * Uses course module id from URL only; submission data is loaded server-side via get_submission_data (current user).
 *
 * @module     plagiarism_dixeo/mod_assign_pre_submission
 * @author     Josemaria Bolanos <admin@mako.digital>
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax', 'core/notification', 'core/str', 'core/templates', 'core/loadingicon'],
function($, Ajax, Notification, Str, Templates, LoadingIcon) {

    /**
     * Find the Submit assignment form: form with action mod/assign/view.php and hidden input action=submit.
     * Returns null if not present (e.g. on action=view when already submitted).
     *
     * @returns {HTMLFormElement|null}
     */
    function findAssignForm() {
        var forms = document.querySelectorAll('form[action*="mod/assign/view.php"]');
        for (var i = 0; i < forms.length; i++) {
            var input = forms[i].querySelector('input[name="action"][value="submit"]');
            if (input) {
                return forms[i];
            }
        }
        return null;
    }

    /**
     * Get a query parameter from the current page URL.
     *
     * @param {string} name Parameter name.
     * @returns {string} Value or empty string.
     */
    function getUrlParam(name) {
        var params = new URLSearchParams(window.location.search);
        return params.get(name) || '';
    }

    var LOADING_MODAL_ID = 'plagiarism_dixeo_loading_modal';
    var RESPONSE_CONTAINER_ID = 'plagiarism_dixeo_submission_response';

    /** Whether the form has already passed authorship check (submit allowed without re-check). */
    var submissionAuthorized = false;

    /**
     * Remove any previous analysis response so the next one is shown alone.
     */
    function clearPreviousResponse() {
        var container = document.getElementById(RESPONSE_CONTAINER_ID);
        if (container && container.parentNode) {
            container.parentNode.removeChild(container);
        }
    }

    /**
     * Hide the loading modal.
     */
    function hideLoadingModal() {
        var el = document.getElementById(LOADING_MODAL_ID);
        if (el) {
            $(el).modal('hide');
        }
    }

    /**
     * Show the loading modal (spinner + message). Creates once and reuses; updates message when reused. Locked.
     * Resolves after the modal has finished showing.
     *
     * @param {string} message Text to show below the spinner (e.g. "Analyzing...", "Verifying...").
     * @returns {Promise<jQuery>}
     */
    function showLoadingModal(message) {
        var existing = document.getElementById(LOADING_MODAL_ID);
        if (existing) {
            var textEl = existing.querySelector('.plagiarism-dixeo-modal-text');
            if (textEl) {
                textEl.textContent = message;
            }
            var $existing = $(existing);
            return new Promise(function(resolve) {
                $existing.one('shown.bs.modal', function() {
                    resolve($existing);
                });
                $existing.modal('show');
            });
        }
        return Templates.render('plagiarism_dixeo/loading_modal', { message: message }).then(function(html, js) {
            var wrapper = document.createElement('div');
            wrapper.innerHTML = html.trim();
            var modal = wrapper.firstChild;
            document.body.appendChild(modal);
            if (js) {
                Templates.runTemplateJS(js);
            }
            LoadingIcon.addIconToContainer(modal.querySelector('.plagiarism-dixeo-modal-spinner'));
            var $modal = $(modal);
            return new Promise(function(resolve) {
                $modal.one('shown.bs.modal', function() {
                    resolve($modal);
                });
                $modal.modal({ backdrop: 'static', keyboard: false });
            });
        });
    }

    /**
     * Run the pre-submission behavior: add Analyze button (id from URL), submit listener if form present,
     * and verification modal trigger for badges (verification required / in progress).
     */
    function run() {
        var cmid = getUrlParam('id').trim();
        if (!cmid) {
            return;
        }

        // Delegate: open verification modal when user clicks the badge (verification required / in progress).
        $(document).off('click.plagiarism_dixeo_verification', '.plagiarism-dixeo-open-verification-modal');
        $(document).on('click.plagiarism_dixeo_verification', '.plagiarism-dixeo-open-verification-modal', function(e) {
            e.preventDefault();
            var recordId = parseInt($(this).data('record-id'), 10) || 0;
            if (recordId > 0) {
                openVerificationModal(recordId);
            }
        });

        var form = findAssignForm();
        if (!form) {
            return;
        }
        var submitButton = form.querySelector('button[type="submit"], input[type="submit"]');
        var buttonContainer = submitButton ? submitButton.parentNode : null;
        if (!buttonContainer) {
            var regionMain = document.getElementById('region-main');
            if (regionMain) {
                var wrapper = document.createElement('div');
                wrapper.className = 'local-dixeo-ux-analyze-toolbar mb-3';
                buttonContainer = wrapper;
                regionMain.insertBefore(wrapper, regionMain.firstChild);
            } else {
                return;
            }
        }

        Str.get_string('analyze', 'plagiarism_dixeo').then(function(analyzeLabel) {
            var analyzeButton = document.createElement('button');
            analyzeButton.type = 'button';
            analyzeButton.className = 'btn btn-primary mr-1';
            analyzeButton.id = 'plagiarism_dixeo_analyze_btn';

            var img = document.createElement('img');
            img.src = (typeof M !== 'undefined' && M.cfg && M.cfg.wwwroot)
                ? M.cfg.wwwroot + '/plagiarism/dixeo/pix/dixeo-white.svg'
                : '';
            img.alt = '';
            img.width = 20;
            img.height = 20;
            analyzeButton.appendChild(img);
            analyzeButton.appendChild(document.createTextNode(' ' + analyzeLabel));

            if (submitButton) {
                buttonContainer.insertBefore(analyzeButton, submitButton);
            } else {
                buttonContainer.appendChild(analyzeButton);
            }
            attachAnalyzeHandler(analyzeButton, parseInt(cmid, 10));
            if (form) {
                attachSubmitHandler(form, cmid);
            }
        }).catch(function() {
            // If strings fail, still allow normal submit.
        });
    }

    /**
     * Attach click handler to the Analyze button. Calls web service with course module id only.
     *
     * @param {HTMLButtonElement} analyzeButton
     * @param {number} cmid Course module ID from URL.
     */
    function attachAnalyzeHandler(analyzeButton, cmid) {
        analyzeButton.addEventListener('click', function() {
            clearPreviousResponse();
            analyzeButton.disabled = true;

            Str.get_string('analyzing', 'plagiarism_dixeo').then(function(analyzingMsg) {
                return showLoadingModal(analyzingMsg);
            }).then(function() {
                Ajax.call([{
                    methodname: 'plagiarism_dixeo_mod_assign_pre_submission',
                    args: { id: cmid },
                    done: function(html) {
                        var container = document.createElement('div');
                        container.id = RESPONSE_CONTAINER_ID;
                        container.innerHTML = html;
                        var header = document.getElementById('id_submissionheader');
                        var form = findAssignForm();
                        var regionMain = document.getElementById('region-main');
                        if (header) {
                            header.parentNode.insertBefore(container, header.nextSibling);
                        } else if (form) {
                            form.insertBefore(container, form.firstChild);
                        } else if (regionMain) {
                            regionMain.insertBefore(container, regionMain.firstChild);
                        } else {
                            document.body.appendChild(container);
                        }
                        analyzeButton.disabled = false;
                        hideLoadingModal();
                    },
                    fail: function(err) {
                        Notification.exception(err);
                        analyzeButton.disabled = false;
                        hideLoadingModal();
                    }
                }]);
            }).catch(function(err) {
                Notification.exception(err);
                analyzeButton.disabled = false;
            });
        });
    }

    /**
     * Attach submit handler: first submit runs check_authorship; then either direct submit or quiz modal.
     *
     * @param {HTMLFormElement} form
     * @param {string} cmid Course module ID from URL.
     */
    function attachSubmitHandler(form, cmid) {
        form.addEventListener('submit', function(e) {
            if (submissionAuthorized) {
                return;
            }
            e.preventDefault();

            var formId = cmid;

            Str.get_string('authorship_verifying', 'plagiarism_dixeo').then(function(verifyingMsg) {
                return showLoadingModal(verifyingMsg);
            }).then(function() {
                Ajax.call([{
                    methodname: 'plagiarism_dixeo_check_authorship',
                    args: {
                        component: 'mod_assign',
                        area: 'submission',
                        id: parseInt(formId, 10) || 0
                    },
                    done: function(data) {
                        hideLoadingModal();
                        var recordId = (data && data.record_id) ? parseInt(data.record_id, 10) : 0;
                        if (recordId === 0) {
                            submissionAuthorized = true;
                            form.submit();
                            return;
                        }
                        openVerificationModal(recordId, form);
                    },
                    fail: function(err) {
                        hideLoadingModal();
                        Notification.exception(err);
                    }
                }]);
            }).catch(function(err) {
                hideLoadingModal();
                Notification.exception(err);
            });
        });
    }

    var VERIFICATION_MODAL_ID = 'plagiarism_dixeo_verification_modal';
    var QUESTION_TYPE_TRUE_FALSE = 'true_false';
    var QUESTION_TYPE_MULTIPLE_CHOICE = 'multiple_choice';

    /* eslint-disable jsdoc/require-jsdoc, jsdoc/require-param, max-len */
    /**
     * Open the verification quiz modal: load via get_verification_quiz, show instructions or questions.
     *
     * @param {number} recordId Authorship record id.
     * @param {HTMLFormElement|null} formOptional Assign form if opened from submit (for submissionAuthorized after close).
     */
    function openVerificationModal(recordId, formOptional) {
        Str.get_string('authorship_verifying', 'plagiarism_dixeo').then(function(loadingMsg) {
            return showLoadingModal(loadingMsg);
        }).then(function() {
            return Ajax.call([{
                methodname: 'plagiarism_dixeo_get_verification_quiz',
                args: { record_id: recordId },
                done: function(data) {
                    hideLoadingModal();
                    var submitted = data.submitted === true;
                    var questions = [];
                    try {
                        questions = typeof data.questions === 'string' ? JSON.parse(data.questions) : (data.questions || []);
                    } catch (e) {
                        questions = [];
                    }
                    var settings = {};
                    try {
                        settings = typeof data.settings === 'string' ? JSON.parse(data.settings) : (data.settings || {});
                    } catch (e) {
                        settings = {};
                    }
                    var instructions = { intro: '', lines: [] };
                    try {
                        instructions = typeof data.instructions === 'string'
                            ? JSON.parse(data.instructions) : (data.instructions || instructions);
                    } catch (e) {}
                    var state = null;
                    if (data.state) {
                        try {
                            state = typeof data.state === 'string' ? JSON.parse(data.state) : data.state;
                        } catch (e) {}
                    }
                    if (submitted) {
                        showVerificationSubmittedModal();
                        return;
                    }
                    if (questions.length === 0) {
                        Str.get_string('error_no_quiz_available', 'plagiarism_dixeo').then(function(msg) {
                            Notification.addNotification({ message: msg, type: 'error' });
                        });
                        return;
                    }
                    showVerificationQuizModal(
                        recordId, questions, settings, instructions, state, formOptional
                    );
                },
                fail: function(err) {
                    hideLoadingModal();
                    Notification.exception(err);
                }
            }]);
        }).catch(function(err) {
            hideLoadingModal();
            Notification.exception(err);
        });
    }

    /**
     * Show a simple modal with "Test submitted" and Close. On close, reload to refresh badge.
     */
    function showVerificationSubmittedModal() {
        var modal = document.getElementById(VERIFICATION_MODAL_ID);
        if (modal && modal.parentNode) {
            modal.parentNode.removeChild(modal);
        }
        Promise.all([
            Str.get_string('verification_test_submitted', 'plagiarism_dixeo'),
            Str.get_string('verification_test_submitted_message', 'plagiarism_dixeo'),
            Str.get_string('verification_close', 'plagiarism_dixeo')
        ]).then(function(arr) {
            var strings = { title: arr[0], body: arr[1], close: arr[2] };
            var wrap = document.createElement('div');
            wrap.id = VERIFICATION_MODAL_ID;
            wrap.className = 'modal fade';
            wrap.setAttribute('role', 'dialog');
            wrap.innerHTML =
                '<div class="modal-dialog" role="document">' +
                '  <div class="modal-content">' +
                '    <div class="modal-header"><h5 class="modal-title">' + strings.title + '</h5></div>' +
                '    <div class="modal-body">' + strings.body + '</div>' +
                '    <div class="modal-footer">' +
                '      <button type="button" class="btn btn-primary plagiarism-dixeo-verification-close">' +
                strings.close + '</button>' +
                '    </div>' +
                '  </div>' +
                '</div>';
            document.body.appendChild(wrap);
            var $m = $(wrap);
            wrap.querySelector('.plagiarism-dixeo-verification-close').addEventListener('click', function() {
                $m.modal('hide');
            });
            $m.on('hidden.bs.modal', function() {
                if (wrap.parentNode) {
                    wrap.parentNode.removeChild(wrap);
                }
                location.reload();
            });
            $m.modal({ backdrop: 'static', keyboard: false });
        }).catch(function() {
            Notification.addNotification({ message: 'Error loading strings.', type: 'error' });
        });
    }

    /**
     * Show the verification quiz modal: instructions (with Start/Cancel) or question screens.
     * @param {number} recordId Authorship record id.
     * @param {Array} questions Quiz questions (no correct answers).
     * @param {Object} settings Timer/navigation settings.
     * @param {Object} instructions Intro and lines.
     * @param {Object|null} state Restored state if in progress.
     * @param {HTMLFormElement|null} formOptional Assign form if opened from submit.
     */
    function showVerificationQuizModal(recordId, questions, settings, instructions, state, formOptional) {
        var modal = document.getElementById(VERIFICATION_MODAL_ID);
        if (modal && modal.parentNode) {
            modal.parentNode.removeChild(modal);
        }
        Promise.all([
            Str.get_string('authorship_cancel', 'plagiarism_dixeo'),
            Str.get_string('verification_instructions_start', 'plagiarism_dixeo'),
            Str.get_string('verification_previous', 'plagiarism_dixeo'),
            Str.get_string('verification_next', 'plagiarism_dixeo'),
            Str.get_string('verification_submit', 'plagiarism_dixeo'),
            Str.get_string('verification_instructions_unlimited', 'plagiarism_dixeo'),
            Str.get_string('true', 'plagiarism_dixeo'),
            Str.get_string('false', 'plagiarism_dixeo')
        ]).then(function(arr) {
            var uiStrings = {
                cancel: arr[0], start: arr[1], previous: arr[2], next: arr[3],
                submit: arr[4], unlimited: arr[5], trueLabel: arr[6], falseLabel: arr[7]
            };
        var totalTimeSec = parseInt(settings.total_time_sec, 10) || 0;
        var allowBackwards = settings.allow_backwards === true || settings.allow_backwards === '1';
        var nextDelaySec = parseInt(settings.next_button_delay_sec, 10) || 3;
        var timeTf = parseInt(settings.time_true_false_sec, 10) || 60;
        var timeMc = parseInt(settings.time_multiple_choice_sec, 10) || 60;
        var timeOpen = parseInt(settings.time_open_sec, 10) || 180;

        var ctx = {
            strings: uiStrings,
            recordId: recordId,
            formOptional: formOptional || null,
            questions: questions,
            settings: settings,
            totalTimeSec: totalTimeSec,
            allowBackwards: allowBackwards,
            nextDelaySec: nextDelaySec,
            timeTf: timeTf,
            timeMc: timeMc,
            timeOpen: timeOpen,
            responses: {},
            timings: { test_started_at: 0, current_index: 0, questions: {} },
            currentIndex: 0,
            visited: {},
            nextUnlockAt: 0,
            timerInterval: null
        };
        if (state && state.test_started_at) {
            ctx.timings = state.response_timings || ctx.timings;
            if (!ctx.timings.questions || typeof ctx.timings.questions !== 'object') {
                ctx.timings.questions = {};
            }
            ctx.responses = state.quiz_responses || {};
            ctx.currentIndex = parseInt(state.current_question_index, 10) || 0;
            if (ctx.currentIndex < 0 || ctx.currentIndex >= questions.length) {
                ctx.currentIndex = 0;
            }
            ctx.timings.test_started_at = state.test_started_at;
            for (var i = 0; i <= ctx.currentIndex && i < questions.length; i++) {
                ctx.visited[i] = true;
            }
            var qkey = 'q' + ctx.currentIndex;
            if (!ctx.timings.questions[qkey] || !ctx.timings.questions[qkey].started_at) {
                if (!ctx.timings.questions[qkey]) {
                    ctx.timings.questions[qkey] = {};
                }
                ctx.timings.questions[qkey].started_at = ctx.currentIndex === 0
                    ? ctx.timings.test_started_at
                    : Math.floor(Date.now() / 1000);
            }
            ctx.timings.current_index = ctx.currentIndex;
        }

        var modalEl = document.createElement('div');
        modalEl.id = VERIFICATION_MODAL_ID;
        modalEl.className = 'modal fade';
        modalEl.setAttribute('role', 'dialog');
        modalEl.innerHTML =
            '<div class="modal-dialog modal-lg" role="document">' +
            '  <div class="modal-content">' +
            '    <div class="modal-header plagiarism-dixeo-verification-header">' +
            '      <h5 class="modal-title plagiarism-dixeo-verification-title"></h5>' +
            '      <span class="plagiarism-dixeo-verification-counter-wrap">' +
            '        <span class="plagiarism-dixeo-verification-donut" aria-hidden="true"></span>' +
            '        <span class="plagiarism-dixeo-verification-counter" aria-hidden="true"></span>' +
            '      </span>' +
            '    </div>' +
            '    <div class="modal-body plagiarism-dixeo-verification-body"></div>' +
            '    <div class="modal-footer plagiarism-dixeo-verification-footer"></div>' +
            '  </div>' +
            '</div>';
        document.body.appendChild(modalEl);

        var titleEl = modalEl.querySelector('.plagiarism-dixeo-verification-title');
        var counterWrapEl = modalEl.querySelector('.plagiarism-dixeo-verification-counter-wrap');
        var counterEl = modalEl.querySelector('.plagiarism-dixeo-verification-counter');
        var donutEl = modalEl.querySelector('.plagiarism-dixeo-verification-donut');
        var bodyEl = modalEl.querySelector('.plagiarism-dixeo-verification-body');
        var footerEl = modalEl.querySelector('.plagiarism-dixeo-verification-footer');

        function showScreen(screen) {
            if (screen === 'instructions') {
                var heading = instructions.heading || '';
                var intro = instructions.intro || '';
                var lines = instructions.lines || [];
                var html = '<div class="plagiarism-dixeo-instructions">';
                if (heading) {
                    html += '<h6 class="plagiarism-dixeo-instructions__heading">' +
                        (heading.replace(/</g, '&lt;').replace(/>/g, '&gt;')) + '</h6>';
                }
                if (intro) {
                    html += '<p class="plagiarism-dixeo-instructions__intro">' +
                        (intro.replace(/</g, '&lt;').replace(/>/g, '&gt;')) + '</p>';
                }
                if (lines.length > 0) {
                    html += '<ul class="plagiarism-dixeo-instructions__rules">';
                    lines.forEach(function(line) {
                        html += '<li>' + (line.replace(/</g, '&lt;').replace(/>/g, '&gt;')) + '</li>';
                    });
                    html += '</ul>';
                }
                html += '</div>';
                bodyEl.innerHTML = html;
                if (counterWrapEl) {
                    counterWrapEl.style.display = 'none';
                }
                footerEl.innerHTML =
                    '<button type="button" class="btn btn-secondary plagiarism-dixeo-verification-cancel">' +
                    ctx.strings.cancel + '</button>' +
                    '<button type="button" class="btn btn-primary plagiarism-dixeo-verification-start">' +
                    ctx.strings.start + '</button>';
                footerEl.querySelector('.plagiarism-dixeo-verification-cancel').addEventListener('click', function() {
                    $(modalEl).modal('hide');
                });
                footerEl.querySelector('.plagiarism-dixeo-verification-start').addEventListener('click', function() {
                    startVerificationAndShowFirstQuestion();
                });
                return;
            }
            if (screen === 'question') {
                renderQuestionScreen(ctx, titleEl, counterEl, donutEl, counterWrapEl, bodyEl, footerEl, modalEl);
                return;
            }
        }

        function startVerificationAndShowFirstQuestion() {
            Ajax.call([{
                methodname: 'plagiarism_dixeo_start_verification',
                args: { record_id: recordId },
                done: function(data) {
                    if (data.success && data.test_started_at) {
                        ctx.timings.test_started_at = data.test_started_at;
                        ctx.currentIndex = 0;
                        ctx.visited[0] = true;
                        ctx.timings.current_index = 0;
                        ctx.timings.questions['q' + ctx.currentIndex] = { started_at: data.test_started_at, ended_at: null, seconds: 0 };
                        showScreen('question');
                        startTimers(ctx, bodyEl, footerEl, modalEl);
                    }
                },
                fail: function(err) {
                    Notification.exception(err);
                }
            }]);
        }

        if (state && state.test_started_at) {
            Str.get_string('authorship_quiz_modal_title', 'plagiarism_dixeo').then(function(t) {
                titleEl.textContent = t;
                showScreen('question');
                startTimers(ctx, bodyEl, footerEl, modalEl);
            });
        } else {
            Str.get_string('authorship_quiz_modal_title', 'plagiarism_dixeo').then(function(t) {
                titleEl.textContent = t;
                showScreen('instructions');
            });
        }

        $(modalEl).on('hidden.bs.modal', function() {
            if (ctx.timerInterval) {
                clearInterval(ctx.timerInterval);
            }
            if (modalEl.parentNode) {
                modalEl.parentNode.removeChild(modalEl);
            }
        });
        $(modalEl).modal({ backdrop: 'static', keyboard: false });
        }).catch(function(err) {
            Notification.exception(err);
        });
    }

    /** Format seconds as MM:SS. */
    function formatMinutesSeconds(sec) {
        if (sec === null || sec === undefined || sec < 0) {
            return '0:00';
        }
        var m = Math.floor(sec / 60);
        var s = Math.floor(sec % 60);
        return (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
    }

    /** Submit verification (used by Submit button and auto-submit when total time expires). */
    function doVerificationSubmit(ctx, bodyEl, modalEl) {
        if (ctx._submitInProgress) {
            return;
        }
        ctx._submitInProgress = true;
        captureResponseFromBody(bodyEl, ctx);
        ctx.timings.test_ended_at = Math.floor(Date.now() / 1000);
        if (ctx.timerInterval) {
            clearInterval(ctx.timerInterval);
            ctx.timerInterval = null;
        }
        $(modalEl).modal('hide');
        Str.get_string('verification_submitting', 'plagiarism_dixeo').then(function(msg) {
            return showLoadingModal(msg);
        }).then(function() {
            Ajax.call([{
                methodname: 'plagiarism_dixeo_verify_authorship',
                args: {
                    record_id: ctx.recordId,
                    responses: JSON.stringify(ctx.responses),
                    response_timings: JSON.stringify(ctx.timings)
                },
                done: function() {
                    hideLoadingModal();
                    if (ctx.formOptional) {
                        submissionAuthorized = true;
                    }
                    location.reload();
                },
                fail: function(err) {
                    hideLoadingModal();
                    Notification.exception(err);
                    ctx._submitInProgress = false;
                }
            }]);
        }).catch(function(err) {
            hideLoadingModal();
            Notification.exception(err);
            ctx._submitInProgress = false;
        });
    }

    /** Update total and per-question timer displays, Next unlock, and auto-submit when total time expires. */
    function startTimers(ctx, bodyEl, footerEl, modalEl) {
        if (ctx.timerInterval) {
            clearInterval(ctx.timerInterval);
        }
        function tick() {
            updateTimerDisplays(ctx, bodyEl, footerEl, modalEl);
        }
        tick();
        ctx.timerInterval = setInterval(tick, 1000);
    }

    /** Write remaining time into footer timer elements (00:00), hide if unlimited, auto-submit when total expires. */
    function updateTimerDisplays(ctx, bodyEl, footerEl, modalEl) {
        var totalRem = getRemainingTotal(ctx);
        var qRem = getRemainingQuestion(ctx);
        if (totalRem === 0) {
            if (ctx.timerInterval) {
                clearInterval(ctx.timerInterval);
                ctx.timerInterval = null;
            }
            doVerificationSubmit(ctx, bodyEl, modalEl);
            return;
        }
        var totalWrap = footerEl.querySelector('.plagiarism-dixeo-footer-total-timer');
        var qWrap = footerEl.querySelector('.plagiarism-dixeo-footer-question-timer');
        var totalEl = totalWrap ? totalWrap.querySelector('.plagiarism-dixeo-timer-value') : null;
        var qEl = qWrap ? qWrap.querySelector('.plagiarism-dixeo-timer-value') : null;
        if (totalWrap) {
            totalWrap.style.display = totalRem === null ? 'none' : 'inline-flex';
            if (totalEl) {
                totalEl.textContent = totalRem !== null ? formatMinutesSeconds(totalRem) : '';
            }
        }
        if (qWrap) {
            qWrap.style.display = qRem === null ? 'none' : 'inline-flex';
            if (qEl) {
                qEl.textContent = qRem !== null ? formatMinutesSeconds(qRem) : '';
            }
        }
        var now = Math.floor(Date.now() / 1000);
        if (ctx.nextUnlockAt > 0 && now >= ctx.nextUnlockAt) {
            ctx.nextUnlockAt = 0;
            var nextBtn = footerEl.querySelector('.plagiarism-dixeo-verification-next');
            if (nextBtn) {
                nextBtn.disabled = false;
            }
        }
    }

    /** Seconds remaining for total quiz or null if unlimited. */
    function getRemainingTotal(ctx) {
        if (!ctx.totalTimeSec || !ctx.timings.test_started_at) {
            return null;
        }
        var elapsed = Math.floor(Date.now() / 1000) - ctx.timings.test_started_at;
        var rem = ctx.totalTimeSec - elapsed;
        return rem <= 0 ? 0 : rem;
    }

    /** Seconds remaining for current question or null if unlimited. */
    function getRemainingQuestion(ctx) {
        var q = ctx.questions[ctx.currentIndex];
        if (!q) {
            return null;
        }
        var qkey = 'q' + ctx.currentIndex;
        var qTiming = ctx.timings.questions[qkey];
        if (!qTiming || !qTiming.started_at) {
            return null;
        }
        var limit = ctx.timeOpen;
        if (q.type === QUESTION_TYPE_TRUE_FALSE) {
            limit = ctx.timeTf;
        } else if (q.type === QUESTION_TYPE_MULTIPLE_CHOICE) {
            limit = ctx.timeMc;
        }
        if (!limit) {
            return null;
        }
        var elapsed = Math.floor(Date.now() / 1000) - qTiming.started_at;
        var rem = limit - elapsed;
        return rem <= 0 ? 0 : rem;
    }

    /** Ensure current question has started_at for timer (fixes restore). */
    function ensureCurrentQuestionStartedAt(ctx) {
        var qkey = 'q' + ctx.currentIndex;
        if (!ctx.timings.questions[qkey] || !ctx.timings.questions[qkey].started_at) {
            if (!ctx.timings.questions[qkey]) {
                ctx.timings.questions[qkey] = {};
            }
            ctx.timings.questions[qkey].started_at = ctx.currentIndex === 0 && ctx.timings.test_started_at
                ? ctx.timings.test_started_at
                : Math.floor(Date.now() / 1000);
        }
    }

    /** Icons: Font Awesome clock (total), list (question time). */
    var ICON_CLOCK = '<span class="plagiarism-dixeo-icon" aria-hidden="true"><i class="fa fa-clock-o"></i></span>';
    var ICON_QUESTION = '<span class="plagiarism-dixeo-icon" aria-hidden="true"><i class="fa fa-list-alt"></i></span>';

    /** Render one question and footer (Prev/Next left, timers + Submit right). */
    function renderQuestionScreen(ctx, titleEl, counterEl, donutEl, counterWrapEl, bodyEl, footerEl, modalEl) {
        var q = ctx.questions[ctx.currentIndex];
        if (!q) {
            return;
        }
        var total = ctx.questions.length;
        var current = ctx.currentIndex + 1;
        var pct = total > 0 ? (current / total) * 100 : 0;
        if (counterWrapEl) {
            counterWrapEl.style.display = '';
        }
        if (counterEl) {
            counterEl.textContent = current + '/' + total;
        }
        if (donutEl) {
            donutEl.style.setProperty('--p', String(pct));
        }
        ensureCurrentQuestionStartedAt(ctx);
        var qId = q.id || ('q' + ctx.currentIndex);
        var totalRem = getRemainingTotal(ctx);
        var qRem = getRemainingQuestion(ctx);

        bodyEl.innerHTML = '<div class="plagiarism-dixeo-question-content">' +
            renderOneQuestionHtml(q, qId, ctx.responses[qId], ctx.strings) + '</div>';

        var prevDisabled = !ctx.allowBackwards || ctx.currentIndex === 0;
        var showNext = ctx.currentIndex < ctx.questions.length - 1;
        var nextDisabled = showNext && !ctx.allowBackwards && ctx.nextUnlockAt > Math.floor(Date.now() / 1000);
        var submitEnabled = Object.keys(ctx.visited).length >= ctx.questions.length;

        var leftBtns = '<button type="button" class="btn btn-secondary plagiarism-dixeo-verification-prev" ' +
            (prevDisabled ? ' disabled' : '') + '>' + ctx.strings.previous + '</button>';
        if (showNext) {
            leftBtns += ' <button type="button" class="btn btn-primary plagiarism-dixeo-verification-next" ' +
                (nextDisabled ? ' disabled' : '') + '>' + ctx.strings.next + '</button>';
        }
        var totalDisplay = totalRem !== null ? 'inline-flex' : 'none';
        var qDisplay = qRem !== null ? 'inline-flex' : 'none';
        var rightPart = '<span class="plagiarism-dixeo-footer-total-timer" style="display:' + totalDisplay + '">' +
            ICON_CLOCK + ' <span class="plagiarism-dixeo-timer-value">' + formatMinutesSeconds(totalRem) + '</span></span>' +
            ' <span class="plagiarism-dixeo-footer-question-timer" style="display:' + qDisplay + '">' +
            ICON_QUESTION + ' <span class="plagiarism-dixeo-timer-value">' + formatMinutesSeconds(qRem) + '</span></span>' +
            ' <button type="button" class="btn btn-success plagiarism-dixeo-verification-submit" ' +
            (submitEnabled ? '' : ' disabled') + '>' + ctx.strings.submit + '</button>';
        footerEl.innerHTML = '<div class="plagiarism-dixeo-verification-footer__left">' + leftBtns + '</div>' +
            '<div class="plagiarism-dixeo-verification-footer__right">' + rightPart + '</div>';

        var prevBtnEl = footerEl.querySelector('.plagiarism-dixeo-verification-prev');
        if (prevBtnEl) {
            prevBtnEl.addEventListener('click', function() {
                if (this.disabled || ctx.currentIndex <= 0) {
                    return;
                }
                captureResponseFromBody(bodyEl, ctx);
                var now = Math.floor(Date.now() / 1000);
                var qkey = 'q' + ctx.currentIndex;
                if (ctx.timings.questions[qkey]) {
                    ctx.timings.questions[qkey].ended_at = now;
                    var sq = ctx.timings.questions[qkey];
                    ctx.timings.questions[qkey].seconds = (sq.ended_at || now) - (sq.started_at || now);
                }
                ctx.currentIndex--;
                ctx.timings.current_index = ctx.currentIndex;
                var prevQkey = 'q' + ctx.currentIndex;
                if (!ctx.timings.questions[prevQkey]) {
                    ctx.timings.questions[prevQkey] = {};
                }
                ctx.timings.questions[prevQkey].started_at = now;
                saveVerificationProgress(ctx, function() {
                    renderQuestionScreen(ctx, titleEl, counterEl, donutEl, counterWrapEl, bodyEl, footerEl, modalEl);
                    startTimers(ctx, bodyEl, footerEl, modalEl);
                });
            });
        }
        var nextBtnEl = footerEl.querySelector('.plagiarism-dixeo-verification-next');
        if (nextBtnEl) {
            nextBtnEl.addEventListener('click', function() {
                if (ctx.currentIndex >= ctx.questions.length - 1) {
                    return;
                }
                captureResponseFromBody(bodyEl, ctx);
                var now = Math.floor(Date.now() / 1000);
                var qkey = 'q' + ctx.currentIndex;
                if (ctx.timings.questions[qkey]) {
                    ctx.timings.questions[qkey].ended_at = now;
                    var sq2 = ctx.timings.questions[qkey];
                    ctx.timings.questions[qkey].seconds = (sq2.ended_at || now) - (sq2.started_at || now);
                }
                if (!ctx.allowBackwards) {
                    ctx.nextUnlockAt = now + ctx.nextDelaySec;
                }
                ctx.currentIndex++;
                ctx.visited[ctx.currentIndex] = true;
                ctx.timings.current_index = ctx.currentIndex;
                var nextQkey = 'q' + ctx.currentIndex;
                if (!ctx.timings.questions[nextQkey]) {
                    ctx.timings.questions[nextQkey] = {};
                }
                ctx.timings.questions[nextQkey].started_at = now;
                saveVerificationProgress(ctx, function() {
                    renderQuestionScreen(ctx, titleEl, counterEl, donutEl, counterWrapEl, bodyEl, footerEl, modalEl);
                    var nBtn = footerEl.querySelector('.plagiarism-dixeo-verification-next');
                    if (nBtn && !ctx.allowBackwards) {
                        nBtn.disabled = true;
                        var unlockAt = ctx.nextUnlockAt;
                        var check = setInterval(function() {
                            if (Math.floor(Date.now() / 1000) >= unlockAt) {
                                nBtn.disabled = false;
                                clearInterval(check);
                            }
                        }, 500);
                    }
                    startTimers(ctx, bodyEl, footerEl, modalEl);
                });
            });
        }
        var submitBtnEl = footerEl.querySelector('.plagiarism-dixeo-verification-submit');
        if (submitBtnEl) {
            submitBtnEl.addEventListener('click', function() {
                if (this.disabled) {
                    return;
                }
                this.disabled = true;
                doVerificationSubmit(ctx, bodyEl, modalEl);
            });
        }
    }

    /** Read current question answer from body into ctx.responses. */
    function captureResponseFromBody(bodyEl, ctx) {
        var q = ctx.questions[ctx.currentIndex];
        if (!q) {
            return;
        }
        var qId = q.id || ('q' + ctx.currentIndex);
        var input = bodyEl.querySelector('input[name="authorship_q[' + qId + ']"]:checked');
        if (input) {
            ctx.responses[qId] = input.value;
            return;
        }
        var sel = bodyEl.querySelector('select[name="authorship_q[' + qId + ']"]');
        if (sel) {
            ctx.responses[qId] = sel.value || '';
            return;
        }
        var textarea = bodyEl.querySelector('textarea[name="authorship_q[' + qId + ']"]');
        if (textarea) {
            ctx.responses[qId] = textarea.value || '';
        }
    }

    /** Persist responses and timings via save_verification_progress. */
    function saveVerificationProgress(ctx, callback) {
        Ajax.call([{
            methodname: 'plagiarism_dixeo_save_verification_progress',
            args: {
                record_id: ctx.recordId,
                responses: JSON.stringify(ctx.responses),
                response_timings: JSON.stringify(ctx.timings)
            },
            done: function() {
                if (callback) {
                    callback();
                }
            },
            fail: function(err) {
                Notification.exception(err);
                if (callback) {
                    callback();
                }
            }
        }]);
    }

    /** Build HTML for one question (true/false, multiple choice, or open). */
    function renderOneQuestionHtml(q, qId, currentValue, uiStrings) {
        var name = 'authorship_q[' + qId + ']';
        var text = (q.text || '').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        var trueLabel = (uiStrings && uiStrings.trueLabel) ? uiStrings.trueLabel : 'True';
        var falseLabel = (uiStrings && uiStrings.falseLabel) ? uiStrings.falseLabel : 'False';
        var html = '<div class="form-group"><p class="font-weight-bold">' + text + '</p>';
        if (q.type === QUESTION_TYPE_TRUE_FALSE) {
            var valTrue = (currentValue === 'true' || currentValue === '1');
            var valFalse = (currentValue === 'false' || currentValue === '0');
            html += '<label class="d-block"><input type="radio" name="' + name + '" value="true" ' +
                (valTrue ? ' checked' : '') + '> ' + trueLabel + '</label>';
            html += '<label class="d-block"><input type="radio" name="' + name + '" value="false" ' +
                (valFalse ? ' checked' : '') + '> ' + falseLabel + '</label>';
        } else if (q.type === QUESTION_TYPE_MULTIPLE_CHOICE && q.options && q.options.length) {
            q.options.forEach(function(opt) {
                var v = (opt.value !== undefined ? opt.value : opt.text) || '';
                var label = (opt.text !== undefined ? opt.text : opt.value) || v;
                var checked = currentValue === v ? ' checked' : '';
                html += '<label class="d-block"><input type="radio" name="' + name + '" value="' +
                    v.replace(/"/g, '&quot;') + '"' + checked + '> ' +
                    label.replace(/</g, '&lt;') + '</label>';
            });
        } else {
            html += '<textarea name="' + name + '" class="form-control" rows="4">' +
                (currentValue || '').replace(/</g, '&lt;') + '</textarea>';
        }
        html += '</div>';
        return html;
    }
    /* eslint-enable jsdoc/require-jsdoc, jsdoc/require-param, max-len */

    /**
     * Initialize after full page load. Waits for window 'load' before doing anything.
     */
    function init() {
        if (document.readyState === 'complete') {
            run();
        } else {
            window.addEventListener('load', run);
        }
    }

    return {
        init: init
    };
});
