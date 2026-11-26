@format @format_designer
Feature: Section cards of the course page with one section per page
  In order to choose a section
  As a student
  I need to see the sections as cards of the same height in each row

  @javascript
  Scenario: The section cards of a row have the same height
    Given the following "users" exist:
      | username | firstname | lastname | email                |
      | student1 | Student   | First    | student1@example.com |
    And the following "courses" exist:
      | fullname | shortname | format   | coursedisplay | numsections |
      | Course 1 | C1        | designer | 1             | 2           |
    And the following "course enrolments" exist:
      | user     | course | role    |
      | student1 | C1     | student |
    # Three cards in one row (General and two sections) with different content.
    And the following "activities" exist:
      | activity | name      | course | section |
      | page     | Page 1    | C1     | 1       |
      | assign   | Assign 1  | C1     | 1       |
      | forum    | Forum 1   | C1     | 1       |
      | quiz     | Quiz 1    | C1     | 1       |
      | url      | Url 1     | C1     | 1       |
      | label    | Label 1   | C1     | 1       |
      | page     | Page 2    | C1     | 2       |
    And I change window size to "1366x768"
    When I am on the "Course 1" course page logged in as student1
    # The cards and their headers, which carry a header background, end at the same height, with the arrows in one line.
    Then the visible ".course-content li.section .section-header-content" css elements in a row should have the same height
    And the visible ".course-content li.section .section-head-text" css elements should be in one row
    And the visible ".course-content li.section .goto-section" css elements should be in one row
