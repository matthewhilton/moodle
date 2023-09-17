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

namespace core\check\external;

use core\check\manager;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use context_system;
use invalid_parameter_exception;

/**
 * Webservice to get result of a given check.
 *
 * @package    core
 * @category   check
 * @copyright  2023 Matthew Hilton <matthewhilton@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_result extends external_api {
    /**
     * Defines parameters
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'reference' => new external_value(PARAM_TEXT, 'Reference of check to get result for'),
            'includedetails' => new external_value(PARAM_BOOL, 'If the details should be included in the response.
                Depending on the check, details could be slower to return.', VALUE_DEFAULT, false),
            'includehtml' => new external_value(PARAM_BOOL, 'If the check should render the html and return it',
                VALUE_DEFAULT, false),
        ]);
    }

    /**
     * Gets the result of the check and returns it.
     * @param string $reference Reference to a specific check. See check::get_ref
     * @param bool $includedetails If the details should be included in the response.
     * Depending on the check, details could be slower to return.
     * @param bool $includehtml If the check should be rendered, and the HTML be returned.
     * @return array returned data
     */
    public static function execute(string $reference, bool $includedetails, bool $includehtml): array {
        global $OUTPUT;

        // Context and capability checks.
        $context = context_system::instance();
        self::validate_context($context);
        require_capability('moodle/site:getcheckresult', $context);

        // Find the check and execute it to get the result.
        $check = manager::get_check($reference);

        if (empty($check)) {
            throw new invalid_parameter_exception("Check {$reference} does not exist.");
        }

        $result = $check->get_result();

        // Build the return data. By defallt only status and summary are returned.
        $data = [
            'status' => s($result->get_status()),
            'summary' => s($result->get_summary()),
        ];

        // Since details might be slower to obtain, we allow this to be optionally returned.
        if ($includedetails) {
            $data['details'] = s($result->get_details());
        }

        // Since HTML might not be needed, we allow this to be optionally returned.
        if ($includehtml) {
            $data['html'] = s($OUTPUT->check_full_result($result, $includedetails));
        }

        return $data;
    }

    /**
     * Defines return structure.
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_TEXT, 'Result status constant'),
            'summary' => new external_value(PARAM_TEXT, 'Summary of result'),
            'html' => new external_value(PARAM_TEXT, 'Rendered full html result', VALUE_OPTIONAL),
            'details' => new external_value(PARAM_TEXT, 'Details of result (if includedetails was enabled)', VALUE_OPTIONAL),
        ]);
    }
}

