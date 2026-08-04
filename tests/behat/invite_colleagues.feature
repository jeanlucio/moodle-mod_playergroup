@mod @mod_playergroup @javascript
Feature: Student sees enrolled colleagues available to invite
  In order to grow my group in a gamified course
  As a student
  I need to see my enrolled colleagues listed in the invite modal so I can invite them

  Background:
    Given the following "users" exist:
      | username | firstname | lastname  | email                 |
      | student1 | Sam       | Creator   | student1@example.com  |
      | student2 | Alex      | Colleague | student2@example.com  |
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

  Scenario: Group creator sees an enrolled colleague listed in the invite modal
    When I click on "Invite Colleagues" "button"
    And I wait until ".modal.show" "css_element" exists
    Then I should see "Alex Colleague" in the ".modal.show" "css_element"
    And "Invite" "button" should exist in the ".modal.show" "css_element"
