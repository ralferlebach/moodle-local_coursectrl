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
 * Renderable for the chronological timeline manager page.
 *
 * Transforms the grouped-by-day date_collector output into a template
 * context suitable for timeline.mustache: a list of day groups, each
 * containing time slots with the activities linked to that moment.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\output;

use local_coursectrl\local\analysis\date_collector;
use local_coursectrl\local\analysis\dependency_index;
use local_coursectrl\local\inventory\inventory_snapshot;
use renderable;
use renderer_base;
use templatable;

/**
 * Renderable for the chronological timeline manager page.
 */
class timeline_page implements renderable, templatable {
    /** @var inventory_snapshot The course inventory. */
    protected inventory_snapshot $snapshot;

    /** @var array User filter options. */
    protected array $filters;

    /**
     * Constructor.
     *
     * @param inventory_snapshot $snapshot The course inventory.
     * @param array              $filters  User filters:
     *                                     - showpast (bool)
     *                                     - onlywithdeps (bool)
     *                                     - components (string[]).
     */
    public function __construct(inventory_snapshot $snapshot, array $filters = []) {
        $this->snapshot = $snapshot;
        $this->filters = array_merge(
            [
                'showpast' => true,
                'onlywithdeps' => false,
                'components' => [],
            ],
            $filters
        );
    }

    /**
     * Build template context for templates/timeline.mustache.
     *
     * @param renderer_base $output Renderer (unused, required by interface).
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $course = $this->snapshot->course;
        $collector = new date_collector();
        $depindex = new dependency_index($this->snapshot->cms);
        $allentries = $collector->collect($this->snapshot->cms);
        $now = time();
        $dateformat = get_string('strftimedaydatetime', 'core_langconfig');
        $dayformat = get_string('strftimedaydate', 'core_langconfig');
        $timeformat = get_string('strftimetime24', 'core_langconfig');

        // Apply filters.
        $entries = [];
        foreach ($allentries as $entry) {
            if (!$this->filters['showpast'] && $entry['timestamp'] < $now) {
                continue;
            }
            if ($this->filters['onlywithdeps']
                && !$depindex->has_dependents($entry['cmid'])) {
                continue;
            }
            if (!empty($this->filters['components'])
                && !in_array($entry['component'], $this->filters['components'], true)) {
                continue;
            }
            $entries[] = $entry;
        }

        // Group by day, then by timestamp within the day.
        $dayGroups = [];
        foreach ($entries as $entry) {
            $daykey = date('Y-m-d', $entry['timestamp']);
            $timekey = $entry['timestamp'];
            if (!isset($dayGroups[$daykey])) {
                $dayGroups[$daykey] = [
                    'daykey' => $daykey,
                    'dayformatted' => userdate($entry['timestamp'], $dayformat),
                    'dayts' => strtotime($daykey . ' 00:00:00'),
                    'ispast' => false,
                    'slots' => [],
                ];
            }
            if (!isset($dayGroups[$daykey]['slots'][$timekey])) {
                $dayGroups[$daykey]['slots'][$timekey] = [
                    'timekey' => (int) $timekey,
                    'timeformatted' => userdate((int) $timekey, $timeformat),
                    'timestamp' => (int) $timekey,
                    'ispast' => (int) $timekey < $now,
                    'entries' => [],
                ];
            }
            $dayGroups[$daykey]['slots'][$timekey]['entries'][] = [
                'cmid' => $entry['cmid'],
                'name' => $entry['name'],
                'modname' => $entry['modname'],
                'component' => $entry['component'],
                'field' => $entry['fieldlabel'],
                'source' => $entry['source'],
                'activityurl' => (new \moodle_url(
                    '/mod/' . $entry['modname'] . '/view.php',
                    ['id' => $entry['cmid']]
                ))->out(false),
                'editurl' => (new \moodle_url(
                    '/course/modedit.php',
                    ['update' => $entry['cmid'], 'return' => 1]
                ))->out(false),
                'dashboardanchor' => (new \moodle_url(
                    '/local/coursectrl/index.php',
                    ['courseid' => $course->id]
                ))->out(false) . '#cm-' . $entry['cmid'],
            ];
        }

        // Convert to flat arrays and mark past days.
        $days = [];
        ksort($dayGroups);
        foreach ($dayGroups as $day) {
            // Flatten slots, sorted by time.
            ksort($day['slots']);
            $slots = array_values($day['slots']);
            $day['slots'] = $slots;
            $day['slotcount'] = count($slots);
            $day['ispast'] = !empty($slots) && end($slots)['ispast'];
            $days[] = $day;
        }

        // Build component filter options from registered components.
        $components = [];
        foreach ($allentries as $entry) {
            $components[$entry['component']] = $entry['modname'];
        }
        $componentoptions = [];
        foreach ($components as $component => $modname) {
            $componentoptions[] = [
                'value' => $component,
                'label' => $modname,
                'selected' => in_array($component, $this->filters['components'], true),
            ];
        }

        return [
            'courseid' => $course->id,
            'coursefullname' => format_string($course->fullname),
            'sesskey' => sesskey(),
            'days' => $days,
            'hasdays' => count($days) > 0,
            'totalentries' => count($entries),
            'totaldays' => count($days),
            'showpast' => $this->filters['showpast'],
            'onlywithdeps' => $this->filters['onlywithdeps'],
            'componentoptions' => $componentoptions,
            'hascomponentoptions' => count($componentoptions) > 0,
            'dashboardurl' => (new \moodle_url(
                '/local/coursectrl/index.php',
                ['courseid' => $course->id]
            ))->out(false),
            'manageurl' => (new \moodle_url(
                '/local/coursectrl/manage.php',
                ['courseid' => $course->id]
            ))->out(false),
            'timelineurl' => (new \moodle_url(
                '/local/coursectrl/timeline.php',
                ['courseid' => $course->id]
            ))->out(false),
        ];
    }
}
