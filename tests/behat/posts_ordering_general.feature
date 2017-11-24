@mod @mod_foreact
Feature: New discussions and discussions with recently added replies are displayed first
  In order to use foreact as a discussion tool
  As a user
  I need to see currently active discussions first

  Background:
    Given the following "users" exist:
      | username  | firstname | lastname  | email                 |
      | teacher1  | Teacher   | 1         | teacher1@example.com  |
      | student1  | Student   | 1         | student1@example.com  |
    And the following "courses" exist:
      | fullname  | shortname | category  |
      | Course 1  | C1        | 0         |
    And the following "course enrolments" exist:
      | user      | course    | role            |
      | teacher1  | C1        | editingteacher  |
      | student1  | C1        | student         |
    And I log in as "teacher1"
    And I am on "Course 1" course homepage with editing mode on
    And I add a "foreact" to section "1" and I fill the form with:
      | foreact name  | Course general foreact                |
      | Description | Single discussion foreact description |
      | foreact type  | Standard foreact for general use      |
    And I log out

  #
  # We need javascript/wait to prevent creation of the posts in the same second. The threads
  # would then ignore each other in the prev/next navigation as the foreact is unable to compute
  # the correct order.
  #
  @javascript
  Scenario: Replying to a foreact post or editing it puts the discussion to the front
    Given I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Course general foreact"
    #
    # Add three posts into the foreact.
    #
    When I add a new discussion to "Course general foreact" foreact with:
      | Subject | foreact post 1            |
      | Message | This is the first post  |
    And I add a new discussion to "Course general foreact" foreact with:
      | Subject | foreact post 2            |
      | Message | This is the second post |
    And I add a new discussion to "Course general foreact" foreact with:
      | Subject | foreact post 3            |
      | Message | This is the third post  |
    #
    # Edit one of the foreact posts.
    #
    And I follow "foreact post 2"
    And I click on "Edit" "link" in the "//div[contains(concat(' ', normalize-space(@class), ' '), ' foreactpost ')][contains(., 'foreact post 2')]" "xpath_element"
    And I set the following fields to these values:
      | Subject | Edited foreact post 2     |
    And I press "Save changes"
    And I wait to be redirected
    And I log out
    #
    # Reply to another foreact post.
    #
    And I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Course general foreact"
    And I follow "foreact post 1"
    And I click on "Reply" "link" in the "//div[@aria-label='foreact post 1 by Student 1']" "xpath_element"
    And I set the following fields to these values:
      | Message | Reply to the first post |
    And I press "Post to foreact"
    And I wait to be redirected
    And I am on "Course 1" course homepage
    And I follow "Course general foreact"
    #
    # Make sure the order of the foreact posts is as expected (most recently participated first).
    #
    Then I should see "foreact post 3" in the "//tr[contains(concat(' ', normalize-space(@class), ' '), ' discussion ')][position()=3]" "xpath_element"
    And I should see "Edited foreact post 2" in the "//tr[contains(concat(' ', normalize-space(@class), ' '), ' discussion ')][position()=2]" "xpath_element"
    And I should see "foreact post 1" in the "//tr[contains(concat(' ', normalize-space(@class), ' '), ' discussion ')][position()=1]" "xpath_element"
    #
    # Make sure the next/prev navigation uses the same order of the posts.
    #
    And I follow "Edited foreact post 2"
    And "//a[@aria-label='Next discussion: foreact post 1']" "xpath_element" should exist
    And "//a[@aria-label='Previous discussion: foreact post 3']" "xpath_element" should exist
