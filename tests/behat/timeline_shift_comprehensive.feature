@local @local_coursectrl @local_coursectrl_shift_workflow
Feature: Timeline date shift – slot, following, and entry modes
  As a teacher
  I want to shift course activity dates from the timeline
  So that I can reschedule a course efficiently and update related text references

  # ── Fixture ───────────────────────────────────────────────────────────────
  # All dates are stable absolute Unix timestamps, not relative expressions,
  # so the tests remain valid regardless of when they are run.
  #
  #   1781481600  =  2026-06-15 00:00 UTC  (assign Aufgabe-A duedate)
  #   1781568000  =  2026-06-16 00:00 UTC  (expected shifted value for +1 day)
  #   1781913600  =  2026-06-20 00:00 UTC  (quiz timeopen)
  #   1782000000  =  2026-06-21 00:00 UTC  (expected shifted quiz value for +1 day)
  #   1781481600                           (forum completionexpected, same as assign)
  #
  # The assign intro deliberately contains "15.06.2026" and "am 15. Juni 2026"
  # as text date references the scanner can find.

  Background:
    Given the following "courses" exist:
      | fullname      | shortname | enablecompletion | startdate  |
      | Shift Course  | SHIFTCRS  | 1                | 1780000000 |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
    And the following "course enrolments" exist:
      | user     | course   | role           |
      | teacher1 | SHIFTCRS | editingteacher |
    And the following "activities" exist:
      | activity | course   | name       | intro                                                            | duedate    |
      | assign   | SHIFTCRS | Aufgabe-A  | Abgabe am 15.06.2026. Weitere Frist am 15. Juni 2026 um 10 Uhr. | 1781481600 |
    And the following "activities" exist:
      | activity | course   | name   | timeopen   | timeclose  |
      | quiz     | SHIFTCRS | Quiz-B | 1781913600 | 1782000000 |
    And the following "activities" exist:
      | activity | course   | name    | intro                                     |
      | forum    | SHIFTCRS | Forum-C | Diskussion startet am 15.06.2026 um 10:00 |
    And the completionexpected of forum Forum-C in course SHIFTCRS is 1781481600

  # ══════════════════════════════════════════════════════════════════════════
  # MUSTER 1 — Modal öffnen, Steuerelemente prüfen, Abbrechen
  # ══════════════════════════════════════════════════════════════════════════

  @javascript
  Scenario: M1-Slot – Shift-Modal öffnet sich und schließt sich wieder sauber
    Given I log in as "teacher1"
    And I am on the timeline page for course "SHIFTCRS"
    # Modal ist anfangs geschlossen.
    Then the shift modal should be closed
    # Slot-Button klicken → Modal öffnet sich.
    When I click the first slot shift button on the timeline
    Then the shift modal should be visible
    And I should see "Vorschau"
    # Alle Pflicht-Steuerelemente sind vorhanden.
    And "coursectrl-shift-delta-days" "field" should exist
    And "coursectrl-shift-delta-hours" "field" should exist
    And "coursectrl-shift-delta-minutes" "field" should exist
    And the followdeps checkbox should be present and unchecked
    And "[data-ccwf-action='preview']" "css_element" should exist
    And "[data-action='close-dialog']" "css_element" should exist
    # Eintragszahl ist in der Beschriftung sichtbar (≥1 Einträge).
    And I should see "Einträge"
    # Abbrechen → Modal schließt sich.
    When I click on "[data-action='close-dialog']" "css_element"
    Then the shift modal should be closed
    # Seite ist wieder frei — Navigation funktioniert.
    When I am on the "Dashboard" page
    Then I should see "Dashboard"

  @javascript
  Scenario: M1-Entry – Shift-Entry-Modal öffnet sich und schließt sich wieder sauber
    Given I log in as "teacher1"
    And I am on the timeline page for course "SHIFTCRS"
    When I click the entry shift button for assign on the timeline
    Then the shift modal should be visible
    And I should see "Aufgabe-A"
    # Abbrechen
    When I click on "[data-action='close-dialog']" "css_element"
    Then the shift modal should be closed
    When I am on the "Dashboard" page
    Then I should see "Dashboard"

  # ══════════════════════════════════════════════════════════════════════════
  # MUSTER 2 — Vollständiger Shift-Workflow ohne Textprüfung
  # ══════════════════════════════════════════════════════════════════════════

  @javascript
  Scenario: M2-Slot – Zeitfenster um 1 Tag verschieben, DB-Prüfung
    Given I log in as "teacher1"
    And I am on the timeline page for course "SHIFTCRS"
    When I click the first slot shift button on the timeline
    Then the shift modal should be visible
    # Delta einstellen: +1 Tag.
    When I set the shift days to 1
    And I set the shift hours to 0
    And I set the shift minutes to 0
    And I click the shift preview button and wait
    # Vorschau: Anzahl der Änderungen und Aktivitätsname prüfen.
    Then the shift preview summary should contain "Felder in"
    And the shift preview should contain a assign activity icon
    And I should see "Aufgabe-A" in the "[data-ccwf-preview-body]" "css_element"
    # (i)-Button zeigt Detailzeile.
    When I click the preview info button 1
    Then I should see "→" in the "[data-ccwf-preview-body]" "css_element"
    # Verschiebung anwenden.
    When I apply the shift and wait
    Then the shift modal should show 1 shifted entry message
    # "Schließen" — der Back-Button ist jetzt zum Schließen umgebaut.
    When I click on "[data-ccwf-action='back']" "css_element"
    And I wait "3" seconds
    # DB-Prüfung: Aufgabe-A wurde um 1 Tag verschoben.
    Then the duedate of assign Aufgabe-A in course SHIFTCRS should be shifted by 1 day(s) from 1781481600

  @javascript
  Scenario: M2-Following – Folgende Termine um 1 Tag verschieben
    Given I log in as "teacher1"
    And I am on the timeline page for course "SHIFTCRS"
    When I click the first following shift button on the timeline
    Then the shift modal should be visible
    When I set the shift days to 1
    And I click the shift preview button and wait
    Then the shift preview summary should contain "Felder in"
    When I apply the shift and wait
    Then the shift modal should show 1 shifted entry message
    When I click on "[data-ccwf-action='back']" "css_element"
    And I wait "3" seconds
    Then the duedate of assign Aufgabe-A in course SHIFTCRS should be shifted by 1 day(s) from 1781481600

  @javascript
  Scenario: M2-Entry-Assign – Einzeltermin (duedate) um 1 Tag verschieben
    Given I log in as "teacher1"
    And I am on the timeline page for course "SHIFTCRS"
    When I click the entry shift button for assign on the timeline
    Then the shift modal should be visible
    # Aktivitätsname ist im Modal-Ziel sichtbar.
    And I should see "Aufgabe-A"
    When I set the shift days to 1
    And I click the shift preview button and wait
    Then the shift preview summary should contain "1 Felder in 1 Aktivität"
    And the shift preview should contain a assign activity icon
    And I should see "Aufgabe-A" in the "[data-ccwf-preview-body]" "css_element"
    When I click the preview info button 1
    # Feldbezeichnung ist kein rohes "duedate" mehr, sondern ein lokalisierter Name.
    Then I should not see "duedate" in the "[data-ccwf-preview-body]" "css_element"
    And I should see "→" in the "[data-ccwf-preview-body]" "css_element"
    When I apply the shift and wait
    Then the shift modal should show 1 shifted entry message
    When I click on "[data-ccwf-action='back']" "css_element"
    And I wait "3" seconds
    Then the duedate of assign Aufgabe-A in course SHIFTCRS should be shifted by 1 day(s) from 1781481600

  @javascript
  Scenario: M2-Entry-Forum-completionexpected – CM-Level-Feld um 1 Tag verschieben
    Given I log in as "teacher1"
    And I am on the timeline page for course "SHIFTCRS"
    When I click the entry shift button for forum on the timeline
    Then the shift modal should be visible
    And I should see "Forum-C"
    When I set the shift days to 1
    And I click the shift preview button and wait
    Then the shift preview summary should contain "1 Felder in 1 Aktivität"
    And the shift preview should contain a forum activity icon
    # Feldname "completionexpected" wird als lokalisierter Label angezeigt.
    When I click the preview info button 1
    Then I should not see "completionexpected" in the "[data-ccwf-preview-body]" "css_element"
    When I apply the shift and wait
    Then the shift modal should show 1 shifted entry message
    When I click on "[data-ccwf-action='back']" "css_element"
    And I wait "3" seconds
    Then the completionexpected of forum Forum-C in course SHIFTCRS should be shifted by 1 day(s) from 1781481600

  @javascript
  Scenario: M2-Slot-mit-Followdeps – Checkbox aktivieren und Shift ausführen
    Given I log in as "teacher1"
    And I am on the timeline page for course "SHIFTCRS"
    When I click the first slot shift button on the timeline
    Then the shift modal should be visible
    # Followdeps-Checkbox aktivieren.
    When I enable the followdeps checkbox in the shift modal
    And I set the shift days to 1
    And I click the shift preview button and wait
    Then the shift preview summary should contain "Felder in"
    When I apply the shift and wait
    Then the shift modal should show 1 shifted entry message
    When I click on "[data-ccwf-action='back']" "css_element"
    And I wait "3" seconds
    Then the duedate of assign Aufgabe-A in course SHIFTCRS should be shifted by 1 day(s) from 1781481600

  @javascript
  Scenario: M2-Entry-Followdeps – Einzeltermin mit abhängigen Aktivitäten verschieben
    Given I log in as "teacher1"
    And I am on the timeline page for course "SHIFTCRS"
    When I click the entry shift button for assign on the timeline
    Then the shift modal should be visible
    When I enable the followdeps checkbox in the shift modal
    And I set the shift days to 1
    And I click the shift preview button and wait
    Then the shift preview summary should contain "Felder in"
    When I apply the shift and wait
    Then the shift modal should show 1 shifted entry message
    When I click on "[data-ccwf-action='back']" "css_element"
    And I wait "3" seconds
    Then the duedate of assign Aufgabe-A in course SHIFTCRS should be shifted by 1 day(s) from 1781481600

  # ══════════════════════════════════════════════════════════════════════════
  # MUSTER 3 — Shift-Workflow mit Textprüfung
  # ══════════════════════════════════════════════════════════════════════════

  @javascript
  Scenario: M3-Entry – Shift mit Textprüfung: Modal prüfen, Textänderungen anwenden
    Given I log in as "teacher1"
    And I am on the timeline page for course "SHIFTCRS"
    When I click the entry shift button for assign on the timeline
    Then the shift modal should be visible
    When I set the shift days to 1
    And I click the shift preview button and wait
    Then the shift preview summary should contain "1 Felder in 1 Aktivität"
    # Textprüfung aktivieren.
    When I enable the text review checkbox in the shift preview
    And I apply the shift and wait
    # Schritt 3: Textprüfungs-Modal erscheint.
    Then the text review step should be visible in the shift modal
    # Aktivitätsname und Icon sichtbar.
    And I should see "Aufgabe-A" in the "[data-ccwf-step='3']" "css_element"
    And "[data-ccwf-step='3'] img[src*='mod_assign']" "css_element" should exist
    # Datumsreferenzen sind sichtbar (der Scanner hat Text gefunden).
    And I should see "15.06.2026" in the "[data-ccwf-step='3']" "css_element"
    # Kontextvorschau per (i)-Button erweitern.
    When I click the first context button in the text review step
    # Erweiterter Kontext ist sichtbar (ccwf-ctx-long nicht mehr d-none).
    Then "[data-ccwf-step='3'] .ccwf-ctx-long:not(.d-none)" "css_element" should exist
    # Erste Einträge auswählen und Textänderungen anwenden.
    When I select the first 2 text hit checkboxes
    And I apply the selected text changes and wait
    # Nach dem Anwenden: Shift-Dialog schließt und Seite lädt neu.
    And I wait "3" seconds
    # Zum Textprüfungs-Tab navigieren und prüfen, ob Ergebnisse neu gescannt wurden.
    When I am on the textreview tab for course "SHIFTCRS"
    Then I should see "Textprüfung"
    And I should not see "Fehler"

  @javascript
  Scenario: M3-Slot-mit-Followdeps-und-Textprüfung
    Given I log in as "teacher1"
    And I am on the timeline page for course "SHIFTCRS"
    When I click the first slot shift button on the timeline
    Then the shift modal should be visible
    When I enable the followdeps checkbox in the shift modal
    And I set the shift days to 1
    And I click the shift preview button and wait
    Then the shift preview summary should contain "Felder in"
    When I enable the text review checkbox in the shift preview
    And I apply the shift and wait
    Then the text review step should be visible in the shift modal
    And I should see "Aufgabe-A" in the "[data-ccwf-step='3']" "css_element"
    When I apply the selected text changes and wait
    And I wait "3" seconds
    When I am on the textreview tab for course "SHIFTCRS"
    Then I should see "Textprüfung"

  @javascript
  Scenario: M3-Following-mit-Textprüfung
    Given I log in as "teacher1"
    And I am on the timeline page for course "SHIFTCRS"
    When I click the first following shift button on the timeline
    Then the shift modal should be visible
    When I set the shift days to 1
    And I click the shift preview button and wait
    Then the shift preview summary should contain "Felder in"
    When I enable the text review checkbox in the shift preview
    And I apply the shift and wait
    Then the text review step should be visible in the shift modal
    When I apply the selected text changes and wait
    And I wait "3" seconds
    When I am on the textreview tab for course "SHIFTCRS"
    Then I should see "Textprüfung"

  # ══════════════════════════════════════════════════════════════════════════
  # MUSTER 4 — Textprüfungs-Reiter: Inhalt, Steuerelemente, Anwenden
  # ══════════════════════════════════════════════════════════════════════════

  @javascript
  Scenario: M4 – Textprüfungs-Reiter: Inhalt korrekt, Delta verstellen, Anwenden
    Given I log in as "teacher1"
    # Textprüfungs-Reiter löst Auto-Scan aus, da noch keine Ergebnisse vorliegen.
    And I am on the textreview tab for course "SHIFTCRS"
    And I wait "4" seconds
    Then I should see "Textprüfung"
    # Aktivitäts-Icons und -namen in der Tabelle prüfen.
    And the textreview table should contain a assign icon
    And I should see "Aufgabe-A" in the "#coursectrl-textreview-table" "css_element"
    # Textfelder-Bezeichnungen sind lokalisiert (nicht "intro").
    And I should not see " intro " in the "#coursectrl-textreview-table" "css_element"
    # Datumsangabe ist in der Tabelle sichtbar.
    And I should see "15.06.2026" in the "#coursectrl-textreview-table" "css_element"
    # Delta-Steuerelemente für Textprüfung sind vorhanden.
    And "coursectrl-textreview-delta-days" "field" should exist
    # Delta auf -1 Tag setzen.
    When I set the textreview delta to -1 days
    # Alle sicheren Einträge auswählen.
    And I click on "[data-action='select-all-safe']" "css_element"
    # "Ausgewählte Änderungen anwenden" klicken und warten.
    And I apply text changes from the textreview tab and wait
    And I wait "3" seconds
    # Nach Reload: Tab ist wieder sichtbar.
    Then I should see "Textprüfung"

  # ══════════════════════════════════════════════════════════════════════════
  # EDGE CASES & NEGATIVE TESTS
  # ══════════════════════════════════════════════════════════════════════════

  @javascript
  Scenario: Shift-Modal zeigt Fehler wenn Delta = 0
    Given I log in as "teacher1"
    And I am on the timeline page for course "SHIFTCRS"
    When I click the first slot shift button on the timeline
    Then the shift modal should be visible
    # Delta bleibt 0 — Klick auf Vorschau ohne Delta-Eingabe.
    When I click on "[data-ccwf-action='preview']" "css_element"
    # Fehlermeldung erscheint; kein Fortschreiten zu Schritt 2.
    Then "[data-ccwf-step1-error]:not(.d-none)" "css_element" should exist
    And "[data-ccwf-step='2'].d-none" "css_element" should exist

  @javascript
  Scenario: Nacheinander zwei Verschiebungen: erstes Datum stimmt, zweites bleibt
    # Sicherstellt, dass eine Entry-Verschiebung NUR das gewählte Feld ändert.
    Given I log in as "teacher1"
    And I am on the timeline page for course "SHIFTCRS"
    # Nur Aufgabe-A / duedate verschieben.
    When I click the entry shift button for assign on the timeline
    Then the shift modal should be visible
    When I set the shift days to 1
    And I click the shift preview button and wait
    Then the shift preview summary should contain "1 Felder in 1 Aktivität"
    When I apply the shift and wait
    And I click on "[data-ccwf-action='back']" "css_element"
    And I wait "3" seconds
    # Aufgabe-A wurde verschoben.
    Then the duedate of assign Aufgabe-A in course SHIFTCRS should be shifted by 1 day(s) from 1781481600
    # Forum-C completionexpected wurde NICHT verändert.
    And the completionexpected of forum Forum-C in course SHIFTCRS should still be 1781481600

  @javascript
  Scenario: M2-Entry-Followdeps-mit-echter-Abhaengigkeit – dependente Aktivität wird mit verschoben
    # Activity A (assign A1) has duedate.
    # Activity B (quiz B1) depends on A1 completion and has its own timeopen.
    # Enabling followdeps when shifting A1 must also shift B1.
    Given the following "activities" exist:
      | activity | course     | name | duedate    |
      | assign   | SHIFTCRS   | A1   | 1700000000 |
    And the following "activities" exist:
      | activity | course     | name | timeopen   |
      | quiz     | SHIFTCRS   | B1   | 1700086400 |
    And the availability of quiz "B1" in course "SHIFTCRS" depends on completion of assign "A1"
    And I log in as "teacher1"
    And I am on the timeline page for course "SHIFTCRS"
    When I click the entry shift button for activity "A1" and field "duedate"
    Then the shift modal should be visible
    When I enable the followdeps checkbox in the shift modal
    And I set the shift days to 1
    And I click the shift preview button and wait
    # Preview must contain both A1 and the dependent B1.
    Then I should see "A1" in the "[data-ccwf-preview-body]" "css_element"
    And I should see "B1" in the "[data-ccwf-preview-body]" "css_element"
    When I apply the shift and wait
    And I click on "[data-ccwf-action='back']" "css_element"
    And I wait "3" seconds
    Then the duedate of assign "A1" in course "SHIFTCRS" should be shifted by 1 day(s) from 1700000000
    And the timeopen of quiz "B1" in course "SHIFTCRS" should be shifted by 1 day(s) from 1700086400
