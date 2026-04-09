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
 * Normalised editable-text entity for the Course Control Hub inventory.
 *
 * A text_item represents a single editable text field attached to another
 * entity (course summary, section summary, activity intro, label content,
 * ...). It is the unit of work for the text-datetime engine (Phase 5).
 *
 * @package    local_coursectrl
 * @copyright  2026 Course Control Hub Contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\entity;

/**
 * Immutable DTO carrying one addressable text field.
 *
 * Unlike course_item, section_item and cm_item, a text_item has no Moodle
 * id of its own. It is identified by the triple (entitytype, entityid,
 * fieldname).
 */
final class text_item extends inventory_item {
    /** Owner entity is a course. */
    public const OWNER_COURSE = 'course';
    /** Owner entity is a course section. */
    public const OWNER_SECTION = 'section';
    /** Owner entity is a course module. */
    public const OWNER_CM = 'cm';
    /** Owner entity is a label activity (treated separately for text workflows). */
    public const OWNER_LABEL = 'label';

    /**
     * Constructor.
     *
     * @param string $entitytype Owner entity type, one of the OWNER_* constants.
     * @param int    $entityid   Moodle id of the owning entity.
     * @param string $fieldname  Name of the field this text belongs to, e.g. 'summary', 'intro'.
     * @param string $content    Raw content of the field.
     * @param int    $format     FORMAT_* constant describing $content.
     */
    public function __construct(
        public readonly string $entitytype,
        public readonly int $entityid,
        public readonly string $fieldname,
        public readonly string $content,
        public readonly int $format,
    ) {
    }

    public function get_type(): string {
        return 'text';
    }

    /**
     * Stable composite key for this text item.
     *
     * Suitable as an array key or cache identifier.
     *
     * @return string composite key in the form "{entitytype}:{entityid}:{fieldname}".
     */
    public function get_key(): string {
        return $this->entitytype . ':' . $this->entityid . ':' . $this->fieldname;
    }

    public function to_array(): array {
        return [
            'type'       => $this->get_type(),
            'entitytype' => $this->entitytype,
            'entityid'   => $this->entityid,
            'fieldname'  => $this->fieldname,
            'content'    => $this->content,
            'format'     => $this->format,
        ];
    }

    public static function from_array(array $data): static {
        $cls = static::class;
        return new self(
            entitytype: (string) self::require_key($data, 'entitytype', $cls),
            entityid:   (int)    self::require_key($data, 'entityid', $cls),
            fieldname:  (string) self::require_key($data, 'fieldname', $cls),
            content:    (string) ($data['content'] ?? ''),
            format:     (int)    ($data['format'] ?? 1),
        );
    }
}
