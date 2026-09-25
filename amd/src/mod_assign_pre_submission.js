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
            showQuizModal(form, (data.quiz_html || ''));
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

    /**
     * Check if all quiz inputs in the container have a value (for enabling Submit).
     *
     * @param {HTMLElement} container
     * @returns {boolean}
     */
    function allQuizQuestionsAnswered(container) {
        var sel = 'input[name^="authorship_q["], select[name^="authorship_q["], ' +
            'textarea[name^="authorship_q["]';
        var inputs = container.querySelectorAll(sel);
        var i;
        for (i = 0; i < inputs.length; i++) {
            var el = inputs[i];
            if (el.type === 'radio' || el.type === 'checkbox') {
                var name = el.name;
                var checked = container.querySelector('input[name="' + name.replace(/[[\]]/g, '\\$&') + '"]:checked');
                if (!checked) {
                    return false;
                }
            } else if (!el.value || !el.value.trim()) {
                return false;
            }
        }
        return true;
    }

    /**
     * Collect record_id and responses from the quiz form fragment.
     *
     * @param {HTMLElement} container
     * @returns {{recordId: number, responses: Object}}
     */
    function collectQuizResponses(container) {
        var recordInput = container.querySelector('input[name="authorship_record_id"]');
        var recordId = (recordInput && recordInput.value) ? parseInt(recordInput.value, 10) : 0;
        var responses = {};
        var sel = 'input[name^="authorship_q["], select[name^="authorship_q["], ' +
            'textarea[name^="authorship_q["]';
        var inputs = container.querySelectorAll(sel);
        var i, name, match;
        for (i = 0; i < inputs.length; i++) {
            var el = inputs[i];
            name = el.name;
            if (el.type === 'radio' || el.type === 'checkbox') {
                if (el.checked) {
                    match = name.match(/authorship_q\[([^\]]+)\]/);
                    if (match) {
                        responses[match[1]] = el.value;
                    }
                }
            } else {
                match = name.match(/authorship_q\[([^\]]+)\]/);
                if (match) {
                    responses[match[1]] = el.value;
                }
            }
        }
        return {recordId: recordId, responses: responses};
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
     * Start the server-clocked countdown for a quiz, restoring any answers saved earlier.
     *
     * @param {number} recordId Authorship record id.
     * @param {HTMLElement} body Element holding the quiz inputs.
     * @param {HTMLElement} display Element the countdown is written into.
     * @param {Function} onExpire Called when the server reports the quiz has closed.
     * @returns {object|null} The timer, or null when the quiz has no usable record id.
     */
    function startQuizTimer(recordId, body, display, onExpire) {
        if (!recordId || recordId <= 0) {
            return null;
        }
        var Timer = QuizTimer.default || QuizTimer;
        var timer = new Timer({
            recordId: recordId,
            body: body,
            display: display,
            collect: function() {
                return collectQuizResponses(body).responses;
            },
            onExpire: onExpire
        });
        timer.start().catch(function(err) {
            Notification.exception(err);
        });
        return timer;
    }

    /**
     * Show the authorship quiz modal: quiz HTML in body, footer with confirmation checkbox, Cancel, Submit.
     * Submit enabled only when all questions answered and checkbox checked. On Submit: verify_authorship then form.submit().
     *
     * @param {HTMLFormElement} form Assign form to submit after verification.
     * @param {string} quizHtml HTML fragment containing the quiz form (with authorship_record_id and authorship_q[id]).
     */
    function showQuizModal(form, quizHtml) {
        var modalTitleStr = 'authorship_quiz_modal_title';
        var confirmStr = 'authorship_confirm_statement';
        var confirmSubmitStr = 'authorship_confirm_and_submit';
        var cancelStr = 'authorship_cancel';

        Promise.all([
            Str.get_string(modalTitleStr, 'plagiarism_dixeo'),
            Str.get_string(confirmStr, 'plagiarism_dixeo'),
            Str.get_string(confirmSubmitStr, 'plagiarism_dixeo'),
            Str.get_string(cancelStr, 'plagiarism_dixeo'),
            Str.get_string('close', 'plagiarism_dixeo'),
            Str.get_string('authorship_quiz_load_error', 'plagiarism_dixeo')
        ]).then(function(labels) {
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

            var parser = new DOMParser();
            var parsed = parser.parseFromString(quizHtml.trim(), 'text/html');
            var quizFragment = parsed.body.firstElementChild;
            if (!quizFragment || !quizFragment.querySelector) {
                Notification.addNotification({message: loadError, type: 'error'});
                return null;
            }
            var actionsDiv = quizFragment.querySelector('.authorship-quiz-actions');
            if (actionsDiv && actionsDiv.parentNode) {
                actionsDiv.parentNode.removeChild(actionsDiv);
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
            var countdown = document.createElement('span');
            countdown.className = 'plagiarism-dixeo-quiz-countdown ml-auto mr-3';
            countdown.setAttribute('role', 'timer');
            countdown.setAttribute('aria-live', 'polite');
            var closeBtn = document.createElement('button');
            closeBtn.type = 'button';
            closeBtn.className = 'close';
            closeBtn.setAttribute('data-dismiss', 'modal');
            closeBtn.setAttribute('aria-label', closeLabel);
            closeBtn.innerHTML = '<span aria-hidden="true">&times;</span>';
            header.appendChild(titleEl);
            header.appendChild(countdown);
            header.appendChild(closeBtn);

            var body = document.createElement('div');
            body.className = 'modal-body plagiarism-dixeo-quiz-body';
            body.appendChild(quizFragment);

            var footer = document.createElement('div');
            footer.className = 'modal-footer';
            var confirmLabelEl = document.createElement('label');
            confirmLabelEl.className = 'mr-auto mb-0';
            var confirmCb = document.createElement('input');
            confirmCb.type = 'checkbox';
            confirmCb.className = 'plagiarism-dixeo-confirm-checkbox';
            confirmLabelEl.appendChild(confirmCb);
            confirmLabelEl.appendChild(document.createTextNode(' ' + confirmLabel));
            var cancelBtn = document.createElement('button');
            cancelBtn.type = 'button';
            cancelBtn.className = 'btn btn-secondary plagiarism-dixeo-quiz-cancel';
            cancelBtn.textContent = cancelLabel;
            var submitBtn = document.createElement('button');
            submitBtn.type = 'button';
            submitBtn.className = 'btn btn-primary plagiarism-dixeo-quiz-submit';
            submitBtn.disabled = true;
            submitBtn.textContent = submitLabel;
            footer.appendChild(confirmLabelEl);
            footer.appendChild(cancelBtn);
            footer.appendChild(submitBtn);

            content.appendChild(header);
            content.appendChild(body);
            content.appendChild(footer);
            dialog.appendChild(content);
            modal.appendChild(dialog);
            document.body.appendChild(modal);

            var $modal = $(modal);

            /**
             * Enable or disable the quiz submit button based on answers and confirmation checkbox.
             */
            function updateSubmitState() {
                var allAnswered = allQuizQuestionsAnswered(body);
                var confirmed = confirmCb && confirmCb.checked;
                submitBtn.disabled = !(allAnswered && confirmed);
            }

            body.addEventListener('change', updateSubmitState);
            body.addEventListener('input', updateSubmitState);
            confirmCb.addEventListener('change', updateSubmitState);

            cancelBtn.addEventListener('click', function() {
                $modal.modal('hide');
            });
            $modal.on('hidden.bs.modal', function() {
                if (modal.parentNode) {
                    modal.parentNode.removeChild(modal);
                }
            });

            submitBtn.addEventListener('click', function() {
                if (submitBtn.disabled) {
                    return;
                }
                var collected = collectQuizResponses(body);
                if (collected.recordId <= 0) {
                    notifyMissingRecord();
                    submitBtn.disabled = false;
                    return;
                }
                submitBtn.disabled = true;
                if (timer) {
                    timer.stop();
                }
                verifyAndSubmit(form, $modal, submitBtn, collected);
            });

            $modal.modal({backdrop: 'static', keyboard: false});

            var collected = collectQuizResponses(body);
            var timer = startQuizTimer(collected.recordId, body, countdown, function() {
                // The server has closed the quiz, so whatever has been answered is submitted as it stands.
                body.querySelectorAll('input, select, textarea').forEach(function(input) {
                    input.disabled = true;
                });
                submitBtn.disabled = true;
                verifyAndSubmit(form, $modal, submitBtn, collectQuizResponses(body));
            });
            $modal.on('hidden.bs.modal', function() {
                if (timer) {
                    timer.stop();
                }
            });
            return null;
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
