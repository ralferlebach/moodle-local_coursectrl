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
 * Renderable for the Course Control Hub course dashboard (v2).
 *
 * Enriched version that includes per-CM dates, availability conditions,
 * completion settings, dependency cross-links and light-weight warnings.
 * Warnings are produced transiently via consistency_runner; nothing is
 * persisted to the database.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\output;

use local_coursectrl\local\analysis\availability_parser;
use local_coursectrl\local\analysis\consistency_runner;
use local_coursectrl\local\analysis\date_collector;
use local_coursectrl\local\analysis\dependency_index;
use local_coursectrl\local\inventory\inventory_snapshot;
use local_coursectrl\manager\registry;
use renderable;
use renderer_base;
use templatable;

/**
 * Enriched dashboard renderable with dates, deps and warnings.
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

        // Build analysis structures.
        $depindex = new dependency_index($this->snapshot->cms);
        $datecollector = new date_collector();
        $datesbycm = $datecollector->collect_grouped_by_cm($this->snapshot->cms);
        $circular = $depindex->find_circular_deps();
        $circularset = $this->build_circular_set($circular);
        $runner = new consistency_runner();
        $checkresults = $runner->get_warnings($this->snapshot->cms, $depindex, $datesbycm);
        $dateformat = get_string('strftimedaydatetime', 'core_langconfig');

        // Build CM name lookup for cross-linking.
        $cmnames = [];
        foreach ($this->snapshot->cms as $cm) {
            $cmnames[$cm->id] = $cm->name;
        }

        $cmsbysection = [];
        $totalwarnings = 0;
        foreach ($this->snapshot->cms as $cm) {
            $cmdata = $this->build_cm_context(
                $cm,
                $depindex,
                $datesbycm[$cm->id] ?? [],
                $cmnames,
                $circularset,
                $checkresults[$cm->id] ?? [],
                $dateformat
            );
            if ($cmdata['haswarnings']) {
                $totalwarnings++;
            }
            $cmsbysection[$cm->sectionid][] = $cmdata;
        }

        $sections = [];
        foreach ($this->snapshot->sections as $section) {
            $sectioncms = $cmsbysection[$section->id] ?? [];
            $sections[] = [
                'id' => $section->id,
                'sectionnum' => $section->sectionnum,
                'name' => $section->name ?? '',
                'hasname' => $section->name !== null && $section->name !== '',
                'visible' => $section->visible,
                'hassummary' => $section->summary !== '',
                'cms' => $sectioncms,
                'cmcount' => count($sectioncms),
                'hascms' => count($sectioncms) > 0,
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
            'warningcount' => $totalwarnings,
            'haswarnings' => $totalwarnings > 0,
            'manageurl' => (new \moodle_url(
                '/local/coursectrl/manage.php',
                ['courseid' => $course->id]
            ))->out(false),
            'textreviewurl' => (new \moodle_url(
                '/local/coursectrl/textreview.php',
                ['courseid' => $course->id]
            ))->out(false),
            'timelineurl' => (new \moodle_url(
                '/local/coursectrl/timeline.php',
                ['courseid' => $course->id]
            ))->out(false),
            'graphurl' => (new \moodle_url(
                '/local/coursectrl/graph.php',
                ['courseid' => $course->id]
            ))->out(false),
        ];
    }

    /**
     * Build template context for a single CM.
     *
     * @param \local_coursectrl\local\entity\cm_item $cm           The CM entity.
     * @param dependency_index                       $depindex     Dependency index.
     * @param array                                  $dates        Date entries for this CM.
     * @param array                                  $cmnames      Lookup: cmid → name.
     * @param array                                  $circularset  Set of cmids in circular deps.
     * @param array                                  $checkresults Consistency issues for this CM.
     * @param string                                 $dateformat   Moodle date format string.
     * @return array
     */
    private function build_cm_context(
        \local_coursectrl\local\entity\cm_item $cm,
        dependency_index $depindex,
        array $dates,
        array $cmnames,
        array $circularset,
        array $checkresults,
        string $dateformat
    ): array {
        // Activity URL and edit URL.
        $activityurl = (new \moodle_url('/mod/' . $cm->modname . '/view.php', ['id' => $cm->id]))->out(false);
        $editurl = (new \moodle_url(
            '/course/modedit.php',
            ['update' => $cm->id, 'return' => 1]
        ))->out(false);

        // Format dates.
        $formatteddates = [];
        foreach ($dates as $entry) {
            $formatteddates[] = [
                'field' => $entry['fieldlabel'],
                'source' => $entry['source'],
                'formatted' => userdate($entry['timestamp'], $dateformat),
                'timestamp' => $entry['timestamp'],
                'ispast' => $entry['timestamp'] < time(),
            ];
        }

        // Prerequisites (activities this CM depends on).
        $prerequisites = [];
        foreach ($depindex->get_prerequisites($cm->id) as $depcmid) {
            $prerequisites[] = [
                'cmid' => $depcmid,
                'name' => $cmnames[$depcmid] ?? 'cmid ' . $depcmid,
                'anchor' => '#cm-' . $depcmid,
            ];
        }

        // Dependents (activities that depend on this CM).
        $dependents = [];
        foreach ($depindex->get_dependents($cm->id) as $depcmid) {
            $dependents[] = [
                'cmid' => $depcmid,
                'name' => $cmnames[$depcmid] ?? 'cmid ' . $depcmid,
                'anchor' => '#cm-' . $depcmid,
            ];
        }

        // Date-based availability restrictions.
        $daterestrictions = [];
        foreach ($depindex->get_date_restrictions($cm->id) as $cond) {
            if ($cond['timestamp'] > 0) {
                $daterestrictions[] = [
                    'direction' => $cond['direction'] === '>=' ? 'from' : 'until',
                    'formatted' => userdate($cond['timestamp'], $dateformat),
                    'timestamp' => $cond['timestamp'],
                ];
            }
        }

        // Completion info.
        $completionlabel = '';
        if ($cm->completion === 1) {
            $completionlabel = get_string('dashboard_completion_manual', 'local_coursectrl');
        } else if ($cm->completion === 2) {
            $completionlabel = get_string('dashboard_completion_auto', 'local_coursectrl');
        }

        // Warnings: circular dependency (from dep index) + consistency issues.
        $warnings = [];
        if (isset($circularset[$cm->id])) {
            $warnings[] = [
                'type' => 'circular',
                'icon' => '❗',
                'message' => get_string('warning_circular_dep', 'local_coursectrl'),
            ];
        }
        foreach ($checkresults as $issue) {
            $formatted = $this->format_check_result($issue);
            if (!empty($formatted)) {
                $warnings[] = $formatted;
            }
        }

        return [
            'cmid' => $cm->id,
            'name' => $cm->name,
            'modname' => $cm->modname,
            'component' => $cm->get_component(),
            'visible' => $cm->visible,
            'activityurl' => $activityurl,
            'editurl' => $editurl,
            'hascompletion' => $cm->completion > 0,
            'completionlabel' => $completionlabel,
            'hascompletionexpected' => $cm->completionexpected > 0,
            'completionexpected' => $cm->completionexpected > 0
                ? userdate($cm->completionexpected, $dateformat) : '',
            'hasavailability' => $cm->availability !== null && $cm->availability !== '',
            'dates' => $formatteddates,
            'hasdates' => count($formatteddates) > 0,
            'prerequisites' => $prerequisites,
            'hasprerequisites' => count($prerequisites) > 0,
            'dependents' => $dependents,
            'hasdependents' => count($dependents) > 0,
            'daterestrictions' => $daterestrictions,
            'hasdaterestrictions' => count($daterestrictions) > 0,
            'warnings' => $warnings,
            'haswarnings' => count($warnings) > 0,
        ];
    }

    /**
     * Format a structured consistency-check issue as a template-ready warning array.
     *
     * Returns an empty array for unknown issue types so callers can safely filter.
     *
     * @param array $issue Structured issue from consistency_runner::get_warnings().
     * @return array Warning array with 'type', 'icon', 'message' keys, or [].
     */
    private function format_check_result(array $issue): array {
        $type = $issue['type'] ?? '';
        if ($type === 'temporal_conflict') {
            return [
                'type' => 'temporal_conflict',
                'icon' => '⚠️',
                'message' => get_string(
                    'warning_temporal_conflict',
                    'local_coursectrl',
                    (object)[
                        'field_early' => $issue['field_early'],
                        'field_late' => $issue['field_late'],
                    ]
                ),
            ];
        }
        if ($type === 'dangling_dep') {
            return [
                'type' => 'dangling_dep',
                'icon' => '⚠️',
                'message' => get_string(
                    'warning_dangling_dep',
                    'local_coursectrl',
                    (object)['cmid' => $issue['depcmid']]
                ),
            ];
        }
        if ($type === 'impossible_dep') {
            return [
                'type' => 'impossible_dep',
                'icon' => '⚠️',
                'message' => get_string(
                    'warning_impossible_dep',
                    'local_coursectrl',
                    (object)['name' => $issue['depname']]
                ),
            ];
        }
        return [];
    }

    /**
     * Build a lookup set of cmids involved in circular dependencies.
     *
     * @param array $circular Circular pairs from dependency_index.
     * @return array<int, true> cmid → true.
     */
    private function build_circular_set(array $circular): array {
        $set = [];
        foreach ($circular as $pair) {
            $set[$pair['a']] = true;
            $set[$pair['b']] = true;
        }
        return $set;
    }
}
