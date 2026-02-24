/**
 * Mod assign grade submission: adds an Analyze button and injects AI grading result into the grader form.
 *
 * @module     plagiarism_dixeo/mod_assign_grade_submission
 * @author     Josemaria Bolanos <admin@mako.digital>
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/ajax', 'core/notification', 'core/str', 'core/templates', 'core/loadingicon'],
function($, Ajax, Notification, Str, Templates, LoadingIcon) {

    var LOADING_MODAL_ID = 'plagiarism_dixeo_loading_modal';

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
     * Insert analysis block right above .gradingform.
     *
     * @param {string} analysis HTML or text for the teacher.
     */
    function injectAnalysis(analysis) {
        var gradingform = document.querySelector('.gradingform');
        if (!gradingform || !analysis) {
            return;
        }
        var wrap = document.createElement('div');
        wrap.className = 'plagiarism-dixeo-grade-analysis alert alert-info';
        wrap.setAttribute('role', 'region');
        wrap.setAttribute('aria-label', 'AI analysis');
        wrap.innerHTML = typeof analysis === 'string' ? analysis : '';
        gradingform.parentNode.insertBefore(wrap, gradingform);
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
            textarea.dispatchEvent(new Event('input', { bubbles: true }));
            textarea.dispatchEvent(new Event('change', { bubbles: true }));
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
            // editor_tiny not available (e.g. Atto or no JS editor); textarea already set above.
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
            el.dispatchEvent(new Event('input', { bubbles: true }));
            el.dispatchEvent(new Event('change', { bubbles: true }));
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
                remarkEl.dispatchEvent(new Event('input', { bubbles: true }));
                remarkEl.dispatchEvent(new Event('change', { bubbles: true }));
            }
            var scoreEl = document.querySelector('[name="advancedgrading[criteria][' + critId + '][score]"]');
            if (scoreEl && c.score !== null && c.score !== undefined && !isNaN(Number(c.score))) {
                scoreEl.value = String(c.score);
                scoreEl.dispatchEvent(new Event('input', { bubbles: true }));
                scoreEl.dispatchEvent(new Event('change', { bubbles: true }));
            }
        });
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
                remarkEl.dispatchEvent(new Event('input', { bubbles: true }));
                remarkEl.dispatchEvent(new Event('change', { bubbles: true }));
            }
            var levelId = c.levelid;
            if (levelId !== null && levelId !== undefined) {
                var radio = document.querySelector(
                    '[name="advancedgrading[criteria][' + critId + '][levelid]"][value="' + levelId + '"]'
                );
                if (radio && !radio.checked) {
                    radio.checked = true;
                    radio.dispatchEvent(new Event('change', { bubbles: true }));
                    // Sync legacy rubric UI: level cell (td.level) gets "checked" class and aria-checked like manual selection.
                    var levelCell = radio.closest('td.level');
                    if (levelCell) {
                        levelCell.classList.add('checked');
                        levelCell.setAttribute('aria-checked', 'true');
                        var row = levelCell.parentElement;
                        if (row) {
                            var siblingLevels = row.querySelectorAll('td.level');
                            for (var i = 0; i < siblingLevels.length; i++) {
                                if (siblingLevels[i] !== levelCell) {
                                    siblingLevels[i].classList.remove('checked');
                                    siblingLevels[i].setAttribute('aria-checked', 'false');
                                }
                            }
                        }
                    }
                }
            }
        });
    }

    /**
     * Apply the full grading response to the page.
     *
     * @param {Object} data Response from the web service.
     */
    function applyResponse(data) {
        if (!data) {
            return;
        }
        injectAnalysis(data.analysis);
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
        });
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

            Str.get_string('analyzing', 'plagiarism_dixeo').then(function(analyzingMsg) {
                return showLoadingModal(analyzingMsg);
            }).then(function() {
                Ajax.call([{
                    methodname: 'plagiarism_dixeo_mod_assign_grade_submission',
                    args: {
                        id: parseInt(cmid, 10) || 0,
                        userid: parseInt(uid, 10) || 0
                    },
                    done: function(data) {
                        applyResponse(data);
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
