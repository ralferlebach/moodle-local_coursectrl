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
 * AMD module for the chronological timeline view.
 *
 * Wires up the per-slot and per-entry shift action buttons. The actual
 * shift dialog and backend call are placeholders at this stage; they
 * will be implemented alongside the dedicated shift backend.
 *
 * @module     local_coursectrl/timeline
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {

    /**
     * Initialise the timeline page JS enhancements.
     */
    var init = function() {
        var root = document.querySelector('[data-region="local_coursectrl-timeline"]');
        if (!root) {
            return;
        }

        // Slot-level shift buttons.
        var slotBtns = root.querySelectorAll('[data-action="shift-slot"]');
        slotBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                var ts = btn.getAttribute('data-timestamp');
                // Placeholder: real dialog comes in the next iteration.
                window.alert('Shift slot (timestamp ' + ts + ') — UI placeholder');
            });
        });

        // Entry-level shift buttons.
        var entryBtns = root.querySelectorAll('[data-action="shift-entry"]');
        entryBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                var cmid = btn.getAttribute('data-cmid');
                var field = btn.getAttribute('data-field');
                window.alert('Shift entry (cmid ' + cmid + ', field ' + field + ') — UI placeholder');
            });
        });
    };

    return {
        init: init,
    };
});
