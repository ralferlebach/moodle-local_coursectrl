# CCH Kontext-Dokument
Erstellt: 2026-04-09
Zuletzt aktualisiert: 2026-04-09 (nach patch-015)
Sitzung Nr.: 003

---

## 1. Aktueller Projektstand

**Aktuelle Phase:** **Phase 2 abgeschlossen.** Inventory-Kern, External-API und Server-Side-Dashboard sind alle live. Phase 3 (Adapter-Basis) kann beginnen.

**Was in dieser Sitzung erledigt wurde:**

**patch-014 — External-API:**
- `classes/external/get_inventory.php` als AJAX-fähige Wrapper-Klasse um `inventory_service::build_for_course()`
- Vollständige `external_single_structure`-Schemadeklaration mit getrennten Helper-Methoden je Entitätstyp
- Nullable-Felder als `VALUE_REQUIRED + NULL_ALLOWED` (nicht VALUE_OPTIONAL — verhindert Drop bei null in `clean_returnvalue`)
- `db/services.php` mit Service-Registrierung
- `tests/external/get_inventory_test.php` mit 4 Integrationstests

**patch-015 — Phase-2-Abschluss (UI):**
- `db/access.php` aus dem Repo gelesen → Capability-Mapping geklärt: **`local/coursectrl:view`** existiert mit `editingteacher` und `manager` als Archetypes. Die offene Frage aus patch-014 ist damit beantwortet.
- `classes/external/get_inventory.php` und `db/services.php` von der Platzhalter-Capability `moodle/course:view` auf die projekteigene `local/coursectrl:view` umgestellt. Tests müssen nicht angepasst werden, weil `editingteacher`-Archetype die Cap automatisch mitbekommt.
- `classes/output/dashboard_page.php` als reiner Transformer von `inventory_snapshot` zu Mustache-Template-Kontext. Keine Moodle-Page-Abhängigkeiten, vollständig isoliert testbar.
- `classes/output/renderer.php` als `plugin_renderer_base` mit `render_dashboard_page()`.
- `templates/dashboard.mustache` mit Bootstrap-basierten Stat-Cards (Sections/Activities/Texts) und einer per-Section-Aufschlüsselung der CMs inkl. Visibility-, Completion- und Availability-Badges.
- `index.php` neu geschrieben: ruft den `inventory_service` server-side, übergibt das Snapshot an den Renderable und rendert es. Capability-Check `local/coursectrl:view`. URL-Konvention beibehalten: `/local/coursectrl/index.php?courseid=N`.
- `lang/en/local_coursectrl.php` und `lang/de/local_coursectrl.php` um 14 neue `dashboard_*`-Strings ergänzt (vollständige Datei, ersetzt das alte File).
- `tests/output/dashboard_page_test.php` mit 5 isolierten Renderable-Tests gegen hand-gebaute Snapshots.
- Version beider Plugins auf `2026040907`.

---

## 2. Designentscheidung: Server-Side Rendering statt AMD

**Phase 2 Abschluss erfolgt explizit ohne AMD-Modul.** Begründung:

- Ein AMD-Modul erfordert `grunt amd` zum Bauen des `amd/build/*.min.js`. Dieser Schritt läuft beim normalen `php admin/cli/upgrade.php` *nicht* mit; nur im moodle-plugin-ci CI-Lauf oder beim manuellen `grunt amd`.
- Ein hand-gebauter Build-File birgt CI-Mismatch-Risiko, weil moodle-plugin-ci `grunt amd` regeneriert und die Diff prüft.
- Ein src-only Ship würde auf Production-Installs (debugging off, cachejs on) nicht laden.
- Server-Side-Rendering funktioniert sofort nach Install, ohne Build-Schritt, ohne JavaScript-Abhängigkeit.
- Das External `get_inventory` bleibt für Mobile-App, Drittanbieter-Clients und ein zukünftiges AMD-Frontend voll nutzbar.

AMD-Enhancements (Live-Filter, Ajax-Refresh, Drag-and-Drop) können in einem späteren Patch nachgereicht werden, sobald sie konkret gebraucht werden — nicht „weil Phase 2 das so vorsieht".

---

## 3. Finalisierte Artefakte (Stand nach patch-015)

### local_coursectrl

| Pfad | Zweck | Patches |
|---|---|---|
| `classes/local/contract/activity_adapter.php` | 13-Methoden-Interface, gefroren. | 009/012 |
| `classes/manager/registry.php` | Adapter-Registry mit DI-Override. | 010/012 |
| `classes/local/entity/inventory_item.php` | Abstrakte Basis. | 011/012 |
| `classes/local/entity/course_item.php` | Kurs-DTO, de-promoted. | 011/012/013 |
| `classes/local/entity/section_item.php` | Section-DTO, de-promoted. | 011/012/013 |
| `classes/local/entity/cm_item.php` | CM-DTO, de-promoted, `get_component()`. | 011/012/013 |
| `classes/local/entity/text_item.php` | Text-DTO, de-promoted, `get_key()`. | 011/012/013 |
| `classes/local/inventory/inventory_snapshot.php` | Snapshot-Value-Object, de-promoted. | 012/013 |
| `classes/local/inventory/inventory_service.php` | Kurs → Snapshot. | 012 |
| `classes/external/get_inventory.php` | External-API-Wrapper, **`local/coursectrl:view`**. | 014/**015** |
| `classes/output/dashboard_page.php` | Renderable, snapshot → template context. | **015** |
| `classes/output/renderer.php` | Plugin-Renderer mit `render_dashboard_page()`. | **015** |
| `db/services.php` | Service-Registrierung, **`local/coursectrl:view`**. | 014/**015** |
| `templates/dashboard.mustache` | Server-side gerendertes Dashboard, Bootstrap-basiert. | **015** |
| `index.php` | Dashboard-Einstiegsseite mit Capability-Check und Renderer-Aufruf. | **015** |
| `lang/en/local_coursectrl.php` | EN-Strings inkl. `dashboard_*`. | **015** |
| `lang/de/local_coursectrl.php` | DE-Strings inkl. `dashboard_*`. | **015** |
| `tests/activity_adapter_contract_test.php` | Reflection-Contract-Test. | 009/012 |
| `tests/registry_test.php` | 7 Registry-Testfälle. | 010/012 |
| `tests/entities_test.php` | 11 Entity-Tests. | 011/012/013 |
| `tests/inventory_service_test.php` | 6 Integrationstests. | 012/013 |
| `tests/external/get_inventory_test.php` | 4 External-API-Tests. | 014 |
| `tests/output/dashboard_page_test.php` | 5 Renderable-Tests. | **015** |
| `tests/fixtures/fake_adapter_*.php` | 6 PSR1-konforme Adapter-Fixtures. | 012/013 |
| `docs/Pflicht- und Lastenheft.md` | Volle konsolidierte Fassung. | 009 |
| `docs/sessions/session-003.md` | Dieses Dokument. | 014/015 |
| `version.php` | → `2026040907`, Release `0.1.7`. | 014/015 |

### block_coursectrl

| Pfad | Zweck | Patches |
|---|---|---|
| `version.php` | Sync-Bumps, Dependency nachgezogen auf `2026040907`. | 014/015 |

---

## 4. Offene Arbeitspakete (priorisiert)

1. **Block-Plugin-Linkziel** — der bestehende `block_coursectrl.php` rendert einen Link auf das Plugin. Dieser Link sollte auf `/local/coursectrl/index.php?courseid={current courseid}` zeigen. Falls er das noch nicht tut, mit dem nächsten Patch nachziehen. **Erfordert Lesen der bestehenden `block_coursectrl.php`.**
2. **Adapter-Basisklasse** `classes/local/contract/abstract_activity_adapter.php` — abstract class mit No-Op-Defaults. **Phase 3 Start.**
3. **Erster Adapter `coursectrlmod_assign`** unter `mod/assign/classes/adapter.php`. **Phase 3.**
4. **Zweite Contract-Interfaces** `inventory_provider.php`, `report_provider.php` (lt. Blueprint).
5. **AMD-Enhancements** für das Dashboard (Live-Filter, Ajax-Refresh) — erst wenn konkret gebraucht.
6. **Label-Inventarisierung** — entweder Sonderbehandlung im `inventory_service` oder über einen `coursectrlmod_label`-Adapter.

---

## 5. Architekturentscheidungen (neu in Session 003)

| Entscheidung | Details | Patch |
|---|---|---|
| External-API Namespace | `local_coursectrl\external`. | 014 |
| External-API Schema | Vollständige `external_single_structure` pro Entität. | 014 |
| Nullable-Felder im Schema | `VALUE_REQUIRED + NULL_ALLOWED`. | 014 |
| Read-Capability | `local/coursectrl:view` (verifiziert via `db/access.php`). | 015 |
| **Phase-2-UI: Server-Side Rendering, kein AMD** | Vermeidet Grunt-Build-Schritt und CI-Mismatch. AMD ist Progressive Enhancement für später. | 015 |
| Renderable-Pattern | `dashboard_page` ist reiner Transformer (snapshot → array), keine Moodle-Page-Abhängigkeit. Renderer kapselt nur den Mustache-Aufruf. Macht das Renderable isoliert testbar. | 015 |
| Mustache-Template-Konvention | Bootstrap-Klassen aus dem Moodle-Core-Theme (`card`, `badge`, `display-4`, etc.). Keine eigenen CSS-Dateien in Phase 2. | 015 |
| Lang-File-Alignment | Lang-Dateien verwenden `=>`-Alignment in `$string`-Arrays. Das ist der Moodle-Standard für Lang-Files und vom phpcs-Sniff explizit erlaubt — entgegen der Alignment-Regel für „normalen" Code. | 015 |

---

## 6. Bekannte Probleme und Risiken

Keine neuen offenen Punkte. Bestehende Risiken aus Session 001/002 unverändert (Availability-API, Freitext-Datum, Workshop).

**Behoben in Session 003:**
- ~~Phase 2 hat keine externe Schnittstelle~~ → patch-014
- ~~Capability-Mapping unklar~~ → patch-015 (auf `local/coursectrl:view` gesetzt nach Lesen von `db/access.php`)
- ~~Phase 2 hat keine UI~~ → patch-015 (Server-Side Dashboard)

---

## 7. Versionen

| Plugin | Version nach patch-015 |
|---|---|
| local_coursectrl | `2026040907` |
| block_coursectrl | `2026040907` |

Letzter Patch: `patch-015`

**Phase 2 vollständig abgeschlossen. Nächster Schritt: Phase 3 — Adapter-Basis.**
