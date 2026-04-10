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
 * Course Control Hub adapter for mod_assign.
 *
 * Patch-018 introduced read-only inventory plus preview for shift_dates and
 * snapshot capture via export_state. Patch-020 adds the write side:
 * execute_action() for shift_dates and restore_state() — both perform direct
 * $DB->update_record('assign', ...) writes.
 *
 * Capability gating is intentionally NOT performed inside the adapter; it
 * lives one layer up in the bulk engine / external function (Phase 4) which
 * will check 'local/coursectrl:bulkaction' before invoking execute_action()
 * or restore_state(). The adapter trusts its caller.
 *
 * Known limitation (Phase-4 follow-up): direct DB updates do NOT trigger
 * mod_assign's calendar event refresh. The bulk engine in Phase 4 must call
 * the appropriate calendar API after a successful execute_action() to keep
 * due-date calendar entries in sync.
 *
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace coursectrlmod_assign;

use local_coursectrl\local\contract\abstract_activity_adapter;

/**
 * Activity adapter wrapping mod_assign.
 */
class adapter extends abstract_activity_adapter
{
    /**
     * Returns the frankenstyle component name of the wrapped module.
     */
    public static function component(): string
    {
        return 'mod_assign';
    }

    /**
     * Whether mod_assign is installed and usable on this site.
     */
    public function is_available(): bool
    {
        $modules = \core_component::get_plugin_list('mod');

        return is_array($modules) && array_key_exists('assign', $modules);
    }

    /**
     * Actions this adapter handles.
     *
     * @return string[]
     */
    public function get_supported_actions(): array
    {
        return ['shift_dates'];
    }

    /**
     * Field descriptors for bulk-editable assign fields.
     */
    public function get_supported_fields(): array
    {
        return field_map::get_date_fields();
    }

    /**
     * Enumerate all assign instances in a course.
     *
     * @param int   $courseid target course id
     * @param array $filters  reserved for future use, currently ignored
     *
     * @return array keyed by cmid
     */
    public function get_instances_for_course(int $courseid, array $filters = []): array
    {
        $result = [];
        $cms = get_coursemodules_in_course('assign', $courseid);
        if (!is_array($cms)) {
            return $result;
        }
        foreach ($cms as $cm) {
            $result[(int) $cm->id] = [
                'cmid' => (int) $cm->id,
                'instanceid' => (int) $cm->instance,
                'name' => (string) $cm->name,
                'visible' => (bool) $cm->visible,
                'sectionid' => (int) $cm->section,
            ];
        }

        return $result;
    }

    /**
     * Return a normalised description of a single assign course module.
     *
     * @param int $cmid course module id
     */
    public function describe_instance(int $cmid): array
    {
        global $DB;
        $cm = get_coursemodule_from_id('assign', $cmid, 0, false, MUST_EXIST);
        $assign = $DB->get_record(
            'assign',
            ['id' => $cm->instance],
            'id, name, duedate, allowsubmissionsfromdate, cutoffdate, gradingduedate',
            MUST_EXIST
        );

        return [
            'cmid' => (int) $cmid,
            'component' => 'mod_assign',
            'instanceid' => (int) $cm->instance,
            'name' => (string) $assign->name,
            'visible' => (bool) $cm->visible,
            'dates' => [
                'duedate' => (int) $assign->duedate,
                'allowsubmissionsfromdate' => (int) $assign->allowsubmissionsfromdate,
                'cutoffdate' => (int) $assign->cutoffdate,
                'gradingduedate' => (int) $assign->gradingduedate,
            ],
        ];
    }

    /**
     * Validate a shift_dates payload.
     *
     * @param string $action  action identifier
     * @param array  $payload action-specific parameters
     * @param int[]  $cmids   target course module ids
     */
    public function validate_action(string $action, array $payload, array $cmids): array
    {
        if ('shift_dates' !== $action) {
            return [
                'valid' => false,
                'errors' => [['code' => 'unsupported_action', 'action' => $action]],
            ];
        }
        if (!array_key_exists('delta', $payload) || !is_numeric($payload['delta'])) {
            return [
                'valid' => false,
                'errors' => [['code' => 'invalid_delta']],
            ];
        }

        return [
            'valid' => true,
            'errors' => [],
            'cmids' => array_values(array_map('intval', $cmids)),
        ];
    }

    /**
     * Build a deterministic preview for shift_dates.
     *
     * @param string $action  action identifier
     * @param array  $payload action-specific parameters
     * @param int[]  $cmids   target course module ids
     */
    public function preview_action(string $action, array $payload, array $cmids): array
    {
        if ('shift_dates' !== $action) {
            return [];
        }
        $delta = (int) ($payload['delta'] ?? 0);
        $items = [];
        $errors = [];
        foreach ($cmids as $rawcmid) {
            $cmid = (int) $rawcmid;

            try {
                $description = $this->describe_instance($cmid);
            } catch (\Throwable $e) {
                $errors[] = [
                    'cmid' => $cmid,
                    'code' => 'describe_failed',
                    'message' => $e->getMessage(),
                ];

                continue;
            }
            $fields = [];
            foreach ($description['dates'] as $name => $oldvalue) {
                if (0 === $oldvalue) {
                    $fields[$name] = [
                        'old' => 0,
                        'new' => 0,
                        'shifted' => false,
                        'reason' => 'unset',
                    ];

                    continue;
                }
                $newvalue = $oldvalue + $delta;
                $fields[$name] = [
                    'old' => $oldvalue,
                    'new' => $newvalue,
                    'shifted' => $newvalue !== $oldvalue,
                ];
            }
            $items[] = [
                'cmid' => $cmid,
                'name' => $description['name'],
                'fields' => $fields,
            ];
        }

        return [
            'action' => 'shift_dates',
            'payload' => ['delta' => $delta],
            'items' => $items,
            'errors' => $errors,
        ];
    }

    /**
     * Execute shift_dates against the given assign course modules.
     *
     * Algorithm per cmid:
     *   1. Resolve the instance and capture a snapshot of its current dates.
     *   2. Compute new values for every shiftable field whose stored value
     *      is non-zero (zero means "unset" in mod_assign and is preserved).
     *   3. If no field would change, return status='noop' WITHOUT writing.
     *   4. Otherwise issue a single $DB->update_record('assign', ...) and
     *      bump timemodified.
     *
     * The snapshot in each item is captured BEFORE the write and is the
     * value the bulk engine should persist into local_coursectrl_snapshot
     * for rollback.
     *
     * @param string $action  action identifier
     * @param array  $payload action-specific parameters; requires 'delta'
     * @param int[]  $cmids   target course module ids
     * @param int    $userid  acting user id (currently unused inside the
     *                        adapter; the bulk engine logs it via events)
     */
    public function execute_action(string $action, array $payload, array $cmids, int $userid): array
    {
        if ('shift_dates' !== $action) {
            return [];
        }
        $validation = $this->validate_action($action, $payload, $cmids);
        if (empty($validation['valid'])) {
            return [
                'action' => $action,
                'payload' => $payload,
                'items' => [],
                'errors' => $validation['errors'],
            ];
        }
        global $DB;
        $delta = (int) $payload['delta'];
        $items = [];
        $errors = [];
        foreach ($cmids as $rawcmid) {
            $cmid = (int) $rawcmid;

            try {
                $description = $this->describe_instance($cmid);
            } catch (\Throwable $e) {
                $errors[] = [
                    'cmid' => $cmid,
                    'code' => 'describe_failed',
                    'message' => $e->getMessage(),
                ];
                $items[] = [
                    'cmid' => $cmid,
                    'status' => 'failed',
                    'message' => $e->getMessage(),
                ];

                continue;
            }
            $snapshot = [
                'component' => 'mod_assign',
                'cmid' => $cmid,
                'instanceid' => $description['instanceid'],
                'fields' => $description['dates'],
                'version' => 1,
            ];
            $update = new \stdClass();
            $update->id = $description['instanceid'];
            $changed = [];
            foreach ($description['dates'] as $name => $oldvalue) {
                if (0 === $oldvalue) {
                    continue;
                }
                $newvalue = $oldvalue + $delta;
                if ($newvalue === $oldvalue) {
                    continue;
                }
                $update->{$name} = $newvalue;
                $changed[] = $name;
            }
            if (empty($changed)) {
                $items[] = [
                    'cmid' => $cmid,
                    'status' => 'noop',
                    'snapshot' => $snapshot,
                    'changed' => [],
                ];

                continue;
            }
            $update->timemodified = time();

            try {
                $DB->update_record('assign', $update);
            } catch (\Throwable $e) {
                $errors[] = [
                    'cmid' => $cmid,
                    'code' => 'db_write_failed',
                    'message' => $e->getMessage(),
                ];
                $items[] = [
                    'cmid' => $cmid,
                    'status' => 'failed',
                    'snapshot' => $snapshot,
                    'message' => $e->getMessage(),
                ];

                continue;
            }
            $items[] = [
                'cmid' => $cmid,
                'status' => 'ok',
                'snapshot' => $snapshot,
                'changed' => $changed,
            ];
        }

        return [
            'action' => 'shift_dates',
            'payload' => ['delta' => $delta],
            'items' => $items,
            'errors' => $errors,
        ];
    }

    /**
     * Capture the rollback-relevant state of one assign instance.
     *
     * @param int $cmid course module id
     */
    public function export_state(int $cmid): array
    {
        $description = $this->describe_instance($cmid);

        return [
            'component' => 'mod_assign',
            'cmid' => (int) $cmid,
            'instanceid' => $description['instanceid'],
            'fields' => $description['dates'],
            'version' => 1,
        ];
    }

    /**
     * Restore an assign instance from a previously exported snapshot.
     *
     * Validation steps:
     *   - component must be 'mod_assign'
     *   - cmid must be set
     *   - fields must be a non-empty array containing at least one of the
     *     four shiftable date fields known to field_map
     *
     * The instance id is taken from the snapshot when present; otherwise
     * it is resolved by re-reading the course module from the cmid.
     *
     * @param array $state snapshot payload
     */
    public function restore_state(array $state): array
    {
        if (($state['component'] ?? null) !== 'mod_assign') {
            return [
                'status' => 'failed',
                'code' => 'invalid_component',
            ];
        }
        if (empty($state['cmid']) || !isset($state['fields']) || !is_array($state['fields'])) {
            return [
                'status' => 'failed',
                'code' => 'invalid_snapshot',
            ];
        }
        $update = new \stdClass();
        if (!empty($state['instanceid'])) {
            $update->id = (int) $state['instanceid'];
        } else {
            try {
                $cm = get_coursemodule_from_id('assign', (int) $state['cmid'], 0, false, MUST_EXIST);
                $update->id = (int) $cm->instance;
            } catch (\Throwable $e) {
                return [
                    'status' => 'failed',
                    'code' => 'cmid_unresolved',
                    'message' => $e->getMessage(),
                ];
            }
        }
        $allowed = field_map::get_shiftable_field_names();
        $restored = [];
        foreach ($state['fields'] as $name => $value) {
            if (!in_array($name, $allowed, true)) {
                continue;
            }
            $update->{$name} = (int) $value;
            $restored[$name] = (int) $value;
        }
        if (empty($restored)) {
            return [
                'status' => 'failed',
                'code' => 'no_restorable_fields',
            ];
        }
        $update->timemodified = time();
        global $DB;

        try {
            $DB->update_record('assign', $update);
        } catch (\Throwable $e) {
            return [
                'status' => 'failed',
                'code' => 'db_write_failed',
                'message' => $e->getMessage(),
            ];
        }

        return [
            'status' => 'ok',
            'cmid' => (int) $state['cmid'],
            'restored' => $restored,
        ];
    }
}
