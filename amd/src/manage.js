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
 * AMD module for the bulk-action management page.
 *
 * Handles select-all/deselect-all, per-section toggle, and
 * dynamic payload panel visibility based on the selected action.
 *
 * @module     local_coursectrl/manage
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {

    /**
     * Initialise the manage page JS enhancements.
     */
    var init = function() {
        var root = document.querySelector('[data-region="local_coursectrl-manage"]');
        if (!root) {
            return;
        }

        // Select-all supported checkboxes.
        var selectAllBtn = root.querySelector('[data-action="select-all-supported"]');
        if (selectAllBtn) {
            selectAllBtn.addEventListener('click', function() {
                var checkboxes = root.querySelectorAll('input[name="cmids[]"]:not(:disabled)');
                checkboxes.forEach(function(cb) {
                    cb.checked = true;
                });
            });
        }

        // Deselect-all checkboxes.
        var deselectAllBtn = root.querySelector('[data-action="deselect-all"]');
        if (deselectAllBtn) {
            deselectAllBtn.addEventListener('click', function() {
                var checkboxes = root.querySelectorAll('input[name="cmids[]"]');
                checkboxes.forEach(function(cb) {
                    cb.checked = false;
                });
            });
        }

        // Per-section select all.
        root.querySelectorAll('[data-action="select-section"]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var sectionId = btn.getAttribute('data-sectionid');
                var checkboxes = root.querySelectorAll(
                    'input[name="cmids[]"][data-sectionid="' + sectionId + '"]:not(:disabled)'
                );
                checkboxes.forEach(function(cb) {
                    cb.checked = true;
                });
            });
        });

        // Per-section deselect all.
        root.querySelectorAll('[data-action="deselect-section"]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var sectionId = btn.getAttribute('data-sectionid');
                var checkboxes = root.querySelectorAll(
                    'input[name="cmids[]"][data-sectionid="' + sectionId + '"]'
                );
                checkboxes.forEach(function(cb) {
                    cb.checked = false;
                });
            });
        });

        // Action-dependent payload panel visibility.
        var actionSelect = root.querySelector('#coursectrl-action');
        if (actionSelect) {
            var updatePayloadPanels = function() {
                var selected = actionSelect.value;
                var panels = root.querySelectorAll('[data-payload-for]');
                panels.forEach(function(panel) {
                    if (panel.getAttribute('data-payload-for') === selected) {
                        panel.style.display = '';
                    } else {
                        panel.style.display = 'none';
                    }
                });
            };
            actionSelect.addEventListener('change', updatePayloadPanels);
            // Run on init to set correct state.
            updatePayloadPanels();
        }

        // Form validation: require at least one CM selected.
        var form = root.querySelector('#coursectrl-manage-form');
        if (form) {
            form.addEventListener('submit', function(e) {
                var checked = root.querySelectorAll('input[name="cmids[]"]:checked');
                if (checked.length === 0) {
                    e.preventDefault();
                    // Show a notification using Moodle's notification system if available.
                    require(['core/notification'], function(Notification) {
                        Notification.addNotification({
                            message: M.util.get_string(
                                'manage_no_selection',
                                'local_coursectrl'
                            ),
                            type: 'warning',
                        });
                    });
                    return false;
                }
                return true;
            });
        }
    };

    return {
        init: init,
    };
});
