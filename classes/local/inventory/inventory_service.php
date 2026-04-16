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
    protected function collect_texts(course_item $course, array $sections): array {
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

        return $result;
    }
}
