@mod @mod_playergroup @javascript
Feature: A group only counts once it reaches the minimum size
  In order to make teams of a useful size
  As a student
  I need to be told how many members my group still needs

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Sam       | Creator  | student1@example.com |
      | student2 | Alex      | Joiner   | student2@example.com |
      | student3 | Bea       | Joiner   | student3@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
      | student2 | C1     | student |
      | student3 | C1     | student |
    And the following "activities" exist:
      | activity    | name           | course | minmembers |
      | playergroup | Group Activity | C1     | 3          |
    And I am on the "Group Activity" "mod_playergroup > view" page logged in as "student1"
    And I click on "Create Group" "button"
    And I wait until ".modal.show" "css_element" exists
    And I set the field "Name" to "Test Group"
    And I click on "Save changes" "button" in the ".modal.show" "css_element"
    And I wait until ".pg-group-card" "css_element" exists

  Scenario: The founder is told how many members the group is missing
    Then I should see "This group needs 2 more member(s) before it counts for the grade and completion."

  Scenario: The notice follows the group as it grows and goes away at the minimum
    Given I log out
    And I am on the "Group Activity" "mod_playergroup > view" page logged in as "student2"
    And I click on "Join Group" "button"
    And I wait until ".pg-group-card" "css_element" exists
    And I log out
    And I am on the "Group Activity" "mod_playergroup > view" page logged in as "student1"
    And I should see "This group needs 1 more member(s) before it counts for the grade and completion."
    And I log out
    And I am on the "Group Activity" "mod_playergroup > view" page logged in as "student3"
    And I click on "Join Group" "button"
    And I wait until ".pg-group-card" "css_element" exists
    And I log out
    When I am on the "Group Activity" "mod_playergroup > view" page logged in as "student1"
    Then I should see "3 / 5"
    And I should not see "more member(s)"
