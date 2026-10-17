# Session 021 — Bug issues, CI subplugin suites, Bootstrap 4/5 compatibility

**Date:** 2026-10-17
**Scope:** local_coursectrl 1.2.1 (2026101700 → 2026101701), block_coursectrldates 1.1.1 (2026101700 → 2026101701)
**Status:** ✅ PHPUnit and Behat green on Moodle 4.5.15 and 5.3+; PHPCS, ESLint, Stylelint clean
**Delivery:** full plugin ZIPs `local_coursectrl-2026101701.zip`, `block_coursectrldates-2026101701.zip`

---

## Issues

Every bug found in sessions 020 and 021 is written up as an issue with
acceptance criteria and Definition of Done (Markdown, ready to post):

| ID | Plugin | Title | Status |
|---|---|---|---|
| CCH-L01 | local | execute_action() error payload for unsupported actions | fixed (S020) |
| CCH-L02 | local | TypeError on invalid/missing course id | fixed (S020) |
| CCH-L03 | local | Shift dialog counts missing (`{$a}` lost) | fixed (S020) |
| CCH-L04 | local | Manage shift dialog on hard-coded English fallbacks (relates to #39) | fixed (S020) |
| CCH-L05 | local | Behat suite failing on a clean installation | fixed (S020) |
| CCH-L06 | local | CI and `make phpunit` skip subplugin testsuites | fixed (S021) |
| CCH-L07 | local | version.php minimum 4.4, no 5.3 support, CI without 5.3 | fixed (S020) |
| CCH-L08 | local | Bootstrap 5 utilities missing on 4.5, BS4 classes unstyled on 5.3 | fixed (S021) |
| CCH-B01 | block | "Ja"/"Abschalten" without effect (legacy external aliases) | fixed (S020) |
| CCH-B02 | block | disable_help: `__PHP_Incomplete_Class` from `allowed_classes` | fixed (S020) |
| CCH-B03 | block | Block Behat features failing | fixed (S020) |
| CCH-B04 | block | Bootstrap 5 utilities missing on 4.5 | fixed (S021) |
| CCH-B05 | block | version.php minimum 4.4, no 5.3 support, CI without 5.3 | fixed (S020) |

Open DoD item for CCH-L06/L07/B05: first green GitHub Actions run (not
possible from the container, no push access).

---

## CCH-L06 — Subplugin testsuites

`moodle-plugin-ci phpunit` guesses `local_coursectrl_testsuite` only; the
makefile hard-coded the same suite. Both now build the list from
`mod/*/tests` and `cal/*/tests`:

```
local_coursectrl_testsuite,coursectrlmod_assign_testsuite,coursectrlmod_quiz_testsuite
```

Negative check with the CCH-L01 regression re-introduced: old step exit 0
(632 tests), new step exit 1 (676 tests, 2 failures).

## CCH-L08 / CCH-B04 — Bootstrap compatibility layer

Moodle 4.5 ships Bootstrap 4.6, Moodle 5.x Bootstrap 5. A class inventory of
all templates/JS against the compiled Boost CSS of both versions found:

- missing on 4.5: `visually-hidden`, `gap-1/2/3`, `fw-normal/semibold/bold`,
  `fst-italic`, `lh-1`, `vstack`, `g-2/g-3` gutters, `form-select(-sm)`,
  `text-body-secondary`;
- missing on 5.3: `input-group-append`, `body.modal-open` scroll lock;
- missing in both: `min-width-0`.

Fix: rules at the end of `styles.css`, scoped to `body.path-local-coursectrl`
(block: `.block_coursectrldates`), reproducing the Bootstrap definitions. No
`!important` (Moodle stylelint): where the theme ships the utility, the theme
wins with the identical value. The group filter select carries both
`custom-select custom-select-sm` and `form-select form-select-sm`, with a
common 2.25rem text inset. `form-switch` stays a plain checkbox on 4.5.

Verified in headless Chromium against both compiled theme CSS files
(before → after on 4.5: hidden label visible → 1 px; gap none → 8 px;
fw-semibold 400 → 600; g-2/g-3 row gap 0 → 8/16 px; select unstyled → styled.
On 5.3: addon inner radius 8 px → 0; modal scroll lock visible → hidden).

---

## Verification

| Check | Moodle 4.5 | Moodle 5.3 |
|---|---|---|
| PHPUnit (all four suites) | 729 OK | 729 OK (667 PHPUnit doc-comment deprecations) |
| Behat `@local_coursectrl,@block_coursectrldates` | 77/77, 866/866 | 77/77, 866/866 |
| Install from delivered ZIP | OK | OK |

PHPCS 0/0, `grunt amd` and `grunt stylelint` clean, Mustache lint without
findings (HTML validator unreachable in the container), phpdoc and
savepoints clean.
