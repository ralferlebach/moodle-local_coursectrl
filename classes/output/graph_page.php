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
 * Renderable for the Course Control Hub graph view.
 *
 * Exports two JSON datasets (dependency graph + Gantt) embedded as
 * data attributes on container elements. The AMD module graphview.js
 * reads these attributes and renders the visualisations in-browser.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\output;

use local_coursectrl\local\analysis\consistency_runner;
use local_coursectrl\local\analysis\date_collector;
use local_coursectrl\local\analysis\dependency_index;
use local_coursectrl\local\inventory\inventory_snapshot;
use local_coursectrl\local\analysis\group_resolver;
use local_coursectrl\local\visualization\gantt_dataset_builder;
use local_coursectrl\local\visualization\graph_dataset_builder;
use renderable;
use renderer_base;
use templatable;

/**
 * Renderable that exports Gantt and dependency-graph datasets.
 */
class graph_page implements renderable, templatable {
    /** @var inventory_snapshot */
    protected inventory_snapshot $snapshot;

* @param inventory_snapshot $snapshot Course inventory snapshot.
* @param array $filters Active graph filter values.
    /** @var array UI filter state. */
    protected array $filters;

    /**
     * Constructor.
     *
     * @param inventory_snapshot $snapshot Course inventory snapshot.
     */
    public function __construct(inventory_snapshot $snapshot, array $filters = []) {
        $this->snapshot = $snapshot;
        $this->filters = array_merge([
            'hideindependents' => true,
            'groupids'         => [],
            'filterbygroup'    => false,
            'blockedids'       => [],
            'nextstepids'      => [],
        ], $filters);
    }

    /**
     * Build template context for templates/graph.mustache.
     *
     * @param renderer_base $output Renderer (unused, required by interface).
     * @return array<string, mixed>
     */
    public function export_for_template(renderer_base $output): array {
        global $DB;
        $course = $this->snapshot->course;
        $cms = $this->snapshot->cms;
        $groupids = array_filter(array_map('intval', $this->filters['groupids'] ?? []));
        $filterbygroup = !empty($this->filters['filterbygroup']) && !empty($groupids);
        $blockedids  = array_filter(array_map('intval', $this->filters['blockedids'] ?? []));
        $nextstepids = array_filter(array_map('intval', $this->filters['nextstepids'] ?? []));

        // Build shared analysis structures.
        // Grade-item map: grade_items.id → cmid, used to resolve grade-based
        // ... availability conditions to actual cmid pairs in the dependency graph.
        $gradeitemmap = [];
        $graderows = $DB->get_records_sql(
            "SELECT gi.id, cm.id AS cmid
               FROM {grade_items} gi
               JOIN {modules} m ON m.name = gi.itemmodule
               JOIN {course_modules} cm ON cm.module = m.id
                                       AND cm.instance = gi.iteminstance
                                       AND cm.course = gi.courseid
              WHERE gi.courseid = :courseid AND gi.itemtype = 'mod'",
            ['courseid' => (int) $course->id]
        );
        foreach ($graderows as $row) {
            $gradeitemmap[(int) $row->id] = (int) $row->cmid;
        }

        // R7: Subsections cannot have a completion state and therefore
        // can never generate a dependency. Remove them from the graph CMs
        // so they are neither shown as nodes nor considered in dep analysis.
        $graphcms = array_filter(
            $cms,
            static fn (\local_coursectrl\local\entity\cm_item $cm): bool =>
                $cm->modname !== 'subsection'
        );

        $depindex = new dependency_index($graphcms, $gradeitemmap);
        $datecollector = new date_collector();
        $datesbycm = $datecollector->collect_grouped_by_cm($graphcms);
        $runner = new consistency_runner();
        $warnings = $runner->get_warnings($graphcms, $depindex, $datesbycm);

        // Graph dataset — use group-filtered forward deps if a group is active.
        $graphbuilder = new graph_dataset_builder();
        if ($filterbygroup && !empty($groupids)) {
            // Use unlock-only forward deps (e=1) to avoid false-positive
            // circular dependency arrows from gate-closing (e=0) conditions.
            $forwardmap = $depindex->get_unlock_forward_for_groups($groupids);
            $graphdata = $graphbuilder->build_with_forward(
                $graphcms,
                $depindex,
                $forwardmap,
                $warnings,
                $blockedids,
                $nextstepids
            );
        } else {
            $graphdata = $graphbuilder->build(
                $graphcms,
                $depindex,
                $warnings,
                $blockedids,
                $nextstepids
            );
        }

        // Enrich graph nodes with module icon URLs for SVG rendering.
        foreach ($graphdata['nodes'] as &$node) {
            $node['iconurl'] = $output->image_url('monologo', 'mod_' . $node['modname'])->out(false);
        }
        unset($node);

        // Gantt dataset.
        $ganttbuilder = new gantt_dataset_builder($datecollector);
        $ganttdata = $ganttbuilder->build($cms);

        // Enrich Gantt bars with human-readable date labels for the tooltip.
        $dateformat = get_string('strftimedaydatetime', 'core_langconfig');
        foreach ($ganttdata['rows'] as &$row) {
            foreach ($row['bars'] as &$bar) {
                $bar['formatted'] = userdate($bar['timestamp'], $dateformat);
            }
            unset($bar);
        }
        unset($row);

        // Load group options for the selector.
        $courseid = $course->id;
        $resolver = new group_resolver($courseid);
        $groupoptions = array_map(function ($g) use ($groupids) {
            $g['selected'] = in_array((int) $g['id'], $groupids, true);
            return $g;
        }, $resolver->get_groups_for_template());

        return [
            'courseid' => $courseid,
            'coursefullname' => format_string($course->fullname),
            'graphjson' => json_encode($graphdata),
            'ganttjson' => json_encode($ganttdata),
            'hasgraph' => $graphdata['hasdata'],
            'hasgantt' => $ganttdata['hasdata'],
            'graphnodecount' => $graphdata['nodecount'],
            'graphedgecount' => $graphdata['edgecount'],
            'ganttrowcount' => $ganttdata['rowcount'],
            'graphurl' => (new \moodle_url(
                '/local/coursectrl/dependencies.php',
                ['courseid' => $courseid]
            ))->out(false),
            'hideindependents'         => !empty($this->filters['hideindependents']),
            'str_hide_independents'    => get_string(
                'graph_hide_independents',
                'local_coursectrl'
            ),
            'filterbygroup' => $filterbygroup,
            'hassimoverlay' => !empty($blockedids) || !empty($nextstepids),
            'groupoptions' => $groupoptions,
            'hasgroupoptions' => !empty($groupoptions),
            'activegroupids' => $groupids,
            'dashboardurl' => (new \moodle_url(
                '/local/coursectrl/index.php',
                ['courseid' => $courseid]
            ))->out(false),
            'timelineurl' => (new \moodle_url(
                '/local/coursectrl/timeline.php',
                ['courseid' => $courseid]
            ))->out(false),
            'manageurl' => (new \moodle_url(
                '/local/coursectrl/manage.php',
                ['courseid' => $courseid]
            ))->out(false),
        ];
    }
}
