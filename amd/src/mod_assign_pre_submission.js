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
    var QUIZ_MODAL_ID = 'plagiarism_dixeo_authorship_quiz_modal';
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
                        showQuizModal(form, (data.quiz_html || ''));
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
        return { recordId: recordId, responses: responses };
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
            Str.get_string(cancelStr, 'plagiarism_dixeo')
        ]).then(function(labels) {
            var title = labels[0];
            var confirmLabel = labels[1];
            var submitLabel = labels[2];
            var cancelLabel = labels[3];

            var existing = document.getElementById(QUIZ_MODAL_ID);
            if (existing && existing.parentNode) {
                existing.parentNode.removeChild(existing);
            }

            var wrap = document.createElement('div');
            wrap.innerHTML = quizHtml.trim();
            var quizFragment = wrap.firstChild;
            if (!quizFragment || !quizFragment.querySelector) {
                Notification.addNotification({ message: 'Verification content could not be loaded.', type: 'error' });
                return;
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
            var closeBtn = '<button type="button" class="close" data-dismiss="modal" ' +
                'aria-label="Close"><span aria-hidden="true">&times;</span></button>';
            var confirmLabelHtml = '<label class="mr-auto mb-0">' +
                '<input type="checkbox" class="local-dixeo-ux-confirm-checkbox" /> ' + confirmLabel +
                '</label>';
            var submitBtnHtml = '<button type="button" class="btn btn-primary ' +
                'local-dixeo-ux-quiz-submit" disabled>' + submitLabel + '</button>';
            modal.innerHTML =
                '<div class="modal-dialog modal-lg" role="document">' +
                '  <div class="modal-content">' +
                '    <div class="modal-header">' +
                '      <h5 class="modal-title">' + title + '</h5>' + closeBtn +
                '    </div>' +
                '    <div class="modal-body local-dixeo-ux-quiz-body"></div>' +
                '    <div class="modal-footer">' +
                '      ' + confirmLabelHtml +
                '      <button type="button" class="btn btn-secondary plagiarism-dixeo-quiz-cancel">' +
                cancelLabel + '</button>' +
                '      ' + submitBtnHtml +
                '    </div>' +
                '  </div>' +
                '</div>';
            var body = modal.querySelector('.local-dixeo-ux-quiz-body');
            body.appendChild(quizFragment);

            document.body.appendChild(modal);

            var $modal = $(modal);
            var confirmCb = modal.querySelector('.local-dixeo-ux-confirm-checkbox');
            var submitBtn = modal.querySelector('.local-dixeo-ux-quiz-submit');
            var cancelBtn = modal.querySelector('.plagiarism-dixeo-quiz-cancel');

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
            if (confirmCb) {
                confirmCb.addEventListener('change', updateSubmitState);
            }

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
                    Str.get_string('authorship_error_missing_record', 'plagiarism_dixeo').then(function(msg) {
                        Notification.addNotification({ message: msg, type: 'error' });
                    });
                    submitBtn.disabled = false;
                    return;
                }
                submitBtn.disabled = true;
                Ajax.call([{
                    methodname: 'plagiarism_dixeo_verify_authorship',
                    args: {
                        record_id: collected.recordId,
                        responses: JSON.stringify(collected.responses)
                    },
                    done: function() {
                        $modal.modal('hide');
                        submissionAuthorized = true;
                        form.submit();
                    },
                    fail: function(err) {
                        Notification.exception(err);
                        submitBtn.disabled = false;
                    }
                }]);
            });

            $modal.modal({ backdrop: 'static', keyboard: false });
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
