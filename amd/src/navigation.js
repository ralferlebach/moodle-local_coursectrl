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
 * AMD module for the local_coursectrl navigation select_menu.
 *
 * Initialises the Bootstrap dropdown on the combobox button and navigates
 * to the target URL when the user selects an option. Falls back gracefully
 * if Bootstrap 5 dropdown API is unavailable (e.g. Bootstrap 4 in Moodle 4.x).
 *
 * @module     local_coursectrl/navigation
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {

    /**
     * Initialise the navigation dropdown attached to the given trigger button.
     *
     * @param {HTMLElement} btn The combobox trigger button element.
     * @return {void}
     */
    var init = function(btn) {
        if (!btn) {
            return;
        }

        var listboxId = btn.getAttribute('aria-controls');
        var listbox = document.getElementById(listboxId);
        if (!listbox) {
            return;
        }

        // Navigate when an option is clicked.
        listbox.addEventListener('click', function(e) {
            var option = e.target.closest('[role="option"]');
            if (!option) {
                return;
            }
            var url = option.getAttribute('data-value');
            if (url) {
                window.location.href = url;
            }
        });

        // Keyboard navigation within the listbox.
        btn.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                btn.click();
            }
        });

        // Update aria-expanded on dropdown show/hide (Bootstrap 4 + 5 compatible).
        var wrapper = btn.closest('[data-region="local_coursectrl-navselect"]');
        if (wrapper) {
            wrapper.addEventListener('show.bs.dropdown', function() {
                btn.setAttribute('aria-expanded', 'true');
            });
            wrapper.addEventListener('hide.bs.dropdown', function() {
                btn.setAttribute('aria-expanded', 'false');
            });
            // Bootstrap 4 events.
            wrapper.addEventListener('shown.bs.dropdown', function() {
                btn.setAttribute('aria-expanded', 'true');
            });
            wrapper.addEventListener('hidden.bs.dropdown', function() {
                btn.setAttribute('aria-expanded', 'false');
            });
        }
    };

    return {
        init: init,
    };
});
