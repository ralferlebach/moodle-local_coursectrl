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
 * Dead-end detector for the Course Control Hub.
 *
 * Performs deterministic (non-simulation) structural analysis of the
 * course dependency graph to identify activities that are permanently
 * or structurally unreachable for all learners.
 *
 * Risk codes produced:
 *
 *   circular_dep_transitive — A cycle exists in the dependency graph
 *                              (A→B→C→A or any length). All activities
 *                              in the cycle are permanently unreachable.
 *                              Severity: error. Probability: 1.0.
 *
 *   dep_on_hidden           — An activity requires completion of a
 *                              permanently hidden activity (visible=false,
 *                              no availability conditions that could
 *                              reveal it). Severity: error. Probability: 1.0.
 *
 *   deadline_before_dep_window — The deadline of activity B lies before
 *                              the latest possible completion window of
 *                              its prerequisite A. Severity: error.
 *                              Probability: 1.0 when both dates are set.
 *
 *   hidden_with_dependents  — An activity is hidden but other activities
 *                              depend on its completion. Severity: warning.
 *                              Probability: 1.0.
 *
 *   completion_required_no_tracking — completionexpected is set but
 *                              completion tracking is off. The reminder
 *                              fires but learners cannot complete the
 *                              activity. Severity: warning. Probability: 1.0.
 *
 *   long_dep_chain          — A dependency chain exceeds the configured
 *                              depth limit. High-length chains are
 *                              fragile: a single broken link blocks the
 *                              entire tail. Severity: notice.
 *                              Probability: 1.0.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\analysis;

use local_coursectrl\local\entity\cm_item;

/**
 * Detects structural dead-ends and risk patterns in the dependency graph.
 */
class dead_end_detector {
    /**
     * Default maximum chain length before a notice is raised.
     * Configurable via local_coursectrl/risk_max_chain_depth.
     *
     * @var int
     */
    private const DEFAULT_MAX_CHAIN_DEPTH = 10;

    /** @var int Resolved maximum chain depth for this run. */
    private int $maxchaindepth;

    /**
     * Constructor.
     *
     * @param int|null $maxchaindepth Override chain depth limit (null = read from config).
     */
    public function __construct(?int $maxchaindepth = null) {
        if ($maxchaindepth !== null) {
            $this->maxchaindepth = max(0, $maxchaindepth);
        } else {
            $configured = (int) get_config('local_coursectrl', 'risk_max_chain_depth');
            $this->maxchaindepth = $configured > 0 ? $configured : self::DEFAULT_MAX_CHAIN_DEPTH;
        }
    }

    /**
     * Run all dead-end detection passes.
     *
     * @param cm_item[]        $cms       Course modules keyed by cmid.
     * @param dependency_index $depindex  Pre-built dependency index.
     * @return array[] Flat list of risk items. Each item has keys:
     *                 type, severity, probability, cmids (affected), message_key, message_params.
     */
    public function detect(array $cms, dependency_index $depindex): array {
        $risks = [];

        $risks = array_merge($risks, $this->detect_transitive_cycles($cms, $depindex));
        $risks = array_merge($risks, $this->detect_hidden_dep_chains($cms, $depindex));
        $risks = array_merge($risks, $this->detect_deadline_inversions($cms, $depindex));
        $risks = array_merge($risks, $this->detect_completion_tracking_mismatch($cms));
        if ($this->maxchaindepth > 0) {
            $risks = array_merge($risks, $this->detect_long_chains($cms, $depindex));
        }

        return $risks;
    }

    /**
     * Detect transitive cycles (A→B→C→A) using DFS with path tracking.
     *
     * Unlike dependency_index::find_circular_deps() which only detects
     * direct mutual pairs, this method finds cycles of any length up to
     * maxchaindepth.
     *
     * @param cm_item[]        $cms      Course modules keyed by cmid.
     * @param dependency_index $depindex Dependency index.
     * @return array[] Risk items.
     */
    private function detect_transitive_cycles(array $cms, dependency_index $depindex): array {
        $forward = $depindex->get_all_forward();
        $allcmids = array_keys($cms);
        $visited = [];
        $cycles = [];
        $cyclekeys = [];

        foreach ($allcmids as $startcmid) {
            if (isset($visited[$startcmid])) {
                continue;
            }
            $path = [];
            $this->dfs_cycle(
                $startcmid,
                $forward,
                $visited,
                $path,
                $cycles,
                $cyclekeys,
                0
            );
        }

        $risks = [];
        foreach ($cycles as $cyclecmids) {
            $risks[] = [
                'type'          => 'circular_dep_transitive',
                'severity'      => 'error',
                'probability'   => 1.0,
                'cmids'         => $cyclecmids,
                'message_key'   => 'risk_circular_dep_transitive',
                'message_params' => ['count' => count($cyclecmids)],
                'affected_count' => count($cyclecmids),
            ];
        }
        return $risks;
    }

    /**
     * Recursive DFS for cycle detection.
     *
     * @param int     $cmid      Current node.
     * @param array   $forward   Forward dependency map.
     * @param array   $visited   Global visited set.
     * @param array   $path      Current DFS path (cmid → position).
     * @param array   $cycles    Accumulated cycle lists.
     * @param array   $cyclekeys De-duplication keys for cycles.
     * @param int     $depth     Current recursion depth.
     * @return void
     */
    private function dfs_cycle(
        int $cmid,
        array $forward,
        array &$visited,
        array &$path,
        array &$cycles,
        array &$cyclekeys,
        int $depth
    ): void {
        if ($this->maxchaindepth > 0 && $depth > $this->maxchaindepth) {
            return;
        }
        if (isset($path[$cmid])) {
            // Cycle found — extract the cycle portion of the path.
            $cyclestart = $path[$cmid];
            $cyclecmids = array_keys(
                array_slice($path, $cyclestart, null, true)
            );
            sort($cyclecmids);
            $key = implode('-', $cyclecmids);
            if (!isset($cyclekeys[$key])) {
                $cyclekeys[$key] = true;
                $cycles[] = $cyclecmids;
            }
            return;
        }
        $path[$cmid] = count($path);
        foreach ($forward[$cmid] ?? [] as $dep) {
            $this->dfs_cycle($dep, $forward, $visited, $path, $cycles, $cyclekeys, $depth + 1);
        }
        unset($path[$cmid]);
        $visited[$cmid] = true;
    }

    /**
     * Detect chains where an activity depends on a permanently hidden one.
     *
     * @param cm_item[]        $cms      Course modules keyed by cmid.
     * @param dependency_index $depindex Dependency index.
     * @return array[] Risk items.
     */
    private function detect_hidden_dep_chains(array $cms, dependency_index $depindex): array {
        $risks = [];
        $hiddenset = [];
        foreach ($cms as $cm) {
            if (!$cm->visible) {
                $hiddenset[$cm->id] = true;
            }
        }

        foreach ($cms as $cm) {
            $prereqs = $depindex->get_prerequisites($cm->id);
            $hiddenpre = array_filter($prereqs, fn ($pid) => isset($hiddenset[$pid]));
            if (!empty($hiddenpre)) {
                $risks[] = [
                    'type'           => 'dep_on_hidden',
                    'severity'       => 'error',
                    'probability'    => 1.0,
                    'cmids'          => [$cm->id],
                    'related_cmids'  => array_values($hiddenpre),
                    'message_key'    => 'risk_dep_on_hidden',
                    'message_params' => [],
                    'affected_count' => 1,
                ];
            }

            // Separate: hidden activity that others depend on.
            if (!$cm->visible && $depindex->has_dependents($cm->id)) {
                $risks[] = [
                    'type'           => 'hidden_with_dependents',
                    'severity'       => 'warning',
                    'probability'    => 1.0,
                    'cmids'          => [$cm->id],
                    'related_cmids'  => $depindex->get_dependents($cm->id),
                    'message_key'    => 'risk_hidden_with_dependents',
                    'message_params' => [],
                    'affected_count' => count($depindex->get_dependents($cm->id)),
                ];
            }
        }
        return $risks;
    }

    /**
     * Detect temporal inversions: activity B has a deadline before the
     * availability window of its prerequisite A closes.
     *
     * This is a conservative check: we compare date restrictions on A
     * (specifically any 'from' or 'until' type) against B's own dates.
     *
     * @param cm_item[]        $cms      Course modules keyed by cmid.
     * @param dependency_index $depindex Dependency index.
     * @return array[] Risk items.
     */
    private function detect_deadline_inversions(array $cms, dependency_index $depindex): array {
        $risks = [];
        foreach ($cms as $cm) {
            $prereqs = $depindex->get_prerequisites($cm->id);
            if (empty($prereqs)) {
                continue;
            }
            $daterestrictions = $depindex->get_date_restrictions($cm->id);
            // Find earliest 'until' date on this CM (its access window closes).
            $untilts = null;
            foreach ($daterestrictions as $dr) {
                if (($dr['direction'] ?? '') === 'until' && !empty($dr['timestamp'])) {
                    if ($untilts === null || $dr['timestamp'] < $untilts) {
                        $untilts = (int)$dr['timestamp'];
                    }
                }
            }
            if ($untilts === null) {
                continue;
            }
            foreach ($prereqs as $preqcmid) {
                $preqdates = $depindex->get_date_restrictions($preqcmid);
                foreach ($preqdates as $pd) {
                    if (($pd['direction'] ?? '') === 'from' && !empty($pd['timestamp'])) {
                        if ((int)$pd['timestamp'] >= $untilts) {
                            $risks[] = [
                                'type'           => 'deadline_before_dep_window',
                                'severity'       => 'error',
                                'probability'    => 1.0,
                                'cmids'          => [$cm->id],
                                'related_cmids'  => [$preqcmid],
                                'message_key'    => 'risk_deadline_before_dep_window',
                                'message_params' => [],
                                'affected_count' => 1,
                            ];
                        }
                    }
                }
            }
        }
        return $risks;
    }

    /**
     * Detect completionexpected set on activities with completion tracking off.
     *
     * @param cm_item[] $cms Course modules keyed by cmid.
     * @return array[] Risk items.
     */
    private function detect_completion_tracking_mismatch(array $cms): array {
        $risks = [];
        foreach ($cms as $cm) {
            if ($cm->completionexpected > 0 && $cm->completion === 0) {
                $risks[] = [
                    'type'           => 'completion_required_no_tracking',
                    'severity'       => 'warning',
                    'probability'    => 1.0,
                    'cmids'          => [$cm->id],
                    'related_cmids'  => [],
                    'message_key'    => 'risk_completion_required_no_tracking',
                    'message_params' => [],
                    'affected_count' => 1,
                ];
            }
        }
        return $risks;
    }

    /**
     * Detect dependency chains exceeding the configured depth limit.
     *
     * Uses BFS from each root (node with no prerequisites) and tracks
     * the longest path to each node.
     *
     * @param cm_item[]        $cms      Course modules keyed by cmid.
     * @param dependency_index $depindex Dependency index.
     * @return array[] Risk items.
     */
    private function detect_long_chains(array $cms, dependency_index $depindex): array {
        $forward = $depindex->get_all_forward();
        $reverse = $depindex->get_all_reverse();
        $allcmids = array_keys($cms);

        // BFS from each root node (no prerequisites) to find max depth per node.
        $maxdepth = [];
        foreach ($allcmids as $cmid) {
            $maxdepth[$cmid] = 0;
        }
        $roots = array_filter($allcmids, fn ($id) => empty($forward[$id]));
        $queue = [];
        foreach ($roots as $root) {
            $queue[] = [$root, 0];
        }
        $processed = [];
        while (!empty($queue)) {
            [$cmid, $depth] = array_shift($queue);
            if ($depth > ($maxdepth[$cmid] ?? 0)) {
                $maxdepth[$cmid] = $depth;
            }
            if (isset($processed[$cmid][$depth])) {
                continue;
            }
            $processed[$cmid][$depth] = true;
            foreach ($reverse[$cmid] ?? [] as $dependent) {
                $queue[] = [$dependent, $depth + 1];
            }
        }

        // Collect nodes at or beyond the limit.
        $risks = [];
        $overlong = array_filter($allcmids, fn ($id) => ($maxdepth[$id] ?? 0) >= $this->maxchaindepth);
        if (!empty($overlong)) {
            $risks[] = [
                'type'           => 'long_dep_chain',
                'severity'       => 'notice',
                'probability'    => 1.0,
                'cmids'          => array_values($overlong),
                'related_cmids'  => [],
                'message_key'    => 'risk_long_dep_chain',
                'message_params' => ['limit' => $this->maxchaindepth],
                'affected_count' => count($overlong),
            ];
        }
        return $risks;
    }
}
