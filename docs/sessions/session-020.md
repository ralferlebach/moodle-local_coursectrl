# Session 020 — Moodle 5.3 support, green test suites, Issue #39

**Date:** 2026-10-17
**Scope:** local_coursectrl 1.2.0 → 1.2.1 (2026101700), block_coursectrldates 1.1.0 → 1.1.1 (2026101700)
**Status:** ✅ PHPUnit and Behat green on Moodle 4.5.15 and 5.3+; PHPCS 0 errors; ESLint clean

---

## Verification

| Check | Moodle 4.5 (PG 16, PHPUnit 9.6) | Moodle 5.3 (PG 17, PHPUnit 11.5) |
|---|---|---|
| PHPUnit (core + block + coursectrlmod_assign/quiz suites) | 729 tests OK | 729 tests OK, 667 PHPUnit deprecations |
| Behat (`@local_coursectrl,@block_coursectrldates`) | 77/77 scenarios, 866/866 steps | 77/77 scenarios, 866/866 steps |

Baseline before this session: PHPUnit 2 failures (both versions), Behat 26/77 passing.
The container setup is documented in the project knowledge (`environment-setup.md`).

---

## Bugs fixed (found by the tests)

1. **`shift_dates_executor::execute_action()`** — the guard returning `[]` for
   unsupported actions was lost in `b882007` (unset_dates). Restored for all
   actions other than `shift_dates`/`unset_dates`, mirroring `preview_action()`.
   The subplugin testsuites caught it; CI never ran them.
2. **`course_context_resolver::render_invalid_course_page()`** typed `$OUTPUT` as
   `core_renderer`, but before `header()` it is a `bootstrap_renderer` → TypeError
   on every invalid-course page. Now uses the globals; all six callers updated.
3. **Shift preview summary** replaced `{$changes}` while the language string uses
   `{$a}`; and `{{#str}}` without parameter strips `{$a}`. Result: the count was
   never shown. Templates now pass `{$a}` through for JS-consumed strings
   (`shift_lbl_fields_changed`, `shift_lbl_slot`, `shift_lbl_following`,
   `shift_lbl_yearassumed`, `shift_lbl_yearlabel`, …).
4. **Manage page shift dialog** carried only four label attributes, so the
   workflow ran entirely on English JS fallbacks. It now has the full set.
5. **Text review tab** delta inputs had no ids (`coursectrl-textreview-delta-*`).

block_coursectrldates (see its CHANGELOG): `block_action` used legacy
`external_api` aliases (not loaded in AJAX context) and
`allowed_classes => ['\stdClass']` (incomplete object) — "Abschalten" and "Ja"
silently failed.

---

## Issue #39 — Hard-coded language strings

- `amd/src/shift_workflow.js`: all literal UI text removed, including the
  English fallbacks in the label map. Labels are read by a new exported
  `readLabels(el)` helper from `data-lbl-*` attributes rendered via
  `get_string()`. German literals ("Datumsangaben gefunden", "Ort", "Gefunden",
  "Termin", "Textstelle zu ändern in …", "Felder einblenden",
  "Vollständiger Kontext") replaced by strings.
- `amd/src/timeline.js`, `amd/src/graphview.js`: fallbacks removed;
  "entries selected." now a string.
- `templates/timeline.mustache` (inline JS): "Einträge", "Seitenweise",
  "Alle anzeigen" via `{{#quote}}{{#str}}…{{/str}}{{/quote}}`.
- `templates/simulation.mustache` (inline JS): fallbacks removed.
- `aria-label="Close"` → `{{#str}} closebuttontitle {{/str}}` (4×).
- `templates/ajax_error.mustache`: the cited text is only the example context
  in the template doc block (not rendered) — unchanged.
- New strings (en + de): `shift_lbl_full_context`, `shift_lbl_hits_found`,
  `shift_lbl_nodates`, `shift_lbl_show_fields`, `shift_msg_nochanges`,
  `textreview_col_rewrite`, `textreview_entries_count`,
  `textreview_entries_selected`, `textreview_paginate`.

---

## Test changes

- Behat features asserted German UI text, while Behat runs in English →
  assertions switched to the English strings of the same keys.
- Checks page tabs were renamed (Problems / Solutions / Simulation) → features updated.
- Duplicate step `the completionexpected of :modtype :name …` removed (ambiguous match);
  new steps `… should still be :originalts` and
  `I should be denied access to the :page page for course :shortname`.
- Entry-shift steps used CSS `:has()` (unsupported by Mink) → XPath.
- Rollback gate now tested with an editing teacher (has view, lacks rollback);
  student access to history/checks/manage asserted as denied.
- Slot/following shift counts corrected to the fixture (Forum-C completionexpected
  shares the assign due date; "following" also covers Quiz-B).
- New `block_coursectrldates/tests/external/block_action_test.php` (5 tests).

## Compatibility

- `version.php`: `requires = 2024100700` (actual Moodle 4.5; previously 4.4's
  2024042200), `supported = [405, 503]`.
- CI: Moodle 5.3 added to PHPUnit and Behat matrices; services raised to
  MariaDB 11.4 / PostgreSQL 17 (minimums of Moodle 5.3); Behat on PHP 8.3.
  Not yet run on GitHub.

## Open

- PHPUnit 11 deprecations for `@covers` docblocks remain by design until 4.5 is
  dropped (the 4.5 phpcs sniff needs the docblocks).
- Template markup still uses Bootstrap 4 classes (`input-group-append`, `mr-1`),
  which Moodle 5.x only supports through its BS4 compatibility layer.
