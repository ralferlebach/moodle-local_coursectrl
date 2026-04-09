# CCH Kontext-Dokument
Erstellt: 2026-04-08
Zuletzt aktualisiert: 2026-04-09 (Korrekturen aus Patches 002–008)
Sitzung Nr.: 001

---

## 1. Aktueller Projektstand

**Aktuelle Phase:** Phase 1 – Skeleton und Infrastruktur (abgeschlossen)

**Abgeschlossene Phasen:**
- Phase 0: Projektvision, Namenskonventionen, MVP-Scope, Arbeitsregeln und Session-Workflow final abgestimmt
- Phase 1: Plugin-Stubs, DB-Schema, CI-Pipeline – alle Smoke-Tests grün, Install fehlerfrei

**Was in dieser Sitzung erledigt wurde:**
- Pflichtenheft und Blueprint analysiert und auf Umsetzbarkeit bewertet
- Technische Risiken identifiziert (Availability-Simulation, Freitext-Erkennung, Workshop-Adapter)
- Session-Workflow definiert: Session-Ende-Prompt, Session-Start-Prompt
- Formale Spielregeln der Zusammenarbeit festgelegt (Patch-ZIP, Versionierung, Diagnose-Skripte)
- Plugin-Stubs für `local_coursectrl` und `block_coursectrl` erstellt (sauber installierbar)
- Vollständiges Datenbankschema (alle 7 Tabellen) in `db/install.xml` definiert
- Capability-Konzept in `db/access.php` für beide Plugins definiert
- Subplugin-Mechanismus via `db/subplugins.json` eingerichtet (`coursectrlmod`)
- `classes/plugininfo/coursectrlmod.php` angelegt (Pflicht ab Moodle 4.x)
- `mod/`-Verzeichnis als Subplugin-Root angelegt
- Sprachstrings (EN + DE) für beide Plugins angelegt
- Smoke-Tests (PHPUnit) für beide Plugins angelegt, alle 4 grün
- CI-Workflow für beide Repos erstellt und durch Patches 002–008 stabilisiert

---

## 2. Finalisierte Artefakte

### local_coursectrl

| Pfad | Zweck | Einschränkungen / TODOs |
|---|---|---|
| `version.php` | Plugin-Metadaten, requires Moodle 4.5. | Version: `2026040802` |
| `settings.php` | Admin-Settings-Stub | Inhalt folgt Phase 2 |
| `index.php` | Einstiegsseite (Stub-Weiterleitung) | Wird in Phase 2 durch Dashboard ersetzt |
| `classes/plugininfo/coursectrlmod.php` | Pflicht-Plugininfo-Klasse für Subplugintyp `coursectrlmod`; verhindert Moodle-Debugging-Message beim Install | Leer für MVP 1; wird in Phase 3 mit Lifecycle-Methoden befüllt |
| `db/access.php` | 6 Capabilities definiert | Vollständig für MVP 1 |
| `db/install.xml` | Alle 7 Tabellen; `NOTNULL="false"` ohne `DEFAULT`-Attribut | Schema verbindlich; nur Ergänzungen per `upgrade.php` |
| `db/subplugins.json` | Subplugintyp `coursectrlmod` → `local/coursectrl/mod` | Typname ohne Unterstrich (Moodle-Anforderung) |
| `lang/en/local_coursectrl.php` | Englische Strings | Werden laufend ergänzt |
| `lang/de/local_coursectrl.php` | Deutsche Strings | Werden laufend ergänzt |
| `mod/README.md` | Platzhalter für Subplugin-Verzeichnis | Wird mit ersten Adaptern in Phase 3 befüllt |
| `tests/stub_test.php` | 4 Smoke-Tests: Version, Capabilities, Tabellen, Strings | Alle grün |
| `.github/workflows/moodle-ci.yml` | CI: Moodle 4.5/5.0/5.1, PHP 8.2–8.4, MariaDB+PgSQL, Node.js 24, kein phpcpd | Behat parallel zu PHPUnit |

### block_coursectrl

| Pfad | Zweck | Einschränkungen / TODOs |
|---|---|---|
| `version.php` | Plugin-Metadaten, dependency auf `local_coursectrl 2026040802` | Version: `2026040802` |
| `block_coursectrl.php` | Block-Klasse mit Link-Render und Capability-Check | Inhalt folgt Phase 2 (Templates, AMD) |
| `db/access.php` | `addinstance` / `myaddinstance` Capabilities | – |
| `lang/en/block_coursectrl.php` | Englische Strings | – |
| `lang/de/block_coursectrl.php` | Deutsche Strings | – |
| `tests/stub_test.php` | 4 Smoke-Tests: Version, Datei-Existenz, Capabilities, Strings | Alle grün |
| `.github/workflows/moodle-ci.yml` | CI: analog local; `CCH_CORE_REPO` via GitHub Repo Variable; `add-plugin` mit if-Guard | `CCH_CORE_REPO = ralferlebach/moodle-local_coursectrl` muss als Repo-Variable gesetzt sein |

---

## 3. Offene Arbeitspakete (priorisiert)

1. **Registry-Mechanismus** (`classes/manager/registry.php`) – Lädt und verwaltet alle verfügbaren `coursectrlmod_*`-Adapter; Voraussetzung für Phase 3

2. **Inventory Service** (`classes/local/inventory/inventory_service.php`) – Kernstück von Phase 2; inventarisiert Kurs, Sections, CMs, Labels, Texte in normalisiertem Modell

3. **Entity-Klassen** (`classes/local/entity/*.php`) – `course_item`, `section_item`, `cm_item`, `text_item`; werden vom Inventory Service befüllt

4. **AJAX-Endpunkt `get_inventory`** (`classes/external/get_inventory.php`) – Externe API für die UI; setzt Entity-Klassen voraus

5. **Inventar-UI** (Dashboard, Selektor) – `templates/dashboard.mustache`, `templates/selector.mustache`, `amd/src/dashboard.js`, `amd/src/selector.js`

6. **Adapter-Interface finalisieren** (`classes/local/contract/activity_adapter.php`) – Interface-Signatur lt. Blueprint; muss als PHP-Interface-Datei ausimplementiert werden

7. **Erster Adapter: `coursectrlmod_assign`** – Adapter-Basisklasse + assign-spezifische Implementierung; Voraussetzung für Phase 4

---

## 4. Architekturentscheidungen (verbindlich)

| Entscheidung | Details |
|---|---|
| Plugin-Aufteilung | `local_coursectrl` = gesamte Fachlogik; `block_coursectrl` = reiner UI-Einstieg, keine eigene Logik |
| Subplugintyp | `coursectrlmod` (kein Unterstrich) unter `local/coursectrl/mod/`; Adapter heißen `coursectrlmod_assign`, `coursectrlmod_quiz` etc. |
| Plugininfo-Pflicht | Jeder custom Subplugintyp ab Moodle 4.x braucht `classes/plugininfo/{typename}.php` mit Klasse `{ownernamespace}\plugininfo\{typename}` extending `\core\plugininfo\base` |
| Namensraum | PHP-Klassen: `local_coursectrl\[subnamespace]`; Tests: Namespace je Plugin (`local_coursectrl`, `block_coursectrl`) |
| Adapter-Interface | Signatur lt. Blueprint verbindlich (11 Methoden); kann um optionale Methoden erweitert werden, bestehende Signaturen sind eingefroren |
| DB-Schema | Alle 7 Tabellen in `install.xml`; `NOTNULL="false"` Felder ohne `DEFAULT`-Attribut; Änderungen nur über `upgrade.php` |
| XMLDB-Regel | `DEFAULT` nur bei `NOTNULL="true"` Feldern mit sinnvollem Initialwert; nie `DEFAULT="null"` |
| Capabilities | 6 Capabilities in `local_coursectrl`; Block-Capabilities nur `addinstance`/`myaddinstance` |
| Moodle-Mindestversion | 4.5 (`requires = 2024042200`) |
| PHP-Mindestversion | 8.2 (kein 8.1-Support) |
| `MOODLE_INTERNAL` | Nicht in Testdateien und reinen Klassendateien ohne Side-Effects |
| Klassenöffnung | Keine Leerzeile nach `{` in Klassen-Deklarationen |
| Inline-Kommentare | Enden mit Satzzeichen (`.`, `!`, `?`) |
| Datumserkennung im Text | In MVP: ausschließlich RegEx, keine externe KI/NLP-Bibliothek |
| Availability/Reachability | Strategie wird in Phase 7/8 vor Ort mit Moodle-Core-Code ausgehandelt |
| Workshop-Adapter | Komplexitätsreduzierend anlegen (Phasenlogik vereinfacht) |
| Diagnose-Skripte | Nur im Chat-Fenster ausgeben, nie als Download |
| Versionierung | `version.php`: YYYYMMDDNN; steigt mit jeder größeren Iteration |
| Commit-Format | Eine englische Zeile + max. 5 Stichpunkte |
| Patch-Lieferung | Nummerierte ZIP (`patch-NNN.zip`) mit nur geänderten Dateien, beide Plugins in korrekter Ordnerstruktur |
| Externe Composer-Deps | Keine ohne vorherige explizite Absprache |

---

## 5. Kritische Abhängigkeiten

```
classes/plugininfo/coursectrlmod.php  ← Pflicht: muss vor jedem CI-Install vorhanden sein
    │
    └── db/subplugins.json (registriert den Typ)

activity_adapter.php (Interface)
    └── Alle coursectrlmod_* Adapter
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
| `MOODLE_502_STABLE` existiert noch nicht | Branch wird ergänzt wenn verfügbar | Zurückgestellt (~Mai 2026) |
| `CCH_CORE_REPO` in block CI | GitHub Repo Variable muss gesetzt sein | Gesetzt: `ralferlebach/moodle-local_coursectrl` ✓ |
| Availability-API-Introspektion | Moodle-interne Condition-Evaluierung; Phase 7/8 | Offen, zurückgestellt |
| Freitext-Datumserkennung (RegEx) | Mehrdeutige Formate DE/EN; Phase 5 | Offen |
| Workshop-Phasenmodell | Vereinfachte Implementierung geplant | Zurückgestellt bis Phase 10 |

**Behobene Probleme dieser Session:**
- ~~`DEFAULT="null"` in XMLDB für nullable Felder~~ → patch-002
- ~~PHPCS-Violations in 5 Dateien~~ → patch-003
- ~~`MOODLE_502_STABLE` nicht vorhanden~~ → patch-004
- ~~`add-plugin` bricht bei leerem `CCH_CORE_REPO` ab~~ → patch-005
- ~~Node.js-20-Deprecation-Warnungen / phpcpd deprecated~~ → patch-006
- ~~Ungültiger Subplugintyp `coursectrl_mod` (Unterstrich)~~ → patch-007
- ~~`class_exists('block_coursectrl')` im PHPUnit-Kontext immer false~~ → patch-007
- ~~Fehlende `classes/plugininfo/coursectrlmod.php` → Moodle-Debugging-Message beim Install~~ → patch-008

---

## 7. GitHub-Repositorien

| Plugin | URL | Hauptbranch |
|---|---|---|
| local_coursectrl | `https://github.com/ralferlebach/moodle-local_coursectrl` | main |
| block_coursectrl | `https://github.com/ralferlebach/moodle-block_coursectrl` | main |

Dokumentationsverzeichnis in local_coursectrl: `docs/`
Session-Dokumente: `docs/sessions/session-NNN.md`
Pflichten-/Lastenheft: `docs/` (bereits abgelegt)
Prompt-Templates: `docs/` (bereits abgelegt)

Letzte bekannte Commit-Message: `Add required plugininfo class for coursectrlmod subplugin type`
Letzter Patch: `patch-008`
Aktuelle Version beider Plugins: `2026040802`

---

## 8. Für die nächste Sitzung mitzugebende Dateien

| Datei | Warum relevant |
|---|---|
| `local/coursectrl/db/install.xml` | DB-Schema als Referenz für alle Persistent-Klassen |
| `local/coursectrl/db/access.php` | Capability-Namen für Rechteprüfungen in Services |
| `local/coursectrl/db/subplugins.json` | Subplugin-Struktur (Typname `coursectrlmod`) |
| `local/coursectrl/classes/plugininfo/coursectrlmod.php` | Referenz für Plugininfo-Pattern bei weiteren Subplugintypen |
| `local/coursectrl/lang/en/local_coursectrl.php` | Bestehende Strings prüfen vor Ergänzung |
| `local/coursectrl/tests/stub_test.php` | Referenz für Teststil und Namensräume |
| Dieses Dokument (`docs/sessions/session-001.md`) | Vollständiger Kontext |

**Optional aber hilfreich:**
- Moodle-Core-Datei `lib/classes/plugininfo/base.php` (Referenz für Plugininfo-Lifecycle-Methoden, Phase 3)
- Moodle-Core-Datei `availability/classes/tree.php` (per Prompt verlinken wenn Phase 7 beginnt)
- Moodle-Core-Datei `lib/completionlib.php` (für Completion-Logik in Phase 7)
