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
 * Renderable for the bulk-action preview page.
 *
 * Formats the preview_manager result into a human-readable confirmation
 * table with old/new date values, skipped items and errors.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\output;

use local_coursectrl\local\dto\preview_change;
use renderable;
use renderer_base;
use templatable;

/**
 * Renderable for the bulk-action preview page.
 */
class preview_page implements renderable, templatable {
    /** @var int Course id. */
    protected int $courseid;

    /** @var string Action identifier. */
    protected string $action;

    /** @var array Raw payload. */
    protected array $payload;

    /** @var int[] Selected cmids. */
    protected array $cmids;

    /** @var array Result from preview_manager::build(). */
    protected array $result;

    /**
     * Constructor.
     *
     * @param int    $courseid Course id.
     * @param string $action   Action identifier.
     * @param array  $payload  Raw payload.
     * @param int[]  $cmids    Selected cmids.
     * @param array  $result   Result from preview_manager::build().
     */
    public function __construct(
        int $courseid,
        string $action,
        array $payload,
        array $cmids,
        array $result
    ) {
        $this->courseid = $courseid;
        $this->action = $action;
        $this->payload = $payload;
        $this->cmids = $cmids;
        $this->result = $result;
    }

    /**
     * Build template context for templates/preview.mustache.
     *
     * @param renderer_base $output Renderer (unused, required by interface).
     * @return array
     */
    public function export_for_template(renderer_base $output): array {
        $dateformat = get_string('strftimedaydatetime', 'core_langconfig');

        $rows = [];
        /** @var preview_change $change */
        foreach ($this->result['changes'] as $change) {
            $fieldrows = [];
            foreach ($change->get_fields() as $fieldname => $descriptor) {
                $old = (int)($descriptor['old'] ?? 0);
                $new = (int)($descriptor['new'] ?? 0);
                $shifted = !empty($descriptor['shifted']);
                $reason = $descriptor['reason'] ?? '';
                $fieldrows[] = [
                    'fieldname' => $fieldname,
                    'oldvalue' => $old > 0 ? userdate($old, $dateformat) : '–',
                    'newvalue' => $new > 0 ? userdate($new, $dateformat) : '–',
                    'shifted' => $shifted,
                    'isunset' => $reason === 'unset',
                    'reason' => $reason,
                    'cmrowspan' => count($change->get_fields()),
                    'component' => $change->get_component(),
                    'name' => $change->get_name(),
                ];
            }
            if (!empty($fieldrows)) {
                $fieldrows[0]['first'] = true;
            }
            $rows[] = [
                'cmid' => $change->get_cmid(),
                'component' => $change->get_component(),
                'name' => $change->get_name(),
                'haschanges' => $change->has_changes(),
                'fields' => $fieldrows,
                'fieldcount' => count($fieldrows),
            ];
        }

        $skipped = [];
        foreach ($this->result['skipped'] as $skip) {
            $skipped[] = [
                'cmid' => (int)($skip['cmid'] ?? 0),
                'reason' => (string)($skip['reason'] ?? 'unknown'),
            ];
        }

        $errors = [];
        foreach ($this->result['errors'] as $error) {
            $errors[] = [
                'cmid' => (int)($error['cmid'] ?? 0),
                'code' => (string)($error['code'] ?? 'unknown'),
                'message' => (string)($error['message'] ?? ''),
            ];
        }

        $summary = $this->result['summary'];

        $payloadjson = json_encode($this->payload);
        $cmidsjson = json_encode($this->cmids);

        $actionlabel = get_string('action_' . $this->action, 'local_coursectrl');
        $deltalabel = '';
        if ($this->action === 'shift_dates' && isset($this->payload['delta'])) {
            $deltaseconds = (int)$this->payload['delta'];
            $absseconds = abs($deltaseconds);
            $days = intdiv($absseconds, 86400);
            $hours = intdiv($absseconds % 86400, 3600);
            $parts = [];
            if ($days !== 0) {
                $parts[] = $days . ' ' . get_string('days');
            }
            if ($hours !== 0) {
                $parts[] = $hours . ' ' . get_string('hours');
            }
            $deltalabel = implode(', ', $parts);
            if ($deltalabel === '') {
                $deltalabel = '0';
            } else if ($deltaseconds > 0) {
                $deltalabel = '+' . $deltalabel;
            } else if ($deltaseconds < 0) {
                $deltalabel = '-' . $deltalabel;
            }
        }

        return [
            'courseid' => $this->courseid,
            'action' => $this->action,
            'actionlabel' => $actionlabel,
            'deltalabel' => $deltalabel,
            'payloadjson' => $payloadjson,
            'cmidsjson' => $cmidsjson,
            'sesskey' => sesskey(),
            'executeurl' => (new \moodle_url('/local/coursectrl/execute.php'))->out(false),
            'manageurl' => (new \moodle_url(
                '/local/coursectrl/manage.php',
                ['courseid' => $this->courseid]
            ))->out(false),
            'rows' => $rows,
            'hasrows' => count($rows) > 0,
            'skipped' => $skipped,
            'hasskipped' => count($skipped) > 0,
            'errors' => $errors,
            'haserrors' => count($errors) > 0,
            'summary_total' => (int)$summary['total'],
            'summary_changes' => (int)$summary['changes'],
            'summary_skipped' => (int)$summary['skipped'],
            'summary_errors' => (int)$summary['errors'],
            'canexecute' => (int)$summary['changes'] > 0 && (int)$summary['errors'] === 0,
        ];
    }
}
