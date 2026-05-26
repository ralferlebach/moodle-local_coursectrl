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
 * AMD module for the simulation (Plausibilitätsprüfung) page.
 *
 * Handles the custom dropdowns for groups, groupings, and completions
 * without relying on Bootstrap JS.
 *
 * Also wires completion/passed/grade sync across both the upper activity-state
 * table and the lower next-steps table, keeping duplicate rows for the same
 * cmid consistent after every change.
 *
 * @module     local_coursectrl/simulation
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([], function() {

    /**
     * Wire up all custom simulation dropdowns.
     *
     * Each dropdown wrapper has data-region="local_coursectrl-simdropdown".
     * Its toggle button has data-action="toggle-simdropdown".
     * Its menu has data-region="local_coursectrl-simdropdown-menu".
     *
     * @param {HTMLElement} root Root element.
     */
    var initDropdowns = function(root) {
        var wrappers = root.querySelectorAll('[data-region="local_coursectrl-simdropdown"]');
        wrappers.forEach(function(wrapper) {
            var toggle = wrapper.querySelector('[data-action="toggle-simdropdown"]');
            var menu = wrapper.querySelector('[data-region="local_coursectrl-simdropdown-menu"]');
            if (!toggle || !menu) {
                return;
            }
            toggle.addEventListener('click', function(e) {
                e.stopPropagation();
                // Close all other open menus first.
                root.querySelectorAll(
                    '[data-region="local_coursectrl-simdropdown-menu"]:not(.d-none)'
                ).forEach(function(openMenu) {
                    if (openMenu !== menu) {
                        openMenu.classList.add('d-none');
                    }
                });
                menu.classList.toggle('d-none');
            });
        });

        // Close all menus when clicking outside any wrapper.
        document.addEventListener('click', function(e) {
            var insideAny = false;
            wrappers.forEach(function(wrapper) {
                if (wrapper.contains(e.target)) {
                    insideAny = true;
                }
            });
            if (!insideAny) {
                root.querySelectorAll(
                    '[data-region="local_coursectrl-simdropdown-menu"]'
                ).forEach(function(menu) {
                    menu.classList.add('d-none');
                });
            }
        });
    };

    /**
     * Apply normalised state to a single row's inputs.
     *
     * Does not touch disabled inputs.
     *
     * @param {HTMLElement} row The sim-row element.
     * @param {boolean} completed New completed state.
     * @param {boolean} passed New passed state.
     * @param {number|null} grade New grade value, or null to leave unchanged.
     */
    var applyStateToRow = function(row, completed, passed, grade) {
        var cbComplete = row.querySelector('[data-field="sim_complete"]');
        var cbPassed = row.querySelector('[data-field="sim_passed"]');
        var inGrade = row.querySelector('[data-field="sim_grade"]');

        if (cbComplete && !cbComplete.disabled) {
            cbComplete.checked = completed;
        }
        if (cbPassed && !cbPassed.disabled) {
            cbPassed.checked = passed;
        }
        if (inGrade && !inGrade.disabled && grade !== null) {
            inGrade.value = grade > 0 ? grade.toFixed(1) : '';
        }
    };

    /**
     * Apply the four normalization rules to a completion/passed/grade state.
     *
     * Mirrors the server-side simulation_state_normalizer rules.
     *
     * @param {string} changedField Which field triggered the change.
     * @param {boolean} completed Current completed state.
     * @param {boolean} passed Current passed state.
     * @param {number|null} grade Current grade value.
     * @param {boolean} reqpass Whether completion requires passing grade.
     * @param {number} gradepass Passing grade threshold percentage.
     * @return {{completed: boolean, passed: boolean, grade: number|null}} Normalised state.
     */
    var applyNormalizationRules = function(changedField, completed, passed, grade, reqpass, gradepass) {
        var newCompleted = completed;
        var newPassed = passed;
        var newGrade = grade;

        // Rule 1: completed + requires pass → passed + grade >= threshold.
        if (changedField === 'sim_complete' && newCompleted && reqpass && gradepass > 0) {
            newPassed = true;
            if (newGrade === null || newGrade < gradepass) {
                newGrade = gradepass;
            }
        }

        // Rule 2: passed + requires pass → completed + grade >= threshold.
        if (changedField === 'sim_passed' && newPassed) {
            if (reqpass) {
                newCompleted = true;
            }
            if (gradepass > 0 && (newGrade === null || newGrade < gradepass)) {
                newGrade = gradepass;
            }
        }

        // Rules 3+4: grade drives passed and completed.
        if (changedField === 'sim_grade' && newGrade !== null && gradepass > 0) {
            if (newGrade >= gradepass) {
                newPassed = true;
                if (reqpass) {
                    newCompleted = true;
                }
            } else {
                newPassed = false;
                if (reqpass) {
                    newCompleted = false;
                }
            }
        }

        return {completed: newCompleted, passed: newPassed, grade: newGrade};
    };

    /**
     * Read the current completion/passed/grade state from a row element.
     *
     * @param {HTMLElement} row The sim-row element.
     * @return {{completed: boolean, passed: boolean, grade: number|null}} Current state.
     */
    var readStateFromRow = function(row) {
        var cbComplete = row.querySelector('[data-field="sim_complete"]');
        var cbPassed = row.querySelector('[data-field="sim_passed"]');
        var inGrade = row.querySelector('[data-field="sim_grade"]');

        return {
            completed: cbComplete && !cbComplete.disabled ? cbComplete.checked : false,
            passed: cbPassed && !cbPassed.disabled ? cbPassed.checked : false,
            grade: inGrade && !inGrade.disabled ? parseFloat(inGrade.value || '0') : null,
        };
    };

    /**
     * Synchronise completion/passed/grade fields within and across tables for
     * all rows sharing the same cmid.
     *
     * @param {HTMLElement} changedRow The <tr> whose input changed.
     * @param {string} changedField 'sim_complete' | 'sim_passed' | 'sim_grade'.
     * @param {HTMLElement} root Simulation root element.
     */
    var syncSimulationRow = function(changedRow, changedField, root) {
        var cmid = changedRow.getAttribute('data-cmid');
        var gradepass = parseFloat(changedRow.getAttribute('data-gradepass') || '0');
        var reqpass = changedRow.getAttribute('data-completion-requires-pass') === '1';

        var current = readStateFromRow(changedRow);
        var gradeChanged = (current.grade !== null);

        var normalised = applyNormalizationRules(
            changedField,
            current.completed,
            current.passed,
            current.grade,
            reqpass,
            gradepass
        );

        // Push normalised state to all rows sharing this cmid (upper + lower table).
        var selector = '[data-region="local-coursectrl-sim-row"][data-cmid="' + cmid + '"]';
        var allrows = Array.prototype.slice.call(root.querySelectorAll(selector));
        allrows.forEach(function(row) {
            applyStateToRow(
                row,
                normalised.completed,
                normalised.passed,
                gradeChanged ? normalised.grade : null
            );
        });
    };

    /**
     * Wire completion/passed/grade change listeners for the sync logic.
     *
     * @param {HTMLElement} root Root element.
     */
    var initGradeSync = function(root) {
        root.addEventListener('change', function(e) {
            var input = e.target;
            var field = input.getAttribute('data-field');
            if (!field) {
                return;
            }
            var row = input.closest('[data-region="local-coursectrl-sim-row"]');
            if (!row) {
                return;
            }
            syncSimulationRow(row, field, root);
        });

        // Handle 'input' events for the grade number field so sync fires on each keystroke.
        root.addEventListener('input', function(e) {
            var input = e.target;
            if (input.getAttribute('data-field') !== 'sim_grade') {
                return;
            }
            var row = input.closest('[data-region="local-coursectrl-sim-row"]');
            if (!row) {
                return;
            }
            syncSimulationRow(row, 'sim_grade', root);
        });
    };

    /**
     * Initialise simulation page JS enhancements.
     *
     * @param {HTMLElement} root Root element.
     */
    var init = function(root) {
        if (!root) {
            return;
        }
        initDropdowns(root);
        initGradeSync(root);
    };

    return {
        init: init,
    };
});
