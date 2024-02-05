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

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use Exception;
use mod_quiz\override_manager;

/**
 * Webservice for searching overrides.
 *
 * @package   mod_quiz
 * @copyright 2024 Matthew Hilton <matthewhilton@catalyst-au.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_overrides extends external_api {
    /**
     * Defines parameters
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'quizzes' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'ID of quiz to get overrides for'),
                ]),
            ),
        ]);
    }

    /**
     * Executes function
     * @param array $quizzes array of quizzes to get overrides for
     * @return array of [id, error], where id is the id of the deleted override, and error is any errors encountered.
     */
    public static function execute($quizzes) {
        $params = self::validate_parameters(self::execute_parameters(), ['quizzes' => $quizzes]);

        return array_map(function($override) {
            try {
                $manager = override_manager::create_from_quiz($override['id']);
                self::validate_context($manager->get_context());

                $overrides = $manager->get_all_overrides();
                $overrideobjects = array_map(fn($o) => $o->to_record(), $overrides);
                return ['data' => $overrideobjects, 'error' => null];
            } catch (Exception $e) {
                return ['data' => [], 'error' => $e->getMessage()];
            }
        }, $params['quizzes']);
    }

    /**
     * Defines return type
     * @return external_multiple_structure
     */
    public static function execute_returns() {
        $overridedatastructure = new external_single_structure([
            'id' => new external_value(PARAM_INT, 'Override ID'),
            'quiz' => new external_value(PARAM_INT, 'Quiz ID'),
            'userid' => new external_value(PARAM_INT, 'User ID', VALUE_DEFAULT, null),
            'groupid' => new external_value(PARAM_INT, 'Group ID', VALUE_DEFAULT, null),
            'timeopen' => new external_value(PARAM_INT, 'Override time open value', VALUE_DEFAULT, null),
            'timeclose' => new external_value(PARAM_INT, 'Override time close value', VALUE_DEFAULT, null),
            'timelimit' => new external_value(PARAM_INT, 'Override time limit value', VALUE_DEFAULT, null),
            'attempts' => new external_value(PARAM_INT, 'Override attempts value', VALUE_DEFAULT, null),
            'password' => new external_value(PARAM_TEXT, 'Override password', VALUE_DEFAULT, null),
        ]);

        $returnstruct = new external_single_structure([
            'data' => new external_multiple_structure($overridedatastructure),
            'error' => new external_value(PARAM_TEXT, 'Error message, if there was an error. Null if success.',
                VALUE_DEFAULT, null),
        ]);

        return new external_multiple_structure($returnstruct);
    }
}
