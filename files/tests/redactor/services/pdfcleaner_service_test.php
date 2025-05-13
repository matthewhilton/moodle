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

use core\exception\moodle_exception;

/**
 * Tests for PDF Cleaner service.
 *
 * To use these tests, you must have Ghostscript installed.
 *
 * @package   core_files
 * @copyright Matthew Hilton <matthewhilton@catalyst-au.net>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \core_files\redactor\services\pdfcleaner_service
 */
final class pdfcleaner_service_test extends \advanced_testcase {
    /**
     * Skips test if Ghostscript is not installed or is not executable.
     *
     * @return bool true if abort/skip, else false.
     */
    private function skip_if_gs_not_installed(): bool {
        global $CFG;
        if (!is_executable($CFG->pathtogs)) {
            $this->markTestSkipped("Ghostscript not installed");
            return true;
        }
        return false;
    }

    /**
     * Tests pdfcleaner service by path.
     *
     * This test verifies that '/Javascript' and '/JS' tags are removed from a given pdf
     * when the pdfcleaner redaction service is applied.
     */
    public function test_pdfcleaner_by_path(): void {
        if ($this->skip_if_gs_not_installed()) {
            return;
        }

        $this->resetAfterTest(true);
        set_config('file_redactor_pdfcleanerenabled', true);

        $path = self::get_fixture_path('core_files', 'redactor/js.pdf');

        $output = file_get_contents($path);
        $this->assertStringContainsString('/JavaScript', $output);
        $this->assertStringContainsString('/JS', $output);

        $service = new pdfcleaner_service();
        $redactedpath = $service->redact_file_by_path('application/pdf', $path);

        $output = file_get_contents($redactedpath);
        $this->assertStringNotContainsString('/JavaScript', $output);
        $this->assertStringNotContainsString('/JS', $output);
    }

    /**
     * Tests pdfcleaner service by contents.
     *
     * This test verifies that '/Javascript' and '/JS' tags are removed from a given pdf
     * when the pdfcleaner redaction service is applied.
     */
    public function test_pdfcleaner_by_contents(): void {
        if ($this->skip_if_gs_not_installed()) {
            return;
        }

        $this->resetAfterTest(true);
        set_config('file_redactor_pdfcleanerenabled', true);

        $path = self::get_fixture_path('core_files', 'redactor/js.pdf');
        $output = file_get_contents($path);
        $this->assertStringContainsString('/JavaScript', $output);
        $this->assertStringContainsString('/JS', $output);

        $service = new pdfcleaner_service();
        $redactedoutput = $service->redact_file_by_content('application/pdf', $output);

        // Ensure the final pdf does NOT contain javascript markers, thus indicating it was flattened.
        $this->assertStringNotContainsString('/JavaScript', $redactedoutput);
        $this->assertStringNotContainsString('/JS', $redactedoutput);
    }

    /**
     * Tests pdfcleaner service correctly detects Ghostscript errors
     * and throws an exception accordingly.
     */
    public function test_pdfcleaner_exception(): void {
        if ($this->skip_if_gs_not_installed()) {
            return;
        }

        $this->resetAfterTest(true);
        set_config('file_redactor_pdfcleanerenabled', true);
        $service = new pdfcleaner_service();

        $this->expectException(moodle_exception::class);
        $this->expectExceptionMessage(get_string('redactor:pdfcleaner:failedprocess', 'core_files'));
        $service->redact_file_by_path('application/pdf', 'doesnotexist');
    }
}
