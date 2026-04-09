# CCH Kontext-Dokument
Erstellt: 2026-04-09
Zuletzt aktualisiert: 2026-04-09 (nach patch-013)
Sitzung Nr.: 002

---

## 1. Aktueller Projektstand

**Aktuelle Phase:** Phase 2 – Inventar (Kern + Lint-Härtung fertig). Nächster Schritt: Adapter-Basisklasse und erster echter Adapter.

**Was in dieser Sitzung seit patch-012 erledigt wurde:**
- **Zweite Lint-Welle aus CI nach patch-012 saniert (95 Violations in 9 Dateien):**
  - 43 `moodle.Commenting.VariableComment.Missing` auf promoted `readonly` Properties der Entity-Klassen und des Snapshots – gefixt durch **De-Promotion**: explizite Property-Deklarationen mit `@var`-Docblocks am Klassenkopf, Konstruktor mit klassischen Parametern und expliziten `$this->...`-Zuweisungen.
  - 15 `moodle.Commenting.MissingDocblock.Function` auf den konkreten Entity-Methoden `get_type()`, `to_array()`, `from_array()` – gefixt durch konkrete PHPDoc-Blöcke auf jeder Implementierung; die Abstract-Dokumentation allein reicht Moodles Sniff nicht. **(patch-013)**
  - 4 `moodle.Files.MoodleInternal.MoodleInternalNotNeeded` Warnings – gefixt durch Entfernen des `defined('MOODLE_INTERNAL') || die();`-Checks in `fake_adapter_base.php`, `fake_not_an_adapter.php`, `entities_test.php`, `inventory_service_test.php`. Die übrigen Fixtures (`fake_adapter_assign` etc.) behalten den Check, weil sie `require_once` als Side-Effect haben. **(patch-013)**
  - 27 `PSR2.Methods.FunctionCallSignature.MultipleArguments` in `entities_test.php` – gefixt durch Entfernen aller Named-Argument-Calls; stattdessen klassische positionale Aufrufe, entweder single-line oder bei multi-line strikt ein Argument pro Zeile. **(patch-013)**
  - 1 blank line after `{` in `entities_test.php` Zeile 40 (hatte ich in patch-012 beim Sweep übersehen, weil ich mich auf die Klassendateien konzentriert hatte). **(patch-013)**
  - 1 `moodle.Commenting.InlineComment.NotCapital` in `entities_test.php` – `// 'name' is missing.` zu `// Key 'name' is intentionally missing from the payload.` umformuliert. **(patch-013)**
- Version beider Plugins auf `2026040905`.

**Was in früheren Patches der Session erledigt wurde:**
- Pflichtenheft aus `.docx` rekonstruiert, 13-Methoden-Adapter-Interface gefroren, Reflection-Contract-Test. **(patch-009)**
- Adapter-Registry mit DI-Override und 7 Testfällen. **(patch-010)**
- Entity-DTOs `inventory_item` (abstract), `course_item`, `section_item`, `cm_item`, `text_item`. **(patch-011)**
- Erste Lint-Welle (blank line after brace, alignment whitespace, class description). Split der `fake_adapters.php` in 6 PSR1-Einzeldateien. Inventory Service und Inventory Snapshot mit 6 Integrationstests. **(patch-012)**

---

## 2. Finalisierte Artefakte

### local_coursectrl (Stand nach patch-013)

| Pfad | Zweck | Patches |
|---|---|---|
| `classes/local/contract/activity_adapter.php` | 13-Methoden-Interface, gefroren, lint-clean. | 009/012 |
| `classes/manager/registry.php` | Adapter-Registry mit Auto-Discovery und DI-Override. | 010/012 |
| `classes/local/entity/inventory_item.php` | Abstrakte Basis, `JsonSerializable`, `require_key`. | 011/012 |
| `classes/local/entity/course_item.php` | Kurs-DTO, de-promoted, voll dokumentiert. | 011/012/013 |
| `classes/local/entity/section_item.php` | Section-DTO, de-promoted, voll dokumentiert. | 011/012/013 |
| `classes/local/entity/cm_item.php` | CM-DTO, de-promoted, voll dokumentiert, `get_component()`. | 011/012/013 |
| `classes/local/entity/text_item.php` | Text-DTO, de-promoted, voll dokumentiert, `get_key()`. | 011/012/013 |
| `classes/local/inventory/inventory_snapshot.php` | Snapshot-Value-Object, de-promoted, voll dokumentiert. | 012/013 |
| `classes/local/inventory/inventory_service.php` | Kurs → Snapshot, einziger DB-Zugriff in Phase 2. | 012 |
| `tests/activity_adapter_contract_test.php` | Reflection-Contract-Test, 13 Methoden. | 009/012 |
| `tests/registry_test.php` | 7 Registry-Testfälle. | 010/012 |
| `tests/entities_test.php` | 11 Entity-Tests, lint-clean, positional calls. | 011/012/013 |
| `tests/inventory_service_test.php` | 6 Integrationstests gegen Testkurse. | 012/013 |
| `tests/fixtures/fake_adapter_base.php` | No-op Basis-Fake, ohne `MOODLE_INTERNAL`. | 012/013 |
| `tests/fixtures/fake_adapter_assign.php` | `mod_assign`-Fake, mit `MOODLE_INTERNAL` (require_once Side-Effect). | 012 |
| `tests/fixtures/fake_adapter_quiz.php` | `mod_quiz`-Fake. | 012 |
| `tests/fixtures/fake_adapter_unavailable.php` | `is_available() === false`. | 012 |
| `tests/fixtures/fake_adapter_empty_component.php` | Leerer Component-Name. | 012 |
| `tests/fixtures/fake_not_an_adapter.php` | Nicht-Implementierung, ohne `MOODLE_INTERNAL`. | 012/013 |
| `docs/Pflicht- und Lastenheft.md` | Volle konsolidierte Fassung. | 009 |
| `docs/sessions/session-002.md` | Dieses Dokument. | laufend |
| `version.php` | → `2026040905`, Release `0.1.5`. | 009–013 |

### block_coursectrl

| Pfad | Zweck | Patches |
|---|---|---|
| `version.php` | Sync-Bumps, Dependency nachgezogen auf `2026040905`. | 009–013 |

---

## 3. Offene Arbeitspakete

1. **Adapter-Basisklasse** `classes/local/contract/abstract_activity_adapter.php` – abstract class mit No-Op-Defaults.
2. **Erster Adapter `coursectrlmod_assign`** unter `mod/assign/classes/adapter.php`.
3. **AJAX-Endpunkt `get_inventory`** (`classes/external/get_inventory.php`).
4. **Inventar-UI** (`templates/dashboard.mustache`, `templates/selector.mustache`, `amd/src/dashboard.js`).
5. **Label-Inventarisierung** – entweder Sonderbehandlung im `inventory_service` oder über einen eigenen `coursectrlmod_label`-Adapter.

---

## 4. Architekturentscheidungen (Stand Session 002)

| Entscheidung | Details | Patch |
|---|---|---|
| Adapter-Interface Methodenanzahl | **13**, Namespace `local_coursectrl\local\contract`. | 009 |
| Interface-Signaturfreeze | Reflection-Test. | 009 |
| Pflichtenheft-Referenz | `docs/Pflicht- und Lastenheft.md` ist verbindlich. | 009 |
| Registry: kein Singleton, DI-Override | Konstruktor akzeptiert optionale Klassenliste. | 010 |
| Registry: Konvention Adapter-Klasse | `coursectrlmod_{name}\adapter` unter `mod/{name}/classes/adapter.php`. | 010 |
| Entity-Klassen: Immutable DTOs | `readonly` Properties. | 011 |
| **Keine Constructor Promotion in Core-Klassen** | Moodles phpcs erkennt promoted `readonly` Properties nicht als kommentierbar. Lösung: explizite Property-Deklarationen am Klassenkopf mit `@var`-Docblocks, klassischer Konstruktor mit Zuweisungen. Neue Regel für alle Domain-Klassen. | 013 |
| **Keine Named Arguments in Tests** | PSR2-Multi-line-Sniff rechnet `name: value, other: x` als mehrere Argumente pro Zeile. Tests nutzen stattdessen positionale Argumente mit expliziter Parameter-Reihenfolge. | 013 |
| `MOODLE_INTERNAL` nur bei Side-Effects | In reinen Klassendateien und Testdateien ohne `require_once`/`include` weglassen, sonst `moodle.Files.MoodleInternal.MoodleInternalNotNeeded`. | 013 |
| Inline-Kommentare beginnen mit Großbuchstabe oder Ziffer | `moodle.Commenting.InlineComment.NotCapital` – auch wenn der Kommentar ein Quote öffnet, vor dem Quote ein Wort setzen. | 013 |
| `text_item` ohne eigene id | Identität über `(entitytype, entityid, fieldname)`. | 011 |
| Version-Lockstep | Beide Plugins werden immer synchron gebumpt. | 009–013 |
| Testfixtures PSR1 single class per file | Multi-class-files werden abgelehnt. | 012 |
| Keine Alignment-Spaces in Arrays | `Universal.WhiteSpace.CommaSpacing.TooMuchSpaceAfter`. | 012 |
| Keine Leerzeile nach `{` | `PSR12.Classes.OpeningBraceSpace.Found`. | 012 |
| Proaktiver Lint-Sweep vor jedem Patch | Blank-line-Sweep, MOODLE_INTERNAL-Sweep, Multi-arg-Sweep über alle neuen und geänderten Dateien. | 012/013 |
| `inventory_service` als einziger DB-Konsument in Phase 2 | Sub-Services erst bei Bedarf. | 012 |
| Inventory-Snapshot als Value Object | Nicht als Persistent. Caching später. | 012 |
| Label-Text-Collection vertagt | Kommt mit erstem Adapter. | 012 |

---

## 5. Bekannte Probleme und Risiken

Keine neuen offenen Punkte. Bestehende Risiken aus Session 001 unverändert.

**Behobene Probleme in Session 002:**
- ~~Widerspruch 11 vs. 13 Methoden im Adapter-Interface~~ → patch-009
- ~~Pflichtenheft im Repo nur in gekürzter Fassung vorhanden~~ → patch-009
- ~~Keine zentrale Adapter-Verwaltung~~ → patch-010
- ~~Keine normalisierten Inventar-DTOs~~ → patch-011
- ~~Kein Inventory Service~~ → patch-012
- ~~Erste Lint-Welle (39 Violations aus 009/010/011)~~ → patch-012
- ~~Multi-class-file `fake_adapters.php` gegen PSR1~~ → patch-012
- ~~Zweite Lint-Welle (95 Violations aus 012): promoted-property Docblocks, method Docblocks, MOODLE_INTERNAL, named arguments, blank line after brace, inline comment~~ → patch-013

---

## 6. Versionen

| Plugin | Version nach patch-013 |
|---|---|
| local_coursectrl | `2026040905` |
| block_coursectrl | `2026040905` |

Letzter Patch: `patch-013`
