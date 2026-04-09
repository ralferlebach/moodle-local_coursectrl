# Kursablauf-Zentrale / Course Control Hub  
## Pflicht- und Lastenheft mit technischem Blueprint und Arbeitspaketen

---

# 1. Projekttitel

Deutsch: Kursablauf-Zentrale  
Englisch: Course Control Hub  

---

# 2. Technische Plugin-Namen

- Core-Plugin: `local_coursectrl`
- Block-Plugin: `block_coursectrl`
- Subplugin-Typ im Core: `coursectrlmod`
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
- Simulation von Sichtbarkeit und Zugänglichkeit aus Sicht definierter Lernenden-Konstellationen
- Erkennung von Sackgassen, Edge Cases und nicht auflösbaren Bearbeitungswegen
- revisionssicheren Protokollierung und Rücknahme von Änderungen

---

# 4. Systemkontext

Das System besteht aus:

- `local_coursectrl` als Core
- `block_coursectrl` als UI-Einstieg
- mehreren Subplugins `coursectrlmod_*`

---

# 5. Zielgruppen

- Lehrende
- Kursverantwortliche
- Studiengangskoordination / E-Learning-Support
- Moodle-Administrator*innen
- Qualitätsmanagement

---

# 6. Muss-Anforderungen

## 6.1 Bulk-Verarbeitung

- Kursmodule
- Kursabschnitte
- Kursbeschreibung
- Section-Namen
- Section-Beschreibungen
- Labels
- relevante Einstellungen

## 6.2 Vorschaupflicht

- alte Werte
- neue Werte
- Konflikte
- Warnungen

## 6.3 Terminlogik

- relativ verschieben
- absolut setzen
- mehrere Felder
- Wochenenden optional
- Feiertage optional

## 6.4 Freitext-Erkennung

Unterstützt:

- Aktivitätsbeschreibungen
- Labels
- Sections
- Kursbeschreibung

Klassifikation:

- sicher
- unsicher
- informativ

## 6.5 Grafische Analyse

- Timeline
- Gantt
- Abhängigkeitsgraph
- Vorgänger/Nachfolger

## 6.6 Simulation

Parameter:

- Zeit
- Gruppe
- Completion

## 6.7 Sackgassenprüfung

- unerreichbare Aktivitäten
- Zyklen
- fehlende nächste Schritte

## 6.8 Protokollierung

- Logging
- Rollback

## 6.9 Erweiterbarkeit

- Subplugin-basierte Architektur

---

# 7. Kann-Anforderungen

- Presets
- Reports
- KI-Unterstützung
- Simulationsprofile

---

# 8. Nicht-Ziele

- automatische Freitextänderung ohne Review
- KI als alleinige Entscheidungsinstanz
- vollständige Modulabdeckung im MVP

---

# 9. Qualitätsanforderungen

- nachvollziehbar
- testbar
- erweiterbar
- performant

---

# 10. Datenschutz

- rollenbasiert
- minimaler Datenbestand
- sichere Logs

---

# Fachliche Funktionsblöcke

## A. Kursinventar  
## B. Bulk-Engine  
## C. Text-Datetime  
## D. Visualisierung  
## E. Simulation  
## F. Risikoanalyse  
## G. Audit / Rollback  

---

# Technischer Blueprint

## Architektur

- Core: `local_coursectrl`
- Block: `block_coursectrl`
- Adapter: `coursectrlmod_*`

## Prinzip

- Modullogik in Subplugins
- Kurslogik im Core

---

# Verzeichnisbaum (gekürzt)

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