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
 * Tests for authorship config and quiz grade helpers.
 *
 * @package    plagiarism_dixeo
 * @category   test
 * @copyright  2026 Dixeo (contact@dixeo.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace plagiarism_dixeo;

use plagiarism_dixeo\authorship\author_storage;
use plagiarism_dixeo\authorship\authorship;


/**
 * Unit coverage for authorship helpers (no AI / edai path).
 *
 * @covers \plagiarism_dixeo\authorship\authorship
 */
final class authorship_helpers_test extends \advanced_testcase {
    public function setUp(): void {
        parent::setUp();
        $this->resetAfterTest(true);
    }

    public function test_is_enabled_respects_config(): void {
        $this->assertFalse(authorship::is_enabled());

        set_config('authorship_enabled', 0, 'plagiarism_dixeo');
        $this->assertFalse(authorship::is_enabled());

        set_config('authorship_enabled', 1, 'plagiarism_dixeo');
        $this->assertTrue(authorship::is_enabled());
    }

    /**
     * Score and threshold pairs with the expected quiz requirement.
     *
     * @return array<string, array{0: float, 1: float, 2: bool}>
     */
    public static function quiz_required_provider(): array {
        return [
            'below the threshold' => [50.0, 75.0, true],
            'at the threshold' => [75.0, 75.0, false],
            'above the threshold' => [90.0, 75.0, false],
            'threshold 0, score 0' => [0.0, 0.0, false],
            'threshold 0, score 100' => [100.0, 0.0, false],
            'threshold 100, score 0' => [0.0, 100.0, true],
            'threshold 100, score 99.9' => [99.9, 100.0, true],
            'threshold 100, score 100' => [100.0, 100.0, false],
        ];
    }

    /**
     * A quiz is required exactly when the score is below the threshold.
     *
     * @dataProvider quiz_required_provider
     * @param float $score Confidence score.
     * @param float $threshold Assignment threshold.
     * @param bool $expected Whether a quiz is required.
     */
    public function test_is_quiz_required_compares_the_score_with_the_threshold(
        float $score,
        float $threshold,
        bool $expected
    ): void {
        $this->assertSame($expected, authorship::is_quiz_required($score, $threshold));
    }

    public function test_compute_quiz_grade_objective_questions_only(): void {
        $record = (object) [
            'quiz_data' => json_encode([
                'questions' => [
                    ['id' => 'q1', 'type' => authorship::QUESTION_TYPE_TRUE_FALSE, 'correct' => 1],
                    ['id' => 'q2', 'type' => authorship::QUESTION_TYPE_MULTIPLE_CHOICE, 'correct' => 'b'],
                    ['id' => 'q3', 'type' => authorship::QUESTION_TYPE_OPEN],
                ],
            ]),
        ];

        $grade = authorship::compute_quiz_grade($record, [
            'q1' => '1',
            'q2' => 'b',
            'q3' => 'anything',
        ]);
        $this->assertSame(100.0, $grade);

        $gradepartial = authorship::compute_quiz_grade($record, [
            'q1' => '0',
            'q2' => 'b',
        ]);
        $this->assertSame(50.0, $gradepartial);
    }

    public function test_a_quiz_in_progress_is_resumable(): void {
        $open = (object) [
            'status' => author_storage::STATUS_VERIFICATION_IN_PROGRESS,
            'quiz_data' => json_encode([
                'questions' => [['id' => 'q1', 'type' => authorship::QUESTION_TYPE_OPEN, 'text' => 'Why?']],
            ]),
        ];
        $this->assertTrue(authorship::is_resumable_record($open));

        $required = clone $open;
        $required->status = author_storage::STATUS_VERIFICATION_REQUIRED;
        $this->assertTrue(authorship::is_resumable_record($required));
    }

    public function test_a_finished_or_empty_quiz_is_not_resumable(): void {
        $this->assertFalse(authorship::is_resumable_record(null));
        $this->assertFalse(authorship::is_resumable_record((object) [
            'status' => author_storage::STATUS_COMPLETE,
            'quiz_data' => json_encode(['questions' => [['id' => 'q1']]]),
        ]));
        $this->assertFalse(authorship::is_resumable_record((object) [
            'status' => author_storage::STATUS_VERIFICATION_IN_PROGRESS,
            'quiz_data' => json_encode(['questions' => []]),
        ]));
    }
}
