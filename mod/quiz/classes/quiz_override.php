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

use core\persistent;
use core_user;
use lang_string;

/**
 * Quiz override persistent
 *
 * @package   mod_quiz
 * @copyright 2024 Matthew Hilton <matthewhilton@catalyst-au.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class quiz_override extends persistent {

    /** @var array quiz setting keys **/
    const KEYS = ['timeopen', 'timeclose', 'timelimit', 'attempts', 'password'];

    /** @var string Database table **/
    const TABLE = 'quiz_overrides';

    /**
     * Defines properties
     * @return array
     */
    protected static function define_properties(): array {
        return [
            'quiz' => [
                'type' => PARAM_INT,
            ],
            'groupid' => [
                'type' => PARAM_INT,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'userid' => [
                'type' => PARAM_INT,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'timeopen' => [
                'type' => PARAM_INT,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'timeclose' => [
                'type' => PARAM_INT,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'timelimit' => [
                'type' => PARAM_INT,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'attempts' => [
                'type' => PARAM_INT,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
            'password' => [
                'type' => PARAM_TEXT,
                'default' => null,
                'null' => NULL_ALLOWED,
            ],
        ];
    }

    /**
     * Validates the overall quiz
     * @param int $value
     * @return lang_string|true
     */
    protected function validate_id($value) {
        // This isn't specifically checking the ID, but is more of a general 'whole persistent' check,
        // that checks at least one of KEYS is set.
        if (!$this->is_at_least_1_key_set()) {
            return new lang_string('quiz_override:mustchangesetting', 'quiz');
        }

        return true;
    }

    /**
     * Checks that at least one of KEYS is set.
     * @return bool
     */
    private function is_at_least_1_key_set() {
        $keysthatareset = array_map(fn($key) => !empty($this->get($key)), self::KEYS);
        return in_array(true, $keysthatareset);
    }

    /**
     * Validates the quiz id value
     * @param int $value
     * @return lang_string|true
     */
    protected function validate_quiz($value) {
        // Ensure it is a valid quiz.
        if (empty(get_coursemodule_from_instance('quiz', $value))) {
            return new lang_string('quiz_override:invalidquiz', 'quiz');
        }

        return true;
    }

    /**
     * Validates the group id value
     * @param int $value
     * @return lang_string|true
     */
    protected function validate_groupid($value) {
        global $DB;

        // Ensure either this value OR groupid is set. At least 1 must be set.
        if (empty($value) && empty($this->get('userid'))) {
            return new lang_string('quiz_override:mustsetuserorgroup', 'quiz');
        }

        // If not set, do not try and validate.
        if (empty($value)) {
            return true;
        }

        // Ensure userid is also not set.
        if (!empty($value) && !empty($this->get('userid'))) {
            return new lang_string('quiz_override:cannotsetbothgroupanduser', 'quiz');
        }

        // Ensure group is valid.
        if (empty(groups_get_group($value))) {
            return new lang_string('quiz_override:invalidgroup', 'quiz');
        }

        // Ensure if a value is currently set, that this new value matches (changing is not allowed).
        if (!empty($this->get('groupid')) && $this->get('groupid') != $value) {
            return new lang_string('quiz_override:cannotchange', 'quiz');
        }

        // Ensure if updating existing, that the value set does not change.
        if (!empty($this->get('id'))) {
            $existingdbvalue = $DB->get_field(self::TABLE, 'groupid', ['id' => $this->get('id')]);

            if ($existingdbvalue != $value) {
                return new lang_string('quiz_override:cannotchange', 'quiz');
            }
        }

        // Ensure that an override does not exist for this group in this quiz (excluding self).
        $existingrecords = $DB->get_records(self::TABLE, ['quiz' => $this->get('quiz'), 'groupid' => $value]);
        $existingrecords = array_filter($existingrecords, fn($record) => $record->id != $this->get('id'));

        if (!empty($existingrecords)) {
            return new lang_string('quiz_override:multipleforgroup', 'quiz');
        }

        return true;
    }

    /**
     * Validates the user id value
     * @param int $value
     * @return lang_string|true
     */
    protected function validate_userid($value) {
        global $DB;

        // Ensure either this value OR groupid is set. At least 1 must be set.
        if (empty($value) && empty($this->get('groupid'))) {
            return new lang_string('quiz_override:mustsetuserorgroup', 'quiz');
        }

        // If not set, do not try and validate.
        if (empty($value)) {
            return true;
        }

        // Ensure groupid is also not set.
        if (!empty($value) && !empty($this->get('groupid'))) {
            return new lang_string('quiz_override:cannotsetbothgroupanduser', 'quiz');
        }

        // Ensure user is valid.
        if (!core_user::is_real_user($value, true)) {
            return new lang_string('quiz_override:invaliduser', 'quiz');
        }

        // Ensure if updating existing, that the value set does not change.
        if (!empty($this->get('id'))) {
            $existingdbvalue = $DB->get_field(self::TABLE, 'userid', ['id' => $this->get('id')]);

            if ($existingdbvalue != $value) {
                return new lang_string('quiz_override:cannotchange', 'quiz');
            }
        }

        // Ensure that an override does not exist for this user in this quiz (excluding self).
        $existingrecords = $DB->get_records(self::TABLE, ['quiz' => $this->get('quiz'), 'userid' => $value]);
        $existingrecords = array_filter($existingrecords, fn($record) => $record->id != $this->get('id'));

        if (!empty($existingrecords)) {
            return new lang_string('quiz_override:multipleforuser', 'quiz');
        }

        return true;
    }

    /**
     * Validates the timeclose value
     * @param int $value
     * @return lang_string|true
     */
    protected function validate_timeclose($value) {
        // If not set, do not try and validate.
        if (empty($value)) {
            return true;
        }

        // Ensure is after timeopen, if set.
        if (!empty($this->get('timeopen')) && $value <= $this->get('timeopen')) {
            return new lang_string('quiz_override:timeclosebeforetimeopen', 'quiz');
        }

        return true;
    }

    /**
     * Validates the attempts value
     * @param int $value
     * @return lang_string|true
     */
    protected function validate_attempts($value) {
        // If not set, do not try and validate.
        if (empty($value)) {
            return true;
        }

        if ($value < 0) {
            return new lang_string('quiz_override:invalidattempts', 'quiz');
        }

        return true;
    }

    /**
     * Validates the timelimit value
     * @param int $value
     * @return lang_string|true
     */
    protected function validate_timelimit($value) {
        // If not set, do not try and validate.
        if (empty($value)) {
            return true;
        }

        if ($value < 0) {
            return new lang_string('quiz_override:invalidtimelimit', 'quiz');
        }

        return true;
    }

    /**
     * Sets any of the quiz override keys that are empty (such as 0 or empty string) to null.
     * They must be null if not set for the quiz override to be applied properly.
     */
    private function set_empty_values_as_null() {
        foreach (self::KEYS as $key) {
            if (empty($this->get($key))) {
                $this->set($key, null);
            }
        }
    }

    /**
     * Callback for before update
     */
    protected function before_update() {
        $this->set_empty_values_as_null();
    }

    /**
     * Callback for before creating
     */
    protected function before_create() {
        $this->set_empty_values_as_null();
    }
}
