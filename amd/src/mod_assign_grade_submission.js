/**
 * Analyze button and AI grading inject on mod/assign grader view.
 *
 * @module     plagiarism_dixeo/mod_assign_grade_submission
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax', 'core/notification', 'core/str', 'core/templates', 'core/loadingicon',
    'plagiarism_dixeo/assign_job_poller'],
function($, Ajax, Notification, Str, Templates, LoadingIcon, AssignJobPoller) {

    var LOADING_MODAL_ID = 'plagiarism_dixeo_loading_modal';
    var DRAFT_NOTICE_ID = 'plagiarism_dixeo_grade_draft_notice';

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
     * @param {string} message Text to show below the spinner (e.g. "Analyzing...").
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
     * Insert analysis block right above .gradingform.
     *
     * @param {string} analysis HTML or text for the teacher (server-sanitized).
     * @returns {Promise}
     */
    function injectAnalysis(analysis) {
        var gradingform = document.querySelector('.gradingform');
        if (!gradingform || !analysis) {
            return Promise.resolve();
        }
        return Str.get_string('analysis_aria_label', 'plagiarism_dixeo').then(function(ariaLabel) {
            return Templates.render('plagiarism_dixeo/html_fragment', {
                html: analysis,
                classes: 'plagiarism-dixeo-grade-analysis alert alert-info',
                arialabel: ariaLabel
            });
        }).then(function(html, js) {
            var wrap = document.createElement('div');
            wrap.innerHTML = html.trim();
            var node = wrap.firstChild;
            gradingform.parentNode.insertBefore(node, gradingform);
            if (js) {
                Templates.runTemplateJS(js);
            }
            return null;
        });
    }

    /**
     * Set feedback HTML into the assign feedback comments editor.
     * Updates the hidden textarea and the TinyMCE instance (Moodle editor_tiny) so the visible editor reflects the content.
     *
     * @param {string} feedbackHtml HTML for the student feedback.
     */
    function injectFeedback(feedbackHtml) {
        if (!feedbackHtml) {
            return;
        }
        var fitem = document.getElementById('fitem_id_assignfeedbackcomments_editor');
        if (!fitem) {
            return;
        }
        var textarea = fitem.querySelector('textarea[id="id_assignfeedbackcomments_editor"]');
        if (textarea) {
            textarea.value = feedbackHtml;
            textarea.dispatchEvent(new Event('input', {bubbles: true}));
            textarea.dispatchEvent(new Event('change', {bubbles: true}));
        }
        // Moodle 4+ TinyMCE (editor_tiny): set content in the editor so the visible UI updates and sync to textarea.
        require(['editor_tiny/editor'], function(editorTiny) {
            var instance = editorTiny.getInstanceForElementId('id_assignfeedbackcomments_editor');
            if (instance) {
                instance.setContent(feedbackHtml);
                if (typeof instance.save === 'function') {
                    instance.save();
                }
            }
        }, function() {
            // Editor_tiny not available (e.g. Atto or no JS editor); textarea already set above.
        });
    }

    /**
     * Set simple direct grade into #id_grade.
     *
     * @param {number} grade Numeric grade.
     */
    function injectGrade(grade) {
        var el = document.getElementById('id_grade');
        if (el && typeof grade === 'number' && !isNaN(grade)) {
            el.value = String(grade);
            el.dispatchEvent(new Event('input', {bubbles: true}));
            el.dispatchEvent(new Event('change', {bubbles: true}));
        }
    }

    /**
     * Set marking guide criteria: remark and score by criterion id.
     *
     * @param {Array} criteria Array of { id, remark, score }.
     */
    function injectGuideCriteria(criteria) {
        if (!Array.isArray(criteria)) {
            return;
        }
        criteria.forEach(function(c) {
            var critId = c.id;
            if (critId === null || critId === undefined) {
                return;
            }
            var remarkEl = document.querySelector('[name="advancedgrading[criteria][' + critId + '][remark]"]');
            if (remarkEl && c.remark !== null && c.remark !== undefined) {
                remarkEl.value = c.remark;
                remarkEl.dispatchEvent(new Event('input', {bubbles: true}));
                remarkEl.dispatchEvent(new Event('change', {bubbles: true}));
            }
            var scoreEl = document.querySelector('[name="advancedgrading[criteria][' + critId + '][score]"]');
            if (scoreEl && c.score !== null && c.score !== undefined && !isNaN(Number(c.score))) {
                scoreEl.value = String(c.score);
                scoreEl.dispatchEvent(new Event('input', {bubbles: true}));
                scoreEl.dispatchEvent(new Event('change', {bubbles: true}));
            }
        });
    }

    /**
     * Clear checked state on sibling rubric level cells.
     *
     * @param {HTMLElement} levelCell Selected level cell.
     */
    function clearSiblingLevelCells(levelCell) {
        var row = levelCell.parentElement;
        if (!row) {
            return;
        }
        var siblingLevels = row.querySelectorAll('td.level');
        for (var i = 0; i < siblingLevels.length; i++) {
            if (siblingLevels[i] !== levelCell) {
                siblingLevels[i].classList.remove('checked');
                siblingLevels[i].setAttribute('aria-checked', 'false');
            }
        }
    }

    /**
     * Apply checked state to a rubric level radio and its cell UI.
     *
     * @param {HTMLInputElement} radio Level radio input.
     */
    function selectRubricLevel(radio) {
        if (radio.checked) {
            return;
        }
        radio.checked = true;
        radio.dispatchEvent(new Event('change', {bubbles: true}));
        var levelCell = radio.closest('td.level');
        if (!levelCell) {
            return;
        }
        levelCell.classList.add('checked');
        levelCell.setAttribute('aria-checked', 'true');
        clearSiblingLevelCells(levelCell);
    }

    /**
     * Set rubric criteria: remark and selected level (radio) by criterion id and level id.
     *
     * @param {Array} criteria Array of { id, remark, levelid }.
     */
    function injectRubricCriteria(criteria) {
        if (!Array.isArray(criteria)) {
            return;
        }
        criteria.forEach(function(c) {
            var critId = c.id;
            if (critId === null || critId === undefined) {
                return;
            }
            var remarkEl = document.querySelector('[name="advancedgrading[criteria][' + critId + '][remark]"]');
            if (remarkEl && c.remark !== null && c.remark !== undefined) {
                remarkEl.value = c.remark;
                remarkEl.dispatchEvent(new Event('input', {bubbles: true}));
                remarkEl.dispatchEvent(new Event('change', {bubbles: true}));
            }
            var levelId = c.levelid;
            if (levelId === null || levelId === undefined) {
                return;
            }
            var radio = document.querySelector(
                '[name="advancedgrading[criteria][' + critId + '][levelid]"][value="' + levelId + '"]'
            );
            if (radio) {
                selectRubricLevel(radio);
            }
        });
    }

    /**
     * Apply the full grading response to the page.
     *
     * @param {Object} data Response from the web service.
     * @returns {Promise}
     */
    function applyResponse(data) {
        if (!data) {
            return Promise.resolve();
        }
        return injectAnalysis(data.analysis).then(function() {
            injectFeedback(data.feedback);
            if (data.gradingmethod === 'simple' && data.grade !== null && data.grade !== undefined) {
                injectGrade(Number(data.grade));
            }
            if (data.gradingmethod === 'guide' && data.criteria) {
                injectGuideCriteria(data.criteria);
            }
            if (data.gradingmethod === 'rubric' && data.criteria) {
                injectRubricCriteria(data.criteria);
            }
            return null;
        });
    }

    /**
     * Put a banner above the grading form saying the values below were written by AI and
     * have not been saved.
     *
     * @returns {Promise}
     */
    function showDraftNotice() {
        var gradingform = document.querySelector('.gradingform');
        if (!gradingform || document.getElementById(DRAFT_NOTICE_ID)) {
            return Promise.resolve();
        }
        return Str.get_string('grade_draft_notice', 'plagiarism_dixeo').then(function(message) {
            var notice = document.createElement('div');
            notice.id = DRAFT_NOTICE_ID;
            notice.className = 'alert alert-warning plagiarism-dixeo-grade-draft-notice';
            notice.setAttribute('role', 'status');
            notice.textContent = message;
            gradingform.parentNode.insertBefore(notice, gradingform);
            return null;
        });
    }

    /**
     * Pre-fill the grading form from a draft grade prepared earlier, if one is waiting.
     *
     * @param {number} cmid Course module id.
     * @param {number} uid Student user id.
     * @returns {Promise}
     */
    function loadDraftGrade(cmid, uid) {
        var draft = null;

        return Ajax.call([{
            methodname: 'plagiarism_dixeo_get_grade_draft',
            args: {id: cmid, userid: uid}
        }])[0].then(function(data) {
            if (!data || !data.hasdraft) {
                return null;
            }
            draft = data;
            return showDraftNotice();
        }).then(function() {
            return draft ? applyResponse(draft) : null;
        }).catch(function() {
            // A missing draft must never stop the teacher from grading by hand.
            return null;
        });
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

    /**
     * Find the "Save changes" submit button and add an "Analyze" button before it.
     * On Analyze click: show analyzing modal (locked), call web service (id/userid from URL), inject response, hide modal.
     */
    function run() {
        var cmid = getUrlParam('id').trim();
        var uid = getUrlParam('userid').trim();
        if (!cmid || !uid) {
            return;
        }

        var saveButton = document.querySelector('form[data-region="grading-actions-form"] button[name="savechanges"]');
        if (!saveButton) {
            return;
        }

        Str.get_string('analyze', 'plagiarism_dixeo').then(function(analyzeLabel) {
            var analyzeButton = document.createElement('button');
            analyzeButton.type = 'button';
            analyzeButton.className = 'btn btn-primary mr-1';
            analyzeButton.id = 'plagiarism_dixeo_grade_analyze_btn';

            var img = document.createElement('img');
            img.src = (typeof M !== 'undefined' && M.cfg && M.cfg.wwwroot)
                ? M.cfg.wwwroot + '/plagiarism/dixeo/pix/dixeo-white.svg'
                : '';
            img.alt = '';
            img.width = 20;
            img.height = 20;
            analyzeButton.appendChild(img);
            analyzeButton.appendChild(document.createTextNode(' ' + analyzeLabel));

            saveButton.parentNode.insertBefore(analyzeButton, saveButton);
            attachAnalyzeClickHandler(analyzeButton, cmid, uid);
            return null;
        }).catch(function() {
            // If strings fail, leave the grader without the Analyze button.
        });

        loadDraftGrade(parseInt(cmid, 10) || 0, parseInt(uid, 10) || 0);
    }

    /**
     * Attach click handler to the Analyze button (grader view).
     *
     * @param {HTMLButtonElement} analyzeButton
     * @param {string} cmid Course module ID from URL.
     * @param {string} uid User ID from URL.
     */
    function attachAnalyzeClickHandler(analyzeButton, cmid, uid) {

        analyzeButton.addEventListener('click', function() {
            analyzeButton.disabled = true;
            var cmidInt = parseInt(cmid, 10) || 0;

            Str.get_string('analyzing', 'plagiarism_dixeo').then(function(analyzingMsg) {
                return showLoadingModal(analyzingMsg);
            }).then(function() {
                return Ajax.call([{
                    methodname: 'plagiarism_dixeo_mod_assign_grade_submission',
                    args: {
                        id: cmidInt,
                        userid: parseInt(uid, 10) || 0
                    }
                }])[0];
            }).then(function(data) {
                if (!data || data.status === 'failed') {
                    if (data && data.errormessage) {
                        Notification.addNotification({message: data.errormessage, type: 'error'});
                    }
                    return null;
                }
                if (data.status === 'completed') {
                    return data;
                }
                return AssignJobPoller.poll(data.jobid, data.courseid, cmidInt, 'grade');
            }).then(function(data) {
                if (!data) {
                    return null;
                }
                if (data.status === 'failed') {
                    if (data.errormessage) {
                        Notification.addNotification({message: data.errormessage, type: 'error'});
                    }
                    return null;
                }
                return applyResponse(data);
            }).catch(function(err) {
                Notification.exception(err);
            }).then(function() {
                analyzeButton.disabled = false;
                hideLoadingModal();
                return null;
            }).catch(function() {
                // Ignore cleanup errors.
            });
        });
    }

    /**
     * Initialize: add Analyze button and wire it to the grade submission web service (id/userid read from URL).
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
