@local_coursectrl @local_coursectrl_shift_precise
Feature: Timeline shift – field-precise assertions and data-attribute verification
  As a teacher
  I want to shift exactly the dates I target
  So that other dates of the same activity are never accidentally modified

  # Fixture timestamps:
  #   Quiz-PRC: timeopen  = 1781913600 (2026-06-19 00:00 UTC)
  #             timeclose = 1782000000 (2026-06-20 00:24 UTC)
  #   Task-PRC: duedate   = 1782000000, completionexpected = 1782000000
  #
  # Slot at 1781913600 → only Quiz timeopen.
  # Slot at 1782000000 → Quiz timeclose + Task-PRC duedate + Task-PRC completionexpected.
  #
  # Following shift at slot 1782000000 shifts targets AT or AFTER that timestamp.
  # Quiz timeopen (1781913600) is BEFORE that slot and must never be affected.

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
    And the completionexpected of assign "Task-PRC" in course "PRECISECRS" is 1782000000

  # ── Data-attribute checks ─────────────────────────────────────────────────

  @javascript
  Scenario: Timeline entry buttons carry raw fieldkey not localized label as data-field
    Given I log in as "teacher1"
    And I am on the timeline page for course "PRECISECRS"
    Then the entry shift button for "Task-PRC" and fieldkey "duedate" should carry correct data attributes

  # ── Entry-shift field precision ───────────────────────────────────────────

  @javascript
  Scenario: Entry-shift for quiz timeopen shifts only timeopen and leaves timeclose unchanged
    Given I log in as "teacher1"
    And I am on the timeline page for course "PRECISECRS"
    When I click the entry shift button for activity "Quiz-PRC" and field "timeopen"
    Then the shift modal should be visible
    When I set the shift days to 1
    And I click the shift preview button and wait
    Then the shift preview summary should contain "Feld(er)"
    When I apply the shift and wait
    And I click on "[data-ccwf-action='back']" "css_element"
    And I wait "3" seconds
    Then the timeopen of quiz "Quiz-PRC" in course "PRECISECRS" should be shifted by 1 day(s) from 1781913600
    And the timeclose of quiz "Quiz-PRC" in course "PRECISECRS" should still be 1782000000

  @javascript
  Scenario: Entry-shift for completionexpected shifts only that CM-level field and leaves duedate unchanged
    Given I log in as "teacher1"
    And I am on the timeline page for course "PRECISECRS"
    When I click the entry shift button for activity "Task-PRC" and field "completionexpected"
    Then the shift modal should be visible
    When I set the shift days to 1
    And I click the shift preview button and wait
    Then the shift preview summary should contain "Feld(er)"
    When I apply the shift and wait
    And I click on "[data-ccwf-action='back']" "css_element"
    And I wait "3" seconds
    Then the completionexpected of assign "Task-PRC" in course "PRECISECRS" should be shifted by 1 day(s) from 1782000000
    And the duedate of assign "Task-PRC" in course "PRECISECRS" should still be 1782000000

  # ── Slot-shift ────────────────────────────────────────────────────────────

  @javascript
  Scenario: Slot-shift at timestamp 1782000000 shifts timeclose and duedate but not quiz timeopen
    Given I log in as "teacher1"
    And I am on the timeline page for course "PRECISECRS"
    When I click the slot shift button for timestamp 1782000000
    Then the shift modal should be visible
    When I set the shift days to 1
    And I click the shift preview button and wait
    Then the shift preview summary should contain "Feld(er)"
    When I apply the shift and wait
    And I click on "[data-ccwf-action='back']" "css_element"
    And I wait "3" seconds
    Then the timeclose of quiz "Quiz-PRC" in course "PRECISECRS" should be shifted by 1 day(s) from 1782000000
    And the duedate of assign "Task-PRC" in course "PRECISECRS" should be shifted by 1 day(s) from 1782000000
    And the timeopen of quiz "Quiz-PRC" in course "PRECISECRS" should still be 1781913600

  # ── Following-shift ───────────────────────────────────────────────────────

  @javascript
  Scenario: Following-shift at timestamp 1782000000 shifts entries at or after that point but not quiz timeopen
    Given I log in as "teacher1"
    And I am on the timeline page for course "PRECISECRS"
    When I click the following shift button for timestamp 1782000000
    Then the shift modal should be visible
    When I set the shift days to 1
    And I click the shift preview button and wait
    Then the shift preview summary should contain "Feld(er)"
    When I apply the shift and wait
    And I click on "[data-ccwf-action='back']" "css_element"
    And I wait "3" seconds
    Then the timeclose of quiz "Quiz-PRC" in course "PRECISECRS" should be shifted by 1 day(s) from 1782000000
    And the duedate of assign "Task-PRC" in course "PRECISECRS" should be shifted by 1 day(s) from 1782000000
    And the timeopen of quiz "Quiz-PRC" in course "PRECISECRS" should still be 1781913600

  # ── Preview/Execute consistency ───────────────────────────────────────────

  @javascript
  Scenario: Preview and Execute agree on field count for an entry shift
    Given I log in as "teacher1"
    And I am on the timeline page for course "PRECISECRS"
    When I click the entry shift button for activity "Quiz-PRC" and field "timeopen"
    Then the shift modal should be visible
    When I set the shift days to 1
    And I click the shift preview button and wait
    Then the shift preview summary should contain "1 Feld(er) in 1 Aktivität(en)"
    When I apply the shift and wait
    Then the shift modal should show 1 shifted entry message
