/**
 * Server-clocked countdown, answer autosave and resume for the authorship verification quiz.
 *
 * The browser only displays the countdown. How much time is left, and whether the quiz is
 * still open at all, is decided by the server on every sync, so reloading the page or
 * changing the clock does not buy the student more time.
 *
 * @module     plagiarism_dixeo/quiz_timer
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Ajax from 'core/ajax';
import {get_string as getString} from 'core/str';

/** @type {number} How often answers are sent to the server and the clock is re-synced, in milliseconds. */
const SYNC_INTERVAL = 15000;

/** @type {number} How often the displayed countdown is redrawn, in milliseconds. */
const TICK_INTERVAL = 1000;

/** @type {number} Seconds left below which the countdown is shown as urgent. */
const URGENT_THRESHOLD = 30;

/**
 * Format a number of seconds as mm:ss.
 *
 * @param {number} seconds Seconds remaining.
 * @returns {string}
 */
const formatClock = (seconds) => {
    const safe = Math.max(0, Math.floor(seconds));
    const minutes = Math.floor(safe / 60);
    return `${minutes}:${String(safe % 60).padStart(2, '0')}`;
};

/**
 * Drives one verification quiz attempt.
 */
export default class QuizTimer {
    /**
     * @param {object} options Timer options.
     * @param {number} options.recordId Authorship record id.
     * @param {HTMLElement} options.body Element containing the quiz inputs.
     * @param {HTMLElement} options.display Element the countdown is written into.
     * @param {Function} options.collect Returns the current answers as an object keyed by question id.
     * @param {Function} options.onExpire Called once when the server reports the quiz has closed.
     */
    constructor({recordId, body, display, collect, onExpire}) {
        this.recordId = recordId;
        this.body = body;
        this.display = display;
        this.collect = collect;
        this.onExpire = onExpire;
        this.remaining = null;
        this.expired = false;
        this.stopped = false;
        this.tickHandle = null;
        this.syncHandle = null;
        this.focusStart = null;
        this.focusQuestion = null;
        this.timings = {};
    }

    /**
     * Start or resume the quiz and begin counting down.
     *
     * @returns {Promise<void>}
     */
    async start() {
        const state = await Ajax.call([{
            methodname: 'plagiarism_dixeo_start_verification',
            args: {record_id: this.recordId}, // eslint-disable-line camelcase
        }])[0];

        this.adopt(state);
        if (this.expired) {
            return;
        }

        await this.restoreAnswers();
        this.trackQuestionFocus();

        this.tickHandle = setInterval(() => this.tick(), TICK_INTERVAL);
        this.syncHandle = setInterval(() => this.sync(), SYNC_INTERVAL);
        this.render();
    }

    /**
     * Stop counting and sending answers.
     */
    stop() {
        this.stopped = true;
        clearInterval(this.tickHandle);
        clearInterval(this.syncHandle);
    }

    /**
     * Put answers saved earlier back into the form, so a reload resumes where the student left off.
     *
     * @returns {Promise<void>}
     */
    async restoreAnswers() {
        const quiz = await Ajax.call([{
            methodname: 'plagiarism_dixeo_get_verification_quiz',
            args: {record_id: this.recordId}, // eslint-disable-line camelcase
        }])[0];

        this.adopt(quiz);

        let answers;
        try {
            answers = JSON.parse(quiz.answers || '{}');
        } catch (e) {
            return;
        }

        Object.entries(answers).forEach(([questionId, value]) => {
            const selector = `[name="authorship_q[${CSS.escape(questionId)}]"]`;
            this.body.querySelectorAll(selector).forEach((input) => {
                if (input.type === 'radio' || input.type === 'checkbox') {
                    input.checked = input.value === value;
                } else {
                    input.value = value;
                }
            });
        });

        this.body.dispatchEvent(new Event('change', {bubbles: true}));
    }

    /**
     * Accumulate how long the student spends on each question.
     */
    trackQuestionFocus() {
        const questionOf = (element) => {
            const match = (element.name || '').match(/authorship_q\[([^\]]+)\]/);
            return match ? match[1] : null;
        };

        this.body.addEventListener('focusin', (event) => {
            const questionId = questionOf(event.target);
            if (questionId) {
                this.focusQuestion = questionId;
                this.focusStart = Date.now();
            }
        });

        this.body.addEventListener('focusout', () => this.bankFocusTime());
    }

    /**
     * Add the time spent on the question being left to its running total.
     */
    bankFocusTime() {
        if (this.focusQuestion === null || this.focusStart === null) {
            return;
        }
        const spent = Math.round((Date.now() - this.focusStart) / 1000);
        this.timings[this.focusQuestion] = (this.timings[this.focusQuestion] || 0) + spent;
        this.focusQuestion = null;
        this.focusStart = null;
    }

    /**
     * Take the server's view of the clock.
     *
     * @param {object} state Response from any of the verification endpoints.
     */
    adopt(state) {
        if (!state) {
            return;
        }
        this.remaining = state.remaining >= 0 ? state.remaining : null;
        if (state.expired || this.remaining === 0) {
            this.expire();
        }
    }

    /**
     * Count down one second locally between syncs.
     */
    tick() {
        if (this.remaining === null) {
            return;
        }
        this.remaining = Math.max(0, this.remaining - 1);
        this.render();
        if (this.remaining === 0) {
            this.sync();
        }
    }

    /**
     * Send the current answers to the server and take back the authoritative time remaining.
     *
     * @returns {Promise<void>}
     */
    async sync() {
        if (this.stopped) {
            return;
        }
        this.bankFocusTime();

        try {
            const state = await Ajax.call([{
                methodname: 'plagiarism_dixeo_save_verification_progress',
                args: {
                    record_id: this.recordId, // eslint-disable-line camelcase
                    responses: JSON.stringify(this.collect()),
                    timings: JSON.stringify(this.timings),
                    current_index: 0, // eslint-disable-line camelcase
                },
            }])[0];
            this.adopt(state);
        } catch (e) {
            // A failed sync is not fatal: the server clock still governs, and the next sync retries.
            return;
        }
    }

    /**
     * Close the quiz once and hand over to the caller.
     */
    expire() {
        if (this.expired) {
            return;
        }
        this.expired = true;
        this.remaining = 0;
        this.stop();
        this.render();
        if (this.onExpire) {
            this.onExpire();
        }
    }

    /**
     * Redraw the countdown.
     *
     * @returns {Promise<void>}
     */
    async render() {
        if (!this.display || this.remaining === null) {
            return;
        }
        const key = this.expired ? 'quiz_expired' : 'quiz_time_remaining';
        const argument = this.expired ? undefined : formatClock(this.remaining);
        this.display.textContent = await getString(key, 'plagiarism_dixeo', argument);
        this.display.classList.toggle('text-danger', this.expired || this.remaining <= URGENT_THRESHOLD);
    }
}
