# Course Control Hub — Session 005 Summary

**Dates:** 2026-04-15 to 2026-04-16
**Patches:** 029 – 038
**Repos:**
- https://github.com/ralferlebach/moodle-local_coursectrl.git
- https://github.com/ralferlebach/moodle-block_coursectrl.git

## Scope

This session delivered the full Phase-5 text-datetime engine, Phase-6 dashboard v2 / manager (chronological timeline with mini-calendar, shift/delete dialogs, user preferences), and the first UI pages for the Phase-4 bulk pipeline. It closes the UI work on the dashboard and manager views against the requirements listed in the Pflichtenheft.

Starting point: version `2026041012` / `0.1.20`, 156 tests, 3 adapters, functional preview/batch pipeline, no UI pages.

End of session: version `2026041603` / `0.1.30`, ~285 tests, UI pages complete for dashboard + timeline + bulk manage + text review, `shift_dates` and `unset_dates` as the two production actions.

## Patches delivered

### patch-029 — UI pages (manage / preview / execute)
- `manage.php`, `preview.php`, `execute.php` with AMD enhancement
- `classes/output/{manage,preview,result}_page.php` + templates
- Bulk Actions button on dashboard, +30 lang strings
- Version `2026041501` / `0.1.21`

### patch-030 — Integration tests for UI pages
- 18 tests across `manage_page_test`, `preview_page_test`, `result_page_test`; dashboard test extended
- Version `2026041502` / `0.1.22`

### patch-031 — Phase-5 skeleton
- `text_datetime_extractor` (regex DE/EN/ISO, 17 tests)
- `text_datetime_parser` (ISO normalisation, 12 tests)
- `text_hit_classifier` (safe/ambiguous/informational, 12 tests)
- `text_hit` persistent (4 tests)
- Fix ESLint `no-unused-vars` in `manage.js`
- Version `2026041503` / `0.1.23`

### patch-032 — Text-change builder + rewriter
- `text_change_builder` (course-wide scan orchestrator, 7 tests)
- `text_datetime_rewriter` (offset-based substring replacement, 10 tests)
- Fix phpcs variable-underscore + inline-comment-capitalization
- Version `2026041504` / `0.1.24`

### patch-033 — Review UI + External API
- `textreview_manager`, External API `get_text_hits` + `apply_text_changes`
- `textreview.php` + `textreview_page` renderable + template + AMD
- Text Review button on dashboard
- Fix PSR12 multi-line `if` in `text_change_builder_test.php`
- Version `2026041505` / `0.1.25`

### patch-034 — Dashboard v2 data enrichment
- Extended `cm_item` with `completionexpected`
- `availability_parser` (11 tests), `dependency_index` (8 tests)
- `date_collector` (unified chronological date list)
- `inventory_service::build_cms()` batch-loads `completionexpected`
- Version `2026041506` / `0.1.26`

### patch-035 — Dashboard v2 template
- Rebuilt `dashboard.mustache` + `dashboard_page.php`: dates per CM, `completionexpected` with ⏰, prerequisites (⬆) and dependents (⬇) as anchor cross-links (`#cm-{id}`), date restrictions, warning badges (❗ for circular deps), activity+edit URLs
- Initial parser UTC fix (incomplete — see patch-036)
- 8 updated dashboard tests
- Version `2026041507` / `0.1.27`

### patch-036 — Timeline page v1 + UTC fix
- Fix `text_datetime_parser::to_timestamp()` and `shift_iso()` with bang-prefixed format strings (`!Y-m-d`, `!Y-m-d\TH:i`) so missing time components default to 00:00:00 UTC
- Fix `list($a, $b) = …` → `[$a, $b] = …` short-list syntax
- `timeline_page` renderable + `timeline.mustache` + `timeline.php` with day-grouped chronological list, filters, placeholder shift buttons, 6 tests
- Version `2026041601` / `0.1.28`

### patch-037 — Mini-calendar + shift dialog + user preferences
- `calendar_grid_builder` (month-wise grid with ISO Mon-start weeks, leading/trailing padding, istoday/ispast/hasentries flags, 7 tests)
- Modal shift dialog (days/hours + followdeps), collapsible mini-calendar
- `shift.php` backend dispatching to `batch_manager::execute()` with transitive dependents expansion
- `lib.php` declaring `local_coursectrl_showcalendar` user preference
- Fix PSR12 multi-line `if` in `timeline_page.php`, camelCase → lowercase in `calendar_grid_builder`
- Version `2026041602` / `0.1.29`

### patch-038 — Delete action + "Sofort umsetzen" preference
- Extended `shift_dates_executor` trait with `unset_dates` action (payload `{fields: [...]}` sets specified date fields to 0)
- All 3 adapters declare `['shift_dates', 'unset_dates']` as supported actions
- Timeline: delete button (🗑) per entry (adapter-sourced only, flagged `deletable`), delete confirm dialog
- `timeline.mustache`: "Sofort umsetzen" checkbox in filter strip
- `lib.php`: `local_coursectrl_immediateapply` preference
- `shift.php` rewritten to handle both actions; when `immediateapply` is set and no errors, redirects silently to timeline instead of showing result page
- Test fixes: `timeline_page_test::test_export_scalars` asserts against `index.php` instead of literal `dashboard`; existing `assign`/`quiz` adapter tests updated to `['shift_dates', 'unset_dates']`
- 6 new trait tests for `unset_dates` (validation, preview, execute, noop, snapshot)
- Version `2026041603` / `0.1.30`

## Architectural decisions

- `completionexpected` lives in `course_modules`, not in per-module tables, so it's handled in Core inventory (not in adapters)
- Availability JSON is parsed once in `availability_parser`, then `dependency_index` builds forward/reverse maps for cross-linking and circular detection
- Date unification: `date_collector` merges three sources (adapter fields, `completionexpected`, availability date conditions) into one chronological list
- Deletion only works on adapter-sourced dates (`source === 'adapter'`); availability date conditions and `completionexpected` are not deletable from the timeline in this iteration — they require the availability editor or the CM edit form
- All date arithmetic uses UTC + bang-prefixed format strings for determinism
- User preferences persist via `core_user_update_user_preferences` from AMD; backend reads with `get_user_preferences()`
- "Sofort umsetzen" pref: when set, successful executions skip the result page → redirect to timeline with success notification

## Lint/test failures fixed this session

| Issue | Patch |
|---|---|
| ESLint `no-unused-vars` on `manage.js` `init()` | 031 |
| phpcs variable underscore `$timeopt_en` | 032 |
| phpcs inline comment lowercase start | 032 |
| phpcs PSR12 multi-line `if` in `text_change_builder_test` | 033 |
| phpunit timezone failure in `test_to_timestamp` (tz shift of 1 day) | 036 (definitive fix) |
| phpcs `Universal.Lists.DisallowLongListSyntax` | 036 |
| phpcs PSR12 multi-line `if` in `timeline_page` | 037 |
| phpcs camelCase `$daysInMonth`, `$firstDow`, `$lastDow` | 037 |
| phpunit `timeline_page_test::test_export_scalars` asserted `dashboard` substring against index.php URL | 038 |

## Test-count trajectory

| Patch | Added | Running total |
|---|---|---|
| Session start | — | 156 |
| 030 | +18 (UI renderables) | 174 |
| 031 | +45 (text engine base) | 219 |
| 032 | +17 (rewriter + builder) | 236 |
| 033 | +1 (textreview integration) | 237 |
| 034 | +19 (parser/index) | 256 |
| 035 | 8 updated | 256 |
| 036 | +6 (timeline) | 262 |
| 037 | +7 (calendar grid) | 269 |
| 038 | +6 unset_dates + 2 timeline | ~277 |

CI report in patch-038 context shows 214 tests because old adapter tests were not yet re-run. After patch-038 the full count should exceed 277.

## UI requirements status (per the Pflichtenheft checklist the user specified)

### Dashboard

| Requirement | Status |
|---|---|
| Activities with view + edit links | ✓ patch-035 |
| Dates and restrictions displayed | ✓ patch-035 |
| Cross-link to controlling activity (⬆) | ✓ patch-035 |
| Cross-link to dependent activities (⬇) | ✓ patch-035 |
| Warning badges (circular deps) | ✓ patch-035 |
| More light checks (temporal conflicts etc.) | planned for patch-039 (Phase 8) |

### Manager / Timeline

| Requirement | Status |
|---|---|
| Mini-calendar, month-wise tiles, hover tooltip | ✓ patch-037 |
| Single-line filter strip, preference-persisted | ✓ patch-037 |
| Day-grouped chronological list | ✓ patch-036/037 |
| Shift action, with optional follow-deps | ✓ patch-037 |
| Delete action with confirm dialog | ✓ patch-038 |
| "Sofort umsetzen" immediate-apply checkbox | ✓ patch-038 |
| completionexpected + availability dates included | ✓ patch-034 |

## Open work for future patches

- **patch-039** (suggested next): Phase-8 light checks — temporal conflicts (`duedate` before `allowsubmissionsfromdate`), unreachable CMs (availability referencing missing cmid), missing completion prerequisite. Surface as ⚠️ badges on the dashboard.
- **Phase 6** full visualisation: dedicated Gantt/graph view distinct from the timeline manager
- **Phase 7** learner simulation
- **Phase 9** rollback_manager + history UI
- **Phase 10** additional adapters: forum, lesson, page, h5pactivity, workshop

## File locations

- Working trees: `/home/claude/work/local_coursectrl/moodle-local_coursectrl-main/` and `/home/claude/work/block_coursectrl/moodle-block_coursectrl-main/`
- Patch directories: `/home/claude/work/patch-0XX/local/coursectrl/…`
- Patch ZIPs: `/mnt/user-data/outputs/patch-0XX.zip`

## Notable code conventions

- Moodle 4.5 (`$plugin->requires = 2024042200`)
- Short array syntax throughout
- No blank line after `{`
- Multiline function arguments: one per line
- Bang-prefixed date format strings for deterministic UTC
- Per-adapter `version.php` bumped in lockstep with local_coursectrl so subplugin dependency resolves
- Lang strings in strict alphabetical order, no section comments (phpcs strict)
