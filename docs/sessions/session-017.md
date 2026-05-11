# Session 017 — CI-Grünstellung: phpcpd, phpcs, Behat

**Datum:** 2026-05-11
**Plugin:** `local_coursectrl`
**Version bei Sitzungsbeginn:** 2026051054
**Version bei Sitzungsende:** 2026051061
**Patches:** 1.1.57 – 1.1.63

---

## Ausgangslage

Mehrere CI-Checks schlugen in `moodle-release.yml` fehl:
- `moodle-plugin-ci phpcpd` — 6 Klone / 197 Zeilen (2 Duplikat-Paare)
- `moodle-plugin-ci phpcs` — 421 Fehler in `batch_manager.php` (phpcs-Crash)
- `moodle-plugin-ci behat` — 3 Steps als `PendingException` gemeldet
- PHPUnit — 1 Failure (`Undefined variable $dependent`)

---

## Problemanalyse und Fixes

### 1. phpcpd: finalize_batch() Extraktion (patch-1.1.57 / neu: 1.1.60)

**Duplikat 1:** `batch_manager.php` L189–234 ↔ L610–655 (45 Zeilen)

Das duplizierte Block enthielt Transaction-Commit, Fehlerbehandlung, Status-Update, Kalender-Refresh und das `batch_executed`-Event.

**Fix:** Private Methode `finalize_batch()` extrahiert. Beide `execute()`- und `execute_from_targets()`-Methoden delegieren mit `return $this->finalize_batch(...)`.

**Achtung — Folgefehler:** Die Extraktion war initial strukturell fehlerhaft (siehe Abschnitt 3).

**Duplikat 2:** `fixture_simulation_test.php` L162–184 ↔ L199–221 (22 Zeilen)

Setup für Prereq/Dependent-Assign mit Completion-Bedingung.

**Fix:** Helper `create_course_with_completion_pair(): array` extrahiert, gibt `['courseid', 'prereqcmid', 'dependentcmid']` zurück.

---

### 2. Behat: PendingException für day(s)-Steps (patches 1.1.58, 1.1.59)

**Symptom:** In `moodle-release.yml` (PHP 8.1) meldete Behat drei Steps als `PendingException`:
```
@Then the timeopen of quiz :arg1 in course :arg2 should be shifted by :arg3 day(s) from :arg4
@Then the completionexpected of assign :arg1 ...
@Then the timeclose of quiz :arg1 ...
```
In `moodle-ci.yml` (PHP 8.2) traten diese Fehler nicht auf.

**Ursache 1 — Ambiguous Step Definitions:**
Es existierten zwei Duplikat-Paare ambiguöser Step-Definitionen:
- `@Given ... is :timestamp` (in `set_completionexpected`) und `@Given ... is :ts` (in `set_completionexpected_for_activity`) — identischer Step-Text, verschiedene Placeholder-Namen
- `@Then the completionexpected of :modtype ...` (generisch) und `@Then the completionexpected of assign ...` (spezifisch)

Behat warf beim Background-Step eine `Ambiguous match`-Exception. Alle nachfolgenden Szenario-Steps wurden als `pending` markiert — auch korrekt definierte Steps wie `timeopen of quiz`.

**Fix (patch-1.1.58):** `set_completionexpected_for_activity` entfernt (Duplikat von `set_completionexpected`). `completionexpected_of_assign_should_be_shifted` entfernt (Duplikat der generischen `:modtype`-Version).

**Ursache 2 — day(s) in Turnip-Annotationen:**
`day(s)` in einer Behat-Turnip-Annotation erzeugt eine zusätzliche optionale Capture-Group. Behat zählt die Capture-Groups und findet einen Mismatch mit den Methodenparametern → Step bleibt undefined.

**Fix (patch-1.1.59):** Alle vier betroffenen `@Then`-Annotationen auf explizite Regex umgestellt:
```
@Then /^the duedate of assign "?(.+?)"? in course "?(\S+?)"? should be shifted by (-?\d+) day\(s\) from (-?\d+)$/
@Then /^the completionexpected of (\w+) "?(.+?)"? in course "?(\S+?)"? should be shifted by (-?\d+) day\(s\) from (-?\d+)$/
@Then /^the timeopen of quiz "?(.+?)"? in course "?(\S+?)"? should be shifted by (-?\d+) day\(s\) from (-?\d+)$/
@Then /^the timeclose of quiz "?(.+?)"? in course "?(\S+?)"? should be shifted by (-?\d+) day\(s\) from (-?\d+)$/
```
`day\(s\)` matcht den Literaltext, ohne zusätzliche Capture-Group. Alle Zeilen ≤ 132 Zeichen. Positionale Capture-Groups statt Named Captures, um Zeilenlänge einzuhalten. `"?(.+?)"?` handhabt sowohl gequotete (`"Quiz-PRC"`) als auch ungequotete (`Forum-C`) Werte.

**Wichtig:** Docblock-Annotations müssen IMMER auf EINER Zeile bleiben — kein Zeilenumbruch innerhalb des Regex erlaubt, Behat parst Annotation zeilenweise.

---

### 3. phpcs: 421 Fehler durch ConstructorReturnSniff-Crash (patches 1.1.61, 1.1.62)

**Symptom:** phpcs meldete bei `batch_manager.php` 421 Fehler:
```
Line 1: Undefined array key "scope_closer" in ConstructorReturnSniff.php
Line 51: Expected MOODLE_INTERNAL check
Lines 53–898: Line indented incorrectly (expected 0 spaces, found 4)
```
418/419 Fehler wären durch phpcbf automatisch fixbar gewesen.

**Ursache:** Brace-Imbalance: `Open: 88, Close: 86` — zwei ungematchte öffnende Geschweifte Klammern.

**Root Cause:** Bei der `finalize_batch`-Extraktion in Patch-1.1.57 wurden die `try`-Blöcke in `execute()` und `execute_from_targets()` nicht korrekt geschlossen:

```php
// FEHLERHAFTER Stand nach patch-1.1.57:
$transaction = $DB->start_delegated_transaction();
try {
    foreach (...) { ... }
    // KEIN } catch {...} !
return $this->finalize_batch($transaction, ...);  // inside try
}  // schließt try statt Methode
// Methoden-} fehlt komplett
```

Zusätzlich hatte der Insert in patch-1.1.57 den Docblock von `refresh_calendars` überschrieben, was ebenfalls zum phpcs-Crash beitrug.

**Fix (patch-1.1.62):**
- `$transaction->allow_commit()` zurück in den `try`-Block beider Methoden
- `} catch (\Throwable $e) { ... }` in beiden Methoden wiederhergestellt
- `finalize_batch()` auf Post-Commit-Arbeit reduziert: kein `\moodle_transaction`-Parameter, kein interner `try`-catch
- Docblock für `refresh_calendars` wiederhergestellt
- Brace-Balance nach Fix: 0

**Design der finalen finalize_batch()-Signatur:**
```php
private function finalize_batch(
    batch $batch,
    bool $hasanyfailure,
    array $successfulbyadapter,
    int $batchid,
    int $userid,
    int $courseid,
    string $action,
    array $summary
): int
```
Enthält nur Status-Update, Kalender-Refresh und Event-Fire — kein Transaction-Handling.

---

### 4. PHPUnit: Undefined variable $dependent (patch-1.1.63)

**Symptom:**
```
✘ Completion dep met grants access
  Undefined variable $dependent
  fixture_simulation_test.php:195
```

**Ursache:** Bei der Refaktorierung auf `create_course_with_completion_pair()` wurde die Assertion in `test_completion_dep_met_grants_access` nicht vollständig migriert:
```php
// ALT (vergessen zu ändern):
$results[(int)$dependent->cmid]['accessible']
// NEU (korrekt):
$results[$dependentcmid]['accessible']
```

**Fix:** Einzelzeilen-Substitution in `fixture_simulation_test.php`.

---

### 5. phpcpd lokale Inkompatibilität

**Symptom:**
```
PHP Fatal error: Uncaught Error: Call to undefined method
SebastianBergmann\Version::getVersion() in Application.php:98
```

**Ursache:** Versions-Mismatch zwischen global installiertem phpcpd und `sebastian/version`.

**Fix:**
```bash
composer global remove sebastian/phpcpd
composer global require sebastian/phpcpd:^6.0
```
phpcpd 6.x ist die letzte Version mit `getVersion()`. Das Makefile-Target bleibt mit `|| true` abgesichert.

---

### 6. Diagnose: moodle-ci.yml vs. moodle-release.yml

**Beobachtung:** Behat-Steps schlugen nur in `moodle-release.yml` fehl, nicht in `moodle-ci.yml`.

**Analyse:**
- `moodle-ci.yml` → läuft auf `development`-Branch, PHP 8.2, `--tags "@local_coursectrl and not @broken"`
- `moodle-release.yml` → läuft auf `main`-Branch nach Merge, PHP 8.1, keine Tag-Filterung, `--scss-deprecations`

Der Unterschied PHP 8.1 vs. 8.2 war relevant für die Ambiguity-Behandlung. Die Ambiguity trat auf beiden auf, wurde aber erst nach dem Merge auf `main` als CI-Failure sichtbar (weil moodle-ci.yml auf dem development-Branch-Stand lief, bevor der ambige Code pushed wurde).

---

## Patch-Übersicht

| Patch | Datei(en) | Inhalt |
|-------|-----------|--------|
| 1.1.57 | batch_manager.php, fixture_simulation_test.php | phpcpd: finalize_batch(), completion-pair helper (initial, fehlerhaft) |
| 1.1.58 | behat_local_coursectrl.php | Ambiguous Step Definitions entfernt |
| 1.1.59 | behat_local_coursectrl.php | Turnip day(s) → Regex day\(s\), ≤132 Zeichen |
| 1.1.60 | batch_manager.php, fixture_simulation_test.php, behat | Re-Lieferung phpcpd-Fixes |
| 1.1.61 | batch_manager.php | refresh_calendars Docblock restauriert |
| 1.1.62 | batch_manager.php, fixture_simulation_test.php, behat | try-catch restauriert, Brace-Balance=0, Konsolidierung |
| 1.1.63 | fixture_simulation_test.php | $dependent->cmid → $dependentcmid |

---

## Kritische Lernpunkte

### Behat-Annotationen: Regex vs. Turnip
- `day(s)` in Turnip-Annotationen erzeugt eine **optionale Capture-Group** → Param-Count-Mismatch → Step undefined
- Lösung: explizite Regex `/^.../$/` mit `day\(s\)` für Literaltext
- Docblock-Annotationen müssen auf **einer Zeile** bleiben (max. 132 Zeichen)
- Regex-Kurzformen: `"?(.+?)"?` für optionale Quotes (lazy), `(\S+?)` für Shortnames, `(-?\d+)` für Ganzzahlen

### Behat-Ambiguität
- **Zwei Annotationen mit identischem Step-Text** (nur Placeholder-Name verschieden) → Ambiguous Match
- Behat markiert bei Ambiguity im Background **alle nachfolgenden Steps** als pending
- Diagnose: `src.count('@Then ...')` und `src.count('@Given ...')` pro Pattern prüfen

### brace-Extraktion via Python str.replace()
- Beim Extrahieren von Code-Blöcken: **immer Brace-Balance prüfen** nach dem Replace
- `Open count ≠ Close count` → phpcs crasht mit ConstructorReturnSniff-Fehler → kaskadierend 400+ Fehler
- Prüfung: `re.sub`-basierter Zähler (ohne Strings/Kommentare) auf den finalen Dateiinhalt

### finalize_batch Architektur
- Transaction-Handling (`allow_commit`, `rollback`) muss **im aufrufenden Scope** bleiben
- Hilfsmethoden für Post-Commit-Arbeit nehmen **keinen** `\moodle_transaction`-Parameter
- phpcpd-Schwellwert `--min-tokens 70`: ~7-Zeilen try-catch ist unter dem Threshold, nicht erkannt als Duplikat

---

## Offene Punkte (unverändert)

- ESLint-Warnings in `shift_workflow.js` (Behat-Coverage Voraussetzung)
- Behat-Coverage für Shift-Workflow vollständig aufbauen
- PHPUnit-Tests für Phase-8-Klassen und Deep-Analysis-Engine
- Cross-Page-Block-Architektur ohne Instanz-Duplizierung
- CI vollständig grün (nach diesen Fixes sollten phpcpd, phpcs, PHPUnit und Behat sauber sein)
