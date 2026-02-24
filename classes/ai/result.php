<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify it
// under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful, but
// WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * AI request result value object.
 *
 * @package    plagiarism_dixeo
 * @author     Josemaria Bolanos <admin@mako.digital>
 * @copyright  2026 Dixeo.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo\ai;

defined('MOODLE_INTERNAL') || die();

/**
 * Immutable result of an AI request (success or failure with details).
 *
 * Returned by ai::send_request(). Use success, data/raw_content, error, error_detail, or get_failure_message().
 */
class result {

    /** @var bool Whether the request succeeded and response met expectations. */
    public $success;

    /** @var array|null Decoded JSON data when success and JSON structure was expected/validated. */
    public $data;

    /** @var string|null Raw message content when success but no JSON structure validation. */
    public $raw_content;

    /** @var string|null Short error reason when !success. */
    public $error;

    /** @var string|null Detailed error message (e.g. validation path, API message). */
    public $error_detail;

    /**
     * Create a successful result with validated/decoded data.
     *
     * @param array $data
     * @return self
     */
    public static function ok(array $data): self {
        return new self(true, $data, null, null, null);
    }

    /**
     * Create a successful result with raw text content (no JSON).
     *
     * @param string $rawcontent Raw message content from the API.
     * @return self
     */
    public static function ok_raw(string $rawcontent): self {
        return new self(true, null, $rawcontent, null, null);
    }

    /**
     * Create a failed result.
     *
     * @param string $error Short reason (e.g. "Invalid response structure").
     * @param string|null $errordetail Optional details (e.g. validation path, API message).
     * @return self
     */
    public static function fail(string $error, ?string $errordetail = null): self {
        return new self(false, null, null, $error, $errordetail);
    }

    /**
     * Constructor. Prefer static factories ok(), ok_raw(), fail().
     *
     * @param bool $success
     * @param array|null $data
     * @param string|null $rawcontent
     * @param string|null $error
     * @param string|null $errordetail
     */
    public function __construct(
        bool $success,
        ?array $data = null,
        ?string $rawcontent = null,
        ?string $error = null,
        ?string $errordetail = null,
    ) {
        $this->success = $success;
        $this->data = $data;
        $this->raw_content = $rawcontent;
        $this->error = $error;
        $this->error_detail = $errordetail;
    }

    /**
     * Get a single string describing the failure (error + detail).
     *
     * @return string Empty when success.
     */
    public function get_failure_message(): string {
        if ($this->success) {
            return '';
        }
        $msg = $this->error ?? 'Unknown error';
        if ($this->error_detail !== null && $this->error_detail !== '') {
            $msg .= ': ' . $this->error_detail;
        }
        return $msg;
    }
}
