@filter @filter_reactions @javascript
Feature: Backup and restore courses with reactions
  In order to preserve reaction data when duplicating courses
  As a teacher
  I need reactions to survive course backup and restore

  Background:
    Given the following "courses" exist:
      | shortname | fullname |
      | C1        | Course 1 |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity | name      | intro                        | introformat | course | content                              | contentformat |
      | page     | Test Page | Test page with reactions      | 1           | C1     | <p>Rate this: {reactions:thumbs}</p> | 1             |
    And the "reactions" filter is "on"
    And the following config values are set as admin:
      | enableasyncbackup | 0 |

  Scenario: Reactions are preserved after backup and restore
    # Student adds a thumbs up reaction.
    When I am on the "Test Page" "page activity" page logged in as student1
    And I click on "Thumbs up" "button"
    And the "aria-pressed" attribute of "Thumbs up" "button" should contain "true"
    And I log out
    # Admin backs up and restores the course into a new course.
    And I log in as "admin"
    And I backup "Course 1" course using this options:
      | Confirmation | Filename | test_backup.mbz |
    And I restore "test_backup.mbz" backup into a new course using this options:
      | Schema | Course name       | Course 1 restored |
      | Schema | Course short name | C1R               |
    Then I should see "Course 1 restored"
    And I should see "Test Page"
    And I log out
    # Verify the reaction was restored for the student.
    And I log in as "student1"
    And I am on "C1R" course homepage
    And I follow "Test Page"
    And the "aria-pressed" attribute of "Thumbs up" "button" should contain "true"
    And I should see "1" in the "[data-response='thumbsup'] [data-region='count']" "css_element"
