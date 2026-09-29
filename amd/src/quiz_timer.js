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

define(['core/ajax', 'core/str'], function(Ajax, Str) {

    /** How often answers are sent to the server and the clock is re-synced, in milliseconds. */
    var SYNC_INTERVAL = 15000;

    /** How often the displayed countdown is redrawn, in milliseconds. */
    var TICK_INTERVAL = 1000;

    /** Seconds left below which the countdown is shown as urgent. */
    var URGENT_THRESHOLD = 30;

    /**
     * Two-digit seconds fragment for mm:ss.
     *
     * @param {number} n Seconds remainder 0–59.
     * @returns {string}
     */
    function pad2(n) {
        return (n < 10 ? '0' : '') + n;
    }

    /**
     * Format a number of seconds as mm:ss.
     *
     * @param {number} seconds Seconds remaining.
     * @returns {string}
     */
    function formatClock(seconds) {
        var safe = Math.max(0, Math.floor(seconds));
        var minutes = Math.floor(safe / 60);
        return minutes + ':' + pad2(safe % 60);
    }

    /**
     * Escape a value for use inside a CSS attribute selector.
     *
     * @param {string} value Raw question id.
     * @returns {string}
     */
    function cssEscape(value) {
        if (window.CSS && window.CSS.escape) {
            return window.CSS.escape(value);
        }
        return String(value).replace(/[^a-zA-Z0-9_-]/g, '\\$&');
    }

    /**
     * Drives one verification quiz attempt.
     *
     * @param {object} options Timer options.
     * @param {number} options.recordId Authorship record id.
     * @param {HTMLElement} options.body Element containing the quiz inputs.
     * @param {HTMLElement} [options.display] Element the total countdown is written into.
     * @param {HTMLElement} [options.questionDisplay] Element the question countdown is written into.
     * @param {Function} options.collect Returns the current answers as an object keyed by question id.
     * @param {Function} [options.getCurrentIndex] Returns the pager index of the visible question.
     * @param {Function} options.onExpire Called once when the server reports the quiz has closed.
     * @param {Function} [options.onQuestionExpire] Called once when the current question's time runs out.
     * @param {Function} [options.onState] Called whenever a server payload is adopted.
     */
    function QuizTimer(options) {
        options = options || {};
        this.recordId = options.recordId;
        this.body = options.body;
        this.display = options.display || null;
        this.questionDisplay = options.questionDisplay || null;
        this.collect = options.collect;
        this.getCurrentIndex = options.getCurrentIndex || function() {
            return 0;
        };
        this.onExpire = options.onExpire;
        this.onQuestionExpire = options.onQuestionExpire || null;
        this.onState = options.onState || null;
        this.remaining = null;
        this.questionRemaining = null;
        this.expired = false;
        this.stopped = false;
        this.tickHandle = null;
        this.syncHandle = null;
        this.focusStart = null;
        this.focusQuestion = null;
        this.timings = {};
        this.questionExpireNotified = false;
        this.questionExpireIndex = null;
    }

    /**
     * Start or resume the quiz and begin counting down.
     *
     * @returns {Promise<object>} The quiz payload from get_verification_quiz.
     */
    QuizTimer.prototype.start = function() {
        var self = this;
        return Ajax.call([{
            methodname: 'plagiarism_dixeo_start_verification',
            args: {record_id: this.recordId} // eslint-disable-line camelcase
        }])[0].then(function(state) {
            self.adopt(state);
            if (self.expired) {
                return state;
            }
            return self.fetchQuiz();
        }).then(function(quiz) {
            if (self.expired) {
                return quiz;
            }
            self.restoreVisibleAnswers(quiz);
            self.trackQuestionFocus();
            self.tickHandle = setInterval(function() {
                self.tick();
            }, TICK_INTERVAL);
            self.syncHandle = setInterval(function() {
                self.sync();
            }, SYNC_INTERVAL);
            self.render();
            return quiz;
        });
    };

    /**
     * Stop counting and sending answers.
     */
    QuizTimer.prototype.stop = function() {
        this.stopped = true;
        clearInterval(this.tickHandle);
        clearInterval(this.syncHandle);
    };

    /**
     * Load the quiz, its saved answers, and the authoritative clocks.
     *
     * @returns {Promise<object>}
     */
    QuizTimer.prototype.fetchQuiz = function() {
        var self = this;
        return Ajax.call([{
            methodname: 'plagiarism_dixeo_get_verification_quiz',
            args: {record_id: this.recordId} // eslint-disable-line camelcase
        }])[0].then(function(quiz) {
            self.adopt(quiz);
            return quiz;
        });
    };

    /**
     * Put saved answers for the visible question back into the form.
     *
     * Other answers stay in memory on the pager; only the question currently in the DOM is filled.
     *
     * @param {object} quiz Payload from get_verification_quiz.
     */
    QuizTimer.prototype.restoreVisibleAnswers = function(quiz) {
        var answers;
        var questionId;
        var selector;
        var inputs;
        var i;
        var input;
        var value;
        try {
            answers = JSON.parse(quiz.answers || '{}');
        } catch (e) {
            return;
        }

        for (questionId in answers) {
            if (!Object.prototype.hasOwnProperty.call(answers, questionId)) {
                continue;
            }
            value = answers[questionId];
            selector = '[name="authorship_q[' + cssEscape(questionId) + ']"]';
            inputs = this.body.querySelectorAll(selector);
            for (i = 0; i < inputs.length; i++) {
                input = inputs[i];
                if (input.type === 'radio' || input.type === 'checkbox') {
                    input.checked = input.value === value;
                } else {
                    input.value = value;
                }
            }
        }
    };

    /**
     * Accumulate how long the student spends on each question.
     */
    QuizTimer.prototype.trackQuestionFocus = function() {
        var self = this;

        /**
         * Question id from an input name, if any.
         *
         * @param {HTMLElement} element Focused element.
         * @returns {string|null}
         */
        function questionOf(element) {
            var match = (element.name || '').match(/authorship_q\[([^\]]+)\]/);
            return match ? match[1] : null;
        }

        this.body.addEventListener('focusin', function(event) {
            var questionId = questionOf(event.target);
            if (questionId) {
                self.focusQuestion = questionId;
                self.focusStart = Date.now();
            }
        });

        this.body.addEventListener('focusout', function() {
            self.bankFocusTime();
        });
    };

    /**
     * Add the time spent on the question being left to its running total.
     */
    QuizTimer.prototype.bankFocusTime = function() {
        var spent;
        if (this.focusQuestion === null || this.focusStart === null) {
            return;
        }
        spent = Math.round((Date.now() - this.focusStart) / 1000);
        this.timings[this.focusQuestion] = (this.timings[this.focusQuestion] || 0) + spent;
        this.focusQuestion = null;
        this.focusStart = null;
    };

    /**
     * Take the server's view of the clock.
     *
     * @param {object} state Response from any of the verification endpoints.
     */
    QuizTimer.prototype.adopt = function(state) {
        var index;
        if (!state) {
            return;
        }
        this.remaining = state.remaining >= 0 ? state.remaining : null;
        this.questionRemaining = state.question_remaining >= 0 ? state.question_remaining : null;
        index = typeof state.current_index === 'number' ? state.current_index : this.getCurrentIndex();
        if (this.questionExpireIndex !== index) {
            this.questionExpireNotified = false;
            this.questionExpireIndex = index;
        }
        if (this.onState) {
            this.onState(state);
        }
        if (state.expired || this.remaining === 0) {
            this.expire();
            return;
        }
        this.maybeNotifyQuestionExpire();
    };

    /**
     * Count down one second locally between syncs.
     */
    QuizTimer.prototype.tick = function() {
        if (this.remaining !== null) {
            this.remaining = Math.max(0, this.remaining - 1);
        }
        if (this.questionRemaining !== null) {
            this.questionRemaining = Math.max(0, this.questionRemaining - 1);
        }
        this.render();
        if (this.remaining === 0) {
            this.sync();
            return;
        }
        this.maybeNotifyQuestionExpire();
    };

    /**
     * Tell the pager once when the current question's time has run out.
     */
    QuizTimer.prototype.maybeNotifyQuestionExpire = function() {
        if (this.questionRemaining !== 0 || this.questionExpireNotified || this.expired) {
            return;
        }
        this.questionExpireNotified = true;
        if (this.onQuestionExpire) {
            this.onQuestionExpire();
        }
    };

    /**
     * Send the current answers to the server and take back the authoritative time remaining.
     *
     * @returns {Promise<object|null>} The saved state, or null when the sync could not run.
     */
    QuizTimer.prototype.sync = function() {
        var self = this;
        if (this.stopped) {
            return Promise.resolve(null);
        }
        this.bankFocusTime();

        return Ajax.call([{
            methodname: 'plagiarism_dixeo_save_verification_progress',
            args: {
                record_id: this.recordId, // eslint-disable-line camelcase
                responses: JSON.stringify(this.collect()),
                timings: JSON.stringify(this.timings),
                current_index: this.getCurrentIndex() // eslint-disable-line camelcase
            }
        }])[0].then(function(state) {
            self.adopt(state);
            return state;
        }).catch(function() {
            // A failed sync is not fatal: the server clock still governs, and the next sync retries.
            return null;
        });
    };

    /**
     * Close the quiz once and hand over to the caller.
     */
    QuizTimer.prototype.expire = function() {
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
    };

    /**
     * Redraw the countdowns.
     *
     * @returns {Promise<void>}
     */
    QuizTimer.prototype.render = function() {
        var self = this;
        var pending = Promise.resolve();
        var key;
        var argument;

        if (this.display) {
            if (this.remaining === null) {
                this.display.textContent = '';
                this.display.classList.remove('text-danger');
            } else {
                key = this.expired ? 'quiz_expired' : 'quiz_time_remaining';
                argument = this.expired ? undefined : formatClock(this.remaining);
                pending = Str.get_string(key, 'plagiarism_dixeo', argument).then(function(text) {
                    self.display.textContent = text;
                    self.display.classList.toggle('text-danger', self.expired || self.remaining <= URGENT_THRESHOLD);
                    return true;
                });
            }
        }
        if (this.questionDisplay) {
            if (this.questionRemaining === null) {
                this.questionDisplay.textContent = '';
                this.questionDisplay.classList.remove('text-danger');
            } else {
                this.questionDisplay.textContent = formatClock(this.questionRemaining);
                this.questionDisplay.classList.toggle(
                    'text-danger',
                    this.questionRemaining === 0 || this.questionRemaining <= URGENT_THRESHOLD
                );
            }
        }
        return pending;
    };

    return QuizTimer;
});
