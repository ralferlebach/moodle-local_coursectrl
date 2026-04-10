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
 * Reusable shift_dates implementation for coursectrlmod_* adapters.
 *
 * Extracts the validate / preview / execute / export / restore logic shared
 * by the assign, quiz and feedback adapters into a single trait. Adapters
 * pull the trait in and supply four module-specific hooks:
 *
 *   - get_table_name(): string
 *       e.g. 'assign', 'quiz', 'feedback'.
 *   - get_field_map_class(): string
 *       fully qualified class name of the field_map providing
 *       get_date_fields() and get_shiftable_field_names().
 *   - read_dates_from_record(\stdClass $record): array
 *       maps a {table} row to a ['fieldname' => int, ...] map of the
 *       date fields the adapter cares about.
 *   - get_record_select_fields(): string
 *       comma-separated SQL SELECT clause used by describe_instance().
 *
 * The trait is intentionally a trait and not an intermediate base class
 * so adapters that need a non-shift_dates pipeline (e.g. lesson, workshop)
 * can simply leave the trait out without forcing a parallel hierarchy.
 *
 * Behaviour preserved verbatim from the per-adapter implementations
 * shipped in patches 018 / 020 / 021 / 022:
 *   - delta=0 or "no field would change" → status 'noop', no DB write
 *   - stored value === 0 → "unset", left untouched (reason 'unset')
 *   - snapshot is captured BEFORE the DB write
 *   - restore_state validates the component, the cmid, the fields and
 *     filters them against get_shiftable_field_names() before writing
 *
 * Capability gating remains the responsibility of the caller (the bulk
 * engine in Phase 4); the trait does not check any capability.
 *
 * Calendar event refresh after a successful shift is also NOT done in
 * the trait. The bulk engine in Phase 4 will issue a centralised
 * refresh after each successful execute_action call.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\contract;

/**
 * Trait providing the standard shift_dates pipeline for date-only adapters.
 */
trait shift_dates_executor {
    /**
     * Returns the database table name this adapter operates on.
     *
     * @return string
     */
    abstract protected function get_table_name(): string;

    /**
     * Returns the fully qualified class name of the field_map providing
     * get_date_fields() and get_shiftable_field_names().
     *
     * @return string
     */
    abstract protected function get_field_map_class(): string;

    /**
     * Map a database record to a flat array of date fields.
     *
     * @param \stdClass $record raw {table} record.
     * @return array<string, int>
     */
    abstract protected function read_dates_from_record(\stdClass $record): array;

    /**
     * Returns the comma-separated SELECT clause used by describe_instance().
     *
     * @return string
     */
    abstract protected function get_record_select_fields(): string;

    /**
     * Validate a shift_dates payload.
     *
     * @param string $action  action identifier.
     * @param array  $payload action-specific parameters.
     * @param int[]  $cmids   target course module ids.
     * @return array
     */
    public function validate_action(string $action, array $payload, array $cmids): array {
        if ($action !== 'shift_dates') {
            return [
                'valid'  => false,
                'errors' => [['code' => 'unsupported_action', 'action' => $action]],
            ];
        }
        if (!array_key_exists('delta', $payload) || !is_numeric($payload['delta'])) {
            return [
                'valid'  => false,
                'errors' => [['code' => 'invalid_delta']],
            ];
        }
        return [
            'valid'  => true,
            'errors' => [],
            'cmids'  => array_values(array_map('intval', $cmids)),
        ];
    }

    /**
     * Build a deterministic preview for shift_dates.
     *
     * @param string $action  action identifier.
     * @param array  $payload action-specific parameters.
     * @param int[]  $cmids   target course module ids.
     * @return array
     */
    public function preview_action(string $action, array $payload, array $cmids): array {
        if ($action !== 'shift_dates') {
            return [];
        }
        $delta = (int)($payload['delta'] ?? 0);
        $items  = [];
        $errors = [];
        foreach ($cmids as $rawcmid) {
            $cmid = (int)$rawcmid;
            try {
                $description = $this->describe_instance($cmid);
            } catch (\Throwable $e) {
                $errors[] = [
                    'cmid'    => $cmid,
                    'code'    => 'describe_failed',
                    'message' => $e->getMessage(),
                ];
                continue;
            }
            $fields = [];
            foreach ($description['dates'] as $name => $oldvalue) {
                if ($oldvalue === 0) {
                    $fields[$name] = [
                        'old'     => 0,
                        'new'     => 0,
                        'shifted' => false,
                        'reason'  => 'unset',
                    ];
                    continue;
                }
                $newvalue = $oldvalue + $delta;
                $fields[$name] = [
                    'old'     => $oldvalue,
                    'new'     => $newvalue,
                    'shifted' => $newvalue !== $oldvalue,
                ];
            }
            $items[] = [
                'cmid'   => $cmid,
                'name'   => $description['name'],
                'fields' => $fields,
            ];
        }
        return [
            'action'  => 'shift_dates',
            'payload' => ['delta' => $delta],
            'items'   => $items,
            'errors'  => $errors,
        ];
    }

    /**
     * Execute shift_dates against the given course modules.
     *
     * @param string $action  action identifier.
     * @param array  $payload action-specific parameters; requires 'delta'.
     * @param int[]  $cmids   target course module ids.
     * @param int    $userid  acting user id.
     * @return array
     */
    public function execute_action(string $action, array $payload, array $cmids, int $userid): array {
        if ($action !== 'shift_dates') {
            return [];
        }
        $validation = $this->validate_action($action, $payload, $cmids);
        if (empty($validation['valid'])) {
            return [
                'action'  => $action,
                'payload' => $payload,
                'items'   => [],
                'errors'  => $validation['errors'],
            ];
        }
        global $DB;
        $delta  = (int)$payload['delta'];
        $table  = $this->get_table_name();
        $items  = [];
        $errors = [];
        foreach ($cmids as $rawcmid) {
            $cmid = (int)$rawcmid;
            try {
                $description = $this->describe_instance($cmid);
            } catch (\Throwable $e) {
                $errors[] = [
                    'cmid'    => $cmid,
                    'code'    => 'describe_failed',
                    'message' => $e->getMessage(),
                ];
                $items[] = [
                    'cmid'    => $cmid,
                    'status'  => 'failed',
                    'message' => $e->getMessage(),
                ];
                continue;
            }
            $snapshot = [
                'component'  => static::component(),
                'cmid'       => $cmid,
                'instanceid' => $description['instanceid'],
                'fields'     => $description['dates'],
                'version'    => 1,
            ];
            $update = new \stdClass();
            $update->id = $description['instanceid'];
            $changed = [];
            foreach ($description['dates'] as $name => $oldvalue) {
                if ($oldvalue === 0) {
                    continue;
                }
                $newvalue = $oldvalue + $delta;
                if ($newvalue === $oldvalue) {
                    continue;
                }
                $update->$name = $newvalue;
                $changed[] = $name;
            }
            if (empty($changed)) {
                $items[] = [
                    'cmid'     => $cmid,
                    'status'   => 'noop',
                    'snapshot' => $snapshot,
                    'changed'  => [],
                ];
                continue;
            }
            $update->timemodified = time();
            try {
                $DB->update_record($table, $update);
            } catch (\Throwable $e) {
                $errors[] = [
                    'cmid'    => $cmid,
                    'code'    => 'db_write_failed',
                    'message' => $e->getMessage(),
                ];
                $items[] = [
                    'cmid'     => $cmid,
                    'status'   => 'failed',
                    'snapshot' => $snapshot,
                    'message'  => $e->getMessage(),
                ];
                continue;
            }
            $items[] = [
                'cmid'     => $cmid,
                'status'   => 'ok',
                'snapshot' => $snapshot,
                'changed'  => $changed,
            ];
        }
        return [
            'action'  => 'shift_dates',
            'payload' => ['delta' => $delta],
            'items'   => $items,
            'errors'  => $errors,
        ];
    }

    /**
     * Capture the rollback-relevant state of one instance.
     *
     * @param int $cmid course module id.
     * @return array
     */
    public function export_state(int $cmid): array {
        $description = $this->describe_instance($cmid);
        return [
            'component'  => static::component(),
            'cmid'       => (int)$cmid,
            'instanceid' => $description['instanceid'],
            'fields'     => $description['dates'],
            'version'    => 1,
        ];
    }

    /**
     * Restore an instance from a previously exported snapshot.
     *
     * @param array $state snapshot payload.
     * @return array
     */
    public function restore_state(array $state): array {
        if (($state['component'] ?? null) !== static::component()) {
            return [
                'status' => 'failed',
                'code'   => 'invalid_component',
            ];
        }
        if (empty($state['cmid']) || !isset($state['fields']) || !is_array($state['fields'])) {
            return [
                'status' => 'failed',
                'code'   => 'invalid_snapshot',
            ];
        }
        $update = new \stdClass();
        if (!empty($state['instanceid'])) {
            $update->id = (int)$state['instanceid'];
        } else {
            try {
                $modname = substr(static::component(), strlen('mod_'));
                $cm = get_coursemodule_from_id($modname, (int)$state['cmid'], 0, false, MUST_EXIST);
                $update->id = (int)$cm->instance;
            } catch (\Throwable $e) {
                return [
                    'status'  => 'failed',
                    'code'    => 'cmid_unresolved',
                    'message' => $e->getMessage(),
                ];
            }
        }
        $fieldmapclass = $this->get_field_map_class();
        $allowed = $fieldmapclass::get_shiftable_field_names();
        $restored = [];
        foreach ($state['fields'] as $name => $value) {
            if (!in_array($name, $allowed, true)) {
                continue;
            }
            $update->$name = (int)$value;
            $restored[$name] = (int)$value;
        }
        if (empty($restored)) {
            return [
                'status' => 'failed',
                'code'   => 'no_restorable_fields',
            ];
        }
        $update->timemodified = time();
        global $DB;
        try {
            $DB->update_record($this->get_table_name(), $update);
        } catch (\Throwable $e) {
            return [
                'status'  => 'failed',
                'code'    => 'db_write_failed',
                'message' => $e->getMessage(),
            ];
        }
        return [
            'status'   => 'ok',
            'cmid'     => (int)$state['cmid'],
            'restored' => $restored,
        ];
    }
}
