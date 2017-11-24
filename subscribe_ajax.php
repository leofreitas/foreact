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
 * Subscribe to or unsubscribe from a foreact discussion.
 *
 * @package    mod_foreact
 * @copyright  2014 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require(__DIR__.'/../../config.php');
require_once($CFG->dirroot . '/mod/foreact/lib.php');

$foreactid        = required_param('foreactid', PARAM_INT);             // The foreact to subscribe or unsubscribe.
$discussionid   = optional_param('discussionid', null, PARAM_INT);  // The discussionid to subscribe.
$includetext    = optional_param('includetext', false, PARAM_BOOL);

$foreact          = $DB->get_record('foreact', array('id' => $foreactid), '*', MUST_EXIST);
$course         = $DB->get_record('course', array('id' => $foreact->course), '*', MUST_EXIST);
if (!$discussion = $DB->get_record('foreact_discussions', array('id' => $discussionid, 'foreact' => $foreactid))) {
    print_error('invaliddiscussionid', 'foreact');
}
$cm             = get_coursemodule_from_instance('foreact', $foreact->id, $course->id, false, MUST_EXIST);
$context        = context_module::instance($cm->id);

require_sesskey();
require_login($course, false, $cm);
require_capability('mod/foreact:viewdiscussion', $context);

$return = new stdClass();

if (is_guest($context, $USER)) {
    // is_guest should be used here as this also checks whether the user is a guest in the current course.
    // Guests and visitors cannot subscribe - only enrolled users.
    throw new moodle_exception('noguestsubscribe', 'mod_foreact');
}

if (!\mod_foreact\subscriptions::is_subscribable($foreact)) {
    // Nothing to do. We won't actually output any content here though.
    echo json_encode($return);
    die;
}

if (\mod_foreact\subscriptions::is_subscribed($USER->id, $foreact, $discussion->id, $cm)) {
    // The user is subscribed, unsubscribe them.
    \mod_foreact\subscriptions::unsubscribe_user_from_discussion($USER->id, $discussion, $context);
} else {
    // The user is unsubscribed, subscribe them.
    \mod_foreact\subscriptions::subscribe_user_to_discussion($USER->id, $discussion, $context);
}

// Now return the updated subscription icon.
$return->icon = foreact_get_discussion_subscription_icon($foreact, $discussion->id, null, $includetext);
echo json_encode($return);
die;
