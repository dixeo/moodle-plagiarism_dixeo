/**
 * Pre-submission Analyze step on the mod/assign submission page (view.php, default or action=view).
 *
 * @module     plagiarism_dixeo/mod_assign_pre_submission
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax', 'core/notification', 'core/str', 'core/templates', 'core/loadingicon',
    'plagiarism_dixeo/assign_job_poller', 'plagiarism_dixeo/quiz_timer'],
function($, Ajax, Notification, Str, Templates, LoadingIcon, AssignJobPoller, QuizTimer) {

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
    var QUIZ_MODAL_ID = 'plagiarism_dixeo_authorship_quiz_modal';
    var RESPONSE_CONTAINER_ID = 'plagiarism_dixeo_submission_response';
    var OPEN_QUIZ_SELECTOR = '.plagiarism-dixeo-open-verification-modal';

    /** Whether the form has already passed authorship check (submit allowed without re-check). */
    var submissionAuthorized = false;

    /** Whether the in-progress panel click listener has been bound. */
    var panelResumeBound = false;

    /**
     * Remove any previous analysis response so the next one is shown alone.
     */
    function clearPreviousResponse() {
        var container = document.getElementById(RESPONSE_CONTAINER_ID);
        if (container && container.parentNode) {
            container.parentNode.removeChild(container);
        }
        document.querySelectorAll('.plagiarism-dixeo-review-feedback').forEach(function(el) {
            if (el.parentNode) {
                el.parentNode.removeChild(el);
            }
        });
    }

    /**
     * Hide the loading modal, including when Bootstrap's hide event does not fire.
     */
    function hideLoadingModal() {
        var el = document.getElementById(LOADING_MODAL_ID);
        if (el) {
            try {
                $(el).modal('hide');
            } catch (ignore) {
                // Hard-hide below.
            }
            el.classList.remove('show');
            el.style.display = 'none';
            el.setAttribute('aria-hidden', 'true');
        }
        document.querySelectorAll('.modal-backdrop').forEach(function(backdrop) {
            if (backdrop.parentNode) {
                backdrop.parentNode.removeChild(backdrop);
            }
        });
        document.body.classList.remove('modal-open');
        document.body.style.removeProperty('padding-right');
    }

    /**
     * Show the loading modal (spinner + message). Creates once and reuses; updates message when reused. Locked.
     * Resolves after the modal has finished showing, or immediately when it is already visible.
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
            if (existing.classList.contains('show')) {
                return Promise.resolve($existing);
            }
            return new Promise(function(resolve) {
                var settled = false;
                var done = function() {
                    if (settled) {
                        return;
                    }
                    settled = true;
                    resolve($existing);
                };
                $existing.one('shown.bs.modal', done);
                $existing.modal('show');
                setTimeout(done, 50);
            });
        }
        return Templates.render('plagiarism_dixeo/loading_modal', {message: message}).then(function(html, js) {
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
                $modal.modal({backdrop: 'static', keyboard: false});
            });
        });
    }

    /**
     * Run the pre-submission behavior: add Analyze button (id from URL), optional submit listener if form present.
     */
    function run() {
        var cmid = getUrlParam('id').trim();
        if (!cmid) {
            return;
        }

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
                wrapper.className = 'plagiarism-dixeo-analyze-toolbar mb-3';
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
                attachPanelResumeHandler(form, cmid);
            }
            return null;
        }).catch(function() {
            // If strings fail, still allow normal submit.
        });
    }

    /**
     * Place the analysis response container in the page.
     *
     * @param {HTMLElement} container
     */
    function insertResponseContainer(container) {
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
    }

    /**
     * Render analysis HTML into the response container.
     *
     * @param {string} htmlFragment Server-sanitized HTML.
     * @returns {Promise}
     */
    function renderAnalysisHtml(htmlFragment) {
        var container = document.createElement('div');
        container.id = RESPONSE_CONTAINER_ID;
        return Templates.render('plagiarism_dixeo/html_fragment', {
            html: htmlFragment || '',
            classes: ''
        }).then(function(html, js) {
            container.innerHTML = html;
            insertResponseContainer(container);
            if (js) {
                Templates.runTemplateJS(js);
            }
            return null;
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
                return Ajax.call([{
                    methodname: 'plagiarism_dixeo_mod_assign_pre_submission',
                    args: {id: cmid}
                }])[0];
            }).then(function(data) {
                if (!data || data.status === 'failed') {
                    if (data && data.errormessage) {
                        Notification.addNotification({message: data.errormessage, type: 'error'});
                    }
                    return null;
                }
                if (data.status === 'completed' && data.html) {
                    return data;
                }
                return AssignJobPoller.poll(data.jobid, data.courseid, cmid, 'review');
            }).then(function(data) {
                analyzeButton.disabled = false;
                hideLoadingModal();
                if (!data) {
                    return null;
                }
                if (data.status === 'failed') {
                    if (data.errormessage) {
                        Notification.addNotification({message: data.errormessage, type: 'error'});
                    }
                    return null;
                }
                return renderAnalysisHtml(data.html || '');
            }).catch(function(err) {
                analyzeButton.disabled = false;
                hideLoadingModal();
                Notification.exception(err);
            });
        });
    }

    /**
     * Run check_authorship and open the quiz, or submit the assignment when no quiz is required.
     *
     * When a quiz is already under way, the server returns that same record instead of starting
     * a new check, so Submit and the in-progress panel both resume the clock and the answers.
     *
     * @param {HTMLFormElement} form Assign form to submit after verification.
     * @param {string} cmid Course module ID from URL.
     * @param {boolean} submitIfNoQuiz When true and no quiz is required, submit the form.
     * @returns {Promise}
     */
    function requestAuthorshipCheck(form, cmid, submitIfNoQuiz) {
        return Str.get_string('authorship_verifying', 'plagiarism_dixeo').then(function(verifyingMsg) {
            return showLoadingModal(verifyingMsg);
        }).then(function() {
            return Ajax.call([{
                methodname: 'plagiarism_dixeo_check_authorship',
                args: {
                    component: 'mod_assign',
                    area: 'submission',
                    id: parseInt(cmid, 10) || 0
                }
            }])[0];
        }).then(function(data) {
            hideLoadingModal();
            if (data && data.status === 'failed' && data.errormessage) {
                Notification.addNotification({
                    message: data.errormessage,
                    type: 'error'
                });
                return null;
            }
            var recordId = (data && data.record_id) ? parseInt(data.record_id, 10) : 0;
            if (recordId === 0) {
                if (submitIfNoQuiz) {
                    submissionAuthorized = true;
                    form.submit();
                }
                return null;
            }
            showQuizModal(form, recordId);
            return null;
        }).catch(function(err) {
            hideLoadingModal();
            Notification.exception(err);
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
            requestAuthorshipCheck(form, cmid, true);
        });
    }

    /**
     * Open the existing quiz from the "Verification in progress" / "Verification required" panel.
     *
     * @param {HTMLFormElement} form Assign form to submit after verification.
     * @param {string} cmid Course module ID from URL.
     */
    function attachPanelResumeHandler(form, cmid) {
        if (panelResumeBound) {
            return;
        }
        panelResumeBound = true;

        document.addEventListener('click', function(e) {
            var link = e.target.closest(OPEN_QUIZ_SELECTOR);
            if (!link) {
                return;
            }
            e.preventDefault();
            e.stopPropagation();
            if (document.getElementById(QUIZ_MODAL_ID)) {
                return;
            }
            requestAuthorshipCheck(form, cmid, false);
        });
    }

    var QUESTION_TYPE_TRUE_FALSE = 'true_false';
    var QUESTION_TYPE_MULTIPLE_CHOICE = 'multiple_choice';
    var LOCK_TO_NEXT_DELAY_MS = 2000;
    var ICON_CLOCK = '<span class="plagiarism-dixeo-icon" aria-hidden="true"><i class="fa fa-clock-o"></i></span>';
    var ICON_QUESTION = '<span class="plagiarism-dixeo-icon" aria-hidden="true"><i class="fa fa-list-alt"></i></span>';

    /**
     * Escape text for insertion into HTML.
     *
     * @param {string} value Raw text.
     * @returns {string}
     */
    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    /**
     * Format a number of seconds as mm:ss.
     *
     * @param {number|null} seconds Seconds remaining.
     * @returns {string}
     */
    function formatMinutesSeconds(seconds) {
        if (seconds === null || seconds === undefined) {
            return '';
        }
        var safe = Math.max(0, Math.floor(seconds));
        var minutes = Math.floor(safe / 60);
        return minutes + ':' + String(safe % 60).padStart(2, '0');
    }

    /**
     * Display values for the quiz footer timers.
     *
     * @param {object|null} timer Quiz timer instance.
     * @returns {{totalDisplay: string, qDisplay: string, totalValue: string, qValue: string}}
     */
    function timerFooterState(timer) {
        var hasTotal = !!(timer && timer.remaining !== null);
        var hasQuestion = !!(timer && timer.questionRemaining !== null);
        return {
            totalDisplay: hasTotal ? 'inline-flex' : 'none',
            qDisplay: hasQuestion ? 'inline-flex' : 'none',
            totalValue: hasTotal ? formatMinutesSeconds(timer.remaining) : '',
            qValue: hasQuestion ? formatMinutesSeconds(timer.questionRemaining) : ''
        };
    }

    /**
     * Prev/Next button HTML for the verification pager.
     *
     * @param {object} strings Localized labels.
     * @param {boolean} prevDisabled Whether Previous is disabled.
     * @param {boolean} showNext Whether to render Next.
     * @param {boolean} nextDisabled Whether Next is disabled.
     * @returns {string}
     */
    function verificationNavHtml(strings, prevDisabled, showNext, nextDisabled) {
        var html = '<button type="button" class="btn btn-secondary plagiarism-dixeo-verification-prev"' +
            (prevDisabled ? ' disabled' : '') + '>' + escapeHtml(strings.previous) + '</button>';
        if (showNext) {
            html += ' <button type="button" class="btn btn-primary plagiarism-dixeo-verification-next"' +
                (nextDisabled ? ' disabled' : '') + '>' + escapeHtml(strings.next) + '</button>';
        }
        return html;
    }

    /**
     * Question id used in form names and saved answers.
     *
     * @param {object} question Question from get_verification_quiz.
     * @param {number} index Index in the quiz.
     * @returns {string}
     */
    function questionId(question, index) {
        return String((question && question.id) || ('q' + index));
    }

    /**
     * Whether every question has a non-empty saved answer.
     *
     * @param {object} ctx Pager state.
     * @returns {boolean}
     */
    function allQuestionsAnswered(ctx) {
        var i, q, id, value;
        for (i = 0; i < ctx.questions.length; i++) {
            q = ctx.questions[i];
            id = questionId(q, i);
            value = ctx.responses[id];
            if (value === undefined || value === null || String(value).trim() === '') {
                return false;
            }
        }
        return ctx.questions.length > 0;
    }

    /**
     * Read the visible question's answer from the DOM into ctx.responses.
     *
     * @param {HTMLElement} bodyEl Quiz body.
     * @param {object} ctx Pager state.
     */
    function captureResponseFromBody(bodyEl, ctx) {
        var q = ctx.questions[ctx.currentIndex];
        if (!q) {
            return;
        }
        var qId = questionId(q, ctx.currentIndex);
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

    /**
     * HTML for one true/false, multiple-choice or open question.
     *
     * @param {object} q Question.
     * @param {string} qId Question id.
     * @param {string} currentValue Saved answer, if any.
     * @param {object} uiStrings Localized labels.
     * @param {number} questionNumber 1-based index shown to the student.
     * @returns {string}
     */
    function renderOneQuestionHtml(q, qId, currentValue, uiStrings, questionNumber) {
        var name = 'authorship_q[' + qId + ']';
        var numberedText = (questionNumber ? questionNumber + '. ' : '') + escapeHtml(q.text || '');
        var html = '<div class="form-group authorship-question"><p class="authorship-question-text">' +
            numberedText + '</p>';
        if (q.type === QUESTION_TYPE_TRUE_FALSE) {
            var valTrue = (currentValue === '1' || currentValue === 'true' || currentValue === true);
            var valFalse = (currentValue === '0' || currentValue === 'false' || currentValue === false);
            html += '<div class="authorship-options">';
            html += '<label class="d-block"><input type="radio" name="' + name + '" value="1"' +
                (valTrue ? ' checked' : '') + '> ' + escapeHtml(uiStrings.trueLabel) + '</label>';
            html += '<label class="d-block"><input type="radio" name="' + name + '" value="0"' +
                (valFalse ? ' checked' : '') + '> ' + escapeHtml(uiStrings.falseLabel) + '</label>';
            html += '</div>';
        } else if (q.type === QUESTION_TYPE_MULTIPLE_CHOICE && q.options && q.options.length) {
            html += '<div class="authorship-options">';
            q.options.forEach(function(opt) {
                var v = String((opt.value !== undefined ? opt.value : opt.text) || '');
                var label = String((opt.text !== undefined ? opt.text : opt.value) || v);
                var checked = String(currentValue) === v ? ' checked' : '';
                html += '<label class="d-block"><input type="radio" name="' + name + '" value="' +
                    escapeHtml(v) + '"' + checked + '> ' + escapeHtml(label) + '</label>';
            });
            html += '</div>';
        } else {
            html += '<textarea name="' + name + '" class="form-control" rows="4">' +
                escapeHtml(currentValue || '') + '</textarea>';
        }
        html += '</div>';
        return html;
    }

    /**
     * Disable inputs and show the locked overlay on the visible question.
     *
     * @param {HTMLElement} bodyEl Quiz body.
     * @param {object} ctx Pager state.
     */
    function lockCurrentQuestionInputs(bodyEl, ctx) {
        var container = bodyEl.querySelector('.plagiarism-dixeo-question-content');
        if (!container || container.querySelector('.plagiarism-dixeo-question-locked-overlay')) {
            return;
        }
        container.querySelectorAll('input, select, textarea').forEach(function(el) {
            el.disabled = true;
        });
        var overlay = document.createElement('div');
        overlay.className = 'plagiarism-dixeo-question-locked-overlay';
        overlay.setAttribute('title', ctx.strings.questionLockedTooltip);
        overlay.setAttribute('aria-label', ctx.strings.questionLockedTooltip);
        overlay.innerHTML = '<span class="plagiarism-dixeo-question-locked-icon" aria-hidden="true">' +
            '<i class="fa fa-lock"></i></span><span class="plagiarism-dixeo-question-locked-text">' +
            escapeHtml(ctx.strings.questionLocked) + '</span>';
        container.appendChild(overlay);
    }

    /**
     * Notify when the authorship record id is missing from the quiz.
     *
     * @returns {Promise}
     */
    function notifyMissingRecord() {
        return Str.get_string('authorship_error_missing_record', 'plagiarism_dixeo').then(function(msg) {
            Notification.addNotification({message: msg, type: 'error'});
            return null;
        }).catch(function() {
            // Ignore string load failure.
        });
    }

    /**
     * Verify authorship answers then submit the assign form.
     *
     * @param {HTMLFormElement} form
     * @param {jQuery} $modal
     * @param {HTMLButtonElement} submitBtn
     * @param {{recordId: number, responses: Object}} collected
     */
    function verifyAndSubmit(form, $modal, submitBtn, collected) {
        Ajax.call([{
            methodname: 'plagiarism_dixeo_verify_authorship',
            args: {
                // Web service parameter name is snake_case.
                // eslint-disable-next-line camelcase
                record_id: collected.recordId,
                responses: JSON.stringify(collected.responses)
            },
            done: function(result) {
                if (result && result.status === 'failed' && result.errormessage) {
                    Notification.addNotification({
                        message: result.errormessage,
                        type: 'error'
                    });
                    submitBtn.disabled = false;
                    return;
                }
                if (result && result.success === false) {
                    submitBtn.disabled = false;
                    return;
                }
                $modal.modal('hide');
                submissionAuthorized = true;
                form.submit();
            },
            fail: function(err) {
                Notification.exception(err);
                submitBtn.disabled = false;
            }
        }]);
    }

    /**
     * Show the authorship quiz as one question at a time, with Prev/Next and dual timers.
     *
     * @param {HTMLFormElement} form Assign form to submit after verification.
     * @param {number} recordId Authorship record id.
     */
    function showQuizModal(form, recordId) {
        if (!recordId || recordId <= 0) {
            notifyMissingRecord();
            return;
        }

        var session = null;

        /**
         * Build the quiz modal and pager after strings have loaded.
         *
         * @param {string[]} labels String API results.
         * @returns {{timer: object, applyLoadedQuiz: function}}
         */
        function startQuizSession(labels) {
            var title = labels[0];
            var confirmLabel = labels[1];
            var submitLabel = labels[2];
            var cancelLabel = labels[3];
            var closeLabel = labels[4];
            var loadError = labels[5];

            var existing = document.getElementById(QUIZ_MODAL_ID);
            if (existing && existing.parentNode) {
                existing.parentNode.removeChild(existing);
            }

            var modal = document.createElement('div');
            modal.id = QUIZ_MODAL_ID;
            modal.className = 'modal fade';
            modal.setAttribute('role', 'dialog');
            modal.setAttribute('aria-hidden', 'true');

            var dialog = document.createElement('div');
            dialog.className = 'modal-dialog modal-lg';
            dialog.setAttribute('role', 'document');
            var content = document.createElement('div');
            content.className = 'modal-content';

            var header = document.createElement('div');
            header.className = 'modal-header';
            var titleEl = document.createElement('h5');
            titleEl.className = 'modal-title';
            titleEl.textContent = title;
            var counterEl = document.createElement('span');
            counterEl.className = 'plagiarism-dixeo-verification-counter ml-3';
            var closeBtn = document.createElement('button');
            closeBtn.type = 'button';
            closeBtn.className = 'close';
            closeBtn.setAttribute('data-dismiss', 'modal');
            closeBtn.setAttribute('aria-label', closeLabel);
            closeBtn.innerHTML = '<span aria-hidden="true">&times;</span>';
            header.appendChild(titleEl);
            header.appendChild(counterEl);
            header.appendChild(closeBtn);

            var body = document.createElement('div');
            body.className = 'modal-body plagiarism-dixeo-quiz-body';

            var footer = document.createElement('div');
            footer.className = 'modal-footer plagiarism-dixeo-verification-footer';

            content.appendChild(header);
            content.appendChild(body);
            content.appendChild(footer);
            dialog.appendChild(content);
            modal.appendChild(dialog);
            document.body.appendChild(modal);

            var $modal = $(modal);
            var ctx = {
                recordId: recordId,
                questions: [],
                responses: {},
                currentIndex: 0,
                visited: {},
                allowBackwards: false,
                nextDelaySec: 0,
                nextUnlockAt: 0,
                confirmed: false,
                submitting: false,
                lockToNextTimeout: null,
                strings: {
                    previous: labels[6],
                    next: labels[7],
                    submit: submitLabel,
                    confirm: confirmLabel,
                    cancel: cancelLabel,
                    timerTestTooltip: labels[8],
                    timerQuestionTooltip: labels[9],
                    questionLocked: labels[10],
                    questionLockedTooltip: labels[11],
                    trueLabel: labels[12],
                    falseLabel: labels[13]
                }
            };
            var timer = null;
            var confirmCb = null;
            var submitBtn = null;

            /**
             * Enable Submit once every question has an answer and the student has confirmed.
             */
            function updateSubmitState() {
                if (!submitBtn || ctx.submitting) {
                    return;
                }
                submitBtn.disabled = !(allQuestionsAnswered(ctx) && confirmCb && confirmCb.checked);
            }

            /**
             * Submit the stored answers through verify_authorship.
             */
            function submitQuiz() {
                if (ctx.submitting) {
                    return;
                }
                captureResponseFromBody(body, ctx);
                if (ctx.recordId <= 0) {
                    notifyMissingRecord();
                    return;
                }
                ctx.submitting = true;
                if (submitBtn) {
                    submitBtn.disabled = true;
                }
                if (timer) {
                    timer.stop();
                }
                if (ctx.lockToNextTimeout) {
                    clearTimeout(ctx.lockToNextTimeout);
                    ctx.lockToNextTimeout = null;
                }
                verifyAndSubmit(form, $modal, submitBtn || closeBtn, {
                    recordId: ctx.recordId,
                    responses: ctx.responses
                });
            }

            /**
             * Move to another question, persisting the current answer first.
             *
             * @param {number} nextIndex Index to show.
             * @param {boolean} fromLock When true, apply the next-button delay as after a timeout.
             */
            function goToQuestion(nextIndex, fromLock) {
                if (nextIndex < 0 || nextIndex >= ctx.questions.length) {
                    return;
                }
                captureResponseFromBody(body, ctx);
                if (fromLock || (!ctx.allowBackwards && nextIndex > ctx.currentIndex)) {
                    ctx.nextUnlockAt = Math.floor(Date.now() / 1000) + ctx.nextDelaySec;
                }
                ctx.currentIndex = nextIndex;
                ctx.visited[ctx.currentIndex] = true;
                if (!timer) {
                    renderQuestionScreen();
                    return;
                }
                timer.sync().then(function() {
                    renderQuestionScreen();
                    return null;
                }).catch(function() {
                    renderQuestionScreen();
                    return null;
                });
            }

            /**
             * Draw the visible question and rebuild the footer controls.
             */
            function renderQuestionScreen() {
                var q = ctx.questions[ctx.currentIndex];
                if (!q) {
                    return;
                }
                var total = ctx.questions.length;
                var current = ctx.currentIndex + 1;
                var qId = questionId(q, ctx.currentIndex);
                counterEl.textContent = current + '/' + total;

                body.innerHTML = '<div class="plagiarism-dixeo-question-content">' +
                    renderOneQuestionHtml(q, qId, ctx.responses[qId], ctx.strings, current) + '</div>';
                if (timer && timer.questionRemaining === 0) {
                    lockCurrentQuestionInputs(body, ctx);
                }

                var prevDisabled = !ctx.allowBackwards || ctx.currentIndex === 0;
                var showNext = ctx.currentIndex < ctx.questions.length - 1;
                var nowSec = Math.floor(Date.now() / 1000);
                var nextDisabled = showNext && !ctx.allowBackwards && ctx.nextUnlockAt > nowSec;
                var timers = timerFooterState(timer);
                var leftBtns = verificationNavHtml(ctx.strings, prevDisabled, showNext, nextDisabled);

                footer.innerHTML =
                    '<div class="plagiarism-dixeo-verification-footer__left">' + leftBtns + '</div>' +
                    '<div class="plagiarism-dixeo-verification-footer__right">' +
                    '<label class="mb-0 plagiarism-dixeo-confirm-label">' +
                    '<input type="checkbox" class="plagiarism-dixeo-confirm-checkbox"> ' +
                    escapeHtml(ctx.strings.confirm) + '</label>' +
                    '<span class="plagiarism-dixeo-footer-total-timer" title="' +
                    escapeHtml(ctx.strings.timerTestTooltip) + '" style="display:' + timers.totalDisplay + '">' +
                    ICON_CLOCK + ' <span class="plagiarism-dixeo-timer-value plagiarism-dixeo-quiz-countdown">' +
                    timers.totalValue + '</span></span>' +
                    ' <span class="plagiarism-dixeo-footer-question-timer" title="' +
                    escapeHtml(ctx.strings.timerQuestionTooltip) + '" style="display:' + timers.qDisplay + '">' +
                    ICON_QUESTION + ' <span class="plagiarism-dixeo-timer-value">' + timers.qValue + '</span></span>' +
                    ' <button type="button" class="btn btn-secondary plagiarism-dixeo-quiz-cancel">' +
                    escapeHtml(ctx.strings.cancel) + '</button>' +
                    ' <button type="button" class="btn btn-success plagiarism-dixeo-verification-submit" disabled>' +
                    escapeHtml(ctx.strings.submit) + '</button>' +
                    '</div>';

                bindVerificationFooter(nextDisabled);
                updateSubmitState();
            }

            /**
             * Attach footer control handlers after the pager HTML is rebuilt.
             *
             * @param {boolean} nextDisabled Whether Next starts locked.
             */
            function bindVerificationFooter(nextDisabled) {
                confirmCb = footer.querySelector('.plagiarism-dixeo-confirm-checkbox');
                submitBtn = footer.querySelector('.plagiarism-dixeo-verification-submit');
                var prevBtnEl = footer.querySelector('.plagiarism-dixeo-verification-prev');
                var nextBtnEl = footer.querySelector('.plagiarism-dixeo-verification-next');
                var cancelBtn = footer.querySelector('.plagiarism-dixeo-quiz-cancel');
                var totalDisplayEl = footer.querySelector('.plagiarism-dixeo-quiz-countdown');
                var questionDisplayEl = footer.querySelector(
                    '.plagiarism-dixeo-footer-question-timer .plagiarism-dixeo-timer-value'
                );

                if (timer) {
                    timer.display = totalDisplayEl;
                    timer.questionDisplay = questionDisplayEl;
                    timer.render();
                }

                if (prevBtnEl) {
                    prevBtnEl.addEventListener('click', function() {
                        if (this.disabled || ctx.currentIndex <= 0) {
                            return;
                        }
                        goToQuestion(ctx.currentIndex - 1, false);
                    });
                }
                if (nextBtnEl) {
                    nextBtnEl.addEventListener('click', function() {
                        if (this.disabled || ctx.currentIndex >= ctx.questions.length - 1) {
                            return;
                        }
                        goToQuestion(ctx.currentIndex + 1, false);
                    });
                    if (nextDisabled) {
                        var unlockAt = ctx.nextUnlockAt;
                        var check = setInterval(function() {
                            if (Math.floor(Date.now() / 1000) >= unlockAt) {
                                nextBtnEl.disabled = false;
                                clearInterval(check);
                            }
                        }, 500);
                    }
                }
                if (cancelBtn) {
                    cancelBtn.addEventListener('click', function() {
                        $modal.modal('hide');
                    });
                }
                if (submitBtn) {
                    submitBtn.addEventListener('click', function() {
                        if (this.disabled) {
                            return;
                        }
                        submitQuiz();
                    });
                }
                if (confirmCb) {
                    confirmCb.checked = !!ctx.confirmed;
                    confirmCb.addEventListener('change', function() {
                        ctx.confirmed = confirmCb.checked;
                        updateSubmitState();
                    });
                }
            }

            /**
             * Lock the current question, then auto-advance when it is not the last.
             */
            function onQuestionExpire() {
                lockCurrentQuestionInputs(body, ctx);
                if (ctx.allowBackwards || ctx.currentIndex >= ctx.questions.length - 1) {
                    return;
                }
                if (ctx.lockToNextTimeout) {
                    clearTimeout(ctx.lockToNextTimeout);
                }
                ctx.lockToNextTimeout = setTimeout(function() {
                    ctx.lockToNextTimeout = null;
                    goToQuestion(ctx.currentIndex + 1, true);
                }, LOCK_TO_NEXT_DELAY_MS);
            }

            $modal.on('hidden.bs.modal', function() {
                if (timer) {
                    timer.stop();
                }
                if (ctx.lockToNextTimeout) {
                    clearTimeout(ctx.lockToNextTimeout);
                }
                if (modal.parentNode) {
                    modal.parentNode.removeChild(modal);
                }
            });

            $modal.modal({backdrop: 'static', keyboard: false});

            body.addEventListener('change', function() {
                captureResponseFromBody(body, ctx);
                updateSubmitState();
            });
            body.addEventListener('input', function() {
                captureResponseFromBody(body, ctx);
                updateSubmitState();
            });

            timer = new QuizTimer({
                recordId: recordId,
                body: body,
                collect: function() {
                    captureResponseFromBody(body, ctx);
                    return ctx.responses;
                },
                getCurrentIndex: function() {
                    return ctx.currentIndex;
                },
                onExpire: function() {
                    body.querySelectorAll('input, select, textarea').forEach(function(input) {
                        input.disabled = true;
                    });
                    lockCurrentQuestionInputs(body, ctx);
                    submitQuiz();
                },
                onQuestionExpire: onQuestionExpire,
                onState: function(state) {
                    if (typeof state.allow_backwards === 'boolean') {
                        ctx.allowBackwards = state.allow_backwards;
                    }
                    if (typeof state.next_button_delay === 'number') {
                        ctx.nextDelaySec = state.next_button_delay;
                    }
                }
            });

            return {
                timer: timer,
                applyLoadedQuiz: function(quiz) {
                    var questions;
                    try {
                        questions = JSON.parse(quiz.questions || '[]');
                    } catch (e) {
                        questions = [];
                    }
                    if (!questions.length) {
                        Notification.addNotification({message: loadError, type: 'error'});
                        $modal.modal('hide');
                        return null;
                    }
                    var answers;
                    try {
                        answers = JSON.parse(quiz.answers || '{}');
                    } catch (err) {
                        answers = {};
                    }
                    ctx.questions = questions;
                    ctx.responses = answers && typeof answers === 'object' ? answers : {};
                    ctx.currentIndex = Math.max(0, Math.min(questions.length - 1, quiz.current_index || 0));
                    ctx.allowBackwards = !!quiz.allow_backwards;
                    ctx.nextDelaySec = quiz.next_button_delay || 0;
                    ctx.visited[ctx.currentIndex] = true;
                    renderQuestionScreen();
                    return null;
                }
            };
        }

        Promise.all([
            Str.get_string('authorship_quiz_modal_title', 'plagiarism_dixeo'),
            Str.get_string('authorship_confirm_statement', 'plagiarism_dixeo'),
            Str.get_string('authorship_confirm_and_submit', 'plagiarism_dixeo'),
            Str.get_string('authorship_cancel', 'plagiarism_dixeo'),
            Str.get_string('close', 'plagiarism_dixeo'),
            Str.get_string('authorship_quiz_load_error', 'plagiarism_dixeo'),
            Str.get_string('verification_previous', 'plagiarism_dixeo'),
            Str.get_string('verification_next', 'plagiarism_dixeo'),
            Str.get_string('timer_test_tooltip', 'plagiarism_dixeo'),
            Str.get_string('timer_question_tooltip', 'plagiarism_dixeo'),
            Str.get_string('question_locked', 'plagiarism_dixeo'),
            Str.get_string('question_locked_tooltip', 'plagiarism_dixeo'),
            Str.get_string('true', 'plagiarism_dixeo'),
            Str.get_string('false', 'plagiarism_dixeo')
        ]).then(function(labels) {
            session = startQuizSession(labels);
            return session.timer.start();
        }).then(function(quiz) {
            return session.applyLoadedQuiz(quiz);
        }).catch(function(err) {
            Notification.exception(err);
        });
    }

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
