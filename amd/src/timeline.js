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
 * AMD module for the timeline page.
 *
 * Wires up shift and delete dialogs, mini-calendar toggle,
 * jump-to-day navigation and the immediate-apply preference.
 *
 * @module     local_coursectrl/timeline
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {

    /**
     * Persist a boolean user preference via Moodle's core_user ws.
     *
     * @param {string}  name  Preference name.
     * @param {boolean} value Value to store.
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
     * Collect all cmids that have an entry at the given timestamp.
     *
     * @param {HTMLElement} root      Root element.
     * @param {string}      timestamp Slot timestamp.
     * @return {string[]} cmids
     */
    var cmidsForSlot = function(root, timestamp) {
        var btn = root.querySelector('[data-action="shift-slot"][data-timestamp="' + timestamp + '"]');
        if (!btn) {
            return [];
        }
        var slot = btn.closest('.list-group-item');
        if (!slot) {
            return [];
        }
        var ids = [];
        slot.querySelectorAll('[data-action="shift-entry"]').forEach(function(b) {
            ids.push(b.getAttribute('data-cmid'));
        });
        return ids;
    };

    /**
     * Open the shift dialog.
     *
     * @param {string[]} cmids CMIDs to shift.
     * @param {string}   mode  'slot' or 'entry'.
     * @param {string}   label Human-readable target label.
     */
    var openShiftDialog = function(cmids, mode, label) {
        var dialog = document.getElementById('coursectrl-shift-dialog');
        if (!dialog) {
            return;
        }
        document.getElementById('coursectrl-shift-cmids').value = cmids.join(',');
        document.getElementById('coursectrl-shift-mode').value = mode;
        var target = document.getElementById('coursectrl-shift-target');
        if (target) {
            target.textContent = label;
        }
        dialog.style.display = 'block';
        dialog.classList.add('show');
    };

    /**
     * Open the delete confirmation dialog.
     *
     * @param {string} cmid  CM id to clear.
     * @param {string} field Field name.
     * @param {string} name  Activity name for display.
     */
    var openDeleteDialog = function(cmid, field, name) {
        var dialog = document.getElementById('coursectrl-delete-dialog');
        if (!dialog) {
            return;
        }
        document.getElementById('coursectrl-delete-cmids').value = cmid;
        document.getElementById('coursectrl-delete-fields').value = field;
        var target = document.getElementById('coursectrl-delete-target');
        if (target) {
            target.textContent = name + ' — ' + field;
        }
        dialog.style.display = 'block';
        dialog.classList.add('show');
    };

    /**
     * Close any open dialog.
     */
    var closeDialogs = function() {
        ['coursectrl-shift-dialog', 'coursectrl-delete-dialog'].forEach(function(id) {
            var d = document.getElementById(id);
            if (d) {
                d.style.display = 'none';
                d.classList.remove('show');
            }
        });
    };

    /**
     * Initialise the timeline page JS enhancements.
     */
    var init = function() {
        var root = document.querySelector('[data-region="local_coursectrl-timeline"]');
        if (!root) {
            return;
        }

        // Slot-level shift.
        root.querySelectorAll('[data-action="shift-slot"]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var ts = btn.getAttribute('data-timestamp');
                var cmids = cmidsForSlot(root, ts);
                openShiftDialog(cmids, 'slot', 'Slot: ' + cmids.length + ' entries');
            });
        });

        // Entry-level shift.
        root.querySelectorAll('[data-action="shift-entry"]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var cmid = btn.getAttribute('data-cmid');
                var link = btn.closest('li').querySelector('a');
                var name = link ? link.textContent.trim() : 'cmid ' + cmid;
                openShiftDialog([cmid], 'entry', name);
            });
        });

        // Entry-level delete.
        root.querySelectorAll('[data-action="delete-entry"]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                openDeleteDialog(
                    btn.getAttribute('data-cmid'),
                    btn.getAttribute('data-field'),
                    btn.getAttribute('data-name') || ''
                );
            });
        });

        // Close dialogs.
        root.querySelectorAll('[data-action="close-dialog"]').forEach(function(btn) {
            btn.addEventListener('click', closeDialogs);
        });

        // Followdeps checkbox → hidden input.
        var followdepsCb = document.getElementById('coursectrl-shift-followdeps-cb');
        if (followdepsCb) {
            followdepsCb.addEventListener('change', function() {
                document.getElementById('coursectrl-shift-followdeps').value =
                    followdepsCb.checked ? '1' : '0';
            });
        }

        // Mini-calendar toggle with preference persistence.
        var toggleBtn = root.querySelector('[data-action="toggle-calendar"]');
        var body = document.getElementById('coursectrl-calendar-body');
        if (toggleBtn && body) {
            toggleBtn.addEventListener('click', function() {
                var isOpen = body.style.display !== 'none';
                body.style.display = isOpen ? 'none' : '';
                toggleBtn.textContent = isOpen ? '+' : '−';
                persistPref('local_coursectrl_showcalendar', !isOpen);
            });
        }

        // Immediate-apply preference toggle.
        var immediateCb = document.getElementById('coursectrl-immediateapply');
        if (immediateCb) {
            immediateCb.addEventListener('change', function() {
                persistPref('local_coursectrl_immediateapply', immediateCb.checked);
                root.setAttribute('data-immediateapply', immediateCb.checked ? '1' : '0');
            });
        }

        // Jump-to-day from mini-calendar cell.
        var jumpActions = ['jump-to-day', 'calday-jump'];
        jumpActions.forEach(function(action) {
            root.querySelectorAll('[data-action="' + action + '"]').forEach(function(cell) {
                cell.addEventListener('click', function() {
                    var daykey = cell.getAttribute('data-daykey');
                    var target = root.querySelector('[data-daykey="' + daykey + '"].card');
                    if (target) {
                        target.scrollIntoView({behavior: 'smooth', block: 'start'});
                        target.classList.add('border-primary');
                        window.setTimeout(function() {
                            target.classList.remove('border-primary');
                        }, 2000);
                    }
                });
            });
        });

        // Gantt: initialise SVG renderer if Gantt tab is active.
        var ganttRegion = root.querySelector('[data-region="local_coursectrl-gantt"]');
        if (ganttRegion) {
            var ganttData = ganttRegion.getAttribute('data-gantt');
            if (ganttData) {
                require(['local_coursectrl/graphview'], function(G) {
                    G.renderGantt(ganttRegion, JSON.parse(ganttData));
                });
            }
        }
    };

    return {
        init: init,
    };
});
