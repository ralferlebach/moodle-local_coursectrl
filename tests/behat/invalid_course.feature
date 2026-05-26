@local @local_coursectrl @local_coursectrl_invalid_course
Feature: Invalid course ID handling for Course Control Hub pages
  In order to protect against URL manipulation
  As a user
  I need to see a warning notification instead of a server error stacktrace
  when accessing a Course Control Hub page with an invalid or non-existent course ID

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |

  Scenario: Dashboard shows warning for non-existent course ID
    Given I log in as "teacher1"
    When I visit "/local/coursectrl/index.php?courseid=999999"
    Then I should see "No valid course context found"
    And I should not see "invalidrecord"
    And I should not see "Stack trace"
    And I should not see "Error code"
    And I should not see "Debug info"

  Scenario: Dashboard shows warning when no course ID supplied
    Given I log in as "teacher1"
    When I visit "/local/coursectrl/index.php"
    Then I should see "No valid course context found"
    And I should not see "Stack trace"

  Scenario: Timeline shows warning for non-existent course ID
    Given I log in as "teacher1"
    When I visit "/local/coursectrl/timeline.php?courseid=999999"
    Then I should see "No valid course context found"
    And I should not see "invalidrecord"
    And I should not see "Stack trace"

  Scenario: Manage page shows warning for non-existent course ID
    Given I log in as "teacher1"
    When I visit "/local/coursectrl/manage.php?courseid=999999"
    Then I should see "No valid course context found"
    And I should not see "Stack trace"

  Scenario: Checks page shows warning for non-existent course ID
    Given I log in as "teacher1"
    When I visit "/local/coursectrl/checks.php?courseid=999999"
    Then I should see "No valid course context found"
    And I should not see "Stack trace"

  Scenario: Dependencies page shows warning for non-existent course ID
    Given I log in as "teacher1"
    When I visit "/local/coursectrl/dependencies.php?courseid=999999"
    Then I should see "No valid course context found"
    And I should not see "Stack trace"

  Scenario: History page shows warning for non-existent course ID
    Given I log in as "teacher1"
    When I visit "/local/coursectrl/history.php?courseid=999999"
    Then I should see "No valid course context found"
    And I should not see "Stack trace"
