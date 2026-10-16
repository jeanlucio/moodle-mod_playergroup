@mod @mod_playergroup
Feature: The course index of PlayerGroup activities
  In order to know whether a course uses the activity
  As a student
  I need the index page to list the activities, or to say there are none

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Sam       | Student  | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
      | Course 2 | C2        | 0        |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
      | student1 | C2     | student |
    And the following "activities" exist:
      | activity    | name           | course | idnumber |
      | playergroup | Group Activity | C2     | pg1      |

  Scenario: A course without PlayerGroup activities says so
    Given I am on the "C1" "mod_playergroup > index" page logged in as "student1"
    Then I should see "There are no PlayerGroups in this course"
    And I should not see "No groups have been created yet"

  Scenario: A course with a PlayerGroup activity lists it
    Given I am on the "C2" "mod_playergroup > index" page logged in as "student1"
    Then I should see "Group Activity"
