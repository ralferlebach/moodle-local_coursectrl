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
 * Renderable for the Course Control Hub history and rollback page.
 *
 * Exports the list of executed batches for a course with rollback buttons
 * for batches that have snapshots and are still in 'executed' status.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\output;

use local_coursectrl\manager\rollback_manager;
use renderable;
use renderer_base;
use templatable;

/**
 * History and rollback page renderable.
 */
class history_page implements renderable, templatable {
    /** @var int Course id. */
    protected int $courseid;

    /** @var array|null Rollback result to surface, or null. */
    protected ?array $rollbackresult;

    /**
     * Constructor.
     *
     * @param int        $courseid      The course being displayed.
     * @param array|null $rollbackresult Result of a just-executed rollback, or null.
     */
    public function __construct(int $courseid, ?array $rollbackresult = null) {
        $this->courseid = $courseid;
        $this->rollbackresult = $rollbackresult;
    }

    /**
     * Build template context for templates/history.mustache.
     *
     * @param renderer_base $output Renderer (unused, required by interface).
     * @return array<string,mixed>
     */
    public function export_for_template(renderer_base $output): array {
        $manager = new rollback_manager();
        $batches = $manager->get_course_batches($this->courseid);

        $batchrows = [];
        foreach ($batches as $batch) {
            $batchrows[] = [
                'id' => $batch['id'],
                'action' => $batch['action'],
                'status' => $batch['status'],
                'status_executed' => $batch['status'] === 'executed',
                'status_rolled_back' => $batch['status'] === 'rolled_back',
                'status_failed' => $batch['status'] === 'failed',
                'timecreated_formatted' => $batch['timecreated_formatted'],
                'itemcount' => $batch['itemcount'],
                'has_snapshots' => $batch['has_snapshots'],
                'can_rollback' => $batch['can_rollback'],
            ];
        }

        // Format rollback result if present.
        $hasrollbackresult = $this->rollbackresult !== null;
        $rollbacksuccess = $hasrollbackresult && ($this->rollbackresult['success'] ?? false);
        $rollbackerror = $hasrollbackresult ? ($this->rollbackresult['error'] ?? '') : '';
        $rollbackitems = [];
        if ($hasrollbackresult) {
            foreach ($this->rollbackresult['items'] ?? [] as $item) {
                $rollbackitems[] = [
                    'entityid' => $item['entityid'],
                    'component' => $item['component'],
                    'status' => $item['status'],
                    'isrestored' => $item['status'] === 'restored',
                    'iserror' => $item['status'] === 'error',
                    'message' => $item['message'],
                ];
            }
        }

        $courseid = $this->courseid;
        return [
            'courseid' => $courseid,
            'sesskey' => sesskey(),
            'batchrows' => $batchrows,
            'hasbatchrows' => count($batchrows) > 0,
            'batchcount' => count($batchrows),
            'hasrollbackresult' => $hasrollbackresult,
            'rollbacksuccess' => $rollbacksuccess,
            'rollbackerror' => $rollbackerror,
            'rollbackitems' => $rollbackitems,
            'hasrollbackitems' => count($rollbackitems) > 0,
            'rollbackrestored' => $this->rollbackresult['restored'] ?? 0,
            'rollbackfailed' => $this->rollbackresult['failed'] ?? 0,
            'rollbackurl' => (new \moodle_url(
                '/local/coursectrl/history.php',
                ['courseid' => $courseid]
            ))->out(false),
            'dashboardurl' => (new \moodle_url(
                '/local/coursectrl/index.php',
                ['courseid' => $courseid]
            ))->out(false),
            'timelineurl' => (new \moodle_url(
                '/local/coursectrl/timeline.php',
                ['courseid' => $courseid]
            ))->out(false),
        ];
    }
}
