@format @format_designer @javascript
Feature: The course index follows the changes of the course while editing
  In order to find my way in the course I am editing
  As a teacher
  I need the course index to show new sections right away

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format   | numsections |
      | Course 1 | C1        | designer | 2           |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |

  Scenario: A new section appears in the course index without reloading the page
    Given I am on the "Course 1" course page logged in as teacher1
    And I turn editing mode on
    And I click on "Open course index" "button"
    And I should see "Designer section 2" in the "courseindex-content" "region"
    And I should not see "Designer section 3" in the "courseindex-content" "region"
    When I click on "Add section" "link" in the "course-addsection" "region"
    Then I should see "Designer section 3" in the "courseindex-content" "region"
