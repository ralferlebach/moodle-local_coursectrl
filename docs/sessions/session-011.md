# Session 011 — Course Control Hub (`local_coursectrl`)

**Datum:** 2026-04-21  
**Start-Version:** 0.2.0-rc11 / 2026042017  
**End-Version:** 0.2.0-rc17 / 2026042023  
**Definitive Codebase:** `COMPLETE-0.2.0-rc13.zip` (36 Dateien, alle Patches konsolidiert)

---

## Gelieferte Patches (Übersicht)

| Patch | Inhalt |
|---|---|
| rc2 | Lang-Fix: `settings_dashboard_*` (erster Versuch, unvollständig) |
| rc3 | Lang-Fix: alle Dashboard-Strings, alphabetisch sortiert, `fix_dashboard_comments.py` |
| rc4 | CSS/Mustache: Checkbox-Alignment in Manage-Aktivitätsliste |
| rc5 | PHPCS-sauber: Lang-Dateien rebuild, `dashboard_page.php` rewritten (keine `// ──` Kommentare) |
| rc6 | `timeline.mustache`: Zurück-Button im Textprüfungs-Leerstand entfernt |
| rc7 | PHPUnit-Fixes: `set_config('dashboard_inventory','show')` in Inventar-Tests, `timecreated` im text_hit Insert, Moodle 5.2 in CI |
| rc8 | Lang-EN: `settings_calmanual_entries_desc` (fehlte seit Basis) |
| rc9 | Runtime-Warnings: `consistency_runner` null-coalesce, `group_resolver` get_recordset_sql |
| rc10 | **COMPLETE** — konsolidierter ZIP aller Patches 0.1.91–rc9 (29 Dateien) |
| rc11 | Lang: `risk_type_completionexpected_window` + `_gap_exceeds_threshold` (EN+DE) |
| rc12 | Alle verbliebenen Bugs dieser Session (36 Dateien, definitiver Stand) |
| rc13 | `risks_*` und `checks_run_deep` Lang-Strings (13 neu), Preview-Key-Fix, Textprüfungs-Tab Auto-Scan |
| rc14 | CI: `MOODLE_502_STABLE` + PHP 8.2 exclude |
| rc15 | Test-Fix: `fixture_date_shift_test` `old_value` → `old` |
| rc16 | CI: Behat-Matrix-Korruption behoben, 5.2 aus Behat entfernt |
| rc17 | CI: Actions auf Node 24 native Versionen gebumpt |

---

## Behobene Bugs (diese Session)

### Lang-Strings (systematisch aufgearbeitet)

Das zentrale Problem dieser Session: Jeder Patch, der eine Lang-Datei enthielt, kopierte sie aus der Basis-ZIP und überschrieb alle vorherigen Ergänzungen. Behoben durch konsequenten **Extract-Sort-Rewrite**-Ansatz (Python, nicht str_replace). Endstand: **586 Strings EN+DE**, alphabetisch sortiert.

Neu hinzugefügt (nie im Basis-Repo vorhanden):

- `checks_run_deep` — in `checks.mustache` referenziert
- `risks_title`, `risks_run_now`, `risks_affected`, `risks_badge_error/warning/notice`, `risks_no_issues`, `risks_not_run_yet`, `risks_score`, `risks_score_detail`, `risks_last_run`, `risks_cascade` — alle in `risks.mustache`
- `risk_type_completionexpected_window`, `risk_type_completionexpected_gap_exceeds_threshold` — in `temporal_conflict_detector.php`
- `settings_calmanual_entries_desc` — in `settings.php` referenziert, nur in DE vorhanden
- Alle `dashboard_*`, `event_*`, `task_purge_old_batches`, `settings_dashboard_*` — aus Patches 0.1.91–0.1.94

**Lektion:** Vor jedem Lang-Patch systematisch alle Mustache-Templates per Python gegen die definierten Strings prüfen. Moodle's `get_string(..., null, true)` (silently fail) maskiert fehlende Strings im Produktivbetrieb.

### Preview-Termine leer (Verschiebungs-Workflow)

`shift_dates_executor.php` lieferte für `shift_dates`-Aktionen `old_value`/`new_value` als Keys, aber `shift_workflow.js` las `fd.old`/`fd.new`. Fix: einheitliche Keys `old`/`new` für alle Aktionen. Dazugehörigen Test (`fixture_date_shift_test`) angepasst.

### Textprüfungs-Tab zeigt keine Texte

Der Tab liest aus `local_coursectrl_text_hit`-DB-Tabelle, die nur nach einem expliziten Scan befüllt wird. Fix: `timeline.js` triggert jetzt automatisch `get_text_hits(rescan=true)` via AJAX wenn `data-hasrows=0`, und lädt die Seite neu wenn Treffer gefunden.

### Checkbox-Alignment Aktivitätsliste

Bootstrap 5 float-basiertes Checkbox-Positioning überlagerte Activity-Icons. Fix: `.local-coursectrl-manage .form-check` auf Flexbox umgestellt, redundante Inline-Styles entfernt.

### Deselect-All lässt Section-Checkboxen stehen

`manage.js` `deselect-section`-Handler leerte nur CM-Checkboxen, nicht den Section-Header-Checkbox. Fix: Section-Checkbox wird jetzt ebenfalls auf `checked=false` gesetzt.

### Info-Alert in Textprüfungs-Tab

`{{^textreview_from_shift}}`-Block mit Workflow-Hinweis auf Wunsch entfernt.

### Font Awesome Icons in Buttons entfernt

`fa-flask` aus dem Deep-Analysis-Button in `checks.mustache`, `fa-refresh` aus dem Analyse-jetzt-ausführen-Button.

### `consistency_runner.php` — PHP Warnings

Direkter Array-Zugriff auf `field_early/field_late/ts_early/ts_late` ohne `??`-Fallback. Diese Keys existieren nur für `temporal_conflict`-Issues. Fix: `?? ''` / `?? 0`.

### `group_resolver.php` — Duplicate-Key Notice

`get_records_sql()` auf `groupings_groups` mit `groupingid` als Key → Duplikate wenn Gruppierung mehrere Gruppen hat. Fix: `get_recordset_sql()` + `$rs->close()`.

### CI-Matrix Moodle 5.2

- Moodle 5.2 setzt PHP ≥ 8.3 voraus → `MOODLE_502_STABLE` + `php: '8.2'` als Exclude eingetragen
- Behat-Matrix-Korruption durch früheres Regex-Replace behoben (`mariadb:` war zerstückelt)
- Moodle 5.2 aus Behat entfernt (Behat läuft hardcoded auf PHP 8.2)
- GitHub Actions auf Node 24 native Versionen gebumpt: `actions/checkout@v4.2.2`, `actions/setup-node@v4.3.0`, `actions/upload-artifact@v4.4.3`

---

## CI-Matrix (Stand rc17)

| Moodle | PHP 8.2 | PHP 8.3 | PHP 8.4 | Behat |
|---|---|---|---|---|
| 4.5 | MariaDB + PgSQL | MariaDB + PgSQL | — (excluded) | ✅ PHP 8.2 |
| 5.0 | MariaDB + PgSQL | MariaDB + PgSQL | — (excluded) | ✅ PHP 8.2 |
| 5.1 | MariaDB + PgSQL | MariaDB + PgSQL | MariaDB + PgSQL | ✅ PHP 8.2 |
| 5.2 | — (excluded) | MariaDB + PgSQL | MariaDB + PgSQL | — (PHP 8.2 inkompatibel) |

---

## Neue Dateien (diese Session, alle in COMPLETE-0.2.0-rc13.zip)

| Datei | Beschreibung |
|---|---|
| `classes/event/batch_created.php` | Event: Batch erstellt |
| `classes/event/batch_rolled_back.php` | Event: Batch zurückgenommen |
| `classes/event/report_generated.php` | Event: Bericht generiert |
| `classes/task/purge_old_batches.php` | Scheduled Task: Historienbereinigung |
| `classes/output/dashboard_page.php` | Dashboard Modell D (Cockpit-Layout) |
| `db/tasks.php` | Task-Registrierung |
| `templates/dashboard.mustache` | Dashboard-Template |
| `README.md` | Plugin-Dokumentation |
| `tests/output/dashboard_page_test.php` | PHPUnit-Tests Dashboard |
| `tests/behat/dashboard.feature` | Behat-Feature Dashboard |
| `tests/behat/timeline.feature` | Behat-Feature Timeline |

---

## Geänderte Dateien (alle in COMPLETE-0.2.0-rc13.zip)

`batch_manager.php`, `rollback_manager.php` — Events; `checks_page.php` — fix_type_for, deepanalysisurl; `timeline_page.php` — textreviewurl entfernt; `renderer.php` — render_textreview_page entfernt; `consistency_runner.php` — null-coalesce; `group_resolver.php` — recordset; `shift_dates_executor.php` — old/new Keys; `settings.php` — Dashboard-Settings; `styles.css` — toolbar + manage Flexbox; `templates/checks.mustache` — deepanalysis, keine Icons; `templates/timeline.mustache` — alle 8 Fixes; `templates/manage.mustache` — keine Inline-Styles; `templates/simulation.mustache` — flex-nowrap, keine Labels; `lang/en` + `lang/de` — 586 Strings; `amd/src/timeline.js` — Auto-Scan; `amd/src/manage.js` + `.min.js` — deselect-section; `tests/behat/behat_local_coursectrl.php` — 3 neue Steps; `.github/workflows/moodle-ci.yml` — 5.2, excludes, Node 24.

---

## Noch offene Punkte

### Akuter Qualitätsmangel
- **`amd/build/timeline.min.js`** — Auto-Scan-Code aus rc13 ist nur in `amd/src/`, nicht im `.min.js`-Build. In Produktionsbetrieb (ohne `$CFG->debugdeveloper`) wird `.min.js` geladen → Auto-Scan inaktiv. Fix: `grunt amd` lokal ausführen oder `.min.js` manuell patchen wie `manage.min.js`.
- **`amd/build/manage.min.js.map`** — Sourcemap veraltet.

### Aus Pflichtenheft (geplant, nicht vergessen)
- **Rollback-UI** — `rollback.php`-Seite mit Formular fehlt (rollback_manager ist fertig)
- **Fixtures-Testabdeckung** — Komplexe Abhängigkeits-Fixtures + vollständige PHPUnit/Behat-Abdeckung (als eigene Zwischenphase geplant, nach Phase 8)
- **Phase 10** — Dokumentation Lehrende/Admins, Release-Paket

---

## Wiederkehrende Erkenntnisse / Architectural Decisions

**[DECISION] Lang-Rebuild-Strategie:** Immer Extract-Sort-Rewrite (Python), nie str_replace in Lang-Dateien. Jede str_replace-basierte Einfügung riskiert Ordnungsverletzungen, die PHPCS meldet.

**[DECISION] Mustache-Audit vor jedem Lang-Patch:** Python-Skript das alle `{{#str}} key, local_coursectrl` aus allen Templates extrahiert und gegen die Lang-Datei prüft — vor jeder Lieferung.

**[DECISION] Preview-Keys:** `shift_dates_executor.php` verwendet jetzt einheitlich `old`/`new` für alle Aktionen (nicht `old_value`/`new_value` für shift_dates). JS liest `fd.old`/`fd.new`.

**[DECISION] Textprüfungs-Tab:** Auto-Scan via AJAX bei `data-hasrows=0`. Kein Server-seitiger Scan bei jedem Seitenaufruf (zu teuer).

**[DECISION] CI Moodle 5.2:** PHPUnit ja (8.3+8.4), Behat nein (hardcoded PHP 8.2 inkompatibel). Kann reaktiviert werden sobald Behat-Job eigene PHP-Version per Matrix bekommt.
