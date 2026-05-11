@local_coursectrl @local_coursectrl_shift_precise
Feature: Timeline shift – field-precise assertions and data-attribute verification
  As a teacher
  I want to shift exactly the dates I target
  So that other dates of the same activity are never accidentally modified

  # ── Fixture ───────────────────────────────────────────────────────────────
  # Quiz-B has timeopen = 1781913600 (2026-06-20) and timeclose = 1782000000 (2026-06-21).
  # Following-shift at slot timeclose must shift timeclose but NOT timeopen.
  # Entry-shift for timeopen must shift timeopen but NOT timeclose.

  Background:
    Given the following "courses" exist:
      | fullname       | shortname  | enablecompletion | startdate  |
      | Precise Course | PRECISECRS | 1                | 1780000000 |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
    And the following "course enrolments" exist:
      | user     | course     | role           |
      | teacher1 | PRECISECRS | editingteacher |
    And the following "activities" exist:
      | activity | course     | name     | timeopen   | timeclose  |
      | quiz     | PRECISECRS | Quiz-PRC | 1781913600 | 1782000000 |
    And the following "activities" exist:
      | activity | course     | name     | duedate    |
      | assign   | PRECISECRS | Task-PRC | 1782000000 |
    And the completionexpected of forum Forum-PRC in course PRECISECRS is 1782000000

  # ── Data-attribute checks ──────────────────────────────────────────────────

  @javascript
  Scenario: Timeline entry buttons carry raw fieldkey, source, and timestamp as data attributes
    Given I log in as "teacher1"
    And I am on the timeline page for course "PRECISECRS"
    # Verify that data-field contains the raw key, not a localized label.
    Then the entry shift button for Task-PRC and fieldkey duedate should carry correct data attributes

  # ── Entry-shift field precision ───────────────────────────────────────────

  @javascript
  Scenario: Entry-shift for quiz timeopen does not move timeclose
    Given I log in as "teacher1"
    And I am on the timeline page for course "PRECISECRS"
    When I click the entry shift button for activity "Quiz-PRC" and field "timeopen"
    Then the shift modal should be visible
    When I set the shift days to 1
    And I click the shift preview button and wait
    Then the shift preview summary should contain "1 Felder in 1 Aktivität"
    When I apply the shift and wait
    And I click on "[data-ccwf-action='back']" "css_element"
    And I wait "3" seconds
    # timeopen must be shifted by 1 day.
    Then the timeopen of quiz Quiz-PRC in course PRECISECRS should be shifted by 1 day(s) from 1781913600
    # timeclose must remain unchanged.
    And the timeclose of quiz Quiz-PRC in course PRECISECRS should still be 1782000000

  @javascript
  Scenario: Entry-shift for completionexpected does not move duedate of same CM
    Given I log in as "teacher1"
    And I am on the timeline page for course "PRECISECRS"
    When I click the entry shift button for activity "Task-PRC" and field "completionexpected"
    Then the shift modal should be visible
    And I should see "Task-PRC"
    When I set the shift days to 1
    And I click the shift preview button and wait
    Then the shift preview summary should contain "1 Felder in 1 Aktivität"
    When I apply the shift and wait
    And I click on "[data-ccwf-action='back']" "css_element"
    And I wait "3" seconds
    # completionexpected is a CM-level field — verified via DB.
    Then the completionexpected of assign Task-PRC in course PRECISECRS should be shifted by 1 day(s) from 1782000000
    # duedate must not be touched.
    And the duedate of assign Task-PRC in course PRECISECRS should still be 1782000000

  # ── Slot-shift: only the targeted slot ────────────────────────────────────

  @javascript
  Scenario: Slot-shift moves targets in the slot but not quiz timeopen one day earlier
    Given I log in as "teacher1"
    And I am on the timeline page for course "PRECISECRS"
    # Click the slot-shift button for the slot at timeclose/duedate (2026-06-21).
    When I click the first slot shift button on the timeline
    Then the shift modal should be visible
    When I set the shift days to 1
    And I click the shift preview button and wait
    Then the shift preview summary should contain "Felder in"
    When I apply the shift and wait
    And I click on "[data-ccwf-action='back']" "css_element"
    And I wait "3" seconds
    # Targets in the slot (timeclose, duedate at 1782000000) are shifted.
    Then the timeclose of quiz Quiz-PRC in course PRECISECRS should be shifted by 1 day(s) from 1782000000
    And the duedate of assign Task-PRC in course PRECISECRS should be shifted by 1 day(s) from 1782000000
    # timeopen (at 1781913600, one day earlier) must NOT be shifted.
    And the timeopen of quiz Quiz-PRC in course PRECISECRS should still be 1781913600

  # ── Preview/Execute consistency ───────────────────────────────────────────

  @javascript
  Scenario: Preview and Execute agree on which fields change for an entry shift
    Given I log in as "teacher1"
    And I am on the timeline page for course "PRECISECRS"
    When I click the entry shift button for activity "Quiz-PRC" and field "timeopen"
    Then the shift modal should be visible
    When I set the shift days to 1
    And I click the shift preview button and wait
    # Preview must mention exactly the targeted field.
    Then the shift preview summary should contain "1 Felder in 1 Aktivität"
    And I should see "Quiz-PRC" in the "[data-ccwf-preview-body]" "css_element"
    When I click the preview info button 1
    # The field detail must NOT show "timeclose".
    Then I should not see "timeclose" in the "[data-ccwf-preview-body]" "css_element"
    When I apply the shift and wait
    # Execute summary must match preview: 1 shifted entry.
    Then the shift modal should show 1 shifted entry message
