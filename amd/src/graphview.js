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
 * AMD module for the Course Control Hub graph view.
 *
 * Renders two SVG visualisations from JSON datasets embedded as data
 * attributes by graph.mustache:
 *
 *   1. Dependency graph — layered nodes + directed edges.
 *      Circular nodes/edges drawn in red; warning nodes get amber border.
 *
 *   2. Gantt chart — one row per CM with date entries.
 *      Bars proportionally placed in the global mints…maxts window.
 *      Hover tooltip shows field label and formatted date.
 *
 * No external libraries required; all rendering uses plain SVG.
 *
 * @module     local_coursectrl/graphview
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {

    var NODE_W = 140;
    var NODE_H = 40;
    var LAYER_GAP_X = 200;
    var NODE_GAP_Y = 60;
    var PAD = 20;
    var GANTT_ROW_H = 28;
    var GANTT_LABEL_W = 180;
    var GANTT_BAR_H = 14;
    var GANTT_MARKER_R = 5;
    var GANTT_PAD = 16;

    var COL_NODE_FILL = '#eef2fb';
    var COL_NODE_STROKE = '#5b7fde';
    var COL_NODE_HIDDEN = '#e9ecef';
    var COL_NODE_HIDDEN_STROKE = '#adb5bd';
    var COL_CIRCULAR_FILL = '#fff0f0';
    var COL_CIRCULAR_STROKE = '#cc3333';
    var COL_WARN_STROKE = '#e0a800';
    var COL_EDGE = '#5b7fde';
    var COL_EDGE_CIRC = '#cc3333';
    var COL_TEXT = '#222';
    var COL_SUB = '#777';
    var COL_GANTT_BAR = '#5b7fde';
    var COL_GANTT_MARK = '#cc3333';
    var COL_GANTT_LBL = '#333';
    // Visibility / usability background bands (under the bar markers).
    // Visibility: narrow, light gray — present when activity is published.
    // Usability:  wider, slightly darker gray — span between earliest open
    // marker and latest close marker. Tooltip shows the localized window.
    var COL_GANTT_VISIBLE = '#eef0f3';
    var COL_GANTT_USABLE = '#d8dde4';
    var GANTT_VISIBLE_H = 4;
    var GANTT_USABLE_H = 10;
    // Simulation overlay colors.
    var COL_BLOCKED_FILL = '#fef2f2';
    var COL_BLOCKED_STROKE = '#dc3545';
    var COL_NEXTSTEP_FILL = '#f0fdf4';
    var COL_NEXTSTEP_STROKE = '#198754';
    var COL_AXIS = '#bbb';

    var svgEl = function(tag, attr) {
        var el = document.createElementNS('http://www.w3.org/2000/svg', tag);
        Object.keys(attr || {}).forEach(function(k) {
            el.setAttribute(k, attr[k]);
        });
        return el;
    };

    var truncate = function(str, maxChars) {
        return str.length > maxChars ? str.substring(0, maxChars - 1) + '\u2026' : str;
    };

    var nodeCenter = function(layer, layerpos) {


    return {
            cx: PAD + NODE_W / 2 + layer * LAYER_GAP_X,
            cy: PAD + NODE_H / 2 + layerpos * NODE_GAP_Y,
        };
    };

    var renderGraph = function(canvas) {
        var raw = canvas.getAttribute('data-graph');
        if (!raw) {
            return;
        }
        var data;
        try {
            data = JSON.parse(raw);
        } catch (ignore) {
            return;
        }
        if (!data.hasdata) {
            return;
        }

        var nodes = data.nodes;
        var edges = data.edges;
        var nodeIndex = {};
        nodes.forEach(function(n) { nodeIndex[n.id] = n; });
        var connected = {};
        edges.forEach(function(edge) {
            connected[edge.from] = true;
            connected[edge.to] = true;
        });

        var layerCounts = {};
        nodes.forEach(function(n) {
            layerCounts[n.layer] = (layerCounts[n.layer] || 0) + 1;
        });
        var maxPerLayer = Math.max.apply(null, Object.values(layerCounts).concat([1]));

        var svgW = PAD * 2 + data.layercount * LAYER_GAP_X;
        if (svgW < 300) {
            svgW = 300;
        }
        var svgH = PAD * 2 + maxPerLayer * NODE_GAP_Y + NODE_H;

        var svg = svgEl('svg', {width: svgW, height: svgH,
            viewBox: '0 0 ' + svgW + ' ' + svgH});

        var defs = svgEl('defs', {});
        var makeMarker = function(id, colour) {
            var m = svgEl('marker', {id: id, markerWidth: '8', markerHeight: '8',
                refX: '6', refY: '3', orient: 'auto'});
            m.appendChild(svgEl('path', {d: 'M0,0 L0,6 L8,3 z', fill: colour}));
            return m;
        };
        defs.appendChild(makeMarker('ccg-arrow', COL_EDGE));
        defs.appendChild(makeMarker('ccg-arrow-circ', COL_EDGE_CIRC));
        svg.appendChild(defs);

        edges.forEach(function(edge) {
            var fn = nodeIndex[edge.from];
            var tn = nodeIndex[edge.to];
            if (!fn || !tn) {
                return;
            }
            var fc = nodeCenter(fn.layer, fn.layerpos);
            var tc = nodeCenter(tn.layer, tn.layerpos);
            var colour = edge.circular ? COL_EDGE_CIRC : COL_EDGE;
            var marker = edge.circular ? 'url(#ccg-arrow-circ)' : 'url(#ccg-arrow)';
            svg.appendChild(svgEl('line', {
                x1: fc.cx - NODE_W / 2, y1: fc.cy,
                x2: tc.cx + NODE_W / 2, y2: tc.cy,
                stroke: colour, 'stroke-width': '1.5', 'marker-end': marker,
            }));
        });

        nodes.forEach(function(node) {
            var pos = nodeCenter(node.layer, node.layerpos);
            var rx = pos.cx - NODE_W / 2;
            var ry = pos.cy - NODE_H / 2;
            var fill = COL_NODE_FILL;
            var stroke = COL_NODE_STROKE;
            var sw = '1.5';
            if (!node.visible) {
                fill = COL_NODE_HIDDEN;
                stroke = COL_NODE_HIDDEN_STROKE;
            }
            if (node.blocked) {
                fill = COL_BLOCKED_FILL;
                stroke = COL_BLOCKED_STROKE;
                sw = '2';
            } else if (node.nextstep) {
                fill = COL_NEXTSTEP_FILL;
                stroke = COL_NEXTSTEP_STROKE;
                sw = '2';
            } else if (node.circular) {
                fill = COL_CIRCULAR_FILL;
                stroke = COL_CIRCULAR_STROKE;
                sw = '2';
            } else if (node.haswarnings) {
                stroke = COL_WARN_STROKE;
                sw = '2';
            }

            var group = svgEl('g', {
                'class': 'coursectrl-node',
                'data-independent': connected[node.id] ? '0' : '1',
                'data-hidden': node.visible ? '0' : '1',
            });
            // Independent nodes are dimmed on initial render (not only after toggle).
            if (!connected[node.id]) {
                group.style.opacity = '0.35';
            }
            var a = svgEl('a', {href: node.url, target: '_blank'});
            a.appendChild(svgEl('rect', {x: rx, y: ry, width: NODE_W, height: NODE_H,
                rx: '5', fill: fill, stroke: stroke, 'stroke-width': sw, cursor: 'pointer'}));

            var lbl = svgEl('text', {x: pos.cx, y: pos.cy,
                'text-anchor': 'middle', 'dominant-baseline': 'middle',
                fill: COL_TEXT, 'font-size': '11', 'font-family': 'sans-serif',
                'pointer-events': 'none'});
            lbl.textContent = truncate(node.label, Math.floor((NODE_W - 24) / 7));
            a.appendChild(lbl);

            // Module icon (16×16) left-aligned inside node.
            if (node.iconurl) {
                a.appendChild(svgEl('image', {
                    href: node.iconurl,
                    x: rx + 4, y: pos.cy - 8,
                    width: 16, height: 16,
                    'pointer-events': 'none',
                }));
            }

            group.appendChild(a);
            svg.appendChild(group);
        });

        canvas.innerHTML = '';
        canvas.appendChild(svg);
    };

    // Collapsed section state: sectionid (string) -> bool.
    var ganttCollapsed = {};

    var renderGantt = function(canvas) {
        var raw = canvas.getAttribute('data-gantt');
        if (!raw) {
            return;
        }
        var data;
        try {
            data = JSON.parse(raw);
        } catch (ignore) {
            return;
        }
        if (!data.hasdata) {
            return;
        }

        // Filter rows according to collapsed sections.
        var allRows = data.rows;
        // Build map: subsection child-sectionid → parent depth-0 sectionid.
        var subsecParent = {};
        var lastDepth0Id = null;
        allRows.forEach(function(row) {
            if (row.issection && row.depth === 0) {
                lastDepth0Id = String(row.sectionid);
            } else if (row.issection && row.depth === 1) {
                subsecParent[String(row.sectionid)] = lastDepth0Id;
            }
        });
        var rows = allRows.filter(function(row) {
            if (row.issection) {
                if (row.depth === 1) {
                    var p = subsecParent[String(row.sectionid)];
                    if (p && ganttCollapsed[p]) {
                        return false;
                    }
                }
                return true;
            }
            if (ganttCollapsed[String(row.sectionid)]) {
                return false;
            }
            var gp = subsecParent[String(row.sectionid)];
            return !(gp && ganttCollapsed[gp]);
        });

        var mints = data.mints;
        var maxts = data.maxts;
        var span = (maxts - mints) || 1;
        var barAreaW = Math.max((canvas.offsetWidth || 600) - GANTT_LABEL_W - GANTT_PAD * 2, 200);
        var svgW = GANTT_LABEL_W + barAreaW + GANTT_PAD * 2;
        var AXIS_LABEL_H = 20;
        var SECTION_ROW_H = 22;
        var svgH = GANTT_PAD;
        rows.forEach(function(row) {
            svgH += row.issection ? SECTION_ROW_H : GANTT_ROW_H;
        });
        svgH += GANTT_PAD + AXIS_LABEL_H;

        var svg = svgEl('svg', {width: svgW, height: svgH,
            viewBox: '0 0 ' + svgW + ' ' + svgH,
            style: 'font-family:sans-serif'});

        var axisY = svgH - GANTT_PAD - AXIS_LABEL_H;

        // Axis lines.
        svg.appendChild(svgEl('line', {
            x1: GANTT_LABEL_W, y1: GANTT_PAD,
            x2: GANTT_LABEL_W, y2: axisY,
            stroke: COL_AXIS, 'stroke-width': '1'}));
        svg.appendChild(svgEl('line', {
            x1: GANTT_LABEL_W, y1: axisY,
            x2: GANTT_LABEL_W + barAreaW, y2: axisY,
            stroke: COL_AXIS, 'stroke-width': '1'}));

        // X-axis ticks.
        var spanDays = Math.round(span / 86400);
        var tickIntervalDays = 1;
        var intervals = [1, 2, 3, 7, 14, 30, 60, 90, 182, 365];
        for (var ti = 0; ti < intervals.length; ti++) {
            if (Math.round(barAreaW / (spanDays / intervals[ti])) >= 50) {
                tickIntervalDays = intervals[ti];
                break;
            }
        }
        var tickIntervalSec = tickIntervalDays * 86400;
        var firstTick = Math.ceil(mints / tickIntervalSec) * tickIntervalSec;
        var tickTs = firstTick;
        while (tickTs <= maxts) {
            var pct = (tickTs - mints) / span;
            var tx = GANTT_LABEL_W + Math.round(pct * barAreaW);
            svg.appendChild(svgEl('line', {
                x1: tx, y1: axisY, x2: tx, y2: axisY + 4,
                stroke: COL_AXIS, 'stroke-width': '1'}));
            var d = new Date(tickTs * 1000);
            var dd = String(d.getDate()).padStart(2, '0');
            var mm = String(d.getMonth() + 1).padStart(2, '0');
            var labelStr = tickIntervalDays >= 30
                ? mm + '/' + d.getFullYear()
                : dd + '.' + mm + '.';
            var ltext = svgEl('text', {
                x: tx, y: axisY + 14,
                'text-anchor': 'middle',
                fill: COL_SUB, 'font-size': '9'});
            ltext.textContent = labelStr;
            svg.appendChild(ltext);
            svg.appendChild(svgEl('line', {
                x1: tx, y1: GANTT_PAD, x2: tx, y2: axisY,
                stroke: '#e8e8e8', 'stroke-width': '1'}));
            tickTs += tickIntervalSec;
        }

        // Build icon URL template from Moodle's global config.
        var iconBase = (typeof M !== 'undefined' && M.cfg)
            ? M.cfg.wwwroot + '/theme/image.php/' + M.cfg.theme + '/mod___MOD__/-1/monologo'
            : '';

        var rowY = GANTT_PAD;
        rows.forEach(function(row) {
            var rh = row.issection ? SECTION_ROW_H : GANTT_ROW_H;
            var midY = rowY + rh / 2;

            if (row.issection) {
                // Section header: light background stripe.
                svg.appendChild(svgEl('rect', {
                    x: 0, y: rowY, width: svgW, height: rh,
                    fill: '#e9ecef', 'fill-opacity': '0.9'}));

                // Collapse/expand triangle.
                var isCollapsed = !!ganttCollapsed[String(row.sectionid)];
                var secArrowX = (row.depth || 0) * 14 + 6;
                var arrow = svgEl('text', {
                    x: secArrowX, y: midY + 4,
                    fill: '#555', 'font-size': '10',
                    cursor: 'pointer'});
                arrow.textContent = isCollapsed ? '▶' : '▼';
                svg.appendChild(arrow);

                // Section label (bold, linked) — indented by depth.
                var secDepthIndent = (row.depth || 0) * 14;
                var arrowLblX = secDepthIndent + 20;
                var maxLbl = Math.floor((GANTT_LABEL_W - arrowLblX - 4) / 7);
                var seclbl;
                if (row.cmurl) {
                    var sa = svgEl('a', {href: row.cmurl, target: '_blank'});
                    var st = svgEl('text', {
                        x: arrowLblX, y: midY + 4,
                        fill: '#333', 'font-size': '11', 'font-weight': 'bold'});
                    st.textContent = truncate(row.name, maxLbl);
                    sa.appendChild(st);
                    seclbl = sa;
                } else {
                    seclbl = svgEl('text', {
                        x: arrowLblX, y: midY + 4,
                        fill: '#333', 'font-size': '11', 'font-weight': 'bold'});
                    seclbl.textContent = truncate(row.name, maxLbl);
                }
                svg.appendChild(seclbl);

                // Section usability window band.
                if (row.window) {
                    var swFrom = row.window.from_ts !== null
                        ? row.window.from_ts : mints;
                    var swTo = row.window.to_ts !== null
                        ? row.window.to_ts : maxts;
                    if (swTo > swFrom) {
                        var swPctFrom = (swFrom - mints) / span;
                        var swPctTo   = (swTo   - mints) / span;
                        var swx  = GANTT_LABEL_W + Math.round(swPctFrom * barAreaW);
                        var swxe = GANTT_LABEL_W + Math.round(swPctTo   * barAreaW);
                        var sww  = Math.max(2, swxe - swx);
                        var sug  = svgEl('g', {cursor: 'default'});
                        var sutip = svgEl('title', {});
                        sutip.textContent = (row.window.from_formatted || '\u2026')
                            + ' \u2013 ' + (row.window.to_formatted || '\u2026');
                        sug.appendChild(sutip);
                        sug.appendChild(svgEl('rect', {
                            x: swx, y: midY - GANTT_USABLE_H / 2,
                            width: sww, height: GANTT_USABLE_H,
                            fill: COL_GANTT_USABLE, rx: '2'}));
                        svg.appendChild(sug);
                    }
                }

                // Section availability date bars.
                if (row.bars && row.bars.length) {
                    row.bars.forEach(function(bar) {
                        var pct = (bar.timestamp - mints) / span;
                        var bx  = GANTT_LABEL_W + Math.max(2, Math.round(pct * barAreaW));
                        var bg  = svgEl('g', {cursor: 'default'});
                        var btip = svgEl('title', {});
                        var blbl = bar.humanlabel || bar.fieldlabel || bar.field;
                        btip.textContent = bar.formatted
                            ? (blbl + ': ' + bar.formatted) : blbl;
                        bg.appendChild(btip);
                        bg.appendChild(svgEl('circle', {
                            cx: bx, cy: midY, r: GANTT_MARKER_R,
                            fill: COL_GANTT_MARK, 'fill-opacity': '0.7'}));
                        svg.appendChild(bg);
                    });
                }

                // Click target for toggle (full label area).
                var clickRect = svgEl('rect', {
                    x: 0, y: rowY, width: GANTT_LABEL_W, height: rh,
                    fill: 'transparent', cursor: 'pointer'});
                clickRect.addEventListener('click', (function(sid) {
                    return function() {
                        ganttCollapsed[sid] = !ganttCollapsed[sid];
                        renderGantt(canvas);
                    };
                })(String(row.sectionid)));
                svg.appendChild(clickRect);

                rowY += rh;
                return;
            }

            // CM row: alternating background.
            var rowIdx = rows.indexOf(row);
            var cmCount = rows.slice(0, rowIdx).filter(function(r) {
                return !r.issection && r.sectionid === row.sectionid;
            }).length;
            var evenRow = (cmCount % 2 === 0);
            if (evenRow) {
                svg.appendChild(svgEl('rect', {
                    x: 0, y: rowY, width: svgW, height: rh,
                    fill: '#f8f9fa', 'fill-opacity': '0.6'}));
            }

            // Indent offset for depth 1.
            var indent = row.depth * 14;
            var iconX = indent + 2;
            var textX = iconX + (row.modname ? 16 : 2);
            var maxLabelChars = Math.floor((GANTT_LABEL_W - textX - 4) / 6.5);

            // Module icon (12×12 image).
            if (row.modname && iconBase) {
                var iconUrl = iconBase.replace('__MOD__', row.modname);
                svg.appendChild(svgEl('image', {
                    href: iconUrl,
                    x: iconX, y: midY - 6,
                    width: '12', height: '12',
                    'image-rendering': 'auto'}));
            }

            // Activity name label (linked if cmurl present).
            var nameText = svgEl('text', {
                x: textX, y: midY,
                'dominant-baseline': 'middle',
                fill: row.visible ? COL_GANTT_LBL : '#aaa',
                'font-size': '10'});
            nameText.textContent = truncate(row.name, maxLabelChars);
            if (row.cmurl) {
                var ca = svgEl('a', {href: row.cmurl, target: '_blank'});
                ca.appendChild(nameText);
                svg.appendChild(ca);
            } else {
                svg.appendChild(nameText);
            }

            // Tree guide line: vertical connector from section to CM.
            if (row.depth > 0) {
                var treeX = indent - 4;
                svg.appendChild(svgEl('line', {
                    x1: treeX, y1: rowY,
                    x2: treeX, y2: midY,
                    stroke: '#ccc', 'stroke-width': '1'}));
                svg.appendChild(svgEl('line', {
                    x1: treeX, y1: midY,
                    x2: iconX, y2: midY,
                    stroke: '#ccc', 'stroke-width': '1'}));
            }

            // Separator line.
            svg.appendChild(svgEl('line', {
                x1: GANTT_LABEL_W, y1: midY,
                x2: GANTT_LABEL_W + barAreaW, y2: midY,
                stroke: '#ddd', 'stroke-width': '1'}));

            // Visibility strip (narrow, always full-width when visible).
            if (row.visible) {
                svg.appendChild(svgEl('rect', {
                    x: GANTT_LABEL_W,
                    y: midY - GANTT_VISIBLE_H / 2,
                    width: barAreaW,
                    height: GANTT_VISIBLE_H,
                    fill: COL_GANTT_VISIBLE,
                    rx: '1'}));
            }

            // Usability window — wider bar.
            // If unlimited (no date-based restrictions), show full-width bar.
            if (row.unlimited) {
                svg.appendChild(svgEl('rect', {
                    x: GANTT_LABEL_W,
                    y: midY - GANTT_USABLE_H / 2,
                    width: barAreaW,
                    height: GANTT_USABLE_H,
                    fill: COL_GANTT_USABLE,
                    rx: '2'}));
            } else if (row.window) {
                var wFrom = row.window.from_ts !== null ? row.window.from_ts : mints;
                var wTo   = row.window.to_ts   !== null ? row.window.to_ts   : maxts;
                if (wTo > wFrom) {
                    var wPctFrom = (wFrom - mints) / span;
                    var wPctTo   = (wTo   - mints) / span;
                    var wx  = GANTT_LABEL_W + Math.round(wPctFrom * barAreaW);
                    var wxe = GANTT_LABEL_W + Math.round(wPctTo   * barAreaW);
                    var ww  = Math.max(2, wxe - wx);
                    var ug  = svgEl('g', {cursor: 'default'});
                    var utip = svgEl('title', {});
                    utip.textContent = (row.window.from_formatted || '…') +
                        ' – ' + (row.window.to_formatted || '…');
                    ug.appendChild(utip);
                    ug.appendChild(svgEl('rect', {
                        x: wx,
                        y: midY - GANTT_USABLE_H / 2,
                        width: ww,
                        height: GANTT_USABLE_H,
                        fill: COL_GANTT_USABLE,
                        rx: '2'}));
                    svg.appendChild(ug);
                }
            }

            // Date marker bars.
            row.bars.forEach(function(bar) {
                var pct = (bar.timestamp - mints) / span;
                var bx  = GANTT_LABEL_W + Math.max(2, Math.round(pct * barAreaW));
                var g   = svgEl('g', {cursor: 'default'});
                var tip = svgEl('title', {});
                var label = bar.humanlabel || bar.fieldlabel || bar.field;
                tip.textContent = bar.formatted ? (label + ': ' + bar.formatted) : label;
                g.appendChild(tip);
                if (bar.source === 'adapter') {
                    g.appendChild(svgEl('rect', {
                        x: bx - 2, y: midY - GANTT_BAR_H / 2,
                        width: 4, height: GANTT_BAR_H,
                        fill: COL_GANTT_BAR, rx: '2'}));
                } else {
                    g.appendChild(svgEl('circle', {
                        cx: bx, cy: midY, r: GANTT_MARKER_R,
                        fill: COL_GANTT_MARK, 'fill-opacity': '0.7'}));
                }
                svg.appendChild(g);
            });

            rowY += rh;
        });

        canvas.innerHTML = '';
        canvas.appendChild(svg);
    };

    var applyIndependentFilter = function(canvas, hide) {
        if (!canvas) {
            return;
        }
        canvas.querySelectorAll('.coursectrl-node').forEach(function(node) {
            var independent = node.getAttribute('data-independent') === '1';
            if (independent) {
                if (hide) {
                    node.style.display = 'none';
                    node.style.pointerEvents = 'none';
                } else {
                    node.style.display = '';
                    node.style.pointerEvents = '';
                    node.style.opacity = '0.35';
                }
            }
        });
    };

    var applyHiddenFilter = function(canvas, hide) {
        if (!canvas) {
            return;
        }
        canvas.querySelectorAll('.coursectrl-node').forEach(function(node) {
            var ishidden = node.getAttribute('data-hidden') === '1';
            if (ishidden) {
                node.style.display = hide ? 'none' : '';
            }
        });
    };

    /**
     * Wire up the independents filter toggle on the graph page.
     *
     * @param {HTMLElement} root The [data-region="local_coursectrl-graph"] element.
     */
    var initFilters = function(root) {
        var toggleBtn = root.querySelector('[data-action="toggle-independents"]');
        var canvas = root.querySelector('[data-region="coursectrl-graph-canvas"]');
        if (toggleBtn) {
            // R6: read initial hide state from data attribute (set by PHP).
            // CSS already hides them via [data-css-hide-indep]; JS removes
            // the attribute when showing so CSS rule no longer applies.
            var hideAttr = canvas ? canvas.getAttribute('data-hide-independents') : '0';
            var startHidden = hideAttr === '1';
            if (startHidden) {
                applyIndependentFilter(canvas, true);
            }
            // Checkbox checked = user wants to show independents.
            toggleBtn.addEventListener('change', function() {
                var hide = !toggleBtn.checked;
                if (canvas) {
                    // Sync CSS class so the rule matches current state.
                    if (hide) {
                        canvas.setAttribute('data-css-hide-indep', '1');
                    } else {
                        canvas.removeAttribute('data-css-hide-indep');
                    }
                }
                applyIndependentFilter(canvas, hide);
            });
        }
        var hiddenBtn = root.querySelector('[data-action="toggle-hidden"]');
        if (hiddenBtn) {
            if (hiddenBtn.checked) {
                applyHiddenFilter(canvas, true);
            }
            hiddenBtn.addEventListener('change', function() {
                applyHiddenFilter(canvas, hiddenBtn.checked);
            });
        }
    };

    return {
        /**
         * Initialise graph and Gantt visualisations inside the root element.
         *
         * @param {HTMLElement} root [data-region="local_coursectrl-graph"] element.
         */
        init: function(root) {
            if (!root) {
                return;
            }
            var graphCanvas = root.querySelector('[data-region="coursectrl-graph-canvas"]');
            var ganttCanvas = root.querySelector('[data-region="coursectrl-gantt-canvas"]');
            if (graphCanvas) {
                renderGraph(graphCanvas);
            }
            if (ganttCanvas) {
                renderGantt(ganttCanvas);
            }
            var ganttTab = root.querySelector('[href="#panel-gantt"]');
            if (ganttTab && ganttCanvas) {
                ganttTab.addEventListener('click', function() {
                    setTimeout(function() { renderGantt(ganttCanvas); }, 50);
                });
            }
            initFilters(root);
        },
        /**
         * Render a Gantt chart into the given element.
         * The element must have a data-gantt attribute with JSON.
         *
         * @param {HTMLElement} canvas Container element with data-gantt attribute.
         */
        renderGantt: renderGantt,
    };
});
