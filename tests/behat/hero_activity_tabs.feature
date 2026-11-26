@format @format_designer @javascript
Feature: Hero activities and the course link in the secondary navigation
  In order to reach important activities and the course from every page
  As a student
  I need to see the hero activities and the course link as tabs, in the order the teacher sets

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | First    | teacher1@example.com |
      | student1 | Student   | First    | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format   | numsections | secondarymenutocourse |
      | Course 1 | C1        | designer | 1           | 1                     |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity | name          | course | section |
      | assign   | Demo assign 1 | C1     | 1       |
      | assign   | Demo assign 2 | C1     | 1       |
      | page     | Demo page     | C1     | 1       |
    And I am on the "Demo assign 1" "assign activity editing" page logged in as teacher1
    And I expand all fieldsets
    And I set the following fields to these values:
      | Show as tab | Everywhere |
      | Order       | -1         |
    And I press "Save and return to course"
    And I am on the "Demo assign 2" "assign activity editing" page
    And I expand all fieldsets
    And I set the following fields to these values:
      | Show as tab | Everywhere |
      | Order       | 1          |
    And I press "Save and return to course"
    And I log out

  Scenario: The hero activities are at their positions on the course page
    When I am on the "Course 1" course page logged in as student1
    # Order -1: before the first tab. Order 1: before the second tab.
    Then I check heroactivity position "Demo assign 1" "1"
    And I check heroactivity position "Course" "2"
    And I check heroactivity position "Demo assign 2" "3"

  Scenario: An activity page shows the course link and the hero activities
    When I am on the "Demo page" "page activity" page logged in as student1
    # The page has no secondary navigation of its own: Designer adds one, with the activity tab. The course link
    # comes first, the hero activities are placed relative to the activity tab.
    Then I check heroactivity position "Course" "1"
    And I check heroactivity position "Demo assign 1" "2"
    And I check heroactivity position "Page" "3"
    And I check heroactivity position "Demo assign 2" "4"
    And I click on "Course" "link" in the ".secondary-navigation" "css_element"
    And I should see "Demo page" in the "region-main" "region"
