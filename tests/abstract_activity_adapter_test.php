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
 * Unit tests for abstract_activity_adapter.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl;

use local_coursectrl\local\contract\abstract_activity_adapter;
use local_coursectrl\local\contract\activity_adapter;

/**
 * Tests that the production base class implements the frozen contract,
 * keeps component() abstract and supplies the agreed no-op defaults for
 * the remaining 12 methods.
 *
 * @covers \local_coursectrl\local\contract\abstract_activity_adapter
 */
final class abstract_activity_adapter_test extends \advanced_testcase {
    /**
     * Build a minimal concrete subclass of the abstract base via an
     * anonymous class. Only component() is implemented; every other
     * method falls through to the no-op defaults under test.
     *
     * @return abstract_activity_adapter
     */
    private function make_concrete_adapter(): abstract_activity_adapter {
        return new class extends abstract_activity_adapter {
            /**
             * Returns a fixed component name for the in-test subclass.
             *
             * @return string
             */
            public static function component(): string {
                return 'mod_assign';
            }
        };
    }

    /**
     * The base class must implement the frozen activity_adapter interface.
     */
    public function test_implements_contract(): void {
        $this->assertTrue(
            is_subclass_of(abstract_activity_adapter::class, activity_adapter::class, true),
            'abstract_activity_adapter must implement activity_adapter.'
        );
    }

    /**
     * The base class must remain abstract and component() must stay abstract.
     */
    public function test_class_and_component_are_abstract(): void {
        $reflection = new \ReflectionClass(abstract_activity_adapter::class);
        $this->assertTrue($reflection->isAbstract(), 'abstract_activity_adapter must be abstract.');
        $component = $reflection->getMethod('component');
        $this->assertTrue($component->isAbstract(), 'component() must remain abstract.');
        $this->assertTrue($component->isStatic(), 'component() must remain static.');
    }

    /**
     * Default is_available() returns true.
     */
    public function test_default_is_available_returns_true(): void {
        $adapter = $this->make_concrete_adapter();
        $this->assertTrue($adapter->is_available());
    }

    /**
     * All array-returning default methods must return an empty array.
     */
    public function test_all_default_methods_return_empty_arrays(): void {
        $adapter = $this->make_concrete_adapter();
        $this->assertSame([], $adapter->get_supported_actions());
        $this->assertSame([], $adapter->get_supported_fields());
        $this->assertSame([], $adapter->get_instances_for_course(42));
        $this->assertSame([], $adapter->get_instances_for_course(42, ['section' => 1]));
        $this->assertSame([], $adapter->describe_instance(123));
        $this->assertSame([], $adapter->validate_action('shift_dates', ['delta' => 86400], [1, 2]));
        $this->assertSame([], $adapter->preview_action('shift_dates', ['delta' => 86400], [1, 2]));
        $this->assertSame([], $adapter->execute_action('shift_dates', ['delta' => 86400], [1, 2], 7));
        $this->assertSame([], $adapter->export_state(123));
        $this->assertSame([], $adapter->restore_state(['cmid' => 123, 'duedate' => 0]));
        $this->assertSame([], $adapter->run_checks([1, 2, 3]));
        $this->assertSame([], $adapter->run_checks([1, 2, 3], ['profile' => 'strict']));
        $this->assertSame([], $adapter->get_dependency_hints([1, 2, 3]));
    }

    /**
     * The concrete subclass returns its declared component name.
     */
    public function test_concrete_subclass_returns_component(): void {
        $adapter = $this->make_concrete_adapter();
        $this->assertSame('mod_assign', $adapter::component());
    }

    /**
     * The legacy non-namespaced fixture base must inherit from the new
     * production abstract base, so that all existing fake adapters benefit
     * from the centralised no-op defaults.
     */
    public function test_fixture_base_extends_production_abstract(): void {
        require_once(__DIR__ . '/fixtures/fake_adapter_base.php');
        $this->assertTrue(
            is_subclass_of(\local_coursectrl_fake_adapter_base::class, abstract_activity_adapter::class, true),
            'fake_adapter_base must extend abstract_activity_adapter since patch-017.'
        );
    }
}
