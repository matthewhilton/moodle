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

namespace core;

use core\check\result;
use core\check\security\passwordpolicy;

/**
 * Example unit tests for check API
 *
 * @package    core
 * @category   check
 * @copyright  2020 Brendan Heywood <brendan@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class check_test extends \advanced_testcase {

    /**
     * A simple example showing how a check and result object works
     *
     * Conceptually a check is analgous to a unit test except at runtime
     * instead of build time so many checks in real life such as testing
     * an API is connecting aren't viable to unit test.
     */
    public function test_passwordpolicy() {
        global $CFG;
        $prior = $CFG->passwordpolicy;

        $check = new passwordpolicy();

        $CFG->passwordpolicy = false;
        $result = $check->get_result();
        $this->assertEquals($result->get_status(), result::WARNING);

        $CFG->passwordpolicy = true;
        $result = $check->get_result();
        $this->assertEquals($result->get_status(), result::OK);

        $CFG->passwordpolicy = $prior;
    }

    /**
     * Provides values to test_get_check
     *
     * @return array
     */
    public static function get_check_provider(): array {
        return [
            'check that exists' => [
                'reference' => (new \core\check\environment\antivirus())->get_ref(),
                'exists' => true,
            ],
            'check that does not exist' => [
                'reference' => 'test_thisisnotacheck',
                'exists' => false,
            ],
        ];
    }

    /**
     * Tests get_check function
     *
     * @param string $reference
     * @param bool $shouldexist
     * @dataProvider get_check_provider
     * @covers \core\check\manager::get_check
     */
    public function test_get_check($reference, $shouldexist) {
        $check = \core\check\manager::get_check($reference);

        if (!$shouldexist) {
            $this->assertNull($check);
        }

        if ($shouldexist) {
            $this->assertNotNull($check);
            $this->assertEquals($reference, $check->get_ref());
        }
    }
}

