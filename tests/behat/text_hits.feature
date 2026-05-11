@local @local_coursectrl @local_coursectrl_texthits
Feature: Text-hit scanning and date replacement in free-text fields
  As a teacher
  I want to find date references in activity descriptions
  So that I can update them when rescheduling a course

  Background:
    Given the following "courses" exist:
      | fullname      | shortname | summary                                 |
      | Text Course   | TEXTCRS   | Abgabe bis 01.06.2026 einreichen bitte. |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | One      | teacher1@example.com |
    And the following "course enrolments" exist:
      | user     | course  | role           |
      | teacher1 | TEXTCRS | editingteacher |

  # Text hit scanning.

  @javascript
  Scenario: The manage page shows the text review option for a teacher
    Given I log in as "teacher1"
    When I am on the manage page for course "TEXTCRS"
    Then I should see "Textprüfung"
