# CCH Kontext-Dokument
Erstellt: 2026-04-11
Zuletzt aktualisiert: 2026-04-11 (nach patch-028, Sitzung abgeschlossen)
Sitzung Nr.: 004 — **abgeschlossen**

---

## 1. Aktueller Projektstand

**Phase 2 — Inventar und Selektionslogik:** vollständig (Session 003).
**Phase 3 — Adapter-Basis und erste Aktivitätstypen:** vollständig (Session 004, patches 017–022).
**Phase 4 — Bulk-Engine für strukturierte Felder:** vollständig für MVP 1 (Session 004, patches 023–028).

**Was in Sitzung 004 erledigt wurde (patches 017–028):**

### Phase 3 (patches 017–022)

**patch-017 — abstract_activity_adapter**
- Produktive Basisklasse mit No-Op-Defaults für 12 der 13 Interface-Methoden.
- Test-Fixture `fake_adapter_base` migriert auf Vererbung.
- 6 neue Unit-Tests.

**patch-018 — coursectrlmod_assign (read + preview)**
- Erstes echtes Subplugin. Field-Map: `duedate`, `allowsubmissionsfromdate`, `cutoffdate`, `gradingduedate`.
- 9 PHPUnit-Tests gegen echtes mod_assign.

**patch-019 — Lint-Fixes + Registry-Auto-Discovery-Test**
- PSR1/PSR12-Fehler behoben, `MOODLE_INTERNAL` aus namespaced Files entfernt.
- 2 Live-Discovery-Tests für assign.

**patch-020 — assign-Adapter Schreibseite**
- `execute_action('shift_dates')` mit echtem DB-Write.
- `restore_state()` mit Snapshot-Validierung.
- `subplugintype_coursectrlmod`-Strings in EN/DE ergänzt.
- 10 neue Tests inkl. Round-Trip.

**patch-021 — coursectrlmod_quiz**
- Zweiter Adapter. Fields: `timeopen`, `timeclose`.
- 19 Tests + 1 Discovery-Test.

**patch-022 — coursectrlmod_feedback (Phase-3-Abschluss)**
- Dritter Adapter. Fields: `timeopen`, `timeclose`.
- 20 Tests inkl. Cross-Component-Snapshot-Reject.
- 1 Discovery-Test.

### Phase 4 (patches 023–028)

**patch-023 — Phase-4-Skeleton**
- `core\persistent`-Wrapper: `batch`, `batch_item`, `snapshot`.
- Immutable DTOs: `preview_change`, `validation_result`, `execution_result`.
- Manager-Skelette: `preview_manager`, `batch_manager` (throws coding_exception).
- 20 neue Tests.

**patch-024 — preview_manager produktiv**
- Kursweite Preview-Aggregation: Registry-Routing, Adapter-Batching, Validation.
- Ergebnis: `changes` (preview_change DTOs), `skipped`, `errors`, `summary`.
- 9 Verhaltenstests (ersetzt 2 Skeleton-Tests).

**patch-025 — DRY-Refactoring: shift_dates_executor Trait**
- Extrahiert ~450 Zeilen gemeinsame Pipeline-Logik aus assign/quiz/feedback.
- 4 Hooks: `get_table_name`, `get_field_map_class`, `read_dates_from_record`, `get_record_select_fields`.
- 0 neue Tests (Refactoring-Garantie: alle 58 Adapter-Tests unverändert grün).

**patch-026 — batch_manager produktiv**
- Volle Execute-Pipeline: Batch-Persist → Transaktion → Snapshot → Adapter-Execute → batch_item-Persist → Calendar-Refresh → Event.
- Interface-Erweiterung: `refresh_calendar_for_cmids()` als 14. Methode (No-Op-Default in abstract base).
- Calendar-Refresh in allen 3 Adaptern via `assign_refresh_events` / `quiz_refresh_events` / `feedback_refresh_events`.
- Neuer Event `batch_executed` mit Summary in `other`.
- Contract-Test auf 14 Methoden aktualisiert.
- 12 Verhaltenstests (ersetzt 2 Skeleton-Tests).
- PSR12-Lint-Fixes in batch_test + batch_item_test (`foreach` mit Inline-Array → Variable).

**patch-027 — Lint-Cleanup**
- Lang-Files: Strings alphabetisch sortiert, Section-Kommentare entfernt.
- Test-Files: `@coversDefaultClass` → `@covers`, `stub_test` → `final` + `@coversNothing`.
- `registry_discovery_test.php` verschoben von `tests/` nach `tests/manager/` (Namespace-Match).
- **Hinweis:** Die alte Datei `tests/registry_discovery_test.php` muss manuell gelöscht werden.

**patch-028 — External API**
- `preview_bulk_action`: AJAX-Wrapper um preview_manager, Cap `local/coursectrl:view`.
- `execute_bulk_action`: AJAX-Wrapper um batch_manager, Cap `local/coursectrl:bulkaction`.
- `db/services.php` um beide Endpunkte erweitert.
- 10 Integrationstests (5 preview + 5 execute).
- Live-verifiziert via Browser-Console: Preview liefert 4 Changes für gemischten Kurs.

---

## 2. Test-Bilanz nach patch-028

| Testklasse | Anzahl | Patches |
|---|---|---|
| `activity_adapter_contract_test` | 3 | 009/012/026 |
| `registry_test` | 7 | 010/012 |
| `registry_discovery_test` | 4 | 019/021/022 |
| `entities_test` | 11 | 011/012/013 |
| `inventory_service_test` | 6 | 012/013 |
| `get_inventory_test` | 5 | 014/016 |
| `dashboard_page_test` | 5 | 015 |
| `abstract_activity_adapter_test` | 6 | 017/019 |
| `coursectrlmod_assign\adapter_test` | 19 | 018/019/020 |
| `coursectrlmod_quiz\adapter_test` | 19 | 021 |
| `coursectrlmod_feedback\adapter_test` | 20 | 022 |
| `batch_test` | 4 | 023/026 |
| `batch_item_test` | 3 | 023/026 |
| `snapshot_test` | 3 | 023 |
| `dtos_test` | 6 | 023 |
| `preview_manager_test` | 9 | 024 |
| `batch_manager_test` | 12 | 026 |
| `preview_bulk_action_test` | 5 | 028 |
| `execute_bulk_action_test` | 5 | 028 |
| `stub_test` (legacy) | 4 | 001/027 |
| **Summe** | **156** | |

**Hinweis:** Die Zählung 156 (statt der geschätzten 158) ergibt sich aus der Netto-Rechnung: 148 (vor patch-028) + 10 (neue External-Tests) − 2 (Skeleton-Tests wurden in patch-024/026 bereits ersetzt, nicht in patch-028 nochmals gezählt). Die exakte Zahl kann je nach PHPUnit-Run leicht variieren.

---

## 3. Architekturentscheidungen aus Session 004

| Entscheidung | Details | Patch |
|---|---|---|
| Abstract base statt Trait für Interface-Defaults | `abstract_activity_adapter` als abstrakte Klasse. | 017 |
| Defaults sind „silent" (leere Arrays) | Statt Exceptions bei unimplementierten Methoden. | 017 |
| Anonyme Klasse für In-Test-Helfer | Vermeidet PSR1-Verletzung (zwei Klassen pro Datei). | 019 |
| `MOODLE_INTERNAL` nur in prozeduralen Dateien | Namespaced Single-Class-Files brauchen es nicht. | 019 |
| Adapter macht keinen Capability-Check | Cap-Check eine Schicht höher (External Function). | 020 |
| Snapshot vor Mutation | `describe_instance()` wird VOR `execute_action()` aufgerufen. | 020 |
| Unset = 0 wird nie verschoben | `nullable_zero`-Flag im Field-Map, `reason: 'unset'`. | 020/021/022 |
| Calendar-Sync ist zentrale Bulk-Engine-Aufgabe | `refresh_calendar_for_cmids` pro Adapter, aufgerufen vom `batch_manager`. | 026 |
| Interface-Erweiterung auf 14 Methoden | `refresh_calendar_for_cmids` additiv, No-Op-Default. | 026 |
| Trait `shift_dates_executor` für DRY | 4 Hooks, ~450 Zeilen Duplikation eliminiert. | 025 |
| Persistents via `core\persistent` | Standard-Pattern für Moodle 4.x mit Validation. | 023 |
| DTOs sind separate Klassen, keine Persistents | Transfer ↔ Storage getrennt; DTOs als JSON in Persistents serialisiert. | 023 |
| Preview-Manager und Batch-Manager als getrennte Klassen | Verschiedene Cap-Anforderungen (view vs bulkaction). | 023/024/026 |
| Transaktionaler Block im batch_manager | Snapshot + Execute + batch_item in einer Transaktion; Calendar-Refresh und Event danach. | 026 |
| `noop` → `batch_item::STATUS_SUCCESS` | Noop ist semantisch ein erfolgreicher Run, der nichts geändert hat. | 026 |
| `payloadjson` als PARAM_RAW in External API | Action-abhängige Payload-Shape; Validierung über Adapter-Layer. | 028 |
| `fieldsjson` als PARAM_RAW im Preview-Return | Per-Field-Descriptoren variieren nach Adapter; UI parsed clientseitig. | 028 |
| Execute-Summary wird aus batch_items berechnet | External Function liest Persistents nach Execute, Manager-Vertrag bleibt schlank. | 028 |
| Lang-Strings alphabetisch ohne Section-Kommentare | Moodle-phpcs verlangt strikt alphabetische Sortierung. | 027 |

---

## 4. Finalisierte Artefakte (Stand nach patch-028)

### local_coursectrl (Kern)

| Pfad | Zweck | Patches |
|---|---|---|
| `version.php` | → `2026041012`, Release `0.1.20`. | 028 |
| `index.php` | Dashboard-Einstiegsseite. | 015 |
| `db/access.php` | 6 Capabilities. | 001 |
| `db/services.php` | 3 Services: get_inventory, preview_bulk_action, execute_bulk_action. | 014/028 |
| `db/install.xml` | 7 Tabellen. | 001 |
| `db/subplugins.json` | coursectrlmod → mod/. | 001 |
| `classes/local/contract/activity_adapter.php` | 14-Methoden-Interface. | 009/026 |
| `classes/local/contract/abstract_activity_adapter.php` | Abstrakte No-Op-Basis. | 017/026 |
| `classes/local/contract/shift_dates_executor.php` | Shared shift_dates Pipeline-Trait. | 025 |
| `classes/local/entity/*.php` | Kurs-/Section-/CM-/Text-Entitäten. | 011–013 |
| `classes/local/inventory/*.php` | inventory_service + inventory_snapshot. | 012/013 |
| `classes/local/persistent/batch.php` | Persistent für local_coursectrl_batch. | 023 |
| `classes/local/persistent/batch_item.php` | Persistent für local_coursectrl_batch_item. | 023 |
| `classes/local/persistent/snapshot.php` | Persistent für local_coursectrl_snapshot. | 023 |
| `classes/local/dto/preview_change.php` | Immutable DTO für Vorschau-Änderungen. | 023 |
| `classes/local/dto/validation_result.php` | Immutable DTO für Validierungsergebnis. | 023 |
| `classes/local/dto/execution_result.php` | Immutable DTO für Ausführungsergebnis. | 023 |
| `classes/manager/registry.php` | Adapter-Registry. | 010/012 |
| `classes/manager/preview_manager.php` | Kursweite Preview-Aggregation. | 024 |
| `classes/manager/batch_manager.php` | Kursweite Execute-Pipeline. | 026 |
| `classes/external/get_inventory.php` | External API: Inventar. | 014/015 |
| `classes/external/preview_bulk_action.php` | External API: Preview. | 028 |
| `classes/external/execute_bulk_action.php` | External API: Execute. | 028 |
| `classes/event/batch_executed.php` | Event: Batch ausgeführt. | 026 |
| `classes/output/dashboard_page.php` | Renderable Snapshot→Template. | 015 |
| `classes/output/renderer.php` | Plugin-Renderer. | 015 |
| `classes/plugininfo/coursectrlmod.php` | Subplugintyp-Plugininfo. | 001 |
| `templates/dashboard.mustache` | Server-side Dashboard. | 015 |
| `lang/en/local_coursectrl.php` | EN-Strings (alphabetisch). | 027 |
| `lang/de/local_coursectrl.php` | DE-Strings (alphabetisch). | 027 |

### coursectrlmod_assign / coursectrlmod_quiz / coursectrlmod_feedback

Jeder Adapter hat: `version.php`, `classes/adapter.php` (mit `shift_dates_executor`-Trait + `refresh_calendar_for_cmids`), `classes/field_map.php`, `classes/privacy/provider.php`, `lang/en/*.php`, `lang/de/*.php`, `tests/adapter_test.php`.

| Plugin | Version | Patches |
|---|---|---|
| coursectrlmod_assign | `2026041011` | 018–027 |
| coursectrlmod_quiz | `2026041011` | 021–027 |
| coursectrlmod_feedback | `2026041011` | 022–027 |

### block_coursectrl

| Pfad | Zweck | Patches |
|---|---|---|
| `version.php` | `2026041010`, Dependency local_coursectrl. | 026 |
| Übrige Dateien | Aus Session 001 unverändert. | 001 |

---

## 5. Pflichtenheft-Status nach Phase-4-Abschluss

| Funktionsblock | Status | Phase |
|---|---|---|
| F1 — Kursinventar | ✓ | 2 |
| F2 — Bulk-Auswahl (adapter-seitig + Manager + External API) | ✓ MVP 1 | 4 |
| F3 — Vorschau (adapter-seitig + kursweite Aggregation + External API) | ✓ | 3/4 |
| F4 — Terminänderung `shift_dates` (adapter-seitig + Bulk-Pipeline) | ✓ | 3/4 |
| F4 — Terminänderung `set_dates` | offen | 4-Nachzügler / Phase 9 |
| F5 — Text-Datetime-Erkennung | offen | **Phase 5** |
| F6 — Text-Datetime-Änderung | offen | **Phase 5** |
| F7 — Visualisierung | offen | **Phase 6** |
| F8 — Lernenden-Simulation | offen | **Phase 7** |
| F9 — Konsistenz/Sackgassen | offen | **Phase 8** |
| F10 — Audit/Rollback (adapter-seitig: export+restore) | ✓ | 3 |
| F10 — Audit/Rollback (Batch-Persist + Snapshot-Persist) | ✓ | 4 |
| F10 — Audit/Rollback (rollback_manager + UI) | offen | **Phase 9** |

---

## 6. Bekannte Probleme und Risiken

**Offen, nicht blockierend:**
- **`set_dates`-Aktion:** Im Pflichtenheft Phase 4 vorgesehen, aber für MVP 1 nicht zwingend. Kann als Zwischen-Patch oder in Phase 9 nachgeliefert werden. Die Adapter-Architektur (Trait-Hooks) unterstützt es bereits; es fehlt nur die Aktion im Trait und die Payload-Validierung.
- **Block-Plugin-Linkziel:** Aus Session 003 übernommen. Niedrige Priorität.
- **Label-Inventarisierung:** Tendenz: über `coursectrlmod_label`-Adapter, sobald Phase 5 läuft.
- **UI-Anbindung:** Server-side Bulk-Formular + Preview-Tabelle + Execute-Confirmation fehlen noch. Die External-API-Endpunkte sind aber produktiv und AJAX-fähig — eine AMD-basierte UI kann jederzeit angebunden werden.

**Aus Session 001/002/003 unverändert:**
- Availability-API-Introspektion (Phase 7/8)
- Workshop-Adapter-Vereinfachung (Phase 10)

---

## 7. Versionen

| Plugin | Version nach Session 004 |
|---|---|
| local_coursectrl | `2026041012` |
| block_coursectrl | `2026041010` |
| coursectrlmod_assign | `2026041011` |
| coursectrlmod_quiz | `2026041011` |
| coursectrlmod_feedback | `2026041011` |

Letzter Patch: `patch-028`
Patches in Session 004: 017, 018, 019, 020, 021, 022, 023, 024, 025, 026, 027, 028

---

## 8. Übergabe an Session 005

**Mögliche Einstiegspunkte für Session 005 (Reihenfolge nach Priorität):**

1. **UI-Anbindung (patch-029):** Server-side Bulk-Formular auf Dashboard oder eigener Seite. Selektoren für Action + Delta + cmid-Liste. Preview-Tabelle als Mustache-Template. Execute-Bestätigung. Nutzt die AJAX-Endpunkte aus patch-028. Kann server-side (kein AMD nötig) oder mit AMD-Enhancement gebaut werden.

2. **Phase 5 — Text-Datetime-Erkennung:** `text_datetime_extractor`, `text_datetime_parser`, `text_hit_classifier`. RegEx-basiert für DE/EN-Datumsformate. Review-UI. Persistierung in `local_coursectrl_text_hit`.

3. **Phase 6 — Visualisierung:** Timeline/Gantt-Darstellung, Abhängigkeitsgraph, Konfliktmarkierungen. Clientseitig mit einer JS-Library (vis.js, d3.js, oder Moodle-internes Chart.js).

4. **Phase 9 — Rollback:** `rollback_manager` der Snapshots aus der `local_coursectrl_snapshot`-Tabelle liest und `adapter::restore_state()` aufruft. UI für Batch-History + Rollback-Bestätigung.

**Mitzugebende Dateien für Session 005:**
- Dieses Dokument
- Die aktuelle Code-Base als ZIP (beide Plugins)
- Optional: Pflichtenheft (ist auch im Repo unter `docs/`)

---

**Sitzung 004 abgeschlossen. Phasen 0–4 für MVP 1 vollständig. Bereit für Session 005.**
