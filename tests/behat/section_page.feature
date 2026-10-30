@format @format_designer @javascript @designer_section_page
Feature: Section pages in designer format
  In order to move between the sections of a course with one section per page
  As a student
  I need every section link to lead to the section page, with the course in the breadcrumb

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | First    | teacher1@example.com |
      | student1 | Student   | First    | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format   | coursedisplay | numsections | courseindex |
      | Course 1 | C1        | designer | 1             | 3           | 1           |
    And the following "activities" exist:
      | activity | name   | course | section |
      | page     | Page 1 | C1     | 1       |
      | page     | Page 2 | C1     | 2       |
      | page     | Page 3 | C1     | 3       |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |

  Scenario: Section links and the section navigation lead to the section page
    Given I am on the "Course 1" course page logged in as student1
    And ".secondary-navigation" "css_element" should exist
    When I click on "Designer section 2" "link" in the "region-main" "region"
    Then I should see "Designer section 2" in the ".breadcrumb" "css_element"
    And I should see "Page 2" in the "region-main" "region"
    And I should not see "Page 1" in the "region-main" "region"
    And ".secondary-navigation" "css_element" should not exist
    And I click on "Designer section 3" "link" in the ".section-navigation" "css_element"
    And I should see "Designer section 3" in the ".breadcrumb" "css_element"
    And I should see "Page 3" in the "region-main" "region"
    And I click on "C1" "link" in the ".breadcrumb" "css_element"
    And I should see "Designer section 1" in the "region-main" "region"
    And ".secondary-navigation" "css_element" should exist

  Scenario: The breadcrumb of the general section page ends with the general section
    Given I am on the "Course 1" course page logged in as student1
    When I click on "General" "link" in the "region-main" "region"
    Then I should see "General" in the ".breadcrumb" "css_element"
    And I should not see "Designer section 1" in the ".breadcrumb" "css_element"
    And "C1" "link" should exist in the ".breadcrumb" "css_element"

  Scenario: The view item of the section menu leads to the section page
    Given I am on the "Course 1" course page logged in as teacher1
    And I turn editing mode on
    When I open section "2" edit menu
    And I click on "View" "link" in the "#section-2" "css_element"
    Then I should see "Designer section 2" in the ".breadcrumb" "css_element"
    And "View" "link" should not exist in the "#section-2" "css_element"
