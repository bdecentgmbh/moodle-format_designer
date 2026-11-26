@format @format_designer
Feature: Activity menus in edit mode
  In order to edit the activities of a section
  As a teacher
  I need to use the menus of an activity without the next activity covering them

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | First    | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format   | numsections |
      | Course 1 | C1        | designer | 1           |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
    And the following "activities" exist:
      | activity | name   | course | section | visible |
      | page     | Page 1 | C1     | 1       | 1       |
      | page     | Page 2 | C1     | 1       | 0       |
      | page     | Page 3 | C1     | 1       | 1       |
      | page     | Page 4 | C1     | 1       | 1       |

  @javascript
  Scenario Outline: The visibility menu of a hidden activity opens above the next activities
    # Room below the activity, so that the menu opens downwards, over the next activities.
    Given I change viewport size to "1366x2000"
    And I am on the "Course 1" course page logged in as teacher1
    And I turn editing mode on
    # The visibility badge of the activities is shown in the List layout.
    And I edit the section "1" to layout "<layout>"
    When I click on "Hidden from students" "button" in the "Page 2" "activity"
    Then the visible ".dropdown-menu.show a" css elements should not be covered by other elements

    Examples:
      | layout |
      | list   |

  @javascript
  Scenario Outline: The action menu of an activity opens above the next activities
    # Room below the activity, so that the menu opens downwards, over the next activities.
    Given I change viewport size to "1366x2000"
    And I am on the "Course 1" course page logged in as teacher1
    And I turn editing mode on
    And I edit the section "1" to layout "<layout>"
    When I open "Page 2" actions menu
    Then the visible ".dropdown-menu.show a" css elements should not be covered by other elements

    Examples:
      | layout  |
      | link    |
      | list    |
      | cards   |
