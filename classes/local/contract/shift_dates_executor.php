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
 * Supports two actions:
 *   - shift_dates:  add a delta to each configured date field.
 *   - unset_dates:  set specified date fields to 0 (unset).
 *
 * Adapters pull the trait in and supply four module-specific hooks:
 *
 *   - get_table_name(): string
 *   - get_field_map_class(): string
 *   - read_dates_from_record(\stdClass $record): array<string,int>
 *   - get_record_select_fields(): string
 *
 * Trait contract:
 *   - delta === 0 or no field would change  -> status 'noop', no DB write.
 *   - Stored value === 0 ("unset")          -> field left untouched on shift.
 *   - The rollback snapshot is captured BEFORE the DB write.
 *   - restore_state() validates component, cmid and fields against the
 *     shiftable field list before writing.
 *   - describe_instances() issues at most two SELECTs per call (cm lookup +
 *     module table), so execute_* and preview_* never generate per-cmid DB
 *     lookups.
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
            $delta = (int) ($payload['delta'] ?? 0);
            return $this->build_action_result(
                'shift_dates',
                ['delta' => $delta],
                $cmids,
                fn (string $field, int $old): array => $this->preview_shift_field($old, $delta),
                false
            );
        }
        if ($action === 'unset_dates') {
            $targetfields = (array) ($payload['fields'] ?? []);
            return $this->build_action_result(
                'unset_dates',
                ['fields' => $targetfields],
                $cmids,
                fn (string $field, int $old): array => $this->preview_unset_field($field, $old, $targetfields),
                false
            );
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
            'cmid'       => (int) $cmid,
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
        $statefields = $state['fields'];
        $update = new \stdClass();
        if (!empty($state['instanceid'])) {
            $update->id = (int) $state['instanceid'];
        } else {
            try {
                $modname = substr(static::component(), strlen('mod_'));
                $cm = get_coursemodule_from_id($modname, (int) $state['cmid'], 0, false, MUST_EXIST);
                $update->id = (int) $cm->instance;
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
        foreach ($statefields as $name => $value) {
            if (!in_array($name, $allowed, true)) {
                continue;
            }
            $update->$name = (int) $value;
            $restored[$name] = (int) $value;
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
            'cmid'     => (int) $state['cmid'],
            'restored' => $restored,
        ];
    }

    /**
     * Bulk describe a set of cmids in exactly two SELECTs.
     *
     * Uses one course_modules join to resolve cmid -> instanceid, then one
     * get_records_list against the adapter's backing table. Cmids that
     * cannot be resolved are silently omitted.
     *
     * @param int[] $cmids course module ids.
     * @return array<int, array> descriptions keyed by cmid.
     */
    public function describe_instances(array $cmids): array {
        global $DB;
        $cmids = array_values(array_unique(array_map('intval', $cmids)));
        if (empty($cmids)) {
            return [];
        }
        $modname = substr(static::component(), strlen('mod_'));

        // Resolve cmid -> {instanceid, visible, courseid} in one query.
        $cminfo = $this->load_cm_info_for_cmids($cmids, $modname);
        if (empty($cminfo)) {
            return [];
        }
        $instanceids = [];
        foreach ($cminfo as $info) {
            $instanceids[] = (int) $info['instanceid'];
        }

        $records = $DB->get_records_list(
            $this->get_table_name(),
            'id',
            $instanceids,
            '',
            $this->get_record_select_fields()
        );
        $result = [];
        foreach ($cminfo as $cmid => $info) {
            $instanceid = (int) $info['instanceid'];
            if (!isset($records[$instanceid])) {
                continue;
            }
            $record = $records[$instanceid];
            $result[(int) $cmid] = [
                'cmid'       => (int) $cmid,
                'component'  => static::component(),
                'instanceid' => $instanceid,
                'name'       => isset($record->name) ? (string) $record->name : '',
                'visible'    => (bool) $info['visible'],
                'dates'      => $this->read_dates_from_record($record),
            ];
        }
        return $result;
    }

    /**
     * Resolve cmid -> basic cm info (instanceid, visible, courseid).
     *
     * Filters cmids against the expected module type so stale ids from
     * other modules are dropped.
     *
     * @param int[]  $cmids   Course module ids.
     * @param string $modname Expected module name.
     * @return array<int, array{instanceid:int, visible:bool, courseid:int}>
     */
    private function load_cm_info_for_cmids(array $cmids, string $modname): array {
        global $DB;
        [$insql, $params] = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED);
        $params['modname'] = $modname;
        $sql = "SELECT cm.id, cm.instance, cm.visible, cm.course
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                 WHERE cm.id {$insql}
                   AND m.name = :modname";
        $rows = $DB->get_records_sql($sql, $params);
        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row->id] = [
                'instanceid' => (int) $row->instance,
                'visible'    => (bool) $row->visible,
                'courseid'   => (int) $row->course,
            ];
        }
        return $result;
    }

    /**
     * Execute shift_dates with DB writes.
     *
     * @param array $payload Payload with 'delta' and optional 'fields' restriction.
     * @param int[] $cmids   Target cmids.
     * @return array
     */
    private function execute_shift_dates(array $payload, array $cmids): array {
        $delta = (int) $payload['delta'];
        $restrictfields = (isset($payload['fields']) && is_array($payload['fields']))
            ? $payload['fields']
            : [];

        $result = $this->build_action_result(
            'shift_dates',
            ['delta' => $delta],
            $cmids,
            function (string $field, int $old) use ($delta, $restrictfields): array {
                if (!empty($restrictfields) && !in_array($field, $restrictfields, true)) {
                    return ['skip' => true];
                }
                if ($old === 0) {
                    return ['skip' => true];
                }
                $new = $old + $delta;
                if ($new === $old) {
                    return ['skip' => true];
                }
                return ['new' => $new];
            },
            true
        );

        // Shift completionexpected for cmids whose anchor field was actually changed.
        // When no anchor is defined, shift for every successful cmid.
        $anchor = method_exists($this, 'get_completion_anchor_field')
            ? $this->get_completion_anchor_field()
            : null;
        $successcmids = [];
        foreach ($result['items'] as $item) {
            if (($item['status'] ?? '') !== 'ok') {
                continue;
            }
            if ($anchor === null || in_array($anchor, $item['changed'] ?? [], true)) {
                $successcmids[] = (int) $item['cmid'];
            }
        }
        if (!empty($successcmids) && $delta !== 0) {
            $this->shift_completionexpected($successcmids, $delta);
        }
        return $result;
    }

    /**
     * Execute unset_dates with DB writes.
     *
     * @param array $payload Payload with 'fields'.
     * @param int[] $cmids   Target cmids.
     * @return array
     */
    private function execute_unset_dates(array $payload, array $cmids): array {
        $targetfields = (array) $payload['fields'];
        return $this->build_action_result(
            'unset_dates',
            ['fields' => $targetfields],
            $cmids,
            function (string $field, int $old) use ($targetfields): array {
                if (!in_array($field, $targetfields, true)) {
                    return ['skip' => true];
                }
                if ($old === 0) {
                    return ['skip' => true];
                }
                return ['new' => 0];
            },
            true
        );
    }

    /**
     * Shared pipeline for preview_* and execute_* methods.
     *
     * Bulk-describes all cmids, then iterates over the in-memory descriptions.
     * The field-level decision is delegated to $fielddecider, which receives
     * field name and old value and returns one of:
     *   - ['skip' => true]  field is left untouched for this cmid
     *   - ['new' => int]    field should be updated to the given value
     *
     * When $doupdate is true, accumulated changes are persisted via one
     * update_record() call per cmid with changes. When false, the result is
     * a preview without any DB write.
     *
     * @param string   $action        Action identifier for the result envelope.
     * @param array    $payload       Echoed back on the result envelope.
     * @param int[]    $cmids         Target cmids.
     * @param callable $fielddecider  function(string $field, int $old): array.
     * @param bool     $doupdate      Whether to persist changes to the DB.
     * @return array action result with 'action', 'payload', 'items', 'errors'.
     */
    private function build_action_result(
        string $action,
        array $payload,
        array $cmids,
        callable $fielddecider,
        bool $doupdate
    ): array {
        global $DB;
        $descriptions = $this->describe_instances($cmids);
        $table = $this->get_table_name();
        $items = [];
        $errors = [];
        foreach ($cmids as $rawcmid) {
            $cmid = (int) $rawcmid;
            if (!isset($descriptions[$cmid])) {
                $errors[] = ['cmid' => $cmid, 'code' => 'describe_failed'];
                if ($doupdate) {
                    $items[] = ['cmid' => $cmid, 'status' => 'failed'];
                }
                continue;
            }
            $description = $descriptions[$cmid];
            $snapshot = $this->build_snapshot($cmid, $description);
            $update = new \stdClass();
            $update->id = $description['instanceid'];
            $fields = [];
            $changed = [];
            foreach ($description['dates'] as $field => $oldvalue) {
                $oldvalue = (int) $oldvalue;
                $decision = $fielddecider($field, $oldvalue);
                if (!empty($decision['skip'])) {
                    if (!$doupdate) {
                        $fields[$field] = $this->preview_unchanged_field($field, $oldvalue, $action);
                    }
                    continue;
                }
                if (!array_key_exists('new', $decision)) {
                    continue;
                }
                $newvalue = (int) $decision['new'];
                $update->$field = $newvalue;
                $changed[] = $field;
                if (!$doupdate) {
                    $fields[$field] = $this->preview_changed_field($field, $oldvalue, $newvalue, $action);
                }
            }
            if ($doupdate) {
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
                    $errors[] = ['cmid' => $cmid, 'code' => 'db_write_failed', 'message' => $e->getMessage()];
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
            } else {
                $items[] = [
                    'cmid'   => $cmid,
                    'name'   => (string) $description['name'],
                    'fields' => $fields,
                ];
            }
        }
        return [
            'action'  => $action,
            'payload' => $payload,
            'items'   => $items,
            'errors'  => $errors,
        ];
    }

    /**
     * Build a canonical rollback snapshot for one cmid.
     *
     * @param int   $cmid        Course module id.
     * @param array $description Output of describe_instances() for this cmid.
     * @return array
     */
    private function build_snapshot(int $cmid, array $description): array {
        return array_merge(
            [
                'component'  => static::component(),
                'cmid'       => $cmid,
                'instanceid' => $description['instanceid'],
                'fields'     => $description['dates'],
                'version'    => 1,
            ],
            $description['dates']
        );
    }

    /**
     * Decide new value for a shift_dates preview entry on a single field.
     *
     * @param int $old   Current value.
     * @param int $delta Shift delta.
     * @return array Decision map for build_action_result().
     */
    private function preview_shift_field(int $old, int $delta): array {
        if ($old === 0) {
            return ['skip' => true];
        }
        return ['new' => $old + $delta];
    }

    /**
     * Decide new value for an unset_dates preview entry on a single field.
     *
     * @param string $field        Field name.
     * @param int    $old          Current value.
     * @param array  $targetfields Fields requested for unset.
     * @return array Decision map for build_action_result().
     */
    private function preview_unset_field(string $field, int $old, array $targetfields): array {
        if (!in_array($field, $targetfields, true)) {
            return ['skip' => true];
        }
        if ($old === 0) {
            return ['skip' => true];
        }
        return ['new' => 0];
    }

    /**
     * Build a preview entry for a field that will not change.
     *
     * @param string $field  Field name.
     * @param int    $old    Current value.
     * @param string $action Action identifier.
     * @return array Preview entry.
     */
    private function preview_unchanged_field(string $field, int $old, string $action): array {
        // Unified: always use 'old'/'new' so the JS renderPreviewHtml can read fd.old/fd.new.
        return [
            'field'   => $field,
            'old'     => $old,
            'new'     => $old,
            'shifted' => false,
            'reason'  => 'unset',
        ];
    }

    /**
     * Build a preview entry for a field that will change.
     *
     * @param string $field  Field name.
     * @param int    $old    Current value.
     * @param int    $new    New value.
     * @param string $action Action identifier.
     * @return array Preview entry.
     */
    private function preview_changed_field(string $field, int $old, int $new, string $action): array {
        // Unified: always use 'old'/'new' so the JS renderPreviewHtml can read fd.old/fd.new.
        return [
            'field'   => $field,
            'old'     => $old,
            'new'     => $new,
            'shifted' => $new !== $old,
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
}
