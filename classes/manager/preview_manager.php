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

use local_coursectrl\local\contract\activity_adapter;
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
                'total'   => count($cmids),
                'changes' => count($changes),
                'skipped' => count($skipped),
                'errors'  => count($errors),
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
