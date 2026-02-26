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
 * Generic AI wrapper around local_edai: system prompt, optional data and files (user message), JSON validation.
 *
 * @package    plagiarism_dixeo
 * @author     Josemaria Bolanos <admin@mako.digital>
 * @copyright  2026 Dixeo.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo\ai;

defined('MOODLE_INTERNAL') || die();

/**
 * Wrapper for local_edai completion API: system prompt, optional user-message data (generic key-value blocks or text),
 * files, optional JSON schema validation.
 *
 * No assignment- or submission-specific logic. Uses global $DB, $USER when constructing.
 * send_request returns result (data/raw_content or error).
 */
class ai {

    /** Default model when not specified (must match a key in local_edai credits_manager model_factory). */
    public const DEFAULT_MODEL = 'gpt-5-mini-2025-08-07';

    /** Model for Assistants API (file upload + file_search). Must be valid for assistants; edai uses gpt-4.1. */
    public const ASSISTANT_MODEL = 'gpt-4.1';

    /** @var \local_edai\ai\openai\openai_api_interface */
    private $openai;

    /**
     * Build the wrapper (uses global $DB, $USER and local_edai ai_factory).
     *
     * @param string $label Label for the edai instance (e.g. "Dixeo").
     * @param array $context Optional context array for api_context.
     */
    public function __construct(string $label = 'Dixeo', array $context = []) {
        global $DB, $USER;
        $apicontext = new \local_edai\ai\api_context($DB, $USER, $context);
        $factory = new \local_edai\ai\ai_factory($label, $apicontext);
        $this->openai = $factory->get_openai_instance();
    }

    /**
     * Send a request: prompt (system message), optional data and files (user message).
     *
     * @param string $prompt The prompt (system message).
     * @param array $data Optional data: associative string values (labeled blocks), or key "text" only (plain body), or arbitrary array (JSON-encoded).
     * @param array $files Optional content parts (OpenAI format).
     * @param array|null $jsonstructure Optional expected JSON structure for validation.
     * @param string|null $model Optional model override.
     * @return result Success with data/raw_content, or failure with error and error_detail.
     */
    public function send_request(
        string $prompt,
        array $data = [],
        array $files = [],
        ?array $jsonstructure = null,
        ?string $model = null,
    ): result {
        $model = $model ?? self::DEFAULT_MODEL;

        try {
            $usercontent = self::build_user_content($data, $files);
            $messages = [];
            if (trim($prompt) !== '') {
                $messages[] = ['role' => 'system', 'content' => trim($prompt)];
            }
            $messages[] = ['role' => 'user', 'content' => $usercontent];
            $request = [
                'model'    => $model,
                'messages' => $messages,
            ];
            if ($jsonstructure !== null) {
                $request['response_format'] = ['type' => 'json_object'];
            }

            $response = $this->openai->completion_api()->create_completion($request);
        } catch (\Throwable $e) {
            return result::fail(
                \get_string('error_request_failed', 'plagiarism_dixeo'),
                $e->getMessage()
            );
        }

        return self::process_response($response, $jsonstructure);
    }

    /**
     * Send a request with uploaded files (Assistants API).
     *
     * @param string $prompt System instructions for the assistant.
     * @param string $usertext User message (e.g. submission block text).
     * @param array $storedfiles Array of \stored_file instances.
     * @param array|null $jsonstructure Optional expected JSON structure; if set, response is parsed and validated.
     * @param string|null $model Optional model override.
     * @return result Success with data/raw_content, or failure with error and error_detail.
     */
    public function send_request_with_files(
        string $prompt,
        string $usertext,
        array $storedfiles,
        ?array $jsonstructure = null,
        ?string $model = null,
    ): result {
        $model = $model ?? self::DEFAULT_MODEL;
        $temppaths = [];
        $uploadedfileids = [];
        $vectorid = null;
        $assistantid = null;
        $threadid = null;

        try {
            if (empty($storedfiles)) {
                return result::fail(\get_string('error_no_files_to_upload', 'plagiarism_dixeo'), 'storedfiles array is empty');
            }

            $curlfiles = [];
            foreach ($storedfiles as $file) {
                if (!($file instanceof \stored_file)) {
                    continue;
                }
                $path = $file->copy_content_to_temp('plagiarism_dixeo_assign');
                if ($path === false) {
                    return result::fail(\get_string('error_failed_copy_file', 'plagiarism_dixeo'), $file->get_filename());
                }
                $temppaths[] = $path;
                $curlfiles[] = new \CURLFile($path, $file->get_mimetype(), $file->get_filename());
            }
            if (empty($curlfiles)) {
                self::cleanup_temp_paths($temppaths);
                return result::fail(\get_string('error_no_valid_files', 'plagiarism_dixeo'), 'No stored_file instances could be converted');
            }

            foreach ($curlfiles as $curlfile) {
                $fileid = $this->openai->file_api()->create_file([
                    'file'     => $curlfile,
                    'purpose'  => 'assistants',
                ]);
                $uploadedfileids[] = $fileid;
            }

            $vectorid = $this->openai->vector_store_api()->create_vector_store(['file_ids' => $uploadedfileids]);

            self::wait_for_vector_store_ready($this->openai, $vectorid);

            $assistantid = $this->openai->assistant_api()->create_assistant([
                'model'        => self::ASSISTANT_MODEL,
                'name'         => 'Dixeo assignment assistant',
                'instructions' => $prompt,
                'tools'        => [['type' => 'file_search']],
            ]);

            $threadid = $this->openai->assistant_api()->create_thread([
                'messages' => [
                    ['role' => 'user', 'content' => $usertext],
                ],
                'tool_resources' => [
                    'file_search' => [
                        'vector_store_ids' => [$vectorid],
                    ],
                ],
            ]);

            $this->openai->assistant_api()->run_and_poll_assistant($threadid, $assistantid, [
                'tool_choice' => 'required',
            ]);

            $content = $this->openai->assistant_api()->get_last_message_content($threadid);
            $content = trim($content);

            if ($jsonstructure !== null) {
                $decoded = json_decode($content, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return result::fail(\get_string('error_response_not_json', 'plagiarism_dixeo'), json_last_error_msg());
                }
                if (!is_array($decoded)) {
                    return result::fail(\get_string('error_expected_json_object', 'plagiarism_dixeo'), 'Root value is not an object');
                }
                $validation = self::validate_structure($decoded, $jsonstructure, 'root');
                if ($validation !== null) {
                    return result::fail(\get_string('error_response_structure', 'plagiarism_dixeo'), $validation);
                }
                return result::ok($decoded);
            }

            return result::ok_raw($content);
        } catch (\Throwable $e) {
            return result::fail(\get_string('error_request_with_files_failed', 'plagiarism_dixeo'), $e->getMessage());
        } finally {
            if ($threadid !== null) {
                try {
                    $this->openai->assistant_api()->delete_thread($threadid);
                } catch (\Throwable $e) {
                    // Ignore cleanup errors.
                }
            }
            if ($assistantid !== null) {
                try {
                    $this->openai->assistant_api()->delete_assistant($assistantid);
                } catch (\Throwable $e) {
                    // Ignore.
                }
            }
            if ($vectorid !== null) {
                try {
                    $this->openai->vector_store_api()->delete_vector_store($vectorid);
                } catch (\Throwable $e) {
                    // Ignore.
                }
            }
            foreach ($uploadedfileids as $fileid) {
                try {
                    $this->openai->file_api()->delete_file($fileid);
                } catch (\Throwable $e) {
                    // Ignore.
                }
            }
            self::cleanup_temp_paths($temppaths);
        }
    }

    /**
     * Poll vector store until status is 'completed' and at least one file is indexed.
     *
     * @param \local_edai\ai\openai\openai_api_interface $openai
     * @param string $vectorid
     * @param int $maxwaitseconds Max seconds to wait (default 120).
     * @param int $pollintervalseconds Seconds between polls (default 2).
     * @throws \Exception If status is failed/expired, timeout, or completed with no indexed files.
     */
    private static function wait_for_vector_store_ready(
        $openai,
        string $vectorid,
        int $maxwaitseconds = 120,
        int $pollintervalseconds = 2,
    ): void {
        sleep(min(5, $pollintervalseconds));
        $deadline = time() + $maxwaitseconds;
        while (true) {
            $vs = $openai->vector_store_api()->get_vector_store($vectorid);
            $status = $vs['status'] ?? 'unknown';
            $counts = $vs['file_counts'] ?? [];
            $completed = (int) ($counts['completed'] ?? 0);

            if ($status === 'completed') {
                if ($completed > 0) {
                    return;
                }
                $msg = \get_string('assign_file_not_indexed', 'plagiarism_dixeo');
                throw new \Exception($msg);
            }
            if (in_array($status, ['failed', 'expired'], true)) {
                throw new \Exception("Vector store ended with status: {$status}. file_counts: " . json_encode($counts));
            }
            if (time() >= $deadline) {
                throw new \Exception("Vector store did not complete within {$maxwaitseconds} seconds (status: {$status}).");
            }
            sleep($pollintervalseconds);
        }
    }

    /**
     * Delete temporary files created for upload.
     *
     * @param array $paths List of paths to unlink.
     */
    private static function cleanup_temp_paths(array $paths): void {
        foreach ($paths as $path) {
            if (is_string($path) && file_exists($path)) {
                @unlink($path);
            }
        }
    }

    /**
     * Build user message content from data and files.
     *
     * @param array $data Optional key-value data (associative or ['text' => ...]).
     * @param array $files Optional OpenAI content parts (arrays with 'type' key).
     * @return string|array String when only text; array of content parts when files present.
     */
    private static function build_user_content(array $data, array $files) {
        $text = '';
        if (!empty($data)) {
            if (isset($data['text']) && count($data) === 1) {
                $text = (string) $data['text'];
            } elseif (self::is_assoc($data) && self::all_string_values($data)) {
                $blocks = [];
                foreach ($data as $key => $value) {
                    $label = is_string($key) ? strtoupper($key) : (string) $key;
                    $blocks[] = $label . ":\n" . (string) $value;
                }
                $text = implode("\n\n", $blocks);
            } else {
                $text = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            }
        }
        $parts = [['type' => 'text', 'text' => $text]];
        foreach ($files as $part) {
            if (is_array($part) && isset($part['type'])) {
                $parts[] = $part;
            }
        }
        if (count($parts) === 1) {
            return $parts[0]['text'];
        }
        return $parts;
    }

    /**
     * Process API response: extract content, decode JSON, validate structure.
     *
     * @param array $response Decoded create_completion response.
     * @param array|null $jsonstructure Optional schema for validation.
     * @return result
     */
    private static function process_response(array $response, ?array $jsonstructure): result {
        if (isset($response['error'])) {
            $info = $response['error'];
            $message = is_array($info) ? ($info['message'] ?? json_encode($info)) : (string) $info;
            return result::fail(\get_string('error_api_returned_error', 'plagiarism_dixeo'), $message);
        }

        if (empty($response['choices']) || !is_array($response['choices'])) {
            return result::fail(\get_string('error_invalid_api_response', 'plagiarism_dixeo'), 'Missing or empty "choices" in response');
        }

        $content = $response['choices'][0]['message']['content'] ?? null;
        if ($content === null || $content === '') {
            return result::fail(\get_string('error_empty_response_content', 'plagiarism_dixeo'), 'choices[0].message.content is empty');
        }

        if ($jsonstructure === null) {
            return result::ok_raw(trim($content));
        }

        $decoded = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return result::fail(
                \get_string('error_response_not_json', 'plagiarism_dixeo'),
                json_last_error_msg() . ' (content length: ' . strlen($content) . ')'
            );
        }

        if (!is_array($decoded)) {
            return result::fail(\get_string('error_expected_json_object', 'plagiarism_dixeo'), 'Root value is not an object');
        }

        $validation = self::validate_structure($decoded, $jsonstructure, 'root');
        if ($validation !== null) {
            return result::fail(\get_string('error_response_structure', 'plagiarism_dixeo'), $validation);
        }

        return result::ok($decoded);
    }

    /**
     * Validate value against a JSON Schema–like structure.
     *
     * @param mixed $value Decoded value to check.
     * @param array $schema Keys: type, properties?, required?, items?
     * @param string $path Path prefix for error messages.
     * @return string|null Error description or null if valid.
     */
    private static function validate_structure($value, array $schema, string $path): ?string {
        $type = $schema['type'] ?? null;
        if ($type === null) {
            return null;
        }

        switch ($type) {
            case 'object':
                if (!is_array($value) || !self::is_assoc($value)) {
                    return $path . ': expected object, got ' . self::type_of($value);
                }
                $required = $schema['required'] ?? [];
                foreach ($required as $key) {
                    if (!array_key_exists($key, $value)) {
                        return $path . ': missing required key "' . $key . '"';
                    }
                }
                $properties = $schema['properties'] ?? [];
                foreach ($value as $k => $v) {
                    if (!isset($properties[$k])) {
                        continue;
                    }
                    $sub = self::validate_structure($v, $properties[$k], $path . '.' . $k);
                    if ($sub !== null) {
                        return $sub;
                    }
                }
                return null;

            case 'array':
                if (!is_array($value)) {
                    return $path . ': expected array, got ' . self::type_of($value);
                }
                $items = $schema['items'] ?? null;
                if ($items !== null && is_array($items)) {
                    foreach ($value as $i => $item) {
                        $sub = self::validate_structure($item, $items, $path . '[' . $i . ']');
                        if ($sub !== null) {
                            return $sub;
                        }
                    }
                }
                return null;

            case 'string':
                if (!is_string($value)) {
                    return $path . ': expected string, got ' . self::type_of($value);
                }
                return null;

            case 'number':
            case 'integer':
                if (!is_numeric($value)) {
                    return $path . ': expected number, got ' . self::type_of($value);
                }
                if ($type === 'integer' && (int) $value != $value) {
                    return $path . ': expected integer, got ' . self::type_of($value);
                }
                return null;

            case 'boolean':
                if (!is_bool($value)) {
                    return $path . ': expected boolean, got ' . self::type_of($value);
                }
                return null;

            case 'null':
                if ($value !== null) {
                    return $path . ': expected null, got ' . self::type_of($value);
                }
                return null;

            default:
                return null;
        }
    }

    private static function is_assoc(array $a): bool {
        if ($a === []) {
            return false;
        }
        return array_keys($a) !== range(0, count($a) - 1);
    }

    private static function all_string_values(array $a): bool {
        foreach ($a as $v) {
            if (!is_scalar($v) && $v !== null) {
                return false;
            }
        }
        return true;
    }

    private static function type_of($v): string {
        if (is_object($v)) {
            return 'object(' . get_class($v) . ')';
        }
        return gettype($v);
    }
}
