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
 * Course-wide preview manager for the Course Control Hub bulk pipeline.
 *
 * Aggregates per-cmid adapter previews into a course-wide preview result.
 * Acts as the read-side counterpart to batch_manager: takes an action
 * identifier and a target cmid set, routes each cmid to the responsible
 * adapter via the registry, and returns a normalised result containing
 * preview_change DTOs plus skipped and error lists.
 *
 * Routing rules:
 *   - cmids without a registered adapter for their component are added to
 *     'skipped' with reason 'no_adapter'.
 *   - cmids whose adapter does not advertise the requested action via
 *     get_supported_actions() are added to 'skipped' with reason
 *     'unsupported_action'.
 *   - cmids that fail the adapter's validate_action() are added to
 *     'errors' with the adapter's error descriptors.
 *   - cmids whose preview_action() raises are added to 'errors' with
 *     code 'preview_failed'.
 *
 * Per-adapter call shape: cmids belonging to the same adapter are passed
 * to that adapter in a single preview_action() call so adapters can batch
 * their DB reads. The aggregation then maps the adapter's per-item array
 * into preview_change instances keyed by cmid.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\manager;

use local_coursectrl\local\analysis\date_collector;
use local_coursectrl\local\contract\activity_adapter;
use local_coursectrl\local\dto\shift_target;
use local_coursectrl\local\field_label_resolver;
use local_coursectrl\local\dto\preview_change;
use local_coursectrl\local\dto\validation_result;

/**
 * Aggregates adapter previews for a course-wide bulk action.
 */
class preview_manager {
    /** @var registry Adapter registry used to look up activity adapters. */
    private registry $registry;

    /**
     * Constructor.
     *
     * @param registry|null $registry optional registry instance, mainly for
     *                                tests. When null, a fresh registry with
     *                                live discovery is created.
     */
    public function __construct(?registry $registry = null) {
        $this->registry = $registry ?? new registry();
    }

    /**
     * Returns the registry instance backing this manager.
     *
     * @return registry
     */
    public function get_registry(): registry {
        return $this->registry;
    }

    /**
     * Build a course-wide preview for a bulk action.
     *
     * Result shape:
     *   [
     *     'action'  => string,
     *     'payload' => array,
     *     'changes' => preview_change[]      // keyed by cmid
     *     'skipped' => array                 // [['cmid' => int, 'reason' => string], ...]
     *     'errors'  => array                 // [['cmid' => int, 'code' => string, ...], ...]
     *     'summary' => array                 // counts: 'total', 'changes', 'skipped', 'errors'
     *   ]
     *
     * @param int    $courseid target course id.
     * @param string $action   canonical action identifier, e.g. 'shift_dates'.
     * @param array  $payload  action-specific parameters.
     * @param int[]  $cmids    target course module ids; empty means "all
     *                         CMs of all components for which an adapter
     *                         is registered".
     * @return array
     */
    public function build(int $courseid, string $action, array $payload, array $cmids = []): array {
        // Targets-based path: delegate when payload carries structured targets.
        if (!empty($payload['targets'])) {
            $targets = [];
            foreach ($payload['targets'] as $t) {
                $targets[] = shift_target::from_array($t);
            }
            return $this->build_from_targets($courseid, $action, $payload, $targets);
        }
        if (empty($cmids)) {
            $cmids = $this->collect_supported_cmids_for_course($courseid);
        } else {
            $requested = array_values(array_unique(array_map('intval', $cmids)));
            $cmids = $this->filter_cmids_to_course($courseid, $requested);
            if (count($cmids) !== count($requested)) {
                throw new \moodle_exception('invalidcmid', 'local_coursectrl');
            }
        }
        $byadapter = $this->group_cmids_by_adapter($cmids, $action);
        $changes = [];
        $errors  = $byadapter['errors'];
        $skipped = $byadapter['skipped'];
        foreach ($byadapter['routed'] as $component => $entry) {
            /** @var activity_adapter $adapter */
            $adapter      = $entry['adapter'];
            $adaptercmids = $entry['cmids'];
            $validation = validation_result::from_adapter_array(
                $adapter->validate_action($action, $payload, $adaptercmids)
            );
            if (!$validation->is_valid()) {
                foreach ($adaptercmids as $cmid) {
                    foreach ($validation->get_errors() as $error) {
                        $errors[] = [
                            'cmid'      => $cmid,
                            'component' => $component,
                            'code'      => $error['code'] ?? 'invalid_payload',
                            'details'   => $error,
                        ];
                    }
                }
                continue;
            }
            try {
                $preview = $adapter->preview_action($action, $payload, $adaptercmids);
            } catch (\Throwable $e) {
                foreach ($adaptercmids as $cmid) {
                    $errors[] = [
                        'cmid'      => $cmid,
                        'component' => $component,
                        'code'      => 'preview_failed',
                        'message'   => $e->getMessage(),
                    ];
                }
                continue;
            }
            foreach ($preview['items'] ?? [] as $item) {
                $cmid = (int)$item['cmid'];
                $changes[$cmid] = new preview_change(
                    $cmid,
                    $component,
                    (string)($item['name'] ?? ''),
                    $item['fields'] ?? []
                );
            }
            foreach ($preview['errors'] ?? [] as $error) {
                $errors[] = [
                    'cmid'      => (int)($error['cmid'] ?? 0),
                    'component' => $component,
                    'code'      => $error['code'] ?? 'preview_failed',
                    'message'   => $error['message'] ?? '',
                ];
            }
        }
        return [
            'action'  => $action,
            'payload' => $payload,
            'changes' => $changes,
            'skipped' => $skipped,
            'errors'  => $errors,
            'summary' => [
                'total'      => count($cmids),
                'changes'    => count($changes),
                'fieldcount' => array_sum(
                    array_map(fn($c) => count($c->get_fields()), array_values($changes))
                ),
                'skipped'    => count($skipped),
                'errors'     => count($errors),
            ],
        ];
    }

    /**
     * Group the input cmids by responsible adapter, separating out cmids
     * without an adapter or whose adapter does not support the action.
     *
     * @param int[]  $cmids  input cmid list.
     * @param string $action canonical action identifier.
     * @return array{routed: array<string, array{adapter: activity_adapter, cmids: int[]}>, skipped: array, errors: array}
     */
    /**
     * Build a preview from a structured list of shift_target objects.
     *
     * Groups adapter targets by (component, field-set) for batched adapter
     * calls. CM-level and availability targets are previewed directly from
     * the course_modules row so they appear in the changes list rather than
     * being silently skipped.
     *
     * @param int          $courseid Course id.
     * @param string       $action   Canonical action identifier.
     * @param array        $payload  Action payload (must contain 'delta').
     * @param shift_target[] $targets Structured target list.
     * @return array Preview result in the same shape as build().
     */
    private function build_from_targets(
        int $courseid,
        string $action,
        array $payload,
        array $targets
    ): array {
        global $DB;

        $delta = (int) ($payload['delta'] ?? 0);
        $changes = [];
        $skipped = [];
        $errors  = [];

        // Validate all target cmids belong to the course.
        $allcmids = array_values(
            array_unique(array_map(function (shift_target $t) {
                return $t->get_cmid();
            }, $targets))
        );
        $validcmids = $this->filter_cmids_to_course($courseid, $allcmids);
        $validset = array_flip($validcmids);

        // Separate targets by source; discard invalid cmids silently.
        $adaptertargets = [];
        $cmtargets      = [];
        $availtargets   = [];
        foreach ($targets as $target) {
            $cmid = $target->get_cmid();
            if (!isset($validset[$cmid])) {
                continue;
            }
            if ($target->get_source() === shift_target::SOURCE_ADAPTER) {
                $adaptertargets[$cmid][] = $target->get_field();
            } else if ($target->get_source() === shift_target::SOURCE_CM) {
                $cmtargets[$cmid][] = $target->get_field();
            } else if ($target->get_source() === shift_target::SOURCE_AVAILABILITY) {
                $availtargets[$cmid][] = $target->get_field();
            }
        }

        // Group adapter targets by (frankenstyle component, sorted field list).
        $adaptergroups = [];
        foreach ($adaptertargets as $cmid => $fields) {
            $adapter = $this->registry->get_for_cmid($cmid);
            if ($adapter === null) {
                $skipped[] = ['cmid' => $cmid, 'reason' => 'no_adapter'];
                continue;
            }
            $component = $adapter::component();
            sort($fields);
            $groupkey = $component . '|' . implode(',', $fields);
            if (!isset($adaptergroups[$groupkey])) {
                $adaptergroups[$groupkey] = [
                    'adapter'   => $adapter,
                    'component' => $component,
                    'fields'    => $fields,
                    'cmids'     => [],
                ];
            }
            $adaptergroups[$groupkey]['cmids'][] = $cmid;
        }

        foreach ($adaptergroups as $group) {
            // Strip meta-keys that are not part of the adapter contract.
            // Targets and followdeps are internal routing hints; passing
            // Them to the adapter causes it to bypass payload.fields.
            $adapterpayload = $payload;
            unset($adapterpayload['targets']);
            unset($adapterpayload['followdeps']);
            if (!empty($group['fields'])) {
                $adapterpayload['fields'] = $group['fields'];
            }
            $adaptercmids = $group['cmids'];
            $validation = validation_result::from_adapter_array(
                $group['adapter']->validate_action($action, $adapterpayload, $adaptercmids)
            );
            if (!$validation->is_valid()) {
                foreach ($adaptercmids as $cmid) {
                    foreach ($validation->get_errors() as $error) {
                        $errors[] = [
                            'cmid'      => $cmid,
                            'component' => $group['component'],
                            'code'      => $error['code'] ?? 'invalid_payload',
                            'details'   => $error,
                        ];
                    }
                }
                continue;
            }
            try {
                $preview = $group['adapter']->preview_action(
                    $action,
                    $adapterpayload,
                    $adaptercmids
                );
            } catch (\Throwable $e) {
                foreach ($adaptercmids as $cmid) {
                    $errors[] = [
                        'cmid'      => $cmid,
                        'component' => $group['component'],
                        'code'      => 'preview_failed',
                        'message'   => $e->getMessage(),
                    ];
                }
                continue;
            }
            foreach ($preview['items'] ?? [] as $item) {
                $cmid = (int) ($item['cmid'] ?? 0);
                $rawfields = $item['fields'] ?? [];
                // Filter to only the fields that were explicitly targeted.
                // Adapters may return all their date fields regardless of
                // Payload.fields, so we apply the restriction here.
                if (!empty($group['fields'])) {
                    $rawfields = array_intersect_key(
                        $rawfields,
                        array_flip($group['fields'])
                    );
                }
                if (empty($rawfields)) {
                    continue;
                }
                $changes[$cmid] = new preview_change(
                    $cmid,
                    $group['component'],
                    (string) ($item['name'] ?? ''),
                    $rawfields
                );
            }
        }

        // Preview CM-level targets (completionexpected, availability dates).
        $allcmlevel = array_unique(
            array_merge(array_keys($cmtargets), array_keys($availtargets))
        );
        foreach ($allcmlevel as $cmid) {
            $change = $this->build_cm_level_preview(
                (int) $cmid,
                $cmtargets[$cmid] ?? [],
                $availtargets[$cmid] ?? [],
                $delta,
                $DB
            );
            if ($change !== null) {
                if (isset($changes[$cmid])) {
                    // Merge CM-level fields into the existing adapter preview so
                    // Both duedate and completionexpected appear for the same CMID.
                    $mergedfields = array_merge(
                        $changes[$cmid]->get_fields(),
                        $change->get_fields()
                    );
                    $changes[$cmid] = new preview_change(
                        (int) $cmid,
                        $changes[$cmid]->get_component(),
                        $changes[$cmid]->get_name(),
                        $mergedfields
                    );
                } else {
                    $changes[$cmid] = $change;
                }
            }
        }

        // Compute total field count across all changed activities.
        $fieldcount = 0;
        foreach ($changes as $change) {
            $fieldcount += count($change->get_fields());
        }

        return [
            'action'  => $action,
            'payload' => $payload,
            'changes' => $changes,
            'skipped' => $skipped,
            'errors'  => $errors,
            'summary' => [
                'total'      => count($allcmids),
                'changes'    => count($changes),
                'fieldcount' => $fieldcount,
                'skipped'    => count($skipped),
                'errors'     => count($errors),
            ],
        ];
    }

    /**
     * Build a preview_change for CM-level date fields of one course module.
     *
     * Covers completionexpected (source=cm) and availability date conditions
     * (source=availability). Returns null when nothing would change.
     *
     * @param int      $cmid         Course module id.
     * @param string[] $cmfields     CM-source field names to preview.
     * @param string[] $availfields  Availability-source field names to preview.
     * @param int      $delta        Shift delta in seconds.
     * @param \moodle_database $db  DB instance.
     * @return preview_change|null
     */
    private function build_cm_level_preview(
        int $cmid,
        array $cmfields,
        array $availfields,
        int $delta,
        \moodle_database $db
    ): ?preview_change {
        $cm = $db->get_record(
            'course_modules',
            ['id' => $cmid],
            'id, completionexpected, availability',
            IGNORE_MISSING
        );
        if (!$cm) {
            return null;
        }

        $previewfields = [];

        if (in_array('completionexpected', $cmfields, true) && (int) $cm->completionexpected > 0) {
            $oldval = (int) $cm->completionexpected;
            $previewfields['completionexpected'] = [
                'old'     => $oldval,
                'new'     => $oldval + $delta,
                'shifted' => $delta !== 0,
            ];
        }

        if (!empty($availfields) && !empty($cm->availability)) {
            $avail = json_decode((string) $cm->availability, true);
            if (is_array($avail)) {
                $dates = [];
                $this->collect_availability_dates($avail, $dates, $delta);
                foreach ($dates as $desc) {
                    // Key by field name so preview_bulk_action resolves the label correctly.
                    $previewfields[$desc['field']] = [
                        'old'     => $desc['old'],
                        'new'     => $desc['new'],
                        'shifted' => $desc['shifted'],
                    ];
                }
            }
        }

        if (empty($previewfields)) {
            return null;
        }

        $name = '';
        $cmobj = get_coursemodule_from_id('', $cmid, 0, false, IGNORE_MISSING);
        if ($cmobj) {
            $name = $cmobj->name;
        }

        // Use mod_<modname> so preview_bulk_action icon lookup can short-circuit.
        $modcomponent = $cmobj ? 'mod_' . $cmobj->modname : 'core_coursemodule';
        return new preview_change($cmid, $modcomponent, $name, $previewfields);
    }

    /**
     * Recursively collect date-condition previews from an availability node.
     *
     * @param array  $node   Decoded availability condition node.
     * @param array  $dates  Accumulator for preview field descriptors.
     * @param int    $delta  Shift delta in seconds.
     * @param int    $idx    Running index for field key generation.
     * @return void
     */
    private function collect_availability_dates(
        array $node,
        array &$dates,
        int $delta,
        int &$idx = 0
    ): void {
        if (($node['type'] ?? '') === 'date' && isset($node['t']) && (int) $node['t'] > 0) {
            $oldval = (int) $node['t'];
            $e = $node['e'] ?? 'a';
            $dates[] = [
                'field'   => 'availability_' . ($e === '>=' ? 'from' : 'until') . '_' . $idx,
                'label'   => field_label_resolver::resolve(
                    'availability_' . ($e === '>=' ? 'from' : 'until') . '_0',
                    '',
                    'availability'
                ),
                'old'     => $oldval,
                'new'     => $oldval + $delta,
                'shifted' => $delta !== 0,
            ];
            $idx++;
            return;
        }
        if (isset($node['c']) && is_array($node['c'])) {
            foreach ($node['c'] as $child) {
                if (is_array($child)) {
                    $this->collect_availability_dates($child, $dates, $delta, $idx);
                }
            }
        }
    }

    /**
     * Group the input cmids by responsible adapter, separating out cmids
     * without an adapter or whose adapter does not support the action.
     *
     * @param int[]  $cmids  Input cmid list.
     * @param string $action Canonical action identifier.
     * @return array Grouping with keys: routed, skipped, errors.
     */
    private function group_cmids_by_adapter(array $cmids, string $action): array {
        $routed  = [];
        $skipped = [];
        $errors  = [];
        foreach ($cmids as $rawcmid) {
            $cmid = (int)$rawcmid;
            $adapter = $this->registry->get_for_cmid($cmid);
            if ($adapter === null) {
                $skipped[] = [
                    'cmid'   => $cmid,
                    'reason' => 'no_adapter',
                ];
                continue;
            }
            if (!in_array($action, $adapter->get_supported_actions(), true)) {
                $skipped[] = [
                    'cmid'      => $cmid,
                    'component' => $adapter::component(),
                    'reason'    => 'unsupported_action',
                ];
                continue;
            }
            $component = $adapter::component();
            if (!isset($routed[$component])) {
                $routed[$component] = [
                    'adapter' => $adapter,
                    'cmids'   => [],
                ];
            }
            $routed[$component]['cmids'][] = $cmid;
        }
        return [
            'routed'  => $routed,
            'skipped' => $skipped,
            'errors'  => $errors,
        ];
    }

    /**
     * Collect every cmid in the course whose component has a registered
     * adapter. Used as the default target set when build() is called with
     * an empty cmids list.
     *
     * @param int $courseid target course id.
     * @return int[]
     */
    /**
     * Return only course module ids that belong to the given course.
     *
     * @param int   $courseid Course id to filter against.
     * @param int[] $cmids    Caller-supplied course module ids.
     * @return int[] Subset of $cmids that belong to $courseid.
     */
    private function filter_cmids_to_course(int $courseid, array $cmids): array {
        global $DB;
        $cmids = array_values(array_unique(array_map('intval', $cmids)));
        if (empty($cmids)) {
            return [];
        }
        [$insql, $params] = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED);
        $params['courseid'] = $courseid;
        $validids = $DB->get_fieldset_select(
            'course_modules',
            'id',
            "course = :courseid AND deletioninprogress = 0 AND id {$insql}",
            $params
        );
        return array_values(array_map('intval', $validids));
    }

    /**
     * Collect every cmid in the course whose component has a registered adapter.
     *
     * Used as the default target set when build() is called with an empty cmids list.
     *
     * @param int $courseid Target course id.
     * @return int[]
     */
    private function collect_supported_cmids_for_course(int $courseid): array {
        $result = [];
        foreach ($this->registry->get_all() as $component => $adapter) {
            $instances = $adapter->get_instances_for_course($courseid);
            foreach ($instances as $entry) {
                $result[] = (int)$entry['cmid'];
            }
        }
        sort($result, SORT_NUMERIC);
        return $result;
    }
}
