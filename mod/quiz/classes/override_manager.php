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
use coding_exception;
use core_user;
use mod_quiz\event\group_override_created;
use mod_quiz\event\group_override_deleted;
use mod_quiz\event\group_override_updated;
use mod_quiz\event\user_override_created;
use mod_quiz\event\user_override_deleted;
use mod_quiz\event\user_override_updated;

/**
 * Manager class for quiz overrides
 *
 * @package   mod_quiz
 * @copyright 2024 Matthew Hilton <matthewhilton@catalyst-au.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class override_manager {

    /** @var quiz_settings the quiz settings **/
    private $quizobj;

    /** @var array quiz override properties that can be modified. **/
    private const KEYS = ['timeopen', 'timeclose', 'timelimit', 'attempts', 'password'];

    public static function create_from_override($overrideid) {
        $override = self::get_override_record($overrideid);

        // TODO some validation here...
        $quizobj = quiz_settings::create($override->quiz);
        return new override_manager($quizobj);
    }

    public static function create_from_quiz($quizid) {
        $quizobj = quiz_settings::create($quizid);
        return new override_manager($quizobj);
    }

    /**
     * Create override manager
     * @param quiz_settings $quizobj the quiz settings to link to
     */
    public function __construct($quizobj) {
        $this->quizobj = $quizobj;
    }

    /**
     * Returns all overrides for the linked quiz
     * @return array
     */
    public function get_all_overrides() {
        global $DB;
        $this->check_capabilties();
        return $DB->get_records('quiz_overrides', ['quiz' => $this->get_quiz_id()]);
    }

    private static function get_override_record($id, $strictness = MUST_EXIST) {
        global $DB;
        return $DB->get_record('quiz_overrides', ['id' => $id], '*', $strictness);
    }
    
    // TODO unit tests.
    public function get_context() {
        return $this->quizobj->get_context();
    }

    /**
     * Validates the formdata
     * @param array $formdata data submitted by form, or passed from webservice
     * @throws coding_exception if there are errors found
     */ // TODO SHOULD this throw a different exception? Maybe w/ lang strings?
    private function validate_formdata($formdata) {
        global $DB;
        $formdata = (object) $formdata;

        // If there are no settings being overwritten, error.
        $datatocheck = array_intersect_key((array) $formdata, array_flip(self::KEYS));

        // If itself is empty, or no values are truthy.
        $isempty = empty($datatocheck) || count(array_filter((array) $datatocheck)) == 0;

        if ($isempty) {
            throw new coding_exception("No settings were changed");
        }

        $existingdata = !empty($formdata->id) ? $this->get_override_record($formdata->id, IGNORE_MISSING) : null;

        // If id of existing is given, check that it exists.
        if (!empty($formdata->id) && empty($existingdata)) {
            throw new coding_exception("Quiz override ID specified does not exist");
        }

        // Ensure the dates make sense (if both are given).
        if (!empty($formdata->timeopen) && !empty($formdata->timeclose) && $formdata->timeclose <= $formdata->timeopen) {
            throw new coding_exception("Close time cannot be before or the same as the open time.");
        }

        // Ensure that userid and groupid are not both set at the same time.
        if (!empty($formdata->userid) && !empty($formdata->groupid)) {
            throw new coding_exception("Userid and groupid were both set, but only one can be set at once.");
        }

        // Ensure they at least one of them is set.
        if (empty($formdata->userid) && empty($formdata->groupid)) {
            throw new coding_exception("Either userid or groupid must be set");
        }

        // If userid is set, validate it is a valid user.
        if (!empty($formdata->userid) && !core_user::is_real_user($formdata->userid, true)) {
            throw new coding_exception("User id invalid");
        }

        // If groupid is set, validate it is a real group.
        if (!empty($formdata->groupid) && empty(groups_get_group($formdata->groupid))) {
            throw new coding_exception("Group is invalid");
        }

        // If updating, and the given userid does not match the existing userid.
        if (!empty($existingdata->userid) && !empty($formdata->userid) && $existingdata->userid != $formdata->userid) {
            throw new coding_exception("User id cannot be changed on existing overrides.
                Delete the override, and make a new one instead.");
        }

        // If updating, and the given groupid does not match the existing groupid.
        if (!empty($existingdata->groupid) && !empty($formdata->groupid) && $existingdata->groupid != $formdata->groupid) {
            throw new coding_exception("Group id cannot be changed on existing overrides.
                Delete the override, and make a new one instead.");
        }

        // Ensure an override does not exist already for this user / group in this quiz.
        // Only 1 override is allowed.
        $params = ['quiz' => $this->get_quiz_id()];

        if (!empty($formdata->userid)) {
            $params = array_merge($params, ['userid' => $formdata->userid]);
        }

        if (!empty($formdata->groupid)) {
            $params = array_merge($params, ['groupid' => $formdata->groupid]);
        }

        $results = $DB->get_records('quiz_overrides', $params);

        // If editing an override, exclude it from the list as will be overwritten.
        if (!empty($formdata->id)) {
            unset($results[$formdata->id]);
        }

        if (!empty($results)) {
            throw new coding_exception("Quiz override already exists for this user or group");
        }
    }

    /**
     * Parses the formdata by finding only the given KEYS,
     * then clearing any values that match the existing quiz. It then re-adds the user or group id.
     * @param array $formdata
     * @return array parsed formdata
     */
    public function parse_formdata($formdata) {
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
        $this->check_capabilties();

        // Extract only the necessary data.
        $datatoset = $this->parse_formdata($formdata);

        $id = $formdata['id'] ?? 0;

        // Add the quiz ID.
        $datatoset['quiz'] = $this->get_quiz_id();
        $datatoset['id'] = $id;

        // Validate the formdata.
        $this->validate_formdata($datatoset);

        // Update the DB record.
        if (!empty($id)) {
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
        quiz_update_open_attempts(['quizid' => $this->get_quiz_id()]);

        // Update calendar events.
        $isgroup = !empty($datatoset['groupid']);
        if ($isgroup) {
            // If is group, must update the entire quiz calendar events.
            quiz_update_events($this->quizobj->get_quiz());
        } else {
            // If is just a user, can update only their calendar event.
            quiz_update_events($this->quizobj->get_quiz(), (object) $datatoset);
        }

        return $id;
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
     */
    public function delete_all_overrides() {
        global $DB;

        $overrides = $DB->get_records('quiz_overrides', ['quiz' => $this->get_quiz_id()], '', 'id');

        foreach ($overrides as $override) {
            $this->delete_override($override->id);
        }
    }

    /**
     * Deletes the given override
     * @param $id override id to delete
     */
    public function delete_override($id) {
        global $DB;

        $this->check_capabilties();

        // Find the override first, and record the data.
        $override = $DB->get_record('quiz_overrides', ['id' => $id], '*', MUST_EXIST);

        // Delete the events.
        $eventssearchparams = ['modulename' => 'quiz', 'instance' => $this->get_quiz_id()];

        if (!empty($override->userid)) {
            $eventssearchparams['userid'] = (int) $override->userid;
        }

        if (!empty($override->groupid)) {
            $eventssearchparams['groupid'] = (int) $override->groupid;
        }

        $events = $DB->get_records('event', $eventssearchparams);
        foreach ($events as $event) {
            $eventold = calendar_event::load($event);
            $eventold->delete();
        }

        $DB->delete_records('quiz_overrides', ['id' => $id]);

        // Clear the cache.
        $this->clear_cache($override->userid, $override->groupid);

        // Log deletion.
        $this->log_deleted($id, $override->userid, $override->groupid);
    }

    /**
     * Requires the user has the override management capability
     */
    private function check_capabilties() {
        require_capability('mod/quiz:manageoverrides', $this->quizobj->get_context());
    }

    private function get_quiz_id() {
        return $this->quizobj->get_quiz()->id;
    }

    private function log_deleted($overrideid, $userid = null, $groupid = null) {
        $params = $this->get_base_event_params($overrideid);
        $params['objectid'] = $overrideid;

        if (!empty($userid)) {
            $params['relateduserid'] = $userid;
            user_override_deleted::create($params)->trigger();
        }

        if (!empty($groupid)) {
            $params['other']['groupid'] = $groupid;
            group_override_deleted::create($params)->trigger();
        }
    }

    private function get_base_event_params($id) {
        return [
            'context' => $this->quizobj->get_context(),
            'other' => [
                'quizid' => $this->get_quiz_id(),
            ],
            'objectid' => $id,
        ];
    }

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

    private function clear_unused_values($formdata) {
        foreach (self::KEYS as $key) {
            // If the formdata is the same as the current quiz object data, clear it.
            if (!empty($formdata[$key]) && $formdata[$key] == $this->quizobj->get_quiz()->$key) {
                $formdata[$key] = null;
            }
        }

        return $formdata;
    }

    public function get_cache() {
        return cache::make('mod_quiz', 'overrides');
    }

    public function get_group_cache_key($groupid): string {
        return "{$this->get_quiz_id()}_g_{$groupid}";
    }

    public function get_user_cache_key($userid): string {
        return "{$this->get_quiz_id()}_u_{$userid}";
    }
}
