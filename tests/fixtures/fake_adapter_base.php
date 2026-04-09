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
 * Base test fixture implementing the activity_adapter contract with no-ops.
 *
 * @package    local_coursectrl
 * @copyright  2026 Course Control Hub Contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * No-op base fixture satisfying the full activity_adapter contract.
 *
 * Concrete fake adapters extend this class and override only the bits
 * relevant to the specific test case.
 */
abstract class local_coursectrl_fake_adapter_base implements \local_coursectrl\local\contract\activity_adapter {
    /**
     * Reports availability. Defaults to true for the base fixture.
     *
     * @return bool
     */
    public function is_available(): bool {
        return true;
    }

    /**
     * Returns the list of supported actions. Empty by default.
     *
     * @return array
     */
    public function get_supported_actions(): array {
        return [];
    }

    /**
     * Returns the list of supported fields. Empty by default.
     *
     * @return array
     */
    public function get_supported_fields(): array {
        return [];
    }

    /**
     * Returns course module instances for a course. Empty by default.
     *
     * @param int   $courseid target course id.
     * @param array $filters  optional filter map.
     * @return array
     */
    public function get_instances_for_course(int $courseid, array $filters = []): array {
        return [];
    }

    /**
     * Returns a normalised description of a course module. Empty by default.
     *
     * @param int $cmid course module id.
     * @return array
     */
    public function describe_instance(int $cmid): array {
        return [];
    }

    /**
     * Validates an action payload. No-op by default.
     *
     * @param string $action  action identifier.
     * @param array  $payload action-specific parameters.
     * @param int[]  $cmids   target course module ids.
     * @return array
     */
    public function validate_action(string $action, array $payload, array $cmids): array {
        return [];
    }

    /**
     * Produces an action preview. No-op by default.
     *
     * @param string $action  action identifier.
     * @param array  $payload action-specific parameters.
     * @param int[]  $cmids   target course module ids.
     * @return array
     */
    public function preview_action(string $action, array $payload, array $cmids): array {
        return [];
    }

    /**
     * Executes an action. No-op by default.
     *
     * @param string $action  action identifier.
     * @param array  $payload action-specific parameters.
     * @param int[]  $cmids   target course module ids.
     * @param int    $userid  acting user id.
     * @return array
     */
    public function execute_action(string $action, array $payload, array $cmids, int $userid): array {
        return [];
    }

    /**
     * Captures snapshot state. Empty by default.
     *
     * @param int $cmid course module id.
     * @return array
     */
    public function export_state(int $cmid): array {
        return [];
    }

    /**
     * Restores snapshot state. No-op by default.
     *
     * @param array $state snapshot payload.
     * @return array
     */
    public function restore_state(array $state): array {
        return [];
    }

    /**
     * Runs module-specific checks. No-op by default.
     *
     * @param int[] $cmids   target course module ids.
     * @param array $profile optional check profile.
     * @return array
     */
    public function run_checks(array $cmids, array $profile = []): array {
        return [];
    }

    /**
     * Returns module-internal dependency hints. Empty by default.
     *
     * @param int[] $cmids target course module ids.
     * @return array
     */
    public function get_dependency_hints(array $cmids): array {
        return [];
    }
}
