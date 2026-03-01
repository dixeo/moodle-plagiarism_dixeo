/**
 * Isolated panel behaviour: maximize/minimize for authorship panels with explanation body.
 * Loaded when panels are present (e.g. assign view or grader view). Does not depend on pre_submission.
 *
 * @module     plagiarism_dixeo/panel
 * @copyright  2026 Dixeo.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery', 'core/str'], function($, Str) {

    /**
     * Attach maximize click handler: show panel header + body in overlay; minimize button and Escape close it.
     */
    function init() {
        $(document).off('click.plagiarism_dixeo_panel_maximize', '.plagiarism-dixeo-panel-maximize');
        $(document).on('click.plagiarism_dixeo_panel_maximize', '.plagiarism-dixeo-panel-maximize', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var btn = e.currentTarget;
            var panel = btn.closest('.plagiarism-dixeo-panel');
            if (!panel) {
                return;
            }
            var headerEl = panel.querySelector('.card-header');
            var titleEl = panel.querySelector('.plagiarism-dixeo-panel-header-title');
            var bodyEl = panel.querySelector('.card-body');
            if (!headerEl || !titleEl || !bodyEl) {
                return;
            }
            Str.get_string('panel_minimize', 'plagiarism_dixeo').then(function(minLabel) {
                var overlay = document.createElement('div');
                overlay.className = 'plagiarism-dixeo-panel-overlay';
                overlay.setAttribute('role', 'dialog');
                overlay.setAttribute('aria-modal', 'true');
                overlay.setAttribute('aria-label', minLabel);

                var backdrop = document.createElement('div');
                backdrop.className = 'plagiarism-dixeo-panel-overlay-backdrop';

                var card = document.createElement('div');
                card.className = 'card plagiarism-dixeo-panel-overlay-card';

                var headerInner = document.createElement('div');
                headerInner.className = headerEl.className;
                var titleWrap = document.createElement('div');
                titleWrap.className = 'plagiarism-dixeo-panel-header-title';
                titleWrap.innerHTML = titleEl.innerHTML;
                headerInner.appendChild(titleWrap);

                var minBtn = document.createElement('button');
                minBtn.type = 'button';
                minBtn.className = 'btn btn-outline-default text-white btn-sm plagiarism-dixeo-panel-minimize p-0';
                minBtn.setAttribute('aria-label', minLabel);
                minBtn.title = minLabel;
                minBtn.innerHTML = '<span class="icon fa fa-compress fa-fw" aria-hidden="true"></span>';
                headerInner.appendChild(minBtn);

                card.appendChild(headerInner);

                var bodyClone = bodyEl.cloneNode(true);
                bodyClone.classList.remove('collapse');
                card.appendChild(bodyClone);

                overlay.appendChild(backdrop);
                overlay.appendChild(card);
                document.body.appendChild(overlay);

                /** Remove overlay from DOM and unbind Escape. */
                function closeOverlay() {
                    if (overlay.parentNode) {
                        overlay.parentNode.removeChild(overlay);
                    }
                    document.removeEventListener('keydown', onEscape);
                }

                /**
                 * Close overlay when Escape is pressed.
                 * @param {KeyboardEvent} ev The key event.
                 */
                function onEscape(ev) {
                    if (ev.key === 'Escape') {
                        closeOverlay();
                    }
                }

                minBtn.addEventListener('click', closeOverlay);
                backdrop.addEventListener('click', closeOverlay);
                document.addEventListener('keydown', onEscape);
                minBtn.focus();
            });
        });
    }

    return {
        init: init
    };
});
