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
 * Skeleton tests for batch_manager.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\manager;

/**
 * Verifies the patch-023 batch_manager skeleton: registry-based DI works
 * and execute() throws coding_exception until patch-025 implements the body.
 *
 * @covers \local_coursectrl\manager\batch_manager
 */
final class batch_manager_test extends \advanced_testcase {
    /**
     * The constructor accepts an injected registry and exposes it.
     */
    public function test_constructor_accepts_injected_registry(): void {
        $registry = new registry([]);
        $manager = new batch_manager($registry);
        $this->assertSame($registry, $manager->get_registry());
    }

    /**
     * Calling execute() throws a coding_exception in patch-023.
     */
    public function test_execute_throws_until_implemented(): void {
        $manager = new batch_manager(new registry([]));
        $this->expectException(\coding_exception::class);
        $manager->execute(1, 'shift_dates', ['delta' => 86400], [], 0);
    }
}
