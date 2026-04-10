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
 * the methods that carry module-specific behaviour. The frozen 13-method
 * contract from local_coursectrl\local\contract\activity_adapter is fully
 * satisfied here with safe no-op defaults; only component() remains abstract
 * because it identifies the target Moodle module and has no sensible default.
 *
 * Default semantics:
 *   - is_available()           returns true (subplugin is loaded, so by
 *                              default the adapter is usable; concrete
 *                              adapters override this if they need to gate
 *                              on the presence of the wrapped module).
 *   - All other methods        return an empty array. The bulk engine,
 *                              preview manager and risk analyzer treat an
 *                              empty array as "no contribution" / "nothing
 *                              to do" rather than as an error, which makes
 *                              this base class safe to plug in for any
 *                              partially implemented adapter.
 *
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\contract;

/**
 * No-op base implementation of activity_adapter for coursectrlmod_* adapters.
 *
 * See class-level docblock for the default semantics of each method.
 */
abstract class abstract_activity_adapter implements activity_adapter
{
    /**
     * Return the Moodle component name this adapter targets.
     *
     * Concrete subclasses must implement this method and return a frankenstyle
     * component name such as 'mod_assign'.
     */
    abstract public static function component(): string;

    /**
     * Whether this adapter is currently usable on the running site.
     *
     * Default: true. Override in subclasses that need to verify the presence
     * of the wrapped activity module or other site preconditions.
     */
    public function is_available(): bool
    {
        return true;
    }

    /**
     * List of action identifiers this adapter can handle.
     *
     * Default: empty list. Override to expose actions from the canonical
     * vocabulary (shift_dates, set_dates, set_visibility, set_completion,
     * set_availability, copy_settings_from_reference, run_checks).
     *
     * @return string[]
     */
    public function get_supported_actions(): array
    {
        return [];
    }

    /**
     * Field-level metadata for adapter-specific bulk-editable fields.
     *
     * Default: empty list. Override to expose a stable field descriptor
     * map consumed by the UI and the bulk validation pipeline.
     */
    public function get_supported_fields(): array
    {
        return [];
    }

    /**
     * Return the course module instances of this component in a course.
     *
     * Default: empty list. Override in concrete adapters to enumerate
     * instances via the wrapped module's API.
     *
     * @param int   $courseid target course id
     * @param array $filters  optional filter map
     */
    public function get_instances_for_course(int $courseid, array $filters = []): array
    {
        return [];
    }

    /**
     * Return a normalised description of a single course module instance.
     *
     * Default: empty array. Override to expose a normalised descriptor.
     *
     * @param int $cmid course module id
     */
    public function describe_instance(int $cmid): array
    {
        return [];
    }

    /**
     * Validate an action payload against a set of course modules.
     *
     * Default: empty result (no errors, no warnings, no per-cmid verdicts).
     * Override in adapters that support actions, since the default would
     * silently accept any payload.
     *
     * @param string $action  action identifier
     * @param array  $payload action-specific parameters
     * @param int[]  $cmids   target course module ids
     */
    public function validate_action(string $action, array $payload, array $cmids): array
    {
        return [];
    }

    /**
     * Produce a preview of an action without applying it.
     *
     * Default: empty preview. Override to compute old/new values, conflicts
     * and warnings without writing to the database.
     *
     * @param string $action  action identifier
     * @param array  $payload action-specific parameters
     * @param int[]  $cmids   target course module ids
     */
    public function preview_action(string $action, array $payload, array $cmids): array
    {
        return [];
    }

    /**
     * Execute an action against the given course modules.
     *
     * Default: empty result. The default implementation does NOT mutate
     * any state. Override in adapters that support actions and capture a
     * snapshot via export_state() before mutating.
     *
     * @param string $action  action identifier
     * @param array  $payload action-specific parameters
     * @param int[]  $cmids   target course module ids
     * @param int    $userid  acting user id
     */
    public function execute_action(string $action, array $payload, array $cmids, int $userid): array
    {
        return [];
    }

    /**
     * Capture the rollback-relevant state of one instance.
     *
     * Default: empty snapshot. Override to capture the fields touched by
     * supported actions so that restore_state() can recreate them.
     *
     * @param int $cmid course module id
     */
    public function export_state(int $cmid): array
    {
        return [];
    }

    /**
     * Restore an instance from a previously exported snapshot.
     *
     * Default: empty result. Override to apply the snapshot back to the
     * wrapped module instance.
     *
     * @param array $state snapshot payload
     */
    public function restore_state(array $state): array
    {
        return [];
    }

    /**
     * Run module-specific consistency and sanity checks.
     *
     * Default: empty result. Override to add module-specific findings to
     * the risk and dead-end analyzer's output.
     *
     * @param int[] $cmids   target course module ids
     * @param array $profile optional check profile
     */
    public function run_checks(array $cmids, array $profile = []): array
    {
        return [];
    }

    /**
     * Return module-internal dependency hints for graph building.
     *
     * Default: empty list. Override to expose module-specific edges that
     * complement Moodle's availability tree (e.g. lesson branches).
     *
     * @param int[] $cmids target course module ids
     */
    public function get_dependency_hints(array $cmids): array
    {
        return [];
    }
}
