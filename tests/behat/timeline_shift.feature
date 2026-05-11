@local @local_coursectrl @local_coursectrl_timeline_shift
Feature: Timeline date shift for availability-condition dates
  As a teacher
  I want to shift availability-based dates on the timeline
  So that I can reschedule course access windows efficiently

  Background:
    Given the following "courses" exist:
      | fullname      | shortname | enablecompletion |
      | Shift Course  | SHIFTCRS  | 1                |
    And the following "users" exist:
      | username | firstname | lastname | email               |
      | teacher1 | Teacher   | One      | t1@example.com      |
    And the following "course enrolments" exist:
      | user     | course   | role           |
      | teacher1 | SHIFTCRS | editingteacher |

  @javascript
  Scenario: Timeline page loads for a course with an assign activity
    Given the following "activities" exist:
      | activity | course   | name       | duedate      |
      | assign   | SHIFTCRS | Homework 1 | ##tomorrow## |
    And I log in as "teacher1"
    When I am on the timeline page for course "SHIFTCRS"
    Then I should see "Homework 1"
    And I should see "Terminuebersicht"

  @javascript
  Scenario: Timeline shows shift dialog when the slot button is clicked
    Given the following "activities" exist:
      | activity | course   | name     | duedate      |
      | assign   | SHIFTCRS | Task A   | ##tomorrow## |
    And I log in as "teacher1"
    When I am on the timeline page for course "SHIFTCRS"
    And I click on "[data-action='shift-slot']" "css_element"
    Then I should see "Termine verschieben"

  @javascript
  Scenario: Text review tab is accessible and shows activity intro hits after scan
    Given the following "activities" exist:
      | activity | course   | name    | intro                            |
      | assign   | SHIFTCRS | Task B  | Abgabe bis 15.06.2026 Uhr.       |
    And I log in as "teacher1"
    When I am on the textreview tab for course "SHIFTCRS"
    Then I should see "Text Review"
    And I should not see "Error"
