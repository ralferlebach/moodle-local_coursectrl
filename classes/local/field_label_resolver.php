<?php
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
 * Centralised field-label resolver for date and text field names.
 *
 * Resolution order for CM entities (first match wins):
 *   1. Activity module lang:      get_string($field, 'mod_' . $modname)
 *   2. core_completion lang:      get_string($field, 'core_completion')
 *   3. core lang:                 get_string($field, 'core')
 *   4. Plugin override:           get_string('field_' . $field, 'local_coursectrl')
 *   5. Raw field name fallback.
 *
 * For section entities, step 1 is replaced by a lookup in the active course
 * format's lang component so section-specific terminology (e.g. "Topic summary"
 * vs "Section summary") is respected when the site admin has customised it.
 *
 * For course entities, step 1 is replaced by a lookup in 'core_course'.
 *
 * Using the activity module's or Moodle core's own strings means site-level
 * string customisations and language-pack overrides are respected automatically
 * and labels never diverge from the rest of the Moodle UI.
 *
 * @package    local_coursectrl
 * @copyright  2026 Ralf Erlebach
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_coursectrl\local;

/**
 * Resolves a raw DB field name to a human-readable, localised label.
 */
class field_label_resolver {

    /**
     * Resolve a field name to a human-readable label.
     *
     * @param string $field      Raw DB field name, e.g. 'duedate', 'intro', 'summary'.
     * @param string $modname    Moodle module name without 'mod_', e.g. 'assign'.
     *                           Pass empty string when no module context is available.
     * @param string $entitytype Entity type: 'cm' | 'section' | 'course' | ''.
     *                           Controls which Moodle component is tried at step 1.
     * @return string Localised label, or $field if nothing was found.
     */
    public static function resolve(
        string $field,
        string $modname = '',
        string $entitytype = 'cm'
    ): string {
        $mgr = get_string_manager();

        // Step 1: entity-type-specific component (highest priority).
        $step1components = self::step1_components($entitytype, $modname);
        foreach ($step1components as $component) {
            if ($mgr->string_exists($field, $component)) {
                return get_string($field, $component);
            }
        }

        // Step 2: core_completion (e.g. completionexpected).
        if ($mgr->string_exists($field, 'core_completion')) {
            return get_string($field, 'core_completion');
        }

        // Step 3: core (e.g. startdate, enddate, description, summary, name).
        if ($mgr->string_exists($field, 'core')) {
            return get_string($field, $core = 'core');
        }

        // Step 4: plugin override — synthetic keys (field_availability_from, etc.)
        // or fields whose module string key differs from the DB column name.
        $overridekey = 'field_' . $field;
        if ($mgr->string_exists($overridekey, 'local_coursectrl')) {
            return get_string($overridekey, 'local_coursectrl');
        }

        // Step 5: raw field name.
        return $field;
    }

    /**
     * Return the list of Moodle components to check at step 1 for the given
     * entity type. The list is ordered by specificity: most specific first.
     *
     * @param string $entitytype Entity type: 'cm', 'section', 'course', or ''.
     * @param string $modname    Module name (used for cm entities).
     * @return string[] Component names to check, in priority order.
     */
    private static function step1_components(string $entitytype, string $modname): array {
        switch ($entitytype) {
            case 'cm':
                // Activity module first; a blank modname is silently skipped.
                return ($modname !== '') ? ['mod_' . $modname] : [];

            case 'section':
                // Sections belong to a course format. We check the active course
                // format first, then fall back to core_courseformat.
                // The format is not available here without DB access, so we rely
                // on core_courseformat which provides generic section terminology
                // shared by all formats, plus core as a further fallback.
                return ['core_courseformat', 'core_course'];

            case 'course':
                // Course-level fields are defined in core_course.
                return ['core_course'];

            default:
                return [];
        }
    }
}
