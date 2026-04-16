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
 * AMD module for the local_coursectrl dashboard page.
 *
 * Handles the calendar block show/hide toggle with preference persistence.
 *
 * @module     local_coursectrl/dashboard
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {

    /**
     * Persist a user preference via Moodle's core_user web service.
     *
     * @param {string}  name  Preference name.
     * @param {boolean} value New value.
     */
    var persistPref = function(name, value) {
        require(['core/ajax'], function(Ajax) {
            Ajax.call([{
                methodname: 'core_user_update_user_preferences',
                args: {
                    preferences: [{
                        type: name,
                        value: value ? '1' : '0',
                    }],
                },
            }]);
        });
    };

    /**
     * Initialise the dashboard page.
     *
     * @param {HTMLElement} root The dashboard root element.
     * @return {void}
     */
    var init = function(root) {
        if (!root) {
            return;
        }

        // Calendar toggle button.
        var toggleBtns = root.querySelectorAll('[data-action="toggle-calendar"]');
        var calWrapper = root.querySelector('[data-region="local_coursectrl-calwrapper"]');

        toggleBtns.forEach(function(btn) {
            btn.addEventListener('click', function() {
                if (!calWrapper) {
                    return;
                }
                var hidden = calWrapper.classList.contains('d-none');
                if (hidden) {
                    calWrapper.classList.remove('d-none');
                } else {
                    calWrapper.classList.add('d-none');
                }
                // Update label span if present.
                var label = btn.querySelector('[data-cal-label]');
                if (label) {
                    label.setAttribute('data-state', hidden ? 'shown' : 'hidden');
                }
                persistPref('local_coursectrl_showcalendar', hidden);
            });
        });

        // Scroll calendar to current month on load.
        if (calWrapper) {
            var calRow = calWrapper.querySelector('[data-region="local_coursectrl-calrow"]');
            var mintEl = calRow ? calRow.querySelector('.bg-success') : null;
            if (mintEl && calRow) {
                calRow.scrollLeft = mintEl.offsetLeft - (calRow.clientWidth / 2) + (mintEl.clientWidth / 2);
            }
        }
    };

    return {
        init: init,
    };
});
