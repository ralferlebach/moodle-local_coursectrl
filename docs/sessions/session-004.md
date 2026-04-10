# CCH Kontext-Dokument
Erstellt: 2026-04-10
Sitzung Nr.: 004 — **gestartet**
Vorgängersitzung: 003 (Phasen 2 + 3 abgeschlossen, patches 014–022)

---

## 1. Aktueller Projektstand bei Session-Start

**Phase 2 — Inventar und Selektionslogik:** vollständig abgeschlossen (Session 003).
**Phase 3 — Adapter-Basis und erste Aktivitätstypen:** vollständig abgeschlossen (Session 003 + Anfang Session 004, patches 017–022).

**Was in Sitzung 003 (zweiter Teil) und Sitzung 004 (Vorlauf) erledigt wurde:**

**patch-017 — abstract_activity_adapter**
- Neue produktive Basisklasse `local_coursectrl\local\contract\abstract_activity_adapter` mit No-Op-Defaults für 12 der 13 Methoden; nur `component()` bleibt abstract.
- Defaults: `is_available()` → `true`, alle 11 anderen Methoden → leeres Array.
- Test-Fixture `local_coursectrl_fake_adapter_base` migriert auf Vererbung der neuen Basisklasse.
- 6 Unit-Tests in `tests/abstract_activity_adapter_test.php`.

**patch-018 — coursectrlmod_assign (read + preview)**
- Erstes echtes Subplugin unter `local/coursectrl/mod/assign/`.
- `adapter.php`, `field_map.php` (4 Datumsfelder: `duedate`, `allowsubmissionsfromdate`, `cutoffdate`, `gradingduedate`), `null_provider`, EN/DE-Lang.
- Implementiert: `component`, `is_available`, `get_supported_actions` → `['shift_dates']`, `get_supported_fields`, `get_instances_for_course`, `describe_instance`, `validate_action`, `preview_action`, `export_state`.
- `execute_action` und `restore_state` zunächst bewusst als geerbte No-Ops.
- 9 PHPUnit-Tests gegen den echten `mod_assign`-Generator.

**patch-019 — Lint-Fixes + Registry-Auto-Discovery-Test**
- 2 Errors aus phpcs behoben: Klasse ohne Docblock-Description (`abstract_activity_adapter_test`, `adapter_test`); zwei Klassen in einer Datei (Helfer durch anonyme Klasse in privater Factory-Methode ersetzt).
- 5 Warnings aus phpcs behoben: `defined('MOODLE_INTERNAL') || die();` aus 6 namespaced Single-Class-Dateien entfernt.
- Neue `tests/registry_discovery_test.php` mit 2 Tests, die belegen, dass die Registry den `coursectrlmod_assign`-Adapter ohne DI-Override über `core_plugin_manager` findet.

**patch-020 — assign-Adapter Schreibseite**
- `subplugintype_coursectrlmod` und `subplugintype_coursectrlmod_plural`-Strings in EN/DE ergänzt (vom Plugin-Manager benötigt).
- `execute_action('shift_dates')` mit echtem `$DB->update_record('assign', …)`: Validierung zuerst, Snapshot vor Mutation, `0`-Werte als „unset" geschützt, `delta=0` → `noop` ohne DB-Write, per-cmid-Status `ok` / `noop` / `failed` mit Snapshot.
- `restore_state()` mit strukturierter Snapshot-Validierung und Fehlercodes `invalid_component` / `invalid_snapshot` / `cmid_unresolved` / `no_restorable_fields` / `db_write_failed`.
- Capability `local/coursectrl:bulkaction` war bereits seit Session 001 in `db/access.php` definiert — keine Schemaänderung nötig, der Adapter macht bewusst **keinen** Cap-Check (Layering: Sache der Bulk-Engine).
- 10 neue Tests, darunter Round-Trip-Test (execute → restore → DB exakt zurück), Snapshot-vor-Mutation-Test, `noop`-Test, Validation-First-Test.

**patch-021 — coursectrlmod_quiz**
- Zweites Subplugin nach demselben Schema. Datumsfelder: `timeopen`, `timeclose`. Dauer-Felder `timelimit` und `graceperiod` bewusst **nicht** im Field-Map (keine Datumsfelder).
- 19 PHPUnit-Tests parallel zu assign + 1 neuer Discovery-Test für quiz.
- Versions-Sync-Bump auch von assign-Subplugin.

**patch-022 — coursectrlmod_feedback (Phase-3-Abschluss)**
- Dritter und letzter MVP-1-Adapter. Datumsfelder: `timeopen`, `timeclose` (gleiche Namen wie quiz, andere Tabelle, andere Semantik).
- 19 Standard-Tests + 1 zusätzlicher **Cross-Component-Test** `test_restore_state_rejects_quiz_snapshot`, der belegt, dass der `component`-Marker im Snapshot Cross-Module-Restores trotz Field-Name-Kollision verhindert.
- Neuer Discovery-Test für feedback.
- Sync-Bumps von assign- und quiz-Subplugins.

---

## 2. Test-Bilanz nach patch-022

| Testklasse | Anzahl | Patches |
|---|---|---|
| `activity_adapter_contract_test` | 3 | 009/012 |
| `registry_test` | 7 | 010/012 |
| `entities_test` | 11 | 011/012/013 |
| `inventory_service_test` | 6 | 012/013 |
| `get_inventory_test` | 5 | 014/016 |
| `dashboard_page_test` | 5 | 015 |
| `abstract_activity_adapter_test` | 6 | 017/019 |
| `registry_discovery_test` | 4 | 019/021/022 |
| `coursectrlmod_assign\adapter_test` | 19 | 018/020 |
| `coursectrlmod_quiz\adapter_test` | 19 | 021 |
| `coursectrlmod_feedback\adapter_test` | 20 | 022 |
| `stub_test` (legacy) | 4 | 001 |
| **Summe** | **109** | |

Erwartung nach Install des kompletten patch-022-Stands: **109 Tests grün, 0 Failures.**

(Hinweis: Eine frühere Schätzung lag bei 99 — der Discovery-Test wuchs mit jedem neuen Adapter mit, und der feedback-Adapter hat 20 statt 19 Tests wegen des Cross-Component-Schutzes.)

---

## 3. Architekturentscheidungen aus Session 003 (zweiter Teil) und Session-004-Vorlauf

| Entscheidung | Details | Patch |
|---|---|---|
| Abstract base statt Trait | `abstract_activity_adapter` als abstrakte Klasse, nicht als Trait — erlaubt Vererbung über `is_subclass_of()` und macht Reflection-Tests trivial. | 017 |
| Defaults sind „silent" | `validate_action`/`preview_action`/`execute_action` liefern per Default leere Arrays statt zu werfen — sicherer für die Bulk-Engine, die einen Adapter ohne `get_supported_actions()`-Eintrag ohnehin nie aufruft. | 017 |
| Anonyme Klasse für In-Test-Helfer | Statt zwei benannter Klassen pro Testdatei (PSR1-Verletzung) eine anonyme Klasse via private Factory-Methode. | 019 |
| `MOODLE_INTERNAL` nur in prozeduralen Dateien | Namespaced Single-Class-Dateien (Adapter, Field-Map, Privacy-Provider, Test-Klassen ohne Side-Effects) dürfen kein `defined('MOODLE_INTERNAL')` enthalten — phpcs `MoodleInternalNotNeeded`. `version.php`, `lang/*/*.php`, `db/*.php` bleiben damit ausgestattet. | 019 |
| **Adapter macht keinen Capability-Check** | `local/coursectrl:bulkaction` wird **eine Layer höher** geprüft (Bulk-Engine, External Function). Der Adapter ist eine Low-Level-Write-API und vertraut seinem Aufrufer. Doppelte Cap-Quellen würden Drift erzeugen. Im Adapter-PHPDoc explizit dokumentiert. | 020 |
| **Snapshot vor Mutation** | `execute_action` ruft `describe_instance` auf und legt das Ergebnis als Snapshot ins Item-Result, **bevor** der DB-Write passiert. Test `test_execute_returns_snapshot_with_old_values` schützt die Reihenfolge. | 020 |
| **Unset = 0 wird nie verschoben** | mod_assign / mod_quiz / mod_feedback verwenden alle `0` als „nicht gesetzt". `0 + delta` würde Daten aus 1970 erzeugen. Alle drei Adapter überspringen `=== 0` mit Reason `'unset'`. Field-Map-Flag `nullable_zero => true`. | 020/021/022 |
| **`noop` statt `skipped`** | `delta=0` oder „nichts zu ändern" liefert Status `noop`, nicht `skipped`. Trennung erlaubt der Bulk-Engine später, „bewusst nichts geändert" von „aller Felder unset" zu unterscheiden. | 020 |
| **Calendar-Sync ist Aufgabe der Bulk-Engine** | Direkter `update_record('assign'/'quiz'/'feedback', …)` umgeht die Calendar-Event-Pipelines der Module. Der Phase-4-`batch_manager` muss nach jedem erfolgreichen `execute_action` einen zentralen Calendar-Refresh anstoßen. **Nicht** in jeden Adapter kopieren — zentrales Problem, zentrale Lösung. In allen drei Adapter-Klassendocblocks dokumentiert. | 020/021/022 |
| **Snapshot-Component-Marker als Cross-Module-Schutz** | Quiz und feedback teilen die Feldnamen `timeopen`/`timeclose`. Der `component`-Eintrag im Snapshot wird in `restore_state` als erstes geprüft und verhindert Cross-Module-Restores. Belegt durch `test_restore_state_rejects_quiz_snapshot` im feedback-Adapter. | 022 |
| **DRY-Refactoring zurückgestellt** | Drei Adapter teilen ~80 % Code. Eine Trait-Extraktion `shift_dates_executor` mit `get_table_name()` und `read_dates_from_record()` als abstrakte Hooks ist konkret machbar (Adapter würden auf ~60 Zeilen schrumpfen). **Aber:** Refactoring nach n=3 ist eine bessere Wette mit echten Aufrufmustern aus der Bulk-Engine als ohne. Entscheidung verschoben auf nach den ersten Phase-4-Patches. | 022 |
| **Subplugin-Versions immer im Sync** | Bei jedem Patch werden alle aktiven Plugins (`local`, `block`, alle `coursectrlmod_*`) auf dieselbe Versionsnummer gehoben — auch wenn der Code unverändert ist. Damit bleibt die Plugin-Manager-Sicht konsistent. | 020/021/022 |

---

## 4. Finalisierte Artefakte (Stand nach patch-022)

### local_coursectrl

| Pfad | Zweck | Patches |
|---|---|---|
| `version.php` | → `2026041006`, Release `0.1.14`. | 017–022 |
| `index.php` | Dashboard-Einstiegsseite mit Capability-Check und Renderer-Aufruf. | 015 |
| `db/access.php` | 6 Capabilities; `local/coursectrl:bulkaction` wartet auf Verwendung durch die Phase-4-Bulk-Engine. | 001 |
| `db/services.php` | Service-Registrierung `local_coursectrl_get_inventory`. | 014/015 |
| `db/install.xml`, `db/subplugins.json` | Aus Session 001 unverändert. | 001 |
| `classes/local/contract/activity_adapter.php` | 13-Methoden-Interface, gefroren. | 009/012 |
| `classes/local/contract/abstract_activity_adapter.php` | Abstrakte No-Op-Basis für Subplugin-Adapter. | 017/019 |
| `classes/manager/registry.php` | Adapter-Registry mit DI-Override. | 010/012 |
| `classes/local/entity/*.php` | Kurs-/Section-/CM-/Text-Entitäten. | 011/012/013 |
| `classes/local/inventory/inventory_snapshot.php` | Snapshot-Value-Object. | 012/013 |
| `classes/local/inventory/inventory_service.php` | Kurs → Snapshot, einziger DB-Zugriff in Phase 2. | 012 |
| `classes/external/get_inventory.php` | External-API-Wrapper, Cap `local/coursectrl:view`. | 014/015 |
| `classes/output/dashboard_page.php` | Renderable Snapshot→Template-Context. | 015 |
| `classes/output/renderer.php` | Plugin-Renderer mit `render_dashboard_page()`. | 015 |
| `classes/plugininfo/coursectrlmod.php` | Subplugintyp-Plugininfo. | 001 |
| `templates/dashboard.mustache` | Server-side Bootstrap-Dashboard. | 015 |
| `lang/en/local_coursectrl.php` | EN-Strings inkl. `dashboard_*`, `subplugintype_coursectrlmod*`. | 015/020 |
| `lang/de/local_coursectrl.php` | DE-Strings inkl. `dashboard_*`, `subplugintype_coursectrlmod*`. | 015/020 |
| `tests/activity_adapter_contract_test.php` | 3 Reflection-Tests. | 009/012 |
| `tests/registry_test.php` | 7 Registry-Tests (DI-Override). | 010/012 |
| `tests/registry_discovery_test.php` | 4 Live-Discovery-Tests (assign/quiz/feedback/count). | 019/021/022 |
| `tests/abstract_activity_adapter_test.php` | 6 Unit-Tests. | 017/019 |
| `tests/entities_test.php` | 11 Entity-Tests. | 011/012/013 |
| `tests/inventory_service_test.php` | 6 Inventory-Service-Tests. | 012/013 |
| `tests/external/get_inventory_test.php` | 5 External-API-Tests. | 014/016 |
| `tests/output/dashboard_page_test.php` | 5 Renderable-Tests. | 015 |
| `tests/fixtures/fake_adapter_*.php` | 6 PSR1-Adapter-Fixtures. | 012/013/019 |
| `tests/stub_test.php` | 4 legacy Smoke-Tests. | 001 |

### local_coursectrl/mod/assign (coursectrlmod_assign)

| Pfad | Zweck | Patches |
|---|---|---|
| `version.php` | → `2026041006`, `0.1.14`. | 018/020/021/022 |
| `classes/adapter.php` | Vollständiger Adapter inkl. `execute_action` und `restore_state`. | 018/019/020 |
| `classes/field_map.php` | 4 Datumsfelder. | 018/019 |
| `classes/privacy/provider.php` | `null_provider`. | 018/019 |
| `lang/en/coursectrlmod_assign.php` | EN-Strings. | 018 |
| `lang/de/coursectrlmod_assign.php` | DE-Strings. | 018 |
| `tests/adapter_test.php` | 19 Tests gegen echtes `mod_assign`. | 018/019/020 |

### local_coursectrl/mod/quiz (coursectrlmod_quiz)

| Pfad | Zweck | Patches |
|---|---|---|
| `version.php` | → `2026041006`, `0.1.14`. | 021/022 |
| `classes/adapter.php` | Vollständiger Adapter. | 021 |
| `classes/field_map.php` | 2 Datumsfelder (`timeopen`, `timeclose`); `timelimit`/`graceperiod` bewusst ausgeschlossen. | 021 |
| `classes/privacy/provider.php` | `null_provider`. | 021 |
| `lang/en/coursectrlmod_quiz.php` | EN-Strings. | 021 |
| `lang/de/coursectrlmod_quiz.php` | DE-Strings. | 021 |
| `tests/adapter_test.php` | 19 Tests gegen echtes `mod_quiz`. | 021 |

### local_coursectrl/mod/feedback (coursectrlmod_feedback)

| Pfad | Zweck | Patches |
|---|---|---|
| `version.php` | → `2026041006`, `0.1.14`. | 022 |
| `classes/adapter.php` | Vollständiger Adapter. | 022 |
| `classes/field_map.php` | 2 Datumsfelder (`timeopen`, `timeclose`). | 022 |
| `classes/privacy/provider.php` | `null_provider`. | 022 |
| `lang/en/coursectrlmod_feedback.php` | EN-Strings. | 022 |
| `lang/de/coursectrlmod_feedback.php` | DE-Strings. | 022 |
| `tests/adapter_test.php` | 20 Tests inkl. Cross-Component-Reject. | 022 |

### block_coursectrl

| Pfad | Zweck | Patches |
|---|---|---|
| `version.php` | Sync-Bumps, Dependency `local_coursectrl 2026041006`. | 017–022 |
| Übrige Dateien | Aus Session 001 unverändert. | 001 |

---

## 5. Pflichtenheft-Status nach Phase-3-Abschluss

| Funktionsblock | Status | Verantwortliche Phase |
|---|---|---|
| F1 — Kursinventar | ✓ | Phase 2 |
| F2 — Bulk-Auswahl | offen | **Phase 4 (jetzt)** |
| F3 — Vorschau (adapter-seitig) | ✓ je Adapter | Phase 3 ✓ |
| F3 — Vorschau (kursweite Aggregation) | offen | **Phase 4 (jetzt)** |
| F4 — Terminänderung (adapter-seitig) | ✓ je Adapter | Phase 3 ✓ |
| F4 — Terminänderung (Bulk-Pipeline) | offen | **Phase 4 (jetzt)** |
| F5 — Text-Datetime-Erkennung | offen | Phase 5 |
| F6 — Text-Datetime-Änderung | offen | Phase 5 |
| F7 — Visualisierung | offen | Phase 6 |
| F8 — Lernenden-Simulation | offen | Phase 7 |
| F9 — Konsistenz/Sackgassen | offen | Phase 8 |
| F10 — Audit/Rollback (export+restore adapter-seitig) | ✓ je Adapter | Phase 3 ✓ |
| F10 — Audit/Rollback (Batch-/Snapshot-Persistierung) | offen | **Phase 4 (jetzt) + Phase 9** |

---

## 6. Bekannte Probleme und Risiken

**Offen, blockierend für Phase 4:**
- Keine.

**Offen, nicht blockierend:**
- **Calendar-Sync nach DB-Writes:** Drei Adapter umgehen mod_*-eigene Calendar-Pipelines. Muss in `batch_manager` als zentraler Schritt nach erfolgreichen execute_actions implementiert werden. **Bitte als verbindliche Anforderung in Phase 4 adressieren.**
- **DRY-Refactoring der drei Adapter:** Trait `shift_dates_executor` ist konkret machbar, wurde aber bewusst zurückgestellt. Entscheidung nach den ersten Phase-4-Patches treffen, wenn die Bulk-Engine echte Aufrufer liefert.
- **Block-Plugin-Linkziel:** Aus Session 003 übernommen — der bestehende `block_coursectrl.php` rendert einen Link auf das Plugin. In Session 003/004 nicht eingelesen. Niedrige Priorität.
- **Label-Inventarisierung:** Phase 2 inventarisiert Labels als CMs, nicht ihre Freitexte. Tendenz: über `coursectrlmod_label`-Adapter, sobald Freitext-Engine in Phase 5 läuft.

**Aus Session 001/002/003 unverändert:**
- Availability-API-Introspektion (Phase 7/8)
- Freitext-Datumserkennung via RegEx (Phase 5)
- Workshop-Adapter-Vereinfachung (Phase 10)

**Behoben in Session 003 (zweiter Teil) und Session-004-Vorlauf:**
- ~~Phase 3 Adapter-Basis~~ → patch-017
- ~~Erster produktiver Adapter (assign read+preview)~~ → patch-018
- ~~Lint-Errors phpcs~~ → patch-019
- ~~Fehlende Subplugin-Lang-Strings~~ → patch-020
- ~~Schreibseite des assign-Adapters~~ → patch-020
- ~~Zweiter produktiver Adapter (quiz)~~ → patch-021
- ~~Dritter produktiver Adapter (feedback)~~ → patch-022
- ~~Cross-Component-Snapshot-Schutz belegen~~ → patch-022

---

## 7. Versionen

| Plugin | Version nach patch-022 |
|---|---|
| local_coursectrl | `2026041006` |
| block_coursectrl | `2026041006` |
| coursectrlmod_assign | `2026041006` |
| coursectrlmod_quiz | `2026041006` |
| coursectrlmod_feedback | `2026041006` |

Letzter Patch dieser Sitzungsphase: `patch-022`
Patches in Session 003/004-Vorlauf: 014, 015, 016, 017, 018, 019, 020, 021, 022

---

## 8. Phase-4-Plan (Bulk-Engine)

**Ziel der Phase laut Pflichtenheft:** sichere Termin-Massenänderung. Strukturierte Datumsänderung. Vorschau und Snapshots in der Persistenzschicht.

**Phasenarbeitspakete laut Blueprint Abschnitt 6 / Phase 4:**

1. `preview_manager` implementieren
2. `batch_manager` implementieren
3. `rollback_manager` vorbereiten
4. Aktion `shift_dates` als kursweite Pipeline (nicht nur adapter-seitig)
5. Aktion `set_dates` implementieren
6. Snapshot-Erzeugung in `local_coursectrl_snapshot`-Tabelle integrieren
7. Vorschau-Tabellen aufbauen
8. Ausführungsworkflow absichern (Cap-Check `local/coursectrl:bulkaction`, Locking, Eventing)
9. Ergebnisreporting ergänzen
10. Calendar-Sync nach erfolgreichen execute_actions zentral lösen

**Vorgesehene Patches in Session 004 (grobe Reihenfolge):**

- **patch-023 — Phase-4-Skeleton:** Manager-Klassen `preview_manager`, `batch_manager` als Skelette mit DI-fähigen Konstruktoren. DTO-Klassen `preview_change`, `execution_result`, `validation_result`. Persistents `batch` und `batch_item` (die DB-Tabellen `local_coursectrl_batch` und `local_coursectrl_batch_item` existieren bereits seit Session 001 in `db/install.xml`). Tests gegen die Persistents.

- **patch-024 — preview_manager produktiv:** Kursweite Aggregation der Adapter-Previews. Input: `courseid`, `action`, `payload`, `cmid[]`. Workflow: registry → adapter pro cmid → adapter::preview_action → Aggregation in `preview_change`-DTOs. Tests mit allen drei Adaptern in einem Kurs.

- **patch-025 — batch_manager + Snapshot-Persistenz:** `execute()`-Methode mit Cap-Check `local/coursectrl:bulkaction`, transaktionalem Snapshot-Persist in `local_coursectrl_snapshot`, dann Adapter-execute-Aufrufe, Eventing über `\local_coursectrl\event\batch_executed`. Calendar-Refresh als zentraler Schritt.

- **patch-026 — External-API-Endpunkte:** `preview_bulk_action` und `execute_bulk_action` als External Functions, Cap-geprüft (`local/coursectrl:view` für Preview, `local/coursectrl:bulkaction` für Execute), strukturierte Schemata.

- **patch-027 — UI-Anbindung Bulk:** Server-side Bulk-Aktionsformular auf der Dashboard-Seite (oder neue Seite). Selektoren für action + delta + cmid-Liste. Preview-Tabelle als Mustache-Template. Ausführungs-Bestätigung. Verwendung der External-API-Endpunkte aus patch-026.

**DRY-Refactoring-Entscheidungspunkt:** **vor patch-023** noch nicht. **Nach patch-024** prüfe ich, ob die echten Aufrufmuster der Bulk-Engine das Trait-Schema rechtfertigen, und liefere ggf. ein dediziertes Refactoring-Patch (`patch-024b` oder als Teil von patch-025).

**Mitzugebende Dateien für die nächste Session:**
- Dieses Dokument
- `local/coursectrl/db/install.xml` (für Verifikation der Batch- und Snapshot-Tabellenfelder)
- `local/coursectrl/classes/local/contract/activity_adapter.php` (Referenz für die 13 Methoden)
- Optional: einer der drei produktiven Adapter als Referenz für die Adapter-Result-Strukturen

---

**Sitzung 003 + Session-004-Vorlauf vollständig. Phasen 0–3 abgeschlossen. Bereit für Phase 4 / Bulk-Engine.**
