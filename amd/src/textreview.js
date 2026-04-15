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
 * AMD module for the text-datetime review page.
 *
 * Handles select-all-safe and deselect-all buttons for hit checkboxes.
 *
 * @module     local_coursectrl/textreview
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {

    /**
     * Initialise the textreview page JS enhancements.
     */
    var init = function() {
        var root = document.querySelector('[data-region="local_coursectrl-textreview"]');
        if (!root) {
            return;
        }

        // Select all safe hits.
        var selectSafeBtn = root.querySelector('[data-action="select-all-safe"]');
        if (selectSafeBtn) {
            selectSafeBtn.addEventListener('click', function() {
                var checkboxes = root.querySelectorAll('input[name="hitids[]"]');
                checkboxes.forEach(function(cb) {
                    if (cb.getAttribute('data-confidence') === 'safe') {
                        cb.checked = true;
                    }
                });
            });
        }

        // Deselect all hits.
        var deselectAllBtn = root.querySelector('[data-action="deselect-all-hits"]');
        if (deselectAllBtn) {
            deselectAllBtn.addEventListener('click', function() {
                var checkboxes = root.querySelectorAll('input[name="hitids[]"]');
                checkboxes.forEach(function(cb) {
                    cb.checked = false;
                });
            });
        }
    };

    return {
        init: init,
    };
});
