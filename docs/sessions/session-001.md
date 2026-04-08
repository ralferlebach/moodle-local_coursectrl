# CCH Kontext-Dokument
Erstellt: 2026-04-08
Sitzung Nr.: 001

---

## 1. Aktueller Projektstand

**Aktuelle Phase:** Phase 0 (abgeschlossen) + Phase 1 – Skeleton und Infrastruktur (begonnen)

**Abgeschlossene Phasen:**
- Phase 0: Projektvision, Namenskonventionen, MVP-Scope, Arbeitsregeln und Session-Workflow final abgestimmt

**Was in dieser Sitzung erledigt wurde:**
- Pflichtenheft und Blueprint analysiert und auf Umsetzbarkeit bewertet
- Technische Risiken identifiziert (Availability-Simulation, Freitext-Erkennung, Workshop-Adapter)
- Session-Workflow definiert: Session-Ende-Prompt, Session-Start-Prompt
- Formale Spielregeln der Zusammenarbeit festgelegt (Patch-ZIP, Versionierung, Diagnose-Skripte)
- Plugin-Stubs für `local_coursectrl` und `block_coursectrl` erstellt (sauber installierbar)
- Vollständiges Datenbankschema (alle 7 Tabellen) in `db/install.xml` definiert
- Capability-Konzept in `db/access.php` für beide Plugins definiert
- Subplugin-Mechanismus via `db/subplugins.json` eingerichtet
- `mod/`-Verzeichnis als Subplugin-Root angelegt
- Sprachstrings (EN + DE) für beide Plugins angelegt
- Smoke-Tests (PHPUnit) für beide Plugins angelegt
- CI-Workflow für beide Repos erstellt (Moodle 4.5–5.2, PHP 8.2–8.4, MariaDB + PostgreSQL, Behat parallel zu PHPUnit)
- Patch-001.zip erstellt und übergeben

---

## 2. Finalisierte Artefakte

### local_coursectrl

| Pfad | Zweck | Einschränkungen / TODOs |
|---|---|---|
| `version.php` | Plugin-Metadaten, requires Moodle 4.5 | – |
| `settings.php` | Admin-Settings-Stub | Inhalt folgt Phase 2 |
| `index.php` | Einstiegsseite (Stub-Weiterleitung) | Wird in Phase 2 durch Dashboard ersetzt |
| `db/access.php` | 6 Capabilities definiert | Vollständig für MVP 1 |
| `db/install.xml` | Alle 7 Tabellen mit Feldern, Keys, Indizes | Schema verbindlich; nur Ergänzungen per upgrade.php |
| `db/subplugins.json` | Subplugintyp `coursectrl_mod` → `local/coursectrl/mod` | – |
| `lang/en/local_coursectrl.php` | Englische Strings | Werden laufend ergänzt |
| `lang/de/local_coursectrl.php` | Deutsche Strings | Werden laufend ergänzt |
| `mod/README.md` | Platzhalter für Subplugin-Verzeichnis | Wird mit ersten Adaptern in Phase 3 befüllt |
| `tests/stub_test.php` | 4 Smoke-Tests: Version, Capabilities, Tabellen, Strings | Wird in Phase 2 durch Inventar-Tests erweitert |
| `.github/workflows/moodle-ci.yml` | CI für local_coursectrl-Repo | MOODLE_502_STABLE erwartet ~Mai 2026 |

### block_coursectrl

| Pfad | Zweck | Einschränkungen / TODOs |
|---|---|---|
| `version.php` | Plugin-Metadaten, dependency auf local_coursectrl 2026040800 | – |
| `block_coursectrl.php` | Block-Klasse mit Link-Render und Capability-Check | Inhalt folgt Phase 2 (Templates, AMD) |
| `db/access.php` | addinstance / myaddinstance Capabilities | – |
| `lang/en/block_coursectrl.php` | Englische Strings | – |
| `lang/de/block_coursectrl.php` | Deutsche Strings | – |
| `tests/stub_test.php` | 3 Smoke-Tests: Version, Klasse, Capabilities, Strings | – |
| `.github/workflows/moodle-ci.yml` | CI für block_coursectrl-Repo inkl. local_coursectrl als Dependency | `CCH_CORE_REPO` muss auf korrekten GitHub-Pfad gesetzt werden |

---

## 3. Offene Arbeitspakete (priorisiert)

1. **`CCH_CORE_REPO` in block_coursectrl CI setzen** – `YOUR_GITHUB_ORG` durch tatsächlichen GitHub-Org/User ersetzen (1-Minuten-Aufgabe, manuell im Repo)

2. **Registry-Mechanismus** (`classes/manager/registry.php`) – Lädt und verwaltet alle verfügbaren `coursectrl_mod_*`-Adapter; Voraussetzung für Phase 3

3. **Inventory Service** (`classes/local/inventory/inventory_service.php`) – Kernstück von Phase 2; inventarisiert Kurs, Sections, CMs, Labels, Texte in normalisiertem Modell

4. **Entity-Klassen** (`classes/local/entity/*.php`) – `course_item`, `section_item`, `cm_item`, `text_item`; werden vom Inventory Service befüllt

5. **AJAX-Endpunkt `get_inventory`** (`classes/external/get_inventory.php`) – Externe API für die UI; setzt Entity-Klassen voraus

6. **Inventar-UI** (Dashboard, Selektor) – `templates/dashboard.mustache`, `templates/selector.mustache`, `amd/src/dashboard.js`, `amd/src/selector.js`

7. **Adapter-Interface finalisieren** (`classes/local/contract/activity_adapter.php`) – Interface-Signatur ist im Blueprint definiert; muss als PHP-Interface-Datei ausimplementiert werden

8. **Erster Adapter: `coursectrl_mod_assign`** – Adapter-Basisklasse + assign-spezifische Implementierung; Voraussetzung für Phase 4

---

## 4. Architekturentscheidungen (verbindlich)

| Entscheidung | Details |
|---|---|
| Plugin-Aufteilung | `local_coursectrl` = gesamte Fachlogik; `block_coursectrl` = reiner UI-Einstieg, keine eigene Logik |
| Subplugintyp | `coursectrl_mod` unter `local/coursectrl/mod/`; gesteuert via `db/subplugins.json` |
| Namensraum | PHP-Klassen: `local_coursectrl\[subnamespace]`; Tests: `local_coursectrl\tests` |
| Adapter-Interface | Signatur lt. Blueprint verbindlich (11 Methoden); kann um optionale Methoden erweitert werden, bestehende Signaturen sind eingefroren |
| DB-Schema | Alle 7 Tabellen in `install.xml` definiert; Änderungen ausschließlich über `upgrade.php` (nie durch Neudefinition in install.xml nach erstem Release) |
| Capabilities | 6 Capabilities in `local_coursectrl`; Block-Capabilities nur `addinstance`/`myaddinstance` |
| Moodle-Mindestversion | 4.5 (`requires = 2024042200`) |
| PHP-Mindestversion | 8.2 (kein 8.1-Support) |
| Datumserkennung im Text | In MVP: ausschließlich RegEx, keine externe KI/NLP-Bibliothek |
| Availability/Reachability | Strategie wird in Phase 7/8 vor Ort mit Moodle-Core-Code ausgehandelt; keine voreilige Abstraktion |
| Workshop-Adapter | Komplexitätsreduzierend anlegen (Anlage wie andere Adapter, Phasenlogik vereinfacht) |
| Diagnose-Skripte | Nur im Chat-Fenster ausgeben, nie als Download |
| Versionierung | `version.php`: YYYYMMDDNN; steigt mit jeder größeren Iteration |
| Commit-Format | Eine englische Zeile + max. 5 Stichpunkte |
| Patch-Lieferung | Nummerierte ZIP (`patch-NNN.zip`) mit nur geänderten Dateien, beide Plugins in korrekter Ordnerstruktur |
| Externe Composer-Deps | Keine ohne vorherige explizite Absprache |

---

## 5. Kritische Abhängigkeiten

```
activity_adapter.php (Interface)
    └── Alle coursectrl_mod_* Adapter
            └── registry.php
                    └── inventory_service.php
                            └── get_inventory.php (External API)
                                    └── dashboard.js / selector.js (UI)

install.xml (DB-Schema)
    └── Alle Persistent-Klassen (batch.php, snapshot.php, ...)
            └── batch_manager.php / rollback_manager.php
                    └── preview_manager.php
                            └── preview.php (UI-Seite)
```

**Reihenfolge für Phase 2/3:**
1. `activity_adapter.php` Interface → 2. `registry.php` → 3. Entity-Klassen → 4. `inventory_service.php` → 5. External API → 6. UI

---

## 6. Bekannte Probleme und Risiken

| Problem | Risiko | Status |
|---|---|---|
| `MOODLE_502_STABLE` existiert noch nicht | CI schlägt für 5.2 fehl bis Branch veröffentlicht (~Mai 2026) | Bekannt, akzeptiert |
| `CCH_CORE_REPO` in block CI ist Platzhalter | block_coursectrl CI funktioniert erst nach manueller Anpassung | Offen – sofort beheben |
| Availability-API-Introspektion | Moodle-interne Condition-Evaluierung ist nicht öffentlich dokumentiert; muss in Phase 7/8 durch direkten Code-Blick erschlossen werden | Offen, zurückgestellt |
| Freitext-Datumserkennung (RegEx) | Mehrdeutige Formate und Sprachmix (DE/EN) erfordern sorgfältige Muster-Entwicklung | Offen, Phase 5 |
| Workshop-Phasenmodell | Unklare Anforderung; wird vereinfacht implementiert | Zurückgestellt bis Phase 10 |
| Smoke-Test `test_plugin_version_is_set` | Gibt nur leeren String zurück wenn Plugin per `add_plugin` und nicht via `install.xml` initialisiert wird – zu prüfen | Zu verifizieren in erster CI-Ausführung |

---

## 7. GitHub-Repositorien

| Plugin | URL | Hauptbranch |
|---|---|---|
| local_coursectrl | *(vom Nutzer anzugeben)* | main |
| block_coursectrl | *(vom Nutzer anzugeben)* | main |

Dokumentationsverzeichnis in local_coursectrl: `docs/`
Session-Dokumente: `docs/sessions/session-NNN.md`
Pflichten-/Lastenheft: `docs/` (bereits abgelegt)
Prompt-Templates: `docs/` (bereits abgelegt)

Letzte bekannte Commit-Message: `Initial plugin stubs: local_coursectrl and block_coursectrl install cleanly`

---

## 8. Für die nächste Sitzung mitzugebende Dateien

| Datei | Warum relevant |
|---|---|
| `local/coursectrl/db/install.xml` | DB-Schema als Referenz für alle Persistent-Klassen |
| `local/coursectrl/db/access.php` | Capability-Namen für Rechteprüfungen in Services |
| `local/coursectrl/db/subplugins.json` | Subplugin-Struktur bestätigen |
| `local/coursectrl/lang/en/local_coursectrl.php` | Bestehende Strings prüfen vor Ergänzung |
| `local/coursectrl/tests/stub_test.php` | Referenz für Teststil und Namensräume |
| Dieses Dokument (`docs/sessions/session-001.md`) | Vollständiger Kontext |

**Optional aber hilfreich:**
- Moodle-Core-Datei `availability/classes/tree.php` (per Prompt verlinken wenn Phase 7 beginnt)
- Moodle-Core-Datei `lib/completionlib.php` (für Completion-Logik in Phase 7)
