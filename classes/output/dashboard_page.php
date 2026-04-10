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
 * Renderable for the Course Control Hub course dashboard.
 *
 * @package    local_coursectrl
 * @copyright  2026 Course Control Hub Contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\output;

use local_coursectrl\local\inventory\inventory_snapshot;
use renderable;
use renderer_base;
use templatable;

/**
 * Pure transformer from inventory_snapshot to mustache template context.
 *
 * Holds no Moodle dependencies beyond the renderer interfaces. Tests can
 * instantiate it with a hand-built snapshot and assert the export shape
 * without booting a full page.
 */
class dashboard_page implements renderable, templatable {
    /** @var inventory_snapshot The snapshot to render. */
    protected inventory_snapshot $snapshot;

    /**
     * Constructor.
     *
     * @param inventory_snapshot $snapshot The snapshot to render.
     */
    public function __construct(inventory_snapshot $snapshot) {
        $this->snapshot = $snapshot;
    }

    /**
     * Build the template context for templates/dashboard.mustache.
     *
     * @param renderer_base $output Renderer for any nested components.
     * @return array<string,mixed>
     */
    public function export_for_template(renderer_base $output): array {
        $course = $this->snapshot->course;

        $cmsbysection = [];
        foreach ($this->snapshot->cms as $cm) {
            $cmsbysection[$cm->sectionid][] = [
                'cmid' => $cm->id,
                'name' => $cm->name,
                'modname' => $cm->modname,
                'component' => $cm->get_component(),
                'visible' => $cm->visible,
                'hascompletion' => $cm->completion > 0,
                'hasavailability' => $cm->availability !== null && $cm->availability !== '',
            ];
        }

        $sections = [];
        foreach ($this->snapshot->sections as $section) {
            $sections[] = [
                'id' => $section->id,
                'sectionnum' => $section->sectionnum,
                'name' => $section->name ?? '',
                'hasname' => $section->name !== null && $section->name !== '',
                'visible' => $section->visible,
                'hassummary' => $section->summary !== '',
                'cms' => $cmsbysection[$section->id] ?? [],
                'cmcount' => count($cmsbysection[$section->id] ?? []),
            ];
        }

        return [
            'courseid' => $course->id,
            'coursefullname' => $course->fullname,
            'courseshortname' => $course->shortname,
            'coursestartdate' => $course->startdate,
            'courseenddate' => $course->enddate,
            'hasenddate' => $course->enddate !== null && $course->enddate > 0,
            'coursevisible' => $course->visible,
            'sectioncount' => $this->snapshot->count_sections(),
            'cmcount' => $this->snapshot->count_cms(),
            'textcount' => $this->snapshot->count_texts(),
            'sections' => $sections,
            'hassections' => count($sections) > 0,
        ];
    }
}
