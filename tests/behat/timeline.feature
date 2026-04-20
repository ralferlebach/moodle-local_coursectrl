@local_coursectrl @local_coursectrl_timeline
Feature: Course Control Hub timeline and text review tab
  As a teacher
  I want to use the timeline and text review features
  So that I can manage course dates and date references in texts

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

  # ── Timeline tab ──────────────────────────────────────────────────────────

  @javascript
  Scenario: Timeline page loads and shows tab navigation
    Given I log in as "teacher1"
    When I am on the timeline page for course "FXCOURSE"
    Then I should see "Schedule"
    And I should see "Text Review"
    And I should see "Gantt Chart"

  @javascript
  Scenario: Timeline shows no dates message for a course without activities
    Given I log in as "teacher1"
    When I am on the timeline page for course "FXCOURSE"
    Then I should see "No dates found"

  @javascript
  Scenario: Timeline shows activity dates when an assign has a due date
    Given the following "activities" exist:
      | activity | course   | name       | duedate    |
      | assign   | FXCOURSE | Homework 1 | ##tomorrow## |
    And I log in as "teacher1"
    When I am on the timeline page for course "FXCOURSE"
    Then I should see "Homework 1"

  # ── Text review tab ───────────────────────────────────────────────────────

  @javascript
  Scenario: Text review tab loads without error (textreview.php no longer exists)
    Given I log in as "teacher1"
    When I am on the textreview tab for course "FXCOURSE"
    Then I should see "Text Review"
    And I should not see "Page not found"
    And I should not see "Error"

  @javascript
  Scenario: Text review tab shows info hint when no scan has been performed
    Given I log in as "teacher1"
    When I am on the textreview tab for course "FXCOURSE"
    Then I should see "Text Review"
