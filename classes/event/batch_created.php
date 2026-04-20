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
 * Event fired by batch_manager when a new bulk action batch record is created.
 *
 * Fired immediately after the batch row is persisted in status 'pending',
 * before any adapter execute calls. Subscribers can use this event to
 * detect the start of a bulk operation pipeline.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\event;

/**
 * Event fired when a new batch row is created in status 'pending'.
 */
class batch_created extends \core\event\base {
    /**
     * Initialise the event metadata.
     *
     * @return void
     */
    protected function init(): void {
        $this->data['crud']        = 'c';
        $this->data['edulevel']    = self::LEVEL_OTHER;
        $this->data['objecttable'] = 'local_coursectrl_batch';
    }

    /**
     * Localised event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('event_batch_created', 'local_coursectrl');
    }

    /**
     * Human-readable description of the event.
     *
     * @return string
     */
    public function get_description(): string {
        $action = $this->other['action'] ?? 'unknown';
        return "User {$this->userid} created bulk action batch {$this->objectid}"
            . " for action '{$action}' in course {$this->courseid}.";
    }

    /**
     * URL to the affected resource.
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        return new \moodle_url('/local/coursectrl/history.php', ['courseid' => $this->courseid]);
    }
}
