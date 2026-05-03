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
 *
 * Performance contract: build_for_course() issues O(1) database queries per
 * course plus O(1) queries per distinct module type in the course. It does
 * not issue per-cmid queries.
 */
class inventory_service {
    /**
     * Text fields to read per module type.
     *
     * Each entry lists the field names on the module's primary table (same
     * table as the one queried for the activity record) that should be
     * scanned by the text-datetime extractor. A module missing from this map
     * contributes no text items.
     *
     * @var array<string, string[]>
     */
    private const TEXT_FIELDS_BY_MODULE = [
        'assign'      => ['intro', 'activity'],
        'book'        => ['intro'],
        'capquiz'     => ['intro'],
        'chat'        => ['intro'],
        'choice'      => ['intro'],
        'choicegroup' => ['intro'],
        'data'        => ['intro'],
        'feedback'    => ['intro', 'page_after_submit'],
        'folder'      => ['intro'],
        'forum'       => ['intro'],
        'glossary'    => ['intro'],
        'h5pactivity' => ['intro'],
        'imscp'       => ['intro'],
        'label'       => ['intro'],
        'lesson'      => ['intro'],
        'lti'         => ['intro'],
        'page'        => ['content', 'intro'],
        'questionnaire' => ['intro'],
        'quiz'        => ['intro'],
        'resource'    => ['intro'],
        'scorm'       => ['intro'],
        'studentquiz' => ['intro'],
        'survey'      => ['intro'],
        'url'         => ['intro'],
        'wiki'        => ['intro'],
        'workshop'    => ['intro', 'instructauthors', 'instructreviewers', 'conclusion'],
    ];

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
        $texts    = $this->collect_texts($course, $sections, $cms);

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
            id: (int) $record->id,
            fullname: (string) $record->fullname,
            shortname: (string) $record->shortname,
            summary: (string) ($record->summary ?? ''),
            summaryformat: (int) ($record->summaryformat ?? 1),
            startdate: (int) ($record->startdate ?? 0),
            enddate: !empty($record->enddate) ? (int) $record->enddate : null,
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
        $rows   = $DB->get_records(
            'course_sections',
            ['course' => $courseid],
            'section ASC',
            'id,section,name,summary,summaryformat,visible,availability,itemid'
        );
        $result = [];
        foreach ($rows as $row) {
            $avail = (!empty($row->availability)) ? (string) $row->availability : null;
            $result[(int) $row->id] = new section_item(
                id: (int) $row->id,
                courseid: $courseid,
                sectionnum: (int) $row->section,
                name: (isset($row->name) && $row->name !== '') ? (string) $row->name : null,
                summary: (string) ($row->summary ?? ''),
                summaryformat: (int) ($row->summaryformat ?? 1),
                visible: !empty($row->visible),
                availability: $avail,
                itemid: (int) ($row->itemid ?? 0),
            );
        }
        return $result;
    }

    /**
     * Load all course modules via modinfo and normalise them into cm_items.
     *
     * Uses one bulk query against course_modules to filter out modules with
     * deletioninprogress = 1 (which get_fast_modinfo may still return from a
     * stale cache) and to pick up the completionexpected field.
     *
     * @param int $courseid Moodle course id.
     * @param course_item $course Normalised course entity.
     * @param array<int,section_item> $sections Section entities keyed by section id.
     * @param array<int,cm_item> $cms Course-module entities keyed by cmid.
     * @return array<int,cm_item> keyed by cmid.
     */
    protected function build_cms(int $courseid): array {
        global $DB;
        $modinfo = get_fast_modinfo($courseid);

        $cmids = [];
        foreach ($modinfo->get_cms() as $cm) {
            $cmids[] = (int) $cm->id;
        }
        if (empty($cmids)) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal($cmids, SQL_PARAMS_NAMED);
        $params['dip'] = 0;
        $rows = $DB->get_records_select(
            'course_modules',
            "id {$insql} AND deletioninprogress = :dip",
            $params,
            '',
            'id, completionexpected'
        );
        $activemap = [];
        foreach ($rows as $row) {
            $activemap[(int) $row->id] = (int) ($row->completionexpected ?? 0);
        }

        $result = [];
        foreach ($modinfo->get_cms() as $cm) {
            if (!isset($activemap[(int) $cm->id])) {
                continue;
            }
            $result[(int) $cm->id] = new cm_item(
                id: (int) $cm->id,
                courseid: $courseid,
                sectionid: (int) $cm->section,
                modname: (string) $cm->modname,
                instance: (int) $cm->instance,
                name: (string) $cm->name,
                visible: (bool) $cm->visible,
                availability: ($cm->availability !== null && $cm->availability !== '')
                    ? (string) $cm->availability : null,
                completion: (int) $cm->completion,
                completionexpected: $activemap[(int) $cm->id],
            );
        }
        return $result;
    }

    /**
     * Collect all text items from course, sections and course modules.
     *
     * Course modules are grouped by module name and loaded with one
     * get_records_list() query per module type, regardless of how many
     * instances exist. All text fields for a module type (intro, content,
     * extra fields) are read in that single query.
     *
     * @param course_item            $course   Course entity.
     * @param array<int,section_item> $sections Section entities.
     * @param array<int,cm_item>      $cms      Course-module entities.
     * @return array<string, text_item> Text items keyed by their entity key.
     */
    protected function collect_texts(course_item $course, array $sections, array $cms): array {
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

        // Group cmids by modname so one DB query per module type is enough.
        $cmidsbymodname = [];
        $instancetocmid = [];
        foreach ($cms as $cm) {
            $modname = $cm->modname;
            if (!isset(self::TEXT_FIELDS_BY_MODULE[$modname])) {
                continue;
            }
            $cmidsbymodname[$modname][] = $cm->instance;
            $instancetocmid[$modname][$cm->instance] = $cm->id;
        }

        foreach ($cmidsbymodname as $modname => $instanceids) {
            // Pre-filter fields against the actual table schema so we never
            // request a column that does not exist in this Moodle installation.
            $tablecolumns = array_keys($DB->get_columns($modname));
            $fields = array_values(array_filter(
                self::TEXT_FIELDS_BY_MODULE[$modname],
                static fn (string $field): bool => in_array($field, $tablecolumns, true)
            ));
            if (empty($fields)) {
                continue;
            }
            $selectfields = 'id,' . implode(',', $fields);
            $records = $DB->get_records_list($modname, 'id', $instanceids, '', $selectfields);
            foreach ($records as $record) {
                $cmid = $instancetocmid[$modname][(int) $record->id] ?? null;
                if ($cmid === null) {
                    continue;
                }
                foreach ($fields as $field) {
                    if (empty($record->$field)) {
                        continue;
                    }
                    $text = new text_item(
                        entitytype: text_item::OWNER_CM,
                        entityid: (int) $cmid,
                        fieldname: $field,
                        content: (string) $record->$field,
                        format: FORMAT_HTML,
                    );
                    $result[$text->get_key()] = $text;
                }
            }
        }

        return $result;
    }
}
