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
 * Webservice for deleting quiz overrides.
 *
 * @package   mod_quiz
 * @copyright 2024 Matthew Hilton <matthewhilton@catalyst-au.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class delete_overrides extends external_api {
    /**
     * Defines parameters
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'overrides' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'ID of override to delete'),
                ]),
            ),
        ]);
    }

    /**
     * Executes function
     * @param array $overrides array of override parameters
     * @return array of [id, error], where id is the id of the deleted override, and error is any errors encountered.
     */
    public static function execute($overrides) {
        $params = self::validate_parameters(self::execute_parameters(), ['overrides' => $overrides]);

        return array_map(function($override) {
            try {
                $manager = override_manager::create_from_override($override['id']);
                self::validate_context($manager->get_context());

                $manager->delete_override($override['id']);
                return ['id' => $override['id'], 'error' => null];
            } catch (Exception $e) {
                return ['id' => $override['id'], 'error' => $e->getMessage()];
            }
        }, $params['overrides']);
    }

    /**
     * Defines return type
     * @return external_multiple_structure
     */
    public static function execute_returns() {
        return new external_multiple_structure(
            new external_single_structure([
                'id' => new external_value(PARAM_INT, 'Id of the override that was passed to be deleted.', VALUE_DEFAULT, null),
                'error' => new external_value(PARAM_TEXT, 'Error message, if there was an error. Null if success.', VALUE_DEFAULT,
                    null),
            ])
        );
    }
}
