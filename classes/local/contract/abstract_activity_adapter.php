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
 * the methods that carry module-specific behaviour. The full contract from
 * local_coursectrl\local\contract\activity_adapter is satisfied here with
 * safe no-op defaults; only component() remains abstract because it
 * identifies the target Moodle module and has no sensible default.
 *
 * In addition to the interface, this base class adds describe_instances()
 * as a bulk counterpart to describe_instance(). It is not part of the
 * activity_adapter interface (which intentionally pins its surface area)
 * but is available to every adapter that extends this class.
 *
 * Default semantics:
 *   - is_available()               returns true.
 *   - All array-returning methods   return an empty array.
 *   - describe_instances()         delegates to describe_instance() per cmid.
 *   - refresh_calendar_for_cmids() is a no-op.
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
     * Return normalised descriptions for a set of course modules.
     *
     * Default implementation delegates to describe_instance() per cmid. The
     * shift_dates_executor trait overrides this with a single-query bulk read.
     * Entries that fail to describe are silently omitted.
     *
     * @param int[] $cmids course module ids.
     * @return array<int, array> descriptions keyed by cmid.
     */
    public function describe_instances(array $cmids): array {
        $result = [];
        foreach ($cmids as $rawcmid) {
            $cmid = (int) $rawcmid;
            try {
                $description = $this->describe_instance($cmid);
            } catch (\Throwable $e) {
                continue;
            }
            if (!empty($description)) {
                $result[$cmid] = $description;
            }
        }
        return $result;
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
     * Return the field name that drives completionexpected shifts.
     *
     * When a shift_dates action runs, completionexpected in course_modules
     * is only shifted if this anchor field was among the fields actually
     * changed. Returning null means completionexpected is always shifted
     * alongside any date shift.
     *
     * Override in adapters that have a well-defined primary deadline field
     * (e.g. 'duedate' for mod_assign, 'timeclose' for mod_quiz).
     *
     * @return string|null Field name or null.
     */
    public function get_completion_anchor_field(): ?string {
        return null;
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
     * Default is a no-op. Adapters that perform direct DB writes must
     * override this and delegate to the wrapped module's refresh function
     * (e.g. assign_refresh_events, quiz_refresh_events).
     *
     * @param int[] $cmids course module ids.
     * @return void
     */
    public function refresh_calendar_for_cmids(array $cmids): void {
    }

    /**
     * Resolve the distinct course ids that contain the given cmids.
     *
     * Utility for refresh_calendar_for_cmids() implementations in concrete
     * adapters. Performs at most one DB query regardless of cmid count.
     *
     * @param int[]  $cmids   Course module ids.
     * @param string $modname Module name (unused, kept for signature stability).
     * @return int[] Distinct course ids.
     */
    protected function collect_courseids_for_cmids(array $cmids, string $modname): array {
        global $DB;
        $cmids = array_values(array_unique(array_map('intval', $cmids)));
        if (empty($cmids)) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED);
        $rows = $DB->get_records_select(
            'course_modules',
            "id {$insql}",
            $params,
            '',
            'id, course'
        );
        $courseids = [];
        foreach ($rows as $row) {
            $courseids[(int) $row->course] = true;
        }
        return array_keys($courseids);
    }
}
