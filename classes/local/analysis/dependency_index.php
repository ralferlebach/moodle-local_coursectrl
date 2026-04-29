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
 * Dependency index for course-module completion relationships.
 *
 * Scans all cm_items in an inventory snapshot and builds two maps:
 *
 *   - forward: cmid → list of cmids this activity depends on
 *              (i.e. the availability of cmid requires completion of those)
 *   - reverse: cmid → list of cmids that depend on this activity
 *              (i.e. completing cmid unlocks those activities)
 *
 * Also collects date-based restrictions and provides a unified
 * per-cm restriction summary for the dashboard.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\analysis;

use local_coursectrl\local\entity\cm_item;

/**
 * Immutable dependency index built from an inventory snapshot.
 */
class dependency_index {
    /** @var array<int, int[]> Forward deps: cmid → cmids it depends on (completion). */
    private array $forward = [];

    /** @var array<int, int[]> Reverse deps: cmid → cmids depending on it. */
    private array $reverse = [];

    /** @var array<int, int[]> Grade-based forward deps: cmid → cmids it grades-depends on. */
    private array $gradeforward = [];

    /** @var array<int, int[]> Unlock-only forward deps: only completion conditions with e=1 (must complete). */
    private array $unlockforward = [];

    /** @var array<int, array> Date restrictions per cmid. */
    private array $daterestrictions = [];

    /** @var array<int, array> Full parsed availability per cmid. */
    private array $parsed = [];

    /** @var availability_parser */
    private availability_parser $parser;

    /**
     * Get forward dependency map filtered for multiple group memberships.
     *
     * A CM's edges are included if its group conditions are satisfied by
     * at least one of the given group ids, or if it has no group conditions.
     *
     * @param int[] $groupids Group ids (empty = return all forward deps).
     * @return array<int, int[]>
     */
    public function get_all_forward_for_groups(array $groupids): array {
        if (empty($groupids)) {
            return $this->forward;
        }
        $filtered = [];
        foreach ($this->forward as $cmid => $deps) {
            $parsed = $this->parsed[$cmid] ?? [];
            $groupconds = $parsed['groupconditions'] ?? [];
            if (!empty($groupconds)) {
                $requiredgroups = array_column(
                    array_filter($groupconds, fn($g) => $g['type'] === 'group'),
                    'id'
                );
                if (
                    !empty($requiredgroups) &&
                    empty(array_intersect($groupids, $requiredgroups))
                ) {
                    // Not the selected group — hide the dependency edges.
                    continue;
                }
            }
            $filtered[$cmid] = $deps;
        }
        return $filtered;
    }

    /**
     * Build the index from cm_items.
     *
     * @param cm_item[] $cms          Keyed by cmid.
     * @param array<int,int> $gradeitemmap Grade item id → cmid mapping.
     *                                 Required to resolve grade-based availability
     *                                 conditions to cmid pairs for graph edges.
     *                                 Obtained from the grade_items table; pass []
     *                                 when no grade context is available.
     */
    public function __construct(array $cms, array $gradeitemmap = []) {
        $this->parser = new availability_parser();
        $this->build($cms, $gradeitemmap);
    }

    /**
     * Get cmids that this activity depends on (its prerequisites).
     *
     * @param int $cmid Course module id.
     * @return int[] Prerequisite cmids.
     */
    public function get_prerequisites(int $cmid): array {
        return $this->forward[$cmid] ?? [];
    }

    /**
     * Get cmids that depend on this activity (its dependents).
     *
     * @param int $cmid Course module id.
     * @return int[] Dependent cmids.
     */
    public function get_dependents(int $cmid): array {
        return $this->reverse[$cmid] ?? [];
    }

    /**
     * Get date-based availability restrictions for a cmid.
     *
     * @param int $cmid Course module id.
     * @return array[] Date conditions: ['direction' => string, 'timestamp' => int].
     */
    public function get_date_restrictions(int $cmid): array {
        return $this->daterestrictions[$cmid] ?? [];
    }

    /**
     * Get the full parsed availability for a cmid.
     *
     * @param int $cmid Course module id.
     * @return array Parsed availability (from availability_parser).
     */
    public function get_parsed_availability(int $cmid): array {
        return $this->parsed[$cmid] ?? $this->parser->parse(null);
    }

    /**
     * Check whether a cmid has any restrictions at all.
     *
     * @param int $cmid Course module id.
     * @return bool
     */
    public function has_restrictions(int $cmid): bool {
        return !empty($this->parsed[$cmid]['hasrestrictions']);
    }

    /**
     * Check whether a cmid has any dependents (other CMs depend on it).
     *
     * @param int $cmid Course module id.
     * @return bool
     */
    public function has_dependents(int $cmid): bool {
        return !empty($this->reverse[$cmid]);
    }

    /**
     * Get the complete forward dependency map.
     *
     * @return array<int, int[]>
     */
    public function get_all_forward(): array {
        return $this->forward;
    }

    /**
     * Get the forward dependency map filtered for a specific group.
     *
     * When a group id is given, edges whose target CM has availability
     * conditions requiring membership in a DIFFERENT group are excluded.
     * This lets the graph show only the dependencies relevant to members
     * of the selected group.
     *
     * @param int $groupid Group id (0 = no filter, returns all forward deps).
     * @return array<int, int[]>
     */
    public function get_all_forward_for_group(int $groupid): array {
        if ($groupid <= 0) {
            return $this->forward;
        }
        $filtered = [];
        foreach ($this->forward as $cmid => $deps) {
            $parsed = $this->parsed[$cmid] ?? [];
            $groupconds = $parsed['groupconditions'] ?? [];
            // If the target CM requires a specific group and this group
            // Not the selected group — hide the dependency edges.
            if (!empty($groupconds)) {
                $requiredgroups = array_column(
                    array_filter($groupconds, fn($g) => $g['type'] === 'group'),
                    'id'
                );
                if (
                    !empty($requiredgroups) &&
                    !in_array($groupid, $requiredgroups, true)
                ) {
                    // This CM is behind a group wall — its deps are invisible
                    // Group condition unsatisfied — exclude from filtered graph.
                    continue;
                }
            }
            $filtered[$cmid] = $deps;
        }
        return $filtered;
    }

    /**
     * Get the complete reverse dependency map.
     *
     * @return array<int, int[]>
     */
    public function get_all_reverse(): array {
        return $this->reverse;
    }

    /**
     * Detect simple circular dependencies.
     *
     * Returns pairs of cmids that mutually depend on each other.
     *
     * @return array[] Each entry: ['a' => int, 'b' => int].
     */
    public function find_circular_deps(): array {
        $circular = [];
        foreach ($this->forward as $cmid => $deps) {
            foreach ($deps as $dep) {
                if (isset($this->forward[$dep]) && in_array($cmid, $this->forward[$dep], true)) {
                    $pair = [min($cmid, $dep), max($cmid, $dep)];
                    $key = $pair[0] . '-' . $pair[1];
                    if (!isset($circular[$key])) {
                        $circular[$key] = ['a' => $pair[0], 'b' => $pair[1]];
                    }
                }
            }
        }
        return array_values($circular);
    }


    /**
     * Return grade-based forward dependencies: cmid → list of cmids it
     * depends on via a grade availability condition.
     *
     * Only populated when a gradeitemmap was supplied at construction time.
     *
     * @return array<int, int[]>
     */
    public function get_grade_forward(): array {
        return $this->gradeforward;
    }

    /**
     * Build the index from cm_items.
     *
     * @param cm_item[]      $cms          Course modules keyed by cmid.
     * @param array<int,int> $gradeitemmap Grade item id → cmid. Used to resolve
     *                                     grade-based availability conditions.
     */
    private function build(array $cms, array $gradeitemmap = []): void {
        foreach ($cms as $cm) {
            $parsed = $this->parser->parse($cm->availability);
            $this->parsed[$cm->id] = $parsed;

            if (!empty($parsed['dateconditions'])) {
                $this->daterestrictions[$cm->id] = $parsed['dateconditions'];
            }

            // Completion-based forward deps.
            $deps = array_keys($parsed['completiondeps']);
            if (!empty($deps)) {
                $this->forward[$cm->id] = $deps;
                foreach ($deps as $depcmid) {
                    if (!isset($this->reverse[$depcmid])) {
                        $this->reverse[$depcmid] = [];
                    }
                    $this->reverse[$depcmid][] = $cm->id;
                }
            }

            // Unlock-only forward deps: completion conditions where e=1
            // (activity must BE completed to unlock the next one).
            // Lock patterns (e=0, "must NOT be completed") are gate-closing
            // designs, not prerequisites, so they are excluded here.
            $unlockdeps = [];
            foreach ($parsed['completiondeps'] as $depcmid => $expectedstate) {
                if ((int) $expectedstate === 1) {
                    $unlockdeps[] = $depcmid;
                }
            }
            if (!empty($unlockdeps)) {
                $this->unlockforward[$cm->id] = $unlockdeps;
            }

            // Grade-based forward deps — resolved via gradeitemmap.
            if (!empty($gradeitemmap) && !empty($parsed['gradeconditions'])) {
                $gradedeps = [];
                foreach ($parsed['gradeconditions'] as $gcond) {
                    $itemid = $gcond['itemid'] ?? 0;
                    if ($itemid > 0 && isset($gradeitemmap[$itemid])) {
                        $depcmid = (int) $gradeitemmap[$itemid];
                        if ($depcmid !== $cm->id && !in_array($depcmid, $gradedeps, true)) {
                            $gradedeps[] = $depcmid;
                        }
                    }
                }
                if (!empty($gradedeps)) {
                    $this->gradeforward[$cm->id] = $gradedeps;
                }
            }
        }
    }
    /**
     * Get the forward dependency map containing only completion-unlock edges.
     *
     * In Moodle availability conditions, completion conditions have an 'e'
     * (expected) parameter: e=1 means "must complete to unlock" (show when done);
     * e=0 means "hide when done" (a gate-closing pattern, not a deadlock risk).
     *
     * Until per-edge e-type tracking is available in this index, this method
     * returns the full forward map. Edges from gate-closing patterns (e=0) may
     * produce conservative false-positive cycle alerts, but will not miss actual
     * deadlock cycles. Refine filtering when e-type is tracked per edge.
     *
     * @return array<int, int[]>
     */
    public function get_unlock_forward(): array {
        return $this->unlockforward;
    }

    /**
     * Get unlock-forward deps filtered for multiple group memberships.
     *
     * Same as get_all_forward_for_groups() but restricted to e=1 conditions.
     *
     * @param int[] $groupids Group ids (empty = return all unlock forward deps).
     * @return array<int, int[]>
     */
    public function get_unlock_forward_for_groups(array $groupids): array {
        if (empty($groupids)) {
            return $this->unlockforward;
        }
        $result = [];
        foreach ($this->unlockforward as $cmid => $deps) {
            $cm = $this->cms[$cmid] ?? null;
            if ($cm === null) {
                continue;
            }
            $groupparsed = $this->parsed[$cmid] ?? [];
            $requiredgroups = $groupparsed['groupconditions'] ?? [];
            if (!empty($requiredgroups)) {
                if (empty(array_intersect($groupids, $requiredgroups))) {
                    continue;
                }
            }
            $result[$cmid] = $deps;
        }
        return $result;
    }
}
