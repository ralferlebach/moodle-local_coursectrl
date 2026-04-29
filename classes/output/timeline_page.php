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
 * v3 adds:
 *   - 'immediateapply' preference passed through to template
 *   - field name per entry (so the delete action can target a single field)
 *   - deletability flag per entry (only adapter-sourced fields are deletable)
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\output;
use local_coursectrl\local\field_label_resolver;
use local_coursectrl\local\analysis\calendar_grid_builder;
use local_coursectrl\local\analysis\date_collector;
use local_coursectrl\local\visualization\gantt_dataset_builder;
use local_coursectrl\manager\calendar_manager;
use local_coursectrl\manager\textreview_manager;
use local_coursectrl\local\analysis\dependency_index;
use local_coursectrl\local\inventory\inventory_snapshot;
use local_coursectrl\local\persistent\text_hit;
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
     *                                     - components (string[])
     *                                     - showcalendar (bool)
     *                                     - immediateapply (bool).
     */
    public function __construct(inventory_snapshot $snapshot, array $filters = []) {
        $this->snapshot = $snapshot;
        $this->filters = array_merge(
            [
                'showpast' => true,
                'onlywithdeps' => false,
                'components' => [],
                'showcalendar' => true,
                'immediateapply' => false,
                'tab' => 'timeline',
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
        $dayformat = get_string('strftimedaydate', 'core_langconfig');
        $timeformat = get_string('strftimetime24', 'core_langconfig');

        $gridbuilder = new calendar_grid_builder();
        $calman = new calendar_manager();
        $months = $gridbuilder->build(
            (int) $course->startdate,
            $course->enddate,
            $allentries,
            $now,
            $calman
        );

        $entries = [];
        foreach ($allentries as $entry) {
            if (!$this->filters['showpast'] && $entry['timestamp'] < $now) {
                continue;
            }
            if (
                $this->filters['onlywithdeps']
                && !$depindex->has_dependents($entry['cmid'])
            ) {
                continue;
            }
            if (
                !empty($this->filters['components'])
                && !in_array($entry['component'], $this->filters['components'], true)
            ) {
                continue;
            }
            $entries[] = $entry;
        }

        $daygroups = [];
        foreach ($entries as $entry) {
            $daykey = date('Y-m-d', $entry['timestamp']);
            $timekey = $entry['timestamp'];
            if (!isset($daygroups[$daykey])) {
                $daygroups[$daykey] = [
                    'daykey' => $daykey,
                    'dayformatted' => userdate($entry['timestamp'], $dayformat),
                    'dayts' => strtotime($daykey . ' 00:00:00'),
                    'ispast' => false,
                    'slots' => [],
                ];
            }
            if (!isset($daygroups[$daykey]['slots'][$timekey])) {
                $daygroups[$daykey]['slots'][$timekey] = [
                    'timekey' => (int) $timekey,
                    'timeformatted' => userdate((int) $timekey, $timeformat),
                    'timestamp' => (int) $timekey,
                    'ispast' => (int) $timekey < $now,
                    'entries' => [],
                ];
            }
            $daygroups[$daykey]['slots'][$timekey]['entries'][] = [
                'cmid' => $entry['cmid'],
                'name' => $entry['name'],
                'modname' => $entry['modname'],
                'component' => $entry['component'],
                'field' => $entry['fieldlabel'],
                'source' => $entry['source'],
                'deletable' => in_array($entry['source'], ['adapter', 'cm', 'availability'], true),
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

        $days = [];
        ksort($daygroups);
        foreach ($daygroups as $day) {
            ksort($day['slots']);
            $slots = array_values($day['slots']);
            $day['slots'] = $slots;
            $day['slotcount'] = count($slots);
            $day['ispast'] = !empty($slots) && end($slots)['ispast'];
            $days[] = $day;
        }

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
            'months' => $months,
            'hascalendar' => count($months) > 0,
            'days' => $days,
            'hasdays' => count($days) > 0,
            'totalentries' => count($entries),
            'totaldays' => count($days),
            'showpast' => $this->filters['showpast'],
            'onlywithdeps' => $this->filters['onlywithdeps'],
            'showcalendar' => $this->filters['showcalendar'],
            'immediateapply' => $this->filters['immediateapply'],
            'componentoptions' => $componentoptions,
            'hascomponentoptions' => count($componentoptions) > 0,
            'groupoptions' => [],
            'hasgroupoptions' => false,
            'activegroupid' => 0,
            'dashboardurl' => (new \moodle_url(
                '/local/coursectrl/index.php',
                ['courseid' => $course->id]
            ))->out(false),
            'manageurl' => (new \moodle_url(
                '/local/coursectrl/manage.php',
                ['courseid' => $course->id]
            ))->out(false),
            'gantt_json' => json_encode($ganttdata = $this->build_gantt_data(
                $this->snapshot->sections,
                $this->snapshot->cms,
                (int) $this->snapshot->course->id
            )),
            'gantt' => $ganttdata,
            'gantt_hasdata' => !empty($ganttdata['hasdata']),
            'activetab' => $this->filters['tab'] ?? 'timeline',
            'tab_timeline'   => ($this->filters['tab'] ?? 'timeline') === 'timeline',
            'tab_textreview' => ($this->filters['tab'] ?? 'timeline') === 'textreview',
            'tab_gantt'      => ($this->filters['tab'] ?? 'timeline') === 'gantt',
            'focusdaykey'    => $this->filters['focusdaykey'] ?? '',
            'hasfocusdaykey' => !empty($this->filters['focusdaykey']),
            'timelineurl' => (new \moodle_url(
                '/local/coursectrl/timeline.php',
                ['courseid' => $course->id]
            ))->out(false),
            'shifturl' => (new \moodle_url(
                '/local/coursectrl/shift.php',
                ['courseid' => $course->id]
            ))->out(false),
        ] + $this->build_textreview_context($course->id);
    }
    /**
     * Build textreview context variables for the Textprüfung tab.
     *
     * Loads persisted text_hit records for the course, pre-populates the
     * delta inputs from the shift that triggered this tab, and surfaces any
     * collision warnings that were stored in the PHP session by shift.php.
     *
     * @param int $courseid The course id.
     * @return array
     */
    private function build_textreview_context(int $courseid): array {
        $deltadays    = (int) ($this->filters['textreview_delta_days'] ?? 0);
        $deltahours   = (int) ($this->filters['textreview_delta_hours'] ?? 0);
        $deltaminutes = (int) ($this->filters['textreview_delta_minutes'] ?? 0);
        $fromshift  = !empty($this->filters['from_shift']);
        $batchid    = (int) ($this->filters['shift_batchid'] ?? 0);

        // Read and clear collision notices stored by shift.php.
        $collisions = [];
        $sessionkey = 'coursectrl_collisions_' . $batchid;
        if ($batchid && !empty($_SESSION[$sessionkey])) {
            $raw = $_SESSION[$sessionkey];
            unset($_SESSION[$sessionkey]);
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                foreach ($decoded as $msg) {
                    $collisions[] = ['message' => $msg];
                }
            }
        }

        $hits = text_hit::get_records(['courseid' => $courseid], 'entitytype, entityid, fieldname');
        if (empty($hits)) {
            return [
                'textreview_hasrows' => false,
                'textreview_rows' => [],
                'textreview_delta_days' => $deltadays,
                'textreview_delta_hours' => $deltahours,
            'textreview_delta_minutes' => $deltaminutes,
                'textreview_from_shift' => $fromshift,
                'textreview_hascollisions' => !empty($collisions),
                'textreview_collisions' => $collisions,
            ];
        }
        $rows = [];
        foreach ($hits as $hit) {
            $conf = $hit->get('confidence');
            $contextraw = $hit->get('contextjson');
            $ctx = $contextraw ? json_decode($contextraw, true) : [];
            $hitentitytype = $hit->get('entitytype');
            $hitentityid   = (int) $hit->get('entityid');
            $hitcmname  = '';
            $hitcmurl   = '';
            $hitmodname = '';
            if ($hitentitytype === 'cm') {
                $cmobj = get_coursemodule_from_id('', $hitentityid, 0, false, IGNORE_MISSING);
                if ($cmobj) {
                    $hitcmname  = $cmobj->name;
                    $hitmodname = $cmobj->modname;
                    $hitcmurl   = (new \moodle_url(
                        '/mod/' . $hitmodname . '/view.php',
                        ['id' => $hitentityid]
                    ))->out(false);
                }
            }
            $rows[] = [
                'id' => $hit->get('id'),
                'entitytype' => $hitentitytype,
                'entityid' => $hitentityid,
                'cmname'  => $hitcmname,
                'cmurl'   => $hitcmurl,
                'modname' => $hitmodname,
                'hascm'   => !empty($hitcmname),
                'fieldname' => field_label_resolver::resolve(
                    (string) $hit->get('fieldname'),
                    $hitmodname,
                    $hitentitytype
                ),
                'matchedtext' => $hit->get('matchedtext'),
                'normalizedvalue' => $hit->get('normalizedvalue'),
                'hasnormalized' => !empty($hit->get('normalizedvalue')),
                'confidence' => $conf,
                'selectable' => $conf !== text_hit::CONFIDENCE_INFORMATIONAL,
                'issafe' => $conf === text_hit::CONFIDENCE_SAFE,
                'isambiguous' => $conf === text_hit::CONFIDENCE_AMBIGUOUS,
                'isinformational' => $conf === text_hit::CONFIDENCE_INFORMATIONAL,
                'contextbefore' => $ctx['before'] ?? '',
                'contextafter' => $ctx['after'] ?? '',
                'contextbefore_short' => mb_substr($ctx['before'] ?? '', -30),
                'contextafter_short'  => mb_substr($ctx['after'] ?? '', 0, 30),
                'normalizedts' => $hit->get('normalizedvalue')
                    ? (int) strtotime((string) $hit->get('normalizedvalue'))
                    : 0,
            ];
        }
        // Sort rows chronologically by normalised date value.
        usort($rows, function (array $a, array $b): int {
            $ta = $a['normalizedvalue'] ? strtotime((string)$a['normalizedvalue']) : 0;
            $tb = $b['normalizedvalue'] ? strtotime((string)$b['normalizedvalue']) : 0;
            return $ta <=> $tb;
        });
        return [
            'textreview_hasrows' => count($rows) > 0,
            'textreview_rows' => $rows,
            'textreview_delta_days' => $deltadays,
            'textreview_delta_hours' => $deltahours,
            'textreview_delta_minutes' => $deltaminutes,
            'textreview_from_shift' => $fromshift,
            'textreview_hascollisions' => !empty($collisions),
            'textreview_collisions' => $collisions,
        ];
    }

    /**
     * Build Gantt dataset for the 'Grafische Übersicht' tab.
     *
     * @param array $cms CMs keyed by cmid.
     * @return array Gantt dataset export.
     */
    private function build_gantt_data(array $sections, array $cms, int $courseid): array {
        $calman  = new calendar_manager();
        $builder = new gantt_dataset_builder();

        // Resolve format-aware section display names.
        // get_section_info() requires the sections to exist in the DB;
        // fall back to section_item->name when the course is not in DB
        // (unit-test scenarios with fake course ids).
        $sectionnames = [];
        try {
            $course  = get_course($courseid);
            $modinfo = get_fast_modinfo($course);
            foreach ($sections as $section) {
                $info = $modinfo->get_section_info($section->sectionnum);
                if ($info !== null) {
                    $sectionnames[$section->id] = get_section_name($course, $info);
                }
            }
        } catch (\Throwable $e) {
            // Course not found or section info unavailable (e.g. in unit tests).
            // $sectionnames stays empty; build_with_structure falls back to
            // section_item->name or the generic numbered fallback.
            $modinfo = null;
        }

        // Build subsection map: cmid => child section id.
        // Moodle flags subsection-owned sections with component='mod_subsection'
        // and itemid = the subsection module instance id.
        // Keyed by section id: marks sections owned by a subsection CM.
        $subsectionsectionids = [];
        // Keyed by cmid: maps subsection CM to its child section id.
        $subsectionmap = [];
        foreach (($modinfo ? $modinfo->get_section_info_all() : []) as $sinfo) {
            if ((string) ($sinfo->component ?? '') !== 'mod_subsection') {
                continue;
            }
            $instanceid = (int) $sinfo->itemid;
            $childsectionid = (int) $sinfo->id;
            // Find the CM that owns this subsection section.
            foreach (($modinfo ? $modinfo->get_cms() : []) as $cm) {
                if ($cm->modname === 'subsection' && (int) $cm->instance === $instanceid) {
                    $subsectionmap[(int) $cm->id] = $childsectionid;
                    $subsectionsectionids[$childsectionid] = true;
                    break;
                }
            }
        }

        return $builder->build_with_structure(
            $sections,
            $cms,
            $courseid,
            $calman,
            $sectionnames,
            $subsectionmap,
            $subsectionsectionids
        );
    }
}
