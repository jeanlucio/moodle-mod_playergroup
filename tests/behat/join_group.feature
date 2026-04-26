@mod @mod_playergroup @javascript
Feature: Student joins an existing open group
  In order to join a team already formed in a gamified course
  As a student
  I need to be able to click Join Group on an open group card and become a member

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

  Scenario: Second student joins the open group and sees the My Group badge
    Given I am on the "Group Activity" "mod_playergroup > view" page logged in as "student2"
    And I should see "Test Group"
    When I click on "Join Group" "button"
    And I wait until ".pg-group-card" "css_element" exists
    Then I should see "My Group"
    And "Join Group" "button" should not exist

  Scenario: Student who already belongs to a group does not see Join buttons on other cards
    Given I am on the "Group Activity" "mod_playergroup > view" page logged in as "student2"
    When I click on "Join Group" "button"
    And I wait until ".pg-group-card" "css_element" exists
    And I log out
    And I am on the "Group Activity" "mod_playergroup > view" page logged in as "student1"
    Then "Join Group" "button" should not exist
