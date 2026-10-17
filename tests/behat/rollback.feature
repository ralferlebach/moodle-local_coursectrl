@local @local_coursectrl @local_coursectrl_rollback
Feature: Rollback of executed bulk actions in the history page
  As a teacher with rollback capability
  I want to undo a bulk date-shift action
  So that I can recover from unintended changes

  Background:
    Given the following "courses" exist:
      | fullname         | shortname | enablecompletion |
      | Rollback Course  | ROLLBACK  | 1                |
    And the following "users" exist:
      | username | firstname | lastname | email                   |
      | teacher1 | Teacher   | One      | teacher1@example.com    |
      | noperms  | No        | Rollback | noperms@example.com     |
    And the following "course enrolments" exist:
      | user     | course   | role           |
      | teacher1 | ROLLBACK | editingteacher |
      | noperms  | ROLLBACK | student        |
    And the following "activities" exist:
      | activity | name   | course   | duedate    |
      | assign   | Task A | ROLLBACK | 1751328000 |

  # History page.

  @javascript
  Scenario: A teacher can open the history page
    Given I log in as "teacher1"
    When I am on the history page for course "ROLLBACK"
    Then I should see "Logs & History"
    And I should see "No actions recorded yet."

  # Rollback capability gate: editing teachers may view the history,
  # but local/coursectrl:rollback is granted to managers only.

  @javascript
  Scenario: An editing teacher without rollback capability sees no rollback buttons
    Given I log in as "teacher1"
    When I am on the history page for course "ROLLBACK"
    Then I should see "Logs & History"
    And I should not see "Undo"

  # Direct URL access without view capability.

  @javascript
  Scenario: A student without view capability cannot open the history page
    Given I log in as "noperms"
    Then I should be denied access to the "history" page for course "ROLLBACK"
