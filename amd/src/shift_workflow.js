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
 * Centralised shift workflow for the Course Control Hub.
 *
 * Provides a three-step AJAX-driven workflow shared by the timeline
 * and manage pages:
 *
 *   Step 1 – Shift configuration (delta, options) — provided by caller.
 *   Step 2 – Preview: which fields will change (via preview_bulk_action).
 *   Step 3 – Execute shift (via shift.php) and optional text review.
 *
 * Callers attach a modal container element and call runWorkflow().
 *
 * @module     local_coursectrl/shift_workflow
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {

    /**
     * Escape a string for safe HTML insertion.
     *
     * @param {string} str Raw string.
     * @return {string} HTML-escaped string.
     */
    var escHtml = function(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    };

    /**
     * Collapse all expanded context snippets except the given button.
     *
     * @param {Element|null} except Button to leave expanded, or null for all.
     * @param {Element}      panel  Container element to scope the query.
     */
    var collapseCtx = function(except, panel) {
        panel.querySelectorAll('.ccwf-ctx-btn').forEach(function(btn) {
            if (btn === except) {
                return;
            }
            var lng = btn.nextElementSibling;
            var srt = btn.previousElementSibling;
            if (lng) {
                lng.classList.add('d-none');
            }
            if (srt) {
                srt.classList.remove('d-none');
            }
            var ic = btn.querySelector('i');
            if (ic) {
                ic.className = 'fa fa-info-circle';
            }
        });
    };

    /**
     * Call the preview_bulk_action external function.
     *
     * @param {number}   courseid   Course id.
     * @param {string}   action     Action identifier (e.g. shift_dates).
     * @param {object}   payload    Payload object (e.g. {delta: 86400}).
     * @param {number[]} cmids      Selected CM ids.
     * @return {Promise<object>} Preview result.
     */
    var fetchPreview = function(courseid, action, payload, cmids) {
        return new Promise(function(resolve, reject) {
            require(['core/ajax'], function(Ajax) {
                Ajax.call([{
                    methodname: 'local_coursectrl_preview_bulk_action',
                    args: {
                        courseid: courseid,
                        action: action,
                        payloadjson: JSON.stringify(payload),
                        cmids: cmids,
                    },
                    done: resolve,
                    fail: reject,
                }]);
            });
        });
    };

    /**
     * Fetch fresh text hits via the get_text_hits external function.
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
     * Apply selected text hit ids via the apply_text_changes external function.
     *
     * @param {number}   courseid Course id.
     * @param {number[]} hitids   Selected hit ids.
     * @param {number}   delta    Shift delta in seconds.
     * @return {Promise<object>} Apply result.
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

    /**
     * POST the shift form to shift.php with format=json.
     *
     * @param {HTMLFormElement} form Shift form element.
     * @param {number}          scantext 1 if text scan is requested.
     * @return {Promise<object>} Resolves with the shift result JSON.
     */
    var doShift = function(form, scantext) {
        var data = new FormData(form);
        data.set('format', 'json');
        data.set('scan_text', String(scantext));
        return fetch(form.action, {method: 'POST', body: data})
            .then(function(r) {
                if (!r.ok) {
                    throw new Error('HTTP ' + r.status);
                }
                return r.json();
            });
    };

    /**
     * Render the preview table HTML for the given preview result.
     *
     * Shows one row per activity with icon + name, change count, and an
     * expandable (i) button that reveals per-field old → new values.
     *
     * @param {object} preview Result from fetchPreview.
     * @return {string} HTML string.
     */
    /**
     * Format a Unix timestamp in the browser-locale date/time format.
     *
     * @param {number} ts Unix timestamp.
     * @return {string} Formatted string, or '–' for zero/null.
     */
    var fmtDate = function(ts) {
        if (!ts || ts <= 0) {
            return '–';
        }
        return new Date(ts * 1000).toLocaleDateString(
            navigator.language || 'de-DE',
            {day: '2-digit', month: '2-digit', year: 'numeric'}
        );
    };

    /**
     * Render the preview activity list for the given preview result.
     *
     * Uses a flex-container layout: each activity gets one row (icon + name +
     * change count + expand button). Clicking the button reveals per-field
     * details (field badge, old value → new value) inline below.
     *
     * @param {object} preview Result from fetchPreview.
     * @return {string} HTML string.
     */
    var renderPreviewHtml = function(preview) {
        var s = preview.summary;
        if (s.changes === 0) {
            return '<div class="alert alert-warning py-2 small mb-0">' +
                'Keine Datumsfelder zu verschieben. Bitte Tage oder Stunden eingeben.' +
                '</div>';
        }
        var summaryHtml =
            '<p class="small mb-2">' +
            '<strong>' + s.changes + '</strong> Felder in ' +
            '<strong>' + s.total + '</strong> Aktivität(en) werden geändert' +
            (s.skipped > 0 ? ', <span class="text-muted">' + s.skipped + ' ohne Datumsvorgaben</span>' : '') +
            (s.errors > 0 ? ', <span class="text-danger">' + s.errors + ' Fehler</span>' : '') +
            '.</p>';

        var changes = (preview.changes || []).filter(function(c) {
            return c.haschanges;
        }).slice().sort(function(a, b) {
            return (a.name || '').localeCompare(b.name || '');
        });

        var rows = changes.map(function(change, idx) {
            var fields = {};
            try { fields = JSON.parse(change.fieldsjson || '{}'); } catch (e) { fields = {}; }
            var fieldNames = Object.keys(fields);
            var detailId = 'ccwf-prev-det-' + idx;

            var iconHtml = change.iconurl
                ? '<img src="' + escHtml(change.iconurl) + '" width="16" height="16"' +
                  ' class="align-middle flex-shrink-0 me-2" alt="">'
                : '';

            // Per-field detail badges.
            var fieldBadges = fieldNames.map(function(fname) {
                var fd = fields[fname];
                var oldVal = fd.oldformatted || fmtDate(fd.old);
                var newVal = fd.newformatted || fmtDate(fd.new);
                return '<div class="d-flex align-items-center flex-wrap gap-2 py-1 border-top">' +
                    '<span class="badge bg-light text-dark border" style="font-size:.8rem">' + escHtml(fname) + '</span>' +
                    '<span class="small text-muted">' + escHtml(oldVal) + '</span>' +
                    '<span class="text-muted">→</span>' +
                    '<span class="small text-success fw-semibold">' + escHtml(newVal) + '</span>' +
                    '</div>';
            }).join('');

            return '<div class="border rounded mb-2 px-2 py-1 ccwf-activity-row">' +
                '<div class="d-flex align-items-center gap-2">' +
                iconHtml +
                '<span class="fw-semibold small flex-fill">' + escHtml(change.name) + '</span>' +
                '<span class="badge bg-light text-dark border small">' + fieldNames.length + '</span>' +
                '<button type="button" class="btn btn-link btn-sm p-0 ms-1 ccwf-preview-toggle"' +
                ' data-target="' + detailId + '" title="Felder einblenden">' +
                '<i class="fa fa-info-circle"></i></button>' +
                '</div>' +
                '<div id="' + detailId + '" class="ccwf-preview-detail d-none mt-1">' +
                fieldBadges +
                '</div>' +
                '</div>';
        }).join('');

        return summaryHtml +
            '<div style="max-height:40vh;overflow-y:auto">' + rows + '</div>';
    };

    /**
     * Render the text hits review panel HTML.
     *
     * @param {object[]} hits     Hits from fetchTextHits.
     * @param {number}   delta    Shift delta in seconds (to compute new date).
     * @param {boolean}  readOnly When true: no checkboxes, confirmation-only view.
     * @return {string} HTML string.
     */
    var renderHitsHtml = function(hits, delta, readOnly) {
        if (!hits || hits.length === 0) {
            return '<p class="text-muted small mb-0">Keine Datumsangaben in Freitexten gefunden.</p>';
        }

        var sorted = hits.slice().sort(function(a, b) {
            // No-year (ambiguous without year) sort to top for easy review.
            if (a.noyear !== b.noyear) {
                return a.noyear ? -1 : 1;
            }
            return (a.normalizedts || 0) - (b.normalizedts || 0);
        });
        var selectable = sorted.filter(function(h) {
            return h.confidence !== 'informational';
        });

        var rows = sorted.map(function(hit) {
            var ctx = {};
            try {
                ctx = hit.contextjson ? JSON.parse(hit.contextjson) : {};
            } catch (e) {
                ctx = {};
            }
            var beforeFull  = escHtml(ctx.before || '');
            var afterFull   = escHtml(ctx.after || '');
            var beforeShort = escHtml((ctx.before || '').slice(-30));
            var afterShort  = escHtml((ctx.after || '').slice(0, 30));
            var matched = escHtml(hit.matchedtext || '');
            var isSel = hit.confidence !== 'informational';
            var checked = hit.confidence === 'safe' ? ' checked' : '';
            var bc = hit.confidence === 'safe' ? 'badge-success'
                : (hit.confidence === 'ambiguous' ? 'badge-warning' : 'badge-secondary');
            var bl = hit.confidence === 'safe' ? 'Sicher'
                : (hit.confidence === 'ambiguous' ? 'Mehrdeutig' : 'Informativ');
            var rc = hit.confidence === 'informational' ? ' table-light text-muted' : '';

            var locHtml;
            if (hit.cmname && hit.cmurl) {
                var iconHtml = hit.iconurl
                    ? '<img src="' + escHtml(hit.iconurl) + '" width="16" height="16" class="mr-1" alt=""> '
                    : '';
                locHtml = iconHtml +
                    '<a href="' + escHtml(hit.cmurl) + '" target="_blank" rel="noopener noreferrer">' +
                    escHtml(hit.cmname) + '</a>';
            } else {
                locHtml = '<code>' + escHtml(hit.entitytype) + ':' + hit.entityid + '</code>';
            }
            locHtml += ' <span class="badge bg-light text-dark border ms-1 small">' +
                escHtml(hit.fieldname) + '</span>';

            // Format normalizedvalue (ISO string) as localized date for display.
            // Use date-only format when the matched text contained no time component.
            var hasTime = hit.normalizedvalue && hit.normalizedvalue.indexOf('T') !== -1
                && !/T00:00:00/.test(hit.normalizedvalue);
            var dateOpts = hasTime
                ? {day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'}
                : {day: '2-digit', month: '2-digit', year: 'numeric'};

            /**
             * Parse an ISO date/datetime string as LOCAL midnight for date-only strings.
             * JS parses 'YYYY-MM-DD' as UTC midnight (ISO 8601), but PHP strtotime
             * uses local timezone. Appending T00:00:00 forces local timezone in JS.
             *
             * @param {string} iso ISO 8601 string from server.
             * @return {Date} Date object.
             */
            var parseLocal = function(iso) {
                if (!iso) {
                    return new Date(NaN);
                }
                // Date-only: append local-midnight marker so JS uses local TZ, not UTC.
                if (/^\d{4}-\d{2}-\d{2}$/.test(iso)) {
                    return new Date(iso + 'T00:00:00');
                }
                return new Date(iso);
            };

            var normDisplay = '';
            if (hit.normalizedvalue) {
                var ndt = parseLocal(hit.normalizedvalue);
                if (!isNaN(ndt.getTime())) {
                    normDisplay = ndt.toLocaleString(navigator.language || 'de-DE', dateOpts);
                } else {
                    normDisplay = hit.normalizedvalue;
                }
            }
            // Compute replacement date: normalizedts (PHP local midnight) + delta seconds.
            // Re-parse normalizedvalue as local to avoid UTC-offset double-counting.
            var replacementDisplay = '';
            if (hit.normalizedts && hit.normalizedts > 0 && delta) {
                // Build shifted date from local-parsed normalizedvalue + delta.
                var baseDate = parseLocal(hit.normalizedvalue);
                if (!isNaN(baseDate.getTime())) {
                    var shiftedDate = new Date(baseDate.getTime() + delta * 1000);
                    replacementDisplay = shiftedDate.toLocaleString(
                        navigator.language || 'de-DE', dateOpts
                    );
                }
            }
            if (!replacementDisplay) {
                replacementDisplay = normDisplay;
            }
            // For no-year patterns: strip year from replacement to match original format.
            if (hit.noyear && replacementDisplay) {
                // Remove ", YYYY" or ".YYYY" or " YYYY" at end of date string.
                replacementDisplay = replacementDisplay
                    .replace(/[.,]\s*\d{4}$/, '')
                    .replace(/\s+\d{4}$/, '')
                    .replace(/\d{4}$/, '')
                    .trim()
                    .replace(/[,.]$/, '');
            }
            var yearNote = '';
            if (hit.noyear && hit.assumedyear) {
                yearNote = ' <span class="badge badge-warning ms-1" title="' +
                    'Das Datum im Text enth\u00e4lt keine Jahresangabe. ' +
                    'F\u00fcr die Verschiebung wird das Jahr ' + hit.assumedyear + ' angenommen.">' +
                    'Jahr fehlt – ' + hit.assumedyear + ' angenommen</span>';
            }
            var normHtml = normDisplay
                ? normDisplay + ' <span class="badge ' + bc + ' ms-1">' + bl + '</span>' + yearNote
                : '<span class="text-muted">\u2013</span>';

            var ctxHtml =
                '<span class="ccwf-ctx-short text-muted">' +
                '\u2026' + beforeShort +
                (replacementDisplay
                    ? '<span class="text-success fw-semibold">' + escHtml(replacementDisplay) + '</span>'
                    : '<strong>' + matched + '</strong>') +
                afterShort + '\u2026' +
                '</span>' +
                '<button type="button" class="btn btn-link btn-sm p-0 ms-1 ccwf-ctx-btn"' +
                ' data-before="' + beforeFull + '" data-match="' + matched + '" data-after="' + afterFull + '"' +
                ' title="Vollst\u00e4ndiger Kontext">' +
                '<i class="fa fa-info-circle"></i></button>' +
                '<span class="ccwf-ctx-long text-muted d-none">' +
                '\u2026' + beforeFull +
                (replacementDisplay
                    ? '<span class="text-success fw-semibold">' + escHtml(replacementDisplay) + '</span>'
                    : '<strong>' + matched + '</strong>') +
                afterFull + '\u2026' +
                '</span>';

            // "Gefunden" column: if match is a raw ISO datetime, show localized form.
            var matchedDisplay = (/^\d{4}-\d{2}-\d{2}T/.test(hit.matchedtext || ''))
                ? normDisplay
                : matched;
            return '<tr class="' + rc + '">' +
                (readOnly ? '' :
                    '<td style="width:34px;vertical-align:middle;text-align:center">' +
                    (isSel
                        ? '<input type="checkbox" class="ccwf-hit-cb"' +
                          ' data-hitid="' + hit.id + '" data-confidence="' + hit.confidence + '"' + checked + '>'
                        : '') +
                    '</td>') +
                '<td class="small">' + locHtml + '</td>' +
                '<td><code>' + matchedDisplay + '</code></td>' +
                '<td class="small">' + normHtml + '</td>' +
                '<td class="small">' + ctxHtml + '</td>' +
                '</tr>';
        }).join('');

        var btnAffected =
            '<button type="button" class="btn btn-sm btn-outline-primary py-0 mr-1" id="ccwf-sel-affected">' +
            'Betroffene markieren</button>';
        var btnDesel =
            '<button type="button" class="btn btn-sm btn-outline-secondary py-0" id="ccwf-desel-all">' +
            'Alle abw\u00e4hlen</button>';
        var selHtml = (!readOnly && selectable.length > 0) ? btnAffected + btnDesel : '';

        return '<div class="d-flex justify-content-between align-items-center mb-2">' +
            '<div>' +
            '<strong class="small">' + hits.length + ' Datumsangaben gefunden</strong>' +
            '<span class="text-muted small ms-2" title="Eintr\u00e4ge ohne Jahreszahl oder nicht eindeutig ' +
            'erkannte Datumsangaben k\u00f6nnen nicht automatisch verschoben werden. ' +
            'Sie werden zur manuellen Pr\u00fcfung angezeigt.">' +
            '\u24d8 Informative Eintr\u00e4ge: kein Jahrgang oder mehrdeutig</span>' +
            '</div>' +
            '<span>' + selHtml + '</span></div>' +
            '<div class="table-responsive" style="max-height:35vh;overflow-y:auto">' +
            '<table class="table table-sm table-striped mb-0 ccwf-hit-table" style="font-size:.85em">' +
            '<thead><tr>' +
            (readOnly ? '' : '<th style="width:34px"></th>') + '<th>Ort</th>' +
            '<th>Gefunden</th><th>Termin</th><th>Textstelle zu \u00e4ndern in \u2026</th>' +
            '</tr></thead>' +
            '<tbody>' + rows + '</tbody></table></div>';
    };

    /**
     * Wire preview activity-detail toggle buttons inside a preview panel.
     *
     * @param {Element} panel Container element with .ccwf-preview-toggle buttons.
     */
    var wirePreviewToggles = function(panel) {
        panel.querySelectorAll('.ccwf-preview-toggle').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var targetId = btn.getAttribute('data-target');
                var target = document.getElementById(targetId);
                if (!target) {
                    return;
                }
                var isOpen = !target.classList.contains('d-none');
                // Close all.
                panel.querySelectorAll('.ccwf-preview-detail').forEach(function(r) {
                    r.classList.add('d-none');
                });
                panel.querySelectorAll('.ccwf-preview-toggle i').forEach(function(i) {
                    i.className = 'fa fa-info-circle';
                });
                if (!isOpen) {
                    target.classList.remove('d-none');
                    btn.querySelector('i').className = 'fa fa-times-circle text-primary';
                }
            });
        });
    };

    /**
     * Wire up context-expand toggle buttons inside a panel.
     *
     * @param {Element} panel Container element.
     */
    var wireCtxToggles = function(panel) {
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.ccwf-ctx-btn')) {
                collapseCtx(null, panel);
            }
        });
        panel.querySelectorAll('.ccwf-ctx-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                var lng = btn.nextElementSibling;
                var srt = btn.previousElementSibling;
                var expanded = lng && !lng.classList.contains('d-none');
                collapseCtx(btn, panel);
                if (expanded) {
                    if (lng) {
                        lng.classList.add('d-none');
                    }
                    if (srt) {
                        srt.classList.remove('d-none');
                    }
                    btn.querySelector('i').className = 'fa fa-info-circle';
                } else {
                    if (lng) {
                        lng.classList.remove('d-none');
                    }
                    if (srt) {
                        srt.classList.add('d-none');
                    }
                    btn.querySelector('i').className = 'fa fa-times-circle text-primary';
                }
            });
        });
        var selAff = panel.querySelector('#ccwf-sel-affected');
        if (selAff) {
            selAff.addEventListener('click', function() {
                panel.querySelectorAll('.ccwf-hit-cb').forEach(function(cb) {
                    cb.checked = cb.getAttribute('data-confidence') === 'safe';
                });
            });
        }
        var desel = panel.querySelector('#ccwf-desel-all');
        if (desel) {
            desel.addEventListener('click', function() {
                panel.querySelectorAll('.ccwf-hit-cb').forEach(function(cb) {
                    cb.checked = false;
                });
            });
        }
    };

    /**
     * Run the full shift workflow inside a modal container.
     *
     * The modal is expected to have a specific structure with named sub-panels.
     * Steps:
     *   1. show step1 (config) — already visible
     *   2. fetch preview and show step2 (preview + confirm)
     *   3. execute shift
     *   4. (if scantext) show step3 (text review)
     *
     * @param {object} opts Options object.
     * @param {Element} opts.modal       The modal root element.
     * @param {HTMLFormElement} opts.form The shift config form.
     * @param {number}  opts.courseid    Course id.
     * @param {number[]} opts.getCmids   Function returning selected cmids.
     * @param {number}  opts.getDelta    Function returning delta in seconds.
     * @param {boolean} opts.getScanText Function returning whether text scan is wanted.
     * @param {Function} opts.onComplete Optional callback when workflow is done.
     */
    var runWorkflow = function(opts) {
        var modal    = opts.modal;
        var form     = opts.form;
        var courseid = opts.courseid;

        var step1 = modal.querySelector('[data-ccwf-step="1"]');
        var step2 = modal.querySelector('[data-ccwf-step="2"]');
        var step3 = modal.querySelector('[data-ccwf-step="3"]');
        var titleEl = modal.querySelector('[data-ccwf-title]');

        /**
         * Switch the visible step panel.
         *
         * @param {string} stepNum Step number ('1', '2', or '3').
         */
        var showStep = function(stepNum) {
            [step1, step2, step3].forEach(function(s) {
                if (s) {
                    s.classList.add('d-none');
                }
            });
            var target = modal.querySelector('[data-ccwf-step="' + stepNum + '"]');
            if (target) {
                target.classList.remove('d-none');
            }
        };

        /**
         * Set the modal title text.
         *
         * @param {string} text New title text.
         */
        var setTitle = function(text) {
            if (titleEl) {
                titleEl.textContent = text;
            }
        };

        // Step 1 → preview
        if (step1) {
            var previewBtn = step1.querySelector('[data-ccwf-action="preview"]');
            if (previewBtn) {
                previewBtn.addEventListener('click', function() {
                    var cmids = opts.getCmids();
                    var delta = opts.getDelta();
                    // Validate inline — never open an empty modal.
                    var errEl = step1.querySelector('[data-ccwf-step1-error]');
                    if (errEl) {
                        errEl.textContent = '';
                        errEl.classList.add('d-none');
                    }
                    if (cmids.length === 0) {
                        if (errEl) {
                            errEl.textContent = 'Bitte mindestens eine Aktivität auswählen.';
                            errEl.classList.remove('d-none');
                        }
                        return;
                    }
                    if (delta === 0) {
                        if (errEl) {
                            errEl.textContent = 'Bitte Tage oder Stunden eingeben.';
                            errEl.classList.remove('d-none');
                        }
                        return;
                    }
                    var payload = {delta: delta};
                    previewBtn.disabled = true;
                    previewBtn.textContent = '\u2026';
                    fetchPreview(courseid, 'shift_dates', payload, cmids)
                        .then(function(preview) {
                            previewBtn.disabled = false;
                            previewBtn.textContent = 'Vorschau';
                            var canExec = (preview.summary.changes || 0) > 0;
                            var html = renderPreviewHtml(preview);

                            // Scantext checkbox — only shown when changes exist.
                            var scantextRow = (opts.getScanText !== undefined && canExec)
                                ? '<div class="d-flex align-items-start gap-2 mt-3">' +
                                  '<input type="checkbox" class="form-check-input flex-shrink-0" id="ccwf-scantext-cb"' +
                                  ' style="margin:0;float:none;margin-top:0.15rem">' +
                                  '<label class="form-check-label small" for="ccwf-scantext-cb"' +
                                  ' style="margin-left:1.25rem">' +
                                  (modal.getAttribute('data-label-scantext') || 'Freitexte auf Datumsangaben prüfen') +
                                  '</label></div>'
                                : '';

                            if (step2) {
                                var previewBodyEl = step2.querySelector('[data-ccwf-preview-body]');
                                previewBodyEl.innerHTML = html + scantextRow;
                                wirePreviewToggles(previewBodyEl);
                                var execBtn = step2.querySelector('[data-ccwf-action="execute"]');
                                if (execBtn) {
                                    // Always reset disabled state when re-entering step2.
                                    execBtn.disabled = false;
                                    execBtn.style.display = canExec ? '' : 'none';
                                }
                            }
                            setTitle(modal.getAttribute('data-label-preview') || 'Vorschau');
                            showStep('2');
                        })
                        .catch(function() {
                            previewBtn.disabled = false;
                            previewBtn.textContent = 'Vorschau';
                        });
                });
            }
        }

        // Step 2 → execute
        if (step2) {
            var execBtn = step2.querySelector('[data-ccwf-action="execute"]');
            var backBtn = step2.querySelector('[data-ccwf-action="back"]');
            if (backBtn) {
                backBtn.addEventListener('click', function() {
                    setTitle(modal.getAttribute('data-label-config') || 'Termine verschieben');
                    showStep('1');
                });
            }
            if (execBtn) {
                execBtn.addEventListener('click', function() {
                    var scantextCb = step2.querySelector('#ccwf-scantext-cb');
                    var doScan = scantextCb ? (scantextCb.checked ? 1 : 0) : 0;
                    execBtn.disabled = true;

                    // Push cmids + delta into form hidden fields.
                    var cmids   = opts.getCmids();
                    var deltaS  = opts.getDelta();
                    var fCmids  = form.querySelector('[name="cmids"]') ||
                                  form.querySelector('[id$="shift-cmids"]');
                    var fDays   = form.querySelector('[name="delta_days"]');
                    var fHours  = form.querySelector('[name="delta_hours"]');
                    if (fCmids) {
                        fCmids.value = cmids.join(',');
                    }
                    var days    = Math.trunc(deltaS / 86400);
                    var hours   = Math.trunc((deltaS % 86400) / 3600);
                    var minutes = Math.trunc((deltaS % 3600) / 60);
                    if (fDays) {
                        fDays.value = days;
                    }
                    if (fHours) {
                        fHours.value = hours;
                    }
                    var fMinutes = form.querySelector('[name="delta_minutes"]');
                    if (fMinutes) {
                        fMinutes.value = minutes;
                    }

                    // Show loading state in step2 body.
                    var previewBody = step2.querySelector('[data-ccwf-preview-body]');
                    if (previewBody) {
                        previewBody.innerHTML =
                            '<div class="text-center py-3">' +
                            '<div class="spinner-border spinner-border-sm text-primary" role="status"></div>' +
                            '<p class="small text-muted mt-2 mb-0">Termine werden verschoben\u2026</p>' +
                            '</div>';
                    }

                    doShift(form, doScan)
                        .then(function(result) {
                            if (!result.success) {
                                if (previewBody) {
                                    previewBody.innerHTML =
                                        '<div class="alert alert-danger py-2 small">' +
                                        (result.error || 'Fehler') + '</div>';
                                }
                                execBtn.disabled = false;
                                return;
                            }
                            var s = result.summary;
                            var successHtml =
                                '<div class="alert alert-success py-2 mb-2 small">' +
                                '<i class="fa fa-check-circle mr-1"></i>' +
                                '<strong>' + s.success + '</strong> Termin(e) verschoben' +
                                (s.error > 0 ? ', <strong class="text-danger">' + s.error + ' Fehler</strong>' : '') +
                                '.</div>';

                            if (doScan === 0) {
                                if (previewBody) {
                                    previewBody.innerHTML = successHtml;
                                }
                                if (execBtn) {
                                    execBtn.classList.add('d-none');
                                }
                                if (backBtn) {
                                    backBtn.textContent = 'Schlie\u00dfen';
                                    backBtn.removeEventListener('click', function() {});
                                    backBtn.addEventListener('click', function() {
                                        if (opts.onComplete) {
                                            opts.onComplete(result);
                                        }
                                    });
                                }
                                return;
                            }

                            // Text scan requested — load step3.
                            if (previewBody) {
                                previewBody.innerHTML =
                                    successHtml +
                                    '<div class="text-center py-2">' +
                                    '<div class="spinner-border spinner-border-sm text-primary" role="status"></div>' +
                                    '<p class="small text-muted mt-2 mb-0">Texte werden analysiert\u2026</p>' +
                                    '</div>';
                            }
                            setTitle(modal.getAttribute('data-label-textreview') || 'Textprüfung');

                            fetchTextHits(courseid)
                                .then(function(data) {
                                    var deltaSec = opts.getDelta();
                                    var hitsHtml = renderHitsHtml(data.hits, deltaSec);
                                    if (step3) {
                                        step3.querySelector('[data-ccwf-hits-body]').innerHTML = hitsHtml;
                                        step3.querySelector('[data-ccwf-shift-result]').innerHTML = successHtml;
                                        var applyBtn3raw = step3.querySelector('[data-ccwf-action="apply-text"]');
                                        if (applyBtn3raw) {
                                            // Clone to remove any previous listeners from earlier shifts.
                                            var applyBtn3 = applyBtn3raw.cloneNode(true);
                                            applyBtn3raw.parentNode.replaceChild(applyBtn3, applyBtn3raw);
                                            var applyDelta = opts.getDelta();
                                            applyBtn3.addEventListener('click', function() {
                                                var ids = [];
                                                step3.querySelectorAll('.ccwf-hit-cb:checked').forEach(function(cb) {
                                                    ids.push(parseInt(cb.getAttribute('data-hitid'), 10));
                                                });
                                                if (ids.length === 0) {
                                                    if (opts.onComplete) {
                                                        opts.onComplete(result);
                                                    }
                                                    return;
                                                }
                                                applyBtn3.disabled = true;
                                                applyTextChanges(courseid, ids, applyDelta)
                                                    .then(function() {
                                                        if (opts.onComplete) {
                                                            opts.onComplete(result);
                                                        }
                                                    })
                                                    .catch(function() {
                                                        applyBtn3.disabled = false;
                                                    });
                                            });
                                        }
                                        wireCtxToggles(step3);
                                        showStep('3');
                                    }
                                })
                                .catch(function() {
                                    if (previewBody) {
                                        previewBody.innerHTML =
                                            successHtml +
                                            '<p class="small text-muted mt-2 mb-0">Textanalyse nicht verfügbar.</p>';
                                    }
                                    if (execBtn) {
                                        execBtn.classList.add('d-none');
                                    }
                                });
                        })
                        .catch(function(err) {
                            if (previewBody) {
                                previewBody.innerHTML =
                                    '<div class="alert alert-danger py-2 small">' +
                                    escHtml(err.message || 'Unbekannter Fehler') + '</div>';
                            }
                            execBtn.disabled = false;
                        });
                });
            }
        }
    };

    /**
     * Reset the shift modal to step 1 and clear all state.
     * Call this when reopening the modal for a new shift.
     *
     * @param {HTMLElement} modal The modal root element.
     */
    var resetWorkflow = function(modal) {
        var steps = modal.querySelectorAll('[data-ccwf-step]');
        steps.forEach(function(s) { s.classList.add('d-none'); });
        var step1 = modal.querySelector('[data-ccwf-step="1"]');
        if (step1) {
            step1.classList.remove('d-none');
        }
        var previewBody = modal.querySelector('[data-ccwf-preview-body]');
        if (previewBody) {
            previewBody.innerHTML = '';
        }
        var hitsBody = modal.querySelector('[data-ccwf-hits-body]');
        if (hitsBody) {
            hitsBody.innerHTML = '';
        }
        var shiftResult = modal.querySelector('[data-ccwf-shift-result]');
        if (shiftResult) {
            shiftResult.innerHTML = '';
        }
        var titleEl = modal.querySelector('[data-ccwf-title]');
        if (titleEl) {
            titleEl.textContent = modal.getAttribute('data-label-config') || '';
        }
        var errEl = step1 ? step1.querySelector('[data-ccwf-step1-error]') : null;
        if (errEl) {
            errEl.textContent = '';
            errEl.classList.add('d-none');
        }
    };

    return {
        /**
         * Run the shift workflow.
         *
         * @param {object} opts Workflow options (see runWorkflow docs).
         */
        run: runWorkflow,
        /**
         * Reset the modal to step 1 for reuse.
         *
         * @param {HTMLElement} modal The modal root element.
         */
        reset: resetWorkflow,
        /**
         * Render text hits HTML (can be used standalone).
         *
         * @param {object[]} hits Hit objects from get_text_hits.
         * @return {string} HTML string.
         */
        renderHits: renderHitsHtml,
        /**
         * Wire context-expand toggle buttons inside a panel.
         *
         * @param {Element} panel Container element.
         */
        wireCtx: wireCtxToggles,
    };
});
