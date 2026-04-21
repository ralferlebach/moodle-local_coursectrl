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
 * Handles select-all/deselect-all, per-section toggle, and wires
 * the submit button to the centralised shift_workflow modal.
 *
 * @module     local_coursectrl/manage
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['local_coursectrl/shift_workflow'], function(ShiftWorkflow) {

    /**
     * Show the manage shift modal.
     *
     * @param {HTMLElement} modalEl Modal root element.
     */
    var openModal = function(modalEl) {
        modalEl.style.display = 'flex';
        modalEl.setAttribute('aria-hidden', 'false');
        modalEl.classList.add('show');
        document.body.classList.add('modal-open');
        var backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop fade show';
        backdrop.id = 'ccmanage-backdrop';
        document.body.appendChild(backdrop);
    };

    /**
     * Close the manage shift modal.
     *
     * @param {HTMLElement} modalEl Modal root element.
     */
    var closeModal = function(modalEl) {
        modalEl.style.display = 'none';
        modalEl.setAttribute('aria-hidden', 'true');
        modalEl.classList.remove('show');
        document.body.classList.remove('modal-open');
        var bd = document.getElementById('ccmanage-backdrop');
        if (bd) {
            bd.remove();
        }
    };

    /**
     * Initialise the manage page JS enhancements.
     *
     * @param {string} courseid Course id string.
     */
    var init = function(courseid) {
        var root = document.querySelector('[data-region="local_coursectrl-manage"]');
        if (!root) {
            return;
        }

        // Select-all with date fields.
        var selectAllBtn = root.querySelector('[data-action="select-all-hasdates"]');
        if (selectAllBtn) {
            selectAllBtn.addEventListener('click', function() {
                root.querySelectorAll('input[name="cmids[]"]:not(:disabled)').forEach(function(cb) {
                    cb.checked = true;
                });
            });
        }

        // Deselect-all.
        var deselectAllBtn = root.querySelector('[data-action="deselect-all"]');
        if (deselectAllBtn) {
            deselectAllBtn.addEventListener('click', function() {
                root.querySelectorAll('input[name="cmids[]"]').forEach(function(cb) {
                    cb.checked = false;
                });
            });
        }

        // Per-section select.
        root.querySelectorAll('[data-action="select-section-dates"]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var sid = btn.getAttribute('data-sectionid');
                root.querySelectorAll(
                    'input[name="cmids[]"][data-sectionid="' + sid + '"]:not(:disabled)'
                ).forEach(function(cb) { cb.checked = true; });
            });
        });

        // Per-section deselect.
        root.querySelectorAll('[data-action="deselect-section"]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var sid = btn.getAttribute('data-sectionid');
                // Deselect all CM checkboxes in this section.
                root.querySelectorAll(
                    'input[name="cmids[]"][data-sectionid="' + sid + '"]'
                ).forEach(function(cb) { cb.checked = false; });
                // Also uncheck the section header checkbox itself.
                var sectionCb = root.querySelector(
                    'input[name="sectionids[]"][data-sectionid="' + sid + '"]'
                );
                if (sectionCb) {
                    sectionCb.checked = false;
                }
            });
        });

        // Section checkbox propagation.
        root.querySelectorAll('input[name="sectionids[]"]').forEach(function(sectionCb) {
            sectionCb.addEventListener('change', function() {
                var sid = sectionCb.getAttribute('data-sectionid');
                root.querySelectorAll(
                    'input[name="cmids[]"][data-sectionid="' + sid + '"]:not(:disabled)'
                ).forEach(function(cb) { cb.checked = sectionCb.checked; });
            });
        });

        // Submit button → open preview modal via shift_workflow.
        var submitBtn = root.querySelector('#coursectrl-preview-btn');
        var shiftForm = root.querySelector('#coursectrl-manage-form');
        var shiftModal = document.getElementById('coursectrl-manage-shift-dialog');

        if (submitBtn && shiftForm && shiftModal) {
            // Prevent default form submit and use workflow instead.
            shiftForm.addEventListener('submit', function(e) {
                e.preventDefault();
            });

            submitBtn.addEventListener('click', function(e) {
                e.preventDefault();
                var selected = Array.from(
                    root.querySelectorAll('input[name="cmids[]"]:checked')
                ).map(function(cb) { return parseInt(cb.value, 10); });

                if (selected.length === 0) {
                    return;
                }

                var daysEl  = root.querySelector('#coursectrl-delta-days');
                var hoursEl = root.querySelector('#coursectrl-delta-hours');

                openModal(shiftModal);

                ShiftWorkflow.run({
                    modal: shiftModal,
                    form: shiftForm,
                    courseid: parseInt(courseid, 10),
                    getCmids: function() {
                        return Array.from(
                            root.querySelectorAll('input[name="cmids[]"]:checked')
                        ).map(function(cb) { return parseInt(cb.value, 10); });
                    },
                    getDelta: function() {
                        var d = parseInt((daysEl && daysEl.value) || '0', 10);
                        var h = parseInt((hoursEl && hoursEl.value) || '0', 10);
                        var minutesEl = root.querySelector('#coursectrl-delta-minutes');
                        var m = parseInt((minutesEl && minutesEl.value) || '0', 10);
                        return (d * 86400) + (h * 3600) + (m * 60);
                    },
                    getScanText: true,
                    onComplete: function() {
                        closeModal(shiftModal);
                        location.reload();
                    },
                });
            });

            // Close modal on cancel buttons.
            shiftModal.querySelectorAll('[data-action="close-manage-dialog"]').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    closeModal(shiftModal);
                });
            });
        }
    };

    return {
        init: init,
    };
});
