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
 * External function: get_text_hits.
 *
 * Triggers a fresh text-datetime scan for a course and returns all
 * detected hits grouped by confidence level.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;
use local_coursectrl\local\field_label_resolver;
use local_coursectrl\manager\textreview_manager;

/**
 * AJAX-callable wrapper for text-datetime scanning.
 */
class get_text_hits extends external_api {
    /**
     * Declare the parameters accepted by execute().
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'rescan' => new external_value(
                PARAM_BOOL,
                'Whether to run a fresh scan before returning hits',
                VALUE_DEFAULT,
                true
            ),
        ]);
    }

    /**
     * Scan and return text-datetime hits for a course.
     *
     * @param int  $courseid Course id.
     * @param bool $rescan   Whether to trigger a fresh scan.
     * @return array
     */
    public static function execute(int $courseid, bool $rescan = true): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'rescan' => $rescan,
        ]);

        global $DB, $PAGE;
        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/coursectrl:view', $context);

        $manager = new textreview_manager();

        $summary = ['total' => 0, 'safe' => 0, 'ambiguous' => 0, 'informational' => 0];
        if ($params['rescan']) {
            try {
                $summary = $manager->scan_course($params['courseid']);
            } catch (\Throwable $e) {
                debugging(
                    'local_coursectrl get_text_hits scan failed: ' . $e->getMessage(),
                    DEBUG_DEVELOPER
                );
            }
        }

        $rawhits = $manager->get_hits($params['courseid']);

        // Bulk-load CM info for all cm-type hits in a single JOIN query
        // to avoid N+1 get_coursemodule_from_id() calls.
        $cmhitids = [];
        foreach ($rawhits as $hit) {
            if ($hit->get('entitytype') === 'cm') {
                $cmhitids[(int) $hit->get('entityid')] = true;
            }
        }
        $cminfobycmid = [];
        if (!empty($cmhitids)) {
            [$insql, $inparams] = $DB->get_in_or_equal(
                array_keys($cmhitids),
                SQL_PARAMS_NAMED
            );
            $inparams['courseid'] = $params['courseid'];
            $sql = "SELECT cm.id, m.name AS modname, cm.instance
                      FROM {course_modules} cm
                      JOIN {modules} m ON m.id = cm.module
                     WHERE cm.course = :courseid
                       AND cm.id {$insql}";

            // Group rows by module type so we can bulk-load instance names
            // with one query per module table instead of one per CM row.
            $bymodname = [];
            $cmrowbyid = [];
            foreach ($DB->get_records_sql($sql, $inparams) as $row) {
                $bymodname[(string) $row->modname][(int) $row->id] = (int) $row->instance;
                $cmrowbyid[(int) $row->id] = (string) $row->modname;
            }
            foreach ($bymodname as $modname => $cmidtoinstance) {
                $instanceids = array_values($cmidtoinstance);
                $namebyinstance = $DB->get_records_list(
                    $modname,
                    'id',
                    $instanceids,
                    '',
                    'id, name'
                );
                foreach ($cmidtoinstance as $cmid => $instanceid) {
                    $cminfobycmid[$cmid] = [
                        'name'    => $namebyinstance[$instanceid]->name ?? '',
                        'modname' => $modname,
                    ];
                }
            }
        }

        $hits = [];
        foreach ($rawhits as $hit) {
            $entitytype = $hit->get('entitytype');
            $entityid   = (int) $hit->get('entityid');
            $cmname  = '';
            $cmurl   = '';
            $modname = '';
            $iconurl = '';
            if ($entitytype === 'cm') {
                $cminfo  = $cminfobycmid[$entityid] ?? [];
                $cmname  = $cminfo['name'] ?? '';
                $modname = $cminfo['modname'] ?? '';
                if ($cmname !== '' && $modname !== '') {
                    $cmurl = (new \moodle_url(
                        '/mod/' . $modname . '/view.php',
                        ['id' => $entityid]
                    ))->out(false);
                    $iconurl = (new \moodle_url('/theme/image.php', [
                        'theme'     => isset($PAGE->theme) ? $PAGE->theme->name : 'boost',
                        'component' => 'mod_' . $modname,
                        'image'     => 'monologo',
                        'rev'       => -1,
                    ]))->out(false);
                }
            }
            $normalizedval = $hit->get('normalizedvalue') ?? '';
            $contextraw = $hit->get('contextjson') ?? '';
            $contextdata = $contextraw ? json_decode($contextraw, true) : [];
            $hitpattern = $contextdata['pattern'] ?? '';
            $noyearpatterns = ['de_dmy_noyear', 'de_numeric_noyear', 'en_mdy_noyear'];
            $isnoyear = in_array($hitpattern, $noyearpatterns, true);
            $assumedyear = ($isnoyear && $normalizedval !== '')
                ? (int) substr($normalizedval, 0, 4) : 0;
            $hits[] = [
                'id' => (int) $hit->get('id'),
                'entitytype' => $entitytype,
                'entityid' => $entityid,
                'cmname' => $cmname,
                'cmurl'  => $cmurl,
                'modname' => $modname,
                'iconurl' => $iconurl,
                'fieldname'  => $hit->get('fieldname'),
                'fieldlabel' => field_label_resolver::resolve(
                    (string) $hit->get('fieldname'),
                    $modname,
                    $entitytype
                ),
                'matchedtext' => $hit->get('matchedtext'),
                'normalizedvalue' => $normalizedval,
                'normalizedts' => $normalizedval ? (int) strtotime($normalizedval) : 0,
                'confidence' => $hit->get('confidence'),
                'noyear' => $isnoyear,
                'assumedyear' => $assumedyear,
                'pattern' => $hitpattern,
                'contextjson' => $hit->get('contextjson') ?? '',
            ];
        }

        return [
            'hits' => $hits,
            'summary' => $summary,
        ];
    }

    /**
     * Declare the return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'hits' => new external_multiple_structure(
                new external_single_structure([
                    'id' => new external_value(PARAM_INT, 'Text hit row id'),
                    'entitytype' => new external_value(PARAM_ALPHANUMEXT, 'Owner entity type'),
                    'entityid' => new external_value(PARAM_INT, 'Owner entity id'),
                    'cmname' => new external_value(PARAM_TEXT, 'Activity name', VALUE_OPTIONAL, ''),
                    'cmurl' => new external_value(PARAM_URL, 'Activity URL', VALUE_OPTIONAL, ''),
                    'modname' => new external_value(PARAM_ALPHANUMEXT, 'Module name', VALUE_OPTIONAL, ''),
                    'iconurl' => new external_value(PARAM_URL, 'Module icon URL', VALUE_OPTIONAL, ''),
                    'fieldname'  => new external_value(PARAM_ALPHANUMEXT, 'Field name'),
                    'fieldlabel' => new external_value(
                        PARAM_TEXT,
                        'Localised field label',
                        VALUE_OPTIONAL,
                        ''
                    ),
                    'matchedtext' => new external_value(PARAM_RAW, 'Matched date substring'),
                    'normalizedvalue' => new external_value(PARAM_RAW, 'ISO 8601 normalised value'),
                    'normalizedts' => new external_value(PARAM_INT, 'Unix timestamp of normalised value', VALUE_OPTIONAL, 0),
                    'confidence' => new external_value(PARAM_ALPHANUMEXT, 'Confidence: safe, ambiguous, informational'),
                    'noyear' => new external_value(PARAM_BOOL, 'True if year was assumed', VALUE_OPTIONAL, false),
                    'assumedyear' => new external_value(PARAM_INT, 'Assumed year (0 if not applicable)', VALUE_OPTIONAL, 0),
                    'pattern' => new external_value(PARAM_ALPHANUMEXT, 'Matched pattern name', VALUE_OPTIONAL, ''),
                    'contextjson' => new external_value(PARAM_RAW, 'JSON context with offset and excerpts'),
                ])
            ),
            'summary' => new external_single_structure([
                'total' => new external_value(PARAM_INT, 'Total hits found'),
                'safe' => new external_value(PARAM_INT, 'Safe hits'),
                'ambiguous' => new external_value(PARAM_INT, 'Ambiguous hits'),
                'informational' => new external_value(PARAM_INT, 'Informational hits'),
            ]),
        ]);
    }
}
