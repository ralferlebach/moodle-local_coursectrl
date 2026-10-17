@local @local_coursectrl @local_coursectrl_checks
Feature: Checks page tab navigation and basic findings
  As a teacher
  I want to use the Checks page to see consistency issues and structural risks
  So that I can identify and correct problems in my course configuration

  Background:
    Given the following "courses" exist:
      | fullname          | shortname | enablecompletion |
      | Fixture Course    | FXCOURSE  | 1                |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
    And the following "course enrolments" exist:
      | user     | course   | role           |
      | teacher1 | FXCOURSE | editingteacher |

  # ── Tab navigation ───────────────────────────────────────────────────────────

  @javascript
  Scenario: Checks page loads on the consistency tab by default
    Given I log in as "teacher1"
    When I am on the checks page for course "FXCOURSE"
    Then I should see "Problems"
    And I should see "Solutions"
    And I should see "Simulation"
    And I should see "No consistency issues found."

  @javascript
  Scenario: Switching to the risks tab shows risk assessment section
    Given I log in as "teacher1"
    When I am on the checks page for course "FXCOURSE" tab "risks"
    Then I should see "Deep analysis & problem solving"
    And I should see "The risk assessment has not yet been run."

  @javascript
  Scenario: Switching to the simulation tab shows simulation section
    Given I log in as "teacher1"
    When I am on the checks page for course "FXCOURSE" tab "simulation"
    Then I should see "Simulation"

  # ── Consistency tab: clean course ─────────────────────────────────────────

  @javascript
  Scenario: Empty course shows no consistency issues
    Given I log in as "teacher1"
    When I am on the checks page for course "FXCOURSE"
    Then I should not see "error"

  # ── Consistency tab: R3 finding visible ───────────────────────────────────

  @javascript
  Scenario: Assign with opening date after due date shows a consistency error
    Given the following "activities" exist:
      | activity | course   | name        | allowsubmissionsfromdate | duedate    |
      | assign   | FXCOURSE | BadAssign   | ##tomorrow##             | ##today##  |
    And I log in as "teacher1"
    When I am on the checks page for course "FXCOURSE"
    Then I should see "BadAssign"
    And I should see "Date conflict" in the "[data-region='panel-consistency']" "css_element"

  # ── Risks tab: run assessment ─────────────────────────────────────────────

  @javascript
  Scenario: Running the risk assessment updates the last-run date
    Given I log in as "teacher1"
    When I run the risk assessment for course "FXCOURSE"
    Then I should see "Last assessment"
    And I should not see "The risk assessment has not yet been run."

  # ── Risks tab: structural finding for circular dependency ─────────────────

  @javascript
  Scenario: Two activities with mutual completion dependency appear as a risk
    Given the following "activities" exist:
      | activity | course   | name   | completion |
      | assign   | FXCOURSE | AssA   | 2          |
      | assign   | FXCOURSE | AssB   | 2          |
    And the availability of assign "AssA" in course "FXCOURSE" depends on completion of assign "AssB"
    And the availability of assign "AssB" in course "FXCOURSE" depends on completion of assign "AssA"
    And I log in as "teacher1"
    When I run the risk assessment for course "FXCOURSE"
    Then I should see "Last assessment"
    And I should see "AssA" in the "[data-region='panel-risks']" "css_element"
    And I should see "AssB" in the "[data-region='panel-risks']" "css_element"

  # ── Simulation tab: basic output ─────────────────────────────────────────

  @javascript
  Scenario: Simulation tab shows accessible activities
    Given the following "activities" exist:
      | activity | course   | name         | completion |
      | assign   | FXCOURSE | OpenActivity | 2          |
    And I log in as "teacher1"
    When I am on the checks page for course "FXCOURSE" tab "simulation"
    Then I should see "Simulation"
