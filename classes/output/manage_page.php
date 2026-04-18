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
 * Renderable for the bulk-action management page.
 *
 * CMs and sections are selectable when they carry at least one date field
 * (adapter date, completionexpected, or an availability date condition).
 * CMs without any date fields are listed but disabled.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\output;

use local_coursectrl\local\analysis\date_collector;
use local_coursectrl\local\inventory\inventory_snapshot;
use renderable;
use renderer_base;
use templatable;

/**
 * Renderable for the bulk-action manage page.
 */
class manage_page implements renderable, templatable {
    /** @var inventory_snapshot The course inventory. */
    protected inventory_snapshot $snapshot;

    /**
     * Constructor.
     *
     * @param inventory_snapshot $snapshot The inventory snapshot.
     * @param string[]           $supportedcomponents Unused; kept for BC.
     */
    public function __construct(inventory_snapshot $snapshot, array $supportedcomponents = []) {
        $this->snapshot = $snapshot;
    }

    /**
     * Build template context for templates/manage.mustache.
     *
     * @param renderer_base $output Renderer (unused, required by interface).
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $course = $this->snapshot->course;

        // Collect which CMs actually have date fields.
        $collector = new date_collector();
        $datesbycm = $collector->collect_grouped_by_cm($this->snapshot->cms);

        // Also check CM-level fields (completionexpected, availability dates).
        $cmswithdates = [];
        foreach ($this->snapshot->cms as $cm) {
            $hasdates = !empty($datesbycm[$cm->id]);
            // Check completionexpected.
            if (!$hasdates && $cm->completionexpected > 0) {
                $hasdates = true;
            }
            // Check availability JSON for date conditions.
            if (!$hasdates && !empty($cm->availability)) {
                $avail = json_decode($cm->availability, true);
                if (is_array($avail)) {
                    $hasdates = $this->availability_has_date($avail);
                }
            }
            $cmswithdates[$cm->id] = $hasdates;
        }

        $cmsbysection = [];
        $withDatesCount = 0;
        foreach ($this->snapshot->cms as $cm) {
            $hasdates = $cmswithdates[$cm->id];
            if ($hasdates) {
                $withDatesCount++;
            }
            $cmsbysection[$cm->sectionid][] = [
                'cmid' => $cm->id,
                'name' => $cm->name,
                'modname' => $cm->modname,
                'component' => $cm->get_component(),
                'visible' => $cm->visible,
                'hasdates' => $hasdates,
                'nodates' => !$hasdates,
                'sectionid' => $cm->sectionid,
            ];
        }

        $sections = [];
        foreach ($this->snapshot->sections as $section) {
            $sectioncms = $cmsbysection[$section->id] ?? [];
            $sectionhasdates = false;
            foreach ($sectioncms as $cmdata) {
                if ($cmdata['hasdates']) {
                    $sectionhasdates = true;
                    break;
                }
            }
            $sections[] = [
                'id' => $section->id,
                'sectionnum' => $section->sectionnum,
                'name' => $section->name ?? '',
                'hasname' => $section->name !== null && $section->name !== '',
                'visible' => $section->visible,
                'cms' => $sectioncms,
                'cmcount' => count($sectioncms),
                'hascms' => count($sectioncms) > 0,
                'hasdates' => $sectionhasdates,
                'nodates' => !$sectionhasdates,
            ];
        }

        return [
            'courseid' => $course->id,
            'coursefullname' => format_string($course->fullname),
            'sesskey' => sesskey(),
                'dashboardurl' => (new \moodle_url(
                '/local/coursectrl/index.php',
                ['courseid' => $course->id]
            ))->out(false),
            'sections' => $sections,
            'hassections' => count($sections) > 0,
            'withdatescount' => $withDatesCount,
        ];
    }

    /**
     * Recursively check whether an availability condition node contains a date condition.
     *
     * @param array $node Decoded availability JSON node.
     * @return bool
     */
    private function availability_has_date(array $node): bool {
        if (($node['type'] ?? '') === 'date') {
            return true;
        }
        foreach ($node['c'] ?? [] as $child) {
            if (is_array($child) && $this->availability_has_date($child)) {
                return true;
            }
        }
        return false;
    }
}
