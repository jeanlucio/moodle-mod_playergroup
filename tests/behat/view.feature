@mod @mod_playergroup
Feature: Teacher and student can view a PlayerGroup activity
  In order to manage group formation
  As a teacher or student
  I need to be able to navigate to the PlayerGroup activity page

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                 |
      | teacher1 | Terry     | Teacher  | teacher1@example.com  |
      | student1 | Sam       | Student  | student1@example.com  |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity    | name             | course | idnumber |
      | playergroup | Group Activity | C1     | pg1      |

  Scenario: Student sees the Create Group button and the empty groups list
    Given I am on the "Group Activity" "mod_playergroup > view" page logged in as "student1"
    Then I should see "Create Group"
    And I should see "No groups have been created yet"

  Scenario: Student does not see the View Report link
    Given I am on the "Group Activity" "mod_playergroup > view" page logged in as "student1"
    Then I should not see "View Report"

  Scenario: Teacher sees the View Report link
    Given I am on the "Group Activity" "mod_playergroup > view" page logged in as "teacher1"
    Then I should see "View Report"
