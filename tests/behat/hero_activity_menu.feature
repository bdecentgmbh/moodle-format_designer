@format @format_designer @javascript @designer_hero_activity
Feature: The secondary navigation keeps its More menu in courses with hero activities
  In order to reach every page of the course
  As a teacher
  I need the items that don't fit in the secondary navigation to stay in its More menu

  Background:
    # The bug was in Designer Basic. Designer Pro has its own way through the same script.
    Given Designer Pro is not installed
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format   | numsections |
      | Course 1 | C1        | designer | 1           |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "activities" exist:
      | activity | name    | course | idnumber | section |
      | page     | Page 1  | C1     | page1    | 1       |

  Scenario: Items that don't fit move into the More menu with a hero activity
    Given I am on the "Page 1" "page activity editing" page logged in as admin
    And I expand all fieldsets
    And I set the field "Show as tab" to "Everywhere"
    And I press "Save and return to course"
    And I change window size to "1200x900"
    When I am on the "Course 1" course page logged in as teacher1
    Then I should see "Page 1" in the ".secondary-navigation" "css_element"
    And the visible ".secondary-navigation .moremenu .nav-tabs > li" css elements should be in one row
