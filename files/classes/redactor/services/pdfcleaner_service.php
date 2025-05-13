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

namespace core_files\redactor\services;

use admin_setting_configcheckbox;
use admin_settingpage;
use core\exception\moodle_exception;

/**
 * Flattens and cleans PDFs using Ghostscript's -dSAFER flag and ps2write.
 *
 * This is intended to remove JS from PDFs.
 *
 * @see       https://ghostscript.readthedocs.io/en/latest/Use.html#dsafer
 * @see       https://ghostscript.readthedocs.io/en/latest/VectorDevices.html
 * @package   core_files
 * @copyright Matthew Hilton <matthewhilton@catalyst-au.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pdfcleaner_service extends service implements file_redactor_service_interface {

    #[\Override]
    public function redact_file_by_path(
        string $mimetype,
        string $filepath
    ): ?string {
        $outfile = make_request_directory() . '/input_redact_path';
        $command = $this->get_gs_command($filepath, $outfile);

        // Apply conversion.
        @exec($command, $output, $code);

        // Check for issues redacting.
        if ($code !== 0 || !file_exists($outfile)) {
            throw new moodle_exception(
                errorcode: 'redactor:pdfcleaner:failedprocess',
                module: 'core_files',
                debuginfo: "code: {$code}",
            );
        }

        // Success - return file.
        return $outfile;
    }

    #[\Override]
    public function redact_file_by_content(
        string $mimetype,
        string $filecontent
    ): ?string {
        // Ghostscript can only operate on paths, not content.
        // So the content is stored to a temporary path.
        $sourcefile = make_request_directory() . '/input_redact_content';
        file_put_contents($sourcefile, $filecontent);

        $destinationfile = $this->redact_file_by_path($mimetype, $sourcefile);
        if (is_null($destinationfile)) {
            return null;
        }
        return file_get_contents($destinationfile) || null;
    }

    #[\Override]
    public function is_mimetype_supported(string $mimetype): bool {
        return $mimetype == 'application/pdf'; 
    }

    #[\Override]
    public function is_enabled(): bool {
        global $CFG;
        return is_executable($CFG->pathtogs) && get_config('file_redactor_pdfcleanerenabled');
    }

    #[\Override]
    public static function add_settings(admin_settingpage $settings): void {
        $settings->add(
            new admin_setting_configcheckbox(
                name: 'file_redactor_pdfcleanerenabled',
                visiblename: get_string('redactor:pdfcleaner:enabled', 'core_files'),
                description: get_string('redactor:pdfcleaner:enabled_desc', 'core_files'),
                // This can be destructive to PDFs, so it is off by default.
                defaultsetting: 0,
            ),
        );
    }

    /**
     * Gets the ghostscript (gs) command to convert the PDF into one without JS.
     *
     * @param string $src The source path of the PDF file.
     * @param string $dst The source path of the PDF file.
     * @return string The ghostscript (gs) command to use to flatten the file
     */
    private function get_gs_command(string $src, string $dst): string {
        global $CFG;

        $gsexec = \escapeshellarg($CFG->pathtogs);
        $intermediate = get_request_storage_directory() . '/pdftemp';
        $tempdstarg = \escapeshellarg($dst);
        $tempsrcarg = \escapeshellarg($src);
        $sharedflags = '-dSAFER -dBATCH -dNOPAUSE';
        // Use a 2 stage execution pipeline via a GS intermediate language that does not support active elements like Javascript.
        return "$gsexec -sDEVICE=ps2write $sharedflags -sOutputFile=$intermediate $tempsrcarg 2>/dev/null && $gsexec -sDEVICE=pdfwrite $sharedflags -sOutputFile=$tempdstarg $intermediate 2>/dev/null";
    }
}
