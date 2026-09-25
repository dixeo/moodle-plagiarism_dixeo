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

import {get_string as getString} from 'core/str';

const SELECTORS = {
    maximize: '.plagiarism-dixeo-panel-maximize',
    panel: '.plagiarism-dixeo-panel',
    header: '.card-header',
    title: '.plagiarism-dixeo-panel-header-title',
    body: '.card-body',
    overlay: '.plagiarism-dixeo-panel-overlay',
};

let registered = false;

/**
 * Build the enlarged copy of a panel and show it over the page.
 *
 * @param {HTMLElement} panel The panel that was maximized.
 * @param {String} minimizeLabel Accessible label for the close button.
 * @returns {void}
 */
const openOverlay = (panel, minimizeLabel) => {
    const header = panel.querySelector(SELECTORS.header);
    const title = panel.querySelector(SELECTORS.title);
    const body = panel.querySelector(SELECTORS.body);
    if (!header || !title || !body) {
        return;
    }

    const overlay = document.createElement('div');
    overlay.className = 'plagiarism-dixeo-panel-overlay';
    overlay.setAttribute('role', 'dialog');
    overlay.setAttribute('aria-modal', 'true');
    overlay.setAttribute('aria-label', minimizeLabel);

    const backdrop = document.createElement('div');
    backdrop.className = 'plagiarism-dixeo-panel-overlay-backdrop';

    const card = document.createElement('div');
    card.className = 'card plagiarism-dixeo-panel-overlay-card';

    const headerCopy = document.createElement('div');
    headerCopy.className = header.className;

    const titleCopy = document.createElement('div');
    titleCopy.className = 'plagiarism-dixeo-panel-header-title';
    titleCopy.textContent = title.textContent.trim();
    headerCopy.appendChild(titleCopy);

    const minimize = document.createElement('button');
    minimize.type = 'button';
    minimize.className = 'btn btn-outline-default text-white btn-sm plagiarism-dixeo-panel-minimize p-0';
    minimize.setAttribute('aria-label', minimizeLabel);
    minimize.title = minimizeLabel;
    const icon = document.createElement('span');
    icon.className = 'icon fa fa-compress fa-fw';
    icon.setAttribute('aria-hidden', 'true');
    minimize.appendChild(icon);
    headerCopy.appendChild(minimize);

    const bodyCopy = body.cloneNode(true);
    bodyCopy.classList.remove('collapse');

    card.appendChild(headerCopy);
    card.appendChild(bodyCopy);
    overlay.appendChild(backdrop);
    overlay.appendChild(card);
    document.body.appendChild(overlay);

    const close = () => {
        overlay.remove();
        document.removeEventListener('keydown', onKeydown);
    };

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

    minimize.addEventListener('click', close);
    backdrop.addEventListener('click', close);
    document.addEventListener('keydown', onKeydown);
    minimize.focus();
};

/**
 * Listen for clicks on any panel's maximize button.
 *
 * @returns {void}
 */
export const init = () => {
    if (registered) {
        return;
    }
    registered = true;

    document.addEventListener('click', async(event) => {
        const button = event.target.closest(SELECTORS.maximize);
        if (!button || document.querySelector(SELECTORS.overlay)) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();

        const panel = button.closest(SELECTORS.panel);
        if (panel) {
            openOverlay(panel, await getString('panel_minimize', 'plagiarism_dixeo'));
        }
    });
};
