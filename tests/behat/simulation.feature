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

  # Direct capability gate tests.

  @javascript
  Scenario: A student without simulate capability cannot access simulation.php directly
    Given the following "users" exist:
      | username | firstname | lastname | email               |
      | student1 | Student   | One      | s1@example.com      |
    And the following "course enrolments" exist:
      | user     | course  | role    |
      | student1 | SIMTEST | student |
    And I log in as "student1"
    When I am on simulation page for course "SIMTEST"
    Then I should not see "Simulation"
    And I should see "Sorry" in the page

  @javascript
  Scenario: A student without simulate capability cannot access checks.php tab simulation directly
    Given the following "users" exist:
      | username | firstname | lastname | email               |
      | student2 | Student   | Two      | s2@example.com      |
    And the following "course enrolments" exist:
      | user     | course  | role    |
      | student2 | SIMTEST | student |
    And I log in as "student2"
    When I am on the checks page for course "SIMTEST" tab "simulation"
    Then I should not see "Simulation"
    And I should see "Sorry" in the page
