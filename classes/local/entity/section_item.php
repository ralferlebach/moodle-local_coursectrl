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
 * Normalised course section entity for the Course Control Hub inventory.
 *
 * @package    local_coursectrl
 * @copyright  2026 Course Control Hub Contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\entity;

/**
 * Immutable DTO carrying the section-level fields the hub cares about.
 */
final class section_item extends inventory_item {
    /**
     * Constructor.
     *
     * @param int         $id            Moodle course_sections.id.
     * @param int         $courseid      Parent course id.
     * @param int         $sectionnum    0-based section number within the course.
     * @param string|null $name          Explicit section name, or null if auto-generated.
     * @param string      $summary       Section summary HTML / text.
     * @param int         $summaryformat FORMAT_* constant for the summary.
     * @param bool        $visible       Section visibility flag.
     */
    public function __construct(
        public readonly int $id,
        public readonly int $courseid,
        public readonly int $sectionnum,
        public readonly ?string $name,
        public readonly string $summary,
        public readonly int $summaryformat,
        public readonly bool $visible,
    ) {
    }

    public function get_type(): string {
        return 'section';
    }

    public function to_array(): array {
        return [
            'type'          => $this->get_type(),
            'id'            => $this->id,
            'courseid'      => $this->courseid,
            'sectionnum'    => $this->sectionnum,
            'name'          => $this->name,
            'summary'       => $this->summary,
            'summaryformat' => $this->summaryformat,
            'visible'       => $this->visible,
        ];
    }

    public static function from_array(array $data): static {
        $cls = static::class;
        return new self(
            id:            (int) self::require_key($data, 'id', $cls),
            courseid:      (int) self::require_key($data, 'courseid', $cls),
            sectionnum:    (int) self::require_key($data, 'sectionnum', $cls),
            name:          isset($data['name']) ? (string)$data['name'] : null,
            summary:       (string) ($data['summary'] ?? ''),
            summaryformat: (int)    ($data['summaryformat'] ?? 1),
            visible:       (bool)   ($data['visible'] ?? true),
        );
    }
}
