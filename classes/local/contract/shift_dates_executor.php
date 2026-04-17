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
 * Reusable date-action implementation for coursectrlmod_* adapters.
 *
 * Handles two actions now:
 *   - shift_dates: add a delta to each configured date field.
 *   - unset_dates: set specified date fields to 0 (unset).
 *
 * Adapters pull the trait in and supply four module-specific hooks:
 *
 *   - get_table_name(): string
 *   - get_field_map_class(): string
 *   - read_dates_from_record(\stdClass $record): array
 *   - get_record_select_fields(): string
 *
 * Behaviour preserved from earlier patches:
 *   - delta=0 or "no field would change" → status 'noop', no DB write.
 *   - stored value === 0 → "unset", left untouched on shift (reason 'unset').
 *   - snapshot is captured BEFORE the DB write.
 *   - restore_state validates component, cmid, fields and filters against
 *     the shiftable field list before writing.
 *
 * Capability gating and calendar refresh are NOT done here; they live in
 * the external function wrapper and the batch_manager respectively.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\contract;

/**
 * Trait providing shift_dates and unset_dates pipelines for date-only adapters.
 */
trait shift_dates_executor {
    /**
     * Returns the database table name this adapter operates on.
     *
     * @return string
     */
    abstract protected function get_table_name(): string;

    /**
     * Returns the fully qualified class name of the field_map.
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
     * Validate a date-action payload.
     *
     * @param string $action  action identifier.
     * @param array  $payload action-specific parameters.
     * @param int[]  $cmids   target course module ids.
     * @return array
     */
    public function validate_action(string $action, array $payload, array $cmids): array {
        if ($action === 'shift_dates') {
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
        if ($action === 'unset_dates') {
            $fields = $payload['fields'] ?? [];
            if (!is_array($fields) || empty($fields)) {
                return [
                    'valid'  => false,
                    'errors' => [['code' => 'invalid_fields']],
                ];
            }
            $allowed = $this->get_field_map_class()::get_shiftable_field_names();
            foreach ($fields as $field) {
                if (!in_array($field, $allowed, true)) {
                    return [
                        'valid'  => false,
                        'errors' => [['code' => 'unknown_field', 'field' => $field]],
                    ];
                }
            }
            return [
                'valid'  => true,
                'errors' => [],
                'cmids'  => array_values(array_map('intval', $cmids)),
            ];
        }
        return [
            'valid'  => false,
            'errors' => [['code' => 'unsupported_action', 'action' => $action]],
        ];
    }

    /**
     * Build a deterministic preview for shift_dates or unset_dates.
     *
     * @param string $action  action identifier.
     * @param array  $payload action-specific parameters.
     * @param int[]  $cmids   target course module ids.
     * @return array
     */
    public function preview_action(string $action, array $payload, array $cmids): array {
        if ($action === 'shift_dates') {
            return $this->preview_shift_dates($payload, $cmids);
        }
        if ($action === 'unset_dates') {
            return $this->preview_unset_dates($payload, $cmids);
        }
        return [];
    }

    /**
     * Execute a date action against the given course modules.
     *
     * @param string $action  action identifier.
     * @param array  $payload action-specific parameters.
     * @param int[]  $cmids   target course module ids.
     * @param int    $userid  acting user id.
     * @return array
     */
    public function execute_action(string $action, array $payload, array $cmids, int $userid): array {
        $validation = $this->validate_action($action, $payload, $cmids);
        if (empty($validation['valid'])) {
            return [
                'action'  => $action,
                'payload' => $payload,
                'items'   => [],
                'errors'  => $validation['errors'],
            ];
        }
        if ($action === 'shift_dates') {
            return $this->execute_shift_dates($payload, $cmids);
        }
        if ($action === 'unset_dates') {
            return $this->execute_unset_dates($payload, $cmids);
        }
        return [];
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

    /**
     * Build preview items for shift_dates.
     *
     * @param array $payload Payload with 'delta'.
     * @param int[] $cmids   Target cmids.
     * @return array
     */
    private function preview_shift_dates(array $payload, array $cmids): array {
        $delta = (int)($payload['delta'] ?? 0);
        $items = [];
        $errors = [];
        foreach ($cmids as $rawcmid) {
            $cmid = (int)$rawcmid;
            try {
                $description = $this->describe_instance($cmid);
            } catch (\Throwable $e) {
                $errors[] = ['cmid' => $cmid, 'code' => 'describe_failed', 'message' => $e->getMessage()];
                continue;
            }
            $fields = [];
            foreach ($description['dates'] as $name => $oldvalue) {
                if ($oldvalue === 0) {
                    $fields[$name] = ['old' => 0, 'new' => 0, 'shifted' => false, 'reason' => 'unset'];
                    continue;
                }
                $newvalue = $oldvalue + $delta;
                $fields[$name] = [
                    'old'     => $oldvalue,
                    'new'     => $newvalue,
                    'shifted' => $newvalue !== $oldvalue,
                ];
            }
            $items[] = ['cmid' => $cmid, 'name' => $description['name'], 'fields' => $fields];
        }
        return [
            'action'  => 'shift_dates',
            'payload' => ['delta' => $delta],
            'items'   => $items,
            'errors'  => $errors,
        ];
    }

    /**
     * Build preview items for unset_dates.
     *
     * @param array $payload Payload with 'fields'.
     * @param int[] $cmids   Target cmids.
     * @return array
     */
    private function preview_unset_dates(array $payload, array $cmids): array {
        $targetfields = (array)($payload['fields'] ?? []);
        $items = [];
        $errors = [];
        foreach ($cmids as $rawcmid) {
            $cmid = (int)$rawcmid;
            try {
                $description = $this->describe_instance($cmid);
            } catch (\Throwable $e) {
                $errors[] = ['cmid' => $cmid, 'code' => 'describe_failed', 'message' => $e->getMessage()];
                continue;
            }
            $fields = [];
            foreach ($description['dates'] as $name => $oldvalue) {
                if (!in_array($name, $targetfields, true)) {
                    continue;
                }
                if ($oldvalue === 0) {
                    $fields[$name] = ['old' => 0, 'new' => 0, 'shifted' => false, 'reason' => 'already_unset'];
                    continue;
                }
                $fields[$name] = ['old' => $oldvalue, 'new' => 0, 'shifted' => true];
            }
            $items[] = ['cmid' => $cmid, 'name' => $description['name'], 'fields' => $fields];
        }
        return [
            'action'  => 'unset_dates',
            'payload' => ['fields' => $targetfields],
            'items'   => $items,
            'errors'  => $errors,
        ];
    }

    /**
     * Execute shift_dates with DB writes.
     *
     * @param array $payload Payload with 'delta'.
     * @param int[] $cmids   Target cmids.
     * @return array
     */
    private function execute_shift_dates(array $payload, array $cmids): array {
        global $DB;
        $delta = (int)$payload['delta'];
        // Optional field restriction: only shift the listed fields.
        $restrictfields = isset($payload['fields']) && is_array($payload['fields'])
            ? $payload['fields']
            : [];
        $table = $this->get_table_name();
        $items = [];
        $errors = [];
        foreach ($cmids as $rawcmid) {
            $cmid = (int)$rawcmid;
            try {
                $description = $this->describe_instance($cmid);
            } catch (\Throwable $e) {
                $errors[] = ['cmid' => $cmid, 'code' => 'describe_failed', 'message' => $e->getMessage()];
                $items[] = ['cmid' => $cmid, 'status' => 'failed', 'message' => $e->getMessage()];
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
                // Skip field if a restriction list is active and this field is not in it.
                if (!empty($restrictfields) && !in_array($name, $restrictfields, true)) {
                    continue;
                }
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
                $items[] = ['cmid' => $cmid, 'status' => 'noop', 'snapshot' => $snapshot, 'changed' => []];
                continue;
            }
            $update->timemodified = time();
            try {
                $DB->update_record($table, $update);
            } catch (\Throwable $e) {
                $errors[] = ['cmid' => $cmid, 'code' => 'db_write_failed', 'message' => $e->getMessage()];
                $items[] = [
                    'cmid' => $cmid, 'status' => 'failed',
                    'snapshot' => $snapshot, 'message' => $e->getMessage(),
                ];
                continue;
            }
            $items[] = ['cmid' => $cmid, 'status' => 'ok', 'snapshot' => $snapshot, 'changed' => $changed];
        }

        // Shift completionexpected only when the adapter's completion anchor
        // field was actually changed. For adapters without an anchor (returns null)
        // we always shift; for adapters with an anchor we only shift if that field
        // appears in the changed list of at least one successfully shifted CM.
        $anchor = method_exists($this, 'get_completion_anchor_field')
            ? $this->get_completion_anchor_field()
            : null;
        $shouldshiftcompletion = false;
        if ($anchor === null) {
            $shouldshiftcompletion = !empty($cmids);
        } else {
            foreach ($items as $item) {
                if (($item['status'] ?? '') === 'ok'
                    && in_array($anchor, $item['changed'] ?? [], true)) {
                    $shouldshiftcompletion = true;
                    break;
                }
            }
        }
        if ($shouldshiftcompletion) {
            // Collect only the cmids where the shift succeeded.
            $successcmids = [];
            foreach ($items as $item) {
                if (($item['status'] ?? '') === 'ok') {
                    $successcmids[] = (int) $item['cmid'];
                }
            }
            $this->shift_completionexpected($successcmids, $delta);
        }
        return [
            'action'  => 'shift_dates',
            'payload' => ['delta' => $delta],
            'items'   => $items,
            'errors'  => $errors,
        ];
    }

    /**
     * Shift the completionexpected field in course_modules for the given cmids.
     *
     * Only shifts cmids where completionexpected > 0 (0 means "not set").
     *
     * @param int[] $cmids Target course module ids.
     * @param int   $delta Seconds to shift.
     * @return void
     */
    private function shift_completionexpected(array $cmids, int $delta): void {
        global $DB;
        if (empty($cmids) || $delta === 0) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal(
            array_map('intval', $cmids),
            SQL_PARAMS_NAMED
        );
        $params['delta'] = $delta;
        $DB->execute(
            "UPDATE {course_modules}
                SET completionexpected = completionexpected + :delta
              WHERE id $insql
                AND completionexpected > 0",
            $params
        );
    }

    /**
     * Execute unset_dates with DB writes.
     *
     * @param array $payload Payload with 'fields'.
     * @param int[] $cmids   Target cmids.
     * @return array
     */
    private function execute_unset_dates(array $payload, array $cmids): array {
        global $DB;
        $targetfields = (array)$payload['fields'];
        $table = $this->get_table_name();
        $items = [];
        $errors = [];
        foreach ($cmids as $rawcmid) {
            $cmid = (int)$rawcmid;
            try {
                $description = $this->describe_instance($cmid);
            } catch (\Throwable $e) {
                $errors[] = ['cmid' => $cmid, 'code' => 'describe_failed', 'message' => $e->getMessage()];
                $items[] = ['cmid' => $cmid, 'status' => 'failed', 'message' => $e->getMessage()];
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
                if (!in_array($name, $targetfields, true)) {
                    continue;
                }
                if ($oldvalue === 0) {
                    continue;
                }
                $update->$name = 0;
                $changed[] = $name;
            }
            if (empty($changed)) {
                $items[] = ['cmid' => $cmid, 'status' => 'noop', 'snapshot' => $snapshot, 'changed' => []];
                continue;
            }
            $update->timemodified = time();
            try {
                $DB->update_record($table, $update);
            } catch (\Throwable $e) {
                $errors[] = ['cmid' => $cmid, 'code' => 'db_write_failed', 'message' => $e->getMessage()];
                $items[] = [
                    'cmid' => $cmid, 'status' => 'failed',
                    'snapshot' => $snapshot, 'message' => $e->getMessage(),
                ];
                continue;
            }
            $items[] = ['cmid' => $cmid, 'status' => 'ok', 'snapshot' => $snapshot, 'changed' => $changed];
        }
        return [
            'action'  => 'unset_dates',
            'payload' => ['fields' => $targetfields],
            'items'   => $items,
            'errors'  => $errors,
        ];
    }
}
