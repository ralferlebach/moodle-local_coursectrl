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

define([], function() {

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
     * Minimally escape a string for safe innerHTML injection.
     *
     * @param {string} s Raw string.
     * @return {string} HTML-escaped string.
     */
    var escHtml = function(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
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
        showStep(1);

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

    /**
     * Toggle between shift modal step 1 (config) and step 2 (AJAX review).
     *
     * @param {number} step 1 or 2.
     */
    var showStep = function(step) {
        var s1 = document.getElementById('coursectrl-shift-step1');
        var s2 = document.getElementById('coursectrl-shift-step2');
        var inner = document.getElementById('coursectrl-shift-dialog-inner');
        if (step === 1) {
            if (s1) {
                s1.classList.remove('d-none');
            }
            if (s2) {
                s2.classList.add('d-none');
            }
            if (inner) {
                inner.classList.remove('modal-lg');
            }
        } else {
            if (s1) {
                s1.classList.add('d-none');
            }
            if (s2) {
                s2.classList.remove('d-none');
            }
            if (inner) {
                inner.classList.add('modal-lg');
            }
            // Update title to "Textprüfung".
            var titleEl = document.getElementById('coursectrl-shift-dialog-title');
            if (titleEl) {
                titleEl.textContent = titleEl.getAttribute('data-label-review') || 'Textpr\u00fcfung';
            }
            // Show loading state.
            var loading = document.getElementById('coursectrl-shift-loading');
            var review = document.getElementById('coursectrl-shift-review');
            var footer = document.getElementById('coursectrl-shift-step2-footer');
            if (loading) {
                loading.classList.remove('d-none');
            }
            if (review) {
                review.classList.add('d-none');
                review.innerHTML = '';
            }
            if (footer) {
                footer.classList.add('d-none');
            }
        }
    };

    /**
     * Update the loading status message in step 2.
     *
     * @param {string} msg Status text.
     */
    var setStatus = function(msg) {
        var el = document.getElementById('coursectrl-shift-statusmsg');
        if (el) {
            el.textContent = msg;
        }
    };

    /**
     * Display an error in step 2 with a close button.
     *
     * @param {string} msg Error message.
     */
    var showError = function(msg) {
        var loading = document.getElementById('coursectrl-shift-loading');
        if (!loading) {
            return;
        }
        loading.innerHTML =
            '<i class="fa fa-exclamation-circle text-danger fa-lg"></i>' +
            '<p class="mt-2 small text-danger mb-2">' + escHtml(msg) + '</p>' +
            '<button type="button" class="btn btn-sm btn-secondary" id="ccshift-err-close">Schlie\u00dfen</button>';
        var btn = document.getElementById('ccshift-err-close');
        if (btn) {
            btn.addEventListener('click', closeDialogs);
        }
    };

    // ── AJAX helpers ─────────────────────────────────────────────────────────

    /**
     * POST the shift form to shift.php with format=json.
     *
     * @param {HTMLFormElement} form Shift form element.
     * @return {Promise<object>} Resolves {success, batchid, summary}.
     */
    var fetchShift = function(form) {
        var data = new FormData(form);
        data.set('format', 'json');
        data.set('scan_text', '0');
        return fetch(form.action, {method: 'POST', body: data})
            .then(function(r) {
                if (!r.ok) {
                    throw new Error('HTTP ' + r.status);
                }
                return r.json();
            });
    };

    /**
     * Fetch fresh text hits via get_text_hits external (triggers rescan).
     *
     * @param {number} courseid Course id.
     * @return {Promise<object>} Resolves {hits, summary}.
     */
    var fetchTextHits = function(courseid) {
        return new Promise(function(resolve, reject) {
            require(['core/ajax'], function(Ajax) {
                Ajax.call([{
                    methodname: 'local_coursectrl_get_text_hits',
                    args: {courseid: courseid, rescan: true},
                    done: resolve,
                    fail: reject,
                }]);
            });
        });
    };

    /**
     * Apply selected text-datetime hits via apply_text_changes external.
     *
     * @param {number}   courseid Course id.
     * @param {number[]} hitids   Selected hit ids.
     * @param {number}   delta    Seconds to shift.
     * @return {Promise<object>} Resolves {applied, skipped, errors}.
     */
    var applyTextChanges = function(courseid, hitids, delta) {
        return new Promise(function(resolve, reject) {
            require(['core/ajax'], function(Ajax) {
                Ajax.call([{
                    methodname: 'local_coursectrl_apply_text_changes',
                    args: {courseid: courseid, hitids: hitids, delta: delta},
                    done: resolve,
                    fail: reject,
                }]);
            });
        });
    };

    // ── Review panel renderer ────────────────────────────────────────────────

    /**
     * Populate the review panel with shift result and text hits.
     *
     * @param {object}   shiftResult From fetchShift.
     * @param {object[]} hits        From fetchTextHits.
     * @param {number}   deltasec    Shift delta in seconds.
     * @param {number}   courseid    Course id.
     */
    var renderReviewPanel = function(shiftResult, hits, deltasec, courseid) {
        var loading = document.getElementById('coursectrl-shift-loading');
        var review = document.getElementById('coursectrl-shift-review');
        var footer = document.getElementById('coursectrl-shift-step2-footer');
        var applyBtn = document.getElementById('coursectrl-shift-apply-text');

        if (loading) {
            loading.classList.add('d-none');
        }
        if (!review) {
            return;
        }

        var s = shiftResult.summary;
        var conflicts = shiftResult.conflicts || [];
        var conflictHtml = '';
        if (conflicts.length > 0) {
            var conflictLines = conflicts.map(function(c) {
                return '<li>' + c.field_early + ' liegt nach ' + c.field_late + '</li>';
            }).join('');
            conflictHtml =
                '<div class="alert alert-warning py-2 mb-2 small">' +
                '<i class="fa fa-exclamation-triangle mr-1"></i>' +
                '<strong>Datumskonflikte erkannt:</strong><ul class="mb-0 mt-1">' +
                conflictLines + '</ul></div>';
        }
        var summaryHtml =
            '<div class="alert alert-success py-2 mb-2 small">' +
            '<i class="fa fa-check-circle mr-1"></i>' +
            '<strong>' + s.success + '</strong> Termin(e) verschoben' +
            (s.error > 0 ? ', <strong class="text-danger">' + s.error + '</strong> Fehler' : '') +
            (s.skipped > 0 ? ', ' + s.skipped + ' \u00fcbersprungen' : '') +
            '.</div>' + conflictHtml;

        if (!hits || hits.length === 0) {
            review.innerHTML = summaryHtml +
                '<p class="text-muted small mb-0">Keine Datumsangaben in Freitexten gefunden.</p>';
            review.classList.remove('d-none');
            if (footer) {
                footer.classList.remove('d-none');
            }
            return;
        }

        var selectable = hits.filter(function(h) {
            return h.confidence !== 'informational';
        });

        var rows = hits.map(function(hit) {
            var ctx = {};
            try {
                ctx = hit.contextjson ? JSON.parse(hit.contextjson) : {};
            } catch (ignore) {
                ctx = {};
            }
            var before = escHtml(ctx.before || '');
            var after = escHtml(ctx.after || '');
            var matched = escHtml(hit.matchedtext || '');
            var isSel = hit.confidence !== 'informational';
            var checked = hit.confidence === 'safe' ? ' checked' : '';
            var bc = hit.confidence === 'safe' ? 'badge-success'
                : (hit.confidence === 'ambiguous' ? 'badge-warning' : 'badge-secondary');
            var bl = hit.confidence === 'safe' ? 'Sicher'
                : (hit.confidence === 'ambiguous' ? 'Mehrdeutig' : 'Informativ');
            var rc = hit.confidence === 'informational' ? ' table-light text-muted' : '';
            return '<tr class="' + rc + '">' +
                '<td class="px-2">' +
                (isSel ? '<input type="checkbox" class="form-check-input coursectrl-hit-cb"' +
                    ' data-hitid="' + hit.id + '" data-confidence="' + hit.confidence + '"' + checked + '>' : '') +
                '</td>' +
                '<td class="small"><code>' + escHtml(hit.entitytype) + ':' + hit.entityid + '</code>' +
                ' <span class="text-muted">' + escHtml(hit.fieldname) + '</span></td>' +
                '<td><code>' + matched + '</code></td>' +
                '<td class="small">\u2026' + before + '<strong>' + matched + '</strong>' + after + '\u2026</td>' +
                '<td><span class="badge ' + bc + '">' + bl + '</span></td>' +
                '</tr>';
        }).join('');

        var selBtnSafe = '<button type="button"' +
            ' class="btn btn-sm btn-outline-primary py-0 mr-1"' +
            ' id="ccshift-sel-safe">Alle sicheren w\u00e4hlen</button>';
        var selBtnDesel = '<button type="button"' +
            ' class="btn btn-sm btn-outline-secondary py-0"' +
            ' id="ccshift-desel-all">Alle abw\u00e4hlen</button>';
        var selHtml = selectable.length > 0
            ? selBtnSafe + selBtnDesel
            : '';

        review.innerHTML = summaryHtml +
            '<div class="d-flex justify-content-between align-items-center mb-2">' +
            '<strong class="small">' + hits.length + ' Datumsangaben gefunden</strong>' +
            '<span>' + selHtml + '</span></div>' +
            '<div class="table-responsive">' +
            '<table class="table table-sm table-striped mb-0" style="font-size:.85em">' +
            '<thead><tr><th style="width:34px"></th><th>Ort</th>' +
            '<th>Text</th><th>Kontext</th><th>Konfidenz</th></tr></thead>' +
            '<tbody>' + rows + '</tbody></table></div>';
        review.classList.remove('d-none');

        var selSafe = document.getElementById('ccshift-sel-safe');
        var deselAll = document.getElementById('ccshift-desel-all');
        if (selSafe) {
            selSafe.addEventListener('click', function() {
                review.querySelectorAll('.coursectrl-hit-cb').forEach(function(cb) {
                    cb.checked = cb.getAttribute('data-confidence') === 'safe';
                });
            });
        }
        if (deselAll) {
            deselAll.addEventListener('click', function() {
                review.querySelectorAll('.coursectrl-hit-cb').forEach(function(cb) {
                    cb.checked = false;
                });
            });
        }

        if (footer) {
            footer.classList.remove('d-none');
        }
        if (applyBtn && selectable.length > 0) {
            applyBtn.classList.remove('d-none');
            applyBtn.onclick = function() {
                var ids = [];
                review.querySelectorAll('.coursectrl-hit-cb:checked').forEach(function(cb) {
                    ids.push(parseInt(cb.getAttribute('data-hitid'), 10));
                });
                if (ids.length === 0) {
                    closeDialogs();
                    return;
                }
                applyBtn.disabled = true;
                var spinnerDiv = '<div class="spinner-border spinner-border-sm' +
                    ' text-primary" role="status"></div>';
                var statusP = '<p class="mt-2 small text-muted mb-0"' +
                    ' id="coursectrl-shift-statusmsg">' +
                    'Text\u00e4nderungen werden angewendet\u2026</p>';
                loading.innerHTML = spinnerDiv + statusP;
                loading.classList.remove('d-none');
                review.classList.add('d-none');
                footer.classList.add('d-none');

                applyTextChanges(courseid, ids, deltasec)
                    .then(function(result) {
                        loading.classList.add('d-none');
                        review.innerHTML =
                            '<div class="alert alert-success py-2 small mb-0">' +
                            '<i class="fa fa-check-circle mr-1"></i>' +
                            '<strong>' + result.applied + '</strong> Textänderung(en) angewendet' +
                            (result.skipped > 0 ? ', ' + result.skipped + ' \u00fcbersprungen' : '') + '.' +
                            '</div>';
                        review.classList.remove('d-none');
                        if (footer) {
                            footer.classList.remove('d-none');
                        }
                        if (applyBtn) {
                            applyBtn.classList.add('d-none');
                        }
                    })
                    .catch(function(err) {
                        showError('Fehler: ' + (err.message || JSON.stringify(err)));
                    });
            };
        }
    };

    // ── Full AJAX shift flow ─────────────────────────────────────────────────

    /**
     * Orchestrate the two-phase AJAX shift+textreview flow.
     *
     * @param {HTMLFormElement} form     Shift form.
     * @param {number}          courseid Course id.
     */
    var runAjaxShiftFlow = function(form, courseid) {
        var daysEl = document.getElementById('coursectrl-shift-delta-days');
        var hoursEl = document.getElementById('coursectrl-shift-delta-hours');
        var deltadays = parseInt((daysEl && daysEl.value) || '0', 10);
        var deltahours = parseInt((hoursEl && hoursEl.value) || '0', 10);
        var deltasec = (deltadays * 86400) + (deltahours * 3600);

        showStep(2);
        setStatus('Termine werden verschoben\u2026');

        fetchShift(form)
            .then(function(result) {
                if (!result.success && result.error === 'nothing_to_do') {
                    showError('Kein gültiger Delta-Wert \u2014 bitte Tage oder Stunden eingeben.');
                    return null;
                }
                shiftApplied = true;
                setStatus('Texte werden analysiert\u2026');
                return fetchTextHits(courseid)
                    .then(function(data) {
                        renderReviewPanel(result, data.hits, deltasec, courseid);
                    })
                    .catch(function() {
                        // Scan failed — show shift result only.
                        var loading = document.getElementById('coursectrl-shift-loading');
                        var footer = document.getElementById('coursectrl-shift-step2-footer');
                        if (loading) {
                            loading.innerHTML =
                                '<div class="alert alert-success py-2 small mb-0">' +
                                '<i class="fa fa-check-circle mr-1"></i>' +
                                result.summary.success + ' Termin(e) verschoben.' +
                                '</div><p class="small text-muted mt-2 mb-0">Textanalyse nicht verfügbar.</p>';
                        }
                        if (footer) {
                            footer.classList.remove('d-none');
                        }
                    });
            })
            .catch(function(err) {
                showError('Verbindungsfehler: ' + (err.message || err));
            });
    };

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

        // Intercept shift form: AJAX path when scan_text is enabled.
        var shiftForm = document.getElementById('coursectrl-shift-form');
        var scantextCb = document.getElementById('coursectrl-shift-scantext-cb');
        var scantextInput = document.getElementById('coursectrl-shift-scantext');

        if (shiftForm) {
            if (scantextCb && scantextInput) {
                scantextInput.value = scantextCb.checked ? '1' : '0';
                scantextCb.addEventListener('change', function() {
                    scantextInput.value = scantextCb.checked ? '1' : '0';
                });
            }
            shiftForm.addEventListener('submit', function(e) {
                if (scantextCb && scantextCb.checked) {
                    e.preventDefault();
                    runAjaxShiftFlow(shiftForm, courseid);
                }
                // scan_text unchecked: normal form submit to shift.php.
            });
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
        var skipBtn = document.getElementById('coursectrl-shift-skip-text');
        if (skipBtn) {
            skipBtn.addEventListener('click', closeDialogs);
        }

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
        root.querySelectorAll('[data-action="jump-to-day"]').forEach(function(cell) {
            cell.addEventListener('click', function() {
                var daykey = cell.getAttribute('data-daykey');
                var target = document.getElementById('day-' + daykey)
                    || root.querySelector('[data-daykey="' + daykey + '"]');
                if (target) {
                    target.scrollIntoView({behavior: 'smooth', block: 'start'});
                    target.classList.add('border-primary');
                    window.setTimeout(function() {
                        target.classList.remove('border-primary');
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
    };

    return {
        init: init,
    };
});
