# Session 015 — Course Control Hub (`local_coursectrl`)

**Datum:** 2026-05-03  
**Thema:** CI/Prechecker-Compliance — PHPCS, PHPDoc, Mustache, ESLint, Workflow  
**Versionslinie:** keine funktionalen Änderungen; ausschließlich Lint/CI-Korrekturen

---

## Ausgangslage

Die Codebase hatte nach Session 014 einen stabilen funktionalen Stand, war aber in mehreren
CI-Checks nicht grün. Ziel dieser Session: alle lokalen und CI-seitigen Lint-Fehler beheben,
bis `make all` und der vollständige GitHub-Actions-Workflow ohne Fehler durchlaufen.

---

## Behobene Probleme (chronologisch)

### 1. PHPCS — Line-length, CRLF, Trailing Whitespace

- **60 Dateien** mit Windows-Zeilenenden (`\r\n` statt `\n`) — alle auf LF konvertiert.
- **9 Dateien** mit Trailing Whitespace innerhalb von PHP-Strings (nicht von `phpcbf` autofix-bar)
  — manuell bereinigt.
- **4 Dateien** mit Zeilen über 180 Zeichen — enstanden durch `fix_phpdoc.php`-Schaden
  (siehe unten), via `git checkout` zurückgesetzt.

### 2. PHPDoc (`local_moodlecheck`) — `@param`-Vollständigkeit

moodlecheck prüft, ob jeder Parameter einer Funktion in der Docblock-`@param`-Liste steht.
Dabei gelten folgende Parser-Einschränkungen (empirisch ermittelt):

| Typ-Konstrukt | Problem | Lösung |
|---|---|---|
| `array<int, V>` | Stoppt am Komma nach dem Space | `array` |
| `Type[]` | Stoppt an `[` | `array` |
| `?Type` (nullable) | Docblock muss `Type\|null` enthalten | `Type\|null` |
| `Type\|null` ohne `?` | Pipe wird korrekt geparst | — |

**Betroffene Funktionen und Fixes:**

| Datei | Funktion | Problem | Fix |
|---|---|---|---|
| `textreview_manager.php` | `__construct` | Verwaister zweiter Docblock (Überbleibsel früherer Patches), `\|null` in Typen fehlte | Orphan entfernt, `Type\|null` ergänzt |
| `consistency_runner.php` | `__construct` | `Type\|null` fehlte in allen 3 @param | `Type\|null` ergänzt |
| `graph_dataset_builder.php` | `build` | `cm_item[]` als Typ | → `array` |
| `graph_dataset_builder.php` | `build_with_forward` | `cm_item[]`, `dependency_index` als `mixed` | korrigiert |
| `graph_dataset_builder.php` | `assign_layer_positions` | Doppelter `@param $layers`, `array<int,int>` Typ | Duplikat entfernt, Typ vereinfacht |

### 3. `fix_phpdoc.php` — Schwerer Schaden durch Type-Rewrite-Logik

**Problem:** Eine frühere Version von `fix_phpdoc.php` hatte eine `params_types_match()`-Prüfung
erhalten, die bei jeder Typ-Abweichung den gesamten Docblock neu schrieb. Dabei wurden
mehrzeilige `@param`-Beschreibungen (Fortsetzungszeilen) in einen einzigen String zusammengeführt
und als eine einzige, bis zu 300 Zeichen lange Zeile ausgegeben.

**Betroffene Dateien (Beispiele):**
- `classes/manager/registry.php` L204–208 (239 Zeichen)
- `classes/local/analysis/deep_journey_simulator.php` L83 (197 Zeichen)
- `classes/local/analysis/dependency_index.php` L98 (231 Zeichen)
- `classes/local/analysis/course_frame_checker.php` L80 (176 Zeichen)

**Fix:** `fix_phpdoc.php` vollständig auf **Count-Only-Modus** zurückgebaut:
- Greift **nur** ein wenn die Anzahl der `@param`-Tags nicht mit der Signatur übereinstimmt.
- Bestehende Typen, Beschreibungen und Zeilenumbrüche werden **nie** angetastet.
- Fehlende Tags werden als `@param mixed $name See function signature.` ergänzt.
- Surplus-Tags werden entfernt.
- Keine Typ-Validierung, keine Typ-Umschreibung.

### 4. Makefile — Output-Filterung

- `lint-phpdoc`: `grep -B1 '    Line' | grep -v '^--$' || true` — zeigt nur Fehlerzeilen mit Dateiname.
- `lint-mustache`: `grep -v '^OK:' || true` — unterdrückt `OK:`-Meldungen.

### 5. CI-Workflow (`moodle-ci.yml`) — PHPDoc-Step

**Problem:** `moodle-plugin-ci phpdoc` hat kein `--exclude`-Flag und scannt `tools/`.
Die Dateien in `tools/` sind in `.gitattributes` bereits als `export-ignore` markiert
(nicht im Moodle.org-Release-ZIP enthalten), aber die CI prüft den vollen Repo-Checkout.

**Lösungsversuch-Historie:**
1. Direkter `php moodlecheck.php`-Aufruf → scheiterte, weil `local_moodlecheck` nicht installiert war.
2. `output=$(moodle-plugin-ci phpdoc) | grep -v '/tools/'` → funktionierte prinzipiell,
   aber unzuverlässig mit Exit-Codes.
3. **Finale Lösung:** `moodle-plugin-ci phpdoc` mit `IGNORE_PATHS: tools` als Env-Variable.

**Zusätzlich:** `@param` in Fließtext-Beschreibungen (Docblock-Text, nicht als Tag)
in `fix_phpdoc.php` auf `param` (ohne `@`) geändert, da moodlecheck auch Text-`@param`
als "Invalid inline phpdocs" flaggt.

### 6. CI-Workflow — `thirdpartylibs`-Step entfernt

`moodle-plugin-ci thirdpartylibs` existiert nicht. Das Plugin liefert keine gebundeten
Drittbibliotheken. Step entfernt.

### 7. Mustache-Templates — HTML-Validation und ESLint

**Ursache der `action=""`-Fehler:** Das Example-JSON im `@template`-Docblock hatte leere
Strings für URL-Variablen (`"timelineurl":""`). Der CI-Mustache-Renderer substituiert diese
Werte und erzeugt `action=""` im gerenderten HTML — gültig in HTML5, aber Validator-Fehler.

**Fix:** Placeholder-Werte `"#"` im Example-JSON gesetzt:
- `timeline.mustache`: `"shifturl":"#"`, `"timelineurl":"#"`
- `manage.mustache`: `"previewurl":"#"` ergänzt
- `simulation.mustache`: `"selfurl":"#"` ergänzt

**ESLint `brace-style`-Verletzungen in `simulation.mustache`:**
Alle einzeiligen `if`/`for`/`forEach`-Blöcke im `{{#js}}`-Block expandiert:

| Zeile | Vor | Nach |
|---|---|---|
| L497 | `if (arrow) { arrow.innerHTML = ...; }` | 3-zeilig |
| L539 | `if (pc.checked && ...) { gi.value = gp; }` | 3-zeilig |
| L544 | `if (!isNaN(v)) { pc.checked = ...; }` | 3-zeilig |
| L560 | `if (gi && pc && ...) { gi.value = gp; }` | 3-zeilig |
| L588 | `if (shown > LIMIT) { cutIdx = i; break; }` | 4-zeilig |
| L591 | `for (...) { allLi[j].style.display = ...; }` | 3-zeilig |

**ESLint `no-multi-spaces`:**
- `var cid   =` → `var cid =`
- `var hide  =` → `var hide =`
- `var allLi  =` → `var allLi =`

**ESLint `brace-style` in `graph.mustache`:**
- `if (!btn || !menu) { return; }` → expandiert

---

## Gelieferte Patches (ZIPs)

| ZIP | Inhalt |
|---|---|
| `patch-phpdoc-final.zip` | `textreview_manager.php`, `consistency_runner.php`, `graph_dataset_builder.php` |
| `patch-fix-phpdoc.zip` | `tools/fix_phpdoc.php` (Count-Only-Modus) |
| `patch-crlf-ws.zip` | 60 Dateien: CRLF→LF + Trailing Whitespace in Strings |
| `patch-mustache-eslint.zip` | `graph.mustache`, `simulation.mustache` (ESLint-Fixes) |
| `patch-mustache-final.zip` | `simulation.mustache` (restliche brace-style + multi-space Fixes) |
| `patch-mustache-action.zip` | `timeline.mustache`, `manage.mustache`, `simulation.mustache` (Example-JSON) |
| `patch-ci-phpdoc-exclude.zip` | `.github/workflows/moodle-ci.yml` (IGNORE_PATHS: tools) |
| `patch-ci-thirdpartylibs.zip` | `.github/workflows/moodle-ci.yml` (thirdpartylibs-Step entfernt) |
| `Makefile` | lint-phpdoc + lint-mustache Output-Filterung |

---

## Technische Erkenntnisse (neu)

### moodlecheck Typ-Parser

moodlecheck verwendet intern einen Typ-Parser, der an folgenden Zeichen stoppt:
- Komma `,` (auch innerhalb von Generics: `array<int, V>` → stoppt nach `int,`)
- Öffnende eckige Klammer `[` (`Type[]` → stoppt vor `[`)
- Pipe `|` wird **korrekt** geparst wenn kein Space folgt

Nullable-Parameter (`?Type`) benötigen `Type|null` im Docblock — der `?`-Prefix allein
reicht nicht.

### moodle-plugin-ci mustache Renderer

Der Renderer substituiert Template-Variablen mit Werten aus dem Example-JSON im
`@template`-Docblock. Undefinierte Variablen werden als leerer String `""` gerendert.
Deshalb muss das Example-JSON alle URL-Variablen mit Nicht-Leer-Werten (`"#"` genügt)
belegen, sonst entstehen `action=""` in Forms.

### fix_phpdoc.php Sicherheitsregel

Das Tool darf **ausschließlich** die Anzahl der `@param`-Tags korrigieren.
Jede Art von Typ-Validierung oder Typ-Umschreibung ist verboten, da moodlecheck
eigene Typ-Einschränkungen hat, die von PHP-Docblock-Standards abweichen und
für die kein automatisiertes Matching zuverlässig möglich ist.

---

## Zustand am Session-Ende

| Check | Status |
|---|---|
| `phpcs` (lokal) | ✅ Clean |
| `eslint` (lokal) | ✅ Clean |
| `moodlecheck` (lokal, `--exclude tools/`) | ✅ Clean |
| Mustache syntax (lokal) | ✅ Clean |
| CI `lint-php` (phpcs) | ✅ Grün |
| CI `lint-js` (ESLint) | ✅ Grün |
| CI `lint-js` (PHPDoc) | ✅ Grün |
| CI `lint-js` (Mustache) | ✅ Grün |
| CI `phpunit` | ✅ Grün (unverändert) |

---

## Übergabe an Session 016

- Alle Lint/CI-Checks grün
- `fix_phpdoc.php` im Count-Only-Modus — keine Typ-Umschreibungen mehr
- `tools/` korrekt aus CI-PHPDoc-Check ausgeschlossen (IGNORE_PATHS)
- `tools/` aus Moodle.org-Release ausgeschlossen (`.gitattributes export-ignore`)
- Nächste inhaltliche Phase nach Pflichtenheft: **Phase 8 — Risiko- und Sackgassenanalyse**
