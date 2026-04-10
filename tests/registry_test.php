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
 * Unit tests for the activity adapter registry.
 *
 * @package    local_coursectrl
 * @copyright  2026 Course Control Hub Contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl;

use local_coursectrl\manager\registry;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/fake_adapter_base.php');
require_once(__DIR__ . '/fixtures/fake_adapter_assign.php');
require_once(__DIR__ . '/fixtures/fake_adapter_quiz.php');
require_once(__DIR__ . '/fixtures/fake_adapter_unavailable.php');
require_once(__DIR__ . '/fixtures/fake_adapter_empty_component.php');
require_once(__DIR__ . '/fixtures/fake_not_an_adapter.php');

/**
 * Unit tests for the activity adapter registry.
 *
 * @covers \local_coursectrl\manager\registry
 */
final class registry_test extends \advanced_testcase {
    /**
     * A registry constructed with an empty override must load cleanly and
     * report zero adapters.
     */
    public function test_empty_override_yields_empty_registry(): void {
        $registry = new registry([]);
        $this->assertSame(0, $registry->count());
        $this->assertSame([], $registry->get_all());
        $this->assertFalse($registry->has('mod_assign'));
        $this->assertNull($registry->get_for_component('mod_assign'));
    }

    /**
     * Two valid fake adapters must be registered and keyed by component.
     */
    public function test_registers_valid_adapters(): void {
        $registry = new registry([
            \local_coursectrl_fake_adapter_assign::class,
            \local_coursectrl_fake_adapter_quiz::class,
        ]);

        $this->assertSame(2, $registry->count());
        $this->assertTrue($registry->has('mod_assign'));
        $this->assertTrue($registry->has('mod_quiz'));

        $assign = $registry->get_for_component('mod_assign');
        $this->assertNotNull($assign);
        $this->assertInstanceOf(\local_coursectrl\local\contract\activity_adapter::class, $assign);
        $this->assertSame('mod_assign', $assign::component());

        $all = $registry->get_all();
        $this->assertArrayHasKey('mod_assign', $all);
        $this->assertArrayHasKey('mod_quiz', $all);
    }

    /**
     * A non-existent class name is silently skipped (developer debugging is
     * emitted and swallowed by assertDebuggingCalled).
     */
    public function test_skips_missing_class(): void {
        $registry = new registry([
            'local_coursectrl_nonexistent_adapter_class',
            \local_coursectrl_fake_adapter_assign::class,
        ]);
        $this->assertSame(1, $registry->count());
        $this->assertTrue($registry->has('mod_assign'));
        $this->assertDebuggingCalled();
    }

    /**
     * A class that does not implement activity_adapter is rejected.
     */
    public function test_skips_class_not_implementing_interface(): void {
        $registry = new registry([
            \local_coursectrl_fake_not_an_adapter::class,
            \local_coursectrl_fake_adapter_assign::class,
        ]);
        $this->assertSame(1, $registry->count());
        $this->assertFalse($registry->has('mod_bogus'));
        $this->assertTrue($registry->has('mod_assign'));
        $this->assertDebuggingCalled();
    }

    /**
     * An adapter reporting is_available() === false must not be registered,
     * and must do so silently (no debugging message).
     */
    public function test_skips_unavailable_adapter(): void {
        $registry = new registry([
            \local_coursectrl_fake_adapter_unavailable::class,
            \local_coursectrl_fake_adapter_assign::class,
        ]);
        $this->assertSame(1, $registry->count());
        $this->assertFalse($registry->has('mod_disabled'));
        $this->assertTrue($registry->has('mod_assign'));
    }

    /**
     * An adapter returning an empty component string must be rejected.
     */
    public function test_skips_adapter_with_empty_component(): void {
        $registry = new registry([
            \local_coursectrl_fake_adapter_empty_component::class,
            \local_coursectrl_fake_adapter_assign::class,
        ]);
        $this->assertSame(1, $registry->count());
        $this->assertTrue($registry->has('mod_assign'));
        $this->assertDebuggingCalled();
    }

    /**
     * get_for_cmid() must resolve the adapter via the target course module's
     * modname, and must return null for non-existent course modules.
     */
    public function test_get_for_cmid_resolves_via_modname(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);

        $registry = new registry([
            \local_coursectrl_fake_adapter_assign::class,
        ]);

        $resolved = $registry->get_for_cmid((int)$assign->cmid);
        $this->assertNotNull($resolved);
        $this->assertSame('mod_assign', $resolved::component());

        $this->assertNull($registry->get_for_cmid(999999));
    }
}
