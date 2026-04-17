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
     * Initialise simulation page JS enhancements.
     *
     * @param {HTMLElement} root Root element.
     */
    var init = function(root) {
        if (!root) {
            return;
        }
        initDropdowns(root);
    };

    return {
        init: init,
    };
});
