<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Tests for foreact events.
 *
 * @package    mod_foreact
 * @category   test
 * @copyright  2014 Dan Poltawski <dan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for foreact events.
 *
 * @package    mod_foreact
 * @category   test
 * @copyright  2014 Dan Poltawski <dan@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_foreact_events_testcase extends advanced_testcase {

    /**
     * Tests set up.
     */
    public function setUp() {
        // We must clear the subscription caches. This has to be done both before each test, and after in case of other
        // tests using these functions.
        \mod_foreact\subscriptions::reset_foreact_cache();

        $this->resetAfterTest();
    }

    public function tearDown() {
        // We must clear the subscription caches. This has to be done both before each test, and after in case of other
        // tests using these functions.
        \mod_foreact\subscriptions::reset_foreact_cache();
    }

    /**
     * Ensure course_searched event validates that searchterm is set.
     *
     * @expectedException        coding_exception
     * @expectedExceptionMessage The 'searchterm' value must be set in other.
     */
    public function test_course_searched_searchterm_validation() {
        $course = $this->getDataGenerator()->create_course();
        $coursectx = context_course::instance($course->id);
        $params = array(
            'context' => $coursectx,
        );

        \mod_foreact\event\course_searched::create($params);
    }

    /**
     * Ensure course_searched event validates that context is the correct level.
     *
     * @expectedException        coding_exception
     * @expectedExceptionMessage Context level must be CONTEXT_COURSE.
     */
    public function test_course_searched_context_validation() {
        $course = $this->getDataGenerator()->create_course();
        $foreact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));
        $context = context_module::instance($foreact->cmid);
        $params = array(
            'context' => $context,
            'other' => array('searchterm' => 'testing'),
        );

        \mod_foreact\event\course_searched::create($params);
    }

    /**
     * Test course_searched event.
     */
    public function test_course_searched() {

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $coursectx = context_course::instance($course->id);
        $searchterm = 'testing123';

        $params = array(
            'context' => $coursectx,
            'other' => array('searchterm' => $searchterm),
        );

        // Create event.
        $event = \mod_foreact\event\course_searched::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

         // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_foreact\event\course_searched', $event);
        $this->assertEquals($coursectx, $event->get_context());
        $expected = array($course->id, 'foreact', 'search', "search.php?id={$course->id}&amp;search={$searchterm}", $searchterm);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());
    }

    /**
     * Ensure discussion_created event validates that foreactid is set.
     *
     * @expectedException        coding_exception
     * @expectedExceptionMessage The 'foreactid' value must be set in other.
     */
    public function test_discussion_created_foreactid_validation() {
        $course = $this->getDataGenerator()->create_course();
        $foreact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));
        $context = context_module::instance($foreact->cmid);

        $params = array(
            'context' => $context,
        );

        \mod_foreact\event\discussion_created::create($params);
    }

    /**
     * Ensure discussion_created event validates that the context is the correct level.
     *
     * @expectedException        coding_exception
     * @expectedExceptionMessage Context level must be CONTEXT_MODULE.
     */
    public function test_discussion_created_context_validation() {
        $course = $this->getDataGenerator()->create_course();
        $foreact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));

        $params = array(
            'context' => context_system::instance(),
            'other' => array('foreactid' => $foreact->id),
        );

        \mod_foreact\event\discussion_created::create($params);
    }

    /**
     * Test discussion_created event.
     */
    public function test_discussion_created() {

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $foreact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['foreact'] = $foreact->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_discussion($record);

        $context = context_module::instance($foreact->cmid);

        $params = array(
            'context' => $context,
            'objectid' => $discussion->id,
            'other' => array('foreactid' => $foreact->id),
        );

        // Create the event.
        $event = \mod_foreact\event\discussion_created::create($params);

        // Trigger and capturing the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Check that the event contains the expected values.
        $this->assertInstanceOf('\mod_foreact\event\discussion_created', $event);
        $this->assertEquals($context, $event->get_context());
        $expected = array($course->id, 'foreact', 'add discussion', "discuss.php?d={$discussion->id}", $discussion->id, $foreact->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());
    }

    /**
     * Ensure discussion_updated event validates that foreactid is set.
     *
     * @expectedException        coding_exception
     * @expectedExceptionMessage The 'foreactid' value must be set in other.
     */
    public function test_discussion_updated_foreactid_validation() {
        $course = $this->getDataGenerator()->create_course();
        $foreact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));
        $context = context_module::instance($foreact->cmid);

        $params = array(
            'context' => $context,
        );

        \mod_foreact\event\discussion_updated::create($params);
    }

    /**
     * Ensure discussion_created event validates that the context is the correct level.
     *
     * @expectedException        coding_exception
     * @expectedExceptionMessage Context level must be CONTEXT_MODULE.
     */
    public function test_discussion_updated_context_validation() {
        $course = $this->getDataGenerator()->create_course();
        $foreact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));

        $params = array(
            'context' => context_system::instance(),
            'other' => array('foreactid' => $foreact->id),
        );

        \mod_foreact\event\discussion_updated::create($params);
    }

    /**
     * Test discussion_created event.
     */
    public function test_discussion_updated() {

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $foreact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['foreact'] = $foreact->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_discussion($record);

        $context = context_module::instance($foreact->cmid);

        $params = array(
            'context' => $context,
            'objectid' => $discussion->id,
            'other' => array('foreactid' => $foreact->id),
        );

        // Create the event.
        $event = \mod_foreact\event\discussion_updated::create($params);

        // Trigger and capturing the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Check that the event contains the expected values.
        $this->assertInstanceOf('\mod_foreact\event\discussion_updated', $event);
        $this->assertEquals($context, $event->get_context());
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());
    }

    /**
     * Ensure discussion_deleted event validates that foreactid is set.
     *
     * @expectedException        coding_exception
     * @expectedExceptionMessage The 'foreactid' value must be set in other.
     */
    public function test_discussion_deleted_foreactid_validation() {
        $course = $this->getDataGenerator()->create_course();
        $foreact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));
        $context = context_module::instance($foreact->cmid);

        $params = array(
            'context' => $context,
        );

        \mod_foreact\event\discussion_deleted::create($params);
    }

    /**
     * Ensure discussion_deleted event validates that context is of the correct level.
     *
     * @expectedException        coding_exception
     * @expectedExceptionMessage Context level must be CONTEXT_MODULE.
     */
    public function test_discussion_deleted_context_validation() {
        $course = $this->getDataGenerator()->create_course();
        $foreact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));

        $params = array(
            'context' => context_system::instance(),
            'other' => array('foreactid' => $foreact->id),
        );

        \mod_foreact\event\discussion_deleted::create($params);
    }

    /**
     * Test discussion_deleted event.
     */
    public function test_discussion_deleted() {

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $foreact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['foreact'] = $foreact->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_discussion($record);

        $context = context_module::instance($foreact->cmid);

        $params = array(
            'context' => $context,
            'objectid' => $discussion->id,
            'other' => array('foreactid' => $foreact->id),
        );

        $event = \mod_foreact\event\discussion_deleted::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_foreact\event\discussion_deleted', $event);
        $this->assertEquals($context, $event->get_context());
        $expected = array($course->id, 'foreact', 'delete discussion', "view.php?id={$foreact->cmid}", $foreact->id, $foreact->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());
    }

    /**
     * Ensure discussion_moved event validates that fromforeactid is set.
     *
     * @expectedException        coding_exception
     * @expectedExceptionMessage The 'fromforeactid' value must be set in other.
     */
    public function test_discussion_moved_fromforeactid_validation() {
        $course = $this->getDataGenerator()->create_course();
        $toforeact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));

        $context = context_module::instance($toforeact->cmid);

        $params = array(
            'context' => $context,
            'other' => array('toforeactid' => $toforeact->id)
        );

        \mod_foreact\event\discussion_moved::create($params);
    }

    /**
     * Ensure discussion_moved event validates that toforeactid is set.
     *
     * @expectedException        coding_exception
     * @expectedExceptionMessage The 'toforeactid' value must be set in other.
     */
    public function test_discussion_moved_toforeactid_validation() {
        $course = $this->getDataGenerator()->create_course();
        $fromforeact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));
        $toforeact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));
        $context = context_module::instance($toforeact->cmid);

        $params = array(
            'context' => $context,
            'other' => array('fromforeactid' => $fromforeact->id)
        );

        \mod_foreact\event\discussion_moved::create($params);
    }

    /**
     * Ensure discussion_moved event validates that the context level is correct.
     *
     * @expectedException        coding_exception
     * @expectedExceptionMessage Context level must be CONTEXT_MODULE.
     */
    public function test_discussion_moved_context_validation() {
        $course = $this->getDataGenerator()->create_course();
        $fromforeact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));
        $toforeact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['foreact'] = $fromforeact->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_discussion($record);

        $params = array(
            'context' => context_system::instance(),
            'objectid' => $discussion->id,
            'other' => array('fromforeactid' => $fromforeact->id, 'toforeactid' => $toforeact->id)
        );

        \mod_foreact\event\discussion_moved::create($params);
    }

    /**
     * Test discussion_moved event.
     */
    public function test_discussion_moved() {
        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $fromforeact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));
        $toforeact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['foreact'] = $fromforeact->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_discussion($record);

        $context = context_module::instance($toforeact->cmid);

        $params = array(
            'context' => $context,
            'objectid' => $discussion->id,
            'other' => array('fromforeactid' => $fromforeact->id, 'toforeactid' => $toforeact->id)
        );

        $event = \mod_foreact\event\discussion_moved::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_foreact\event\discussion_moved', $event);
        $this->assertEquals($context, $event->get_context());
        $expected = array($course->id, 'foreact', 'move discussion', "discuss.php?d={$discussion->id}",
            $discussion->id, $toforeact->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());
    }


    /**
     * Ensure discussion_viewed event validates that the contextlevel is correct.
     *
     * @expectedException        coding_exception
     * @expectedExceptionMessage Context level must be CONTEXT_MODULE.
     */
    public function test_discussion_viewed_context_validation() {
        $course = $this->getDataGenerator()->create_course();
        $foreact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['foreact'] = $foreact->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_discussion($record);

        $params = array(
            'context' => context_system::instance(),
            'objectid' => $discussion->id,
        );

        \mod_foreact\event\discussion_viewed::create($params);
    }

    /**
     * Test discussion_viewed event.
     */
    public function test_discussion_viewed() {
        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $foreact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['foreact'] = $foreact->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_discussion($record);

        $context = context_module::instance($foreact->cmid);

        $params = array(
            'context' => $context,
            'objectid' => $discussion->id,
        );

        $event = \mod_foreact\event\discussion_viewed::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_foreact\event\discussion_viewed', $event);
        $this->assertEquals($context, $event->get_context());
        $expected = array($course->id, 'foreact', 'view discussion', "discuss.php?d={$discussion->id}",
            $discussion->id, $foreact->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());
    }

    /**
     * Ensure course_module_viewed event validates that the contextlevel is correct.
     *
     * @expectedException        coding_exception
     * @expectedExceptionMessage Context level must be CONTEXT_MODULE.
     */
    public function test_course_module_viewed_context_validation() {
        $course = $this->getDataGenerator()->create_course();
        $foreact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));

        $params = array(
            'context' => context_system::instance(),
            'objectid' => $foreact->id,
        );

        \mod_foreact\event\course_module_viewed::create($params);
    }

    /**
     * Test the course_module_viewed event.
     */
    public function test_course_module_viewed() {
        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $foreact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));

        $context = context_module::instance($foreact->cmid);

        $params = array(
            'context' => $context,
            'objectid' => $foreact->id,
        );

        $event = \mod_foreact\event\course_module_viewed::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_foreact\event\course_module_viewed', $event);
        $this->assertEquals($context, $event->get_context());
        $expected = array($course->id, 'foreact', 'view foreact', "view.php?f={$foreact->id}", $foreact->id, $foreact->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $url = new \moodle_url('/mod/foreact/view.php', array('f' => $foreact->id));
        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());
    }

    /**
     * Ensure subscription_created event validates that the foreactid is set.
     *
     * @expectedException        coding_exception
     * @expectedExceptionMessage The 'foreactid' value must be set in other.
     */
    public function test_subscription_created_foreactid_validation() {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $foreact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));

        $params = array(
            'context' => context_module::instance($foreact->cmid),
            'relateduserid' => $user->id,
        );

        \mod_foreact\event\subscription_created::create($params);
    }

    /**
     * Ensure subscription_created event validates that the relateduserid is set.
     *
     * @expectedException        coding_exception
     * @expectedExceptionMessage The 'relateduserid' must be set.
     */
    public function test_subscription_created_relateduserid_validation() {
        $course = $this->getDataGenerator()->create_course();
        $foreact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));

        $params = array(
            'context' => context_module::instance($foreact->cmid),
            'objectid' => $foreact->id,
        );

        \mod_foreact\event\subscription_created::create($params);
    }

    /**
     * Ensure subscription_created event validates that the contextlevel is correct.
     *
     * @expectedException        coding_exception
     * @expectedExceptionMessage Context level must be CONTEXT_MODULE.
     */
    public function test_subscription_created_contextlevel_validation() {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $foreact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));

        $params = array(
            'context' => context_system::instance(),
            'other' => array('foreactid' => $foreact->id),
            'relateduserid' => $user->id,
        );

        \mod_foreact\event\subscription_created::create($params);
    }

    /**
     * Test the subscription_created event.
     */
    public function test_subscription_created() {
        // Setup test data.
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $foreact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();
        $context = context_module::instance($foreact->cmid);

        // Add a subscription.
        $record = array();
        $record['course'] = $course->id;
        $record['foreact'] = $foreact->id;
        $record['userid'] = $user->id;
        $subscription = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_subscription($record);

        $params = array(
            'context' => $context,
            'objectid' => $subscription->id,
            'other' => array('foreactid' => $foreact->id),
            'relateduserid' => $user->id,
        );

        $event = \mod_foreact\event\subscription_created::create($params);

        // Trigger and capturing the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_foreact\event\subscription_created', $event);
        $this->assertEquals($context, $event->get_context());
        $expected = array($course->id, 'foreact', 'subscribe', "view.php?f={$foreact->id}", $foreact->id, $foreact->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $url = new \moodle_url('/mod/foreact/subscribers.php', array('id' => $foreact->id));
        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());
    }

    /**
     * Ensure subscription_deleted event validates that the foreactid is set.
     *
     * @expectedException        coding_exception
     * @expectedExceptionMessage The 'foreactid' value must be set in other.
     */
    public function test_subscription_deleted_foreactid_validation() {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $foreact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));

        $params = array(
            'context' => context_module::instance($foreact->cmid),
            'relateduserid' => $user->id,
        );

        \mod_foreact\event\subscription_deleted::create($params);
    }

    /**
     * Ensure subscription_deleted event validates that the relateduserid is set.
     *
     * @expectedException        coding_exception
     * @expectedExceptionMessage The 'relateduserid' must be set.
     */
    public function test_subscription_deleted_relateduserid_validation() {
        $course = $this->getDataGenerator()->create_course();
        $foreact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));

        $params = array(
            'context' => context_module::instance($foreact->cmid),
            'objectid' => $foreact->id,
        );

        \mod_foreact\event\subscription_deleted::create($params);
    }

    /**
     * Ensure subscription_deleted event validates that the contextlevel is correct.
     *
     * @expectedException        coding_exception
     * @expectedExceptionMessage Context level must be CONTEXT_MODULE.
     */
    public function test_subscription_deleted_contextlevel_validation() {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $foreact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));

        $params = array(
            'context' => context_system::instance(),
            'other' => array('foreactid' => $foreact->id),
            'relateduserid' => $user->id,
        );

        \mod_foreact\event\subscription_deleted::create($params);
    }

    /**
     * Test the subscription_deleted event.
     */
    public function test_subscription_deleted() {
        // Setup test data.
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $foreact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();
        $context = context_module::instance($foreact->cmid);

        // Add a subscription.
        $record = array();
        $record['course'] = $course->id;
        $record['foreact'] = $foreact->id;
        $record['userid'] = $user->id;
        $subscription = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_subscription($record);

        $params = array(
            'context' => $context,
            'objectid' => $subscription->id,
            'other' => array('foreactid' => $foreact->id),
            'relateduserid' => $user->id,
        );

        $event = \mod_foreact\event\subscription_deleted::create($params);

        // Trigger and capturing the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_foreact\event\subscription_deleted', $event);
        $this->assertEquals($context, $event->get_context());
        $expected = array($course->id, 'foreact', 'unsubscribe', "view.php?f={$foreact->id}", $foreact->id, $foreact->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $url = new \moodle_url('/mod/foreact/subscribers.php', array('id' => $foreact->id));
        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());
    }

    /**
     * Ensure readtracking_enabled event validates that the foreactid is set.
     *
     * @expectedException        coding_exception
     * @expectedExceptionMessage The 'foreactid' value must be set in other.
     */
    public function test_readtracking_enabled_foreactid_validation() {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $foreact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));

        $params = array(
            'context' => context_module::instance($foreact->cmid),
            'relateduserid' => $user->id,
        );

        \mod_foreact\event\readtracking_enabled::create($params);
    }

    /**
     * Ensure readtracking_enabled event validates that the relateduserid is set.
     *
     * @expectedException        coding_exception
     * @expectedExceptionMessage The 'relateduserid' must be set.
     */
    public function test_readtracking_enabled_relateduserid_validation() {
        $course = $this->getDataGenerator()->create_course();
        $foreact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));

        $params = array(
            'context' => context_module::instance($foreact->cmid),
            'objectid' => $foreact->id,
        );

        \mod_foreact\event\readtracking_enabled::create($params);
    }

    /**
     * Ensure readtracking_enabled event validates that the contextlevel is correct.
     *
     * @expectedException        coding_exception
     * @expectedExceptionMessage Context level must be CONTEXT_MODULE.
     */
    public function test_readtracking_enabled_contextlevel_validation() {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $foreact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));

        $params = array(
            'context' => context_system::instance(),
            'other' => array('foreactid' => $foreact->id),
            'relateduserid' => $user->id,
        );

        \mod_foreact\event\readtracking_enabled::create($params);
    }

    /**
     * Test the readtracking_enabled event.
     */
    public function test_readtracking_enabled() {
        // Setup test data.
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $foreact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));
        $context = context_module::instance($foreact->cmid);

        $params = array(
            'context' => $context,
            'other' => array('foreactid' => $foreact->id),
            'relateduserid' => $user->id,
        );

        $event = \mod_foreact\event\readtracking_enabled::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_foreact\event\readtracking_enabled', $event);
        $this->assertEquals($context, $event->get_context());
        $expected = array($course->id, 'foreact', 'start tracking', "view.php?f={$foreact->id}", $foreact->id, $foreact->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $url = new \moodle_url('/mod/foreact/view.php', array('f' => $foreact->id));
        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());
    }

    /**
     *  Ensure readtracking_disabled event validates that the foreactid is set.
     *
     * @expectedException        coding_exception
     * @expectedExceptionMessage The 'foreactid' value must be set in other.
     */
    public function test_readtracking_disabled_foreactid_validation() {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $foreact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));

        $params = array(
            'context' => context_module::instance($foreact->cmid),
            'relateduserid' => $user->id,
        );

        \mod_foreact\event\readtracking_disabled::create($params);
    }

    /**
     *  Ensure readtracking_disabled event validates that the relateduserid is set.
     *
     * @expectedException        coding_exception
     * @expectedExceptionMessage The 'relateduserid' must be set.
     */
    public function test_readtracking_disabled_relateduserid_validation() {
        $course = $this->getDataGenerator()->create_course();
        $foreact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));

        $params = array(
            'context' => context_module::instance($foreact->cmid),
            'objectid' => $foreact->id,
        );

        \mod_foreact\event\readtracking_disabled::create($params);
    }

    /**
     *  Ensure readtracking_disabled event validates that the contextlevel is correct
     *
     * @expectedException        coding_exception
     * @expectedExceptionMessage Context level must be CONTEXT_MODULE.
     */
    public function test_readtracking_disabled_contextlevel_validation() {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $foreact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));

        $params = array(
            'context' => context_system::instance(),
            'other' => array('foreactid' => $foreact->id),
            'relateduserid' => $user->id,
        );

        \mod_foreact\event\readtracking_disabled::create($params);
    }

    /**
     *  Test the readtracking_disabled event.
     */
    public function test_readtracking_disabled() {
        // Setup test data.
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $foreact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));
        $context = context_module::instance($foreact->cmid);

        $params = array(
            'context' => $context,
            'other' => array('foreactid' => $foreact->id),
            'relateduserid' => $user->id,
        );

        $event = \mod_foreact\event\readtracking_disabled::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_foreact\event\readtracking_disabled', $event);
        $this->assertEquals($context, $event->get_context());
        $expected = array($course->id, 'foreact', 'stop tracking', "view.php?f={$foreact->id}", $foreact->id, $foreact->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $url = new \moodle_url('/mod/foreact/view.php', array('f' => $foreact->id));
        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());
    }

    /**
     *  Ensure subscribers_viewed event validates that the foreactid is set.
     *
     * @expectedException        coding_exception
     * @expectedExceptionMessage The 'foreactid' value must be set in other.
     */
    public function test_subscribers_viewed_foreactid_validation() {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $foreact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));

        $params = array(
            'context' => context_module::instance($foreact->cmid),
            'relateduserid' => $user->id,
        );

        \mod_foreact\event\subscribers_viewed::create($params);
    }

    /**
     *  Ensure subscribers_viewed event validates that the contextlevel is correct.
     *
     * @expectedException        coding_exception
     * @expectedExceptionMessage Context level must be CONTEXT_MODULE.
     */
    public function test_subscribers_viewed_contextlevel_validation() {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $foreact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));

        $params = array(
            'context' => context_system::instance(),
            'other' => array('foreactid' => $foreact->id),
            'relateduserid' => $user->id,
        );

        \mod_foreact\event\subscribers_viewed::create($params);
    }

    /**
     *  Test the subscribers_viewed event.
     */
    public function test_subscribers_viewed() {
        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $foreact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));
        $context = context_module::instance($foreact->cmid);

        $params = array(
            'context' => $context,
            'other' => array('foreactid' => $foreact->id),
        );

        $event = \mod_foreact\event\subscribers_viewed::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_foreact\event\subscribers_viewed', $event);
        $this->assertEquals($context, $event->get_context());
        $expected = array($course->id, 'foreact', 'view subscribers', "subscribers.php?id={$foreact->id}", $foreact->id, $foreact->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());
    }

    /**
     * Ensure user_report_viewed event validates that the reportmode is set.
     *
     * @expectedException        coding_exception
     * @expectedExceptionMessage The 'reportmode' value must be set in other.
     */
    public function test_user_report_viewed_reportmode_validation() {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();

        $params = array(
            'context' => context_course::instance($course->id),
            'relateduserid' => $user->id,
        );

        \mod_foreact\event\user_report_viewed::create($params);
    }

    /**
     * Ensure user_report_viewed event validates that the contextlevel is correct.
     *
     * @expectedException        coding_exception
     * @expectedExceptionMessage Context level must be either CONTEXT_SYSTEM, CONTEXT_COURSE or CONTEXT_USER.
     */
    public function test_user_report_viewed_contextlevel_validation() {
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $foreact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));

        $params = array(
            'context' => context_module::instance($foreact->cmid),
            'other' => array('reportmode' => 'posts'),
            'relateduserid' => $user->id,
        );

        \mod_foreact\event\user_report_viewed::create($params);
    }

    /**
     *  Ensure user_report_viewed event validates that the relateduserid is set.
     *
     * @expectedException        coding_exception
     * @expectedExceptionMessage The 'relateduserid' must be set.
     */
    public function test_user_report_viewed_relateduserid_validation() {

        $params = array(
            'context' => context_system::instance(),
            'other' => array('reportmode' => 'posts'),
        );

        \mod_foreact\event\user_report_viewed::create($params);
    }

    /**
     * Test the user_report_viewed event.
     */
    public function test_user_report_viewed() {
        // Setup test data.
        $user = $this->getDataGenerator()->create_user();
        $course = $this->getDataGenerator()->create_course();
        $context = context_course::instance($course->id);

        $params = array(
            'context' => $context,
            'relateduserid' => $user->id,
            'other' => array('reportmode' => 'discussions'),
        );

        $event = \mod_foreact\event\user_report_viewed::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_foreact\event\user_report_viewed', $event);
        $this->assertEquals($context, $event->get_context());
        $expected = array($course->id, 'foreact', 'user report',
            "user.php?id={$user->id}&amp;mode=discussions&amp;course={$course->id}", $user->id);
        $this->assertEventLegacyLogData($expected, $event);
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());
    }

    /**
     *  Ensure post_created event validates that the postid is set.
     */
    public function test_post_created_postid_validation() {
        $course = $this->getDataGenerator()->create_course();
        $foreact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['foreact'] = $foreact->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_discussion($record);

        $params = array(
            'context' => context_module::instance($foreact->cmid),
            'other' => array('foreactid' => $foreact->id, 'foreacttype' => $foreact->type, 'discussionid' => $discussion->id)
        );

        \mod_foreact\event\post_created::create($params);
    }

    /**
     * Ensure post_created event validates that the discussionid is set.
     *
     * @expectedException        coding_exception
     * @expectedExceptionMessage The 'discussionid' value must be set in other.
     */
    public function test_post_created_discussionid_validation() {
        $course = $this->getDataGenerator()->create_course();
        $foreact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['foreact'] = $foreact->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_post($record);

        $params = array(
            'context' => context_module::instance($foreact->cmid),
            'objectid' => $post->id,
            'other' => array('foreactid' => $foreact->id, 'foreacttype' => $foreact->type)
        );

        \mod_foreact\event\post_created::create($params);
    }

    /**
     *  Ensure post_created event validates that the foreactid is set.
     *
     * @expectedException        coding_exception
     * @expectedExceptionMessage The 'foreactid' value must be set in other.
     */
    public function test_post_created_foreactid_validation() {
        $course = $this->getDataGenerator()->create_course();
        $foreact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['foreact'] = $foreact->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_post($record);

        $params = array(
            'context' => context_module::instance($foreact->cmid),
            'objectid' => $post->id,
            'other' => array('discussionid' => $discussion->id, 'foreacttype' => $foreact->type)
        );

        \mod_foreact\event\post_created::create($params);
    }

    /**
     * Ensure post_created event validates that the foreacttype is set.
     *
     * @expectedException        coding_exception
     * @expectedExceptionMessage The 'foreacttype' value must be set in other.
     */
    public function test_post_created_foreacttype_validation() {
        $course = $this->getDataGenerator()->create_course();
        $foreact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['foreact'] = $foreact->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_post($record);

        $params = array(
            'context' => context_module::instance($foreact->cmid),
            'objectid' => $post->id,
            'other' => array('discussionid' => $discussion->id, 'foreactid' => $foreact->id)
        );

        \mod_foreact\event\post_created::create($params);
    }

    /**
     *  Ensure post_created event validates that the contextlevel is correct.
     *
     * @expectedException        coding_exception
     * @expectedExceptionMessage Context level must be CONTEXT_MODULE.
     */
    public function test_post_created_context_validation() {
        $course = $this->getDataGenerator()->create_course();
        $foreact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['foreact'] = $foreact->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_post($record);

        $params = array(
            'context' => context_system::instance(),
            'objectid' => $post->id,
            'other' => array('discussionid' => $discussion->id, 'foreactid' => $foreact->id, 'foreacttype' => $foreact->type)
        );

        \mod_foreact\event\post_created::create($params);
    }

    /**
     * Test the post_created event.
     */
    public function test_post_created() {
        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $foreact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['foreact'] = $foreact->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_post($record);

        $context = context_module::instance($foreact->cmid);

        $params = array(
            'context' => $context,
            'objectid' => $post->id,
            'other' => array('discussionid' => $discussion->id, 'foreactid' => $foreact->id, 'foreacttype' => $foreact->type)
        );

        $event = \mod_foreact\event\post_created::create($params);

        // Trigger and capturing the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_foreact\event\post_created', $event);
        $this->assertEquals($context, $event->get_context());
        $expected = array($course->id, 'foreact', 'add post', "discuss.php?d={$discussion->id}#p{$post->id}",
            $foreact->id, $foreact->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $url = new \moodle_url('/mod/foreact/discuss.php', array('d' => $discussion->id));
        $url->set_anchor('p'.$event->objectid);
        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());
    }

    /**
     * Test the post_created event for a single discussion foreact.
     */
    public function test_post_created_single() {
        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $foreact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id, 'type' => 'single'));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['foreact'] = $foreact->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_post($record);

        $context = context_module::instance($foreact->cmid);

        $params = array(
            'context' => $context,
            'objectid' => $post->id,
            'other' => array('discussionid' => $discussion->id, 'foreactid' => $foreact->id, 'foreacttype' => $foreact->type)
        );

        $event = \mod_foreact\event\post_created::create($params);

        // Trigger and capturing the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_foreact\event\post_created', $event);
        $this->assertEquals($context, $event->get_context());
        $expected = array($course->id, 'foreact', 'add post', "view.php?f={$foreact->id}#p{$post->id}",
            $foreact->id, $foreact->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $url = new \moodle_url('/mod/foreact/view.php', array('f' => $foreact->id));
        $url->set_anchor('p'.$event->objectid);
        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());
    }

    /**
     *  Ensure post_deleted event validates that the postid is set.
     */
    public function test_post_deleted_postid_validation() {
        $course = $this->getDataGenerator()->create_course();
        $foreact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['foreact'] = $foreact->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_discussion($record);

        $params = array(
            'context' => context_module::instance($foreact->cmid),
            'other' => array('foreactid' => $foreact->id, 'foreacttype' => $foreact->type, 'discussionid' => $discussion->id)
        );

        \mod_foreact\event\post_deleted::create($params);
    }

    /**
     * Ensure post_deleted event validates that the discussionid is set.
     *
     * @expectedException        coding_exception
     * @expectedExceptionMessage The 'discussionid' value must be set in other.
     */
    public function test_post_deleted_discussionid_validation() {
        $course = $this->getDataGenerator()->create_course();
        $foreact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['foreact'] = $foreact->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_post($record);

        $params = array(
            'context' => context_module::instance($foreact->cmid),
            'objectid' => $post->id,
            'other' => array('foreactid' => $foreact->id, 'foreacttype' => $foreact->type)
        );

        \mod_foreact\event\post_deleted::create($params);
    }

    /**
     *  Ensure post_deleted event validates that the foreactid is set.
     *
     * @expectedException        coding_exception
     * @expectedExceptionMessage The 'foreactid' value must be set in other.
     */
    public function test_post_deleted_foreactid_validation() {
        $course = $this->getDataGenerator()->create_course();
        $foreact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['foreact'] = $foreact->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_post($record);

        $params = array(
            'context' => context_module::instance($foreact->cmid),
            'objectid' => $post->id,
            'other' => array('discussionid' => $discussion->id, 'foreacttype' => $foreact->type)
        );

        \mod_foreact\event\post_deleted::create($params);
    }

    /**
     * Ensure post_deleted event validates that the foreacttype is set.
     *
     * @expectedException        coding_exception
     * @expectedExceptionMessage The 'foreacttype' value must be set in other.
     */
    public function test_post_deleted_foreacttype_validation() {
        $course = $this->getDataGenerator()->create_course();
        $foreact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['foreact'] = $foreact->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_post($record);

        $params = array(
            'context' => context_module::instance($foreact->cmid),
            'objectid' => $post->id,
            'other' => array('discussionid' => $discussion->id, 'foreactid' => $foreact->id)
        );

        \mod_foreact\event\post_deleted::create($params);
    }

    /**
     *  Ensure post_deleted event validates that the contextlevel is correct.
     *
     * @expectedException        coding_exception
     * @expectedExceptionMessage Context level must be CONTEXT_MODULE.
     */
    public function test_post_deleted_context_validation() {
        $course = $this->getDataGenerator()->create_course();
        $foreact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['foreact'] = $foreact->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_post($record);

        $params = array(
            'context' => context_system::instance(),
            'objectid' => $post->id,
            'other' => array('discussionid' => $discussion->id, 'foreactid' => $foreact->id, 'foreacttype' => $foreact->type)
        );

        \mod_foreact\event\post_deleted::create($params);
    }

    /**
     * Test post_deleted event.
     */
    public function test_post_deleted() {
        global $DB;

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $foreact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();
        $cm = get_coursemodule_from_instance('foreact', $foreact->id, $foreact->course);

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['foreact'] = $foreact->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_discussion($record);

        // When creating a discussion we also create a post, so get the post.
        $discussionpost = $DB->get_records('foreact_posts');
        // Will only be one here.
        $discussionpost = reset($discussionpost);

        // Add a few posts.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $posts = array();
        $posts[$discussionpost->id] = $discussionpost;
        for ($i = 0; $i < 3; $i++) {
            $post = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_post($record);
            $posts[$post->id] = $post;
        }

        // Delete the last post and capture the event.
        $lastpost = end($posts);
        $sink = $this->redirectEvents();
        foreact_delete_post($lastpost, true, $course, $cm, $foreact);
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Check that the events contain the expected values.
        $this->assertInstanceOf('\mod_foreact\event\post_deleted', $event);
        $this->assertEquals(context_module::instance($foreact->cmid), $event->get_context());
        $expected = array($course->id, 'foreact', 'delete post', "discuss.php?d={$discussion->id}", $lastpost->id, $foreact->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $url = new \moodle_url('/mod/foreact/discuss.php', array('d' => $discussion->id));
        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);
        $this->assertNotEmpty($event->get_name());

        // Delete the whole discussion and capture the events.
        $sink = $this->redirectEvents();
        foreact_delete_discussion($discussion, true, $course, $cm, $foreact);
        $events = $sink->get_events();
        // We will have 3 events. One for the discussion (creating a discussion creates a post), and two for the posts.
        $this->assertCount(3, $events);

        // Loop through the events and check they are valid.
        foreach ($events as $event) {
            $post = $posts[$event->objectid];

            // Check that the event contains the expected values.
            $this->assertInstanceOf('\mod_foreact\event\post_deleted', $event);
            $this->assertEquals(context_module::instance($foreact->cmid), $event->get_context());
            $expected = array($course->id, 'foreact', 'delete post', "discuss.php?d={$discussion->id}", $post->id, $foreact->cmid);
            $this->assertEventLegacyLogData($expected, $event);
            $url = new \moodle_url('/mod/foreact/discuss.php', array('d' => $discussion->id));
            $this->assertEquals($url, $event->get_url());
            $this->assertEventContextNotUsed($event);
            $this->assertNotEmpty($event->get_name());
        }
    }

    /**
     * Test post_deleted event for a single discussion foreact.
     */
    public function test_post_deleted_single() {
        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $foreact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id, 'type' => 'single'));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['foreact'] = $foreact->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_post($record);

        $context = context_module::instance($foreact->cmid);

        $params = array(
            'context' => $context,
            'objectid' => $post->id,
            'other' => array('discussionid' => $discussion->id, 'foreactid' => $foreact->id, 'foreacttype' => $foreact->type)
        );

        $event = \mod_foreact\event\post_deleted::create($params);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_foreact\event\post_deleted', $event);
        $this->assertEquals($context, $event->get_context());
        $expected = array($course->id, 'foreact', 'delete post', "view.php?f={$foreact->id}", $post->id, $foreact->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $url = new \moodle_url('/mod/foreact/view.php', array('f' => $foreact->id));
        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());
    }

    /**
     * Ensure post_updated event validates that the discussionid is set.
     *
     * @expectedException        coding_exception
     * @expectedExceptionMessage The 'discussionid' value must be set in other.
     */
    public function test_post_updated_discussionid_validation() {
        $course = $this->getDataGenerator()->create_course();
        $foreact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['foreact'] = $foreact->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_post($record);

        $params = array(
            'context' => context_module::instance($foreact->cmid),
            'objectid' => $post->id,
            'other' => array('foreactid' => $foreact->id, 'foreacttype' => $foreact->type)
        );

        \mod_foreact\event\post_updated::create($params);
    }

    /**
     * Ensure post_updated event validates that the foreactid is set.
     *
     * @expectedException        coding_exception
     * @expectedExceptionMessage The 'foreactid' value must be set in other.
     */
    public function test_post_updated_foreactid_validation() {
        $course = $this->getDataGenerator()->create_course();
        $foreact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['foreact'] = $foreact->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_post($record);

        $params = array(
            'context' => context_module::instance($foreact->cmid),
            'objectid' => $post->id,
            'other' => array('discussionid' => $discussion->id, 'foreacttype' => $foreact->type)
        );

        \mod_foreact\event\post_updated::create($params);
    }

    /**
     * Ensure post_updated event validates that the foreacttype is set.
     *
     * @expectedException        coding_exception
     * @expectedExceptionMessage The 'foreacttype' value must be set in other.
     */
    public function test_post_updated_foreacttype_validation() {
        $course = $this->getDataGenerator()->create_course();
        $foreact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['foreact'] = $foreact->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_post($record);

        $params = array(
            'context' => context_module::instance($foreact->cmid),
            'objectid' => $post->id,
            'other' => array('discussionid' => $discussion->id, 'foreactid' => $foreact->id)
        );

        \mod_foreact\event\post_updated::create($params);
    }

    /**
     *  Ensure post_updated event validates that the contextlevel is correct.
     *
     * @expectedException        coding_exception
     * @expectedExceptionMessage Context level must be CONTEXT_MODULE.
     */
    public function test_post_updated_context_validation() {
        $course = $this->getDataGenerator()->create_course();
        $foreact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['foreact'] = $foreact->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_post($record);

        $params = array(
            'context' => context_system::instance(),
            'objectid' => $post->id,
            'other' => array('discussionid' => $discussion->id, 'foreactid' => $foreact->id, 'foreacttype' => $foreact->type)
        );

        \mod_foreact\event\post_updated::create($params);
    }

    /**
     * Test post_updated event.
     */
    public function test_post_updated() {
        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $foreact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['foreact'] = $foreact->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_post($record);

        $context = context_module::instance($foreact->cmid);

        $params = array(
            'context' => $context,
            'objectid' => $post->id,
            'other' => array('discussionid' => $discussion->id, 'foreactid' => $foreact->id, 'foreacttype' => $foreact->type)
        );

        $event = \mod_foreact\event\post_updated::create($params);

        // Trigger and capturing the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_foreact\event\post_updated', $event);
        $this->assertEquals($context, $event->get_context());
        $expected = array($course->id, 'foreact', 'update post', "discuss.php?d={$discussion->id}#p{$post->id}",
            $post->id, $foreact->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $url = new \moodle_url('/mod/foreact/discuss.php', array('d' => $discussion->id));
        $url->set_anchor('p'.$event->objectid);
        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());
    }

    /**
     * Test post_updated event.
     */
    public function test_post_updated_single() {
        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $foreact = $this->getDataGenerator()->create_module('foreact', array('course' => $course->id, 'type' => 'single'));
        $user = $this->getDataGenerator()->create_user();

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['foreact'] = $foreact->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_post($record);

        $context = context_module::instance($foreact->cmid);

        $params = array(
            'context' => $context,
            'objectid' => $post->id,
            'other' => array('discussionid' => $discussion->id, 'foreactid' => $foreact->id, 'foreacttype' => $foreact->type)
        );

        $event = \mod_foreact\event\post_updated::create($params);

        // Trigger and capturing the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_foreact\event\post_updated', $event);
        $this->assertEquals($context, $event->get_context());
        $expected = array($course->id, 'foreact', 'update post', "view.php?f={$foreact->id}#p{$post->id}",
            $post->id, $foreact->cmid);
        $this->assertEventLegacyLogData($expected, $event);
        $url = new \moodle_url('/mod/foreact/view.php', array('f' => $foreact->id));
        $url->set_anchor('p'.$post->id);
        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);

        $this->assertNotEmpty($event->get_name());
    }

    /**
     * Test discussion_subscription_created event.
     */
    public function test_discussion_subscription_created() {
        global $CFG;
        require_once($CFG->dirroot . '/mod/foreact/lib.php');

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $options = array('course' => $course->id, 'forcesubscribe' => foreact_CHOOSESUBSCRIBE);
        $foreact = $this->getDataGenerator()->create_module('foreact', $options);

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['foreact'] = $foreact->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_post($record);

        // Trigger and capturing the event.
        $sink = $this->redirectEvents();

        // Trigger the event by subscribing the user to the foreact discussion.
        \mod_foreact\subscriptions::subscribe_user_to_discussion($user->id, $discussion);

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_foreact\event\discussion_subscription_created', $event);


        $cm = get_coursemodule_from_instance('foreact', $discussion->foreact);
        $context = \context_module::instance($cm->id);
        $this->assertEquals($context, $event->get_context());

        $url = new \moodle_url('/mod/foreact/subscribe.php', array(
            'id' => $foreact->id,
            'd' => $discussion->id
        ));

        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);
        $this->assertNotEmpty($event->get_name());
    }

    /**
     * Test validation of discussion_subscription_created event.
     */
    public function test_discussion_subscription_created_validation() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/foreact/lib.php');

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $options = array('course' => $course->id, 'forcesubscribe' => foreact_CHOOSESUBSCRIBE);
        $foreact = $this->getDataGenerator()->create_module('foreact', $options);

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['foreact'] = $foreact->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_post($record);

        // The user is not subscribed to the foreact. Insert a new discussion subscription.
        $subscription = new \stdClass();
        $subscription->userid  = $user->id;
        $subscription->foreact = $foreact->id;
        $subscription->discussion = $discussion->id;
        $subscription->preference = time();

        $subscription->id = $DB->insert_record('foreact_discussion_subs', $subscription);

        $context = context_module::instance($foreact->cmid);

        $params = array(
            'context' => $context,
            'objectid' => $subscription->id,
            'relateduserid' => $user->id,
            'other' => array(
                'foreactid' => $foreact->id,
                'discussion' => $discussion->id,
            )
        );

        $event = \mod_foreact\event\discussion_subscription_created::create($params);

        // Trigger and capturing the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);
    }

    /**
     * Test contextlevel validation of discussion_subscription_created event.
     *
     * @expectedException        coding_exception
     * @expectedExceptionMessage Context level must be CONTEXT_MODULE.
     */
    public function test_discussion_subscription_created_validation_contextlevel() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/foreact/lib.php');

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $options = array('course' => $course->id, 'forcesubscribe' => foreact_CHOOSESUBSCRIBE);
        $foreact = $this->getDataGenerator()->create_module('foreact', $options);

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['foreact'] = $foreact->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_post($record);

        // The user is not subscribed to the foreact. Insert a new discussion subscription.
        $subscription = new \stdClass();
        $subscription->userid  = $user->id;
        $subscription->foreact = $foreact->id;
        $subscription->discussion = $discussion->id;
        $subscription->preference = time();

        $subscription->id = $DB->insert_record('foreact_discussion_subs', $subscription);

        $context = context_module::instance($foreact->cmid);

        $params = array(
            'context' => \context_course::instance($course->id),
            'objectid' => $subscription->id,
            'relateduserid' => $user->id,
            'other' => array(
                'foreactid' => $foreact->id,
                'discussion' => $discussion->id,
            )
        );

        // Without an invalid context.
        \mod_foreact\event\discussion_subscription_created::create($params);
    }

    /**
     * Test discussion validation of discussion_subscription_created event.
     *
     * @expectedException        coding_exception
     * @expectedExceptionMessage The 'discussion' value must be set in other.
     */
    public function test_discussion_subscription_created_validation_discussion() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/foreact/lib.php');

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $options = array('course' => $course->id, 'forcesubscribe' => foreact_CHOOSESUBSCRIBE);
        $foreact = $this->getDataGenerator()->create_module('foreact', $options);

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['foreact'] = $foreact->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_post($record);

        // The user is not subscribed to the foreact. Insert a new discussion subscription.
        $subscription = new \stdClass();
        $subscription->userid  = $user->id;
        $subscription->foreact = $foreact->id;
        $subscription->discussion = $discussion->id;
        $subscription->preference = time();

        $subscription->id = $DB->insert_record('foreact_discussion_subs', $subscription);

        // Without the discussion.
        $params = array(
            'context' => context_module::instance($foreact->cmid),
            'objectid' => $subscription->id,
            'relateduserid' => $user->id,
            'other' => array(
                'foreactid' => $foreact->id,
            )
        );

        \mod_foreact\event\discussion_subscription_created::create($params);
    }

    /**
     * Test foreactid validation of discussion_subscription_created event.
     *
     * @expectedException        coding_exception
     * @expectedExceptionMessage The 'foreactid' value must be set in other.
     */
    public function test_discussion_subscription_created_validation_foreactid() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/foreact/lib.php');

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $options = array('course' => $course->id, 'forcesubscribe' => foreact_CHOOSESUBSCRIBE);
        $foreact = $this->getDataGenerator()->create_module('foreact', $options);

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['foreact'] = $foreact->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_post($record);

        // The user is not subscribed to the foreact. Insert a new discussion subscription.
        $subscription = new \stdClass();
        $subscription->userid  = $user->id;
        $subscription->foreact = $foreact->id;
        $subscription->discussion = $discussion->id;
        $subscription->preference = time();

        $subscription->id = $DB->insert_record('foreact_discussion_subs', $subscription);

        // Without the foreactid.
        $params = array(
            'context' => context_module::instance($foreact->cmid),
            'objectid' => $subscription->id,
            'relateduserid' => $user->id,
            'other' => array(
                'discussion' => $discussion->id,
            )
        );

        \mod_foreact\event\discussion_subscription_created::create($params);
    }

    /**
     * Test relateduserid validation of discussion_subscription_created event.
     *
     * @expectedException        coding_exception
     * @expectedExceptionMessage The 'relateduserid' must be set.
     */
    public function test_discussion_subscription_created_validation_relateduserid() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/foreact/lib.php');

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $options = array('course' => $course->id, 'forcesubscribe' => foreact_CHOOSESUBSCRIBE);
        $foreact = $this->getDataGenerator()->create_module('foreact', $options);

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['foreact'] = $foreact->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_post($record);

        // The user is not subscribed to the foreact. Insert a new discussion subscription.
        $subscription = new \stdClass();
        $subscription->userid  = $user->id;
        $subscription->foreact = $foreact->id;
        $subscription->discussion = $discussion->id;
        $subscription->preference = time();

        $subscription->id = $DB->insert_record('foreact_discussion_subs', $subscription);

        $context = context_module::instance($foreact->cmid);

        // Without the relateduserid.
        $params = array(
            'context' => context_module::instance($foreact->cmid),
            'objectid' => $subscription->id,
            'other' => array(
                'foreactid' => $foreact->id,
                'discussion' => $discussion->id,
            )
        );

        \mod_foreact\event\discussion_subscription_created::create($params);
    }

    /**
     * Test discussion_subscription_deleted event.
     */
    public function test_discussion_subscription_deleted() {
        global $CFG;
        require_once($CFG->dirroot . '/mod/foreact/lib.php');

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $options = array('course' => $course->id, 'forcesubscribe' => foreact_INITIALSUBSCRIBE);
        $foreact = $this->getDataGenerator()->create_module('foreact', $options);

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['foreact'] = $foreact->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_post($record);

        // Trigger and capturing the event.
        $sink = $this->redirectEvents();

        // Trigger the event by unsubscribing the user to the foreact discussion.
        \mod_foreact\subscriptions::unsubscribe_user_from_discussion($user->id, $discussion);

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_foreact\event\discussion_subscription_deleted', $event);


        $cm = get_coursemodule_from_instance('foreact', $discussion->foreact);
        $context = \context_module::instance($cm->id);
        $this->assertEquals($context, $event->get_context());

        $url = new \moodle_url('/mod/foreact/subscribe.php', array(
            'id' => $foreact->id,
            'd' => $discussion->id
        ));

        $this->assertEquals($url, $event->get_url());
        $this->assertEventContextNotUsed($event);
        $this->assertNotEmpty($event->get_name());
    }

    /**
     * Test validation of discussion_subscription_deleted event.
     */
    public function test_discussion_subscription_deleted_validation() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/foreact/lib.php');

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $options = array('course' => $course->id, 'forcesubscribe' => foreact_INITIALSUBSCRIBE);
        $foreact = $this->getDataGenerator()->create_module('foreact', $options);

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['foreact'] = $foreact->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_post($record);

        // The user is not subscribed to the foreact. Insert a new discussion subscription.
        $subscription = new \stdClass();
        $subscription->userid  = $user->id;
        $subscription->foreact = $foreact->id;
        $subscription->discussion = $discussion->id;
        $subscription->preference = \mod_foreact\subscriptions::foreact_DISCUSSION_UNSUBSCRIBED;

        $subscription->id = $DB->insert_record('foreact_discussion_subs', $subscription);

        $context = context_module::instance($foreact->cmid);

        $params = array(
            'context' => $context,
            'objectid' => $subscription->id,
            'relateduserid' => $user->id,
            'other' => array(
                'foreactid' => $foreact->id,
                'discussion' => $discussion->id,
            )
        );

        $event = \mod_foreact\event\discussion_subscription_deleted::create($params);

        // Trigger and capturing the event.
        $sink = $this->redirectEvents();
        $event->trigger();
        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Without an invalid context.
        $params['context'] = \context_course::instance($course->id);
        $this->expectException('coding_exception');
        $this->expectExceptionMessage('Context level must be CONTEXT_MODULE.');
        \mod_foreact\event\discussion_deleted::create($params);

        // Without the discussion.
        unset($params['discussion']);
        $this->expectException('coding_exception');
        $this->expectExceptionMessage('The \'discussion\' value must be set in other.');
        \mod_foreact\event\discussion_deleted::create($params);

        // Without the foreactid.
        unset($params['foreactid']);
        $this->expectException('coding_exception');
        $this->expectExceptionMessage('The \'foreactid\' value must be set in other.');
        \mod_foreact\event\discussion_deleted::create($params);

        // Without the relateduserid.
        unset($params['relateduserid']);
        $this->expectException('coding_exception');
        $this->expectExceptionMessage('The \'relateduserid\' value must be set in other.');
        \mod_foreact\event\discussion_deleted::create($params);
    }

    /**
     * Test contextlevel validation of discussion_subscription_deleted event.
     *
     * @expectedException        coding_exception
     * @expectedExceptionMessage Context level must be CONTEXT_MODULE.
     */
    public function test_discussion_subscription_deleted_validation_contextlevel() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/foreact/lib.php');

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $options = array('course' => $course->id, 'forcesubscribe' => foreact_CHOOSESUBSCRIBE);
        $foreact = $this->getDataGenerator()->create_module('foreact', $options);

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['foreact'] = $foreact->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_post($record);

        // The user is not subscribed to the foreact. Insert a new discussion subscription.
        $subscription = new \stdClass();
        $subscription->userid  = $user->id;
        $subscription->foreact = $foreact->id;
        $subscription->discussion = $discussion->id;
        $subscription->preference = time();

        $subscription->id = $DB->insert_record('foreact_discussion_subs', $subscription);

        $context = context_module::instance($foreact->cmid);

        $params = array(
            'context' => \context_course::instance($course->id),
            'objectid' => $subscription->id,
            'relateduserid' => $user->id,
            'other' => array(
                'foreactid' => $foreact->id,
                'discussion' => $discussion->id,
            )
        );

        // Without an invalid context.
        \mod_foreact\event\discussion_subscription_deleted::create($params);
    }

    /**
     * Test discussion validation of discussion_subscription_deleted event.
     *
     * @expectedException        coding_exception
     * @expectedExceptionMessage The 'discussion' value must be set in other.
     */
    public function test_discussion_subscription_deleted_validation_discussion() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/foreact/lib.php');

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $options = array('course' => $course->id, 'forcesubscribe' => foreact_CHOOSESUBSCRIBE);
        $foreact = $this->getDataGenerator()->create_module('foreact', $options);

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['foreact'] = $foreact->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_post($record);

        // The user is not subscribed to the foreact. Insert a new discussion subscription.
        $subscription = new \stdClass();
        $subscription->userid  = $user->id;
        $subscription->foreact = $foreact->id;
        $subscription->discussion = $discussion->id;
        $subscription->preference = time();

        $subscription->id = $DB->insert_record('foreact_discussion_subs', $subscription);

        // Without the discussion.
        $params = array(
            'context' => context_module::instance($foreact->cmid),
            'objectid' => $subscription->id,
            'relateduserid' => $user->id,
            'other' => array(
                'foreactid' => $foreact->id,
            )
        );

        \mod_foreact\event\discussion_subscription_deleted::create($params);
    }

    /**
     * Test foreactid validation of discussion_subscription_deleted event.
     *
     * @expectedException        coding_exception
     * @expectedExceptionMessage The 'foreactid' value must be set in other.
     */
    public function test_discussion_subscription_deleted_validation_foreactid() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/foreact/lib.php');

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $options = array('course' => $course->id, 'forcesubscribe' => foreact_CHOOSESUBSCRIBE);
        $foreact = $this->getDataGenerator()->create_module('foreact', $options);

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['foreact'] = $foreact->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_post($record);

        // The user is not subscribed to the foreact. Insert a new discussion subscription.
        $subscription = new \stdClass();
        $subscription->userid  = $user->id;
        $subscription->foreact = $foreact->id;
        $subscription->discussion = $discussion->id;
        $subscription->preference = time();

        $subscription->id = $DB->insert_record('foreact_discussion_subs', $subscription);

        // Without the foreactid.
        $params = array(
            'context' => context_module::instance($foreact->cmid),
            'objectid' => $subscription->id,
            'relateduserid' => $user->id,
            'other' => array(
                'discussion' => $discussion->id,
            )
        );

        \mod_foreact\event\discussion_subscription_deleted::create($params);
    }

    /**
     * Test relateduserid validation of discussion_subscription_deleted event.
     *
     * @expectedException        coding_exception
     * @expectedExceptionMessage The 'relateduserid' must be set.
     */
    public function test_discussion_subscription_deleted_validation_relateduserid() {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/foreact/lib.php');

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $options = array('course' => $course->id, 'forcesubscribe' => foreact_CHOOSESUBSCRIBE);
        $foreact = $this->getDataGenerator()->create_module('foreact', $options);

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['foreact'] = $foreact->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_post($record);

        // The user is not subscribed to the foreact. Insert a new discussion subscription.
        $subscription = new \stdClass();
        $subscription->userid  = $user->id;
        $subscription->foreact = $foreact->id;
        $subscription->discussion = $discussion->id;
        $subscription->preference = time();

        $subscription->id = $DB->insert_record('foreact_discussion_subs', $subscription);

        $context = context_module::instance($foreact->cmid);

        // Without the relateduserid.
        $params = array(
            'context' => context_module::instance($foreact->cmid),
            'objectid' => $subscription->id,
            'other' => array(
                'foreactid' => $foreact->id,
                'discussion' => $discussion->id,
            )
        );

        \mod_foreact\event\discussion_subscription_deleted::create($params);
    }

    /**
     * Test that the correct context is used in the events when subscribing
     * users.
     */
    public function test_foreact_subscription_page_context_valid() {
        global $CFG, $PAGE;
        require_once($CFG->dirroot . '/mod/foreact/lib.php');

        // Setup test data.
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($user->id, $course->id);

        $options = array('course' => $course->id, 'forcesubscribe' => foreact_CHOOSESUBSCRIBE);
        $foreact = $this->getDataGenerator()->create_module('foreact', $options);
        $quiz = $this->getDataGenerator()->create_module('quiz', $options);

        // Add a discussion.
        $record = array();
        $record['course'] = $course->id;
        $record['foreact'] = $foreact->id;
        $record['userid'] = $user->id;
        $discussion = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_discussion($record);

        // Add a post.
        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $this->getDataGenerator()->get_plugin_generator('mod_foreact')->create_post($record);

        // Set up the default page event to use this foreact.
        $PAGE = new moodle_page();
        $cm = get_coursemodule_from_instance('foreact', $discussion->foreact);
        $context = \context_module::instance($cm->id);
        $PAGE->set_context($context);
        $PAGE->set_cm($cm, $course, $foreact);

        // Trigger and capturing the event.
        $sink = $this->redirectEvents();

        // Trigger the event by subscribing the user to the foreact.
        \mod_foreact\subscriptions::subscribe_user($user->id, $foreact);

        $events = $sink->get_events();
        $sink->clear();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_foreact\event\subscription_created', $event);
        $this->assertEquals($context, $event->get_context());

        // Trigger the event by unsubscribing the user to the foreact.
        \mod_foreact\subscriptions::unsubscribe_user($user->id, $foreact);

        $events = $sink->get_events();
        $sink->clear();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_foreact\event\subscription_deleted', $event);
        $this->assertEquals($context, $event->get_context());

        // Trigger the event by subscribing the user to the discussion.
        \mod_foreact\subscriptions::subscribe_user_to_discussion($user->id, $discussion);

        $events = $sink->get_events();
        $sink->clear();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_foreact\event\discussion_subscription_created', $event);
        $this->assertEquals($context, $event->get_context());

        // Trigger the event by unsubscribing the user from the discussion.
        \mod_foreact\subscriptions::unsubscribe_user_from_discussion($user->id, $discussion);

        $events = $sink->get_events();
        $sink->clear();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_foreact\event\discussion_subscription_deleted', $event);
        $this->assertEquals($context, $event->get_context());

        // Now try with the context for a different module (quiz).
        $PAGE = new moodle_page();
        $cm = get_coursemodule_from_instance('quiz', $quiz->id);
        $quizcontext = \context_module::instance($cm->id);
        $PAGE->set_context($quizcontext);
        $PAGE->set_cm($cm, $course, $quiz);

        // Trigger and capturing the event.
        $sink = $this->redirectEvents();

        // Trigger the event by subscribing the user to the foreact.
        \mod_foreact\subscriptions::subscribe_user($user->id, $foreact);

        $events = $sink->get_events();
        $sink->clear();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_foreact\event\subscription_created', $event);
        $this->assertEquals($context, $event->get_context());

        // Trigger the event by unsubscribing the user to the foreact.
        \mod_foreact\subscriptions::unsubscribe_user($user->id, $foreact);

        $events = $sink->get_events();
        $sink->clear();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_foreact\event\subscription_deleted', $event);
        $this->assertEquals($context, $event->get_context());

        // Trigger the event by subscribing the user to the discussion.
        \mod_foreact\subscriptions::subscribe_user_to_discussion($user->id, $discussion);

        $events = $sink->get_events();
        $sink->clear();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_foreact\event\discussion_subscription_created', $event);
        $this->assertEquals($context, $event->get_context());

        // Trigger the event by unsubscribing the user from the discussion.
        \mod_foreact\subscriptions::unsubscribe_user_from_discussion($user->id, $discussion);

        $events = $sink->get_events();
        $sink->clear();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_foreact\event\discussion_subscription_deleted', $event);
        $this->assertEquals($context, $event->get_context());

        // Now try with the course context - the module context should still be used.
        $PAGE = new moodle_page();
        $coursecontext = \context_course::instance($course->id);
        $PAGE->set_context($coursecontext);

        // Trigger the event by subscribing the user to the foreact.
        \mod_foreact\subscriptions::subscribe_user($user->id, $foreact);

        $events = $sink->get_events();
        $sink->clear();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_foreact\event\subscription_created', $event);
        $this->assertEquals($context, $event->get_context());

        // Trigger the event by unsubscribing the user to the foreact.
        \mod_foreact\subscriptions::unsubscribe_user($user->id, $foreact);

        $events = $sink->get_events();
        $sink->clear();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_foreact\event\subscription_deleted', $event);
        $this->assertEquals($context, $event->get_context());

        // Trigger the event by subscribing the user to the discussion.
        \mod_foreact\subscriptions::subscribe_user_to_discussion($user->id, $discussion);

        $events = $sink->get_events();
        $sink->clear();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_foreact\event\discussion_subscription_created', $event);
        $this->assertEquals($context, $event->get_context());

        // Trigger the event by unsubscribing the user from the discussion.
        \mod_foreact\subscriptions::unsubscribe_user_from_discussion($user->id, $discussion);

        $events = $sink->get_events();
        $sink->clear();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\mod_foreact\event\discussion_subscription_deleted', $event);
        $this->assertEquals($context, $event->get_context());

    }

    /**
     * Test mod_foreact_observer methods.
     */
    public function test_observers() {
        global $DB, $CFG;

        require_once($CFG->dirroot . '/mod/foreact/lib.php');

        $foreactgen = $this->getDataGenerator()->get_plugin_generator('mod_foreact');

        $course = $this->getDataGenerator()->create_course();
        $trackedrecord = array('course' => $course->id, 'type' => 'general', 'forcesubscribe' => foreact_INITIALSUBSCRIBE);
        $untrackedrecord = array('course' => $course->id, 'type' => 'general');
        $trackedforeact = $this->getDataGenerator()->create_module('foreact', $trackedrecord);
        $untrackedforeact = $this->getDataGenerator()->create_module('foreact', $untrackedrecord);

        // Used functions don't require these settings; adding
        // them just in case there are APIs changes in future.
        $user = $this->getDataGenerator()->create_user(array(
            'maildigest' => 1,
            'trackforeacts' => 1
        ));

        $manplugin = enrol_get_plugin('manual');
        $manualenrol = $DB->get_record('enrol', array('courseid' => $course->id, 'enrol' => 'manual'));
        $student = $DB->get_record('role', array('shortname' => 'student'));

        // The role_assign observer does it's job adding the foreact_subscriptions record.
        $manplugin->enrol_user($manualenrol, $user->id, $student->id);

        // They are not required, but in a real environment they are supposed to be required;
        // adding them just in case there are APIs changes in future.
        set_config('foreact_trackingtype', 1);
        set_config('foreact_trackreadposts', 1);

        $record = array();
        $record['course'] = $course->id;
        $record['foreact'] = $trackedforeact->id;
        $record['userid'] = $user->id;
        $discussion = $foreactgen->create_discussion($record);

        $record = array();
        $record['discussion'] = $discussion->id;
        $record['userid'] = $user->id;
        $post = $foreactgen->create_post($record);

        foreact_tp_add_read_record($user->id, $post->id);
        foreact_set_user_maildigest($trackedforeact, 2, $user);
        foreact_tp_stop_tracking($untrackedforeact->id, $user->id);

        $this->assertEquals(1, $DB->count_records('foreact_subscriptions'));
        $this->assertEquals(1, $DB->count_records('foreact_digests'));
        $this->assertEquals(1, $DB->count_records('foreact_track_prefs'));
        $this->assertEquals(1, $DB->count_records('foreact_read'));

        // The course_module_created observer does it's job adding a subscription.
        $foreactrecord = array('course' => $course->id, 'type' => 'general', 'forcesubscribe' => foreact_INITIALSUBSCRIBE);
        $extraforeact = $this->getDataGenerator()->create_module('foreact', $foreactrecord);
        $this->assertEquals(2, $DB->count_records('foreact_subscriptions'));

        $manplugin->unenrol_user($manualenrol, $user->id);

        $this->assertEquals(0, $DB->count_records('foreact_digests'));
        $this->assertEquals(0, $DB->count_records('foreact_subscriptions'));
        $this->assertEquals(0, $DB->count_records('foreact_track_prefs'));
        $this->assertEquals(0, $DB->count_records('foreact_read'));
    }

}
