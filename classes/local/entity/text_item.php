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
final class text_item extends inventory_item
{
    /** Owner entity is a course. */
    public const OWNER_COURSE = 'course';

    /** Owner entity is a course section. */
    public const OWNER_SECTION = 'section';

    /** Owner entity is a course module. */
    public const OWNER_CM = 'cm';

    /** Owner entity is a label activity. */
    public const OWNER_LABEL = 'label';

    /** @var string Owner entity type, one of the OWNER_* constants. */
    public readonly string $entitytype;

    /** @var int Moodle id of the owning entity. */
    public readonly int $entityid;

    /** @var string Name of the field this text belongs to, e.g. 'summary', 'intro'. */
    public readonly string $fieldname;

    /** @var string Raw content of the field. */
    public readonly string $content;

    /** @var int FORMAT_* constant describing the content. */
    public readonly int $format;

    /**
     * Constructor.
     *
     * @param string $entitytype owner entity type, one of the OWNER_* constants
     * @param int    $entityid   moodle id of the owning entity
     * @param string $fieldname  name of the field this text belongs to
     * @param string $content    raw content of the field
     * @param int    $format     FORMAT_* constant describing the content
     */
    public function __construct(
        string $entitytype,
        int $entityid,
        string $fieldname,
        string $content,
        int $format
    ) {
        $this->entitytype = $entitytype;
        $this->entityid = $entityid;
        $this->fieldname = $fieldname;
        $this->content = $content;
        $this->format = $format;
    }

    /**
     * Return the type discriminator.
     *
     * @return string always 'text'
     */
    public function get_type(): string
    {
        return 'text';
    }

    /**
     * Return a stable composite key for this text item.
     *
     * @return string composite key in the form "{entitytype}:{entityid}:{fieldname}"
     */
    public function get_key(): string
    {
        return $this->entitytype.':'.$this->entityid.':'.$this->fieldname;
    }

    /**
     * Return a plain array representation suitable for serialisation.
     *
     * @return array<string,mixed>
     */
    public function to_array(): array
    {
        return [
            'type' => $this->get_type(),
            'entitytype' => $this->entitytype,
            'entityid' => $this->entityid,
            'fieldname' => $this->fieldname,
            'content' => $this->content,
            'format' => $this->format,
        ];
    }

    /**
     * Reconstruct a text_item from its array representation.
     *
     * @param array<string,mixed> $data serialised entity
     *
     * @throws \coding_exception when a required key is missing
     */
    public static function from_array(array $data): static
    {
        $cls = self::class;

        return new self(
            (string) self::require_key($data, 'entitytype', $cls),
            (int) self::require_key($data, 'entityid', $cls),
            (string) self::require_key($data, 'fieldname', $cls),
            (string) ($data['content'] ?? ''),
            (int) ($data['format'] ?? 1)
        );
    }
}
