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
 * Builds normalised inventory snapshots for a course.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local\inventory;

use local_coursectrl\local\entity\cm_item;
use local_coursectrl\local\entity\course_item;
use local_coursectrl\local\entity\section_item;
use local_coursectrl\local\entity\text_item;

/**
 * Core inventory builder.
 */
class inventory_service {
    /**
     * Build a complete inventory snapshot for a course.
     *
     * @param int $courseid Moodle course id.
     * @return inventory_snapshot
     * @throws \dml_missing_record_exception when the course does not exist.
     */
    public function build_for_course(int $courseid): inventory_snapshot {
        $course   = $this->build_course($courseid);
        $sections = $this->build_sections($courseid);
        $cms      = $this->build_cms($courseid);
        $texts    = $this->collect_texts($course, $sections);

        return new inventory_snapshot($course, $sections, $cms, $texts);
    }

    /**
     * Load the course row and normalise it into a course_item.
     *
     * @param int $courseid Moodle course id.
     * @return course_item
     */
    protected function build_course(int $courseid): course_item {
        global $DB;
        $record = $DB->get_record('course', ['id' => $courseid], '*', \MUST_EXIST);
        return new course_item(
            id: (int)$record->id,
            fullname: (string)$record->fullname,
            shortname: (string)$record->shortname,
            summary: (string)($record->summary ?? ''),
            summaryformat: (int)($record->summaryformat ?? 1),
            startdate: (int)($record->startdate ?? 0),
            enddate: !empty($record->enddate) ? (int)$record->enddate : null,
            visible: !empty($record->visible),
        );
    }

    /**
     * Load all course sections and normalise them into section_items.
     *
     * @param int $courseid Moodle course id.
     * @return array<int,section_item> keyed by course_sections.id.
     */
    protected function build_sections(int $courseid): array {
        global $DB;
        $rows   = $DB->get_records('course_sections', ['course' => $courseid], 'section ASC');
        $result = [];
        foreach ($rows as $row) {
            $result[(int)$row->id] = new section_item(
                id: (int)$row->id,
                courseid: $courseid,
                sectionnum: (int)$row->section,
                name: (isset($row->name) && $row->name !== '') ? (string)$row->name : null,
                summary: (string)($row->summary ?? ''),
                summaryformat: (int)($row->summaryformat ?? 1),
                visible: !empty($row->visible),
            );
        }
        return $result;
    }

    /**
     * Load all course modules via modinfo and normalise them into cm_items.
     *
     * Includes the completionexpected field from the course_modules table
     * which drives the Moodle timeline reminder.
     *
     * @param int $courseid Moodle course id.
     * @return array<int,cm_item> keyed by cmid.
     */
    protected function build_cms(int $courseid): array {
        global $DB;
        $modinfo = get_fast_modinfo($courseid);

        $cmids = [];
        foreach ($modinfo->get_cms() as $cm) {
            $cmids[] = (int)$cm->id;
        }
        $expectedmap = [];
        if (!empty($cmids)) {
            [$insql, $params] = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED);
            $rows = $DB->get_records_select(
                'course_modules',
                "id {$insql}",
                $params,
                '',
                'id, completionexpected'
            );
            foreach ($rows as $row) {
                $expectedmap[(int)$row->id] = (int)($row->completionexpected ?? 0);
            }
        }

        $result = [];
        foreach ($modinfo->get_cms() as $cm) {
            $result[(int)$cm->id] = new cm_item(
                id: (int)$cm->id,
                courseid: $courseid,
                sectionid: (int)$cm->section,
                modname: (string)$cm->modname,
                instance: (int)$cm->instance,
                name: (string)$cm->name,
                visible: (bool)$cm->visible,
                availability: ($cm->availability !== null && $cm->availability !== '')
                    ? (string)$cm->availability : null,
                completion: (int)$cm->completion,
                completionexpected: $expectedmap[(int)$cm->id] ?? 0,
            );
        }
        return $result;
    }

    /**
     * Collect editable text fields from the course and its sections.
     *
     * @param course_item             $course   The course entity.
     * @param array<int,section_item> $sections The section entities.
     * @return array<string,text_item> keyed by text_item::get_key().
     */
    /**
     * Map of module name → additional text fields beyond the standard intro/content field.
     *
     * These fields can contain free-text with date references that should be
     * picked up by the text-datetime scanner. They are read from the module's
     * primary table (same table as intro/content).
     *
     * @var array<string, string[]>
     */
    private const EXTRA_TEXT_FIELDS = [
        // Mod_assign: Aktivitätsanleitung (activity instructions).
        'assign' => ['activity'],
        // Mod_feedback: Seite nach dem Absenden.
        'feedback' => ['page_after_submit'],
        // Mod_workshop: three rich-text instruction fields.
        'workshop' => ['instructauthors', 'instructreviewers', 'conclusion'],
        // Mod_page: has both content (primary) and intro.
        'page' => ['intro'],
    ];

    /**
     * Collect all text items from course, sections and course modules.
     *
     * @param course_item $course   Course entity.
     * @param array       $sections Section entities keyed by section id.
     * @return array<string, text_item> Text items keyed by their entity key.
     */
    protected function collect_texts(course_item $course, array $sections): array {
        global $DB;
        $result = [];

        if ($course->summary !== '') {
            $text = new text_item(
                entitytype: text_item::OWNER_COURSE,
                entityid: $course->id,
                fieldname: 'summary',
                content: $course->summary,
                format: $course->summaryformat,
            );
            $result[$text->get_key()] = $text;
        }

        foreach ($sections as $section) {
            if ($section->summary === '') {
                continue;
            }
            $text = new text_item(
                entitytype: text_item::OWNER_SECTION,
                entityid: $section->id,
                fieldname: 'summary',
                content: $section->summary,
                format: $section->summaryformat,
            );
            $result[$text->get_key()] = $text;
        }

        // Collect intro/content fields from course module instances.
        // Build a per-modname cache of which field to read ('intro', 'content', or null).
        $fieldcache = [];
        try {
            $modinfo = get_fast_modinfo($course->id);
        } catch (\Throwable $e) {
            return $result;
        }

        foreach ($modinfo->get_cms() as $cm) {
            $modname = (string)$cm->modname;

            // Determine which text field this module type uses (cached per modname).
            if (!array_key_exists($modname, $fieldcache)) {
                $fieldcache[$modname] = $this->resolve_text_field($modname, $DB);
            }
            $fieldname = $fieldcache[$modname];
            if ($fieldname === null) {
                continue;
            }

            try {
                $record = $DB->get_record(
                    $modname,
                    ['id' => (int)$cm->instance],
                    'id,' . $fieldname
                );
                if (!$record || empty($record->$fieldname)) {
                    continue;
                }
                $text = new text_item(
                    entitytype: text_item::OWNER_CM,
                    entityid: (int)$cm->id,
                    fieldname: $fieldname,
                    content: (string)$record->$fieldname,
                    format: FORMAT_HTML,
                );
                $result[$text->get_key()] = $text;
            } catch (\Throwable $e) {
                continue;
            }

            // Also scan plugin-specific extra text fields for this module type.
            $extrafields = self::EXTRA_TEXT_FIELDS[$modname] ?? [];
            foreach ($extrafields as $extrafield) {
                try {
                    $extrarec = $DB->get_record(
                        $modname,
                        ['id' => (int)$cm->instance],
                        'id,' . $extrafield
                    );
                    if (!$extrarec || empty($extrarec->$extrafield)) {
                        continue;
                    }
                    $extratext = new text_item(
                        entitytype: text_item::OWNER_CM,
                        entityid: (int)$cm->id,
                        fieldname: $extrafield,
                        content: (string)$extrarec->$extrafield,
                        format: FORMAT_HTML,
                    );
                    $result[$extratext->get_key()] = $extratext;
                } catch (\Throwable $e) {
                    debugging(
                        'local_coursectrl: collect_texts extra field ' . $extrafield .
                        ' failed for ' . $modname . ': ' . $e->getMessage(),
                        DEBUG_DEVELOPER
                    );
                }
            }
        }

        return $result;
    }

    /**
     * Determine which text column to read for a given module type.
     *
     * Returns 'content' for mod_page, 'intro' for modules that have it,
     * and null when the table or a suitable column does not exist.
     *
     * @param string   $modname Module name (e.g. 'assign').
     * @param \moodle_database $DB Moodle database instance.
     * @return string|null Column name or null.
     */
    private function resolve_text_field(string $modname, \moodle_database $DB): ?string {
        // Mod_page stores its body in 'content', not 'intro'.
        if ($modname === 'page') {
            return 'content';
        }
        // All other standard modules use 'intro' if the column exists.
        try {
            $columns = $DB->get_columns($modname);
            if (array_key_exists('intro', $columns)) {
                return 'intro';
            }
        } catch (\Throwable $e) {
            // Table might not exist or schema lookup failed — skip gracefully.
            debugging(
                'local_coursectrl: resolve_text_field failed for ' . $modname . ': ' . $e->getMessage(),
                DEBUG_DEVELOPER
            );
        }
        return null;
    }
}
