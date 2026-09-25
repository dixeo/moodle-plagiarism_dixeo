/**
 * Poll assign UX jobs via plagiarism_dixeo_get_assign_job_status.
 *
 * @module     plagiarism_dixeo/assign_job_poller
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/ajax', 'core/str'], function(Ajax, Str) {

    var INITIAL_DELAY_MS = 3000;
    var INTERVAL_MS = 2000;
    var TIMEOUT_MS = 180000;

    /**
     * Resolve after a delay.
     *
     * @param {number} ms Milliseconds.
     * @returns {Promise}
     */
    function delay(ms) {
        return new Promise(function(resolve) {
            setTimeout(resolve, ms);
        });
    }

    /**
     * Poll until the job is completed or failed.
     *
     * @param {string} jobid Remote job id.
     * @param {number} courseid Course id.
     * @param {number} cmid Assign course module id.
     * @param {string} flow review|grade.
     * @returns {Promise<Object>} Final status payload.
     */
    function poll(jobid, courseid, cmid, flow) {
        var started = Date.now();
        var timedOut = false;

        /**
         * Reject with the client-facing timeout string.
         *
         * @returns {Promise}
         */
        function timeoutError() {
            return Str.get_string('job_poll_timeout', 'plagiarism_dixeo').then(function(msg) {
                throw new Error(msg);
            });
        }

        /**
         * One poll tick.
         *
         * @returns {Promise<Object>}
         */
        function tick() {
            if (timedOut || Date.now() - started > TIMEOUT_MS) {
                return timeoutError();
            }

            return Ajax.call([{
                methodname: 'plagiarism_dixeo_get_assign_job_status',
                args: {
                    jobid: jobid,
                    courseid: courseid,
                    cmid: cmid,
                    flow: flow
                }
            }])[0].then(function(data) {
                if (!data) {
                    throw new Error('empty');
                }
                if (data.status === 'completed' || data.status === 'failed' || data.status === 'complete') {
                    return data;
                }
                return INTERVAL_MS;
            }).then(function(resultOrDelay) {
                if (typeof resultOrDelay === 'number') {
                    // Recursive poll after delay; nesting is required for the loop.
                    // eslint-disable-next-line promise/no-nesting
                    return delay(resultOrDelay).then(tick);
                }
                return resultOrDelay;
            });
        }

        var work = delay(INITIAL_DELAY_MS).then(tick);
        var limit = delay(TIMEOUT_MS).then(function() {
            timedOut = true;
            return timeoutError();
        });
        return Promise.race([work, limit]);
    }

    return {
        poll: poll
    };
});
