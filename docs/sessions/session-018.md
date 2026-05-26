# Session 015 — Review Issues #30–#37: Full Implementation

**Date:** 2026-05-26
**Scope:** local_coursectrl v1.2.0 → v1.2.1
**Status:** ✅ Complete — all CI checks green (PHPCS 0 errors, ESLint clean, PHPUnit 632 tests passing)

---

## Context

Starting point was the development-branch ZIP at v1.2 (post-1.0 stable, post-session-014 patches). A gap analysis was performed against the full CI output from a code review covering issues #30–#37. All fixes were implemented in a single session across five patch deliveries (r1–r5) with CI iteration.

---

## What Was Done

### Block A — Issue #36: Invalid course ID hardening (11 entry points)

**New file:** `classes/local/page/course_context_resolver.php`

A central helper that resolves a raw `courseid` integer into a `(course, context)` pair using `$DB->get_record()` instead of `get_course()`. Returns `null` for `courseid ≤ 0` or non-existent IDs — no `dml_missing_record_exception`, no stacktrace.

Two resolution modes:
- `render_invalid_course_page()` — for HTML views: renders a full Moodle page with a warning notification and exits.
- `throw new moodle_exception(...)` — for POST endpoints.

**Entry points updated (11 files):**

| File | Type | Resolution |
|---|---|---|
| `index.php` | HTML view | `render_invalid_course_page()` |
| `timeline.php` | HTML view | `render_invalid_course_page()` |
| `manage.php` | HTML view | `render_invalid_course_page()` |
| `checks.php` | HTML view | `render_invalid_course_page()` |
| `dependencies.php` | HTML view | `render_invalid_course_page()` |
| `history.php` | HTML view | `render_invalid_course_page()` |
| `simulation.php` | Redirect wrapper | `moodle_exception` |
| `execute.php` | POST endpoint | `moodle_exception` |
| `shift.php` | POST endpoint | `moodle_exception` |
| `fix_action.php` | POST endpoint | `moodle_exception` |
| `rollback.php` | POST endpoint | `moodle_exception` |

The existing lang string `error_no_course` was reused (already correct in EN + DE).

**Behat:** new `tests/behat/invalid_course.feature` with 7 scenarios covering all HTML views, `courseid=999999`, and no-courseid cases.

---

### Block B — Issue #36: Calendar-provider defaults on install/upgrade

**`settings.php`:** `calopenholidays_enabled` default changed from `0` to `1`.

**`db/install.php`:** extended `xmldb_local_coursectrl_install()` to derive country and language from Moodle site configuration (`get_config('core', 'country')` / `get_config('core', 'lang')`), normalise both to uppercase ISO-3166-alpha-2 / ISO-639-1, and set:
- `calopenholidays_enabled = 1`
- `calopenholidays_countryisocode`
- `calopenholidays_languageisocode`
- `calnager_countrycode`

No external network calls; all values read from local Moodle config. Fallbacks: DE / EN.

**`db/upgrade.php`:** new savepoint `2026051200` initialises the same four keys for existing installations, but only when the config value is not yet set (`get_config(...) === false`). Admin-set values are never overwritten.

---

### Block B/E — Issues #32/#34/#36: Language strings

**`lang/en/local_coursectrl.php` and `lang/de/local_coursectrl.php`:**

- **R-prefix removal** (Issue #36): Strings `settings_r1_heading`, `settings_r2_heading`, `settings_r4_heading`, `settings_r7_heading` — the `R1 — `, `R2 — `, `R4 — `, `R7 — ` prefixes removed from the values in both files.
- **`cachedef_caldata`** (Issue #32/34): added `'Calendar provider data'` (EN) and `'Kalenderanbieter-Daten'` (DE).
- **External privacy strings** (Issue #31): 9 new `privacy:metadata:external:*` strings for Nager and OpenHolidays, placed alphabetically after the `privacy:metadata:batch*` group (ordering required two CI iterations to get right).

---

### Block D — Issue #31: Privacy provider

**`classes/privacy/provider.php`:** two `add_external_location_link()` calls added to `get_metadata()`:

- `nager_date_api`: declares `countrycode` and `year` as transmitted parameters; documents explicitly that no personal user data is sent.
- `openholidays_api`: declares `countryisocode`, `languageisocode`, `subdivisioncode`, `validfrom`, `validto`; same personal-data declaration.

The declaration is present for transparency as required by Moodle plugin review, even though only administrative configuration values (not user data) are transmitted.

---

### Block C — Issue #30: PARAM_RAW removal from entry points

All `PARAM_RAW` usages in request-processing code replaced:

| File | Old | New | Validation added |
|---|---|---|---|
| `shift.php` | `cmids` PARAM_RAW | PARAM_TEXT | Regex `^[0-9]+(,[0-9]+)*$`, max 500 IDs |
| `shift.php` | `targets` PARAM_RAW | PARAM_TEXT | (parsed downstream by `shift_target::from_json_array`) |
| `execute.php` | `payloadjson` PARAM_RAW | PARAM_TEXT | `JSON_THROW_ON_ERROR`, length ≤ 65536, array root |
| `execute.php` | `cmidsjson` PARAM_RAW | PARAM_TEXT | `JSON_THROW_ON_ERROR`, length ≤ 16384, int[] only |
| `simulation.php` | `sim_grade[]` PARAM_RAW | PARAM_TEXT | — |
| `simulation.php` | forward-key loop | PARAM_RAW | PARAM_TEXT |
| `checks.php` | `sim_grade[]` PARAM_RAW | PARAM_TEXT | `is_numeric()` check, cmid > 0 whitelist |

Silent `json_decode` fallbacks (`if (!is_array($x)) { $x = []; }`) in `execute.php` replaced with explicit `invalid_parameter_exception` throws.

PARAM_RAW in persistent-class DB field definitions and External API return values (HTML/JSON content) left in place with explanatory comments — these are not request-input parameters.

---

### Block F — Issue #33: innerHTML → Templates / DOM APIs

**`amd/src/graphview.js`:** two `canvas.innerHTML = ''` clearing operations replaced with DOM-safe `while (canvas.firstChild) { canvas.removeChild(canvas.firstChild); }` loops.

**`amd/src/timeline.js`:**
- Added `core/templates` and `core/notification` to AMD `define()` dependencies.
- Four `innerHTML` assignments replaced with `Templates.renderForPromise()` + `Templates.replaceNodeContents()` or DOM element construction.
- The error fallback in the text-changes `fail` handler now uses `document.createElement` + `textContent` (no string concatenation).

**`amd/src/shift_workflow.js`:**
- Added `core/templates` to AMD `define()` dependencies.
- New helper `clearElement(el)` — DOM-safe alternative to `el.innerHTML = ''`.
- Ten `innerHTML` assignments replaced: loading spinners and success/error messages via `Templates.replaceNodeContents(el, html, '')`; clearing operations via `clearElement()`.
- The HTML strings passed to `replaceNodeContents` come from `renderPreviewHtml()` / `renderHitsHtml()` which already use `escHtml()` on all user-controlled values.

**New Mustache templates:**
- `templates/ajax_loading.mustache` — spinner for modal bodies.
- `templates/ajax_error.mustache` — error message with `{{message}}` (auto-escaped).

**AMD rebuild:** all four modified AMD files rebuilt with `npx terser --compress passes=2 --mangle`.

---

### Block G — Issue #37: Simulation completion/pass/grade sync

#### PHP — `classes/output/simulation_page.php`

Extended the grade-item SQL query to also fetch `cm.completionpassgrade` from `course_modules`. New field `completion_requires_pass` computed per CM (`completionpassgrade = 1` AND `haspassgrade = true` AND `completionenabled = true`) and exported in every row of both `cmformrows` and `nextstepformrows`.

#### Mustache — `templates/simulation.mustache`

Both tables (upper activity-state table and lower next-steps table) updated:

- `<tr>` elements: added `data-region="local-coursectrl-sim-row"`, `data-cmid`, `data-gradepass`, `data-completion-requires-pass`.
- Enabled input elements: added `data-field="sim_complete"`, `data-field="sim_passed"`, `data-field="sim_grade"`.

#### JavaScript — `amd/src/simulation.js`

Completely rewritten (was a dropdown-only module). New structure:

- `readStateFromRow(row)` — reads current checkbox/input values from a row.
- `applyNormalizationRules(changedField, completed, passed, grade, reqpass, gradepass)` — pure function applying the four sync rules; extracted to keep `syncSimulationRow` below ESLint's complexity limit of 20.
- `applyStateToRow(row, completed, passed, grade)` — writes normalised state back.
- `syncSimulationRow(changedRow, changedField, root)` — orchestrates the above; queries all rows for the same `cmid` and syncs them.
- `initGradeSync(root)` — event delegation on `change` and `input`.

The four rules:
1. `completed` + `reqpass` → set `passed`; elevate grade to threshold if no grade was explicitly submitted.
2. `passed` + `reqpass` → set `completed`; same grade elevation logic.
3. Submitted `grade ≥ threshold` → set `passed` (and `completed` if `reqpass`).
4. Submitted `grade < threshold` → clear `passed` (and `completed` if `reqpass`). **Rule 4 takes priority over rules 1/2** — a submitted grade is the source of truth.

The `gradesubmitted` flag (`is_numeric()` check on raw string) is the key to the priority: rules 1/2 only elevate grade when none was submitted; rules 3/4 only run when one was.

#### PHP — `classes/local/simulation/simulation_state_normalizer.php`

New class implementing the identical four rules server-side, applied in `checks.php` before `new learner_state(...)`. Parameters validated: cmid whitelist, `is_numeric()` grade check, 0–100 clamp.

`checks.php` updated to: fetch per-CM metadata (completionpassgrade, gradepass, completionenabled) via SQL, build `$cmmeta[]`, call `simulation_state_normalizer::normalise()`.

#### Tests

`tests/local/simulation/simulation_state_normalizer_test.php` — 10 PHPUnit tests covering all four rules, invalid cmids, non-numeric grades, grade clamping, multi-CM independence.

---

### Block H — Issue #35: Empty Dashboard

The course_context_resolver (Block A) eliminates the primary cause of a blank page (DML stacktrace from `get_course()` corrupting the output buffer). Direct defensive hardening of `dashboard_page::export_for_template()` was not implemented — this requires live reproduction on a specific Moodle instance to identify which sub-component triggers the failure. The existing `tests/behat/dashboard.feature` provides smoke coverage. See open item below.

---

## CI Iteration History

| Patch | Issue |
|---|---|
| r1 | Initial delivery: all blocks A–H |
| r2 | PHPCS: install.php alignment spaces; lang ordering (external before batch); timeline.js line 133; simulation.js alignment + complexity; test class/method docblocks |
| r3 | PHPUnit: normalizer rule 4 bug (grade elevated by rule 1 then rule 3 applied); non-numeric grade cast to 0.0 |
| r4 | PHPCS: `use PHPUnit\…\CoversClass` → full-path `#[\PHPUnit\…\CoversClass]` (didn't fix warnings) |
| r5 | PHPCS: `@covers` added to each test method docblock (matches project convention) |

---

## Architecture Decisions

| Decision | Rationale |
|---|---|
| `course_context_resolver` as separate class (not lib.php) | Testable, namespaced, consistent with project architecture |
| `render_invalid_course_page()` as `never` return type | Signals to static analysis that function does not return; avoids unreachable-code false positives |
| POST endpoints throw `moodle_exception`, not render page | POST endpoints have no PAGE setup at the point of the check |
| `gradesubmitted` flag in normalizer | Submitted grade = explicit user intent; rules 1/2 must not override it |
| Rules 3/4 only on `$gradesubmitted = true` | Prevents implicit grade=0 from `(float)'abc'` triggering rule 4 |
| `clearElement()` in shift_workflow.js | DOM-safe alternative to `innerHTML = ''` satisfying Issue #33 |
| External privacy links declared even for non-personal data | Required by Moodle plugin review; comment explains no personal data |

---

## Regression Risk

- **shift_workflow.js**: extensive innerHTML → `Templates.replaceNodeContents` changes. The ESLint warnings already present from session 014 (7 warnings) are unchanged; Behat coverage for the shift workflow exists in `timeline_shift*.feature`.
- **normalizer in checks.php**: replaces the previous hand-coded sim_grade loop; tested by 10 PHPUnit tests.
- **course_context_resolver**: previously `index.php` had a manual `!$courseid` check — the resolver subsumes this and adds the DB existence check on top.

---

## Open Items

| Item | Priority | Notes |
|---|---|---|
| Issue #35: root cause of empty dashboard on specific courses | Medium | Needs live reproduction with `DEBUG_ALL` — cannot be fixed without concrete error output |
| Behat JS tests for simulation grade sync (#37) | Medium | `simulation_grade_sync.feature` scaffolded; needs live Moodle to run @javascript |
| Privacy provider tests for external links (#31) | Low | `tests/privacy/provider_test.php` exists; should add assertion for external location links |

---

## Files Changed

```
classes/local/page/course_context_resolver.php     NEW
classes/local/simulation/simulation_state_normalizer.php  NEW
classes/output/simulation_page.php                 MODIFIED
classes/privacy/provider.php                       MODIFIED
templates/ajax_loading.mustache                    NEW
templates/ajax_error.mustache                      NEW
templates/simulation.mustache                      MODIFIED
amd/src/graphview.js                               MODIFIED
amd/src/shift_workflow.js                          MODIFIED
amd/src/timeline.js                                MODIFIED
amd/src/simulation.js                              REWRITTEN
amd/build/graphview.min.js                         REBUILT
amd/build/shift_workflow.min.js                    REBUILT
amd/build/timeline.min.js                          REBUILT
amd/build/simulation.min.js                        REBUILT
index.php                                          MODIFIED
timeline.php                                       MODIFIED
manage.php                                         MODIFIED
checks.php                                         MODIFIED
dependencies.php                                   MODIFIED
history.php                                        MODIFIED
simulation.php                                     MODIFIED
execute.php                                        MODIFIED
shift.php                                          MODIFIED
fix_action.php                                     MODIFIED
rollback.php                                       MODIFIED
settings.php                                       MODIFIED
db/install.php                                     MODIFIED
db/upgrade.php                                     MODIFIED
lang/en/local_coursectrl.php                       MODIFIED
lang/de/local_coursectrl.php                       MODIFIED
tests/behat/invalid_course.feature                 NEW
tests/local/simulation/simulation_state_normalizer_test.php  NEW
version.php                                        MODIFIED (2026051100 → 2026051200, v1.2.1)
```

**34 files changed. 5 new files. 1 file rewritten.**

---

## Lessons Learned

- **Moodle PHPCS coverage sniff** (this version): requires `@covers` on *each* test method individually — class-level `#[CoversClass]` or class-docblock `@covers` alone is not sufficient.
- **Lang file alphabetical ordering**: inserting strings with `str_replace` accumulates ordering errors across patches. Always use the Extract-Sort-Rewrite pattern; insertion must account for alphabetical position of the new key, not just proximity to a related key.
- **`is_numeric()` before `(float)` cast**: `(float) 'abc'` silently becomes `0.0`. For user-submitted grade values, always verify `is_numeric()` before casting.
- **Rule priority in combined state machines**: when multiple normalization rules can conflict, an explicit priority signal (`gradesubmitted` flag) is more reliable than rule ordering alone.
- **`Templates.replaceNodeContents(el, html, '')` for trusted HTML**: acceptable replacement for `innerHTML` when the HTML source is known-safe (generated by our own `escHtml()`-using functions). The empty JS string avoids running arbitrary template scripts.
