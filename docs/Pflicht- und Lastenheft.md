# Kursablauf-Zentrale / Course Control Hub  
## Pflicht- und Lastenheft mit technischem Blueprint und Arbeitspaketen

(komplette konsolidierte Version, Subplugin-Typ: coursectrlmod)

---

# 1. Projekttitel
Deutsch: Kursablauf-Zentrale  
Englisch: Course Control Hub  

---

# 2. Technische Plugin-Namen
- Core-Plugin: `local_coursectrl`
- Block-Plugin: `block_coursectrl`
- Subplugin-Typ: `coursectrlmod`
- Subplugin-Verzeichnis: `mod`

## Beispiel-Subplugins
- `coursectrlmod_assign`
- `coursectrlmod_quiz`
- `coursectrlmod_lesson`
- `coursectrlmod_page`
- `coursectrlmod_feedback`
- `coursectrlmod_forum`
- `coursectrlmod_workshop`
- `coursectrlmod_h5pactivity`

---

# 3. Ziel des Systems
Die Kursablauf-Zentrale ist ein Moodle-Werkzeug für Lehrende zur:

- kursweiten Analyse von Aktivitäts-, Termin-, Sichtbarkeits- und Freigabelogik
- sicheren Durchführung von Bulk-Änderungen
- Erkennung und optionalen Mitbearbeitung von Datums-/Zeitangaben in Freitexten
- grafischen Darstellung von Zeitachsen, Abhängigkeiten und Bearbeitungspfaden
- Simulation von Sichtbarkeit und Zugänglichkeit
- Erkennung von Sackgassen und Edge Cases
- revisionssicheren Protokollierung und Rücknahme von Änderungen

---

# 4. Systemkontext
- Core: `local_coursectrl`
- Block: `block_coursectrl`
- Subplugins: `coursectrlmod_*`

---

# 5. Zielgruppen
- Lehrende
- Kursverantwortliche
- Admins
- QM

---

# 6. Muss-Anforderungen
## Bulk
- Module, Sections, Labels, Texte

## Vorschau
- alt/neu
- Konflikte

## Termine
- shift / set

## Freitext
- Erkennung + Klassifikation

## Visualisierung
- Timeline, Gantt, Graph

## Simulation
- Zustandssimulation

## Sackgassenprüfung
- Dead ends

## Audit
- Logging + Rollback

## Erweiterbarkeit
- Subplugins

---

# 7. Kann-Anforderungen
- Presets
- Reports
- KI optional

---

# 8. Nicht-Ziele
- blindes Text-Rewriting
- vollständige Modulabdeckung im MVP

---

# 9. Qualitätsanforderungen
- testbar
- erweiterbar
- performant

---

# 10. Datenschutz
- rollenbasiert
- minimal

---

# Fachliche Blöcke
- Inventar
- Bulk
- Text
- Visualisierung
- Simulation
- Risiko
- Audit

---

# Technischer Blueprint

## Prinzipien
- Core first
- Adapter je Modul
- deterministisch

---

# Verzeichnisstruktur

```text
local/coursectrl/
  classes/
  db/
  templates/
  amd/
  lang/
  mod/
    assign/
    quiz/
    lesson/
    page/
    feedback/
    forum/
    workshop/
    h5pactivity/
```

---

# Adapter-Interface

```php
interface activity_adapter {
    public static function component(): string;
    public function preview_action(...);
    public function execute_action(...);
}
```

---

# Datenmodell
- batch
- batch_item
- snapshot
- preset
- report
- text_hit
- risk

---

# Funktionen
F1 Inventar  
F2 Auswahl  
F3 Vorschau  
F4 Termine  
F5 Text  
F6 Visualisierung  
F7 Simulation  
F8 Risiko  
F9 Rollback  

---

# Arbeitsplan

## Phase 1
Skeleton

## Phase 2
Inventar

## Phase 3
Adapter

## Phase 4
Bulk

## Phase 5
Text

## Phase 6
Visualisierung

## Phase 7
Simulation

## Phase 8
Risiko

## Phase 9
Audit

## Phase 10
Härtung

---

# MVP

## MVP1
- Bulk Dates

## MVP2
- Text + Timeline

## MVP3
- Simulation

---

# Fazit
System = Bulk + Analyse + Simulation + QA
