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
 * Event fired by batch_manager when a bulk action batch has been executed.
 *
 * Carries the batch id as objectid, the course id and acting user id, and
 * the action plus per-status counts in 'other'. Logging plugins and audit
 * tools can subscribe to this event to track bulk modifications.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\event;

/**
 * Event fired after a batch_manager::execute() call has finished.
 */
class batch_executed extends \core\event\base {
    /**
     * Initialise the event metadata.
     *
     * @return void
     */
    protected function init(): void {
        $this->data['crud']        = 'u';
        $this->data['edulevel']    = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_coursectrl_batch';
    }

    /**
     * Localised event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('event_batch_executed', 'local_coursectrl');
    }

    /**
     * Human-readable description of the event.
     *
     * @return string
     */
    public function get_description(): string {
        $action = $this->other['action'] ?? 'unknown';
        return "User {$this->userid} executed bulk action '{$action}' as batch {$this->objectid} in course {$this->courseid}.";
    }

    /**
     * URL to the affected resource (the bulk dashboard for the course).
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        return new \moodle_url('/local/coursectrl/index.php', ['courseid' => $this->courseid]);
    }
}
