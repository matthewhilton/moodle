<?php

use mod_quiz\event\group_override_created;
use mod_quiz\event\group_override_updated;
use mod_quiz\event\user_override_created;
use mod_quiz\event\user_override_updated;
use mod_quiz\override_manager;
use mod_quiz\quiz_settings;

class override_manager_test extends advanced_testcase {
    
    private $quizobj;

    private $course;

    public function setUp(): void {
        $this->resetAfterTest();

        $this->course = $this->getDataGenerator()->create_course(['groupmode' => SEPARATEGROUPS]);
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $this->course->id]);
        $this->quizobj = quiz_settings::create($quiz->id);
    }

    public static function upsert_override_provider(): array {
        return [
            'create user override - no existing data' => [
                'existingdata' => [],
                'formdata' => [
                    'userid' => ':userid',
                    'groupid' => null,
                    'timeopen' => 999,
                    'timeclose' => 1000,
                    'timelimit' => 1,
                    'attempts' => 999,
                    'password' => 'test',
                ],
                'expectedcountchange' => 1,
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
                'expectedcountchange' => 1,
                'expectedevent' => user_override_created::class,
            ],
            'create user override - only timeopen' => [
                'existingdata' => [],
                'formdata' => [
                    'userid' => ':userid',
                    'groupid' => null,
                    'timeopen' => 111,
                    'timeclose' => null,
                    'timelimit' => null,
                    'attempts' => null,
                    'password' => 'test',
                ],
                'expectedcountchange' => 1,
                'expectedevent' => user_override_created::class,
            ],
            'create group override - no existing data' => [
                'existingdata' => [],
                'formdata' => [
                    'userid' => null,
                    'groupid' => ':groupid',
                    'timeopen' => 999,
                    'timeclose' => 1000,
                    'timelimit' => 1,
                    'attempts' => 999,
                    'password' => 'test',
                ],
                'expectedcountchange' => 1,
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
                'expectedcountchange' => 1,
                'expectedevent' => group_override_created::class,
            ],
            'create group override - only timeopen' => [
                'existingdata' => [],
                'formdata' => [
                    'userid' => null,
                    'groupid' => ':groupid',
                    'timeopen' => 111,
                    'timeclose' => null,
                    'timelimit' => null,
                    'attempts' => null,
                    'password' => null,
                ],
                'expectedcountchange' => 1,
                'expectedevent' => group_override_created::class,
            ],
            'update user override - updating existing data' => [
                'existingdata' => [
                    'userid' => ':userid',
                    'groupid' => null,
                    'timeopen' => 222,
                    'timeclose' => 223,
                    'timelimit' => 2,
                    'attempts' => 2,
                    'password' => 'test2',
                ],
                'formdata' => [
                    'id' => ':existingid',
                    'userid' => ':userid',
                    'groupid' => null,
                    'timeopen' => 999,
                    'timeclose' => 1000,
                    'timelimit' => 1,
                    'attempts' => 999,
                    'password' => 'test',
                ],
                'expectedcountchange' => 0,
                'expectedevent' => user_override_updated::class,
            ],
            'update group override - updating existing data' => [
                'existingdata' => [
                    'userid' => null,
                    'groupid' => ':groupid',
                    'timeopen' => 222,
                    'timeclose' => 223,
                    'timelimit' => 2,
                    'attempts' => 2,
                    'password' => 'test2',
                ],
                'formdata' => [
                    'id' => ':existingid',
                    'userid' => null,
                    'groupid' => ':groupid',
                    'timeopen' => 999,
                    'timeclose' => 1000,
                    'timelimit' => 1,
                    'attempts' => 999,
                    'password' => 'test',
                ],
                'expectedcountchange' => 0,
                'expectedevent' => group_override_updated::class,
            ],
            'update override, but new data has no user or group set' => [
                'existingdata' => [
                    'userid' => ':userid',
                    'groupid' => null,
                    'timeopen' => 222,
                    'timeclose' => 223,
                    'timelimit' => 2,
                    'attempts' => 2,
                    'password' => 'test2',
                ],
                'formdata' => [
                    'id' => ':existingid',
                    'userid' => null,
                    'groupid' => null,
                    'timeopen' => 999,
                    'timeclose' => 1000,
                    'timelimit' => 1,
                    'attempts' => 999,
                    'password' => 'test',
                ],
                'expectedcountchange' => 0,
                'expectedevent' => '',
                'expectedexception' => 'Either userid or groupid must be set',
            ],
            'saving both group and userid - expected invalid' => [
                'existingdata' => [],
                'formdata' => [
                    'userid' => ':userid',
                    'groupid' => ':groupid',
                    'timeopen' => 999,
                    'timeclose' => 1000,
                    'timelimit' => 1,
                    'attempts' => 999,
                    'password' => 'test',
                ],
                'expectedcountchange' => 0,
                'expectedevent' => '',
                'expectedexception' => 'Userid and groupid were both set, but only one can be set at once.',
            ],
            'saving neither group nor userid - expected invalid' => [
                'existingdata' => [],
                'formdata' => [
                    'userid' => null,
                    'groupid' => null,
                    'timeopen' => 999,
                    'timeclose' => 1000,
                    'timelimit' => 1,
                    'attempts' => 999,
                    'password' => 'test',
                ],
                'expectedcountchange' => 0,
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
                'expectedcountchange' => 0,
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
                'expectedcountchange' => 0,
                'expectedevent' => '',
                'expectedexception' => 'No settings were changed',
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
                'expectedcountchange' => 0,
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
                'expectedcountchange' => 0,
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
                'expectedcountchange' => 0,
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
                'expectedcountchange' => 0,
                'expectedevent' => '',
                'expectedexception' => 'Close time cannot be before or the same as the open time.',
            ],
        ];
    }

    /**
     * Replaces the placeholders in the given data.
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
     * @dataProvider upsert_override_provider
     */
    public function test_upsert_override(array $existingdata, array $formdata, int $expectedcountchange, string $expectedeventclass, string $expectedexception = '') {
        global $DB;
        $this->setAdminUser();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $this->course->id);
        $groupid = groups_create_group((object) ['courseid' => $this->course->id, 'name' => 'test']);

        // Replace any userid or groupid placeholders in the form data or existing data.
        $placeholdervalues = [
            ':userid' => $user->id,
            ':groupid' => $groupid,
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
        $this->assertEquals($expectedcountchange, $aftercount - $beforecount);

        // Read back the created/updated value, and compare it to the formdata.
        $readback = $DB->get_record('quiz_overrides', ['id' => $id]);

        foreach ($formdata as $key => $value) {
            $this->assertEquals($value, $readback->{$key});
        }

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
        $expectedcount = 0;

        if (!empty($formdata['timeopen'])) {
            $expectedcount += 1;
        }

        if (!empty($formdata['timeclose'])) {
            $expectedcount += 1;
        }

        // Find all events. We assume the test event times do not exceed 99999.
        $events = calendar_get_events(0, 99999, [$user->id], [$groupid], false);
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

    public static function permissions_provider(): array {
        return [
            'no permissions' => [
                'permissions' => [],
                'expectedexception' => 'Sorry, but you do not currently have permissions to do that',
            ],
            'has permissions' => [
                'permissions' => ['mod/quiz:manageoverrides' => CAP_ALLOW],
                'expectedexception' => null,
            ],
        ];
    }

    /**
     * @dataProvider permissions_provider
     */
    public function test_permissions(array $permissiontogive, $expectedexception) {
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

        // Try edit.
        $manager = new override_manager($this->quizobj);
        $manager->upsert_override([
            'userid' => $user->id,
            'password' => 'test',
        ]);
    }
}
