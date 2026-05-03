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
 * Renderable for the Course Control Hub dashboard (Modell D).
 *
 * Cockpit layout:
 *   1. Stat tiles + collapsible calendar.
 *   2. Problem summary (errors / warnings / notices) — if any exist.
 *   3. Upcoming structured dates (left) + text hits (right).
 *   4. Full course inventory — visibility controlled by dashboard_inventory setting.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\output;
use local_coursectrl\local\field_label_resolver;
use local_coursectrl\local\analysis\calendar_grid_builder;
use local_coursectrl\local\analysis\consistency_runner;
use local_coursectrl\local\analysis\date_collector;
use local_coursectrl\local\analysis\dependency_index;
use local_coursectrl\local\inventory\inventory_snapshot;
use local_coursectrl\manager\calendar_manager;
use local_coursectrl\manager\registry;
use renderable;
use renderer_base;
use templatable;

/**
 * Dashboard renderable (Modell D cockpit layout).
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
     * @param renderer_base $output Renderer for nested components.
     * @return array<string,mixed>
     */
    public function export_for_template(renderer_base $output): array {
        global $DB;

        $course = $this->snapshot->course;
        $courseid = (int)$course->id;

        // Read settings.
        $upcomingcount = max(1, (int)(get_config('local_coursectrl', 'dashboard_upcoming_count') ?: 7));
        $warningcap = (int)(get_config('local_coursectrl', 'dashboard_warning_cap') ?: 0);
        $textfindcount = (int)(get_config('local_coursectrl', 'dashboard_textfind_count') ?: 0);
        $inventorysetting = get_config('local_coursectrl', 'dashboard_inventory') ?: 'admin_only';

        // Resolve effective caps (0 = same as upcoming).
        $effectivewarncap = $warningcap > 0 ? $warningcap : $upcomingcount;
        $effectivetextcount = $textfindcount > 0 ? $textfindcount : $upcomingcount;

        // Inventory visibility.
        $isadmin = has_capability('moodle/site:config', \context_system::instance());
        $showinventory = ($inventorysetting === 'show')
            || ($inventorysetting === 'admin_only' && $isadmin);

        // Build analysis structures.
        $depindex = new dependency_index($this->snapshot->cms);
        $datecollector = new date_collector();
        $datesbycm = $datecollector->collect_grouped_by_cm($this->snapshot->cms);
        $circular = $depindex->find_circular_deps();
        $circularset = $this->build_circular_set($circular);
        $runner = new consistency_runner();
        $allwarnings = $runner->get_warnings(
            $this->snapshot->cms,
            $depindex,
            $datesbycm,
            null,
            $course
        );
        $dateformat = get_string('strftimedaydatetime', 'core_langconfig');

        // Build URL helpers.
        $checksurl = (new \moodle_url(
            '/local/coursectrl/checks.php',
            ['courseid' => $courseid]
        ))->out(false);
        $deepurl = (new \moodle_url(
            '/local/coursectrl/checks.php',
            ['courseid' => $courseid, 'tab' => 'risks', 'run' => '1']
        ))->out(false);
        $timelineurl = (new \moodle_url(
            '/local/coursectrl/timeline.php',
            ['courseid' => $courseid]
        ))->out(false);
        $textreviewurl = (new \moodle_url(
            '/local/coursectrl/timeline.php',
            ['courseid' => $courseid, 'tab' => 'textreview']
        ))->out(false);

        // Build CM url/name/modname lookups.
        $cmurls = [];
        $cmnames = [];
        $cmmodnames = [];
        foreach ($this->snapshot->cms as $cm) {
            $cmnames[$cm->id] = $cm->name;
            $cmmodnames[$cm->id] = $cm->modname;
            $cmurls[$cm->id] = (new \moodle_url(
                '/mod/' . $cm->modname . '/view.php',
                ['id' => $cm->id]
            ))->out(false);
        }

        // Build section url/name lookups for section-type text hits.
        $sectionnames = [];
        $sectionurls  = [];
        foreach ($this->snapshot->sections as $section) {
            $sname = ($section->name !== '')
                ? format_string($section->name)
                : get_string('sectionname', 'format_topics') . ' ' . $section->sectionnum;
            $sectionnames[$section->id] = $sname;
            $sectionurls[$section->id]  = (new \moodle_url(
                '/course/view.php',
                ['id' => $courseid, 'section' => $section->sectionnum]
            ))->out(false);
        }

        // Problem summary.
        $errorrows = [];
        $warningrows = [];
        $noticerows = [];
        $errorcount = 0;
        $warningcount = 0;
        $noticecount = 0;

        foreach ($circularset as $cmid => $unused) {
            $errorcount++;
            if (count($errorrows) < $effectivewarncap) {
                $errorrows[] = $this->build_problem_row(
                    (int)$cmid,
                    $cmnames,
                    $cmurls,
                    $cmmodnames,
                    get_string('warning_circular_dep', 'local_coursectrl')
                );
            }
        }

        foreach ($allwarnings as $cmid => $issues) {
            foreach ($issues as $issue) {
                $severity = $issue['severity'] ?? 'warning';
                $msg = $this->format_issue_message($issue);
                if ($msg === '') {
                    continue;
                }
                $row = $this->build_problem_row(
                    (int)$cmid,
                    $cmnames,
                    $cmurls,
                    $cmmodnames,
                    $msg
                );
                if ($severity === 'error') {
                    $errorcount++;
                    if (count($errorrows) < $effectivewarncap) {
                        $errorrows[] = $row;
                    }
                } else if ($severity === 'notice') {
                    $noticecount++;
                    if (count($noticerows) < $effectivewarncap) {
                        $noticerows[] = $row;
                    }
                } else {
                    $warningcount++;
                    if (count($warningrows) < $effectivewarncap) {
                        $warningrows[] = $row;
                    }
                }
            }
        }

        $totalproblems = $errorcount + $warningcount + $noticecount;
        $errormore = max(0, $errorcount - count($errorrows));
        $warningmore = max(0, $warningcount - count($warningrows));
        $noticemore = max(0, $noticecount - count($noticerows));

        // Upcoming structured dates.
        $upcomingdates = $this->build_upcoming_dates(
            $datesbycm,
            $cmnames,
            $cmurls,
            $cmmodnames,
            $upcomingcount,
            $dateformat
        );

        // Text hits from DB.
        $courseurl = (new \moodle_url('/course/view.php', ['id' => $courseid]))->out(false);
        $texthits = $this->build_text_hits(
            $DB,
            $courseid,
            $effectivetextcount,
            $cmnames,
            $cmurls,
            $cmmodnames,
            $sectionnames,
            $sectionurls,
            format_string($course->fullname),
            $courseurl
        );
        $texthitsscanned = $DB->record_exists(
            'local_coursectrl_text_hit',
            ['courseid' => $courseid]
        );

        // Calendar.
        $calbuilder = new calendar_grid_builder();
        $calman = new calendar_manager();
        $allentries = $datecollector->collect($this->snapshot->cms);
        $calmonths = $calbuilder->build(
            (int)$course->startdate,
            (int)($course->enddate ?: 0),
            $allentries,
            time(),
            $calman
        );

        // Inventory (conditional).
        $sections = [];
        if ($showinventory) {
            $cmsbysection = [];
            foreach ($this->snapshot->cms as $cm) {
                $cmdata = $this->build_cm_context(
                    $cm,
                    $depindex,
                    $datesbycm[$cm->id] ?? [],
                    $cmnames,
                    $circularset,
                    $allwarnings[$cm->id] ?? [],
                    $dateformat
                );
                $cmsbysection[$cm->sectionid][] = $cmdata;
            }
            foreach ($this->snapshot->sections as $section) {
                $sectioncms = $cmsbysection[$section->id] ?? [];
                $sections[] = [
                    'id' => $section->id,
                    'sectionnum' => $section->sectionnum,
                    'name' => $section->name ?? '',
                    'hasname' => $section->name !== null && $section->name !== '',
                    'visible' => $section->visible,
                    'cms' => $sectioncms,
                    'cmcount' => count($sectioncms),
                    'hascms' => count($sectioncms) > 0,
                ];
            }
        }

        return [
            'courseid' => $courseid,
            'coursefullname' => format_string($course->fullname),
            'courseshortname' => $course->shortname,
            'coursestartdate' => $course->startdate,
            'courseenddate' => $course->enddate,
            'hasenddate' => !empty($course->enddate),
            'coursevisible' => $course->visible,
            'sectioncount' => $this->snapshot->count_sections(),
            'cmcount' => $this->snapshot->count_cms(),
            'textcount' => $this->snapshot->count_texts(),
            'months' => $calmonths,
            'hascalendar' => count($calmonths) > 0,
            'showcalendar' => (bool)get_user_preferences('local_coursectrl_showcalendar', 1),
            'hasproblems' => $totalproblems > 0,
            'totalproblems' => $totalproblems,
            'errorcount' => $errorcount,
            'warningcount' => $warningcount,
            'noticecount' => $noticecount,
            'haserrors' => $errorcount > 0,
            'haswarnings' => $warningcount > 0,
            'hasnotices' => $noticecount > 0,
            'errorrows' => $errorrows,
            'warningrows' => $warningrows,
            'noticerows' => $noticerows,
            'errormore' => $errormore,
            'warningmore' => $warningmore,
            'noticemore' => $noticemore,
            'haserrormore' => $errormore > 0,
            'haswarningmore' => $warningmore > 0,
            'hasnoticemore' => $noticemore > 0,
            'checksurl' => $checksurl,
            'deepanalysisurl' => $deepurl,
            'upcomingdates' => $upcomingdates,
            'hasupcomingdates' => count($upcomingdates) > 0,
            'texthits' => $texthits,
            'hastexthits' => count($texthits) > 0,
            'texthitsscanned' => $texthitsscanned,
            'textreviewurl' => $textreviewurl,
            'timelineurl' => $timelineurl,
            'manageurl' => (new \moodle_url(
                '/local/coursectrl/manage.php',
                ['courseid' => $courseid]
            ))->out(false),
            'graphurl' => (new \moodle_url(
                '/local/coursectrl/dependencies.php',
                ['courseid' => $courseid]
            ))->out(false),
            'historyurl' => (new \moodle_url(
                '/local/coursectrl/history.php',
                ['courseid' => $courseid]
            ))->out(false),
            'showinventory' => $showinventory,
            'isinventoryadmin' => $isadmin && $inventorysetting === 'admin_only',
            'sections' => $sections,
            'hassections' => count($sections) > 0,
        ];
    }

    /**
     * Build a single problem row for the warning summary panel.
     *
     * @param int    $cmid       Course module id.
     * @param array  $cmnames    Lookup: cmid → name.
     * @param array  $cmurls     Lookup: cmid → url.
     * @param array  $cmmodnames Lookup: cmid → modname.
     * @param string $message    Warning message.
     * @return array
     */
    private function build_problem_row(
        int $cmid,
        array $cmnames,
        array $cmurls,
        array $cmmodnames,
        string $message
    ): array {
        return [
            'cmid' => $cmid,
            'cmname' => $cmnames[$cmid] ?? 'ID ' . $cmid,
            'cmurl' => $cmurls[$cmid] ?? '#',
            'modname' => $cmmodnames[$cmid] ?? '',
            'message' => $message,
        ];
    }

    /**
     * Format a structured warning issue to a display string.
     *
     * @param array $issue Issue array from consistency_runner.
     * @return string Empty string for unknown types.
     */
    private function format_issue_message(array $issue): string {
        $type = $issue['type'] ?? '';
        if ($type === 'temporal_conflict') {
            return get_string(
                'warning_temporal_conflict',
                'local_coursectrl',
                (object)[
                    'field_early' => $issue['field_early'] ?? '',
                    'field_late' => $issue['field_late'] ?? '',
                ]
            );
        }
        if ($type === 'dangling_dep') {
            return get_string(
                'warning_dangling_dep',
                'local_coursectrl',
                (object)['cmid' => $issue['depcmid'] ?? 0]
            );
        }
        if ($type === 'impossible_dep') {
            return get_string(
                'warning_impossible_dep',
                'local_coursectrl',
                (object)['name' => $issue['depname'] ?? '']
            );
        }
        return (string)($issue['message'] ?? '');
    }

    /**
     * Build upcoming structured dates, sorted ascending, limited to count.
     *
     * @param array  $datesbycm   Date entries keyed by cmid.
     * @param array  $cmnames     Lookup: cmid → name.
     * @param array  $cmurls      Lookup: cmid → url.
     * @param array  $cmmodnames  Lookup: cmid → modname.
     * @param int    $count       Maximum entries to return.
     * @param string $dateformat  Moodle date format string.
     * @return array
     */
    private function build_upcoming_dates(
        array $datesbycm,
        array $cmnames,
        array $cmurls,
        array $cmmodnames,
        int $count,
        string $dateformat
    ): array {
        $now = time();
        $flat = [];
        foreach ($datesbycm as $cmid => $entries) {
            foreach ($entries as $entry) {
                $ts = (int)($entry['timestamp'] ?? 0);
                if ($ts < $now) {
                    continue;
                }
                $flat[] = [
                    'cmid' => (int)$cmid,
                    'cmname' => $cmnames[$cmid] ?? 'ID ' . $cmid,
                    'cmurl' => $cmurls[$cmid] ?? '#',
                    'modname' => $cmmodnames[$cmid] ?? '',
                    'field' => $entry['fieldlabel'] ?? $entry['field'] ?? '',
                    'timeformatted' => userdate($ts, $dateformat),
                    'timestamp' => $ts,
                ];
            }
        }
        usort($flat, fn($a, $b) => $a['timestamp'] <=> $b['timestamp']);
        return array_slice($flat, 0, $count);
    }

    /**
     * Read text hits from DB and return template-ready rows.
     *
     * @param \moodle_database $db        DB connection.
     * @param int              $courseid  Course id.
     * @param int              $count     Maximum rows to return.
     * @param array            $cmnames   Lookup: cmid → name.
     * @param array            $cmurls    Lookup: cmid → url.
     * @return array
     */
    private function build_text_hits(
        \moodle_database $db,
        int $courseid,
        int $count,
        array $cmnames,
        array $cmurls,
        array $cmmodnames = [],
        array $sectionnames = [],
        array $sectionurls = [],
        string $coursename = '',
        string $courseurl = ''
    ): array {
        $records = $db->get_records(
            'local_coursectrl_text_hit',
            ['courseid' => $courseid],
            'id ASC',
            '*',
            0,
            $count
        );
        $rows = [];
        foreach ($records as $rec) {
            $entityid = (int)$rec->entityid;
            $rawfield = (string)$rec->fieldname;
            $entitytype = (string)$rec->entitytype;
            $modname = ($entitytype === 'cm') ? ($cmmodnames[$entityid] ?? '') : '';
            $fieldlabel = field_label_resolver::resolve($rawfield, $modname, $entitytype);
            // Resolve entity name and link based on entity type.
            switch ($entitytype) {
                case 'section':
                    $entityname = $sectionnames[$entityid] ?? '';
                    $entityurl  = $sectionurls[$entityid] ?? '#';
                    break;
                case 'course':
                    $entityname = $coursename;
                    $entityurl  = $courseurl;
                    break;
                default:
                    $entityname = $cmnames[$entityid] ?? '';
                    $entityurl  = $cmurls[$entityid] ?? '#';
                    break;
            }
            $rows[] = [
                'matchedtext' => (string)$rec->matchedtext,
                'normalizedvalue' => (string)($rec->normalizedvalue ?? ''),
                'hasnormalized' => !empty($rec->normalizedvalue),
                'entitytype' => $entitytype,
                'entityid' => $entityid,
                'fieldname' => $fieldlabel,
                'modname' => $modname,
                'hasmodname' => ($entitytype === 'cm' && $modname !== ''),
                'cmname' => $entityname,
                'cmurl' => $entityurl,
                'hascm' => ($entityname !== ''),
            ];
        }
        return $rows;
    }

    /**
     * Build template context for a single CM (for the inventory section).
     *
     * @param \local_coursectrl\local\entity\cm_item $cm           CM entity.
     * @param dependency_index                       $depindex     Dependency index.
     * @param array                                  $dates        Date entries for this CM.
     * @param array                                  $cmnames      Lookup: cmid → name.
     * @param array                                  $circularset  Set of cmids in circular deps.
     * @param array                                  $checkresults Consistency issues for this CM.
     * @param string                                 $dateformat   Date format string.
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
        $activityurl = (new \moodle_url(
            '/mod/' . $cm->modname . '/view.php',
            ['id' => $cm->id]
        ))->out(false);
        $editurl = (new \moodle_url(
            '/course/modedit.php',
            ['update' => $cm->id, 'return' => 1]
        ))->out(false);

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

        $prerequisites = [];
        foreach ($depindex->get_prerequisites($cm->id) as $depcmid) {
            $prerequisites[] = [
                'cmid' => $depcmid,
                'name' => $cmnames[$depcmid] ?? 'cmid ' . $depcmid,
                'anchor' => '#cm-' . $depcmid,
            ];
        }

        $dependents = [];
        foreach ($depindex->get_dependents($cm->id) as $depcmid) {
            $dependents[] = [
                'cmid' => $depcmid,
                'name' => $cmnames[$depcmid] ?? 'cmid ' . $depcmid,
                'anchor' => '#cm-' . $depcmid,
            ];
        }

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

        $completionlabel = '';
        if ($cm->completion === 1) {
            $completionlabel = get_string('dashboard_completion_manual', 'local_coursectrl');
        } else if ($cm->completion === 2) {
            $completionlabel = get_string('dashboard_completion_auto', 'local_coursectrl');
        }

        $warnings = [];
        if (isset($circularset[$cm->id])) {
            $warnings[] = [
                'type' => 'circular',
                'icon' => '❗',
                'message' => get_string('warning_circular_dep', 'local_coursectrl'),
                'cmname' => $cm->name,
                'cmurl' => $activityurl,
            ];
        }
        foreach ($checkresults as $issue) {
            $msg = $this->format_issue_message($issue);
            if ($msg !== '') {
                $warnings[] = [
                    'type' => $issue['type'] ?? 'check',
                    'icon' => '⚠️',
                    'message' => $msg,
                    'cmname' => $cm->name,
                    'cmurl' => $activityurl,
                ];
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
     * Build a lookup set of cmids involved in circular dependencies.
     *
     * @param array $circular Circular pairs from dependency_index.
     * @return array<int,true>
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
