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

namespace core\external\check;

defined('MOODLE_INTERNAL') || die();

use core\check\external\get_result;
use core\check\result;
use externallib_advanced_testcase;
use required_capability_exception;
use context_system;

global $CFG;
require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Unit tests check API get_result webservice
 *
 * @package     core
 * @covers      \core\check\external\get_reuslt
 * @copyright   2023 Matthew Hilton <matthewhilton@catalyst-au.net>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_result_test extends externallib_advanced_testcase {

    /**
     * Provides values to execute_test
     * @return array
     */
    public static function execute_provider() : array {
        return [
            'get check result (default)' => [
                'reference' => 'core_passwordpolicy',
                'includedetails' => false,
                'includehtml' => false,
                'expectedreturn' => [
                    'status' => result::OK,
                    'summary' => get_string('check_passwordpolicy_ok', 'report_security'),
                    'details' => null,
                    'html' => null,
                ],
            ],
            'get check result (with details)' => [
                'reference' => 'core_passwordpolicy',
                'includedetails' => true,
                'includehtml' => false,
                'expectedreturn' => [
                    'status' => result::OK,
                    'summary' => get_string('check_passwordpolicy_ok', 'report_security'),
                    // Note the password policy check does not expose details.
                    // But this will still check that something was returned.
                    'details' => '',
                    'html' => null,
                ],
            ],
            'get check result (with html)' => [
                'reference' => 'core_passwordpolicy',
                'includedetails' => false,
                'includehtml' => true,
                'expectedreturn' => [
                    'status' => result::OK,
                    'summary' => get_string('check_passwordpolicy_ok', 'report_security'),
                    'details' => null,
                    'html' => 'badge-success',
                ],
            ],
            'get check result (with details and html)' => [
                'reference' => 'core_passwordpolicy',
                'includedetails' => true,
                'includehtml' => true,
                'expectedreturn' => [
                    'status' => result::OK,
                    'summary' => get_string('check_passwordpolicy_ok', 'report_security'),
                    // Note the password policy check does not expose details.
                    // But this will still check that something was returned.
                    'details' => '',
                    'html' => 'badge-success',
                ],
            ],
            'invalid check reference' => [
                'reference' => 'fake_notarealcheck',
                'includedetails' => true,
                'includehtml' => true,
                'expectedreturn' => [],
                'expectedexceptionmessage' => 'Check fake_notarealcheck does not exist',
            ],
        ];
    }

    /**
     * Tests the execute function
     *
     * @param string $reference Check class reference to pass to the function
     * @param bool $includedetails if details are included
     * @param bool $includehtml if html is included
     * @param array $expectedreturn an array of key value pairs. For each key, if the value is null it expects the
     * webservice to not return it. If it has a value, it checks that that value was inside what was returned from the webservice.
     * @param string $expectedexceptionmessage If not null the test will expect this exception message
     * @dataProvider execute_provider
     */
    public function test_execute(string $reference, bool $includedetails, bool $includehtml, array $expectedreturn,
        string $expectedexceptionmessage = '') {
        global $CFG;
        $this->resetAfterTest(true);

        // This makes the check we test (password policy) always return OK.
        $CFG->passwordpolicy = true;

        if (!empty($expectedexceptionmessage)) {
            $this->expectExceptionMessage($expectedexceptionmessage);
        }

        // Execute the ws function.
        $this->setAdminUser();
        $wsresult = (object) get_result::execute($reference, $includedetails, $includehtml);

        foreach ($expectedreturn as $key => $expectedvalue) {
            // If the expected result is null, ensure the return value was also null.
            if (is_null($expectedvalue)) {
                $this->assertTrue(empty($wsresult->$key));
            }

            // If the expected result is set, ensure it is contained in the return value.
            if (!is_null($expectedvalue)) {
                $this->assertTrue(!empty($wsresult->$key));
                $this->assertStringContainsString($expectedvalue, $wsresult->$key);
            }
        }
    }

    /**
     * Provides values to test_capability_check
     * @return array
     */
    public static function capability_check_provider(): array {
        return [
            'has permission' => [
                'permission' => CAP_ALLOW,
                'expectedexception' => null,
            ],
            'does not have permission' => [
                'permission' => CAP_PROHIBIT,
                'expectedexception' => required_capability_exception::class,
            ],
        ];
    }

    /**
     * Tests that capabilites are being checked correctly by the webservice.
     *
     * @param int $permission the permission level to assign the capability to the role for.
     * @param string|null $expectedexception Exception class expected, or null if none is expected.
     * @dataProvider capability_check_provider
     */
    public function test_capability_check($permission, $expectedexception) {
        $this->resetAfterTest(true);

        $user = $this->getDataGenerator()->create_user();
        $role = $this->getDataGenerator()->create_role();
        role_assign($role, $user->id, context_system::instance()->id);
        role_change_permission($role, context_system::instance(), 'moodle/site:getcheckresult', $permission);

        if (!empty($expectedexception)) {
            $this->expectException($expectedexception);
        }

        $this->setUser($user);
        get_result::execute('core_passwordpolicy', false, false);
    }
}
