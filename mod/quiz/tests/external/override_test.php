<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_quiz\external;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/../../../../webservice/tests/helpers.php');

use externallib_advanced_testcase;
use mod_quiz\quiz_override;

/**
 * Tests for override webservices
 *
 * @package   mod_quiz
 * @copyright 2024 Matthew Hilton <matthewhilton@catalyst-au.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \mod_quiz\external\get_overrides
 * @covers \mod_quiz\external\upsert_overrides
 * @covers \mod_quiz\external\delete_overrides
 */
class override_test extends externallib_advanced_testcase {

    /** @var object test quiz */
    private $quiz;

    /** @var quiz_override test override to update/delete **/
    private $override;

    /** @var object test user **/
    private $user;

    /**
     * Sets up tests
     */
    public function setUp(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);

        // Create a single test override for a user.
        $this->user = $this->getDataGenerator()->create_user();
        $this->override = new quiz_override(0, (object) ['userid' => $this->user->id, 'quiz' => $this->quiz->id,
            'timeopen' => 12345, ]);
        $this->override->save();
        $this->setAdminUser();
    }

    /**
     * Tests get_overrides
     */
    public function test_get_overrides(): void {
        // Get overrides for the test quiz, and one that does not exist.
        $data = [
            'quizzes' => [
                'id' => $this->quiz->id,
            ],
            [
                'id' => -1,
            ],
        ];

        $result = get_overrides::execute($data);

        // First quiz is real, so has data and no error.
        $this->assertNotEmpty($result[0]['data']);
        $this->assertEmpty($result[0]['error']);

        // Second quiz is not real, and so has no data and has an error.
        $this->assertEmpty($result[1]['data']);
        $this->assertNotEmpty($result[1]['error']);
    }

    /**
     * Tests upsert_overrides
     */
    public function test_upsert_overrides(): void {
        // Make a new user to insert a new override for.
        $user2 = $this->getDataGenerator()->create_user();

        // First is a good insert,
        // second is a bad insert
        // third is a good update
        // fourth is a bad update.
        $data = [
            'overrides' => [
                'userid' => $user2->id,
                'quizid' => $this->quiz->id,
                'timeopen' => 9999,
            ],
            [
                'userid' => $this->user->id,
                'quizid' => $this->quiz->id,
                'timeopen' => -1,
            ],
            [
                'id' => $this->override->get('id'),
                'userid' => $this->user->id,
                'quizid' => $this->quiz->id,
                'timeopen' => 55,
            ],
            [
                'id' => $this->override->get('id'),
                'userid' => $this->user->id,
                'quizid' => $this->quiz->id,
                'timeopen' => 10,
                'timeclose' => 5,
            ],
        ];

        $result = upsert_overrides::execute($data);

        // First insert is good, so expect an id.
        $this->assertNotEmpty($result[0]['id']);
        $this->assertEmpty($result[0]['error']);

        // Second insert is bad, so expect an error.
        $this->assertEmpty($result[1]['id']);
        $this->assertNotEmpty($result[1]['error']);

        // Third update is good, so expect an id.
        $this->assertNotEmpty($result[2]['id']);
        $this->assertEmpty($result[2]['error']);

        // Fourth update is bad, so expect an error.
        $this->assertEmpty($result[3]['id']);
        $this->assertNotEmpty($result[3]['error']);
    }

    /**
     * Tests delete_overrides
     */
    public function test_delete_overrides(): void {
        $data = [
            'overrides' => [
                'id' => $this->override->get('id'),
            ],
            [
                'id' => -1,
            ],
        ];

        $result = delete_overrides::execute($data);

        // First delete is good.
        $this->assertNotEmpty($result[0]['id']);
        $this->assertEmpty($result[0]['error']);

        // Second delete is expected to error.
        $this->assertNotEmpty($result[1]['id']);
        $this->assertNotEmpty($result[1]['error']);
    }
}
