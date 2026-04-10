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

/**
 * Smoke tests – verify that local_coursectrl installs and loads cleanly.
 *
 * @package    local_coursectrl
 * @category   test
 * @copyright  2026 Course Control Hub Contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_coursectrl
 */

namespace local_coursectrl;

/**
 * Smoke test suite for the local_coursectrl plugin skeleton.
 */
class stub_test extends \advanced_testcase {
    /**
     * Verify the plugin version is recorded in the database after installation.
     */
    public function test_plugin_version_is_set(): void {
        $version = get_config('local_coursectrl', 'version');
        $this->assertNotEmpty($version, 'Plugin version must be stored after installation.');
    }

    /**
     * Verify that all required capabilities are defined.
     */
    public function test_capabilities_exist(): void {
        $caps = [
            'local/coursectrl:view',
            'local/coursectrl:bulkaction',
            'local/coursectrl:viewreports',
            'local/coursectrl:rollback',
            'local/coursectrl:managepresets',
            'local/coursectrl:simulate',
        ];
        foreach ($caps as $cap) {
            $this->assertTrue(
                get_capability_info($cap) !== false,
                "Capability '{$cap}' must be registered."
            );
        }
    }

    /**
     * Verify that all core database tables exist.
     */
    public function test_database_tables_exist(): void {
        global $DB;
        $tables = [
            'local_coursectrl_batch',
            'local_coursectrl_batch_item',
            'local_coursectrl_snapshot',
            'local_coursectrl_preset',
            'local_coursectrl_report',
            'local_coursectrl_text_hit',
            'local_coursectrl_risk',
        ];
        foreach ($tables as $table) {
            $this->assertTrue(
                $DB->get_manager()->table_exists($table),
                "Database table '{$table}' must exist after installation."
            );
        }
    }

    /**
     * Verify that required language strings are present.
     */
    public function test_language_strings_en(): void {
        $strings = [
            'pluginname',
            'stub_placeholder',
            'error_no_course',
            'error_no_capability',
        ];
        foreach ($strings as $key) {
            $str = get_string($key, 'local_coursectrl');
            $this->assertNotEmpty($str, "Language string '{$key}' must not be empty.");
            $this->assertStringNotContainsString('[[', $str, "Language string '{$key}' is missing.");
        }
    }
}
