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
 * Renderable for the bulk-action execution result page.
 *
 * Shows a summary of the batch that was just executed: batch id,
 * overall status, and per-status item counts.
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
 * Renderable for the batch execution result page.
 */
class result_page implements renderable, templatable {
    /** @var int Course id. */
    protected int $courseid;

    /** @var int Batch id. */
    protected int $batchid;

    /** @var string Batch status. */
    protected string $status;

    /** @var array Summary counts. */
    protected array $summary;

    /** @var string Action label. */
    protected string $action;

    /**
     * Constructor.
     *
     * @param int    $courseid Course id.
     * @param int    $batchid  Batch id.
     * @param string $status   Batch status.
     * @param array  $summary  Summary counts (total, success, skipped, error).
     * @param string $action   Action identifier.
     */
    public function __construct(
        int $courseid,
        int $batchid,
        string $status,
        array $summary,
        string $action
    ) {
        $this->courseid = $courseid;
        $this->batchid = $batchid;
        $this->status = $status;
        $this->summary = $summary;
        $this->action = $action;
    }

    /**
     * Build template context for templates/result.mustache.
     *
     * @param renderer_base $output Renderer (unused, required by interface).
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $issuccess = $this->status === 'executed';

        return [
            'courseid' => $this->courseid,
            'batchid' => $this->batchid,
            'status' => $this->status,
            'issuccess' => $issuccess,
            'actionlabel' => get_string('action_' . $this->action, 'local_coursectrl'),
            'summary_total' => (int)($this->summary['total'] ?? 0),
            'summary_success' => (int)($this->summary['success'] ?? 0),
            'summary_skipped' => (int)($this->summary['skipped'] ?? 0),
            'summary_error' => (int)($this->summary['error'] ?? 0),
            'dashboardurl' => (new \moodle_url(
                '/local/coursectrl/index.php',
                ['courseid' => $this->courseid]
            ))->out(false),
            'manageurl' => (new \moodle_url(
                '/local/coursectrl/manage.php',
                ['courseid' => $this->courseid]
            ))->out(false),
        ];
    }
}
