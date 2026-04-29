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
 * Unified date collector for the Course Control Hub.
 *
 * Gathers all date/time entries from two sources:
 *   1. Adapter-level fields (duedate, timeopen, timeclose, etc.)
 *   2. CM-level fields (completionexpected, availability date conditions)
 *
 * Returns a flat, chronologically sorted list suitable for the dashboard
 * enrichment and the manager's chronological timeline view.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\analysis;

use local_coursectrl\local\field_label_resolver;

use local_coursectrl\local\entity\cm_item;
use local_coursectrl\manager\registry;

/**
 * Collects and merges all date entries for a course.
 */
class date_collector {
    /** @var registry */
    private registry $registry;

    /** @var availability_parser */
    private availability_parser $availparser;

    /**
     * Constructor.
     *
     * @param registry|null           $registry    Optional custom registry.
     * @param availability_parser|null $availparser Optional custom parser.
     */
    public function __construct(?registry $registry = null, ?availability_parser $availparser = null) {
        $this->registry = $registry ?? new registry();
        $this->availparser = $availparser ?? new availability_parser();
    }

    /**
     * Collect all dates for a set of cm_items.
     *
     * Each returned entry is an associative array:
     *   - cmid:      int
     *   - name:      string (activity name)
     *   - modname:   string
     *   - component: string (frankenstyle)
     *   - field:     string (field identifier)
     *   - fieldlabel: string (human-readable label)
     *   - timestamp: int (unix timestamp)
     *   - source:    string ('adapter' | 'cm' | 'availability')
     *
     * @param cm_item[] $cms Keyed by cmid.
     * @return array[] Chronologically sorted list of date entries.
     */
    public function collect(array $cms): array {
        $entries = [];

        foreach ($cms as $cm) {
            // CM-level: completionexpected.
            if ($cm->completionexpected > 0) {
                $entries[] = [
                    'cmid' => $cm->id,
                    'name' => $cm->name,
                    'modname' => $cm->modname,
                    'component' => $cm->get_component(),
                    'field' => 'completionexpected',
                    'fieldlabel' => field_label_resolver::resolve('completionexpected', $cm->modname, 'cm'),
                    'timestamp' => $cm->completionexpected,
                    'source' => 'cm',
                ];
            }

            // Availability date conditions.
            $dateconditions = $this->availparser->get_date_conditions($cm->availability);
            foreach ($dateconditions as $i => $cond) {
                if ($cond['timestamp'] > 0) {
                    $direction = $cond['direction'] === '>=' ? 'from' : 'until';
                    $entries[] = [
                        'cmid' => $cm->id,
                        'name' => $cm->name,
                        'modname' => $cm->modname,
                        'component' => $cm->get_component(),
                        'field' => 'availability_' . $direction . '_' . $i,
                        'fieldlabel' => field_label_resolver::resolve('availability_' . $direction, '', 'cm'),
                        'timestamp' => $cond['timestamp'],
                        'source' => 'availability',
                    ];
                }
            }

            // Adapter-level dates.
            $adapter = $this->registry->get_for_component($cm->get_component());
            if ($adapter !== null) {
                try {
                    $description = $adapter->describe_instance($cm->id);
                    $dates = $description['dates'] ?? [];
                    foreach ($dates as $fieldname => $value) {
                        if ((int) $value > 0) {
                            $entries[] = [
                                'cmid' => $cm->id,
                                'name' => $cm->name,
                                'modname' => $cm->modname,
                                'component' => $cm->get_component(),
                                'field' => $fieldname,
                                'fieldlabel' => field_label_resolver::resolve($fieldname, $cm->modname, 'cm'),
                                'timestamp' => (int) $value,
                                'source' => 'adapter',
                            ];
                        }
                    }
                } catch (\Throwable $e) {
                    // Adapter failed for this CM, skip gracefully.
                    continue;
                }
            }
        }

        // Sort chronologically.
        usort($entries, fn($a, $b) => $a['timestamp'] <=> $b['timestamp']);

        return $entries;
    }

    /**
     * Collect and group entries by day (Y-m-d).
     *
     * @param cm_item[] $cms Keyed by cmid.
     * @return array<string, array[]> Keyed by 'Y-m-d' date string.
     */
    public function collect_grouped_by_day(array $cms): array {
        $entries = $this->collect($cms);
        $grouped = [];
        foreach ($entries as $entry) {
            $day = date('Y-m-d', $entry['timestamp']);
            $grouped[$day][] = $entry;
        }
        return $grouped;
    }

    /**
     * Collect and group entries by cmid.
     *
     * @param cm_item[] $cms Keyed by cmid.
     * @return array<int, array[]> Keyed by cmid.
     */
    public function collect_grouped_by_cm(array $cms): array {
        $entries = $this->collect($cms);
        $grouped = [];
        foreach ($entries as $entry) {
            $grouped[$entry['cmid']][] = $entry;
        }
        return $grouped;
    }
}
