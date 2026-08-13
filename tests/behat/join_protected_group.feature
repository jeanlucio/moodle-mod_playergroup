@mod @mod_playergroup @javascript
Feature: Student joins a password-protected group
  In order to join a locked team in a gamified course
  As a student
  I need to enter the correct group password, using either the mouse or the keyboard

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
    And I set the field "Name" to "Locked Group"
    And I set the field "Privacy" to "Protected (password)"
    And I set the field "Group password" to "secret123"
    And I click on "Save changes" "button" in the ".modal.show" "css_element"
    And I wait until ".pg-group-card" "css_element" exists
    And I log out
    And I am on the "Group Activity" "mod_playergroup > view" page logged in as "student2"
    And I click on "Join Group" "button"
    And I wait until "#pg-join-password" "css_element" exists

  Scenario: Pressing enter with the correct password joins the group, like clicking Save
    When I set the field "Group password" to "secret123"
    And I press the enter key
    And I wait until ".pg-group-card" "css_element" exists
    Then I should see "My Group"
    And "Join Group" "button" should not exist

  Scenario: The wrong password shows a dialog on the activity page, not a system error page
    When I set the field "Group password" to "wrongpassword"
    And I click on "Save changes" "button" in the ".modal.show" "css_element"
    And I wait until "Incorrect password" "text" exists
    Then I should see "Incorrect password" in the ".modal.show" "css_element"
    And the url should match "/mod/playergroup/view\.php"
    And I should see "Group Activity"

  Scenario: The password field does not offer the browser's saved passwords
    Then the "autocomplete" attribute of "#pg-join-password" "css_element" should contain "new-password"
