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
 * Test fixtures for local_coursectrl registry tests.
 *
 * Defines minimal activity_adapter implementations that the registry test
 * can inject via the $classnameoverride constructor argument. These are
 * intentionally not placed under classes/ so that Moodle's autoloader does
 * not pick them up in production runs.
 *
 * @package    local_coursectrl
 * @copyright  2026 Course Control Hub Contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Base stub that satisfies the full activity_adapter contract with no-ops.
 */
abstract class local_coursectrl_fake_adapter_base implements \local_coursectrl\local\contract\activity_adapter {

    public function is_available(): bool {
        return true;
    }

    public function get_supported_actions(): array {
        return [];
    }

    public function get_supported_fields(): array {
        return [];
    }

    public function get_instances_for_course(int $courseid, array $filters = []): array {
        return [];
    }

    public function describe_instance(int $cmid): array {
        return [];
    }

    public function validate_action(string $action, array $payload, array $cmids): array {
        return [];
    }

    public function preview_action(string $action, array $payload, array $cmids): array {
        return [];
    }

    public function execute_action(string $action, array $payload, array $cmids, int $userid): array {
        return [];
    }

    public function export_state(int $cmid): array {
        return [];
    }

    public function restore_state(array $state): array {
        return [];
    }

    public function run_checks(array $cmids, array $profile = []): array {
        return [];
    }

    public function get_dependency_hints(array $cmids): array {
        return [];
    }
}

/**
 * Available fake adapter targeting mod_assign.
 */
class local_coursectrl_fake_adapter_assign extends local_coursectrl_fake_adapter_base {
    public static function component(): string {
        return 'mod_assign';
    }
}

/**
 * Available fake adapter targeting mod_quiz.
 */
class local_coursectrl_fake_adapter_quiz extends local_coursectrl_fake_adapter_base {
    public static function component(): string {
        return 'mod_quiz';
    }
}

/**
 * Fake adapter that reports itself as unavailable and must be skipped.
 */
class local_coursectrl_fake_adapter_unavailable extends local_coursectrl_fake_adapter_base {
    public static function component(): string {
        return 'mod_disabled';
    }

    public function is_available(): bool {
        return false;
    }
}

/**
 * Fake adapter with an empty component name; must be rejected by the registry.
 */
class local_coursectrl_fake_adapter_empty_component extends local_coursectrl_fake_adapter_base {
    public static function component(): string {
        return '';
    }
}

/**
 * Class that does NOT implement activity_adapter; must be rejected.
 */
class local_coursectrl_fake_not_an_adapter {
    public static function component(): string {
        return 'mod_bogus';
    }
}
