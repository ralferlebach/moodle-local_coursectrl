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
 * Implements a two-phase shift dialog:
 *   Phase 1: configure shift (delta, followdeps, scan_text checkbox).
 *   Phase 2: AJAX execute shift via shift.php?format=json, then scan text
 *             hits via local_coursectrl_get_text_hits, render review panel,
 *             and apply via local_coursectrl_apply_text_changes.
 *
 * Also wires mini-calendar toggle, jump-to-day, immediate-apply pref,
 * delete dialog, and the custom component-filter dropdown.
 *
 * @module     local_coursectrl/timeline
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['local_coursectrl/shift_workflow'], function(ShiftWorkflow) {

    // Track whether a structural shift was applied so we reload on close.
    var shiftApplied = false;

    // ── Utility helpers ──────────────────────────────────────────────────────

    /**
     * Persist a boolean user preference via Moodle core_user web service.
     *
     * @param {string}  name  Preference key.
     * @param {boolean} value New value.
     */
    var persistPref = function(name, value) {
        require(['core/ajax'], function(Ajax) {
            Ajax.call([{
                methodname: 'core_user_update_user_preferences',
                args: {
                    preferences: [{type: name, value: value ? '1' : '0'}],
                },
            }]);
        });
    };


    /**
     * Collect cmids from entry buttons inside a slot card body.
     *
     * @param {HTMLElement} slotBody .card-body of a slot row.
     * @return {string[]} Array of cmid strings.
     */
    var cmidsForSlotBody = function(slotBody) {
        var ids = [];
        slotBody.querySelectorAll('[data-action="shift-entry"]').forEach(function(b) {
            ids.push(b.getAttribute('data-cmid'));
        });
        return ids;
    };

    // ── Modal helpers ────────────────────────────────────────────────────────

    /**
     * Open the shift dialog, reset to step 1, pre-fill cmids/label/title.
     *
     * @param {string[]} cmids     CM ids to shift.
     * @param {string}   mode      'slot', 'entry' or 'following'.
     * @param {string}   label     Human-readable scope description.
     * @param {boolean}  following True for shift-following action.
     * @param {string}   field     Optional single field name to restrict the shift.
     */
    var openShiftDialog = function(cmids, mode, label, following, field) {
        var dialog = document.getElementById('coursectrl-shift-dialog');
        if (!dialog) {
            return;
        }
        shiftApplied = false;
        // Reset the workflow to step 1 before opening.
        ShiftWorkflow.reset(dialog);

        document.getElementById('coursectrl-shift-cmids').value = cmids.join(',');
        document.getElementById('coursectrl-shift-mode').value = mode;

        // Set field restriction (single field for entry-level shifts, empty for slot/following).
        var fieldsInput = document.getElementById('coursectrl-shift-fields');
        if (fieldsInput) {
            fieldsInput.value = (field && mode === 'entry') ? field : '';
        }

        var daysEl = document.getElementById('coursectrl-shift-delta-days');
        var hoursEl = document.getElementById('coursectrl-shift-delta-hours');
        if (daysEl) {
            daysEl.value = '0';
        }
        if (hoursEl) {
            hoursEl.value = '0';
        }

        var targetEl = document.getElementById('coursectrl-shift-target');
        if (targetEl) {
            targetEl.textContent = label;
        }

        var titleEl = document.getElementById('coursectrl-shift-dialog-title');
        if (titleEl) {
            var attr = following ? 'data-label-following' : 'data-label-single';
            titleEl.textContent = titleEl.getAttribute(attr) || titleEl.textContent;
        }

        dialog.style.display = 'block';
        dialog.classList.add('show');
    };

    /**
     * Open the delete confirmation dialog.
     *
     * @param {string} cmid  CM id.
     * @param {string} field Field name.
     * @param {string} name  Activity name.
     */
    var openDeleteDialog = function(cmid, field, name) {
        var dialog = document.getElementById('coursectrl-delete-dialog');
        if (!dialog) {
            return;
        }
        document.getElementById('coursectrl-delete-cmids').value = cmid;
        document.getElementById('coursectrl-delete-fields').value = field;
        var targetEl = document.getElementById('coursectrl-delete-target');
        if (targetEl) {
            targetEl.textContent = name + ' \u2014 ' + field;
        }
        dialog.style.display = 'block';
        dialog.classList.add('show');
    };

    /**
     * Close all open dialogs. Reloads if a shift was applied.
     */
    var closeDialogs = function() {
        ['coursectrl-shift-dialog', 'coursectrl-delete-dialog'].forEach(function(id) {
            var d = document.getElementById(id);
            if (d) {
                d.style.display = 'none';
                d.classList.remove('show');
            }
        });
        if (shiftApplied) {
            window.location.reload();
        }
    };

    // ── Step management ──────────────────────────────────────────────────────




    // ── AJAX helpers ─────────────────────────────────────────────────────────




    // ── Review panel renderer ────────────────────────────────────────────────


    // ── Full AJAX shift flow ─────────────────────────────────────────────────


    // ── Component filter
    // ── Component filter dropdown ────────────────────────────────────────────

    /**
     * Wire up the Moodle-compatible custom component-filter dropdown.
     *
     * @param {HTMLElement} root Timeline root element.
     */
    var initCompFilterDropdown = function(root) {
        var wrapper = root.querySelector('[data-region="local_coursectrl-compfilter"]');
        if (!wrapper) {
            return;
        }
        var toggle = wrapper.querySelector('[data-action="toggle-compfilter"]');
        var menu = wrapper.querySelector('[data-region="local_coursectrl-compfilter-menu"]');
        if (!toggle || !menu) {
            return;
        }
        toggle.addEventListener('click', function(e) {
            e.stopPropagation();
            menu.classList.toggle('d-none');
        });
        document.addEventListener('click', function(e) {
            if (!wrapper.contains(e.target)) {
                menu.classList.add('d-none');
            }
        });
    };

    // ── Main entry point ─────────────────────────────────────────────────────

    /**
     * Initialise all timeline page JS enhancements.
     */
    var init = function() {
        var root = document.querySelector('[data-region="local_coursectrl-timeline"]');
        if (!root) {
            return;
        }
        var courseid = parseInt(root.getAttribute('data-courseid') || '0', 10);

        // Auto-scan on textreview tab when DB has no hits yet.
        // This ensures the first visit to the tab always shows current results.
        var trPanel = root.querySelector('[data-region="local_coursectrl-textreview-panel"]');
        if (trPanel && trPanel.getAttribute('data-hasrows') !== '1') {
            var scanBtn = trPanel.querySelector('[data-action="rescan-text"]');
            if (scanBtn) {
                scanBtn.setAttribute('data-autoscan', '1');
                scanBtn.dispatchEvent(new Event('click'));
            } else {
                // No explicit button — trigger AJAX scan and refresh the panel.
                require(['core/ajax'], function(Ajax) {
                    Ajax.call([{
                        methodname: 'local_coursectrl_get_text_hits',
                        args: {courseid: courseid, rescan: true},
                        done: function(result) {
                            if (result.hits && result.hits.length > 0) {
                                // Hits found — reload the page to show them.
                                window.location.reload();
                            }
                        },
                        fail: function() { /* Silent fail — user can manually rescan. */ }
                    }]);
                });
            }
        }

        // Open shift dialog from slot-level button.
        root.querySelectorAll('[data-action="shift-slot"]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var slotBody = btn.closest('.card-body');
                var cmids = slotBody ? cmidsForSlotBody(slotBody) : [];
                openShiftDialog(cmids, 'slot', 'Zeitfenster: ' + cmids.length + ' Eintr\u00e4ge', false);
            });
        });

        // Open shift dialog for all entries from a point onward.
        root.querySelectorAll('[data-action="shift-following"]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var ts = parseInt(btn.getAttribute('data-timestamp'), 10) || 0;
                var cmids = [];
                root.querySelectorAll('.card-body').forEach(function(slotBody) {
                    var slotBtn = slotBody.querySelector('[data-action="shift-slot"]');
                    if (!slotBtn) {
                        return;
                    }
                    var candidateTs = parseInt(slotBtn.getAttribute('data-timestamp'), 10) || 0;
                    if (candidateTs >= ts) {
                        cmids = cmids.concat(cmidsForSlotBody(slotBody));
                    }
                });
                cmids = cmids.filter(function(v, i, a) {
                    return a.indexOf(v) === i;
                });
                openShiftDialog(cmids, 'slot', 'Ab diesem Zeitpunkt: ' + cmids.length + ' Eintr\u00e4ge', true);
            });
        });

        // Open shift dialog for a single entry.
        root.querySelectorAll('[data-action="shift-entry"]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var cmid = btn.getAttribute('data-cmid');
                var field = btn.getAttribute('data-field') || '';
                var li = btn.closest('li');
                var link = li ? li.querySelector('a') : null;
                var name = link ? link.textContent.trim() : 'cmid ' + cmid;
                openShiftDialog([cmid], 'entry', name, false, field);
            });
        });

        // Wire shift workflow via shared shift_workflow module.
        var shiftModal = document.getElementById('coursectrl-shift-dialog');
        var shiftForm  = document.getElementById('coursectrl-shift-form');
        if (shiftModal && shiftForm) {
            ShiftWorkflow.run({
                modal: shiftModal,
                form: shiftForm,
                courseid: courseid,
                getCmids: function() {
                    var v = document.getElementById('coursectrl-shift-cmids');
                    return v ? v.value.split(',').filter(Boolean).map(Number) : [];
                },
                getDelta: function() {
                    var d = parseInt(
                        (document.getElementById('coursectrl-shift-delta-days') || {}).value || '0',
                        10
                    );
                    var h = parseInt(
                        (document.getElementById('coursectrl-shift-delta-hours') || {}).value || '0',
                        10
                    );
                    var m = parseInt(
                        (document.getElementById('coursectrl-shift-delta-minutes') || {}).value || '0',
                        10
                    );
                    return (d * 86400) + (h * 3600) + (m * 60);
                },
                getScanText: true,
                onComplete: function() {
                    shiftApplied = true;
                    closeDialogs();
                    location.reload();
                },
            });
        }

        // Textreview tab: "Textänderungen anwenden" → confirmation modal.
        var applyBtn = document.getElementById('coursectrl-textreview-apply-btn');
        var trModal  = document.getElementById('coursectrl-textreview-confirm-modal');
        var trForm   = document.getElementById('coursectrl-textreview-inline-form');
        if (applyBtn && trModal && trForm) {
            /**
             * Open the textreview confirmation modal.
             */
            var openTrModal = function() {
                trModal.style.display = 'flex';
                trModal.setAttribute('aria-hidden', 'false');
                trModal.classList.add('show');
                document.body.classList.add('modal-open');
                var bd = document.createElement('div');
                bd.className = 'modal-backdrop fade show';
                bd.id = 'cctr-modal-bd';
                document.body.appendChild(bd);
            };
            /**
             * Close the textreview confirmation modal.
             */
            var closeTrModal = function() {
                trModal.style.display = 'none';
                trModal.setAttribute('aria-hidden', 'true');
                trModal.classList.remove('show');
                document.body.classList.remove('modal-open');
                var bd = document.getElementById('cctr-modal-bd');
                if (bd) {
                    bd.remove();
                }
            };

            applyBtn.addEventListener('click', function() {
                var hitids = Array.from(
                    trForm.querySelectorAll('input[name="hitids[]"]:checked')
                ).map(function(cb) { return parseInt(cb.value, 10); });
                if (hitids.length === 0) {
                    return;
                }
                var dD = parseInt(
                    (trForm.querySelector('[name="delta_days"]') || {}).value || '0', 10
                );
                var dH = parseInt(
                    (trForm.querySelector('[name="delta_hours"]') || {}).value || '0', 10
                );
                var dM = parseInt(
                    (trForm.querySelector('[name="delta_minutes"]') || {}).value || '0', 10
                );
                var deltaSec = (dD * 86400) + (dH * 3600) + (dM * 60);
                var modalBody = document.getElementById('coursectrl-textreview-modal-body');
                if (modalBody) {
                    modalBody.innerHTML =
                        '<div class="text-center py-3">' +
                        '<div class="spinner-border spinner-border-sm text-primary" role="status"></div>' +
                        '</div>';
                }
                openTrModal();
                require(['core/ajax'], function(Ajax) {
                    Ajax.call([{
                        methodname: 'local_coursectrl_get_text_hits',
                        args: {courseid: courseid, rescan: false},
                        done: function(data) {
                            var selected = (data.hits || []).filter(function(h) {
                                return hitids.indexOf(h.id) !== -1;
                            });
                            if (modalBody) {
                                modalBody.innerHTML = ShiftWorkflow.renderHits(
                                    selected, deltaSec, true
                                );
                                ShiftWorkflow.wireCtx(modalBody);
                            }
                        },
                        fail: function() {
                            if (modalBody) {
                                modalBody.innerHTML =
                                    '<p class="small text-muted">' +
                                    hitids.length + ' Einträge ausgewählt.</p>';
                            }
                        },
                    }]);
                });
                var modalApplyBtn = document.getElementById('coursectrl-textreview-modal-apply');
                if (modalApplyBtn) {
                    modalApplyBtn.disabled = false;
                    var applyOnce = function() {
                        modalApplyBtn.disabled = true;
                        modalApplyBtn.removeEventListener('click', applyOnce);
                        require(['core/ajax'], function(Ajax) {
                            Ajax.call([{
                                methodname: 'local_coursectrl_apply_text_changes',
                                args: {courseid: courseid, hitids: hitids, delta: deltaSec},
                                done: function() {
                                    closeTrModal();
                                    location.reload();
                                },
                                fail: function() {
                                    modalApplyBtn.disabled = false;
                                    modalApplyBtn.addEventListener('click', applyOnce);
                                },
                            }]);
                        });
                    };
                    modalApplyBtn.addEventListener('click', applyOnce);
                }
            });
            trModal.querySelectorAll('[data-action="close-textreview-modal"]').forEach(
                function(btn) { btn.addEventListener('click', closeTrModal); }
            );
        }

        // Delete entry dialog.
        root.querySelectorAll('[data-action="delete-entry"]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                openDeleteDialog(
                    btn.getAttribute('data-cmid'),
                    btn.getAttribute('data-field'),
                    btn.getAttribute('data-name') || ''
                );
            });
        });

        // Close-dialog buttons (step 1 cancel, step 2 skip, delete cancel).
        root.querySelectorAll('[data-action="close-dialog"]').forEach(function(btn) {
            btn.addEventListener('click', closeDialogs);
        });
                // Skip button handled by shift_workflow - no wiring needed here.


        // Followdeps checkbox → hidden input.
        var followdepsCb = document.getElementById('coursectrl-shift-followdeps-cb');
        if (followdepsCb) {
            followdepsCb.addEventListener('change', function() {
                document.getElementById('coursectrl-shift-followdeps').value =
                    followdepsCb.checked ? '1' : '0';
            });
        }

        // Mini-calendar toggle with preference persistence.
        var calToggle = root.querySelector('[data-action="toggle-calendar"]');
        var calWrapper = root.querySelector('[data-region="local_coursectrl-calwrapper"]');
        if (calToggle && calWrapper) {
            calToggle.addEventListener('click', function() {
                var hidden = calWrapper.classList.contains('d-none');
                if (hidden) {
                    calWrapper.classList.remove('d-none');
                } else {
                    calWrapper.classList.add('d-none');
                }
                var labelEl = calToggle.querySelector('[data-cal-label]');
                if (labelEl) {
                    labelEl.textContent = hidden
                        ? calToggle.getAttribute('data-label-hide')
                        : calToggle.getAttribute('data-label-show');
                }
                persistPref('local_coursectrl_showcalendar', hidden ? 1 : 0);
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

        // Jump to day from mini-calendar.
        // If the target day row is absent from the DOM (showpast is off and
        // the day is in the past), activate the showpast checkbox, append a
        // focus_day hidden field to its form, and submit — the page reloads
        // with past entries visible and data-focusdaykey triggers the scroll.
        var showpastCb = root.querySelector('#coursectrl-showpast');
        root.querySelectorAll('[data-action="jump-to-day"]').forEach(function(cell) {
            cell.addEventListener('click', function() {
                var daykey = cell.getAttribute('data-daykey');
                // Check whether the day row exists in the timeline list.
                var target = document.getElementById('day-' + daykey);
                if (!target && showpastCb && !showpastCb.checked) {
                    // Day row is missing and showpast is off — the entry is
                    // filtered out. Enable showpast and reload to the target day.
                    var form = showpastCb.closest('form');
                    if (form) {
                        showpastCb.checked = true;
                        var hidden = document.createElement('input');
                        hidden.type = 'hidden';
                        hidden.name = 'focus_day';
                        hidden.value = daykey;
                        form.appendChild(hidden);
                        form.submit();
                    }
                    return;
                }
                // Day row exists — scroll to the header, highlight the card.
                if (target) {
                    target.scrollIntoView({behavior: 'smooth', block: 'start'});
                    var card1 = target.closest('.card') || target;
                    card1.classList.add('border-primary');
                    window.setTimeout(function() {
                        card1.classList.remove('border-primary');
                    }, 2000);
                }
            });
        });

        // Gantt renderer.
        var ganttRegion = root.querySelector('[data-region="local_coursectrl-gantt"]');
        if (ganttRegion) {
            require(['local_coursectrl/graphview'], function(G) {
                G.renderGantt(ganttRegion);
            });
        }

        // Custom component-filter dropdown.
        initCompFilterDropdown(root);

        // Scroll horizontal calendar to the current month on page load.
        var calWrapper = root.querySelector('[data-region="local_coursectrl-calwrapper"]');
        if (calWrapper) {
            var calRow = calWrapper.querySelector('[data-region="local_coursectrl-calrow"]');
            var currentMonth = calRow ? calRow.querySelector('.month-current') : null;
            if (currentMonth && calRow) {
                calRow.scrollLeft = currentMonth.offsetLeft
                    - (calRow.clientWidth / 2)
                    + (currentMonth.clientWidth / 2);
            }
        }

        // Scroll to a focused day when arriving from the calendar or checks page.
        // The server sets data-focusdaykey when a specific CM was requested.
        var focusday = root.getAttribute('data-focusdaykey');
        if (focusday) {
            var focustarget = document.getElementById('day-' + focusday)
                || root.querySelector('[data-daykey="' + focusday + '"]');
            if (focustarget) {
                window.setTimeout(function () {
                    focustarget.scrollIntoView({behavior: 'smooth', block: 'start'});
                    var card2 = focustarget.closest('.card') || focustarget;
                    card2.classList.add('border-primary');
                    window.setTimeout(function () {
                        card2.classList.remove('border-primary');
                    }, 2500);
                }, 200);
            }
        }
    };

    return {
        init: init,
    };
});
