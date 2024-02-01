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

namespace mod_quiz;

use advanced_testcase;
use mod_quiz\event\group_override_created;
use mod_quiz\event\group_override_updated;
use mod_quiz\event\user_override_created;
use mod_quiz\event\user_override_updated;
use mod_quiz\override_manager;
use mod_quiz\quiz_settings;
use context_system;

/**
 * Test for override_manager class
 *
 * @package   mod_quiz
 * @copyright 2024 Matthew Hilton <matthewhilton@catalyst-au.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \mod_quiz\override_manager
 */
class override_manager_test extends advanced_testcase {

    /** @var quiz_settings $quizobj **/
    private $quizobj;

    /** @var object $course **/
    private $course;

    /** @var array Default quiz settings **/
    private const TEST_QUIZ_SETTINGS = [
        'attempts' => 5,
        'timeopen' => 100000000,
        'timeclose' => 10000001,
        'timelimit' => 10,
    ];

    /**
     * Sets up the tests
     */
    public function setUp(): void {
        $this->resetAfterTest();

        $this->course = $this->getDataGenerator()->create_course(['groupmode' => SEPARATEGROUPS]);
        $quizparams = array_merge(self::TEST_QUIZ_SETTINGS, ['course' => $this->course->id]);
        $quiz = $this->getDataGenerator()->create_module('quiz', $quizparams);
        $this->quizobj = quiz_settings::create($quiz->id);
    }

    /**
     * Utility function that replaces the placeholders in the given data.
     * @param array $data
     * @param array $placeholdervalues
     * @return array the $data with the placeholders replaced
     */
    private function replace_placeholders(array $data, array $placeholdervalues) {
        foreach ($data as $key => $value) {
            $replacement = $placeholdervalues[$value] ?? null;

            if (!empty($replacement)) {
                $data[$key] = $replacement;
            }
        }

        return $data;
    }

    /**
     * Provides values to test_upsert_override
     * @return array
     */
    public static function upsert_override_provider(): array {
        return [
            'create user override - no existing data' => [
                'existingdata' => [],
                'formdata' => [
                    'userid' => ':userid',
                    'groupid' => null,
                    'timeopen' => 50,
                    'timeclose' => 51,
                    'timelimit' => 1,
                    'attempts' => 999,
                    'password' => 'test',
                ],
                'expectedrecordscreated' => 1,
                'expectedevent' => user_override_created::class,
            ],
            'create user override - no calendar events should be created' => [
                'existingdata' => [],
                'formdata' => [
                    'userid' => ':userid',
                    'groupid' => null,
                    'timeopen' => null,
                    'timeclose' => null,
                    'timelimit' => 1,
                    'attempts' => 999,
                    'password' => 'test',
                ],
                'expectedrecordscreated' => 1,
                'expectedevent' => user_override_created::class,
            ],
            'create user override - only timeopen' => [
                'existingdata' => [],
                'formdata' => [
                    'userid' => ':userid',
                    'groupid' => null,
                    'timeopen' => 50,
                    'timeclose' => null,
                    'timelimit' => null,
                    'attempts' => null,
                    'password' => 'test',
                ],
                'expectedrecordscreated' => 1,
                'expectedevent' => user_override_created::class,
            ],
            'create group override - no existing data' => [
                'existingdata' => [],
                'formdata' => [
                    'userid' => null,
                    'groupid' => ':groupid',
                    'timeopen' => 50,
                    'timeclose' => 51,
                    'timelimit' => 1,
                    'attempts' => 999,
                    'password' => 'test',
                ],
                'expectedrecordscreated' => 1,
                'expectedevent' => group_override_created::class,
            ],
            'create group override - no calendar events should be created' => [
                'existingdata' => [],
                'formdata' => [
                    'userid' => null,
                    'groupid' => ':groupid',
                    'timeopen' => null,
                    'timeclose' => null,
                    'timelimit' => 1,
                    'attempts' => 999,
                    'password' => 'test',
                ],
                'expectedrecordscreated' => 1,
                'expectedevent' => group_override_created::class,
            ],
            'create group override - only timeopen' => [
                'existingdata' => [],
                'formdata' => [
                    'userid' => null,
                    'groupid' => ':groupid',
                    'timeopen' => 50,
                    'timeclose' => null,
                    'timelimit' => null,
                    'attempts' => null,
                    'password' => null,
                ],
                'expectedrecordscreated' => 1,
                'expectedevent' => group_override_created::class,
            ],
            'update user override - updating existing data' => [
                'existingdata' => [
                    'userid' => ':userid',
                    'groupid' => null,
                    'timeopen' => 50,
                    'timeclose' => 51,
                    'timelimit' => 2,
                    'attempts' => 2,
                    'password' => 'test2',
                ],
                'formdata' => [
                    'id' => ':existingid',
                    'userid' => ':userid',
                    'groupid' => null,
                    'timeopen' => 52,
                    'timeclose' => 53,
                    'timelimit' => 1,
                    'attempts' => 999,
                    'password' => 'test',
                ],
                'expectedrecordscreated' => 0,
                'expectedevent' => user_override_updated::class,
            ],
            'update group override - updating existing data' => [
                'existingdata' => [
                    'userid' => null,
                    'groupid' => ':groupid',
                    'timeopen' => 50,
                    'timeclose' => 51,
                    'timelimit' => 2,
                    'attempts' => 2,
                    'password' => 'test2',
                ],
                'formdata' => [
                    'id' => ':existingid',
                    'userid' => null,
                    'groupid' => ':groupid',
                    'timeopen' => 52,
                    'timeclose' => 53,
                    'timelimit' => 1,
                    'attempts' => 999,
                    'password' => 'test',
                ],
                'expectedrecordscreated' => 0,
                'expectedevent' => group_override_updated::class,
            ],
            'update override, but new data has no user or group set' => [
                'existingdata' => [
                    'userid' => ':userid',
                    'groupid' => null,
                    'timeopen' => 50,
                    'timeclose' => 51,
                    'timelimit' => 2,
                    'attempts' => 2,
                    'password' => 'test2',
                ],
                'formdata' => [
                    'id' => ':existingid',
                    'userid' => null,
                    'groupid' => null,
                    'timeopen' => 52,
                    'timeclose' => 53,
                    'timelimit' => 1,
                    'attempts' => 999,
                    'password' => 'test',
                ],
                'expectedrecordscreated' => 0,
                'expectedevent' => '',
                'expectedexception' => 'Either userid or groupid must be set',
            ],
            'saving both group and userid - expected invalid' => [
                'existingdata' => [],
                'formdata' => [
                    'userid' => ':userid',
                    'groupid' => ':groupid',
                    'timeopen' => 50,
                    'timeclose' => 51,
                    'timelimit' => 1,
                    'attempts' => 999,
                    'password' => 'test',
                ],
                'expectedrecordscreated' => 0,
                'expectedevent' => '',
                'expectedexception' => 'Userid and groupid were both set, but only one can be set at once.',
            ],
            'saving neither group nor userid - expected invalid' => [
                'existingdata' => [],
                'formdata' => [
                    'userid' => null,
                    'groupid' => null,
                    'timeopen' => 50,
                    'timeclose' => 51,
                    'timelimit' => 1,
                    'attempts' => 999,
                    'password' => 'test',
                ],
                'expectedrecordscreated' => 0,
                'expectedevent' => '',
                'expectedexception' => 'Either userid or groupid must be set',
            ],
            'form data is empty' => [
                'existingdata' => [],
                'formdata' => [],
                'expectedcountchange' => 0,
                'expectedevent' => '',
                'expectedexception' => 'No settings were changed',
            ],
            'form data is all nulls' => [
                'existingdata' => [],
                'formdata' => [
                    'userid' => null,
                    'groupid' => null,
                    'timeopen' => null,
                    'timeclose' => null,
                    'timelimit' => null,
                    'attempts' => null,
                    'password' => null,
                ],
                'expectedrecordscreated' => 0,
                'expectedevent' => '',
                'expectedexception' => 'No settings were changed',
            ],
            'user is given, but no settings change' => [
                'existingdata' => [],
                'formdata' => [
                    'userid' => ':userid',
                    'groupid' => null,
                    'timeopen' => null,
                    'timeclose' => null,
                    'timelimit' => null,
                    'attempts' => null,
                    'password' => null,
                ],
                'expectedrecordscreated' => 0,
                'expectedevent' => '',
                'expectedexception' => 'No settings were changed',
            ],
            'all settings submitted are the same as what is in the quiz (invalid - no change)' => [
                'existingdata' => [],
                'formdata' => [
                    'userid' => ':userid',
                    'groupid' => null,
                    'timeopen' => self::TEST_QUIZ_SETTINGS['timeopen'],
                    'timeclose' => self::TEST_QUIZ_SETTINGS['timeclose'],
                    'attempts' => self::TEST_QUIZ_SETTINGS['attempts'],
                    'timelimit' => self::TEST_QUIZ_SETTINGS['timelimit'],
                    'password' => null,
                ],
                'expectedrecordscreated' => 0,
                'expectedevent' => '',
                'expectedexception' => 'No settings were changed',
            ],
            'some settings submitted are the same as what is in the quiz (valid)' => [
                'existingdata' => [],
                'formdata' => [
                    'userid' => ':userid',
                    'groupid' => null,
                    // Make these the same, they should be ignored.
                    'timeopen' => self::TEST_QUIZ_SETTINGS['timeopen'],
                    'timeclose' => self::TEST_QUIZ_SETTINGS['timeclose'],
                    'attempts' => self::TEST_QUIZ_SETTINGS['attempts'],
                    // However change this, this should still get updated.
                    'timelimit' => self::TEST_QUIZ_SETTINGS['timelimit'] + 5,
                    'password' => null,
                ],
                'expectedrecordscreated' => 1,
                'expectedevent' => user_override_created::class,
            ],
            'user id is invalid' => [
                'existingdata' => [],
                'formdata' => [
                    'userid' => 99999999,
                    'groupid' => null,
                    'timeopen' => null,
                    'timeclose' => null,
                    'timelimit' => null,
                    'attempts' => null,
                    'password' => 'mypass',
                ],
                'expectedrecordscreated' => 0,
                'expectedevent' => '',
                'expectedexception' => 'User id invalid',
            ],
            'group id is invalid' => [
                'existingdata' => [],
                'formdata' => [
                    'userid' => null,
                    'groupid' => 99999999,
                    'timeopen' => null,
                    'timeclose' => null,
                    'timelimit' => null,
                    'attempts' => null,
                    'password' => 'mypass',
                ],
                'expectedrecordscreated' => 0,
                'expectedevent' => '',
                'expectedexception' => 'Group is invalid',
            ],
            'timeclose is before timestart' => [
                'existingdata' => [],
                'formdata' => [
                    'userid' => ':userid',
                    'groupid' => null,
                    'timeopen' => 50,
                    'timeclose' => 49,
                    'timelimit' => null,
                    'attempts' => null,
                    'password' => null,
                ],
                'expectedrecordscreated' => 0,
                'expectedevent' => '',
                'expectedexception' => 'Close time cannot be before or the same as the open time.',
            ],
            'timeclose is the same as timestart' => [
                'existingdata' => [],
                'formdata' => [
                    'userid' => ':userid',
                    'groupid' => null,
                    'timeopen' => 50,
                    'timeclose' => 50,
                    'timelimit' => null,
                    'attempts' => null,
                    'password' => null,
                ],
                'expectedrecordscreated' => 0,
                'expectedevent' => '',
                'expectedexception' => 'Close time cannot be before or the same as the open time.',
            ],
            'existing id given is invalid' => [
                'existingdata' => [],
                'formdata' => [
                    'id' => -1,
                    'userid' => ':userid',
                    'groupid' => null,
                    'timeopen' => 50,
                    'timeclose' => 51,
                    'timelimit' => null,
                    'attempts' => null,
                    'password' => null,
                ],
                'expectedrecordscreated' => 0,
                'expectedevent' => '',
                'expectedexception' => 'Quiz override ID specified does not exist',
            ],
            'user value changed when updated (not allowed)' => [
                'existingdata' => [
                    'userid' => ":userid",
                    'groupid' => null,
                    'timeopen' => 50,
                    'timeclose' => 51,
                    'timelimit' => 2,
                    'attempts' => 2,
                    'password' => 'test2',
                ],
                'formdata' => [
                    'id' => ':existingid',
                    'userid' => ":user2id",
                    'groupid' => null,
                    'timeopen' => 50,
                    'timeclose' => 51,
                    'timelimit' => null,
                    'attempts' => null,
                    'password' => null,
                ],
                'expectedrecordscreated' => 1,
                'expectedevent' => '',
                'expectedexception' => 'User id cannot be changed on existing overrides.',
            ],
            'group value changed when updated (not allowed)' => [
                'existingdata' => [
                    'userid' => null,
                    'groupid' => ':groupid',
                    'timeopen' => 50,
                    'timeclose' => 51,
                    'timelimit' => 2,
                    'attempts' => 2,
                    'password' => 'test2',
                ],
                'formdata' => [
                    'id' => ':existingid',
                    'userid' => null,
                    'groupid' => ':group2id',
                    'timeopen' => 50,
                    'timeclose' => 51,
                    'timelimit' => null,
                    'attempts' => null,
                    'password' => null,
                ],
                'expectedrecordscreated' => 1,
                'expectedevent' => '',
                'expectedexception' => 'Group id cannot be changed on existing overrides.',
            ],
        ];
    }

    /**
     * Tests upsert_override function
     * @param array $existingdata If given, an existing override will be created.
     * @param array $formdata The data being tested, simulating being submitted
     * @param int $expectedrecordscreated The number of records that are expected to be created by upsert
     * @param string $expectedeventclass an event class, which is expected to the emitted by upsert
     * @param string $expectedexception if given, the test will expect an exception with this message.
     * @dataProvider upsert_override_provider
     */
    public function test_upsert_and_get_override(array $existingdata, array $formdata, int $expectedrecordscreated,
        string $expectedeventclass, string $expectedexception = '') {
        global $DB;
        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $this->course->id);

        $user2 = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user2->id, $this->course->id);

        $groupid = groups_create_group((object) ['courseid' => $this->course->id, 'name' => 'test']);
        $group2id = groups_create_group((object) ['courseid' => $this->course->id, 'name' => 'test2']);

        // Replace any userid or groupid placeholders in the form data or existing data.
        $placeholdervalues = [
            ':userid' => $user->id,
            ':user2id' => $user2->id,
            ':groupid' => $groupid,
            ':group2id' => $group2id,
        ];

        if (!empty($existingdata)) {
            // Raw insert the existing data for the test into the DB.
            // We assume it is valid for the test.
            $existingid = $DB->insert_record('quiz_overrides', $this->replace_placeholders($existingdata, $placeholdervalues));
            $placeholdervalues[':existingid'] = $existingid;
        }

        $formdata = $this->replace_placeholders($formdata, $placeholdervalues);

        $manager = new override_manager($this->quizobj);

        // Put some test data in the cache, to ensure it gets modified.
        $cache = $manager->get_cache();

        if (!empty($formdata['userid'])) {
            $cache->set($manager->get_user_cache_key($formdata['userid']), 'thisisatest');
        }

        if (!empty($formdata['groupid'])) {
            $cache->set($manager->get_group_cache_key($formdata['groupid']), 'thisisatest');
        }

        // Get the count before.
        $beforecount = $DB->count_records('quiz_overrides');

        // Expect an exception if specified by the test.
        if (!empty($expectedexception)) {
            $this->expectExceptionMessage($expectedexception);
        }

        $sink = $this->redirectEvents();

        // Submit the form data.
        $id = $manager->upsert_override($formdata);

        // Get the count after and compare to the expected.
        $aftercount = $DB->count_records('quiz_overrides');
        $this->assertEquals($expectedrecordscreated, $aftercount - $beforecount);

        // Read back the created/updated value, and compare it to the formdata.
        $readback = $DB->get_record('quiz_overrides', ['id' => $id]);

        foreach ($formdata as $key => $value) {
            // If the value is the same as the quiz, we expect it to be null.
            if (!empty(self::TEST_QUIZ_SETTINGS[$key]) && $value == self::TEST_QUIZ_SETTINGS[$key]) {
                $this->assertNull($readback->{$key});
            } else {
                // Else we expect the value to have been set.
                $this->assertEquals($value, $readback->{$key});
            }
        }

        // Check the get_all_overrides function returns this data as well.
        $alloverrides = $manager->get_all_overrides();
        $this->assertCount($aftercount, $alloverrides);
        $this->assertTrue(in_array($id, array_keys((array) $alloverrides)));

        // Check that the cache was cleared (if expected to be a valid change).
        // We set an intial value 'thisisatest' before the update. If it still reads this, it means it wasn't updated.
        // Generally it will now contain the quiz override object.
        $cache = $manager->get_cache();

        if (!empty($formdata['userid'])) {
            $val = $cache->get($manager->get_user_cache_key($formdata['userid']));
            $this->assertNotEquals('thisisatest', $val);
        }

        if (!empty($formdata['groupid'])) {
            $val = $cache->get($manager->get_group_cache_key($formdata['groupid']));
            $this->assertNotEquals('thisisatest', $val);
        }

        // Check that the calendar events are created as well.
        // This is only if the times were set, and they were set differently to the default.
        $expectedcount = 0;

        if (!empty($formdata['timeopen']) && $formdata['timeopen'] != self::TEST_QUIZ_SETTINGS['timeopen']) {
            $expectedcount += 1;
        }

        if (!empty($formdata['timeclose']) && $formdata['timeclose'] != self::TEST_QUIZ_SETTINGS['timeclose']) {
            $expectedcount += 1;
        }

        // Find all events. We assume the test event times do not exceed a time of 999.
        $events = calendar_get_events(0, 999, [$user->id], [$groupid], false);
        $this->assertCount($expectedcount, $events);

        // Check the expected event was also emitted.
        if (!empty($expectedeventclass)) {
            $events = $sink->get_events();
            $eventclasses = array_map(function($e) {
                return get_class($e);
            }, $events);
            $this->assertTrue(in_array($expectedeventclass, $eventclasses));
        }
    }

    // Test delete all override as well - maybe pass in func ?

    /**
     * Tests delete_override function
     */
    public function test_delete_override() {
        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user();

        // Create an override.
        $data = [
            'userid' => $user->id,
            'timeopen' => 500,
        ];
        $manager = new override_manager($this->quizobj);
        $id = $manager->upsert_override($data);

        // Check the calendar event was made.
        $this->assertCount(1, calendar_get_events(0, 999, [$user->id], false, false));

        // Check that the cache was made.
        $cache = $manager->get_cache();
        $key = $manager->get_user_cache_key($user->id);
        $this->assertNotEmpty($cache->get($key));

        // Delete the override.
        $manager->delete_override($id);

        // Check the calendar event was deleted.
        $this->assertCount(0, calendar_get_events(0, 999, [$user->id], false, false));

        // Check that the cache was cleared.
        $this->assertEmpty($cache->get($key));
    }

    /**
     * Provides values to the test_permissions test
     * @return array
     */
    public static function permissions_provider(): array {
        // Define some functions to call for the test.
        // Note the data itself is irrelevant, as permissions should always be checked first.
        $upsert = fn($manager) => $manager->upsert_override([]);
        $get = fn($manager) => $manager->get_all_overrides();
        $delete = fn($manager) => $manager->delete_override(-1);

        return [
            'no permissions - upserting' => [
                'permissions' => [],
                'expectedexception' => 'Sorry, but you do not currently have permissions to do that',
                'function' => $upsert,
            ],
            'no permissions - getting' => [
                'permissions' => [],
                'expectedexception' => 'Sorry, but you do not currently have permissions to do that',
                'function' => $get,
            ],
            'no permissions - deleting' => [
                'permissions' => [],
                'expectedexception' => 'Sorry, but you do not currently have permissions to do that',
                'function' => $delete,
            ],
            'has permissions' => [
                'permissions' => ['mod/quiz:manageoverrides' => CAP_ALLOW],
                'expectedexception' => null,
                'function' => $get,
            ],
        ];
    }

    /**
     * Tests permission checking.
     * @param array $permissiontogive an array of captype => permission to give to the test user
     * @param string $expectedexception if given, the test will expect an exception with this message to be thrown.
     * @dataProvider permissions_provider
     */
    public function test_permissions(array $permissiontogive, $expectedexception, $functiontoexecute) {
        // Setup the role and permissions.
        $roleid = $this->getDataGenerator()->create_role();
        foreach ($permissiontogive as $capname => $permission) {
            role_change_permission($roleid, context_system::instance(), $capname, $permission);
        }

        $user = $this->getDataGenerator()->create_user();
        role_assign($roleid, $user->id, context_system::instance()->id);

        if (!empty($expectedexception)) {
            $this->expectExceptionMessage($expectedexception);
        }

        $this->setUser($user);

        // Execute the function defined by the test, using the created manager.
        $manager = new override_manager($this->quizobj);
        $functiontoexecute($manager);
    }
}
