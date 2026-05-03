@local_coursectrl @local_coursectrl_simulation
Feature: Simulation tab functionality
  As a teacher
  I want to run the learner journey simulation
  So that I can identify blocked activities and next steps

  Background:
    Given the following "courses" exist:
      | fullname         | shortname | enablecompletion |
      | Simulation Test  | SIMTEST   | 1                |
    And the following "users" exist:
      | username | firstname | lastname | email               |
      | teacher1 | Teacher   | One      | t1@example.com      |
    And the following "course enrolments" exist:
      | user     | course  | role           |
      | teacher1 | SIMTEST | editingteacher |

  @javascript
  Scenario: Checks page loads and shows the Simulation tab
    Given the following "activities" exist:
      | activity | course  | name    |
      | assign   | SIMTEST | Task S1 |
    And I log in as "teacher1"
    When I am on the checks page for course "SIMTEST"
    Then I should see "Simulation"
    And I should not see "Error"

  @javascript
  Scenario: Simulation tab shows the run form
    Given the following "activities" exist:
      | activity | course  | name    |
      | assign   | SIMTEST | Task S2 |
    And I log in as "teacher1"
    When I am on the checks page for course "SIMTEST" tab "simulation"
    Then I should see "Simulation"
    And I should not see "Page not found"

  # Capability gate: students without simulate capability see no simulation content.

  @javascript
  Scenario: A student without simulate capability sees no simulation form on checks page
    Given the following "users" exist:
      | username | firstname | lastname | email               |
      | student1 | Student   | One      | s1@example.com      |
    And the following "course enrolments" exist:
      | user     | course  | role    |
      | student1 | SIMTEST | student |
    And the following "activities" exist:
      | activity | course  | name    |
      | assign   | SIMTEST | Task C1 |
    And I log in as "student1"
    When I am on the checks page for course "SIMTEST"
    Then I should not see "Simulation"
