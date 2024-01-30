<?php

namespace mod_quiz;

use cache;
use calendar_event;
use coding_exception;
use mod_quiz\event\group_override_created;
use mod_quiz\event\group_override_deleted;
use mod_quiz\event\group_override_updated;
use mod_quiz\event\user_override_created;
use mod_quiz\event\user_override_deleted;
use mod_quiz\event\user_override_updated;

class override_manager {
    private $quizobj;

    private const KEYS = ['timeopen', 'timeclose', 'timelimit', 'attempts', 'password'];

    public function __construct($quizobj) {
        $this->quizobj = $quizobj;
    }

    /*
     * TODO process
     * Get quiz id
     * Find existing overrides -> apply these settings ontop of them.
     * *** FOR some reason - delete the old override ???
     * Insert or update the record + update the cache
     * Trigger events
     * quiz_update_open_attempts
     * quiz_update_events based on group mode
     * redirect
     */

    /**
     * TODO If updating one, but the group or user is the same as an existing one, merge them
     */

    private function validate_formdata($formdata) {
        global $DB;

        // Ensure it is an object.
        $formdata = (object) $formdata;

        // First ensure userid and groupid are both not set at the same time.
        if (!empty($formdata->userid) && !empty($formdata->groupid)) {
            throw new coding_exception("Userid and groupid were both set, but only one can be set at once.");
        }

        // Ensure they at least one of them is set.
        if (empty($formdata->userid) && empty($formdata->groupid)) {
            throw new coding_exception("Either userid or groupid must be set");
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

    public function parse_formdata($formdata) {
        // Get the data from the form that we want to update.
        $settings = array_intersect_key((array) $formdata, array_flip(self::KEYS));

        // Remove values that are the same as currently in the quiz.
        $settings = $this->clear_unused_values($settings);

        // Add the user / group back as applicable.
        $userorgroupdata = array_intersect_key((array) $formdata, array_flip(['userid', 'groupid']));

        return array_merge($settings, $userorgroupdata);
    }

    public function upsert_override($formdata) {
        global $DB;

        // Ensure its an array.
        $formdata = (array) $formdata;

        // Ensure logged in user can manage overrides.
        $this->check_capabilties();

        // Validate the formdata. We cannot assume it is valid.
        $this->validate_formdata($formdata);

        // Validate and get the data submitted by the form.
        $datatoset = $this->parse_formdata($formdata);

        // Add the quiz ID.
        $datatoset['quiz'] = $this->get_quiz_id();

        // Update the DB record.
        if (!empty($formdata['id'])) {
            $datatoset['id'] = $formdata['id'];
            $id = $formdata['id'];
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

    private function clear_cache($userid = null, $groupid = null) {
        $cache = $this->get_cache();

        if (!empty($userid)) {
            $cache->delete($this->get_user_cache_key($userid));
        }

        if (!empty($groupid)) {
            $cache->delete($this->get_group_cache_key($groupid));
        }
    }

    public function delete_all_overrides() {
        global $DB;

        $overrides = $DB->get_records('quiz_overrides', ['quiz' => $this->get_quiz_id()], '', 'id');

        foreach ($overrides as $override) {
            $this->delete_override($override->id);
        }
    }

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

    private function check_capabilties() {
        require_capability('mod/quiz:manageoverrides', $this->quizobj->get_context());
    }

    private function get_quiz_id() {
        return $this->quizobj->get_quiz()->id;
    }

    private function log_deleted($overrideid, $userid = null, $groupid = null) {
        $params = $this->get_base_event_params();
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
