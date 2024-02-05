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

use cache;
use calendar_event;
use mod_quiz\event\group_override_created;
use mod_quiz\event\group_override_deleted;
use mod_quiz\event\group_override_updated;
use mod_quiz\event\user_override_created;
use mod_quiz\event\user_override_deleted;
use mod_quiz\event\user_override_updated;
use context_module;
use core_user;
use invalid_parameter_exception;
use lang_string;
use required_capability_exception;

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/mod/quiz/locallib.php');

/**
 * Manager class for quiz overrides
 *
 * @package   mod_quiz
 * @copyright 2024 Matthew Hilton <matthewhilton@catalyst-au.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class override_manager {

    /** @var array quiz setting keys that can be overwritten **/
    const KEYS = ['timeopen', 'timeclose', 'timelimit', 'attempts', 'password'];

    /** @var int $quizid **/
    private $quizid;

    /**
     * Creates a manager instance for the quiz linked to a given override
     * @param int $overrideid Id of existing override
     * @return override_manager
     */
    public static function create_from_override($overrideid) {
        global $DB;
        $quiz = $DB->get_field('quiz_overrides', 'quiz', ['id' => $overrideid], MUST_EXIST);
        return self::create_from_quiz($quiz);
    }

    /**
     * Creates a manager instance linked to the given quiz.
     * @param int $quizid
     * @return override_manager
     */
    public static function create_from_quiz($quizid) {
        return new override_manager($quizid);
    }

    /**
     * Create override manager
     * @param int $quizid The quiz to link the manager to.
     */
    public function __construct($quizid) {
        $this->quizid = $quizid;
    }

    /**
     * Returns all overrides for the linked quiz
     * @return array array of quiz_override persistent objects
     */
    public function get_all_overrides() {
        global $DB;
        $this->require_read_capability();
        return $DB->get_records('quiz_overrides', ['quiz' => $this->quizid]);
    }

    /**
     * Gets the context of the manager.
     * @return context
     */
    public function get_context() {
        $cm = get_coursemodule_from_instance('quiz', $this->quizid, 0, false, MUST_EXIST);
        return context_module::instance($cm->id);
    }

    /**
     * Validates the data
     * @param array $formdata
     * @return lang_string|true lang string if error, else true.
     */
    private function validate_data($formdata) {
        global $DB;

        $formdata = (object) $formdata;

        // Ensure at least one of KEYS is set.
        $keysthatareset = array_map(fn($key) => !empty($formdata->$key), self::KEYS);
        if (!in_array(true, $keysthatareset)) {
            return new lang_string('overridemustchangesetting', 'quiz');
        }

        // Ensure quiz is a valid quiz.
        if (empty(get_coursemodule_from_instance('quiz', $formdata->quiz))) {
            return new lang_string('overrideinvalidquiz', 'quiz');
        }

        // Ensure either userid or groupid is set.
        if (empty($formdata->userid) && empty($formdata->groupid)) {
            return new lang_string('overridemustsetuserorgroup', 'quiz');
        }

        // Ensure not both userid and groupid are set.
        if (!empty($formdata->userid) && !empty($formdata->groupid)) {
            return new lang_string('overridecannotsetbothgroupanduser', 'quiz');
        }

        // If group is set, ensure it is a real group.
        if (!empty($formdata->groupid) && empty(groups_get_group($formdata->groupid))) {
            return new lang_string('overrideinvalidgroup', 'quiz');
        }

        // If user is set, ensure it is a valid user.
        if (!empty($formdata->userid) && !core_user::is_real_user($formdata->userid, true)) {
            return new lang_string('overrideinvaliduser', 'quiz');
        }

        // Ensure timeclose is later than timeopen, if both are set.
        if (!empty($formdata->timeclose) && !empty($formdata->timeopen) && $formdata->timeclose <= $formdata->timeopen) {
            return new lang_string('overridetimeclosebeforetimeopen', 'quiz');
        }

        // Ensure attempts is greater than zero.
        if (!empty($formdata->attempts) && $formdata->attempts <= 0) {
            return new lang_string('overrideinvalidattempts', 'quiz');
        }

        // Ensure timelimit is greather than zero.
        if (!empty($formdata->timelimit) && $formdata->timelimit <= 0) {
            return new lang_string('overrideinvalidtimelimit', 'quiz');
        }

        // Ensure other records do not exist with the same group or user.
        if (!empty($formdata->userid) || !empty($formdata->groupid)) {
            $existingrecordparams = ['quiz' => $formdata->quiz, 'groupid' => $formdata->groupid ?? null,
                'userid' => $formdata->userid ?? null, ];
            $records = $DB->get_records('quiz_overrides', $existingrecordparams, '', 'id');

            // Ignore self if updating.
            if (!empty($formdata->id)) {
                unset($records[$formdata->id]);
            }

            // If count is not zero, it means existing records exist already for this user/group.
            if (!empty($records)) {
                return new lang_string('overridemultiplerecordsexist', 'quiz');
            }
        }

        // If is existing record, validate it against the existing record.
        if (!empty($formdata->id)) {
            $existingrecordvalidation = $this->validate_against_existing_record($formdata->id, $formdata);

            if ($existingrecordvalidation !== true) {
                return $existingrecordvalidation;
            }
        }

        // All checks passed.
        return true;
    }

    /**
     * Validates the formdata against an existing record.
     * @param int $existingid
     * @param object $formdata
     */
    private function validate_against_existing_record($existingid, $formdata) {
        global $DB;

        $existingrecord = $DB->get_record('quiz_overrides', ['id' => $existingid]);

        // Existing record must exist.
        if (empty($existingrecord)) {
            return new lang_string('overrideinvalidexistingid', 'quiz');
        }

        // Group value must match existing record if it is set in the formdata.
        if (!empty($formdata->groupid) && $existingrecord->groupid != $formdata->groupid) {
            return new lang_string('overridecannotchange', 'quiz');
        }

        // User value must match existing record if it is set in the formdata.
        if (!empty($formdata->userid) && $existingrecord->userid != $formdata->userid) {
            return new lang_string('overridecannotchange', 'quiz');
        }

        return true;
    }

    /**
     * Parses the formdata by finding only the given KEYS,
     * then clearing any values that match the existing quiz. It then re-adds the user or group id.
     * @param array $formdata
     * @return array parsed formdata
     */
    public function parse_formdata($formdata) {
        // Ensure its an array.
        $formdata = (array) $formdata;

        // Get the data from the form that we want to update.
        $settings = array_intersect_key($formdata, array_flip(self::KEYS));

        // Remove values that are the same as currently in the quiz.
        $settings = $this->clear_unused_values($settings);

        // Add the user / group back as applicable.
        $userorgroupdata = array_intersect_key($formdata, array_flip(['userid', 'groupid']));

        return array_merge($settings, $userorgroupdata);
    }

    /**
     * Upserts the given override. If an id is given, it updates, otherwise it creates a new one.
     * @param array $formdata
     * @return int updated/inserted record id
     */
    public function upsert_override($formdata): int {
        global $DB;

        // Ensure its an array.
        $formdata = (array) $formdata;

        // Ensure logged in user can manage overrides.
        $this->require_write_capability();

        // Extract only the necessary data.
        $datatoset = $this->parse_formdata($formdata);
        $datatoset['quiz'] = $this->quizid;
        $datatoset['id'] = $formdata['id'] ?? 0;

        // Validate the data is OK.
        // Returns a lang string if not OK, otherwise true.
        $validation = $this->validate_data($datatoset);

        if ($validation !== true) {
            throw new invalid_parameter_exception($validation);
        }

        // Insert or update.
        $id = $datatoset['id'];
        if (!empty($datatoset['id'])) {
            $DB->update_record('quiz_overrides', $datatoset);
        } else {
            $id = $DB->insert_record('quiz_overrides', $datatoset);
        }

        $userid = $datatoset['userid'] ?? null;
        $groupid = $datatoset['groupid'] ?? null;

        // Clear the cache.
        $this->clear_cache($userid, $groupid);

        // Trigger moodle events.
        if (empty($formdata['id'])) {
            $this->log_created($id, $userid, $groupid);
        } else {
            $this->log_updated($id, $userid, $groupid);
        }

        // Update open events.
        quiz_update_open_attempts(['quizid' => $this->quizid]);

        // Update calendar events.
        $isgroup = !empty($datatoset['groupid']);
        if ($isgroup) {
            // If is group, must update the entire quiz calendar events.
            quiz_update_events($this->get_quiz());
        } else {
            // If is just a user, can update only their calendar event.
            quiz_update_events($this->get_quiz(), (object) $datatoset);
        }

        return $id;
    }

    /**
     * Returns the linked quiz object
     * @return object
     */
    private function get_quiz() {
        global $DB;
        return $DB->get_record('quiz', ['id' => $this->quizid], '*', MUST_EXIST);
    }

    /**
     * Deletes the override cache record for the given user/group
     * @param int $userid
     * @param int $groupid
     */
    private function clear_cache($userid = null, $groupid = null) {
        $cache = $this->get_cache();

        if (!empty($userid)) {
            $cache->delete($this->get_user_cache_key($userid));
        }

        if (!empty($groupid)) {
            $cache->delete($this->get_group_cache_key($groupid));
        }
    }

    /**
     * Deletes all the overrides for the linked quiz
     * @param bool $shouldlog If true, will log a override_deleted event
     */
    public function delete_all_overrides($shouldlog = true) {
        global $DB;

        $overrides = $DB->get_records('quiz_overrides', ['quiz' => $this->quizid], '', 'id');

        foreach ($overrides as $override) {
            $this->delete_override($override->id, $shouldlog);
        }
    }

    /**
     * Deletes the given override
     * @param int $id override id to delete
     * @param bool $shouldlog If true, will log a override_deleted event
     */
    public function delete_override($id, $shouldlog = true) {
        global $DB;

        $this->require_write_capability();

        // Find the override first, and record the data.
        $override = $DB->get_record('quiz_overrides', ['id' => $id], '*', MUST_EXIST);

        $userid = $override->userid ?? null;
        $groupid = $override->groupid ?? null;

        // Delete the events.
        $eventssearchparams = ['modulename' => 'quiz', 'instance' => $this->quizid];

        if (!empty($override->userid)) {
            $eventssearchparams['userid'] = $userid;
        }

        if (!empty($override->groupid)) {
            $eventssearchparams['groupid'] = $groupid;
        }

        $events = $DB->get_records('event', $eventssearchparams);
        foreach ($events as $event) {
            $eventold = calendar_event::load($event);
            $eventold->delete();
        }

        $DB->delete_records('quiz_overrides', ['id' => $id]);

        // Clear the cache.
        $this->clear_cache($userid, $groupid);

        // Log deletion.
        if ($shouldlog) {
            $this->log_deleted($id, $userid, $groupid);
        }
    }

    /**
     * Requires the user has the override management capability
     */
    private function require_write_capability() {
        require_capability('mod/quiz:manageoverrides', $this->get_context());
    }

    /**
     * Requires the user has the override viewing capability
     */
    private function require_read_capability() {
        // If user can manage, they can also view, because it would be illogical
        // to be able to create and edit overrides without being able to view them.
        if (!has_any_capability(['mod/quiz:viewoverrides', 'mod/quiz:manageoverrides'], $this->get_context())) {
            throw new required_capability_exception($this->get_context(), 'mod/quiz:viewoverrides', 'nopermissions', '');
        }
    }

    /**
     * Returns common event data
     * @param int $id override id
     * @return array
     */
    private function get_base_event_params($id) {
        return [
            'context' => $this->get_context(),
            'other' => [
                'quizid' => $this->quizid,
            ],
            'objectid' => $id,
        ];
    }

    /**
     * Log that a given override was deleted
     * @param int $id
     * @param int $userid or null
     * @param int $groupid or null
     */
    private function log_deleted($id, $userid = null, $groupid = null) {
        $params = $this->get_base_event_params($id);
        $params['objectid'] = $id;

        if (!empty($userid)) {
            $params['relateduserid'] = $userid;
            user_override_deleted::create($params)->trigger();
        }

        if (!empty($groupid)) {
            $params['other']['groupid'] = $groupid;
            group_override_deleted::create($params)->trigger();
        }
    }

    /**
     * Log that a given override was created
     * @param int $id override id
     * @param int $userid or null
     * @param int $groupid or null
     */
    private function log_created($id, $userid = null, $groupid = null) {
        $params = $this->get_base_event_params($id);

        if (!empty($userid)) {
            $params['relateduserid'] = $userid;
            user_override_created::create($params)->trigger();
        }

        if (!empty($groupid)) {
            $params['other']['groupid'] = $groupid;
            group_override_created::create($params)->trigger();
        }
    }

    /**
     * Log that a given override was updated
     * @param int $id override id
     * @param int $userid or null
     * @param int $groupid or null
     */
    private function log_updated($id, $userid = null, $groupid = null) {
        $params = $this->get_base_event_params($id);

        if (!empty($userid)) {
            $params['relateduserid'] = $userid;
            user_override_updated::create($params)->trigger();
        }

        if (!empty($groupid)) {
            $params['other']['groupid'] = $groupid;
            group_override_updated::create($params)->trigger();
        }
    }

    /**
     * Clears any KEYS in the formdata, where the value matches what is already in the quiz
     * @param array $formdata
     * @return array formdata with same values cleared
     */
    private function clear_unused_values($formdata) {
        $quiz = $this->get_quiz();

        foreach (self::KEYS as $key) {
            // If the formdata is the same as the current quiz object data, clear it.
            if (!empty($formdata[$key]) && $formdata[$key] == $quiz->$key) {
                $formdata[$key] = null;
            }

            // If the formdata is empty, set it to null.
            // This avoid putting 0 or '' into the DB since the override logic expects null.
            if (empty($formdata[$key])) {
                $formdata[$key] = null;
            }
        }

        return $formdata;
    }

    /**
     * Returns the override cache
     * @return cache
     */
    public static function get_cache() {
        return cache::make('mod_quiz', 'overrides');
    }

    /**
     * Returns group cache key
     * @param int $groupid
     * @return string
     */
    public function get_group_cache_key($groupid) {
        return "{$this->quizid}_g_{$groupid}";
    }

    /**
     * Returns user cache key
     * @param int $userid
     * @return string
     */
    public function get_user_cache_key($userid) {
        return "{$this->quizid}_u_{$userid}";
    }
}
