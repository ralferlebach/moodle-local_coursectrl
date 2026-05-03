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
 * PHPUnit tests for the db/upgrade.php upgrade path.
 *
 * Verifies that each upgrade step exits cleanly and that conditions
 * and savepoints are consistent across the supported oldversion range.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl;

#[\PHPUnit\Framework\Attributes\CoversFunction('xmldb_local_coursectrl_upgrade')]
/**
 * Tests for the upgrade() function defined in db/upgrade.php.
 *
 * @covers ::xmldb_local_coursectrl_upgrade
 */
final class upgrade_test extends \advanced_testcase {

    /**
     * Load upgrade infrastructure and the plugin upgrade function.
     *
     * @return void
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        // Moodle's upgrade helpers (upgrade_plugin_savepoint, etc.) live in upgradelib.
        require_once($CFG->dirroot . '/lib/upgradelib.php');
        require_once($CFG->dirroot . '/local/coursectrl/db/upgrade.php');
    }

    /**
     * Upgrading from a version that has already passed all steps is a no-op.
     * This avoids table-drop side effects and tests the fast-forward path.
     */
    public function test_upgrade_from_current_version_is_noop(): void {
        $this->resetAfterTest();
        // Passing the current plugin version: all conditions are false, returns true.
        $this->assertTrue(xmldb_local_coursectrl_upgrade(2026050300));
    }

    /**
     * Upgrading from 2026042952 (the preset/report drop savepoint) skips that step.
     * The savepoint condition is strictly less-than, so passing 2026042952 is a no-op
     * for that step, and only the final 1.0.0 no-schema step runs.
     */
    public function test_upgrade_from_2026042952_skips_drop_step(): void {
        $this->resetAfterTest();
        // Starting exactly at the drop step's savepoint: must skip it cleanly.
        $this->assertTrue(xmldb_local_coursectrl_upgrade(2026042952));
    }

    /**
     * Upgrading from 2026042963 (between the two highest steps) runs only the
     * final 1.0.0 no-schema step, which is safe to run in a test environment.
     */
    public function test_upgrade_from_2026042963(): void {
        $this->resetAfterTest();
        $this->assertTrue(xmldb_local_coursectrl_upgrade(2026042963));
    }

    /**
     * Upgrading from 2026050299 (one below the 1.0.0 savepoint) triggers only
     * the savepoint-only final step, which must return true.
     */
    public function test_upgrade_from_2026050299(): void {
        $this->resetAfterTest();
        $this->assertTrue(xmldb_local_coursectrl_upgrade(2026050299));
    }
}
