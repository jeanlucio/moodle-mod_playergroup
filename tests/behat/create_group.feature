@mod @mod_playergroup @javascript
Feature: Student creates a group via the modal
  In order to form a team in a gamified course
  As a student
  I need to be able to create a group and see it listed on the activity page

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Sam       | Student  | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    And the following "activities" exist:
      | activity    | name             | course |
      | playergroup | Group Activity | C1     |

  Scenario: Student creates an open group and the card appears in the list
    Given I am on the "Group Activity" "mod_playergroup > view" page logged in as "student1"
    And I should see "Create Group"
    When I click on "Create Group" "button"
    And I wait until ".modal.show" "css_element" exists
    And I set the field "Name" to "Test Group"
    And I click on "Save changes" "button" in the ".modal.show" "css_element"
    And I wait until ".pg-group-card" "css_element" exists
    Then I should see "Test Group"
    And I should not see "Create Group"

  Scenario: Student cannot create a second group once they already belong to one
    Given I am on the "Group Activity" "mod_playergroup > view" page logged in as "student1"
    When I click on "Create Group" "button"
    And I wait until ".modal.show" "css_element" exists
    And I set the field "Name" to "Test Group"
    And I click on "Save changes" "button" in the ".modal.show" "css_element"
    And I wait until ".pg-group-card" "css_element" exists
    Then "Create Group" "button" should not exist
