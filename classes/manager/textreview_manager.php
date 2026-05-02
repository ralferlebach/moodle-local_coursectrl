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
 * Manager for the text-datetime review workflow.
 *
 * Orchestrates two operations:
 *   1. Scan: runs the text_change_builder pipeline on all text_items
 *      from the course inventory and persists the hits.
 *   2. Apply: takes a set of confirmed text_hit ids and a delta, loads
 *      the original texts, applies the rewriter, and writes back.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\manager;

use local_coursectrl\local\inventory\inventory_service;
use local_coursectrl\local\persistent\text_hit;
use local_coursectrl\local\text\text_change_builder;
use local_coursectrl\local\text\text_datetime_rewriter;

/**
 * Orchestrator for the text-datetime review workflow.
 */
class textreview_manager {
    /** @var text_change_builder */
    private text_change_builder $builder;

    /** @var text_datetime_rewriter */
    private text_datetime_rewriter $rewriter;

    /** @var inventory_service */
    private inventory_service $inventoryservice;

    /**
     * Constructor.
     *
     * @param text_change_builder|null    $builder          Optional custom builder.
     * @param text_datetime_rewriter|null $rewriter         Optional custom rewriter.
     * @param inventory_service|null      $inventoryservice Optional custom inventory service.
     */
    /** @var string[] Allowed text fields on the course table. */
    private const COURSE_TEXT_FIELDS = ['summary', 'fullname', 'shortname'];

    /** @var string[] Allowed text fields on the course_sections table. */
    private const SECTION_TEXT_FIELDS = ['summary', 'name'];

    /** @var string[] Allowed text fields on activity module tables. */
    private const CM_TEXT_FIELDS = [
        'intro',
        'content',
        'name',
        'activity', // Assign: activity description.
        'page_after_submit', // Feedback: completion page text.
        'instructauthors', // Workshop: author instructions.
        'instructreviewers', // Workshop: reviewer instructions.
        'conclusion', // Workshop: conclusion text.
    ];

    /**
     * Construct the textreview_manager.
     *
     * @param inventory_service|null     $inventoryservice Inventory service; defaults to new instance.
     * @param text_datetime_extractor|null $extractor      Text extractor; defaults to new instance.
     * @param text_datetime_rewriter|null  $rewriter       Text rewriter; defaults to new instance.
     */
    public function __construct(
        ?text_change_builder $builder = null,
        ?text_datetime_rewriter $rewriter = null,
        ?inventory_service $inventoryservice = null
    ) {
        $this->builder = $builder ?? new text_change_builder();
        $this->rewriter = $rewriter ?? new text_datetime_rewriter();
        $this->inventoryservice = $inventoryservice ?? new inventory_service();
    }

    /**
     * Scan a course for text-datetime hits and persist them.
     *
     * @param int $courseid Course id.
     * @return array Summary: total, safe, ambiguous, informational.
     */
    public function scan_course(int $courseid): array {
        $snapshot = $this->inventoryservice->build_for_course($courseid);
        return $this->builder->scan_and_persist($courseid, $snapshot->texts);
    }

    /**
     * Load persisted hits for a course, optionally filtered by confidence.
     *
     * @param int         $courseid   Course id.
     * @param string|null $confidence Optional confidence filter.
     * @return text_hit[]
     */
    public function get_hits(int $courseid, ?string $confidence = null): array {
        $conditions = ['courseid' => $courseid];
        if ($confidence !== null) {
            $conditions['confidence'] = $confidence;
        }
        return text_hit::get_records($conditions, 'entitytype, entityid, fieldname');
    }

    /**
     * Apply a delta shift to confirmed text hits.
     *
     * Loads the original text for each unique (entitytype, entityid,
     * fieldname) triple, applies the rewriter, and writes back to the
     * database. Only hits whose id is in $hitids are applied.
     *
     * @param int   $courseid Course id (for validation).
     * @param int[] $hitids   IDs of confirmed text_hit rows.
     * @param int   $delta    Seconds to shift.
     * @return array{applied: int, skipped: int, errors: array}
     */
    public function apply_changes(int $courseid, array $hitids, int $delta): array {
        global $DB;

        // Load and validate hits.
        $hits = [];
        foreach ($hitids as $id) {
            $hit = new text_hit((int) $id);
            if ((int) $hit->get('courseid') !== $courseid) {
                throw new \moodle_exception('accessdenied', 'error');
            }
            $key = $hit->get('entitytype') . ':' . $hit->get('entityid') . ':' . $hit->get('fieldname');
            $hits[$key][] = $hit;
        }

        $totalapplied = 0;
        $totalskipped = 0;
        $errors = [];

        foreach ($hits as $key => $groupedhits) {
            $first = $groupedhits[0];
            $entitytype = $first->get('entitytype');
            $entityid = (int) $first->get('entityid');
            $fieldname = $first->get('fieldname');

            // Validate fieldname against whitelist before any DB access.
            // coding_exception propagates intentionally — a non-whitelisted
            // field indicates a programming error, not a recoverable runtime
            // condition, and must not be silently swallowed.
            $this->require_allowed_field($entitytype, $fieldname);

            // Load original text.
            try {
                $text = $this->load_text($entitytype, $entityid, $fieldname, $courseid);
            } catch (\moodle_exception $e) {
                $errors[] = [
                    'key' => $key,
                    'code' => 'load_failed',
                    'message' => $e->getMessage(),
                ];
                continue;
            }

            // Build hit record arrays for the rewriter.
            $hitrecords = [];
            foreach ($groupedhits as $hit) {
                $hitrecords[] = [
                    'matchedtext' => $hit->get('matchedtext'),
                    'normalizedvalue' => $hit->get('normalizedvalue'),
                    'contextjson' => $hit->get('contextjson'),
                ];
            }

            $result = $this->rewriter->rewrite($text, $hitrecords, $delta);

            if (!empty($result['applied'])) {
                try {
                    $this->save_text($entitytype, $entityid, $fieldname, $result['text'], $courseid);
                    $totalapplied += count($result['applied']);
                } catch (\moodle_exception $e) {
                    $errors[] = [
                        'key' => $key,
                        'code' => 'save_failed',
                        'message' => $e->getMessage(),
                    ];
                }
            }
            $totalskipped += count($result['skipped']);
        }

        // Purge cached hits so the next scan starts from a clean state.
        $this->purge_hits($courseid);

        return [
            'applied' => $totalapplied,
            'skipped' => $totalskipped,
            'errors' => $errors,
        ];
    }

    /**
     * Delete all cached text_hit rows for a course.
     *
     * Called after any change that modifies activity text fields so the next
     * call to get_text_hits (rescan=true) starts from a clean slate.
     *
     * @param int $courseid Course id.
     */
    public function purge_hits(int $courseid): void {
        global $DB;
        $DB->delete_records('local_coursectrl_text_hit', ['courseid' => $courseid]);
    }

    /**
     * Load a text field from the database.
     *
     * @param string $entitytype Entity type (course, section, cm).
     * @param int    $entityid   Entity id.
     * @param string $fieldname  Field name.
     * @return string Text content.
     * @throws \coding_exception When the entity type or field is unknown.
     */
    /**
     * Throw a coding_exception if $fieldname is not on the whitelist for $entitytype.
     *
     * Prevents $fieldname — which originates from persisted data — from being used
     * as a raw column name in DB operations without validation.
     *
     * @param string $entitytype Entity type: course, section, or cm.
     * @param string $fieldname  Field name to validate.
     * @throws \coding_exception When the field is not in the whitelist.
     */
    private function require_allowed_field(string $entitytype, string $fieldname): void {
        $allowed = match ($entitytype) {
            'course'  => self::COURSE_TEXT_FIELDS,
            'section' => self::SECTION_TEXT_FIELDS,
            'cm'      => self::CM_TEXT_FIELDS,
            default   => throw new \coding_exception('Unknown entity type: ' . $entitytype),
        };
        if (!in_array($fieldname, $allowed, true)) {
            throw new \coding_exception('Invalid text field for ' . $entitytype . ': ' . $fieldname);
        }
    }

    /**
     * Load a text field from the database, optionally scoped to a course.
     *
     * @param string $entitytype Entity type: course, section, or cm.
     * @param int    $entityid   Entity id.
     * @param string $fieldname  Field name (must pass whitelist check).
     * @param int    $courseid   When >0, adds a course-binding WHERE clause.
     * @return string Text content.
     * @throws \coding_exception When the entity type or field is unknown.
     */
    private function load_text(
        string $entitytype,
        int $entityid,
        string $fieldname,
        int $courseid = 0
    ): string {
        global $DB;
        $this->require_allowed_field($entitytype, $fieldname);
        switch ($entitytype) {
            case 'course':
                $record = $DB->get_record('course', ['id' => $entityid], $fieldname, MUST_EXIST);
                return (string) ($record->$fieldname ?? '');
            case 'section':
                $where = ['id' => $entityid];
                if ($courseid > 0) {
                    $where['course'] = $courseid;
                }
                $record = $DB->get_record('course_sections', $where, $fieldname, MUST_EXIST);
                return (string) ($record->$fieldname ?? '');
            case 'cm':
                $cmwhere = ['id' => $entityid];
                if ($courseid > 0) {
                    $cmwhere['course'] = $courseid;
                }
                $cm = $DB->get_record('course_modules', $cmwhere, 'module, instance', MUST_EXIST);
                $modulename = $DB->get_field('modules', 'name', ['id' => $cm->module]);
                $record = $DB->get_record($modulename, ['id' => $cm->instance], $fieldname, MUST_EXIST);
                return (string) ($record->$fieldname ?? '');
            default:
                throw new \coding_exception('Unknown entity type: ' . $entitytype);
        }
    }

    /**
     * Write a text field back to the database.
     *
     * @param string $entitytype Entity type (course, section, cm).
     * @param int    $entityid   Entity id.
     * @param string $fieldname  Field name.
     * @param string $text       New text content.
     * @throws \coding_exception When the entity type is unknown.
     */
    private function save_text(
        string $entitytype,
        int $entityid,
        string $fieldname,
        string $text,
        int $courseid = 0
    ): void {
        global $DB;
        $this->require_allowed_field($entitytype, $fieldname);
        switch ($entitytype) {
            case 'course':
                $DB->set_field('course', $fieldname, $text, ['id' => $entityid]);
                break;
            case 'section':
                $where = ['id' => $entityid];
                if ($courseid > 0) {
                    $where['course'] = $courseid;
                }
                $DB->set_field('course_sections', $fieldname, $text, $where);
                break;
            case 'cm':
                $cmwhere = ['id' => $entityid];
                if ($courseid > 0) {
                    $cmwhere['course'] = $courseid;
                }
                $cm = $DB->get_record('course_modules', $cmwhere, 'module, instance', MUST_EXIST);
                $modulename = $DB->get_field('modules', 'name', ['id' => $cm->module]);
                $DB->set_field($modulename, $fieldname, $text, ['id' => $cm->instance]);
                break;
            default:
                throw new \coding_exception('Unknown entity type: ' . $entitytype);
        }
    }
}
