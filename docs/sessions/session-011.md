# Session 011 — Course Control Hub (`local_coursectrl`)

**Datum:** 2026-04-21  
**Start-Version:** 0.2.0-rc11 / 2026042017  
**End-Version:** 0.9.0-rc1 / 2026042025  
**Definitive Codebase:** `COMPLETE-0.2.0-rc13.zip` (36 Dateien, alle Patches konsolidiert)  
**Letzte Einzel-Patches:** rc14–rc18 + Versionsbump → endgültig **0.9.0-rc1**

---

## Versionsnummer-Entscheidung

**[DECISION] 0.9.0-rc1 statt 0.2.0-rc:** `0.2.x` war ein Überbleibsel aus der Entwicklungsphase und passte nicht zu einem RC-Label. MVP 1 + MVP 2 vollständig, MVP 3 bis auf dokumentierte Restpunkte fertig. `0.9.0` signalisiert: produktionsnahe Qualität, kurz vor 1.0 — aber noch nicht released. `1.0.0` ist der Zielzustand nach Schließung der offenen Test-Lücken und `grunt amd`.

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
| rc17/rc18 | CI: Actions auf `@v4` Floating-Tags, `FORCE_JAVASCRIPT_ACTIONS_TO_NODE24` reicht |
| 0.9.0-rc1 | Versionsnummer `0.2.0` → `0.9.0-rc1` |

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

Der Tab liest aus `local_coursectrl_text_hit`-DB-Tabelle, die nur nach einem expliziten Scan befüllt wird. Fix: `timeline.js` triggert automatisch `get_text_hits(rescan=true)` via AJAX wenn `data-hasrows=0`, Seiten-Reload wenn Treffer gefunden.

### Checkbox-Alignment Aktivitätsliste

Bootstrap 5 float-basiertes Checkbox-Positioning überlagerte Activity-Icons. Fix: `.local-coursectrl-manage .form-check` auf Flexbox, redundante Inline-Styles entfernt.

### Deselect-All lässt Section-Checkboxen stehen

`manage.js` `deselect-section`-Handler leerte nur CM-Checkboxen, nicht den Section-Header-Checkbox. Fix: Section-Checkbox wird ebenfalls auf `checked=false` gesetzt.

### Info-Alert in Textprüfungs-Tab / FA-Icons entfernt

`{{^textreview_from_shift}}`-Block entfernt. `fa-flask` und `fa-refresh` aus Deep-Analysis-Buttons entfernt.

### `consistency_runner.php` — PHP Warnings

Direkter Array-Zugriff auf `field_early/field_late/ts_early/ts_late` ohne `??`-Fallback. Fix: `?? ''` / `?? 0`.

### `group_resolver.php` — Duplicate-Key Notice

`get_records_sql()` auf `groupings_groups` mit `groupingid` als Key → Duplikate. Fix: `get_recordset_sql()` + `$rs->close()`.

### CI-Matrix Moodle 5.2

- Moodle 5.2 setzt PHP ≥ 8.3 voraus → `MOODLE_502_STABLE` + `php: '8.2'` als Exclude
- Behat-Matrix-Korruption behoben, 5.2 aus Behat entfernt
- GitHub Actions auf `@v4` Floating-Tags; `FORCE_JAVASCRIPT_ACTIONS_TO_NODE24: true` erzwingt Node 24

---

## CI-Matrix (Stand 0.9.0-rc1)

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

## Offene Punkte für Session 012

### Priorität 1 — Vor 1.0.0 zwingend

#### Test-Lücken (durch vollständige Quellcode-Analyse ermittelt)

Die Codebase hat 72 Klassen, 496 laufende Tests in 65 Testklassen. Die Fixture-Tests (`fixture_analysis_test`, `fixture_simulation_test`, `fixture_logging_rollback_test`) sind bereits vorhanden und gut — die ursprüngliche "Fixtures-Zwischenphase" aus dem Pflichtenheft gilt damit als **erledigt**.

**Echter Nachholbedarf:**

| Klasse | Situation | Priorität |
|---|---|---|
| `privacy/provider.php` | 7 DSGVO-Methoden (`get_metadata`, `export_user_data`, `delete_data_*`), kein einziger Test. Für ein Produktiv-Release nicht akzeptabel. | **hoch** |
| `manager/textreview_manager.php` | 247 Zeilen Scan-Logik + Persistenz, kein dedizierter Test — nur indirekt via `fixture_date_shift_test`. | **hoch** |
| `external/apply_text_changes.php` | Kein API-Test — nur JS-Pfad war testbar. | mittel |
| `external/get_text_hits.php` | Kein API-Test — rescan-Logik (true/false) und DB-Persistenz ungetestet. | mittel |

**Fehlende `@covers`-Deklarationen** (Code wird getestet, Docblock fehlt — PHPUnit-Warnung):

- `shift_dates_executor` — intensiv in `fixture_date_shift_test`, `@covers` fehlt im Docblock
- Entities (`cm_item`, `section_item`, `course_item`, `inventory_item`, `text_item`) — in `entities_test.php` getestet, `@covers` fehlt
- `inventory_snapshot` — indirekt via `inventory_service_test`

#### AMD Build

`amd/build/timeline.min.js` — Auto-Scan-Code (rc13) ist nur in `amd/src/`. In Produktionsbetrieb wird `.min.js` geladen → Auto-Scan inaktiv. Fix: `grunt amd` lokal ausführen und `.min.js` committen.

### Priorität 2 — Qualität, nicht release-blockierend

- `amd/build/manage.min.js.map` — Sourcemap veraltet (cosmetic)
- Behat 5.2 reaktivieren sobald Behat-Matrix eigene PHP-Version steuert

### Nach 1.0.0 verschoben

- **Rollback-UI** — `rollback.php`-Seite mit Formular. `rollback_manager` ist fertig und getestet (`fixture_logging_rollback_test` mit 9 Tests), die UI-Seite ist komfortabel aber nicht release-blockierend. Bewusst auf Post-1.0 verschoben.
- **Phase 10** — Dokumentation für Lehrende/Admins, Release-Paket
- Behat 5.2 reaktivieren

---

## Wiederkehrende Erkenntnisse / Architectural Decisions

**[DECISION] Versionsnummer:** `0.9.x-rc` bis Test-Lücken geschlossen und `grunt amd` ausgeführt. Dann `1.0.0`.

**[DECISION] Rollback-UI nach 1.0:** `rollback_manager` ist fertig und vollständig getestet. Die UI-Seite (`rollback.php`) kommt als eigenständiges Feature nach dem 1.0-Release.

**[DECISION] Lang-Rebuild-Strategie:** Immer Extract-Sort-Rewrite (Python), nie str_replace in Lang-Dateien. Jede str_replace-basierte Einfügung riskiert PHPCS-Ordnungsverletzungen und Überschreiben vorheriger Patches.

**[DECISION] Mustache-Audit vor jedem Patch:** Python-Skript das alle `{{#str}} key, local_coursectrl` aus allen Templates extrahiert und gegen die Lang-Datei prüft — vor jeder Lieferung Pflicht.

**[DECISION] Preview-Keys:** `shift_dates_executor.php` verwendet einheitlich `old`/`new`. JS liest `fd.old`/`fd.new`.

**[DECISION] Textprüfungs-Tab:** Auto-Scan via AJAX bei `data-hasrows=0`. Kein Server-seitiger Scan bei jedem Seitenaufruf.

**[DECISION] CI Moodle 5.2:** PHPUnit ja (8.3+8.4), Behat nein. Behat-5.2 kommt nach 1.0.
