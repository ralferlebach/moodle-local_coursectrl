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
        global $DB;
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
        $withdatescount = 0;
        foreach ($this->snapshot->cms as $cm) {
            $hasdates = $cmswithdates[$cm->id];
            if ($hasdates) {
                $withdatescount++;
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

        // Resolve locale-aware section names and build subsection nesting.
        $sectionnamesbyid = [];
        try {
            $modinfo = get_fast_modinfo($course->id);
            foreach ($modinfo->get_section_info_all() as $sinfo) {
                $sectionnamesbyid[(int) $sinfo->id] = get_section_name($course, $sinfo);
            }
        } catch (\Throwable $e) {
            // Non-fatal: section names fall back to raw name or section number.
            debugging('local_coursectrl: get_section_name failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        // Build child-section map: Strategy 1 (DB), 2 (delegatesection), 3 (name).
        $childsectionbysubcmid = [];
        $subsectionsectionids = [];
        $allsnapshotsections = $this->snapshot->sections;
        $subsecrows = $DB->get_records_select(
            'course_sections',
            "course = ? AND component = 'mod_subsection' AND itemid > 0",
            [$course->id],
            '',
            'id,itemid'
        );
        foreach ($subsecrows as $row) {
            $sec = $allsnapshotsections[(int) $row->id] ?? null;
            if ($sec !== null) {
                $childsectionbysubcmid[(int) $row->itemid] = $sec;
                $subsectionsectionids[(int) $row->id] = true;
            }
        }
        // Strategy 2: cm_info->delegatesection + Strategy 3: name-matching.
        try {
            $minfo = get_fast_modinfo($course->id);
            foreach ($minfo->get_cms() as $cminfo) {
                if ($cminfo->modname !== 'subsection') {
                    continue;
                }
                $cmid = (int) $cminfo->id;
                if (isset($childsectionbysubcmid[$cmid])) {
                    continue;
                }
                if (isset($cminfo->delegatesection) && $cminfo->delegatesection !== null) {
                    $dsid = (int) $cminfo->delegatesection->id;
                    $sec = $allsnapshotsections[$dsid] ?? null;
                    if ($sec !== null) {
                        $childsectionbysubcmid[$cmid] = $sec;
                        $subsectionsectionids[$dsid] = true;
                    }
                    continue;
                }
                // Strategy 3: name-match.
                $cmname = $cminfo->name;
                foreach ($allsnapshotsections as $sec) {
                    if ((string) $sec->name === (string) $cmname) {
                        $childsectionbysubcmid[$cmid] = $sec;
                        $subsectionsectionids[$sec->id] = true;
                        break;
                    }
                }
            }
        } catch (\Throwable $e) {
            debugging('local_coursectrl: subsection map failed: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
        // Sort sections by sectionnum.
        $allsections = array_values($this->snapshot->sections);
        usort($allsections, fn ($a, $b) => $a->sectionnum <=> $b->sectionnum);
        $sections = [];
        foreach ($allsections as $section) {
            if (isset($subsectionsectionids[$section->id])) {
                continue; // Rendered inline under subsection CM.
            }
            $rawcms = $cmsbysection[$section->id] ?? [];
            $sectioncms = [];
            $sectionhasdates = false;
            foreach ($rawcms as $cmdata) {
                if ($cmdata['hasdates']) {
                    $sectionhasdates = true;
                }
                // If this CM is a subsection, embed its child CMs.
                if ($cmdata['modname'] === 'subsection') {
                    $childsec = $childsectionbysubcmid[(int) $cmdata['cmid']] ?? null;
                    $childcms = [];
                    if ($childsec !== null) {
                        foreach ($cmsbysection[$childsec->id] ?? [] as $childcm) {
                            $childcm['depth'] = 2;
                            $childcm['isindented'] = true;
                            $childcms[] = $childcm;
                            if ($childcm['hasdates']) {
                                $sectionhasdates = true;
                            }
                        }
                    }
                    $cmdata['is_subsection_header'] = true;
                    $cmdata['subsection_cms'] = $childcms;
                    $cmdata['has_subsection_cms'] = !empty($childcms);
                    $cmdata['depth'] = 1;
                    $cmdata['subsection_name'] = $sectionnamesbyid[$childsec->id ?? 0]
                        ?? ($cmdata['name'] ?? '');
                } else {
                    $cmdata['is_subsection_header'] = false;
                    $cmdata['subsection_cms'] = [];
                    $cmdata['has_subsection_cms'] = false;
                    $cmdata['depth'] = 1;
                }
                $sectioncms[] = $cmdata;
            }
            $sectionname = $sectionnamesbyid[$section->id]
                ?? ($section->name ?? get_string('section') . ' ' . $section->sectionnum);
            $sections[] = [
                'id'         => $section->id,
                'sectionnum' => $section->sectionnum,
                'name'       => $sectionname,
                'hasname'    => true,
                'visible'    => $section->visible,
                'cms'        => $sectioncms,
                'cmcount'    => count($sectioncms),
                'hascms'     => count($sectioncms) > 0,
                'hasdates'   => $sectionhasdates,
                'nodates'    => !$sectionhasdates,
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
            'withdatescount' => $withdatescount,
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
