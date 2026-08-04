@mod @mod_playergroup @javascript
Feature: Student views a group's members
  In order to know who I am teaming up with in a gamified course
  As a student
  I need to be able to open the member list from a group's card

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Sam       | Creator  | student1@example.com |
      | student2 | Alex      | Joiner   | student2@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
      | student2 | C1     | student |
    And the following "activities" exist:
      | activity    | name             | course |
      | playergroup | Group Activity | C1     |
    And I am on the "Group Activity" "mod_playergroup > view" page logged in as "student1"
    And I click on "Create Group" "button"
    And I wait until ".modal.show" "css_element" exists
    And I set the field "Name" to "Test Group"
    And I click on "Save changes" "button" in the ".modal.show" "css_element"
    And I wait until ".pg-group-card" "css_element" exists
    And I log out
    And I am on the "Group Activity" "mod_playergroup > view" page logged in as "student2"
    And I click on "Join Group" "button"
    And I wait until ".pg-group-card" "css_element" exists

  Scenario: Student opens the member list and sees both members with the leader marked
    When I click on "2 / 5" "button"
    And I wait until ".modal.show" "css_element" exists
    Then I should see "Sam Creator" in the ".modal.show" "css_element"
    And I should see "Alex Joiner" in the ".modal.show" "css_element"
    And I should see "Leader" in the ".modal.show" "css_element"
