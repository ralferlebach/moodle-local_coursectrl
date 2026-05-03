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
    /** @var int Moodle course_sections.id. */
    public readonly int $id;

    /** @var int Parent course id. */
    public readonly int $courseid;

    /** @var int 0-based section number within the course. */
    public readonly int $sectionnum;

    /** @var string|null Explicit section name, or null if auto-generated. */
    public readonly ?string $name;

    /** @var string Section summary HTML or text. */
    public readonly string $summary;

    /** @var int FORMAT_* constant describing the summary. */
    public readonly int $summaryformat;

    /** @var bool Section visibility flag. */
    public readonly bool $visible;

    /** @var string|null JSON availability string, or null when unrestricted. */
    public readonly ?string $availability;

    /**
     * @var int Instance id of the owning subsection CM, or 0 when not a subsection.
     * Populated from course_sections.itemid (set by mod_subsection on creation).
     * @param int $id Moodle course_sections.id.
     * @param int $courseid Parent course id.
     * @param int $sectionnum 0-based section number within the course.
     * @param string|null $name Explicit section name, or null.
     * @param string $summary Section summary HTML or text.
     * @param int $summaryformat FORMAT_* constant for the summary.
     * @param bool $visible Section visibility flag.
     */
    public readonly int $itemid;

    /**
     * Constructor.
     *
     * @param int         $id            Moodle course_sections.id.
     * @param int         $courseid      Parent course id.
     * @param int         $sectionnum    0-based section number within the course.
     * @param string|null $name          Explicit section name, or null.
     * @param string      $summary       Section summary HTML or text.
     * @param int         $summaryformat FORMAT_* constant for the summary.
     * @param bool        $visible       Section visibility flag.
     */
    public function __construct(
        int $id,
        int $courseid,
        int $sectionnum,
        ?string $name,
        string $summary,
        int $summaryformat,
        bool $visible,
        ?string $availability = null,
        int $itemid = 0
    ) {
        $this->id = $id;
        $this->courseid = $courseid;
        $this->sectionnum = $sectionnum;
        $this->name = $name;
        $this->summary = $summary;
        $this->summaryformat = $summaryformat;
        $this->visible = $visible;
        $this->availability = $availability;
        $this->itemid = $itemid;
    }

    /**
     * Return the type discriminator.
     *
     * @return string always 'section'.
     */
    public function get_type(): string {
        return 'section';
    }

    /**
     * Return a plain array representation suitable for serialisation.
     *
     * @param array<string,mixed> $data Serialised entity produced by to_array().
     * @return array<string,mixed>
     */
    public function to_array(): array {
        return [
            'type' => $this->get_type(),
            'id' => $this->id,
            'courseid' => $this->courseid,
            'sectionnum' => $this->sectionnum,
            'name' => $this->name,
            'summary' => $this->summary,
            'summaryformat' => $this->summaryformat,
            'visible'       => $this->visible,
            'availability'  => $this->availability,
            'itemid'        => $this->itemid,
        ];
    }

    /**
     * Reconstruct a section_item from its array representation.
     *
     * @param array<string,mixed> $data serialised entity.
     * @return static
     * @throws \coding_exception when a required key is missing.
     */
    public static function from_array(array $data): static {
        $cls = static::class;
        return new self(
            (int) self::require_key($data, 'id', $cls),
            (int) self::require_key($data, 'courseid', $cls),
            (int) self::require_key($data, 'sectionnum', $cls),
            isset($data['name']) ? (string) $data['name'] : null,
            (string) ($data['summary'] ?? ''),
            (int) ($data['summaryformat'] ?? 1),
            (bool) ($data['visible'] ?? true)
        );
    }
}
