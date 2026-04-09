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

### 6.2 Vorschaupflicht

Vor jeder ändernden Aktion muss eine Vorschau erzeugt werden:

- alte Werte
- neue Werte
- Konflikte
- Warnungen
- nicht verarbeitbare Elemente

### 6.3 Terminlogik

Das System muss Datums- und Zeitfelder bearbeiten können:

- relativ verschieben
- absolut setzen
- mehrere Felder pro Aktion
- optional Wochenenden berücksichtigen
- optional Feiertage berücksichtigen

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

### 6.5 Grafische Analyse

Das System muss mindestens folgende visuelle Analysen bereitstellen:

- Zeitachsen-/Timeline-Darstellung
- Gantt-ähnliche Darstellung von Terminen und Zeitfenstern
- Abhängigkeitsgraph
- Vorgänger-/Nachfolgerbeziehungen
- Konfliktmarkierungen

### 6.6 Simulation

Das System muss die Sichtbarkeit und Erreichbarkeit für eine definierte Lernenden-Konstellation simulieren können.

**Simulationsparameter:**

- Zeit / Datum
- Gruppe / Gruppierung
- angenommene Completion-Zustände
- optionale weitere Statusannahmen

### 6.7 Sackgassenprüfung

Das System muss Kurspfade auf logische Sackgassen prüfen können, insbesondere:

- unerreichbare Aktivitäten
- zirkuläre Sperren
- unauflösbare Bedingungen
- fehlende nächste Handlung
- Edge Cases in Freigabeketten

### 6.8 Protokollierung und Rollback

Das System muss Änderungen protokollieren und, sofern technisch möglich, rückgängig machen können.

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

---

# Konkreter technischer Blueprint

## 1. Architekturprinzip

### 1.1 Gesamtarchitektur

- `local_coursectrl` enthält die gesamte Fachlogik.
- `block_coursectrl` dient als kurssichtbarer Einstiegspunkt.
- `coursectrlmod_*` kapseln modulbezogene Speziallogik.
- Kurs-, Abschnitts-, Label- und Textlogik verbleibt im Core.

### 1.2 Schichten

- Presentation Layer
- Application Layer
- Domain Layer
- Adapter Layer
- Persistence Layer

### 1.3 Kerngrundsatz

Modulspezifisches Verhalten in Subplugins, kursweite und textuelle Logik im Core.

---

## 2. Verzeichnisbaum

```text
local/coursectrl/
  version.php
  settings.php
  index.php
  manage.php
  preview.php
  execute.php
  reports.php
  simulation.php
  history.php
  rollback.php

  db/
    access.php
    install.xml
    services.php
    subplugins.json
    events.php
    upgrade.php

  classes/
    manager/
      registry.php
      inventory_manager.php
      workflow_manager.php
      preview_manager.php
      batch_manager.php
      rollback_manager.php
      preset_manager.php
      report_manager.php
      simulation_manager.php
      textreview_manager.php

    local/
      contract/
        activity_adapter.php
        inventory_provider.php
        report_provider.php

      entity/
        inventory_item.php
        course_item.php
        section_item.php
        cm_item.php
        text_item.php
        batch_item.php
        learner_state.php
        graph_node.php
        graph_edge.php
        risk_item.php
        text_hit.php

      dto/
        preview_change.php
        execution_result.php
        validation_result.php
        simulation_result.php
        timeline_result.php
        graph_result.php
        rollback_result.php

      inventory/
        inventory_service.php
        entity_normalizer.php
        section_inventory.php
        course_inventory.php
        label_inventory.php
        text_inventory.php

      text/
        text_datetime_extractor.php
        text_datetime_parser.php
        text_datetime_rewriter.php
        text_hit_classifier.php
        text_change_builder.php

      analysis/
        course_graph_builder.php
        dependency_graph_builder.php
        temporal_conflict_detector.php
        reachability_analyzer.php
        dead_end_detector.php
        escape_path_checker.php
        consistency_runner.php
        risk_prioritizer.php

      simulation/
        condition_evaluator.php
        visibility_simulator.php
        learner_state_factory.php
        next_step_engine.php
        scenario_builder.php

      visualization/
        timeline_builder.php
        gantt_dataset_builder.php
        graph_dataset_builder.php
        heatmap_dataset_builder.php

      persistent/
        batch.php
        batch_item.php
        snapshot.php
        preset.php
        report.php
        text_hit.php
        risk.php

      form/
        selector_form.php
        bulk_action_form.php
        text_review_form.php
        simulation_form.php
        check_profile_form.php
        rollback_form.php
        preset_form.php

      output/
        renderer.php
        dashboard_page.php
        preview_page.php
        report_page.php
        simulation_page.php
        history_page.php

    external/
      get_inventory.php
      preview_bulk_action.php
      execute_bulk_action.php
      get_text_hits.php
      apply_text_changes.php
      get_timeline.php
      get_dependency_graph.php
      run_checks.php
      run_simulation.php
      get_batch_history.php
      rollback_batch.php
      save_preset.php
      load_preset.php

    event/
      batch_created.php
      batch_executed.php
      batch_rolled_back.php
      report_generated.php

    privacy/
      provider.php

  templates/
    dashboard.mustache
    selector.mustache
    preview.mustache
    textreview.mustache
    timeline.mustache
    graph.mustache
    simulation.mustache
    history.mustache
    risks.mustache

  amd/src/
    dashboard.js
    selector.js
    preview.js
    textreview.js
    timeline.js
    graphview.js
    simulation.js
    history.js
    ajax.js

  lang/
    de/local_coursectrl.php
    en/local_coursectrl.php

  mod/
    assign/
      version.php
      classes/
        adapter.php
        field_map.php
        validator.php
        checker.php
      lang/de/coursectrlmod_assign.php
      lang/en/coursectrlmod_assign.php

    quiz/
      version.php
      classes/
        adapter.php
        field_map.php
        validator.php
        checker.php
      lang/de/coursectrlmod_quiz.php
      lang/en/coursectrlmod_quiz.php

    lesson/
      version.php
      classes/
        adapter.php
        field_map.php
        validator.php
        checker.php

    page/
      version.php
      classes/
        adapter.php
        field_map.php
        validator.php
        checker.php

    feedback/
      version.php
      classes/
        adapter.php
        field_map.php
        validator.php
        checker.php

    forum/
      version.php
      classes/
        adapter.php
        field_map.php
        validator.php
        checker.php

    workshop/
      version.php
      classes/
        adapter.php
        field_map.php
        validator.php
        checker.php

    h5pactivity/
      version.php
      classes/
        adapter.php
        field_map.php
        validator.php
        checker.php
```

### Block-Plugin

```text
blocks/coursectrl/
  version.php
  block_coursectrl.php
  db/access.php
  lang/de/block_coursectrl.php
  lang/en/block_coursectrl.php
  templates/block.mustache
  classes/output/renderer.php
```

---

## 3. Standardisierte Adapter-Schnittstelle

```php
<?php
namespace local_coursectrl\local\contract;

interface activity_adapter {
    public static function component(): string;
    public function is_available(): bool;
    public function get_supported_actions(): array;
    public function get_supported_fields(): array;
    public function get_instances_for_course(int $courseid, array $filters = []): array;
    public function describe_instance(int $cmid): array;
    public function validate_action(string $action, array $payload, array $cmids): array;
    public function preview_action(string $action, array $payload, array $cmids): array;
    public function execute_action(string $action, array $payload, array $cmids, int $userid): array;
    public function export_state(int $cmid): array;
    public function restore_state(array $state): array;
    public function run_checks(array $cmids, array $profile = []): array;
    public function get_dependency_hints(array $cmids): array;
}
```

### Unterstützte Kernaktionen

- `shift_dates`
- `set_dates`
- `set_visibility`
- `set_completion`
- `set_availability`
- `copy_settings_from_reference`
- `run_checks`

---

## 4. Datenmodell

### 1. `local_coursectrl_batch`

Kopfdatensatz einer Aktion. Felder: `id`, `courseid`, `userid`, `action`, `payloadjson`, `status`, `timecreated`, `timemodified`.

### 2. `local_coursectrl_batch_item`

Ergebnis je betroffenem Objekt. Felder: `id`, `batchid`, `entitytype`, `entityid`, `component`, `status`, `previewjson`, `resultjson`.

### 3. `local_coursectrl_snapshot`

Rollback-Basis. Felder: `id`, `batchid`, `entitytype`, `entityid`, `component`, `statejson`, `timecreated`.

### 4. `local_coursectrl_preset`

Gespeicherte Vorlagen. Felder: `id`, `userid`, `courseid`, `name`, `description`, `action`, `configjson`, `scope`.

### 5. `local_coursectrl_report`

Analyse- und Prüfberichte. Felder: `id`, `courseid`, `userid`, `reporttype`, `configjson`, `resultjson`, `timecreated`.

### 6. `local_coursectrl_text_hit`

Gefundene Datums-/Zeitstelle in Text. Felder: `id`, `courseid`, `entitytype`, `entityid`, `fieldname`, `matchedtext`, `normalizedvalue`, `confidence`, `contextjson`.

### 7. `local_coursectrl_risk`

Risikofund / Konflikt / Sackgassenhinweis. Felder: `id`, `courseid`, `risktype`, `severity`, `entitytype`, `entityid`, `detailsjson`, `timecreated`.

---

## 5. Formale Funktionsspezifikation

- **F1 – Kursinventar:** Das System muss alle relevanten Kursobjekte inventarisieren und normalisieren. Input: `courseid`. Output: normalisierte Entitätsliste.
- **F2 – Bulk-Auswahl:** Das System muss filterbare und persistente Zielmengen für Bulk-Aktionen bereitstellen.
- **F3 – Vorschau:** Das System muss eine konfliktsensitive Vorschau jeder Änderung erzeugen.
- **F4 – Terminänderung:** Das System muss strukturierte Datumsfelder modulübergreifend ändern können.
- **F5 – Text-Datetime-Erkennung:** Das System muss Datums-/Zeitstellen in Freitexten erkennen, bewerten und zur Review bereitstellen.
- **F6 – Text-Datetime-Änderung:** Das System muss bestätigte Texttreffer in nachvollziehbarer Weise aktualisieren können.
- **F7 – Visualisierung:** Das System muss visuelle Darstellungen für Zeit, Abhängigkeiten und Konflikte generieren.
- **F8 – Lernenden-Simulation:** Das System muss Sichtbarkeit und Erreichbarkeit aus Sicht definierter Zustände simulieren.
- **F9 – Konsistenz- und Sackgassenanalyse:** Das System muss logische Fehlkonfigurationen und nicht auflösbare Pfade erkennen.
- **F10 – Audit / Rollback:** Das System muss ausgeführte Aktionen nachvollziehbar protokollieren und rückgängig machen können.

---

## 6. Schrittweise Arbeitsplanung

### Phase 0 – Projektvorbereitung

**Ziel:** Grundlagen, Abgrenzung, technische Leitentscheidungen.

**Arbeitsschritte:** Projektvision final abstimmen; Namenskonventionen festziehen; Rollen- und Rechtekonzept definieren; Ziel-Moodle-Versionen festlegen; Coding-Standards und Branching-Strategie festlegen; MVP-Scope definieren; Teststrategie definieren; Review- und Freigabeprozess definieren.

**Deliverables:** Architekturentscheidungsdokument; MVP-Scope; Rechtekonzept; Technische Guideline.

### Phase 1 – Skeleton und Infrastruktur

**Ziel:** lauffähiges Grundgerüst.

**Arbeitsschritte:** `local_coursectrl`-Skeleton anlegen; `block_coursectrl`-Skeleton anlegen; `db/subplugins.json` einrichten; `db/access.php` anlegen; `db/install.xml` mit Basistabellen erstellen; Basisnavigation und Einstiegsseiten anlegen; Renderer- und Template-Grundgerüst anlegen; Registry-Mechanismus implementieren; Persistents für Batch, Snapshot, Preset, Report anlegen; Minimaltest für Installation und Grundnavigation erstellen.

**Deliverables:** installierbares Plugin-Grundgerüst; funktionierende Navigation; Basistabellen; Registry.

### Phase 2 – Inventar und Selektionslogik

**Ziel:** Kurse vollständig und normalisiert erfassen.

**Arbeitsschritte:** `inventory_service` implementieren; Kursobjekte inventarisieren; Sections inventarisieren; Labels inventarisieren; Texte inventarisieren; Filterlogik implementieren; Selektionszustand modellieren; Inventar-UI aufbauen; AJAX-Endpunkt `get_inventory`; UI-Tests mit kleinen und großen Kursen.

**Deliverables:** vollständige Inventarliste; Filter- und Auswahloberfläche; normalisierte Entitätsmodelle.

### Phase 3 – Adapter-Basis und erste Aktivitätstypen

**Ziel:** modulbezogene Logik anschließen.

**Arbeitsschritte:** Adapter-Interface finalisieren; Basisklasse für Adapter implementieren; `coursectrlmod_assign` umsetzen; `coursectrlmod_quiz` umsetzen; `coursectrlmod_feedback` umsetzen; Field-Mappings definieren; Validatoren je Adapter anlegen; Preview-Rückgabe standardisieren; modulspezifische Checks vorbereiten; Integration in Registry testen.

**Deliverables:** 3 erste produktive Adapter; standardisierte Adapter-API; modulbezogene Vorschau- und Ausführungslogik.

### Phase 4 – Bulk-Engine für strukturierte Felder

**Ziel:** sichere Termin-Massenänderung.

**Arbeitsschritte:** `preview_manager` implementieren; `batch_manager` implementieren; `rollback_manager` vorbereiten; Aktion `shift_dates` implementieren; Aktion `set_dates` implementieren; Snapshot-Erzeugung integrieren; Vorschau-Tabellen aufbauen; Ausführungsworkflow absichern; Ergebnisreporting ergänzen; Testfälle für Datumskonflikte erstellen.

**Deliverables:** funktionierende Bulk-Vorschau; produktive strukturierte Datumsänderung; Batch- und Snapshot-Mechanik.

### Phase 5 – Freitext-Zeit-/Datumsanalyse

**Ziel:** textuelle Termine erkennen und kontrolliert mitverändern.

**Arbeitsschritte:** `text_datetime_extractor` implementieren; Normalisierung von Treffern implementieren; Confidence-/Ambiguitätslogik entwickeln; unterstützte Textquellen anbinden; `text_review_form` entwickeln; `get_text_hits`-Service implementieren; `apply_text_changes`-Service implementieren; Textänderungs-Vorschau integrieren; Text-Snapshots für Rollback ergänzen; Tests für sichere/unsichere Treffer erstellen.

**Deliverables:** Freitext-Erkennung; Review-Oberfläche; kontrollierte Textänderung.

### Phase 6 – Visualisierung

**Ziel:** Kurslogik visuell erfassbar machen.

**Arbeitsschritte:** `timeline_builder` implementieren; Gantt-Datenmodell definieren; `dependency_graph_builder` implementieren; Konfliktmarkierungen aufbauen; Timeline-Frontend umsetzen; Graph-Frontend umsetzen; Drilldown für einzelne Knoten ergänzen; Exportoptionen für Visualisierungen ergänzen; UI-Performance für große Kurse testen; Nutzbarkeit mit realen Kursdaten prüfen.

**Deliverables:** Timeline/Gantt; Abhängigkeitsgraph; visuelle Konfliktanzeige.

### Phase 7 – Lernenden-Simulation

**Ziel:** Sichtbarkeit und Erreichbarkeit aus konkreter Perspektive simulieren.

**Arbeitsschritte:** `learner_state`-Modell definieren; `condition_evaluator` implementieren; `visibility_simulator` implementieren; `next_step_engine` implementieren; `simulation_form` entwickeln; `run_simulation`-Service implementieren; Ergebnisdarstellung als Liste und Graph; Begründungen pro Sperre ausgeben; Simulationsprofile speichern; Grenzfälle je Aktivitätstyp testen.

**Deliverables:** funktionierende Kurszustandssimulation; Sichtbarkeitsdarstellung; Next-Step-Auswertung.

### Phase 8 – Risiko-, Konsistenz- und Sackgassenanalyse

**Ziel:** logische Fehlkonfigurationen zuverlässig erkennen.

**Arbeitsschritte:** `consistency_runner` implementieren; `reachability_analyzer` entwickeln; `dead_end_detector` entwickeln; `escape_path_checker` entwickeln; Risiko-Klassifikation und Severity-Modell festlegen; `run_checks`-Service implementieren; Risikopanel im UI umsetzen; direkte Verknüpfung von Fund → Korrektur vorbereiten; Prüfprofile anlegen; Tests mit problematischen Beispielszenarien aufbauen.

**Deliverables:** deterministische Risikoanalyse; Sackgassenprüfung; strukturierte Risiko-Reports.

### Phase 9 – Presets, Historie, Audit, Rollback

**Ziel:** produktionsreife Nutzung.

**Arbeitsschritte:** Preset-Manager implementieren; Historienansicht aufbauen; Rollback auf Batch-Ebene finalisieren; Teil-Rollback für Text / Struktur trennen; Audit-Renderer ergänzen; Exportfunktionen ergänzen; Eventing vervollständigen; Aufbewahrungslogik definieren; Datenschutzreview durchführen; Praxis-Tests mit Administrator- und Lehrendenrollen.

**Deliverables:** Presets; Historie; Rollback; Audit-Ansicht.

### Phase 10 – Härtung und Ausbau

**Ziel:** Produktionsqualität und weitere Aktivitätstypen.

**Arbeitsschritte:** `forum`-Adapter fertigstellen; `lesson`-Adapter fertigstellen; `page`-Adapter fertigstellen; `h5pactivity`-Adapter fertigstellen; `workshop`-Adapter mit erhöhter Prüftiefe fertigstellen; Performance-Profiling durchführen; Behat-/PHPUnit-Tests ausbauen; UI-/UX-Feinschliff; Dokumentation für Lehrende und Admins erstellen; Release- und Migrationspaket vorbereiten.

**Deliverables:** vollständige Erstversion; erweiterte Adapterabdeckung; Dokumentation; Release Candidate.

---

## 7. Priorisierte MVP-Definition

### MVP 1

- Skeleton
- Registry
- Inventar
- Auswahl
- Adapter: `assign`, `quiz`, `feedback`
- strukturierte Datumsverschiebung
- Vorschau
- Snapshots light

### MVP 2

- Text-Datetime-Erkennung
- Textreview
- Timeline/Gantt
- Presets
- Historie

### MVP 3

- Simulation
- Risiko-/Sackgassenprüfung
- weitere Adapter
- Rollback vollständig
