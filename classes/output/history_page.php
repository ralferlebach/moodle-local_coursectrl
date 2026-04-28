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
 * Renderable for the Logs & Historie page.
 *
 * Reads batch records from local_coursectrl_batch (most recent first) and
 * enriches them with per-item counts and rollback availability info.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\output;

use renderable;
use renderer_base;
use templatable;

/**
 * Renderable for the Logs & Historie page.
 */
class history_page implements renderable, templatable {
    /** @var int The course id. */
    private int $courseid;

    /** @var int Maximum number of batches to show (from settings, default 100). */
    private int $maxbatches;

    /** @var array|null Rollback result to surface in the page, or null. */
    private ?array $rollbackresult;

    /** @var int Current page (0-based). */
    private int $page;

    /**
     * Constructor.
     *
     * @param int        $courseid       The course id.
     * @param array|null $rollbackresult Optional rollback result from rollback.php.
     * @param int        $maxbatches     Maximum number of batches to show.
     * @param int        $page           Current page number (0-based).
     */
    public function __construct(
        int $courseid,
        ?array $rollbackresult = null,
        int $maxbatches = 100,
        int $page = 0
    ) {
        $this->courseid = $courseid;
        $this->rollbackresult = $rollbackresult;
        $this->maxbatches = $maxbatches;
        $this->page = max(0, $page);
    }

    /**
     * Export for template.
     *
     * @param renderer_base $output Renderer.
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $DB;
        $course = get_course($this->courseid);
        $context = \context_course::instance($this->courseid);
        $canrollback = has_capability('local/coursectrl:rollback', $context);
        $dateformat = get_string('strftimedaydatetime', 'core_langconfig');

        $maxbatches = (int) (get_config('local_coursectrl', 'history_maxcount') ?: $this->maxbatches);
        $perpage = 20;
        $offset = $this->page * $perpage;

        $totalcount = $DB->count_records('local_coursectrl_batch', ['courseid' => $this->courseid]);
        $totalpages = max(1, (int) ceil($totalcount / $perpage));
        $currentpage = min($this->page, $totalpages - 1);

        $batches = $DB->get_records(
            'local_coursectrl_batch',
            ['courseid' => $this->courseid],
            'timecreated DESC',
            '*',
            $currentpage * $perpage,
            $perpage
        );

        // Bulk-load all data for the current page in four queries:
        // (1) batch items, (2) CM names, (3) snapshot existence, (4) user names.
        // This collapses O(n) per-batch DB calls into O(4) regardless of batch count.
        $rows = [];
        if (!empty($batches)) {
            $batchids = array_keys($batches);
            [$batchinsql, $batchparams] = $DB->get_in_or_equal($batchids, SQL_PARAMS_NAMED);

            // Query 1: all batch items for this page.
            $allitems = $DB->get_records_select(
                'local_coursectrl_batch_item',
                "batchid {$batchinsql}",
                $batchparams,
                'batchid ASC, id ASC'
            );
            $itemsbybatch = [];
            $allcmids = [];
            foreach ($allitems as $item) {
                $bid = (int) $item->batchid;
                $eid = (int) $item->entityid;
                $itemsbybatch[$bid][] = $item;
                $allcmids[$eid] = $eid;
            }

            // Query 2: CM name and modname for all entity ids.
            $cminfobycmid = [];
            if (!empty($allcmids)) {
                [$cminsql, $cmparams] = $DB->get_in_or_equal(
                    array_values($allcmids),
                    SQL_PARAMS_NAMED
                );
                $cmsql = "SELECT cm.id, m.name AS modname, cm.instance
                             FROM {course_modules} cm
                             JOIN {modules} m ON m.id = cm.module
                            WHERE cm.id {$cminsql}";
                $cmrows = $DB->get_records_sql($cmsql, $cmparams);
                foreach ($cmrows as $row) {
                    $modname = (string) $row->modname;
                    $name = $DB->get_field(
                        $modname,
                        'name',
                        ['id' => (int) $row->instance]
                    ) ?: '';
                    $cminfobycmid[(int) $row->id] = [
                        'modname' => $modname,
                        'name'    => $name,
                    ];
                }
            }

            // Query 3: snapshot existence per batch.
            $snapshotbatchids = $DB->get_fieldset_sql(
                "SELECT DISTINCT batchid
                   FROM {local_coursectrl_snapshot}
                  WHERE batchid {$batchinsql}",
                $batchparams
            );
            $hassnapshots = array_fill_keys(
                array_map('intval', $snapshotbatchids),
                true
            );

            // Query 4: display names for all batch owners.
            $ownerids = array_unique(array_map(fn ($b) => (int) $b->userid, $batches));
            [$userinsql, $userparams] = $DB->get_in_or_equal($ownerids, SQL_PARAMS_NAMED);
            $userfields = 'id,firstname,lastname,email,'
                . 'firstnamephonetic,lastnamephonetic,middlename,alternatename';
            $usersraw = $DB->get_records_select(
                'user',
                "id {$userinsql}",
                $userparams,
                '',
                $userfields
            );
            $userbyid = [];
            foreach ($usersraw as $u) {
                $userbyid[(int) $u->id] = fullname($u);
            }

            foreach ($batches as $batch) {
                $batchitemsraw = $itemsbybatch[(int) $batch->id] ?? [];
                // Count distinct cmids and total changed fields (not batch_item rows).
                $activitycmids = [];
                $totalfieldchanges = 0;
                $detailrows = [];
                foreach ($batchitemsraw as $bitem) {
                    $eid = (int)$bitem->entityid;
                    $activitycmids[$eid] = true;
                    $result = $bitem->resultjson ? json_decode($bitem->resultjson, true) : [];
                    $changed = $result['changed'] ?? [];
                    $totalfieldchanges += count($changed);
                    $cminfo  = $cminfobycmid[$eid] ?? [];
                    $modname = $cminfo['modname'] ?? '';
                    $cmname  = $cminfo['name'] ?? '';
                    $cmurl   = $modname && $cmname
                        ? (new \moodle_url('/mod/' . $modname . '/view.php', ['id' => $eid]))->out(false)
                        : '';
                    $detailrows[] = [
                        'entityid'   => $eid,
                        'cmname'     => $cmname,
                        'cmurl'      => $cmurl,
                        'modname'    => $modname,
                        'hascmname'  => !empty($cmname),
                        'component'  => (string) $bitem->component,
                        'status'     => (string) $bitem->status,
                        'issuccess'  => $bitem->status === \local_coursectrl\local\persistent\batch_item::STATUS_SUCCESS,
                        'isskipped'  => $bitem->status === \local_coursectrl\local\persistent\batch_item::STATUS_SKIPPED,
                        'iserror'    => $bitem->status === \local_coursectrl\local\persistent\batch_item::STATUS_ERROR,
                        'changed'    => array_map(fn($f) => ['field' => $f], $changed),
                        'haschanged' => !empty($changed),
                    ];
                }
                $activitycount = count($activitycmids);
                $hassnapshot = !empty($hassnapshots[(int) $batch->id]);
                $username = $userbyid[(int) $batch->userid]
                    ?? get_string('unknownuser', 'local_coursectrl');

                $status = (string) $batch->status;
                $rows[] = [
                    'batchid'          => (int) $batch->id,
                    'action'           => (string) $batch->action,
                    'actionlabel'      => get_string('action_' . $batch->action, 'local_coursectrl', null, true)
                        ?: (string) $batch->action,
                    'status'           => $status,
                    'status_pending'   => $status === \local_coursectrl\local\persistent\batch::STATUS_PENDING,
                    'status_executed'  => $status === \local_coursectrl\local\persistent\batch::STATUS_EXECUTED,
                    'status_failed'    => $status === \local_coursectrl\local\persistent\batch::STATUS_FAILED,
                    'status_rolledback' => $status === \local_coursectrl\local\persistent\batch::STATUS_ROLLED_BACK,
                    'itemcount'        => $totalfieldchanges,
                    'activitycount'    => $activitycount,
                    'detailrows'       => $detailrows,
                    'hasdetailrows'    => !empty($detailrows),
                    'username'         => $username,
                    'timeago'          => format_time(time() - $batch->timecreated),
                    'timeformatted'    => userdate($batch->timecreated, $dateformat),
                    'canrollback'      => $canrollback && $hassnapshot
                        && $status === \local_coursectrl\local\persistent\batch::STATUS_EXECUTED,
                    'rolledback'       => $status === \local_coursectrl\local\persistent\batch::STATUS_ROLLED_BACK,
                    'rollbackurl'      => (new \moodle_url(
                        '/local/coursectrl/rollback.php',
                        ['batchid' => $batch->id, 'courseid' => $this->courseid, 'sesskey' => sesskey()]
                    ))->out(false),
                ];
            }
        } // End if (!empty($batches)).

        return [
            'courseid'       => $this->courseid,
            'coursefullname' => format_string($course->fullname),
            'sesskey'        => sesskey(),
            'rows'           => $rows,
            'hasrows'        => count($rows) > 0,
            'rowcount'       => count($rows),
            'totalcount'     => $totalcount,
            'maxbatches'     => $maxbatches,
            'currentpage'    => $currentpage,
            'currentpage1'   => $currentpage + 1,
            'totalpages'     => $totalpages,
            'haspagination'  => $totalpages > 1,
            'prevpage'       => max(0, $currentpage - 1),
            'nextpage'       => min($totalpages - 1, $currentpage + 1),
            'hasprev'        => $currentpage > 0,
            'hasnext'        => $currentpage < $totalpages - 1,
            'pagestart'      => $currentpage * $perpage + 1,
            'pageend'        => min(($currentpage + 1) * $perpage, $totalcount),
            'historyurl'     => (new \moodle_url(
                '/local/coursectrl/history.php',
                ['courseid' => $this->courseid]
            ))->out(false),
            'canrollback'    => $canrollback,
            'batchrows'      => $rows,
            'hasbatchrows'   => count($rows) > 0,
            'batchcount'     => count($rows),
            'hasrollbackresult' => $this->rollbackresult !== null,
            'rollbacksuccess'   => !empty($this->rollbackresult['success']),
            'rollbackrestored'  => (int) ($this->rollbackresult['restored'] ?? 0),
            'rollbackfailed'    => (int) ($this->rollbackresult['failed'] ?? 0),
            'rollbackerror'     => (string) ($this->rollbackresult['error'] ?? ''),
            'rollbackitems'     => $this->build_rollback_items($this->rollbackresult['items'] ?? []),
            'rollbackurl'    => (new \moodle_url(
                '/local/coursectrl/rollback.php',
                ['courseid' => $this->courseid, 'sesskey' => sesskey()]
            ))->out(false),
            'dashboardurl'   => (new \moodle_url(
                '/local/coursectrl/index.php',
                ['courseid' => $this->courseid]
            ))->out(false),
        ];
    }

    /**
     * Build the rollback items array for template rendering.
     *
     * @param array $items Raw rollback item arrays from rollback_manager.
     * @return array Enriched items with boolean status flags.
     */
    private function build_rollback_items(array $items): array {
        $result = [];
        foreach ($items as $item) {
            $status = (string) ($item['status'] ?? '');
            $result[] = [
                'entityid'   => (int) ($item['entityid'] ?? 0),
                'component'  => (string) ($item['component'] ?? ''),
                'status'     => $status,
                'message'    => (string) ($item['message'] ?? ''),
                'isrestored' => $status === 'restored',
                'iserror'    => $status === 'error',
            ];
        }
        return $result;
    }
}
