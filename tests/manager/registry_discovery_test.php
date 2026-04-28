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
 * Integration tests for registry auto-discovery.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\manager;

#[\PHPUnit\Framework\Attributes\CoversClass(\local_coursectrl\manager\registry::class)]
/**
 * Verifies that the registry picks up real coursectrlmod_* subplugins
 * shipped in this repository through core_plugin_manager, without any
 * dependency-injection override. This complements registry_test which
 * exercises the override path with synthetic fixtures.
 *
 * @covers \local_coursectrl\manager\registry
 */
final class registry_discovery_test extends \advanced_testcase {
    /**
     * Live discovery must register the coursectrlmod_assign subplugin.
     * @covers \local_coursectrl\manager\registry
     */
    public function test_auto_discovery_finds_assign_subplugin(): void {
        $registry = new registry();
        $this->assertTrue(
            $registry->has('mod_assign'),
            'Live registry must discover coursectrlmod_assign and register it under mod_assign.'
        );
        $adapter = $registry->get_for_component('mod_assign');
        $this->assertNotNull($adapter);
        $this->assertSame('mod_assign', $adapter::component());
    }

    /**
     * Live discovery must register the coursectrlmod_quiz subplugin.
     * @covers \local_coursectrl\manager\registry
     */
    public function test_auto_discovery_finds_quiz_subplugin(): void {
        $registry = new registry();
        $this->assertTrue(
            $registry->has('mod_quiz'),
            'Live registry must discover coursectrlmod_quiz and register it under mod_quiz.'
        );
        $adapter = $registry->get_for_component('mod_quiz');
        $this->assertNotNull($adapter);
        $this->assertSame('mod_quiz', $adapter::component());
    }

    /**
     * Live discovery must register the coursectrlmod_feedback subplugin.
     * @covers \local_coursectrl\manager\registry
     */
    public function test_auto_discovery_finds_feedback_subplugin(): void {
        $registry = new registry();
        $this->assertTrue(
            $registry->has('mod_feedback'),
            'Live registry must discover coursectrlmod_feedback and register it under mod_feedback.'
        );
        $adapter = $registry->get_for_component('mod_feedback');
        $this->assertNotNull($adapter);
        $this->assertSame('mod_feedback', $adapter::component());
    }

    /**
     * The live registry must contain at least the assign adapter.
     * @covers \local_coursectrl\manager\registry
     */
    public function test_auto_discovery_count_at_least_one(): void {
        $registry = new registry();
        $this->assertGreaterThanOrEqual(1, $registry->count());
    }
}
