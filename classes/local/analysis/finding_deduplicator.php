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
 * Finding deduplicator for the Course Control Hub risk pipeline.
 *
 * Problem solved
 * The deep_journey_simulator produces one finding per (cmid, grademode,
 * group-profile) tuple. When the same CM is unreachable across many scenarios,
 * it generates N cards for the same root cause. This overwhelms the UI and
 * hides the actual signal.
 *
 * This class collapses duplicate findings into one canonical card per
 * (cmid, effective_cause) and builds aggregate cards when multiple CMs
 * share an identical root cause.
 *
 * Deduplication logic
 *  Fingerprint for journey_unreachable:
 *    fingerprint = cmid + '|' + severity (ignoring grademode/groupids).
 *
 *  Within a fingerprint group:
 *    - The "error" finding (Best Case, grademode=pass) is kept as canonical.
 *    - All other scenarios are counted but collapsed.
 *    - affected_scenarios: number of collapsed scenarios.
 *
 *  Aggregation across CMs:
 *    When N findings for different CMs share the same cascade root AND
 *    the same severity, a single aggregate card replaces them:
 *      type = 'journey_unreachable_group'
 *      affected_count = N
 *      cmids = [all affected cmids]
 *
 * Other finding types (dead-ends, temporal conflicts) pass through unchanged.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\analysis;

/**
 * Collapses duplicate and structurally equivalent findings into canonical cards.
 */
class finding_deduplicator {
    /** @var int Minimum number of CMs to form an aggregate card. */
    private int $aggregatethreshold;

    /**
     * Constructor.
     *
     * @param int $aggregatethreshold Min CMs sharing same cause for an aggregate card (default 3).
     */
    public function __construct(int $aggregatethreshold = 3) {
        $this->aggregatethreshold = max(2, $aggregatethreshold);
    }

    /**
     * Deduplicate and optionally aggregate findings.
     *
     * @param array[] $findings All risk findings from the pipeline.
     * @return array[] Deduplicated findings.
     */
    public function deduplicate(array $findings): array {
        // Separate journey findings from pass-through findings.
        $journeyfindings = [];
        $otherfindings = [];

        foreach ($findings as $item) {
            if (($item['type'] ?? '') === 'journey_unreachable') {
                $journeyfindings[] = $item;
            } else {
                $otherfindings[] = $item;
            }
        }

        if (empty($journeyfindings)) {
            return $findings;
        }

        // Step 1: collapse per-CM duplicates across scenarios.
        // Key: cmid|severity (grademode and groupids differ between scenarios
        // For the same underlying cause).
        $canonical = []; // Fingerprint → canonical finding.
        $scenariocount = []; // Fingerprint → int count.
        $groupprofiles = []; // Fingerprint → set of group-id strings.

        foreach ($journeyfindings as $item) {
            $cmid = (int) (($item['cmids'] ?? [])[0] ?? 0);
            $severity = $item['severity'] ?? 'notice';
            $fingerprint = $cmid . '|' . $severity;

            $scenariocount[$fingerprint] = ($scenariocount[$fingerprint] ?? 0) + 1;

            $gkey = implode(',', $item['groupids'] ?? []);
            $groupprofiles[$fingerprint][$gkey] = true;

            if (!isset($canonical[$fingerprint])) {
                // First (or best-severity) occurrence becomes canonical.
                $canonical[$fingerprint] = $item;
                continue;
            }

            // Prefer the Best Case (pass) scenario as the representative card —
            // It produces error severity when the CM is critical, which is
            // The most actionable representation.
            $existing = $canonical[$fingerprint];
            if (($item['grademode'] ?? '') === 'pass' && ($existing['grademode'] ?? '') !== 'pass') {
                $canonical[$fingerprint] = $item;
            }
        }

        // Attach scenario counts and profile counts to canonical findings.
        $deduped = [];
        foreach ($canonical as $fingerprint => $item) {
            $item['affected_scenarios'] = $scenariocount[$fingerprint] ?? 1;
            $item['affected_profiles'] = count($groupprofiles[$fingerprint] ?? []);
            $deduped[] = $item;
        }

        // Step 2: aggregate cards — group CMs that share the same severity and
        // Have no cascade children (they are independent primaries with same cause).
        // Heuristic: same severity + same cascade_count=0 + same grademode.
        $aggregategroups = []; // Aggkey → [item, ...].

        foreach ($deduped as $item) {
            $cmid = (int) (($item['cmids'] ?? [])[0] ?? 0);
            $severity = $item['severity'] ?? 'notice';
            $cascadecount = (int) ($item['cascade_count'] ?? 0);
            $grademode = $item['grademode'] ?? '';
            $profiles = (int) ($item['affected_profiles'] ?? 1);

            // Group by shared section cause regardless of cascade_count:
            // CMs blocked by the same section (same section_id) should appear
            // On one card even when some have cascade entries.
            $sectionid = (int) ($item['section_id'] ?? 0);
            if (!empty($item['section_cause']) && $sectionid > 0) {
                // Section-caused: group by section_id + severity (ignoring cascade).
                $aggkey = $severity . '|s' . $sectionid;
                $aggregategroups[$aggkey][] = $item;
            } else if ($cascadecount === 0) {
                // Non-section independent primaries: existing cascade-free grouping.
                $aggkey = $severity . '|' . $grademode . '|' . $profiles . '|s0';
                $aggregategroups[$aggkey][] = $item;
            }
        }

        // Build result: aggregate groups above threshold → one group card;
        // All others remain as individual cards.
        $groupedfingerprints = [];
        $result = [];

        foreach ($aggregategroups as $aggkey => $groupitems) {
            if (count($groupitems) >= $this->aggregatethreshold) {
                // Build aggregate card.
                $allcmids = [];
                $maxscore = 0;
                $maxscenarios = 0;
                $allcascadecmids = [];
                foreach ($groupitems as $gi) {
                    foreach ($gi['cmids'] ?? [] as $gcmid) {
                        $allcmids[] = (int) $gcmid;
                    }
                    // Collect cascade entries from all group members.
                    foreach ($gi['cascade_cmids'] ?? [] as $ccmid) {
                        $allcascadecmids[] = (int) $ccmid;
                    }
                    $maxscore = max($maxscore, (int) ($gi['score'] ?? 0));
                    $maxscenarios = max($maxscenarios, (int) ($gi['affected_scenarios'] ?? 1));

                    // Mark individual cards as absorbed into aggregate.
                    $gcmid = (int) (($gi['cmids'] ?? [])[0] ?? 0);
                    $gsev = $gi['severity'] ?? 'notice';
                    $groupedfingerprints[$gcmid . '|' . $gsev] = true;
                }

                // Use first item as template for aggregate card.
                $template = $groupitems[0];
                $template['type'] = 'journey_unreachable_group';
                // Propagate section cause if all items share one.
                // Propagate section_cause when all non-zero section_ids match.
                // Use count of each section_id to find the dominant one.
                $sectionidcounts = [];
                foreach ($groupitems as $gi) {
                    $gsid = (int) ($gi['section_id'] ?? 0);
                    if ($gsid > 0) {
                        $sectionidcounts[$gsid] = ($sectionidcounts[$gsid] ?? 0) + 1;
                    }
                }
                arsort($sectionidcounts);
                $dominantsid = (int) array_key_first($sectionidcounts);
                $dominantcount = $sectionidcounts[$dominantsid] ?? 0;
                if ($dominantsid > 0 && $dominantcount >= count($groupitems) - 1) {
                    // All (or all but one) items share the same section_id.
                    $template['section_cause'] = true;
                    $template['section_id'] = $dominantsid;
                    // Find a representative item for the section name.
                    foreach ($groupitems as $gi) {
                        if ((int) ($gi['section_id'] ?? 0) === $dominantsid && !empty($gi['section_name'])) {
                            $template['section_name'] = $gi['section_name'];
                            $template['section_num'] = $gi['section_num'] ?? 0;
                            break;
                        }
                    }
                    if (empty($template['section_name'])) {
                        // All section names empty — use section_num from any item.
                        foreach ($groupitems as $gi) {
                            if ((int) ($gi['section_id'] ?? 0) === $dominantsid) {
                                $template['section_num'] = $gi['section_num'] ?? 0;
                                break;
                            }
                        }
                    }
                } else {
                    $template['section_cause'] = false;
                    $template['section_id'] = 0;
                }
                $template['cmids'] = $allcmids;
                $template['affected_count'] = count($allcmids);
                $template['score'] = $maxscore;
                $template['affected_scenarios'] = $maxscenarios;
                // Merge all cascade entries from grouped items.
                $allcascadecmids = array_values(array_unique($allcascadecmids));
                $template['cascade_cmids'] = $allcascadecmids;
                $template['cascade_count'] = count($allcascadecmids);
                // Merge subsection_children from all group members.
                $allsubchildren = [];
                foreach ($groupitems as $gi) {
                    foreach ($gi['subsection_children'] ?? [] as $scmid) {
                        $allsubchildren[] = (int) $scmid;
                    }
                }
                $template['subsection_children'] = array_values(array_unique($allsubchildren));
                $template['message_key'] = 'risk_journey_unreachable_group';
                $result[] = $template;
            }
        }

        // Add non-aggregated individual cards.
        foreach ($deduped as $item) {
            $cmid = (int) (($item['cmids'] ?? [])[0] ?? 0);
            $severity = $item['severity'] ?? 'notice';
            $fingerprint = $cmid . '|' . $severity;
            if (!isset($groupedfingerprints[$fingerprint])) {
                $result[] = $item;
            }
        }

        return array_merge($result, $otherfindings);
    }
}
