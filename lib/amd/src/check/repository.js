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

/**
 * Check API webservice repository
 *
 * @module     core/check
 * @copyright  2023 Matthew Hilton <matthewhilton@catalyst-au.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {call as fetchMany} from 'core/ajax';

/**
 * Call check_get_result webservice function
 *
 * @param {String} reference Unique reference to check.
 * @param {Boolean} includeDetails If details should be included in the response
 * @param {Boolean} includeHtml If the rendered result HTML should be included in the response.
 */
export const getCheckResult = (reference, includeDetails, includeHtml) => fetchMany([{
    methodname: 'core_check_get_result',
    args: {
        reference,
        includedetails: includeDetails,
        includehtml: includeHtml
    },
}])[0];

