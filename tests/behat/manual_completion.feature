@format @format_designer
Feature: Manual completion on the Designer course page
  In order to track my progress
  As a student
  I need to mark activities as done on the course page

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | First    | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format   | numsections | enablecompletion | coursedisplay |
      | Course 1 | C1        | designer | 1           | 1                | 0             |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    And the following "activities" exist:
      | activity | name   | course | section | completion |
      | page     | Page 1 | C1     | 1       | 1          |

  @javascript
  Scenario: Mark an activity as done on the course page
    Given I am on the "Course 1" course page logged in as student1
    When I click on "Mark as done" "button" in the "Page 1" "activity"
    Then I should see "Done" in the "Page 1" "activity"
    And I should see "Page 1" in the "region-main" "region"
