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
 * Maximize and minimize behaviour for the authorship panels.
 *
 * @module     plagiarism_dixeo/panel
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/str'], function(Str) {

    var SELECTORS = {
        maximize: '.plagiarism-dixeo-panel-maximize',
        panel: '.plagiarism-dixeo-panel',
        header: '.card-header',
        title: '.plagiarism-dixeo-panel-header-title',
        body: '.card-body',
        overlay: '.plagiarism-dixeo-panel-overlay'
    };

    var registered = false;

    /**
     * Build the enlarged copy of a panel and show it over the page.
     *
     * @param {HTMLElement} panel The panel that was maximized.
     * @param {String} minimizeLabel Accessible label for the close button.
     * @returns {void}
     */
    function openOverlay(panel, minimizeLabel) {
        var header = panel.querySelector(SELECTORS.header);
        var title = panel.querySelector(SELECTORS.title);
        var body = panel.querySelector(SELECTORS.body);
        var overlay;
        var backdrop;
        var card;
        var headerCopy;
        var titleCopy;
        var minimize;
        var icon;
        var bodyCopy;

        if (!header || !title || !body) {
            return;
        }

        overlay = document.createElement('div');
        overlay.className = 'plagiarism-dixeo-panel-overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');
        overlay.setAttribute('aria-label', minimizeLabel);

        backdrop = document.createElement('div');
        backdrop.className = 'plagiarism-dixeo-panel-overlay-backdrop';

        card = document.createElement('div');
        card.className = 'card plagiarism-dixeo-panel-overlay-card';

        headerCopy = document.createElement('div');
        headerCopy.className = header.className;

        titleCopy = document.createElement('div');
        titleCopy.className = 'plagiarism-dixeo-panel-header-title';
        titleCopy.textContent = title.textContent.trim();
        headerCopy.appendChild(titleCopy);

        minimize = document.createElement('button');
        minimize.type = 'button';
        minimize.className = 'btn btn-outline-default text-white btn-sm plagiarism-dixeo-panel-minimize p-0';
        minimize.setAttribute('aria-label', minimizeLabel);
        minimize.title = minimizeLabel;
        icon = document.createElement('span');
        icon.className = 'icon fa fa-compress fa-fw';
        icon.setAttribute('aria-hidden', 'true');
        minimize.appendChild(icon);
        headerCopy.appendChild(minimize);

        bodyCopy = body.cloneNode(true);
        bodyCopy.classList.remove('collapse');

        card.appendChild(headerCopy);
        card.appendChild(bodyCopy);
        overlay.appendChild(backdrop);
        overlay.appendChild(card);
        document.body.appendChild(overlay);

        /**
         * Close the overlay on Escape.
         *
         * @param {KeyboardEvent} event The key event.
         * @returns {void}
         */
        function onKeydown(event) {
            if (event.key === 'Escape') {
                close();
            }
        }

        /**
         * Remove the overlay and its key listener.
         *
         * @returns {void}
         */
        function close() {
            if (overlay.parentNode) {
                overlay.parentNode.removeChild(overlay);
            }
            document.removeEventListener('keydown', onKeydown);
        }

        minimize.addEventListener('click', close);
        backdrop.addEventListener('click', close);
        document.addEventListener('keydown', onKeydown);
        minimize.focus();
    }

    /**
     * Listen for clicks on any panel's maximize button.
     *
     * @returns {void}
     */
    function init() {
        if (registered) {
            return;
        }
        registered = true;

        document.addEventListener('click', function(event) {
            var button = event.target.closest ? event.target.closest(SELECTORS.maximize) : null;
            var panel;
            if (!button || document.querySelector(SELECTORS.overlay)) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            panel = button.closest(SELECTORS.panel);
            if (panel) {
                Str.get_string('panel_minimize', 'plagiarism_dixeo').then(function(minimizeLabel) {
                    openOverlay(panel, minimizeLabel);
                    return true;
                }).catch(function() {
                    return false;
                });
            }
        });
    }

    return {
        init: init
    };
});
