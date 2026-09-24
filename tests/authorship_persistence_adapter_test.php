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
 * Tests for authorship_persistence_adapter.
 *
 * @package    plagiarism_dixeo
 * @category   test
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo;


/**
 * CRUD tests for the assign UX persistence adapter.
 *
 * @covers \plagiarism_dixeo\authorship\authorship_persistence_adapter
 */
final class authorship_persistence_adapter_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
        $this->setAdminUser();
    }

    public function test_create_get_and_replace_by_context(): void {
        $user = $this->getDataGenerator()->create_user();
        $adapter = new \plagiarism_dixeo\authorship\authorship_persistence_adapter();

        $quiz = [
            'version' => 1,
            'questions' => [
                ['id' => 'q0', 'type' => 'true_false', 'text' => 'Claim?', 'correct' => 1],
            ],
            'meta' => ['generated' => true],
        ];

        $created = $adapter->create_authorship_record(
            (int) $user->id,
            'mod_assign',
            'submission',
            42,
            55.5,
            $quiz
        );

        $this->assertNotNull($created);
        $this->assertGreaterThan(0, (int) $created->id);
        $this->assertEquals(55.5, (float) $created->initial_confidence);
        $this->assertNull($created->final_confidence);
        $decoded = json_decode($created->quiz_data, true);
        $this->assertSame('q0', $decoded['questions'][0]['id']);

        $byid = $adapter->get_authorship_record((int) $created->id);
        $this->assertNotNull($byid);
        $this->assertEquals($created->id, $byid->id);

        $byctx = $adapter->get_authorship_by_context((int) $user->id, 'mod_assign', 'submission', 42);
        $this->assertNotNull($byctx);
        $this->assertEquals($created->id, $byctx->id);

        $replaced = $adapter->create_authorship_record(
            (int) $user->id,
            'mod_assign',
            'submission',
            42,
            70.0,
            null
        );
        $this->assertNotNull($replaced);
        $this->assertNotEquals($created->id, $replaced->id);
        $this->assertNull($adapter->get_authorship_record((int) $created->id));
        $this->assertEquals(70.0, (float) $replaced->initial_confidence);
        $this->assertNull($replaced->quiz_data);
    }

    public function test_save_quiz_result(): void {
        $user = $this->getDataGenerator()->create_user();
        $adapter = new \plagiarism_dixeo\authorship\authorship_persistence_adapter();

        $created = $adapter->create_authorship_record(
            (int) $user->id,
            'mod_assign',
            'submission',
            7,
            40.0,
            ['version' => 1, 'questions' => []]
        );
        $this->assertNotNull($created);

        $updated = $adapter->save_quiz_result(
            (int) $created->id,
            ['q0' => '1'],
            82.25,
            75.0
        );

        $this->assertNotNull($updated);
        $this->assertEquals(82.25, (float) $updated->final_confidence);
        $this->assertEquals(75.0, (float) $updated->quiz_grade);
        $responses = json_decode($updated->quiz_responses, true);
        $this->assertSame(['q0' => '1'], $responses);

        $reloaded = $adapter->get_authorship_record((int) $created->id);
        $this->assertEquals(82.25, (float) $reloaded->final_confidence);
    }

    public function test_get_returns_null_for_missing(): void {
        $adapter = new \plagiarism_dixeo\authorship\authorship_persistence_adapter();

        $this->assertNull($adapter->get_authorship_record(0));
        $this->assertNull($adapter->get_authorship_record(999999999));
        $this->assertNull($adapter->get_authorship_by_context(1, 'mod_assign', 'submission', 0));
        $this->assertNull($adapter->save_quiz_result(999999999, [], 50.0, null));
    }
}
