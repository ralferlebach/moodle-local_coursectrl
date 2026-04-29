@local_coursectrl @local_coursectrl_bulk
Feature: Bulk action selection, preview, and execution
  As a teacher with bulkaction capability
  I want to shift dates across multiple course activities at once
  So that I can reschedule a course efficiently without editing each activity

  Background:
    Given the following "courses" exist:
      | fullname       | shortname | enablecompletion |
      | Bulk Course    | BULK      | 1                |
    And the following "users" exist:
      | username | firstname | lastname | email                 |
      | teacher1 | Teacher   | One      | teacher1@example.com  |
      | viewer1  | View      | Only     | viewer1@example.com   |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | BULK   | editingteacher |
      | viewer1  | BULK   | student        |
    And the following "activities" exist:
      | activity | name    | course | duedate    |
      | assign   | Task 1  | BULK   | 1751328000 |

  # Bulk action workflow.

  @javascript
  Scenario: A teacher can open the manage page and see the activity selector
    Given I log in as "teacher1"
    When I am on the manage page for course "BULK"
    Then I should see "Task 1"
    And I should see "Datumsverschiebung" in the page

  @javascript
  Scenario: A teacher can navigate from manage to preview with selected activities
    Given I log in as "teacher1"
    When I am on the manage page for course "BULK"
    Then I should see "Task 1"

  # Capability gates.

  @javascript
  Scenario: A student without bulkaction capability cannot access manage.php
    Given I log in as "viewer1"
    When I navigate to "/local/coursectrl/manage.php?courseid=1" in Moodle
    Then I should see "Zugriff verweigert" in the page

  @javascript
  Scenario: An unauthenticated user is redirected to login for manage.php
    Given I am on the "manage" page for course "BULK" without logging in
    Then I should be redirected to the login page
