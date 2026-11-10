<?php
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

namespace plagiarism_dixeo\tests;

/**
 * Database row lock factory that runs a callback just before a given lock is taken.
 *
 * Simulates another session changing a record between a request's first read and the moment
 * it acquires its lock, so tests can check that decisions are made on a copy read under the lock.
 *
 * @package    plagiarism_dixeo
 * @category   test
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class race_lock_factory extends \core\lock\db_record_lock_factory {
    /** @var string|null Resource whose next lock runs the callback. */
    public static $resource = null;

    /** @var callable|null Runs once, before the next lock on the resource is taken. */
    public static $beforenextlock = null;

    /**
     * Set the change to make just before the next lock on a resource is taken.
     *
     * @param string|null $resource Resource name, or null to clear.
     * @param callable|null $callback Change to make.
     */
    public static function before_lock(?string $resource, ?callable $callback): void {
        self::$resource = $resource;
        self::$beforenextlock = $callback;
    }

    /**
     * Run the pending callback when the resource matches, then take the lock.
     *
     * @param string $resource Resource name.
     * @param int $timeout Seconds to wait.
     * @param int $maxlifetime Seconds before the lock expires.
     * @return \core\lock\lock|false
     */
    public function get_lock($resource, $timeout, $maxlifetime = 86400) {
        if ($resource === self::$resource && self::$beforenextlock !== null) {
            $callback = self::$beforenextlock;
            self::before_lock(null, null);
            $callback();
        }
        return parent::get_lock($resource, $timeout, $maxlifetime);
    }
}
