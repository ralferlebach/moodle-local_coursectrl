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
 * Group resolver for the Course Control Hub.
 *
 * Loads all groups and groupings defined in a course and provides fast
 * membership lookups. Results are cached per instance so that multiple
 * consumers in a single request (availability checks, simulation,
 * timeline filters) share one database round-trip.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\analysis;

/**
 * Resolves and caches the groups and groupings of a single course.
 */
class group_resolver {
    /** @var int The course id this instance was built for. */
    private int $courseid;

    /** @var array<int, string> Group id → group name. */
    private array $groups = [];

    /** @var array<int, string> Grouping id → grouping name. */
    private array $groupings = [];

    /** @var array<int, int[]> Grouping id → list of group ids it contains. */
    private array $groupinggroups = [];

    /** @var bool Whether data has been loaded from the DB. */
    private bool $loaded = false;

    /**
     * Constructor.
     *
     * @param int $courseid The course to resolve groups for.
     */
    public function __construct(int $courseid) {
        $this->courseid = $courseid;
    }

    /**
     * Whether the given group id exists in this course.
     *
     * @param int $groupid Moodle group id.
     * @return bool
     */
    public function group_exists(int $groupid): bool {
        $this->ensure_loaded();
        return isset($this->groups[$groupid]);
    }

    /**
     * Whether the given grouping id exists in this course.
     *
     * @param int $groupingid Moodle grouping id.
     * @return bool
     */
    public function grouping_exists(int $groupingid): bool {
        $this->ensure_loaded();
        return isset($this->groupings[$groupingid]);
    }

    /**
     * Return all groups in the course as an array for template export.
     *
     * @return array[] Each entry: ['id' => int, 'name' => string].
     */
    public function get_groups_for_template(): array {
        $this->ensure_loaded();
        $result = [];
        foreach ($this->groups as $id => $name) {
            $result[] = ['id' => $id, 'name' => $name];
        }
        return $result;
    }

    /**
     * Return all groupings in the course as an array for template export.
     *
     * @return array[] Each entry: ['id' => int, 'name' => string].
     */
    public function get_groupings_for_template(): array {
        $this->ensure_loaded();
        $result = [];
        foreach ($this->groupings as $id => $name) {
            $result[] = ['id' => $id, 'name' => $name];
        }
        return $result;
    }

    /**
     * Return all group ids in this course.
     *
     * @return int[]
     */
    public function get_group_ids(): array {
        $this->ensure_loaded();
        return array_keys($this->groups);
    }

    /**
     * Return all grouping ids in this course.
     *
     * @return int[]
     */
    public function get_grouping_ids(): array {
        $this->ensure_loaded();
        return array_keys($this->groupings);
    }

    /**
     * Return the name of a group, or null if it does not exist.
     *
     * @param int $groupid Moodle group id.
     * @return string|null
     */
    public function get_group_name(int $groupid): ?string {
        $this->ensure_loaded();
        return $this->groups[$groupid] ?? null;
    }

    /**
     * Return the name of a grouping, or null if it does not exist.
     *
     * @param int $groupingid Moodle grouping id.
     * @return string|null
     */
    public function get_grouping_name(int $groupingid): ?string {
        $this->ensure_loaded();
        return $this->groupings[$groupingid] ?? null;
    }

    /**
     * Load groups and groupings from the database (once).
     */
    private function ensure_loaded(): void {
        if ($this->loaded) {
            return;
        }
        $this->load();
        $this->loaded = true;
    }

    /**
     * Perform the database queries to populate groups and groupings.
     */
    private function load(): void {
        global $DB;

        $grouprecords = $DB->get_records(
            'groups',
            ['courseid' => $this->courseid],
            'name ASC',
            'id, name'
        );
        foreach ($grouprecords as $rec) {
            $this->groups[(int) $rec->id] = (string) $rec->name;
        }

        $groupingrecords = $DB->get_records(
            'groupings',
            ['courseid' => $this->courseid],
            'name ASC',
            'id, name'
        );
        foreach ($groupingrecords as $rec) {
            $this->groupings[(int) $rec->id] = (string) $rec->name;
        }

        if (!empty($this->groupings)) {
            [$insql, $inparams] = $DB->get_in_or_equal(
                array_keys($this->groupings),
                SQL_PARAMS_NAMED
            );
            $members = $DB->get_records_sql(
                "SELECT groupingid, groupid
                   FROM {groupings_groups}
                  WHERE groupingid $insql",
                $inparams
            );
            foreach ($members as $m) {
                $this->groupinggroups[(int) $m->groupingid][] = (int) $m->groupid;
            }
        }
    }
}
