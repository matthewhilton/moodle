<?php

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
                'expectedvalid' => true,
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
                'expectedvalid' => true,
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
                'expectedvalid' => true,
            ],
        ];
    }

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
    public function test_upsert_override(array $existingdata, array $formdata, int $expectedcountchange, bool $expectedvalid) {
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

        // TODO create any existing data.
        $manager = new override_manager($this->quizobj);

        // Put some test data in the cache, to ensure it gets deleted.
        if ($expectedvalid) {
            $cache = $manager->get_cache();

            if (!empty($formdata['userid'])) {
                $cache->set($manager->get_user_cache_key($formdata['userid']), 'thisisatest');
            }

            if (!empty($formdata['groupid'])) {
                $cache->set($manager->get_group_cache_key($formdata['groupid']), 'thisisatest');
            }
        }

        // Get the count before.
        $beforecount = $DB->count_records('quiz_overrides');

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
        if ($expectedvalid) {
            $cache = $manager->get_cache();

            if (!empty($formdata['userid'])) {
                $val = $cache->get($manager->get_user_cache_key($formdata['userid']));
                $this->assertNotEquals('thisisatest', $val);
            }

            if (!empty($formdata['groupid'])) {
                $val = $cache->get($manager->get_group_cache_key($formdata['groupid']));
                $this->assertNotEquals('thisisatest', $val);
            }
        }
    }

    // Test invalid data.
    //
    // Test CRUD
    //
    // Test events (maybe already tested by events_test ?)
    //
    // Test permissions check
    //
    // Test cache
    //
    // Test calendar events updating
}
