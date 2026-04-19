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
 * Renderable for the risk assessment page.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\output;

use renderable;
use renderer_base;
use templatable;
use local_coursectrl\local\analysis\dependency_index;
use local_coursectrl\local\analysis\date_collector;
use local_coursectrl\local\analysis\risk_assessment_runner;
use local_coursectrl\local\inventory\inventory_service;

/**
 * Renderable for the risk assessment result page.
 */
class risk_page implements renderable, templatable {
    /** @var object Course record. */
    private object $course;

    /** @var int Courseid. */
    private int $courseid;

    /** @var bool True if a fresh assessment was requested this page load. */
    private bool $freshrun;

    /**
     * Constructor.
     *
     * @param object $course   Course record.
     * @param bool   $freshrun True if the assessment should run now.
     */
    public function __construct(object $course, bool $freshrun = false) {
        $this->course = $course;
        $this->courseid = (int)$course->id;
        $this->freshrun = $freshrun;
    }

    /**
     * Build template context.
     *
     * @param renderer_base $output
     * @return array<string, mixed>
     */
    public function export_for_template(renderer_base $output): array {
        $svc = new inventory_service();
        $snapshot = $svc->build($this->courseid);
        $depindex = new dependency_index($snapshot->cms);
        $datecollector = new date_collector();
        $datesbycm = $datecollector->collect_grouped_by_cm($snapshot->cms);

        if ($this->freshrun) {
            $runner = new risk_assessment_runner();
            $items = $runner->run($snapshot->cms, $depindex, $datesbycm, $this->courseid);
            $lastrun = time();
        } else {
            $items = risk_assessment_runner::load_last($this->courseid);
            $lastrun = risk_assessment_runner::last_run_time($this->courseid);
        }

        // Build CM name + URL lookup for linking.
        $cmnames = [];
        $cmurls = [];
        foreach ($snapshot->cms as $cm) {
            $cmnames[$cm->id] = $cm->name;
            $cmurls[$cm->id] = (new \moodle_url(
                '/mod/' . $cm->modname . '/view.php',
                ['id' => $cm->id]
            ))->out(false);
        }

        // Group items by type for the grouped UI view.
        $grouped = $this->group_items($items, $cmnames, $cmurls);

        return [
            'courseid'       => $this->courseid,
            'coursefullname' => $this->course->fullname,
            'hasresults'     => !empty($items),
            'haslastrun'     => $lastrun > 0,
            'lastrundate'    => $lastrun > 0 ? userdate($lastrun) : '',
            'totalcount'     => count($items),
            'errorcount'     => count(array_filter($items, fn ($i) => ($i['severity'] ?? '') === 'error')),
            'warningcount'   => count(array_filter($items, fn ($i) => ($i['severity'] ?? '') === 'warning')),
            'noticecount'    => count(array_filter($items, fn ($i) => ($i['severity'] ?? '') === 'notice')),
            'groups'         => $grouped,
            'hasgroups'      => !empty($grouped),
            'runurl'         => (new \moodle_url(
                '/local/coursectrl/risks.php',
                ['courseid' => $this->courseid, 'run' => 1]
            ))->out(false),
            'dashboardurl'   => (new \moodle_url(
                '/local/coursectrl/index.php',
                ['id' => $this->courseid]
            ))->out(false),
        ];
    }

    /**
     * Group scored items by risk type for the grouped UI display.
     *
     * @param array[]          $items
     * @param array<int,string> $cmnames
     * @param array<int,string> $cmurls
     * @return array[]
     */
    private function group_items(array $items, array $cmnames, array $cmurls): array {
        $bytype = [];
        foreach ($items as $item) {
            $type = $item['type'] ?? 'unknown';
            if (!isset($bytype[$type])) {
                $bytype[$type] = [
                    'type'      => $type,
                    'severity'  => $item['severity'] ?? 'notice',
                    'score'     => $item['score'] ?? 0,
                    'findings'  => [],
                ];
            }
            // Keep highest score for the group.
            if (($item['score'] ?? 0) > $bytype[$type]['score']) {
                $bytype[$type]['score'] = $item['score'];
                $bytype[$type]['severity'] = $item['severity'] ?? 'notice';
            }

            // Build linked CM list.
            $linkedcms = [];
            foreach ($item['cmids'] ?? [] as $cmid) {
                $linkedcms[] = [
                    'cmid' => $cmid,
                    'name' => $cmnames[$cmid] ?? 'ID ' . $cmid,
                    'url'  => $cmurls[$cmid] ?? '#',
                ];
            }

            $cascadelinked = [];
            foreach ($item['cascade_cmids'] ?? [] as $cmid) {
                $cascadelinked[] = [
                    'cmid' => $cmid,
                    'name' => $cmnames[$cmid] ?? 'ID ' . $cmid,
                    'url'  => $cmurls[$cmid] ?? '#',
                ];
            }

            $bytype[$type]['findings'][] = [
                'cmids'             => $linkedcms,
                'hascmids'          => !empty($linkedcms),
                'cascade_cmids'     => $cascadelinked,
                'hascascade'        => !empty($cascadelinked),
                'cascade_count'     => $item['cascade_count'] ?? 0,
                'score'             => $item['score'] ?? 0,
                'severity'          => $item['severity'] ?? 'notice',
                'probability'       => $item['probability'] ?? 1.0,
                'has_escape'        => !empty($item['has_escape']),
                'escape_type'       => $item['escape_type'] ?? 'none',
                'message'           => $item['message'] ?? '',
                'hasmessage'        => !empty($item['message']),
            ];
        }

        // Sort groups by score descending.
        uasort($bytype, fn ($a, $b) => $b['score'] - $a['score']);

        // Add localised label and icon to each group.
        $severityicon = ['error' => '❗', 'warning' => '⚠️', 'notice' => 'ℹ️'];
        $result = [];
        foreach ($bytype as $type => $group) {
            $labelkey = 'risk_type_' . $type;
            $label = get_string($labelkey, 'local_coursectrl', null, true)
                ?: $type;
            $group['label'] = $label;
            $group['icon'] = $severityicon[$group['severity']] ?? 'ℹ️';
            $group['findingcount'] = count($group['findings']);
            $group['typelabelkey'] = $type;
            $result[] = $group;
        }
        return $result;
    }
}
