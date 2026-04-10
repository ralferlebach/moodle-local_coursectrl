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
 * Contract tests for the activity_adapter interface.
 *
 * These tests freeze the shape of the adapter contract so that any
 * accidental renaming, removal or signature change of a method is caught
 * by CI before it can land on main. The Pflichtenheft lists exactly 13
 * methods on this interface; the test enforces that number.
 *
 * @package    local_coursectrl
 * @copyright  2026 Course Control Hub Contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl;

/**
 * Reflection-based contract test for activity_adapter.
 *
 * @coversNothing
 */
final class activity_adapter_contract_test extends \advanced_testcase {
    /** Fully qualified interface name. */
    private const IFACE = 'local_coursectrl\\local\\contract\\activity_adapter';

    /**
     * The interface file and class must be present and loadable.
     */
    public function test_interface_exists(): void {
        $this->assertTrue(
            interface_exists(self::IFACE),
            'Interface local_coursectrl\\local\\contract\\activity_adapter must exist.'
        );
    }

    /**
     * The interface must define exactly the 13 methods specified in the
     * Pflichtenheft. Both under- and over-definition fail the build.
     */
    public function test_interface_method_count(): void {
        $ref = new \ReflectionClass(self::IFACE);
        $this->assertCount(
            13,
            $ref->getMethods(),
            'activity_adapter must expose exactly 13 methods per the Pflichtenheft.'
        );
    }

    /**
     * Every method named in the Pflichtenheft must be present with the
     * correct name, parameter count and return type. Signatures are frozen.
     */
    public function test_interface_method_signatures(): void {
        $expected = [
            'component' => ['params' => 0, 'return' => 'string', 'static' => true],
            'is_available' => ['params' => 0, 'return' => 'bool', 'static' => false],
            'get_supported_actions' => ['params' => 0, 'return' => 'array', 'static' => false],
            'get_supported_fields' => ['params' => 0, 'return' => 'array', 'static' => false],
            'get_instances_for_course' => ['params' => 2, 'return' => 'array', 'static' => false],
            'describe_instance' => ['params' => 1, 'return' => 'array', 'static' => false],
            'validate_action' => ['params' => 3, 'return' => 'array', 'static' => false],
            'preview_action' => ['params' => 3, 'return' => 'array', 'static' => false],
            'execute_action' => ['params' => 4, 'return' => 'array', 'static' => false],
            'export_state' => ['params' => 1, 'return' => 'array', 'static' => false],
            'restore_state' => ['params' => 1, 'return' => 'array', 'static' => false],
            'run_checks' => ['params' => 2, 'return' => 'array', 'static' => false],
            'get_dependency_hints' => ['params' => 1, 'return' => 'array', 'static' => false],
        ];

        $ref = new \ReflectionClass(self::IFACE);
        foreach ($expected as $name => $spec) {
            $this->assertTrue(
                $ref->hasMethod($name),
                "activity_adapter must declare method {$name}()."
            );
            $method = $ref->getMethod($name);
            $this->assertSame(
                $spec['static'],
                $method->isStatic(),
                "Method {$name}() has unexpected static modifier."
            );
            $this->assertSame(
                $spec['params'],
                $method->getNumberOfParameters(),
                "Method {$name}() has unexpected parameter count."
            );
            $returntype = $method->getReturnType();
            $this->assertNotNull(
                $returntype,
                "Method {$name}() must declare a return type."
            );
            $this->assertSame(
                $spec['return'],
                (string)$returntype,
                "Method {$name}() has unexpected return type."
            );
        }
    }
}
