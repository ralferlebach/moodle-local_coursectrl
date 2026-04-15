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
    /** @var array<int, int[]> Forward deps: cmid → cmids it depends on. */
    private array $forward = [];

    /** @var array<int, int[]> Reverse deps: cmid → cmids depending on it. */
    private array $reverse = [];

    /** @var array<int, array> Date restrictions per cmid. */
    private array $daterestrictions = [];

    /** @var array<int, array> Full parsed availability per cmid. */
    private array $parsed = [];

    /** @var availability_parser */
    private availability_parser $parser;

    /**
     * Build the index from cm_items.
     *
     * @param cm_item[] $cms Keyed by cmid.
     */
    public function __construct(array $cms) {
        $this->parser = new availability_parser();
        $this->build($cms);
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
     * Build the index from cm_items.
     *
     * @param cm_item[] $cms Keyed by cmid.
     */
    private function build(array $cms): void {
        foreach ($cms as $cm) {
            $parsed = $this->parser->parse($cm->availability);
            $this->parsed[$cm->id] = $parsed;

            if (!empty($parsed['dateconditions'])) {
                $this->daterestrictions[$cm->id] = $parsed['dateconditions'];
            }

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
        }
    }
}
