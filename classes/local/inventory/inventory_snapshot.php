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
 * Immutable snapshot of the normalised inventory of a single course.
 *
 * @package    local_coursectrl
 * @copyright  2026 Course Control Hub Contributors
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\inventory;

use local_coursectrl\local\entity\cm_item;
use local_coursectrl\local\entity\course_item;
use local_coursectrl\local\entity\section_item;
use local_coursectrl\local\entity\text_item;

/**
 * Aggregates the four entity collections produced by inventory_service.
 *
 * A snapshot is a read-only value object. It is the unit of work for the
 * selector UI, the bulk preview engine, the visualisation layer and the
 * simulation engine.
 */
final class inventory_snapshot implements \JsonSerializable {
    /** @var course_item The normalised course entity. */
    public readonly course_item $course;

    /** @var array<int,section_item> Sections keyed by section id. */
    public readonly array $sections;

    /** @var array<int,cm_item> Course modules keyed by cmid. */
    public readonly array $cms;

* @param course_item $course Normalised course entity.
* @param array<int,section_item> $sections Section entities keyed by section id.
* @param array<int,cm_item> $cms Course-module entities keyed by cmid.
* @param array<string,text_item> $texts Editable texts keyed by text_item::get_key().
    /** @var array<string,text_item> Editable texts keyed by text_item::get_key(). */
    public readonly array $texts;

    /**
     * Constructor.
     *
     * @param course_item             $course   The normalised course entity.
     * @param array<int,section_item> $sections Sections keyed by section id.
     * @param array<int,cm_item>      $cms      Course modules keyed by cmid.
     * @param array<string,text_item> $texts    Editable texts keyed by text_item::get_key().
     */
    public function __construct(course_item $course, array $sections, array $cms, array $texts) {
        $this->course = $course;
        $this->sections = $sections;
        $this->cms = $cms;
        $this->texts = $texts;
    }

    /**
     * Number of course sections in the snapshot.
     *
     * @return int
     */
    public function count_sections(): int {
        return count($this->sections);
    }

    /**
     * Number of course modules in the snapshot.
     *
     * @return int
     */
    public function count_cms(): int {
        return count($this->cms);
    }

    /**
     * Number of editable texts in the snapshot.
     *
     * @return int
     */
    public function count_texts(): int {
        return count($this->texts);
    }

    /**
     * Return a plain array representation of the snapshot.
     *
     * @return array<string,mixed>
     */
    public function to_array(): array {
        return [
            'course' => $this->course->to_array(),
            'sections' => array_values(array_map(fn($s) => $s->to_array(), $this->sections)),
            'cms' => array_values(array_map(fn($c) => $c->to_array(), $this->cms)),
            'texts' => array_values(array_map(fn($t) => $t->to_array(), $this->texts)),
        ];
    }

    /**
     * JsonSerializable hook.
     *
     * @return array<string,mixed>
     */
    public function jsonSerialize(): array {
        return $this->to_array();
    }
}
