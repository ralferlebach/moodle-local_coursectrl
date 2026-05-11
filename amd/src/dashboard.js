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
                    label.textContent = hidden
                        ? btn.getAttribute('data-label-hide')
                        : btn.getAttribute('data-label-show');
                }
                persistPref('local_coursectrl_showcalendar', hidden ? 1 : 0);
            });
        });

        // Scroll calendar to current month on load.
        if (calWrapper) {
            var calRow = calWrapper.querySelector('[data-region="local_coursectrl-calrow"]');
            var mintEl = calRow ? calRow.querySelector('.month-current') : null;
            if (mintEl && calRow) {
                calRow.scrollLeft = mintEl.offsetLeft - (calRow.clientWidth / 2) + (mintEl.clientWidth / 2);
            }
        }
    };

    /**
     * Trigger a text scan when the dashboard loads and no scan has been done yet.
     *
     * Reads data-texthitsscanned from the dashboard root element. When the value
     * is '0', fires a background rescan via get_text_hits so subsequent page
     * loads show up-to-date results without requiring the user to visit the
     * Textprüfung tab first.
     */
    var autoscanIfNeeded = function() {
        var root = document.querySelector('[data-region="local_coursectrl-dashboard"]');
        if (!root || root.getAttribute('data-texthitsscanned') !== '0') {
            return;
        }
        var courseid = parseInt(root.getAttribute('data-courseid') || '0', 10);
        if (courseid <= 0) {
            return;
        }
        require(['core/ajax'], function(Ajax) {
            Ajax.call([{
                methodname: 'local_coursectrl_get_text_hits',
                args: {courseid: courseid, rescan: true},
                done: function(result) {
                    if (result.hits && result.hits.length > 0) {
                        window.location.reload();
                    }
                },
                fail: function() {
                    // Silent fail — user can trigger scan via Textprüfung tab.
                },
            }]);
        });
    };


    autoscanIfNeeded();

    return {
        init: init,
    };
});
