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
 * Standardised adapter contract for coursectrlmod_* subplugins.
 *
 * Every activity adapter shipped under local/coursectrl/mod/* must implement
 * this interface. The signatures defined here are binding for the lifetime
 * of the 0.x series; additive changes (such as the patch-026 addition of
 * refresh_calendar_for_cmids) are allowed and require a default no-op
 * implementation in abstract_activity_adapter so existing subplugins
 * continue to work without modification.
 *
 * The interface is derived from the Pflichtenheft / Lastenheft, section
 * "Standardisierte Adapter-Schnittstelle", and backs the following functional
 * requirements:
 *
 *   F1  Kursinventar                -> get_instances_for_course, describe_instance
 *   F3  Vorschau                    -> validate_action, preview_action
 *   F4  Terminänderung              -> execute_action (shift_dates, set_dates)
 *   F8  Lernenden-Simulation        -> get_dependency_hints
 *   F9  Konsistenz-/Sackgassenanalyse -> run_checks
 *   F10 Audit / Rollback            -> export_state, restore_state
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\contract;

/**
 * Contract for a Course Control Hub activity adapter.
 *
 * Implementations live in local/coursectrl/mod/{modname}/classes/adapter.php
 * and are registered via the coursectrlmod subplugin type (see
 * local/coursectrl/db/subplugins.json).
 */
interface activity_adapter {
    /**
     * Return the Moodle component name this adapter targets.
     *
     * Must match the frankenstyle name of the core activity module the
     * adapter wraps, e.g. 'mod_assign', 'mod_quiz', 'mod_feedback'.
     *
     * @return string frankenstyle component name of the target module.
     */
    public static function component(): string;

    /**
     * Whether this adapter is currently usable on the running site.
     *
     * Typical checks: the target module is installed, required capabilities
     * are resolvable, and no blocking Moodle version mismatch exists.
     *
     * @return bool true if the adapter may be used, false otherwise.
     */
    public function is_available(): bool;

    /**
     * List of action identifiers this adapter can handle.
     *
     * Values are drawn from the canonical action vocabulary defined in the
     * Pflichtenheft, e.g. 'shift_dates', 'set_dates', 'set_visibility',
     * 'set_completion', 'set_availability', 'copy_settings_from_reference',
     * 'run_checks'. Adapters may expose a subset.
     *
     * @return string[] list of supported action identifiers.
     */
    public function get_supported_actions(): array;

    /**
     * Field-level metadata for adapter-specific bulk-editable fields.
     *
     * Used by the UI layer to render selectors and by the bulk engine to
     * validate payloads. The structure is adapter-defined but must be
     * stable across calls for the same component.
     *
     * @return array field descriptor list.
     */
    public function get_supported_fields(): array;

    /**
     * Return the course module instances of this component in a course.
     *
     * @param int   $courseid target course id.
     * @param array $filters  optional filter map (section, visibility, ...).
     * @return array list of instance descriptors keyed by cmid.
     */
    public function get_instances_for_course(int $courseid, array $filters = []): array;

    /**
     * Return a normalised description of a single course module instance.
     *
     * @param int $cmid course module id.
     * @return array normalised instance description.
     */
    public function describe_instance(int $cmid): array;

    /**
     * Validate an action payload against a set of course modules.
     *
     * Must not mutate any state. Returns a structured validation result
     * containing errors, warnings and per-cmid verdicts.
     *
     * @param string $action  action identifier.
     * @param array  $payload action-specific parameters.
     * @param int[]  $cmids   target course module ids.
     * @return array validation result.
     */
    public function validate_action(string $action, array $payload, array $cmids): array;

    /**
     * Produce a preview of an action without applying it.
     *
     * Must be deterministic and must not write to the database. The return
     * value feeds the preview UI (old values, new values, conflicts,
     * warnings, unprocessable items).
     *
     * @param string $action  action identifier.
     * @param array  $payload action-specific parameters.
     * @param int[]  $cmids   target course module ids.
     * @return array preview result.
     */
    public function preview_action(string $action, array $payload, array $cmids): array;

    /**
     * Execute an action against the given course modules.
     *
     * Implementations must honour snapshots and return a structured
     * execution result per cmid. Rollback-relevant state must be captured
     * via export_state() before mutation.
     *
     * @param string $action  action identifier.
     * @param array  $payload action-specific parameters.
     * @param int[]  $cmids   target course module ids.
     * @param int    $userid  acting user id for audit purposes.
     * @return array execution result.
     */
    public function execute_action(string $action, array $payload, array $cmids, int $userid): array;

    /**
     * Capture the rollback-relevant state of one instance.
     *
     * The returned array is stored in local_coursectrl_snapshot.statejson
     * and must be sufficient for restore_state() to recreate the pre-change
     * values of every field touched by supported actions.
     *
     * @param int $cmid course module id.
     * @return array snapshot payload.
     */
    public function export_state(int $cmid): array;

    /**
     * Restore an instance from a previously exported snapshot.
     *
     * @param array $state snapshot payload as produced by export_state().
     * @return array restore result.
     */
    public function restore_state(array $state): array;

    /**
     * Run module-specific consistency and sanity checks.
     *
     * Used by the risk / dead-end analyzer to augment the core checks with
     * module-specific knowledge (e.g. quiz attempts open, assignment
     * cutoff before due date).
     *
     * @param int[] $cmids   target course module ids.
     * @param array $profile optional check profile.
     * @return array check result.
     */
    public function run_checks(array $cmids, array $profile = []): array;

    /**
     * Return module-internal dependency hints for graph building.
     *
     * Complements Moodle's availability tree with module-specific edges
     * (e.g. lesson branches, workshop phases, H5P sub-activities).
     *
     * @param int[] $cmids target course module ids.
     * @return array dependency hint list.
     */
    public function get_dependency_hints(array $cmids): array;

    /**
     * Refresh calendar events for the affected course modules.
     *
     * Called by the bulk engine after a successful execute_action() call
     * to keep Moodle calendar entries in sync with the mutated date fields.
     * The default implementation in abstract_activity_adapter is a no-op,
     * so existing subplugins continue to work without modification.
     *
     * Adapters that perform direct $DB->update_record() writes (rather
     * than going through the wrapped module's high-level API) MUST
     * override this method and delegate to the module's calendar refresh
     * function (e.g. assign_refresh_events, quiz_refresh_events).
     *
     * @param int[] $cmids course module ids whose calendar events should
     *                     be refreshed.
     * @return void
     */
    public function refresh_calendar_for_cmids(array $cmids): void;
}
