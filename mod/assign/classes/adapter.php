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
 * Patch-018 scope: read-only inventory plus preview for shift_dates and
 * snapshot capture via export_state. The mutating methods execute_action()
 * and restore_state() are intentionally inherited as no-ops from
 * abstract_activity_adapter and will be implemented in a follow-up patch
 * once the bulk engine in Phase 4 lands.
 *
 * @package    coursectrlmod_assign
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace coursectrlmod_assign;

use local_coursectrl\local\contract\abstract_activity_adapter;

/**
 * Activity adapter wrapping mod_assign.
 */
class adapter extends abstract_activity_adapter {
    /**
     * Returns the frankenstyle component name of the wrapped module.
     *
     * @return string
     */
    public static function component(): string {
        return 'mod_assign';
    }

    /**
     * Whether mod_assign is installed and usable on this site.
     *
     * @return bool
     */
    public function is_available(): bool {
        $modules = \core_component::get_plugin_list('mod');
        return is_array($modules) && array_key_exists('assign', $modules);
    }

    /**
     * Actions this adapter handles.
     *
     * Patch-018 exposes shift_dates as previewable. execute_action remains
     * a no-op until the bulk engine ships.
     *
     * @return string[]
     */
    public function get_supported_actions(): array {
        return ['shift_dates'];
    }

    /**
     * Field descriptors for bulk-editable assign fields.
     *
     * @return array
     */
    public function get_supported_fields(): array {
        return field_map::get_date_fields();
    }

    /**
     * Enumerate all assign instances in a course.
     *
     * @param int   $courseid target course id.
     * @param array $filters  reserved for future use, currently ignored.
     * @return array keyed by cmid.
     */
    public function get_instances_for_course(int $courseid, array $filters = []): array {
        $result = [];
        $cms = get_coursemodules_in_course('assign', $courseid);
        if (!is_array($cms)) {
            return $result;
        }
        foreach ($cms as $cm) {
            $result[(int)$cm->id] = [
                'cmid'       => (int)$cm->id,
                'instanceid' => (int)$cm->instance,
                'name'       => (string)$cm->name,
                'visible'    => (bool)$cm->visible,
                'sectionid'  => (int)$cm->section,
            ];
        }
        return $result;
    }

    /**
     * Return a normalised description of a single assign course module.
     *
     * @param int $cmid course module id.
     * @return array
     */
    public function describe_instance(int $cmid): array {
        global $DB;
        $cm = get_coursemodule_from_id('assign', $cmid, 0, false, MUST_EXIST);
        $assign = $DB->get_record(
            'assign',
            ['id' => $cm->instance],
            'id, name, duedate, allowsubmissionsfromdate, cutoffdate, gradingduedate',
            MUST_EXIST
        );
        return [
            'cmid'       => (int)$cmid,
            'component'  => 'mod_assign',
            'instanceid' => (int)$cm->instance,
            'name'       => (string)$assign->name,
            'visible'    => (bool)$cm->visible,
            'dates'      => [
                'duedate'                  => (int)$assign->duedate,
                'allowsubmissionsfromdate' => (int)$assign->allowsubmissionsfromdate,
                'cutoffdate'               => (int)$assign->cutoffdate,
                'gradingduedate'           => (int)$assign->gradingduedate,
            ],
        ];
    }

    /**
     * Validate a shift_dates payload.
     *
     * Other actions are reported as unsupported. The bulk engine in Phase 4
     * is expected to call this before preview / execute.
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
     * Per-field semantics: a stored date value of 0 means "unset" in
     * mod_assign and is left untouched (reason: 'unset'). All other values
     * are reported with old / new / shifted flags.
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
     * Capture the rollback-relevant state of one assign instance.
     *
     * Returns a flat snapshot of the four shiftable date fields plus the
     * cmid and instanceid needed by a future restore_state implementation.
     *
     * @param int $cmid course module id.
     * @return array
     */
    public function export_state(int $cmid): array {
        $description = $this->describe_instance($cmid);
        return [
            'component'  => 'mod_assign',
            'cmid'       => (int)$cmid,
            'instanceid' => $description['instanceid'],
            'fields'     => $description['dates'],
            'version'    => 1,
        ];
    }
}
