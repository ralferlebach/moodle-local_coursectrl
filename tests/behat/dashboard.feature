@local @local_coursectrl @local_coursectrl_dashboard
Feature: Course Control Hub dashboard cockpit layout
  As a teacher
  I want to see the Course Control Hub dashboard
  So that I get an overview of course state, problems and upcoming dates

  Background:
    Given the following "courses" exist:
      | fullname       | shortname | enablecompletion |
      | Fixture Course | FXCOURSE  | 1                |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
    And the following "course enrolments" exist:
      | user     | course   | role           |
      | teacher1 | FXCOURSE | editingteacher |

  @javascript
  Scenario: Dashboard loads and shows stat tiles
    Given I log in as "teacher1"
    When I am on the coursectrl dashboard for course "FXCOURSE"
    Then I should see "Sections"
    And I should see "Activities"
    And I should see "Editable texts"
    And I should see "Problems"

  @javascript
  Scenario: Dashboard shows no problem summary for a clean course
    Given I log in as "teacher1"
    When I am on the coursectrl dashboard for course "FXCOURSE"
    Then I should not see "Show problem details"

  @javascript
  Scenario: Dashboard shows upcoming dates section
    Given I log in as "teacher1"
    When I am on the coursectrl dashboard for course "FXCOURSE"
    Then I should see "Upcoming dates"
    And I should see "Dates found in texts"

  @javascript
  Scenario: Dashboard shows timeline and textreview action buttons
    Given I log in as "teacher1"
    When I am on the coursectrl dashboard for course "FXCOURSE"
    Then I should see "Shift dates"
    And I should see "Edit dates in texts"

  @javascript
  Scenario: Dashboard shows problem summary when an assign has conflicting dates
    Given the following "activities" exist:
      | activity | course   | name      | allowsubmissionsfromdate | duedate   |
      | assign   | FXCOURSE | BadAssign | ##tomorrow##             | ##today## |
    And I log in as "teacher1"
    When I am on the coursectrl dashboard for course "FXCOURSE"
    Then I should see "Show problem details"
    And I should see "Deep analysis"
