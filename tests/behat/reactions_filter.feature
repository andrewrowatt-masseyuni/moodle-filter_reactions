@filter @filter_reactions
Feature: Reactions filter renders interactive widgets
  In order to gather feedback from users
  As a teacher
  I need the reactions filter to render thumbs and star widgets from tags in content

  Background:
    Given the following "courses" exist:
      | shortname | fullname |
      | C1        | Course 1 |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
      | student2 | Student   | 2        | student2@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
      | student2 | C1     | student        |
    And the "reactions" filter is "on"

  Scenario: Thumbs widget is rendered from filter tag
    Given the following "activities" exist:
      | activity | name       | intro                        | introformat | course | content                              | contentformat |
      | page     | Test Page  | Test page with reactions      | 1           | C1     | <p>Rate this: {reactions:thumbs}</p> | 1             |
    When I am on the "Test Page" "page activity" page logged in as student1
    Then "[data-region='filter-reactions'][data-type='thumbs']" "css_element" should exist
    And I should see "Rate this:"
    And "Thumbs up" "button" should exist
    And "Thumbs down" "button" should exist

  Scenario: Stars widget is rendered from filter tag
    Given the following "activities" exist:
      | activity | name       | intro                        | introformat | course | content                             | contentformat |
      | page     | Test Page  | Test page with reactions      | 1           | C1     | <p>Rate this: {reactions:stars}</p>  | 1             |
    When I am on the "Test Page" "page activity" page logged in as student1
    Then "[data-region='filter-reactions'][data-type='stars']" "css_element" should exist
    And I should see "Your rating"
    And "1 stars" "button" should exist
    And "5 stars" "button" should exist

  Scenario: Multiple widgets render independently
    Given the following "activities" exist:
      | activity | name       | intro                        | introformat | course | content                                                         | contentformat |
      | page     | Test Page  | Test page with reactions      | 1           | C1     | <p>{reactions:thumbs,item1}</p><p>{reactions:stars,item2}</p>    | 1             |
    When I am on the "Test Page" "page activity" page logged in as student1
    Then "[data-region='filter-reactions'][data-type='thumbs']" "css_element" should exist
    And "[data-region='filter-reactions'][data-type='stars']" "css_element" should exist

  Scenario: Invalid reaction type is not rendered
    Given the following "activities" exist:
      | activity | name       | intro                        | introformat | course | content                                | contentformat |
      | page     | Test Page  | Test page with reactions      | 1           | C1     | <p>Rate: {reactions:invalid}</p>        | 1             |
    When I am on the "Test Page" "page activity" page logged in as student1
    Then "[data-region='filter-reactions']" "css_element" should not exist
    And I should see "{reactions:invalid}"

  @javascript
  Scenario: Student can click thumbs up and see count update
    Given the following "activities" exist:
      | activity | name       | intro                        | introformat | course | content                              | contentformat |
      | page     | Test Page  | Test page with reactions      | 1           | C1     | <p>Rate this: {reactions:thumbs}</p> | 1             |
    When I am on the "Test Page" "page activity" page logged in as student1
    And I click on "Thumbs up" "button"
    Then the "aria-pressed" attribute of "Thumbs up" "button" should contain "true"

  @javascript
  Scenario: Student can toggle thumbs up off
    Given the following "activities" exist:
      | activity | name       | intro                        | introformat | course | content                              | contentformat |
      | page     | Test Page  | Test page with reactions      | 1           | C1     | <p>Rate this: {reactions:thumbs}</p> | 1             |
    When I am on the "Test Page" "page activity" page logged in as student1
    And I click on "Thumbs up" "button"
    And the "aria-pressed" attribute of "Thumbs up" "button" should contain "true"
    And I click on "Thumbs up" "button"
    Then the "aria-pressed" attribute of "Thumbs up" "button" should contain "false"

  @javascript
  Scenario: Student can switch from thumbs up to thumbs down
    Given the following "activities" exist:
      | activity | name       | intro                        | introformat | course | content                              | contentformat |
      | page     | Test Page  | Test page with reactions      | 1           | C1     | <p>Rate this: {reactions:thumbs}</p> | 1             |
    When I am on the "Test Page" "page activity" page logged in as student1
    And I click on "Thumbs up" "button"
    And the "aria-pressed" attribute of "Thumbs up" "button" should contain "true"
    And I click on "Thumbs down" "button"
    Then the "aria-pressed" attribute of "Thumbs down" "button" should contain "true"
    And the "aria-pressed" attribute of "Thumbs up" "button" should contain "false"

  @javascript
  Scenario: Student can rate with stars
    Given the following "activities" exist:
      | activity | name       | intro                        | introformat | course | content                             | contentformat |
      | page     | Test Page  | Test page with reactions      | 1           | C1     | <p>Rate this: {reactions:stars}</p>  | 1             |
    When I am on the "Test Page" "page activity" page logged in as student1
    And I click on "3 stars" "button"
    Then ".filter-reactions-star.selected[data-value='3']" "css_element" should exist
    And ".filter-reactions-star.filled[data-value='1']" "css_element" should exist
    And ".filter-reactions-star.filled[data-value='2']" "css_element" should exist

  @javascript
  Scenario: Student can change star rating
    Given the following "activities" exist:
      | activity | name       | intro                        | introformat | course | content                             | contentformat |
      | page     | Test Page  | Test page with reactions      | 1           | C1     | <p>Rate this: {reactions:stars}</p>  | 1             |
    When I am on the "Test Page" "page activity" page logged in as student1
    And I click on "3 stars" "button"
    And ".filter-reactions-star.selected[data-value='3']" "css_element" should exist
    And I click on "5 stars" "button"
    Then ".filter-reactions-star.selected[data-value='5']" "css_element" should exist
    And ".filter-reactions-star.selected[data-value='3']" "css_element" should not exist

  @javascript
  Scenario: Reactions persist across page reloads
    Given the following "activities" exist:
      | activity | name       | intro                        | introformat | course | content                              | contentformat |
      | page     | Test Page  | Test page with reactions      | 1           | C1     | <p>Rate this: {reactions:thumbs}</p> | 1             |
    When I am on the "Test Page" "page activity" page logged in as student1
    And I click on "Thumbs up" "button"
    And the "aria-pressed" attribute of "Thumbs up" "button" should contain "true"
    And I reload the page
    Then the "aria-pressed" attribute of "Thumbs up" "button" should contain "true"

  @javascript
  Scenario: Different users see each other's reaction counts
    Given the following "activities" exist:
      | activity | name       | intro                        | introformat | course | content                              | contentformat |
      | page     | Test Page  | Test page with reactions      | 1           | C1     | <p>Rate this: {reactions:thumbs}</p> | 1             |
    When I am on the "Test Page" "page activity" page logged in as student1
    And I click on "Thumbs up" "button"
    And I log out
    And I am on the "Test Page" "page activity" page logged in as student2
    Then I should see "1" in the "[data-response='thumbsup'] [data-region='count']" "css_element"

  @javascript
  Scenario: Star rating persists across page reloads
    Given the following "activities" exist:
      | activity | name       | intro                        | introformat | course | content                             | contentformat |
      | page     | Test Page  | Test page with reactions      | 1           | C1     | <p>Rate this: {reactions:stars}</p>  | 1             |
    When I am on the "Test Page" "page activity" page logged in as student1
    And I click on "4 stars" "button"
    And ".filter-reactions-star.selected[data-value='4']" "css_element" should exist
    And I reload the page
    Then ".filter-reactions-star.selected[data-value='4']" "css_element" should exist

  @javascript
  Scenario: Custom item IDs keep reactions separate
    Given the following "activities" exist:
      | activity | name       | intro                        | introformat | course | content                                                                    | contentformat |
      | page     | Test Page  | Test page with reactions      | 1           | C1     | <p>{reactions:thumbs,widgetA}</p><p>{reactions:thumbs,widgetB}</p>          | 1             |
    When I am on the "Test Page" "page activity" page logged in as student1
    And I click on "[data-itemid='widgetA'] [data-response='thumbsup']" "css_element"
    Then "[data-itemid='widgetA'] [data-response='thumbsup'][aria-pressed='true']" "css_element" should exist
    And "[data-itemid='widgetB'] [data-response='thumbsup'][aria-pressed='false']" "css_element" should exist
