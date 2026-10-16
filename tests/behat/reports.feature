@mod @mod_playergroup @javascript
Feature: Teacher reads the activity log and the groups, and a student cannot
  In order to follow how the class formed its groups
  As a teacher
  I need the activity log and the groups and members report, and students must not reach them

  Background:
    Given the following config values are set as admin:
      | config         | value             | plugin  |
      | enabled_stores | logstore_standard | tool_log |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Terry     | Teacher  | teacher1@example.com |
      | student1 | Sam       | Student  | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity    | name           | course |
      | playergroup | Group Activity | C1     |
    And I am on the "Group Activity" "mod_playergroup > view" page logged in as "student1"
    And I click on "Create Group" "button"
    And I wait until ".modal.show" "css_element" exists
    And I set the field "Name" to "Test Group"
    And I click on "Save changes" "button" in the ".modal.show" "css_element"
    And I wait until ".pg-group-card" "css_element" exists
    And I log out

  Scenario: Teacher sees who created the group in the activity log
    Given I am on the "Group Activity" "mod_playergroup > report" page logged in as "teacher1"
    Then I should see "Activity Log"
    And I should see "Group created"
    And I should see "Sam Student"
    And "Export to CSV" "link" should exist
    And "Export to Excel" "link" should exist

  Scenario: Teacher sees each group with its leader in the groups and members report
    Given I am on the "Group Activity" "mod_playergroup > groupsreport" page logged in as "teacher1"
    Then I should see "Test Group"
    And I should see "Sam Student"
    And I should see "Leader"
    And "Export to CSV" "link" should exist

  Scenario: The teacher reaches both reports from the activity page
    Given I am on the "Group Activity" "mod_playergroup > view" page logged in as "teacher1"
    When I click on "Groups and Members" "link"
    Then I should see "Test Group"
    And I should see "Leader"

  Scenario: A student asking for the activity log gets the normal page instead
    Given I am on the "Group Activity" "mod_playergroup > report" page logged in as "student1"
    Then I should see "Test Group"
    And I should not see "Activity Log"
    And I should not see "Export to CSV"
