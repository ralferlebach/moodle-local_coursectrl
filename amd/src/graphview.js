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
                x1: fc.cx + NODE_W / 2, y1: fc.cy,
                x2: tc.cx - NODE_W / 2, y2: tc.cy,
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
            if (node.circular) {
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

            var lbl = svgEl('text', {x: pos.cx, y: pos.cy - 4,
                'text-anchor': 'middle', 'dominant-baseline': 'middle',
                fill: COL_TEXT, 'font-size': '11', 'font-family': 'sans-serif',
                'pointer-events': 'none'});
            lbl.textContent = truncate(node.label, Math.floor((NODE_W - 8) / 7));
            a.appendChild(lbl);

            var sub = svgEl('text', {x: pos.cx, y: pos.cy + 10,
                'text-anchor': 'middle', 'dominant-baseline': 'middle',
                fill: COL_SUB, 'font-size': '9', 'font-family': 'sans-serif',
                'pointer-events': 'none'});
            sub.textContent = node.modname;
            a.appendChild(sub);

            group.appendChild(a);
            svg.appendChild(group);
        });

        canvas.innerHTML = '';
        canvas.appendChild(svg);
    };

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

        var rows = data.rows;
        var mints = data.mints;
        var maxts = data.maxts;
        var span = (maxts - mints) || 1;
        var barAreaW = Math.max((canvas.offsetWidth || 400) - GANTT_LABEL_W - GANTT_PAD * 2, 200);
        var svgW = GANTT_LABEL_W + barAreaW + GANTT_PAD * 2;
        // Extra height for x-axis labels below the chart.
        var AXIS_LABEL_H = 20;
        var svgH = GANTT_PAD + rows.length * GANTT_ROW_H + GANTT_PAD + AXIS_LABEL_H;

        var svg = svgEl('svg', {width: svgW, height: svgH,
            viewBox: '0 0 ' + svgW + ' ' + svgH});

        var axisY = svgH - GANTT_PAD - AXIS_LABEL_H;
        svg.appendChild(svgEl('line', {
            x1: GANTT_LABEL_W, y1: GANTT_PAD,
            x2: GANTT_LABEL_W, y2: axisY,
            stroke: COL_AXIS, 'stroke-width': '1'}));
        svg.appendChild(svgEl('line', {
            x1: GANTT_LABEL_W, y1: axisY,
            x2: GANTT_LABEL_W + barAreaW, y2: axisY,
            stroke: COL_AXIS, 'stroke-width': '1'}));

        // X-axis tick marks and date labels.
        // Aim for ~5-8 readable ticks across the available width.
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
        // Align first tick to a round date boundary.
        var firstTick = Math.ceil(mints / tickIntervalSec) * tickIntervalSec;
        var tickTs = firstTick;
        while (tickTs <= maxts) {
            var pct = (tickTs - mints) / span;
            var tx = GANTT_LABEL_W + Math.round(pct * barAreaW);
            // Tick mark.
            svg.appendChild(svgEl('line', {
                x1: tx, y1: axisY,
                x2: tx, y2: axisY + 4,
                stroke: COL_AXIS, 'stroke-width': '1'}));
            // Date label — format as DD.MM. or DD.MM.YYYY depending on span.
            var d = new Date(tickTs * 1000);
            var labelStr = '';
            var dd = String(d.getDate()).padStart(2, '0');
            var mm = String(d.getMonth() + 1).padStart(2, '0');
            if (tickIntervalDays >= 30) {
                labelStr = mm + '/' + d.getFullYear();
            } else {
                labelStr = dd + '.' + mm + '.';
            }
            var ltext = svgEl('text', {
                x: tx, y: axisY + 14,
                'text-anchor': 'middle',
                fill: COL_SUB, 'font-size': '9', 'font-family': 'sans-serif'});
            ltext.textContent = labelStr;
            svg.appendChild(ltext);
            // Faint vertical grid line through rows.
            svg.appendChild(svgEl('line', {
                x1: tx, y1: GANTT_PAD,
                x2: tx, y2: axisY,
                stroke: '#e8e8e8', 'stroke-width': '1'}));
            tickTs += tickIntervalSec;
        }

        rows.forEach(function(row, ri) {
            var rowY = GANTT_PAD + ri * GANTT_ROW_H;
            var midY = rowY + GANTT_ROW_H / 2;

            if (ri % 2 === 0) {
                svg.appendChild(svgEl('rect', {x: 0, y: rowY,
                    width: svgW, height: GANTT_ROW_H,
                    fill: '#f8f9fa', 'fill-opacity': '0.6'}));
            }

            var maxLabelChars = Math.floor((GANTT_LABEL_W - 8) / 7);
            var lbl = svgEl('text', {x: GANTT_LABEL_W - 6, y: midY,
                'text-anchor': 'end', 'dominant-baseline': 'middle',
                fill: COL_GANTT_LBL, 'font-size': '10', 'font-family': 'sans-serif'});
            lbl.textContent = truncate(row.name, maxLabelChars);
            svg.appendChild(lbl);

            svg.appendChild(svgEl('line', {
                x1: GANTT_LABEL_W, y1: midY,
                x2: GANTT_LABEL_W + barAreaW, y2: midY,
                stroke: '#ddd', 'stroke-width': '1'}));

            row.bars.forEach(function(bar) {
                var pct = (bar.timestamp - mints) / span;
                var bx = GANTT_LABEL_W + Math.round(pct * barAreaW);

                var g = svgEl('g', {cursor: 'default'});
                var tip = svgEl('title', {});
                tip.textContent = bar.fieldlabel + ': ' + (bar.formatted || bar.timestamp);
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
        });

        canvas.innerHTML = '';
        canvas.appendChild(svg);
    };


    /**
     * Toggle visibility of independent nodes on the dependency graph.
     *
     * @param {HTMLElement} canvas The graph canvas element.
     * @param {boolean}     hide   Whether to hide independent nodes.
     */
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
            if (toggleBtn.checked) {
                applyIndependentFilter(canvas, true);
            }
            toggleBtn.addEventListener('change', function() {
                applyIndependentFilter(canvas, toggleBtn.checked);
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
