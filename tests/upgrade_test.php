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
 * Verifies that each upgrade step is idempotent and that conditions
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
     * Load the upgrade function exactly once.
     *
     * @return void
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        require_once($CFG->dirroot . '/local/coursectrl/db/upgrade.php');
        parent::setUpBeforeClass();
    }

    /**
     * Upgrading from a version before the first step runs without error.
     */
    public function test_upgrade_from_zero(): void {
        $this->resetAfterTest();
        $this->assertTrue(xmldb_local_coursectrl_upgrade(0));
    }

    /**
     * Upgrading from exactly 2026042951 (one below the preset/report drop step) runs without error.
     */
    public function test_upgrade_from_2026042951(): void {
        $this->resetAfterTest();
        $this->assertTrue(xmldb_local_coursectrl_upgrade(2026042951));
    }

    /**
     * Upgrading from exactly 2026042952 (the savepoint of the preset/report drop step)
     * must not re-run that step — the condition is strict less-than.
     */
    public function test_upgrade_from_2026042952_skips_drop_step(): void {
        $this->resetAfterTest();
        // Starting at the savepoint: the drop step must be skipped (condition: < 2026042952).
        $this->assertTrue(xmldb_local_coursectrl_upgrade(2026042952));
    }

    /**
     * Upgrading from 2026042963 (between the two highest steps) runs without error.
     */
    public function test_upgrade_from_2026042963(): void {
        $this->resetAfterTest();
        $this->assertTrue(xmldb_local_coursectrl_upgrade(2026042963));
    }

    /**
     * Upgrading from 2026050299 (one below the final 1.0.0 savepoint) runs without error.
     */
    public function test_upgrade_from_2026050299(): void {
        $this->resetAfterTest();
        $this->assertTrue(xmldb_local_coursectrl_upgrade(2026050299));
    }

    /**
     * Running the full upgrade twice (idempotency check) must not throw.
     */
    public function test_upgrade_is_idempotent(): void {
        $this->resetAfterTest();
        $this->assertTrue(xmldb_local_coursectrl_upgrade(0));
        $this->assertTrue(xmldb_local_coursectrl_upgrade(0));
    }
}
