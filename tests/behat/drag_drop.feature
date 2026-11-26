@format @format_designer
Feature: Drag and drop of activities
  In order to arrange the activities of a section
  As a teacher
  I need to see where a dragged activity will be dropped

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
      | activity | name   | course | section |
      | page     | Page 1 | C1     | 1       |
      | page     | Page 2 | C1     | 1       |
      | page     | Page 3 | C1     | 1       |
      | page     | Page 4 | C1     | 1       |

  @javascript
  Scenario Outline: The drop position is shown while an activity is dragged
    Given I am on the "Course 1" course page logged in as teacher1
    And I turn editing mode on
    And I edit the section "1" to layout "<layout>"
    # Over an activity: the dragged activity goes before it.
    When I drag "Page 1" activity over "Page 3" "activity"
    Then the drop position should be shown before "Page 3" activity
    And I drop the dragged activity
    And "Page 2" "activity" should appear before "Page 1" "activity"
    And "Page 1" "activity" should appear before "Page 3" "activity"
    # Elsewhere in the section, e.g. between two activities: the dragged activity goes to the end of the section.
    And I drag "Page 2" activity over "#section-1 [data-for='cmlist']" "css_element"
    And the drop position should be shown after "Page 4" activity

    Examples:
      | layout |
      | link   |
      | list   |
      | cards  |
