@mod @mod_playergroup @javascript
Feature: A student invites a colleague, who answers the invitation
  In order to build a team with the people I choose
  As a student
  I need to send an invitation and my colleague needs to accept or decline it

  Background:
    Given the following "users" exist:
      | username | firstname | lastname  | email                |
      | student1 | Sam       | Creator   | student1@example.com |
      | student2 | Alex      | Colleague | student2@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
      | student2 | C1     | student |
    And the following "activities" exist:
      | activity    | name           | course |
      | playergroup | Group Activity | C1     |
    And I am on the "Group Activity" "mod_playergroup > view" page logged in as "student1"
    And I click on "Create Group" "button"
    And I wait until ".modal.show" "css_element" exists
    And I set the field "Name" to "Test Group"
    And I click on "Save changes" "button" in the ".modal.show" "css_element"
    And I wait until ".pg-group-card" "css_element" exists
    And I click on "Invite Colleagues" "button"
    And I wait until ".modal.show" "css_element" exists
    And I click on "Invite" "button" in the ".modal.show" "css_element"
    And I should see "Invitation sent!" in the ".modal.show" "css_element"
    And I log out

  Scenario: The colleague accepts the invitation and joins the group
    Given I am on the "Group Activity" "mod_playergroup > view" page logged in as "student2"
    And I should see "Pending Invitations"
    And I should see "From: Sam Creator"
    When I click on "Accept" "button"
    And I wait until ".pg-group-card" "css_element" exists
    Then I should see "My Group"
    And I should not see "Pending Invitations"

  Scenario: The colleague declines the invitation and stays without a group
    Given I am on the "Group Activity" "mod_playergroup > view" page logged in as "student2"
    When I click on "Decline" "button"
    And I wait until "Create Group" "button" exists
    Then I should not see "Pending Invitations"
    And I should not see "My Group"
