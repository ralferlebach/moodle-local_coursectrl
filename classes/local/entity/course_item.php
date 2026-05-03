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
    /** @var int Moodle course id. */
    public readonly int $id;

    /** @var string Course full name. */
    public readonly string $fullname;

    /** @var string Course short name. */
    public readonly string $shortname;

    /** @var string Course summary HTML or text. */
    public readonly string $summary;

    /** @var int FORMAT_* constant describing the summary. */
    public readonly int $summaryformat;

    /** @var int Unix timestamp of the course start. */
    public readonly int $startdate;

    /** @var int|null Unix timestamp of the course end, or null. */
    public readonly ?int $enddate;

    /** @var bool Whether the course is visible to students. */
    public readonly bool $visible;

    /**
     * Constructor.
     *
     * @param int      $id            Moodle course id.
     * @param string   $fullname      Course full name.
     * @param string   $shortname     Course short name.
     * @param string   $summary       Course summary HTML or text.
     * @param int      $summaryformat FORMAT_* constant for the summary.
     * @param int      $startdate     Unix timestamp of the course start.
     * @param int|null $enddate       Unix timestamp of the course end, or null.
     * @param bool     $visible       Whether the course is visible to students.
     */
    public function __construct(
        int $id,
        string $fullname,
        string $shortname,
        string $summary,
        int $summaryformat,
        int $startdate,
        ?int $enddate,
        bool $visible
    ) {
        $this->id = $id;
        $this->fullname = $fullname;
        $this->shortname = $shortname;
        $this->summary = $summary;
        $this->summaryformat = $summaryformat;
        $this->startdate = $startdate;
        $this->enddate = $enddate;
        $this->visible = $visible;
    }

    /**
     * Return the type discriminator.
     *
     * @return string always 'course'.
     */
    public function get_type(): string {
        return 'course';
    }

    /**
     * Return a plain array representation suitable for serialisation.
     *
     * @return array<string,mixed>
     */
    public function to_array(): array {
        return [
            'type' => $this->get_type(),
            'id' => $this->id,
            'fullname' => $this->fullname,
            'shortname' => $this->shortname,
            'summary' => $this->summary,
            'summaryformat' => $this->summaryformat,
            'startdate' => $this->startdate,
            'enddate' => $this->enddate,
            'visible' => $this->visible,
        ];
    }

    /**
     * Reconstruct a course_item from its array representation.
     *
     * @param array $data serialised entity.
     * @return static
     * @throws \coding_exception when a required key is missing.
     */
    public static function from_array(array $data): static {
        $cls = static::class;
        return new self(
            (int) self::require_key($data, 'id', $cls),
            (string) self::require_key($data, 'fullname', $cls),
            (string) self::require_key($data, 'shortname', $cls),
            (string) ($data['summary'] ?? ''),
            (int) ($data['summaryformat'] ?? 1),
            (int) self::require_key($data, 'startdate', $cls),
            isset($data['enddate']) ? (int) $data['enddate'] : null,
            (bool) ($data['visible'] ?? true)
        );
    }
}
