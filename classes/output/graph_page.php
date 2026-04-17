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
            'hideindependents' => false,
        ], $filters);
    }

    /**
     * Build template context for templates/graph.mustache.
     *
     * @param renderer_base $output Renderer (unused, required by interface).
     * @return array<string, mixed>
     */
    public function export_for_template(renderer_base $output): array {
        $course = $this->snapshot->course;
        $cms = $this->snapshot->cms;

        // Build shared analysis structures.
        $depindex = new dependency_index($cms);
        $datecollector = new date_collector();
        $datesbycm = $datecollector->collect_grouped_by_cm($cms);
        $runner = new consistency_runner();
        $warnings = $runner->get_warnings($cms, $depindex, $datesbycm);

        // Graph dataset.
        $graphbuilder = new graph_dataset_builder();
        $graphdata = $graphbuilder->build($cms, $depindex, $warnings);

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

        $courseid = $course->id;
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
                '/local/coursectrl/graph.php',
                ['courseid' => $courseid]
            ))->out(false),
            'hideindependents' => !empty($this->filters['hideindependents']),
            'groupoptions' => [],
            'hasgroupoptions' => false,
            'activegroupid' => 0,
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
