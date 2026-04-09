# Pflichtenheft

## 1. Projekttitel

**Deutsch:** Kursablauf-Zentrale  
**Englisch:** Course Control Hub

---

## 2. Technische Plugin-Namen

- Core-Plugin: `local_coursectrl`
- Block-Plugin: `block_coursectrl`
- Subplugin-Typ im Core: `coursectrlmod`
- Subplugin-Verzeichnis: `mod`

### Beispiel-Subplugins

- `coursectrlmod_assign`
- `coursectrlmod_quiz`
- `coursectrlmod_lesson`
- `coursectrlmod_page`
- `coursectrlmod_feedback`
- `coursectrlmod_forum`
- `coursectrlmod_workshop`
- `coursectrlmod_h5pactivity`

---

## 3. Ziel des Systems

Die Kursablauf-Zentrale ist ein Moodle-Werkzeug für Lehrende zur:

- kursweiten Analyse von Aktivitäts-, Termin-, Sichtbarkeits- und Freigabelogik
- sicheren Durchführung von Bulk-Änderungen
- Erkennung und optionalen Mitbearbeitung von Datums-/Zeitangaben in Freitexten
- grafischen Darstellung von Zeitachsen, Abhängigkeiten und Bearbeitungspfaden
- Simulation von Sichtbarkeit und Zugänglichkeit aus Sicht definierter Lernenden-Konstellationen
- Erkennung von Sackgassen, Edge Cases und nicht auflösbaren Bearbeitungswegen
- revisionssicheren Protokollierung und Rücknahme von Änderungen

---

## 4. Systemkontext

Das System besteht aus:

- einem Core-Plugin `local_coursectrl` als fachlichem und technischem Hauptsystem
- einem Block-Plugin `block_coursectrl` als UI-Einstieg im Kurs
- mehreren Subplugins `coursectrlmod_*`, die die modulbezogene Logik einzelner Moodle-Aktivitätstypen kapseln

---

## 5. Zielgruppen

- Lehrende
- Kursverantwortliche
- Studiengangskoordination / E-Learning-Support
- Moodle-Administrator*innen
- Qualitätsmanagement / Kursreview

---

## 6. Muss-Anforderungen

### 6.1 Bulk-Verarbeitung

Das System muss kursweite Massenaktionen auf unterstützte Entitäten erlauben:

- Kursmodule
- Kursabschnitte
- Kursbeschreibung
- Section-Namen
- Section-Beschreibungen
- Labels
- relevante Kurs- und Section-Einstellungen

---

### 6.2 Vorschaupflicht

Vor jeder ändernden Aktion muss eine Vorschau erzeugt werden:

- alte Werte
- neue Werte
- Konflikte
- Warnungen
- nicht verarbeitbare Elemente

---

### 6.3 Terminlogik

Das System muss Datums- und Zeitfelder bearbeiten können:

- relativ verschieben
- absolut setzen
- mehrere Felder pro Aktion
- optional Wochenenden berücksichtigen
- optional Feiertage berücksichtigen

---

### 6.4 Freitext-Erkennung

Das System muss Datums- und Zeitangaben in Freitextfeldern erkennen können in:

- Aktivitätsbeschreibungen
- Label-Texten
- Section-Titeln
- Section-Beschreibungen
- Kursbeschreibung
- weiteren unterstützten Textfeldern

Die gefundenen Stellen müssen getrennt klassifiziert werden in:

- sicher transformierbar
- unsicher / mehrdeutig
- nur informativ

---

### 6.5 Grafische Analyse

Das System muss mindestens folgende visuelle Analysen bereitstellen:

- Zeitachsen-/Timeline-Darstellung
- Gantt-ähnliche Darstellung von Terminen und Zeitfenstern
- Abhängigkeitsgraph
- Vorgänger-/Nachfolgerbeziehungen
- Konfliktmarkierungen

---

### 6.6 Simulation

Das System muss die Sichtbarkeit und Erreichbarkeit für eine definierte Lernenden-Konstellation simulieren können.

**Simulationsparameter:**

- Zeit / Datum
- Gruppe / Gruppierung
- angenommene Completion-Zustände
- optionale weitere Statusannahmen

---

### 6.7 Sackgassenprüfung

Das System muss Kurspfade auf logische Sackgassen prüfen können, insbesondere:

- unerreichbare Aktivitäten
- zirkuläre Sperren
- unauflösbare Bedingungen
- fehlende nächste Handlung
- Edge Cases in Freigabeketten

---

### 6.8 Protokollierung und Rollback

Das System muss Änderungen protokollieren und, sofern technisch möglich, rückgängig machen können.

---

### 6.9 Erweiterbarkeit

Die modulbezogene Logik muss über standardisierte Subplugins gekapselt werden, damit weitere Aktivitätstypen ohne Kernumbau ergänzt werden können.

---

## 7. Kann-Anforderungen

- Presets für wiederkehrende Aktionen
- Export von Reports
- gespeicherte Prüfschemata
- optionale KI-gestützte Priorisierung von Risikofällen
- gruppenspezifische Simulationsprofile
- visuelle Vergleichsansicht vor/nach Änderung

---

## 8. Nicht-Ziele in der ersten Ausbaustufe

- vollautomatische Freitextänderung ohne Review in unsicheren Fällen
- autonome KI-Entscheidungen über Kurslogik
- vollumfängliche Unterstützung aller Moodle-Aktivitätstypen in MVP 1
- feingranulare Bewertungssimulation über alle Sonderfälle aller Module hinweg

---

## 9. Qualitätsanforderungen

- nachvollziehbar
- testbar
- erweiterbar
- fehlertolerant
- datensparsam
- rollen- und berechtigungssicher
- bei großen Kursen performant genug für produktive Nutzung

---

## 10. Datenschutz und Sicherheit

- nur berechtigte Rollen dürfen Änderungen ausführen
- Vorschau und Simulation dürfen nur kurskontextbezogen erfolgen
- Snapshots und Logs müssen auf das notwendige Maß begrenzt werden
- Texte und Änderungsstände dürfen nur im Rahmen der fachlichen Funktion gespeichert werden
- Risiko- und Prüfberichte müssen berechtigungsgeschützt sein

---

# Fachliche Funktionsblöcke

## A. Kursinventar
Erfassung aller relevanten Kursobjekte in einem normalisierten Modell.

## B. Bulk-Aktions-Engine
Durchführung von Massenänderungen mit Vorschau, Validierung und Ausführung.

## C. Text-Datetime-Engine
Analyse und Änderung von Datums-/Zeitangaben in Freitexten.

## D. Visual Analytics
Grafische Darstellung von Terminen, Abhängigkeiten und Freigabelogik.

## E. Simulation
Prüfung der Kurslogik aus Sicht definierter Lernenden-Konstellationen.

## F. Risiko- und Sackgassenanalyse
Deterministische Prüfung auf problematische Bearbeitungspfade.

## G. Audit / Rollback / Presets
Nachvollziehbarkeit, Wiederverwendung und sichere Rücknahme.