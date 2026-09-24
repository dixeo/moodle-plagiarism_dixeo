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

/**
 * Privacy provider tests for plagiarism_dixeo.
 *
 * @package    plagiarism_dixeo
 * @category   test
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use plagiarism_dixeo\privacy\provider;


/**
 * Privacy API tests for authorship records.
 *
 * @covers \plagiarism_dixeo\privacy\provider
 */
final class privacy_provider_test extends \core_privacy\tests\provider_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Insert an authorship row for a user.
     *
     * @param int $userid User id.
     * @param int $itemid Item id.
     * @return int Record id.
     */
    private function insert_authorship(int $userid, int $itemid = 1): int {
        global $DB;

        $now = time();
        return (int) $DB->insert_record('plagiarism_dixeo_authorship', (object) [
            'component' => 'mod_assign',
            'area' => 'submission',
            'itemid' => $itemid,
            'initial_confidence' => 72.5,
            'quiz_data' => json_encode([['id' => 'q1', 'type' => 'open', 'prompt' => 'Why?']]),
            'quiz_responses' => json_encode(['q1' => 'Because']),
            'quiz_grade' => 80.0,
            'final_confidence' => 65.0,
            'userid' => $userid,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
    }

    public function test_get_metadata(): void {
        $collection = new collection('plagiarism_dixeo');
        $items = provider::get_metadata($collection)->get_collection();
        $names = array_map(static fn($item) => $item->get_name(), $items);
        $this->assertContains(provider::TABLE, $names);
    }

    public function test_export_and_delete_for_user(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->insert_authorship((int) $user->id, 10);
        $this->insert_authorship((int) $other->id, 11);

        $contextlist = provider::get_contexts_for_userid((int) $user->id);
        $this->assertCount(1, $contextlist->get_contextids());

        $usercontext = \context_user::instance((int) $user->id);
        $this->assertEquals([$usercontext->id], $contextlist->get_contextids());

        $approved = new approved_contextlist($user, 'plagiarism_dixeo', [$usercontext->id]);
        provider::export_user_data($approved);
        $writer = writer::with_context($usercontext);
        $this->assertTrue($writer->has_any_data());

        provider::delete_data_for_user($approved);
        $this->assertFalse($DB->record_exists(provider::TABLE, ['userid' => $user->id]));
        $this->assertTrue($DB->record_exists(provider::TABLE, ['userid' => $other->id]));
    }

    public function test_get_users_in_context_and_delete_users(): void {
        global $DB;

        $user = $this->getDataGenerator()->create_user();
        $this->insert_authorship((int) $user->id);

        $usercontext = \context_user::instance((int) $user->id);
        $userlist = new userlist($usercontext, 'plagiarism_dixeo');
        provider::get_users_in_context($userlist);
        $this->assertEquals([(int) $user->id], $userlist->get_userids());

        $approved = new approved_userlist($usercontext, 'plagiarism_dixeo', [(int) $user->id]);
        provider::delete_data_for_users($approved);
        $this->assertFalse($DB->record_exists(provider::TABLE, ['userid' => $user->id]));
    }
}
