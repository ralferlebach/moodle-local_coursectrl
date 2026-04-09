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
 * Normalised course entity for the Course Control Hub inventory.
 *
 * @package    local_coursectrl
 * @copyright  2026 Course Control Hub Contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\entity;

/**
 * Immutable DTO carrying the course-level fields the hub cares about.
 */
final class course_item extends inventory_item {
    /**
     * Constructor.
     *
     * @param int      $id            Moodle course id.
     * @param string   $fullname      Course full name.
     * @param string   $shortname     Course short name.
     * @param string   $summary       Course summary HTML / text.
     * @param int      $summaryformat FORMAT_* constant for the summary.
     * @param int      $startdate     Unix timestamp of the course start.
     * @param int|null $enddate       Unix timestamp of the course end, or null.
     * @param bool     $visible       Whether the course is visible to students.
     */
    public function __construct(
        public readonly int $id,
        public readonly string $fullname,
        public readonly string $shortname,
        public readonly string $summary,
        public readonly int $summaryformat,
        public readonly int $startdate,
        public readonly ?int $enddate,
        public readonly bool $visible,
    ) {
    }

    public function get_type(): string {
        return 'course';
    }

    public function to_array(): array {
        return [
            'type'          => $this->get_type(),
            'id'            => $this->id,
            'fullname'      => $this->fullname,
            'shortname'     => $this->shortname,
            'summary'       => $this->summary,
            'summaryformat' => $this->summaryformat,
            'startdate'     => $this->startdate,
            'enddate'       => $this->enddate,
            'visible'       => $this->visible,
        ];
    }

    public static function from_array(array $data): static {
        $cls = static::class;
        return new self(
            id:            (int)    self::require_key($data, 'id', $cls),
            fullname:      (string) self::require_key($data, 'fullname', $cls),
            shortname:     (string) self::require_key($data, 'shortname', $cls),
            summary:       (string) ($data['summary'] ?? ''),
            summaryformat: (int)    ($data['summaryformat'] ?? 1),
            startdate:     (int)    self::require_key($data, 'startdate', $cls),
            enddate:       isset($data['enddate']) ? (int)$data['enddate'] : null,
            visible:       (bool)   ($data['visible'] ?? true),
        );
    }
}
