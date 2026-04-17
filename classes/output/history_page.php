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
 * Renderable for the Logs & Historie page.
 *
 * Reads batch records from local_coursectrl_batch (most recent first) and
 * enriches them with per-item counts and rollback availability info.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\output;

use renderable;
use renderer_base;
use templatable;

/**
 * Renderable for the Logs & Historie page.
 */
class history_page implements renderable, templatable {
    /** @var int The course id. */
    private int $courseid;

    /** @var int Maximum number of batches to show (from settings, default 100). */
    private int $maxbatches;

    /** @var array|null Rollback result to surface in the page, or null. */
    private ?array $rollbackresult;

    /**
     * Constructor.
     *
     * @param int        $courseid       The course id.
     * @param array|null $rollbackresult Optional rollback result from rollback.php.
     * @param int        $maxbatches     Maximum number of batches to show.
     */
    public function __construct(int $courseid, ?array $rollbackresult = null, int $maxbatches = 100) {
        $this->courseid = $courseid;
        $this->rollbackresult = $rollbackresult;
        $this->maxbatches = $maxbatches;
    }

    /**
     * Export for template.
     *
     * @param renderer_base $output Renderer.
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        global $DB;
        $course = get_course($this->courseid);
        $context = \context_course::instance($this->courseid);
        $canrollback = has_capability('local/coursectrl:rollback', $context);
        $dateformat = get_string('strftimedaydatetime', 'core_langconfig');

        $maxbatches = (int) (get_config('local_coursectrl', 'history_maxcount') ?: $this->maxbatches);

        $batches = $DB->get_records(
            'local_coursectrl_batch',
            ['courseid' => $this->courseid],
            'timecreated DESC',
            '*',
            0,
            $maxbatches
        );

        $rows = [];
        foreach ($batches as $batch) {
            $items = $DB->count_records(
                'local_coursectrl_batch_item',
                ['batchid' => $batch->id]
            );
            $hassnapshot = $DB->record_exists(
                'local_coursectrl_snapshot',
                ['batchid' => $batch->id]
            );
            $user = $DB->get_record(
                'user',
                ['id' => $batch->userid],
                'id, firstname, lastname, email, firstnamephonetic, lastnamephonetic, middlename, alternatename',
                IGNORE_MISSING
            );
            $username = $user
                ? fullname($user)
                : get_string('unknownuser', 'local_coursectrl');

            $rows[] = [
                'batchid'     => (int) $batch->id,
                'action'      => (string) $batch->action,
                'actionlabel' => get_string('action_' . $batch->action, 'local_coursectrl', null, true)
                    ?: (string) $batch->action,
                'status'      => (string) $batch->status,
                'itemcount'   => $items,
                'username'    => $username,
                'timeago'     => format_time(time() - $batch->timecreated),
                'timeformatted' => userdate($batch->timecreated, $dateformat),
                'canrollback' => $canrollback && $hassnapshot
                    && $batch->status === \local_coursectrl\local\persistent\batch::STATUS_EXECUTED,
                'rolledback'  => $batch->status === \local_coursectrl\local\persistent\batch::STATUS_ROLLED_BACK,
                'rollbackurl' => (new \moodle_url(
                    '/local/coursectrl/rollback.php',
                    ['batchid' => $batch->id, 'courseid' => $this->courseid, 'sesskey' => sesskey()]
                ))->out(false),
            ];
        }

        return [
            'courseid'       => $this->courseid,
            'coursefullname' => format_string($course->fullname),
            'sesskey'        => sesskey(),
            'rows'           => $rows,
            'hasrows'        => count($rows) > 0,
            'rowcount'       => count($rows),
            'maxbatches'     => $maxbatches,
            'canrollback'    => $canrollback,
            'batchrows'      => $rows,
            'hasbatchrows'   => count($rows) > 0,
            'batchcount'     => count($rows),
            'hasrollbackresult' => $this->rollbackresult !== null,
            'rollbacksuccess'   => !empty($this->rollbackresult['success']),
            'rollbackrestored'  => (int) ($this->rollbackresult['restored'] ?? 0),
            'rollbackfailed'    => (int) ($this->rollbackresult['failed'] ?? 0),
            'rollbackerror'     => (string) ($this->rollbackresult['error'] ?? ''),
            'rollbackitems'     => $this->build_rollback_items($this->rollbackresult['items'] ?? []),
            'rollbackurl'    => (new \moodle_url(
                '/local/coursectrl/rollback.php',
                ['courseid' => $this->courseid, 'sesskey' => sesskey()]
            ))->out(false),
            'dashboardurl'   => (new \moodle_url(
                '/local/coursectrl/index.php',
                ['courseid' => $this->courseid]
            ))->out(false),
        ];
    }

    /**
     * Build the rollback items array for template rendering.
     *
     * @param array $items Raw rollback item arrays from rollback_manager.
     * @return array Enriched items with boolean status flags.
     */
    private function build_rollback_items(array $items): array {
        $result = [];
        foreach ($items as $item) {
            $status = (string) ($item['status'] ?? '');
            $result[] = [
                'entityid'   => (int) ($item['entityid'] ?? 0),
                'component'  => (string) ($item['component'] ?? ''),
                'status'     => $status,
                'message'    => (string) ($item['message'] ?? ''),
                'isrestored' => $status === 'restored',
                'iserror'    => $status === 'error',
            ];
        }
        return $result;
    }
}
