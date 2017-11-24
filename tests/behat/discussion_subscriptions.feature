@mod @mod_foreact
Feature: A user can control their own subscription preferences for a discussion
  In order to receive notifications for things I am interested in
  As a user
  I need to choose my discussion subscriptions

  Background:
    Given the following "users" exist:
      | username | firstname | lastname | email |
      | student1 | Student   | One      | student.one@example.com |
    And the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1 | 0 |
    And the following "course enrolments" exist:
      | user | course | role |
      | student1 | C1 | student |
    And I log in as "admin"
    And I am on "Course 1" course homepage with editing mode on

  Scenario: An optional foreact can have discussions subscribed to
    Given I add a "foreact" to section "1" and I fill the form with:
      | foreact name        | Test foreact name |
      | foreact type        | Standard foreact for general use |
      | Description       | Test foreact description |
      | Subscription mode | Optional subscription |
    And I add a new discussion to "Test foreact name" foreact with:
      | Subject | Test post subject one |
      | Message | Test post message one |
    And I add a new discussion to "Test foreact name" foreact with:
      | Subject | Test post subject two |
      | Message | Test post message two |
    And I log out
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test foreact name"
    Then I should see "Subscribe to this foreact"
    And "You are not subscribed to this discussion. Click to subscribe." "link" should exist in the "Test post subject one" "table_row"
    And "You are not subscribed to this discussion. Click to subscribe." "link" should exist in the "Test post subject two" "table_row"
    And I click on "You are not subscribed to this discussion. Click to subscribe." "link" in the "Test post subject one" "table_row"
    And I should see "Student One will be notified of new posts in 'Test post subject one' of 'Test foreact name'"
    And I should see "Subscribe to this foreact"
    And "You are subscribed to this discussion. Click to unsubscribe." "link" should exist in the "Test post subject one" "table_row"
    And "You are not subscribed to this discussion. Click to subscribe." "link" should exist in the "Test post subject two" "table_row"
    And I click on "You are subscribed to this discussion. Click to unsubscribe." "link" in the "Test post subject one" "table_row"
    And I should see "Student One will NOT be notified of new posts in 'Test post subject one' of 'Test foreact name'"
    And I should see "Subscribe to this foreact"
    And "You are not subscribed to this discussion. Click to subscribe." "link" should exist in the "Test post subject one" "table_row"
    And "You are not subscribed to this discussion. Click to subscribe." "link" should exist in the "Test post subject two" "table_row"
    And I click on "You are not subscribed to this discussion. Click to subscribe." "link" in the "Test post subject one" "table_row"
    And I should see "Student One will be notified of new posts in 'Test post subject one' of 'Test foreact name'"
    And I should see "Subscribe to this foreact"
    And "You are subscribed to this discussion. Click to unsubscribe." "link" should exist in the "Test post subject one" "table_row"
    And "You are not subscribed to this discussion. Click to subscribe." "link" should exist in the "Test post subject two" "table_row"
    And I follow "Subscribe to this foreact"
    And I should see "Student One will be notified of new posts in 'Test foreact name'"
    And I should see "Unsubscribe from this foreact"
    And "You are subscribed to this discussion. Click to unsubscribe." "link" should exist in the "Test post subject one" "table_row"
    And "You are subscribed to this discussion. Click to unsubscribe." "link" should exist in the "Test post subject two" "table_row"
    And I follow "Unsubscribe from this foreact"
    And I should see "Student One will NOT be notified of new posts in 'Test foreact name'"
    And I should see "Subscribe to this foreact"
    And "You are not subscribed to this discussion. Click to subscribe." "link" should exist in the "Test post subject one" "table_row"
    And "You are not subscribed to this discussion. Click to subscribe." "link" should exist in the "Test post subject two" "table_row"

  Scenario: An automatic subscription foreact can have discussions unsubscribed from
    Given I add a "foreact" to section "1" and I fill the form with:
      | foreact name        | Test foreact name |
      | foreact type        | Standard foreact for general use |
      | Description       | Test foreact description |
      | Subscription mode | Auto subscription |
    And I add a new discussion to "Test foreact name" foreact with:
      | Subject | Test post subject one |
      | Message | Test post message one |
    And I add a new discussion to "Test foreact name" foreact with:
      | Subject | Test post subject two |
      | Message | Test post message two |
    And I log out
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test foreact name"
    Then I should see "Unsubscribe from this foreact"
    And "You are subscribed to this discussion. Click to unsubscribe." "link" should exist in the "Test post subject one" "table_row"
    And "You are subscribed to this discussion. Click to unsubscribe." "link" should exist in the "Test post subject two" "table_row"
    And I click on "You are subscribed to this discussion. Click to unsubscribe." "link" in the "Test post subject one" "table_row"
    And I should see "Student One will NOT be notified of new posts in 'Test post subject one' of 'Test foreact name'"
    And I should see "Unsubscribe from this foreact"
    And "You are not subscribed to this discussion. Click to subscribe." "link" should exist in the "Test post subject one" "table_row"
    And "You are subscribed to this discussion. Click to unsubscribe." "link" should exist in the "Test post subject two" "table_row"
    And I click on "You are not subscribed to this discussion. Click to subscribe." "link" in the "Test post subject one" "table_row"
    And I should see "Student One will be notified of new posts in 'Test post subject one' of 'Test foreact name'"
    And I should see "Unsubscribe from this foreact"
    And "You are subscribed to this discussion. Click to unsubscribe." "link" should exist in the "Test post subject one" "table_row"
    And "You are subscribed to this discussion. Click to unsubscribe." "link" should exist in the "Test post subject two" "table_row"
    And I click on "You are subscribed to this discussion. Click to unsubscribe." "link" in the "Test post subject one" "table_row"
    And I should see "Student One will NOT be notified of new posts in 'Test post subject one' of 'Test foreact name'"
    And I should see "Unsubscribe from this foreact"
    And "You are not subscribed to this discussion. Click to subscribe." "link" should exist in the "Test post subject one" "table_row"
    And "You are subscribed to this discussion. Click to unsubscribe." "link" should exist in the "Test post subject two" "table_row"
    And I follow "Unsubscribe from this foreact"
    And I should see "Student One will NOT be notified of new posts in 'Test foreact name'"
    And I should see "Subscribe to this foreact"
    And "You are not subscribed to this discussion. Click to subscribe." "link" should exist in the "Test post subject one" "table_row"
    And "You are not subscribed to this discussion. Click to subscribe." "link" should exist in the "Test post subject two" "table_row"
    And I follow "Subscribe to this foreact"
    And I should see "Student One will be notified of new posts in 'Test foreact name'"
    And I should see "Unsubscribe from this foreact"
    And "You are subscribed to this discussion. Click to unsubscribe." "link" should exist in the "Test post subject one" "table_row"
    And "You are subscribed to this discussion. Click to unsubscribe." "link" should exist in the "Test post subject two" "table_row"

  Scenario: A user does not lose their preferences when a foreact is switch from optional to automatic
    Given I add a "foreact" to section "1" and I fill the form with:
      | foreact name        | Test foreact name |
      | foreact type        | Standard foreact for general use |
      | Description       | Test foreact description |
      | Subscription mode | Optional subscription |
    And I add a new discussion to "Test foreact name" foreact with:
      | Subject | Test post subject one |
      | Message | Test post message one |
    And I add a new discussion to "Test foreact name" foreact with:
      | Subject | Test post subject two |
      | Message | Test post message two |
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test foreact name"
    And I should see "Subscribe to this foreact"
    And "You are not subscribed to this discussion. Click to subscribe." "link" should exist in the "Test post subject one" "table_row"
    And "You are not subscribed to this discussion. Click to subscribe." "link" should exist in the "Test post subject two" "table_row"
    And I click on "You are not subscribed to this discussion. Click to subscribe." "link" in the "Test post subject one" "table_row"
    And I should see "Student One will be notified of new posts in 'Test post subject one' of 'Test foreact name'"
    And I should see "Subscribe to this foreact"
    And "You are subscribed to this discussion. Click to unsubscribe." "link" should exist in the "Test post subject one" "table_row"
    And "You are not subscribed to this discussion. Click to subscribe." "link" should exist in the "Test post subject two" "table_row"
    And I log out
    And I log in as "admin"
    And I am on "Course 1" course homepage
    And I follow "Test foreact name"
    And I navigate to "Edit settings" in current page administration
    And I set the following fields to these values:
      | Subscription mode | Auto subscription |
    And I press "Save and return to course"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test foreact name"
    And I should see "Unsubscribe from this foreact"
    And "You are subscribed to this discussion. Click to unsubscribe." "link" should exist in the "Test post subject one" "table_row"
    And "You are subscribed to this discussion. Click to unsubscribe." "link" should exist in the "Test post subject two" "table_row"
    When I follow "Unsubscribe from this foreact"
    Then I should see "Student One will NOT be notified of new posts in 'Test foreact name'"
    And I should see "Subscribe to this foreact"
    And "You are subscribed to this discussion. Click to unsubscribe." "link" should exist in the "Test post subject one" "table_row"
    And "You are not subscribed to this discussion. Click to subscribe." "link" should exist in the "Test post subject two" "table_row"

  Scenario: A user does not lose their preferences when a foreact is switch from optional to automatic
    Given I add a "foreact" to section "1" and I fill the form with:
      | foreact name        | Test foreact name |
      | foreact type        | Standard foreact for general use |
      | Description       | Test foreact description |
      | Subscription mode | Optional subscription |
    And I add a new discussion to "Test foreact name" foreact with:
      | Subject | Test post subject one |
      | Message | Test post message one |
    And I add a new discussion to "Test foreact name" foreact with:
      | Subject | Test post subject two |
      | Message | Test post message two |
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test foreact name"
    And I should see "Subscribe to this foreact"
    And "You are not subscribed to this discussion. Click to subscribe." "link" should exist in the "Test post subject one" "table_row"
    And "You are not subscribed to this discussion. Click to subscribe." "link" should exist in the "Test post subject two" "table_row"
    And I click on "You are not subscribed to this discussion. Click to subscribe." "link" in the "Test post subject one" "table_row"
    And I should see "Student One will be notified of new posts in 'Test post subject one' of 'Test foreact name'"
    And I should see "Subscribe to this foreact"
    And "You are subscribed to this discussion. Click to unsubscribe." "link" should exist in the "Test post subject one" "table_row"
    And "You are not subscribed to this discussion. Click to subscribe." "link" should exist in the "Test post subject two" "table_row"
    And I log out
    And I log in as "admin"
    And I am on "Course 1" course homepage
    And I follow "Test foreact name"
    And I navigate to "Edit settings" in current page administration
    And I set the following fields to these values:
      | Subscription mode | Auto subscription |
    And I press "Save and return to course"
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test foreact name"
    And I should see "Unsubscribe from this foreact"
    And "You are subscribed to this discussion. Click to unsubscribe." "link" should exist in the "Test post subject one" "table_row"
    And "You are subscribed to this discussion. Click to unsubscribe." "link" should exist in the "Test post subject two" "table_row"
    When I follow "Unsubscribe from this foreact"
    And I should see "Student One will NOT be notified of new posts in 'Test foreact name'"
    Then I should see "Subscribe to this foreact"
    And "You are subscribed to this discussion. Click to unsubscribe." "link" should exist in the "Test post subject one" "table_row"
    And "You are not subscribed to this discussion. Click to subscribe." "link" should exist in the "Test post subject two" "table_row"

  Scenario: An optional foreact prompts a user to subscribe to a discussion when posting unless they have already chosen not to subscribe
    Given I add a "foreact" to section "1" and I fill the form with:
      | foreact name        | Test foreact name |
      | foreact type        | Standard foreact for general use |
      | Description       | Test foreact description |
      | Subscription mode | Optional subscription |
    And I add a new discussion to "Test foreact name" foreact with:
      | Subject | Test post subject one |
      | Message | Test post message one |
    And I add a new discussion to "Test foreact name" foreact with:
      | Subject | Test post subject two |
      | Message | Test post message two |
    And I log out
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test foreact name"
    And I should see "Subscribe to this foreact"
    And I reply "Test post subject one" post from "Test foreact name" foreact with:
      | Subject | Reply 1 to discussion 1 |
      | Message | Discussion contents 1, second message |
      | Discussion subscription | 1 |
    And I reply "Test post subject two" post from "Test foreact name" foreact with:
      | Subject | Reply 1 to discussion 1 |
      | Message | Discussion contents 1, second message |
      | Discussion subscription | 0 |
    And I follow "Test foreact name"
    Then "You are subscribed to this discussion. Click to unsubscribe." "link" should exist in the "Test post subject one" "table_row"
    And "You are not subscribed to this discussion. Click to subscribe." "link" should exist in the "Test post subject two" "table_row"
    And I follow "Test post subject one"
    And I follow "Reply"
    And the field "Discussion subscription" matches value "Send me notifications of new posts in this discussion"
    And I follow "Test foreact name"
    And I follow "Test post subject two"
    And I follow "Reply"
    And the field "Discussion subscription" matches value "I don't want to be notified of new posts in this discussion"

  Scenario: An automatic foreact prompts a user to subscribe to a discussion when posting unless they have already chosen not to subscribe
    Given I add a "foreact" to section "1" and I fill the form with:
      | foreact name        | Test foreact name |
      | foreact type        | Standard foreact for general use |
      | Description       | Test foreact description |
      | Subscription mode | Auto subscription |
    And I add a new discussion to "Test foreact name" foreact with:
      | Subject | Test post subject one |
      | Message | Test post message one |
    And I add a new discussion to "Test foreact name" foreact with:
      | Subject | Test post subject two |
      | Message | Test post message two |
    And I log out
    When I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Test foreact name"
    And I should see "Unsubscribe from this foreact"
    And I reply "Test post subject one" post from "Test foreact name" foreact with:
      | Subject | Reply 1 to discussion 1 |
      | Message | Discussion contents 1, second message |
      | Discussion subscription | 1 |
    And I reply "Test post subject two" post from "Test foreact name" foreact with:
      | Subject | Reply 1 to discussion 1 |
      | Message | Discussion contents 1, second message |
      | Discussion subscription | 0 |
    And I follow "Test foreact name"
    Then "You are subscribed to this discussion. Click to unsubscribe." "link" should exist in the "Test post subject one" "table_row"
    And "You are not subscribed to this discussion. Click to subscribe." "link" should exist in the "Test post subject two" "table_row"
    And I follow "Test post subject one"
    And I follow "Reply"
    And the field "Discussion subscription" matches value "Send me notifications of new posts in this discussion"
    And I follow "Test foreact name"
    And I follow "Test post subject two"
    And I follow "Reply"
    And the field "Discussion subscription" matches value "I don't want to be notified of new posts in this discussion"

  Scenario: A guest should not be able to subscribe to a discussion
    Given I am on site homepage
    And I add a "foreact" to section "1" and I fill the form with:
     | foreact name        | Test foreact name |
     | foreact type        | Standard foreact for general use |
     | Description       | Test foreact description |
    And I add a new discussion to "Test foreact name" foreact with:
     | Subject | Test post subject one |
     | Message | Test post message one |
    And I log out
    When I log in as "guest"
    And I follow "Test foreact name"
    Then "You are not subscribed to this discussion. Click to subscribe." "link" should not exist in the "Test post subject one" "table_row"
    And "You are subscribed to this discussion. Click to unsubscribe." "link" should not exist in the "Test post subject one" "table_row"
    And I follow "Test post subject one"
    And "You are not subscribed to this discussion. Click to subscribe." "link" should not exist
    And "You are subscribed to this discussion. Click to unsubscribe." "link" should not exist

  Scenario: A user who is not logged in should not be able to subscribe to a discussion
    Given I am on site homepage
    And I add a "foreact" to section "1" and I fill the form with:
     | foreact name        | Test foreact name |
     | foreact type        | Standard foreact for general use |
     | Description       | Test foreact description |
    And I add a new discussion to "Test foreact name" foreact with:
     | Subject | Test post subject one |
     | Message | Test post message one |
    And I log out
    When I follow "Test foreact name"
    Then "You are not subscribed to this discussion. Click to subscribe." "link" should not exist in the "Test post subject one" "table_row"
    And "You are subscribed to this discussion. Click to unsubscribe." "link" should not exist in the "Test post subject one" "table_row"
    And I follow "Test post subject one"
    And "You are not subscribed to this discussion. Click to subscribe." "link" should not exist
    And "You are subscribed to this discussion. Click to unsubscribe." "link" should not exist

  Scenario: A user can toggle their subscription preferences when viewing a discussion
    Given I add a "foreact" to section "1" and I fill the form with:
      | foreact name        | Test foreact name |
      | foreact type        | Standard foreact for general use |
      | Description       | Test foreact description |
      | Subscription mode | Optional subscription |
    And I add a new discussion to "Test foreact name" foreact with:
      | Subject | Test post subject one |
      | Message | Test post message one |
    And I log out
    And I log in as "student1"
    And I am on "Course 1" course homepage
    When I follow "Test foreact name"
    Then "Subscribe to this foreact" "link" should exist in current page administration
    And I follow "Test post subject one"
    And "You are not subscribed to this discussion. Click to subscribe" "link" should exist
    And I follow "Test foreact name"
    And I navigate to "Subscribe to this foreact" in current page administration
    And I should see "Student One will be notified of new posts in 'Test foreact name'"
    And "Unsubscribe from this foreact" "link" should exist in current page administration
    And I follow "Test post subject one"
    And "You are subscribed to this discussion. Click to unsubscribe" "link" should exist
    And I follow "You are subscribed to this discussion. Click to unsubscribe"
    And I should see "Student One will NOT be notified of new posts in 'Test post subject one' of 'Test foreact name'"
    And I follow "Test post subject one"
    #And I should see "Unsubscribe from this foreact"
    And "You are not subscribed to this discussion. Click to subscribe" "link" should exist
    And I follow "Test foreact name"
    And I navigate to "Unsubscribe from this foreact" in current page administration
    And I should see "Student One will NOT be notified of new posts in 'Test foreact name'"
    And "Subscribe to this foreact" "link" should exist in current page administration
    And I follow "Test post subject one"
    And "You are not subscribed to this discussion. Click to subscribe" "link" should exist
    And I follow "You are not subscribed to this discussion. Click to subscribe"
    And I should see "Student One will be notified of new posts in 'Test post subject one' of 'Test foreact name'"
    And "You are subscribed to this discussion. Click to unsubscribe" "link" should exist
    And I follow "Test foreact name"
    And I navigate to "Subscribe to this foreact" in current page administration
    And I should see "Student One will be notified of new posts in 'Test foreact name'"
    And "Unsubscribe from this foreact" "link" should exist in current page administration
    And I follow "Test post subject one"
    And "You are subscribed to this discussion. Click to unsubscribe" "link" should exist
    And I follow "Test foreact name"
    And I navigate to "Unsubscribe from this foreact" in current page administration
    And I should see "Student One will NOT be notified of new posts in 'Test foreact name'"
    And "Subscribe to this foreact" "link" should exist in current page administration
    And I follow "Test post subject one"
    And "You are not subscribed to this discussion. Click to subscribe" "link" should exist
