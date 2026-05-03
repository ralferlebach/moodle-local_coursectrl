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
 * Gantt dataset builder for the Course Control Hub.
 *
 * Collects all date entries for a course (adapter dates, completionexpected,
 * availability date conditions) and organises them into a Gantt row structure
 * where each CM with at least one date becomes a row and each date entry
 * becomes a bar marker within that row.
 *
 * The builder also computes the global time window (mints / maxts) so that
 * the rendering layer can normalise bar positions to percentages without any
 * further calculation.
 *
 * Rows are sorted by the earliest bar timestamp in each row so that the
 * Gantt chart reads chronologically from left to right and top to bottom.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\visualization;
use local_coursectrl\local\field_label_resolver;
use local_coursectrl\local\analysis\availability_parser;
use local_coursectrl\local\analysis\date_collector;
use local_coursectrl\local\entity\cm_item;
use local_coursectrl\manager\calendar_manager;

/**
 * Builds a Gantt row dataset from a set of CMs.
 */
class gantt_dataset_builder {
    /** @var date_collector */
    private date_collector $collector;

    /**
     * Constructor.
     *
     * @param date_collector|null $collector Optional custom collector for DI/testing.
     */
    /** @var availability_parser */
    private availability_parser $availparser;

    /**
     * Constructor.
     *
     * @param date_collector|null      $collector   Optional collector for DI.
     * @param availability_parser|null $availparser Optional parser for DI.
     */
    public function __construct(
        ?date_collector $collector = null,
        ?availability_parser $availparser = null
    ) {
        $this->collector   = $collector ?? new date_collector();
        $this->availparser = $availparser ?? new availability_parser();
    }

    /**
     * Build the Gantt dataset.
     *
     * @param cm_item[]             $cms    Course modules keyed by cmid.
     * @param calendar_manager|null $calman Optional calendar manager for holiday bands.
     * @return array{
     *     rows: array,
     *     mints: int,
     *     maxts: int,
     *     hasdata: bool,
     *     rowcount: int,
     *     holidaybands: array,
     *     hasholidaybands: bool
     * }
     */
    public function build(array $cms, ?calendar_manager $calman = null): array {
        if (empty($cms)) {
            return $this->empty_result();
        }

        $bycm = $this->collector->collect_grouped_by_cm($cms);
        $datetimefmt = get_string('strftimedaydatetime', 'core_langconfig');
        $dateonlyfmt = get_string('strftimedaydate', 'core_langconfig');

        // Build per-CM rows, skipping CMs with no date entries.
        $rows = [];
        foreach ($cms as $cm) {
            $entries = $bycm[$cm->id] ?? [];
            if (empty($entries)) {
                continue;
            }
            $bars = [];
            $opents = [];
            $closets = [];
            foreach ($entries as $entry) {
                $kind = $this->classify_field((string) $entry['field']);
                $ts = (int) $entry['timestamp'];
                $bars[] = [
                    'field' => $entry['field'],
                    'fieldlabel' => $entry['fieldlabel'],
                    'humanlabel' => !empty($entry['fieldlabel'])
                        ? (string) $entry['fieldlabel']
                        : $this->localised_field_label(
                            (string) $entry['field'],
                            (string) ($entry['modname'] ?? ''),
                            'cm'
                        ),
                    'timestamp' => $ts,
                    'formatted' => userdate($ts, $datetimefmt),
                    'source' => $entry['source'],
                    'kind' => $kind,
                ];
                if ($kind === 'open') {
                    $opents[] = $ts;
                } else if ($kind === 'close') {
                    $closets[] = $ts;
                }
            }
            // Sort bars within row chronologically.
            usort($bars, fn($a, $b) => $a['timestamp'] <=> $b['timestamp']);

            // Usability window: from earliest "open" marker to latest
            // "close" marker. Either side may be missing.
            $window = null;
            if (!empty($opents) || !empty($closets)) {
                $window = [
                    'from_ts'        => !empty($opents) ? min($opents) : null,
                    'to_ts'          => !empty($closets) ? max($closets) : null,
                    'has_from'       => !empty($opents),
                    'has_to'         => !empty($closets),
                    'from_formatted' => !empty($opents) ? userdate(min($opents), $dateonlyfmt) : '',
                    'to_formatted'   => !empty($closets) ? userdate(max($closets), $dateonlyfmt) : '',
                ];
            }

            $rows[] = [
                'cmid' => $cm->id,
                'name' => $cm->name,
                'modname' => $cm->modname,
                'component' => $cm->get_component(),
                'visible' => (bool) $cm->visible,
                'bars' => $bars,
                'window' => $window,
                'rowmints' => $bars[0]['timestamp'],
            ];
        }

        if (empty($rows)) {
            return $this->empty_result();
        }

        // Sort rows by earliest bar timestamp.
        usort($rows, fn($a, $b) => $a['rowmints'] <=> $b['rowmints']);

        // Compute global time window.
        $mints = PHP_INT_MAX;
        $maxts = 0;
        foreach ($rows as $row) {
            foreach ($row['bars'] as $bar) {
                $mints = min($mints, $bar['timestamp']);
                $maxts = max($maxts, $bar['timestamp']);
            }
        }

        // Strip internal sort key.
        foreach ($rows as &$row) {
            unset($row['rowmints']);
        }
        unset($row);

        return [
            'rows' => $rows,
            'mints' => $mints,
            'maxts' => $maxts,
            'hasdata' => true,
            'rowcount' => count($rows),
            'holidaybands' => $this->build_holiday_bands($mints, $maxts, $calman),
            'hasholidaybands' => $calman !== null,
        ];
    }

    /**
     * Build the Gantt dataset including ALL sections and CMs in course order.
     *
     * Section headers are emitted at depth 0; CMs at depth 1 underneath.
     * CMs with no date entries are included (empty bars) so the full course
     * structure is always visible.
     *
     * @param array                 $sections Section items keyed by section id.
     * @param cm_item[]             $cms      Course modules keyed by cmid, in course order.
     * @param int                   $courseid Course id for section URLs.
     * @param calendar_manager|null $calman   Optional calendar manager for holiday bands.
     * @return array Same shape as build(), with additional row fields.
     */
    public function build_with_structure(
        array $sections,
        array $cms,
        int $courseid,
        ?\local_coursectrl\manager\calendar_manager $calman = null,
        array $sectionnames = [],
        array $subsectionmap = [],
        array $subsectionsectionids = []
    ): array {
        $bycm        = $this->collector->collect_grouped_by_cm($cms);
        $datetimefmt = get_string('strftimedaydatetime', 'core_langconfig');
        $dateonlyfmt = get_string('strftimedaydate', 'core_langconfig');

        // Group CMs by sectionid, preserving course order.
        $cmsbysection = [];
        foreach ($cms as $cm) {
            $cmsbysection[$cm->sectionid][] = $cm;
        }

        // Sort sections by sectionnum.
        $sortedsections = $sections;
        uasort($sortedsections, fn($a, $b) => $a->sectionnum <=> $b->sectionnum);

        // Helper: build a CM row array from a cm_item.
        // Defined here so it can be reused for both regular and subsection CMs.
        $buildcmrow = function (
            \local_coursectrl\local\entity\cm_item $cm,
            int $sectionid,
            int $depth,
            ?array $parentwindow = null
        ) use (
            $bycm,
            $datetimefmt,
            $dateonlyfmt,
            &$mints,
            &$maxts,
            &$hasdata
        ): array {
            $entries  = $bycm[$cm->id] ?? [];
            $bars     = [];
            $opents   = [];
            $closets  = [];
            $hasavail = false;
            // Section boundary timestamps for out-of-window detection.
            $secfrom = $parentwindow['from_ts'] ?? null;
            $secto   = $parentwindow['to_ts'] ?? null;
            foreach ($entries as $entry) {
                $kind = $this->classify_field((string) $entry['field']);
                $ts   = (int) $entry['timestamp'];
                // Flag bars that fall outside the parent section's accessible window.
                $outsection = ($secfrom !== null && $ts < $secfrom)
                    || ($secto !== null && $ts > $secto);
                $bars[] = [
                    'field'      => $entry['field'],
                    'fieldlabel' => $entry['fieldlabel'],
                    'humanlabel' => !empty($entry['fieldlabel'])
                        ? (string) $entry['fieldlabel']
                        : $this->localised_field_label(
                            (string) $entry['field'],
                            (string) ($entry['modname'] ?? ''),
                            'cm'
                        ),
                    'timestamp'  => $ts,
                    'formatted'  => userdate($ts, $datetimefmt),
                    'source'     => $entry['source'],
                    'kind'       => $kind,
                    'outsection' => $outsection,
                ];
                if ($entry['source'] === 'availability') {
                    $hasavail = true;
                }
                if ($kind === 'open') {
                    $opents[] = $ts;
                } else if ($kind === 'close') {
                    $closets[] = $ts;
                }
                $mints   = min($mints, $ts);
                $maxts   = max($maxts, $ts);
                $hasdata = true;
            }
            usort($bars, fn($a, $b) => $a['timestamp'] <=> $b['timestamp']);
            // Own usability window from open/close markers.
            $window = null;
            if (!empty($opents) || !empty($closets)) {
                $window = [
                    'from_ts'        => !empty($opents) ? min($opents) : null,
                    'to_ts'          => !empty($closets) ? max($closets) : null,
                    'has_from'       => !empty($opents),
                    'has_to'         => !empty($closets),
                    'from_formatted' => !empty($opents)
                        ? userdate(min($opents), $dateonlyfmt) : '',
                    'to_formatted'   => !empty($closets)
                        ? userdate(max($closets), $dateonlyfmt) : '',
                ];
            } else if ($parentwindow !== null && empty($bars)) {
                // No own dates: inherit section window so the Gantt shows
                // the period during which this CM is actually accessible.
                $window = $parentwindow;
            }
            $cmurl = (new \moodle_url(
                '/mod/' . $cm->modname . '/view.php',
                ['id' => $cm->id]
            ))->out(false);
            return [
                'issection'    => false,
                'sectionid'    => $sectionid,
                'depth'        => $depth,
                'cmid'         => $cm->id,
                'name'         => $cm->name,
                'modname'      => $cm->modname,
                'visible'      => (bool) $cm->visible,
                'cmurl'        => $cmurl,
                'bars'         => $bars,
                'window'       => $window,
                // Cascade: unlimited only when no availability condition applies
                // AND the section window does not restrict an empty-bar CM.
                'unlimited'    => !$hasavail && !($parentwindow !== null && empty($bars)),
                // Pass section window to JS for shading outside-window areas.
                'parentwindow' => $parentwindow,
            ];
        };

        $rows    = [];
        $mints   = PHP_INT_MAX;
        $maxts   = 0;
        $hasdata = false;

        foreach ($sortedsections as $section) {
            // Skip sections owned by subsection CMs — they are rendered
            // inline when the subsection CM is encountered below.
            if (!empty($subsectionsectionids[$section->id])) {
                continue;
            }

            // Section header row.
            if (!empty($sectionnames[$section->id])) {
                $secname = (string) $sectionnames[$section->id];
            } else if ($section->name !== '' && $section->name !== null) {
                $secname = format_string((string) $section->name);
            } else {
                $secname = get_string('section') . ' ' . $section->sectionnum;
            }
            $sectionurl = (new \moodle_url(
                '/course/view.php',
                ['id' => $courseid, 'section' => $section->sectionnum]
            ))->out(false);
            $rows[] = [
                'issection'  => true,
                'sectionid'  => $section->id,
                'depth'      => 0,
                'cmid'       => 0,
                'name'       => $secname,
                'modname'    => '',
                'visible'    => (bool) $section->visible,
                'cmurl'      => $sectionurl,
                'bars'       => [],
                'window'     => null,
                'unlimited'  => false,
            ];

            // Section availability date bars.
            $secavailbars = [];
            $secopents    = [];
            $secclosets   = [];
            if ($section->availability !== null) {
                $dateconds = $this->availparser->get_date_conditions($section->availability);
                foreach ($dateconds as $i => $cond) {
                    // Translate raw Moodle operators to canonical direction names.
                    $rawdir    = (string) $cond['direction'];
                    $direction = ($rawdir === '>=') ? 'from' : 'until';
                    $ts        = (int) $cond['timestamp'];
                    $flabel    = field_label_resolver::resolve(
                        'availability_' . $direction,
                        '',
                        'section'
                    ) . ($i > 0 ? ' (#' . $i . ')' : '');
                    $secavailbars[] = [
                        'field'      => 'availability_' . $direction . '_' . $i,
                        'fieldlabel' => $flabel,
                        'humanlabel' => $flabel,
                        'timestamp'  => $ts,
                        'formatted'  => userdate($ts, $datetimefmt),
                        'source'     => 'availability',
                        'kind'       => $this->classify_field('availability_' . $direction),
                    ];
                    if ($direction === 'from') {
                        $secopents[] = $ts;
                    } else {
                        $secclosets[] = $ts;
                    }
                    $mints   = min($mints, $ts);
                    $maxts   = max($maxts, $ts);
                    $hasdata = true;
                }
            }
            // Compute the section's accessible window (used for CM cascade).
            $sectionwindow = (!empty($secopents) || !empty($secclosets)) ? [
                'from_ts'        => !empty($secopents) ? min($secopents) : null,
                'to_ts'          => !empty($secclosets) ? max($secclosets) : null,
                'from_formatted' => !empty($secopents)
                    ? userdate(min($secopents), $dateonlyfmt) : '',
                'to_formatted'   => !empty($secclosets)
                    ? userdate(max($secclosets), $dateonlyfmt) : '',
            ] : null;

            if (!empty($secavailbars)) {
                usort($secavailbars, fn($a, $b) => $a['timestamp'] <=> $b['timestamp']);
                $secwindow = [
                    'from_ts'        => !empty($secopents) ? min($secopents) : null,
                    'to_ts'          => !empty($secclosets) ? max($secclosets) : null,
                    'has_from'       => !empty($secopents),
                    'has_to'         => !empty($secclosets),
                    'from_formatted' => !empty($secopents)
                        ? userdate(min($secopents), $dateonlyfmt) : '',
                    'to_formatted'   => !empty($secclosets)
                        ? userdate(max($secclosets), $dateonlyfmt) : '',
                ];
                // Update the section header row with its bars.
                $rows[count($rows) - 1]['bars']   = $secavailbars;
                $rows[count($rows) - 1]['window'] = $secwindow;
            }

            // CM rows under this section.
            foreach ($cmsbysection[$section->id] ?? [] as $cm) {
                if ($cm->modname === 'subsection' && isset($subsectionmap[$cm->id])) {
                    // Render subsection as a section-header row at depth 1.
                    $subsecurl = (new \moodle_url(
                        '/mod/subsection/view.php',
                        ['id' => $cm->id]
                    ))->out(false);
                    // Compute bars from the subsection CM's own date entries.
                    // date_collector already gathered completionexpected and
                    // availability conditions via the same cm->availability field
                    // used by every other mod plugin — no separate parsing needed.
                    $childsectionid = $subsectionmap[$cm->id];
                    $subsecbars  = [];
                    $subopents   = [];
                    $subclosets  = [];
                    foreach ($bycm[$cm->id] ?? [] as $entry) {
                        $kind = $this->classify_field((string) $entry['field']);
                        $sts  = (int) $entry['timestamp'];
                        $subsecbars[] = [
                            'field'      => $entry['field'],
                            'fieldlabel' => $entry['fieldlabel'],
                            'humanlabel' => !empty($entry['fieldlabel'])
                                ? (string) $entry['fieldlabel']
                                : $this->localised_field_label(
                                    (string) $entry['field'],
                                    (string) ($entry['modname'] ?? ''),
                                    'cm'
                                ),
                            'timestamp'  => $sts,
                            'formatted'  => userdate($sts, $datetimefmt),
                            'source'     => $entry['source'],
                            'kind'       => $kind,
                        ];
                        if ($kind === 'open') {
                            $subopents[] = $sts;
                        } else if ($kind === 'close') {
                            $subclosets[] = $sts;
                        }
                        $mints   = min($mints, $sts);
                        $maxts   = max($maxts, $sts);
                        $hasdata = true;
                    }
                    usort($subsecbars, fn($a, $b) => $a['timestamp'] <=> $b['timestamp']);
                    $subsecwindow = null;
                    if (!empty($subopents) || !empty($subclosets)) {
                        $subsecwindow = [
                            'from_ts'        => !empty($subopents) ? min($subopents) : null,
                            'to_ts'          => !empty($subclosets) ? max($subclosets) : null,
                            'has_from'       => !empty($subopents),
                            'has_to'         => !empty($subclosets),
                            'from_formatted' => !empty($subopents)
                                ? userdate(min($subopents), $dateonlyfmt) : '',
                            'to_formatted'   => !empty($subclosets)
                                ? userdate(max($subclosets), $dateonlyfmt) : '',
                        ];
                    }
                    $rows[] = [
                        'issection'  => true,
                        'issubsection' => true,
                        'sectionid'  => $childsectionid,
                        'depth'      => 1,
                        'cmid'       => $cm->id,
                        'name'       => $cm->name,
                        'modname'    => 'subsection',
                        'visible'    => (bool) $cm->visible,
                        'cmurl'      => $subsecurl,
                        'bars'       => $subsecbars,
                        'window'     => $subsecwindow,
                        'unlimited'  => false,
                    ];
                    // Render child CMs of this subsection at depth 2.
                    foreach ($cmsbysection[$childsectionid] ?? [] as $childcm) {
                        // Child CMs inherit from subsection + grandparent section.
                        // Merge both windows: use the tighter of the two boundaries.
                        $mergedfrom = array_filter(
                            array_map(
                                fn($w) => $w['from_ts'] ?? null,
                                array_filter([$sectionwindow,
                                    (!empty($subopents) || !empty($subclosets)) ? [
                                        'from_ts' => !empty($subopents) ? min($subopents) : null,
                                        'to_ts'   => !empty($subclosets) ? max($subclosets) : null,
                                    ] : null,
                                ])
                            )
                        );
                        $mergedto = array_filter(
                            array_map(
                                fn($w) => $w['to_ts'] ?? null,
                                array_filter([$sectionwindow,
                                    (!empty($subopents) || !empty($subclosets)) ? [
                                        'from_ts' => !empty($subopents) ? min($subopents) : null,
                                        'to_ts'   => !empty($subclosets) ? max($subclosets) : null,
                                    ] : null,
                                ])
                            )
                        );
                        $childwindow = (!empty($mergedfrom) || !empty($mergedto)) ? [
                            'from_ts'        => !empty($mergedfrom) ? max($mergedfrom) : null,
                            'to_ts'          => !empty($mergedto) ? min($mergedto) : null,
                            'from_formatted' => !empty($mergedfrom)
                                ? userdate(max($mergedfrom), $dateonlyfmt) : '',
                            'to_formatted'   => !empty($mergedto)
                                ? userdate(min($mergedto), $dateonlyfmt) : '',
                        ] : null;
                        $rows[] = $buildcmrow(
                            $childcm,
                            $childsectionid,
                            2,
                            $childwindow
                        );
                    }
                } else {
                    $rows[] = $buildcmrow(
                        $cm,
                        $section->id,
                        1,
                        $sectionwindow
                    );
                }
            }
        }

        if (!$hasdata) {
            // No date entries at all, but still return the structure.
            $mints = time();
            $maxts = strtotime('+3 months', $mints);
        }

        return [
            'rows'            => $rows,
            'mints'           => $mints,
            'maxts'           => $maxts,
            'hasdata'         => true,
            'rowcount'        => count($rows),
            'holidaybands'    => $this->build_holiday_bands($mints, $maxts, $calman),
            'hasholidaybands' => $calman !== null,
        ];
    }

    /**
     * Return an empty dataset when no date entries exist.
     *
     * @return array
     */
    private function empty_result(): array {
        return [
            'rows' => [],
            'mints' => 0,
            'maxts' => 0,
            'hasdata' => false,
            'rowcount' => 0,
            'holidaybands' => [],
            'hasholidaybands' => false,
        ];
    }

    /**
     * Build a list of holiday band descriptors for the Gantt renderer.
     *
     * Each band represents a single special day or a contiguous run of
     * special days of the same category. The renderer uses these to paint
     * semi-transparent background bands across all rows.
     *
     * @param int                   $mints  Global min timestamp of the Gantt.
     * @param int                   $maxts  Global max timestamp of the Gantt.
     * @param calendar_manager|null $calman Calendar manager, or null.
     * @return array[] Each entry: {from_ts, to_ts, category, names[]}.
     */
    private function build_holiday_bands(
        int $mints,
        int $maxts,
        ?calendar_manager $calman
    ): array {
        if ($calman === null || $mints <= 0 || $maxts <= 0) {
            return [];
        }
        $holidays = $calman->get_holidays_for_range($mints, $maxts);
        if (empty($holidays)) {
            return [];
        }
        ksort($holidays);
        $bands = [];
        foreach ($holidays as $datekey => $events) {
            $ts = strtotime($datekey);
            if ($ts === false) {
                continue;
            }
            $category = 'custom';
            $names = [];
            foreach ($events as $ev) {
                $names[] = $ev['name'];
                if ($ev['category'] === 'public_holiday') {
                    $category = 'public_holiday';
                } else if ($ev['category'] === 'school_holiday' && $category !== 'public_holiday') {
                    $category = 'school_holiday';
                }
            }
            $bands[] = [
                'from_ts' => $ts,
                'to_ts' => $ts + 86399,
                'category' => $category,
                'names' => $names,
                'label' => implode(', ', array_unique($names)),
            ];
        }
        return $bands;
    }

    /**
     * Classify a date field by its effect on activity usability.
     *
     * 'open'  — the field opens the activity for use (timeopen,
     *           allowsubmissionsfromdate, available, from, start, begin, ...).
     * 'close' — the field closes / deadlines the activity (timeclose, duedate,
     *           cutoffdate, deadline, until, end, ...).
     * 'event' — point-in-time event with no opening / closing semantics
     *           (completionexpected, ...).
     *
     * Used both by the renderer (for marker styling) and by build() to
     * derive each row's usability window (earliest open → latest close).
     *
     * @param string $field Raw field name (e.g. 'timeopen', 'duedate').
     * @return string One of 'open' | 'close' | 'event'.
     */
    private function classify_field(string $field): string {
        $f = strtolower($field);
        if (
            str_contains($f, 'open') || str_contains($f, 'available')
            || str_contains($f, 'from') || str_contains($f, 'start')
            || str_contains($f, 'begin')
        ) {
            return 'open';
        }
        if (
            str_contains($f, 'close') || str_contains($f, 'due')
            || str_contains($f, 'cutoff') || str_contains($f, 'deadline')
            || str_contains($f, 'until') || str_contains($f, 'end')
        ) {
            return 'close';
        }
        return 'event';
    }

    /**
     * Resolve a localised, human-readable label for a date field name.
     *
     * Resolution order:
     *
     *   1. Plugin string `field_<name>` from local_coursectrl. This lets
     *      adapters or custom labelling override anything else.
     *   2. A small hand-curated mapping for the most common Moodle date
     *      field names. These are stable across Moodle versions and avoid
     *      having to load a different component string for each module.
     *   3. A prettified version of the raw field name as last-resort
     *      fallback (snake_case → Title Case).
     *
     * Always returns a non-empty string.
     *
     * @param string $field Raw field name.
     * @return string Localised label fit for hover tooltip display.
     */
    private function localised_field_label(string $field, string $modname = ''): string {
        return field_label_resolver::resolve($field, $modname, 'cm');
    }
}
