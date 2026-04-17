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
 * Transforms the inventory snapshot into a template context suitable for
 * the manage.mustache CM-selector form. Sections are listed with their
 * course modules as checkbox groups so that users can pick which CMs to
 * include in a bulk action.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\output;

use local_coursectrl\local\inventory\inventory_snapshot;
use local_coursectrl\manager\registry;
use renderable;
use renderer_base;
use templatable;

/**
 * Renderable for the bulk-action manage page.
 */
class manage_page implements renderable, templatable {
    /** @var inventory_snapshot The course inventory. */
    protected inventory_snapshot $snapshot;

    /** @var string[] Components with a registered adapter. */
    protected array $supportedcomponents;

    /**
     * Constructor.
     *
     * @param inventory_snapshot $snapshot            The inventory snapshot.
     * @param string[]           $supportedcomponents Frankenstyle component
     *                                                names that have a
     *                                                registered adapter.
     */
    public function __construct(inventory_snapshot $snapshot, array $supportedcomponents) {
        $this->snapshot = $snapshot;
        $this->supportedcomponents = $supportedcomponents;
    }

    /**
     * Build template context for templates/manage.mustache.
     *
     * @param renderer_base $output Renderer (unused, required by interface).
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $course = $this->snapshot->course;

        $cmsbysection = [];
        $supportedcount = 0;
        foreach ($this->snapshot->cms as $cm) {
            $issupported = in_array($cm->get_component(), $this->supportedcomponents, true);
            if ($issupported) {
                $supportedcount++;
            }
            $cmsbysection[$cm->sectionid][] = [
                'cmid' => $cm->id,
                'name' => $cm->name,
                'modname' => $cm->modname,
                'component' => $cm->get_component(),
                'visible' => $cm->visible,
                'supported' => $issupported,
                'sectionid' => $cm->sectionid,
            ];
        }

        $sections = [];
        foreach ($this->snapshot->sections as $section) {
            $sectioncms = $cmsbysection[$section->id] ?? [];
            $sectionhassupported = false;
            foreach ($sectioncms as $cm) {
                if ($cm['supported']) {
                    $sectionhassupported = true;
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
                'hassupported' => $sectionhassupported,
            ];
        }

        $actions = [
            [
                'value' => 'shift_dates',
                'label' => get_string('action_shift_dates', 'local_coursectrl'),
                'selected' => true,
            ],
        ];

        return [
            'courseid' => $course->id,
            'coursefullname' => format_string($course->fullname),
            'sesskey' => sesskey(),
            'previewurl' => (new \moodle_url('/local/coursectrl/preview.php'))->out(false),
            'dashboardurl' => (new \moodle_url(
                '/local/coursectrl/index.php',
                ['courseid' => $course->id]
            ))->out(false),
            'actions' => $actions,
            'sections' => $sections,
            'hassections' => count($sections) > 0,
            'supportedcount' => $supportedcount,
        ];
    }
}
