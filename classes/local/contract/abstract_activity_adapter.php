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
 * Default base implementation of the activity_adapter contract.
 *
 * Concrete coursectrlmod_* subplugins extend this class and override only
 * the methods that carry module-specific behaviour. The 14-method contract
 * from local_coursectrl\local\contract\activity_adapter is fully satisfied
 * here with safe no-op defaults; only component() remains abstract because
 * it identifies the target Moodle module and has no sensible default.
 *
 * Default semantics:
 *   - is_available()             returns true.
 *   - All array-returning methods return an empty array.
 *   - refresh_calendar_for_cmids does nothing (added in patch-026).
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\contract;

/**
 * No-op base implementation of activity_adapter for coursectrlmod_* adapters.
 */
abstract class abstract_activity_adapter implements activity_adapter {
    /**
     * Return the Moodle component name this adapter targets.
     *
     * @return string
     */
    abstract public static function component(): string;

    /**
     * Whether this adapter is currently usable on the running site.
     *
     * @return bool
     */
    public function is_available(): bool {
        return true;
    }

    /**
     * List of action identifiers this adapter can handle.
     *
     * @return string[]
     */
    public function get_supported_actions(): array {
        return [];
    }

    /**
     * Field-level metadata for adapter-specific bulk-editable fields.
     *
     * @return array
     */
    public function get_supported_fields(): array {
        return [];
    }

    /**
     * Return the course module instances of this component in a course.
     *
     * @param int   $courseid target course id.
     * @param array $filters  optional filter map.
     * @return array
     */
    public function get_instances_for_course(int $courseid, array $filters = []): array {
        return [];
    }

    /**
     * Return a normalised description of a single course module instance.
     *
     * @param int $cmid course module id.
     * @return array
     */
    public function describe_instance(int $cmid): array {
        return [];
    }

    /**
     * Validate an action payload against a set of course modules.
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
     * Produce a preview of an action without applying it.
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
     * Execute an action against the given course modules.
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
     * Capture the rollback-relevant state of one instance.
     *
     * @param int $cmid course module id.
     * @return array
     */
    public function export_state(int $cmid): array {
        return [];
    }

    /**
     * Restore an instance from a previously exported snapshot.
     *
     * @param array $state snapshot payload.
     * @return array
     */
    public function restore_state(array $state): array {
        return [];
    }

    /**
     * Run module-specific consistency and sanity checks.
     *
     * @param int[] $cmids   target course module ids.
     * @param array $profile optional check profile.
     * @return array
     */
    public function run_checks(array $cmids, array $profile = []): array {
        return [];
    }

    /**
     * Return module-internal dependency hints for graph building.
     *
     * @param int[] $cmids target course module ids.
     * @return array
     */
    public function get_dependency_hints(array $cmids): array {
        return [];
    }

    /**
     * Refresh calendar events for the affected course modules.
     *
     * Default: no-op. Adapters that perform direct DB writes must override
     * this method and delegate to the wrapped module's calendar refresh
     * function (e.g. assign_refresh_events, quiz_refresh_events).
     *
     * @param int[] $cmids course module ids.
     * @return void
     */
    public function refresh_calendar_for_cmids(array $cmids): void {
        // No-op by default; adapters that mutate state via direct DB writes
        // override this method.
    }
}
