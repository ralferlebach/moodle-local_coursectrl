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
 * Each test sets the plugin's stored version to the desired oldversion
 * before calling xmldb_local_coursectrl_upgrade(), so upgrade_plugin_savepoint()
 * does not see a downgrade and every step that fires is idempotent.
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
     * Load upgrade infrastructure and the plugin upgrade function exactly once.
     *
     * @return void
     */
    public static function setUpBeforeClass(): void {
        parent::setUpBeforeClass();
        global $CFG;
        // Upgrade_plugin_savepoint() is defined in lib/upgradelib.php.
        require_once($CFG->dirroot . '/lib/upgradelib.php');
        require_once($CFG->dirroot . '/local/coursectrl/db/upgrade.php');
    }

    /**
     * Set the plugin's stored version so upgrade_plugin_savepoint() does not
     * see a downgrade when the upgrade function fires subsequent savepoints.
     *
     * @param int $oldversion Version to pretend was previously installed.
     * @return void
     */
    private function set_installed_version(int $oldversion): void {
        set_config('version', $oldversion, 'local_coursectrl');
    }

    /**
     * Upgrading from a version that has already passed all steps is a no-op.
     */
    public function test_upgrade_from_current_version_is_noop(): void {
        $this->resetAfterTest();
        // All conditions are false; upgrade returns true without touching DB.
        $this->set_installed_version(2026050300);
        $this->assertTrue(xmldb_local_coursectrl_upgrade(2026050300));
    }

    /**
     * Upgrading from 2026042952 skips the drop step (condition: < 2026042952)
     * and runs only the final no-schema step for 2026050300.
     */
    public function test_upgrade_from_2026042952_skips_drop_step(): void {
        $this->resetAfterTest();
        $this->set_installed_version(2026042952);
        $this->assertTrue(xmldb_local_coursectrl_upgrade(2026042952));
    }

    /**
     * Upgrading from 2026042963 runs only the final 2026050300 no-schema step.
     */
    public function test_upgrade_from_2026042963(): void {
        $this->resetAfterTest();
        $this->set_installed_version(2026042963);
        $this->assertTrue(xmldb_local_coursectrl_upgrade(2026042963));
    }

    /**
     * Upgrading from 2026050299 (one below the 1.0.0 savepoint) triggers only
     * the final savepoint step and must return true.
     */
    public function test_upgrade_from_2026050299(): void {
        $this->resetAfterTest();
        $this->set_installed_version(2026050299);
        $this->assertTrue(xmldb_local_coursectrl_upgrade(2026050299));
    }
}
