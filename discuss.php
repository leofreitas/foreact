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
 * Displays a post, and all the posts below it.
 * If no post is given, displays all posts in a discussion
 *
 * @package   mod_foreact
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$d      = required_param('d', PARAM_INT);                // Discussion ID
$parent = optional_param('parent', 0, PARAM_INT);        // If set, then display this post and all children.
$mode   = optional_param('mode', 0, PARAM_INT);          // If set, changes the layout of the thread
$move   = optional_param('move', 0, PARAM_INT);          // If set, moves this discussion to another foreact
$mark   = optional_param('mark', '', PARAM_ALPHA);       // Used for tracking read posts if user initiated.
$postid = optional_param('postid', 0, PARAM_INT);        // Used for tracking read posts if user initiated.
$pin    = optional_param('pin', -1, PARAM_INT);          // If set, pin or unpin this discussion.

$url = new moodle_url('/mod/foreact/discuss.php', array('d'=>$d));
if ($parent !== 0) {
    $url->param('parent', $parent);
}
$PAGE->set_url($url);

$discussion = $DB->get_record('foreact_discussions', array('id' => $d), '*', MUST_EXIST);
$course = $DB->get_record('course', array('id' => $discussion->course), '*', MUST_EXIST);
$foreact = $DB->get_record('foreact', array('id' => $discussion->foreact), '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('foreact', $foreact->id, $course->id, false, MUST_EXIST);

require_course_login($course, true, $cm);

// move this down fix for MDL-6926
require_once($CFG->dirroot.'/mod/foreact/lib.php');

$modcontext = context_module::instance($cm->id);
require_capability('mod/foreact:viewdiscussion', $modcontext, NULL, true, 'noviewdiscussionspermission', 'foreact');

if (!empty($CFG->enablerssfeeds) && !empty($CFG->foreact_enablerssfeeds) && $foreact->rsstype && $foreact->rssarticles) {
    require_once("$CFG->libdir/rsslib.php");

    $rsstitle = format_string($course->shortname, true, array('context' => context_course::instance($course->id))) . ': ' . format_string($foreact->name);
    rss_add_http_header($modcontext, 'mod_foreact', $foreact, $rsstitle);
}

// Move discussion if requested.
if ($move > 0 and confirm_sesskey()) {
    $return = $CFG->wwwroot.'/mod/foreact/discuss.php?d='.$discussion->id;

    if (!$foreactto = $DB->get_record('foreact', array('id' => $move))) {
        print_error('cannotmovetonotexist', 'foreact', $return);
    }

    require_capability('mod/foreact:movediscussions', $modcontext);

    if ($foreact->type == 'single') {
        print_error('cannotmovefromsingleforeact', 'foreact', $return);
    }

    if (!$foreactto = $DB->get_record('foreact', array('id' => $move))) {
        print_error('cannotmovetonotexist', 'foreact', $return);
    }

    if ($foreactto->type == 'single') {
        print_error('cannotmovetosingleforeact', 'foreact', $return);
    }

    // Get target foreact cm and check it is visible to current user.
    $modinfo = get_fast_modinfo($course);
    $foreacts = $modinfo->get_instances_of('foreact');
    if (!array_key_exists($foreactto->id, $foreacts)) {
        print_error('cannotmovetonotfound', 'foreact', $return);
    }
    $cmto = $foreacts[$foreactto->id];
    if (!$cmto->uservisible) {
        print_error('cannotmovenotvisible', 'foreact', $return);
    }

    $destinationctx = context_module::instance($cmto->id);
    require_capability('mod/foreact:startdiscussion', $destinationctx);

    if (!foreact_move_attachments($discussion, $foreact->id, $foreactto->id)) {
        echo $OUTPUT->notification("Errors occurred while moving attachment directories - check your file permissions");
    }
    // For each subscribed user in this foreact and discussion, copy over per-discussion subscriptions if required.
    $discussiongroup = $discussion->groupid == -1 ? 0 : $discussion->groupid;
    $potentialsubscribers = \mod_foreact\subscriptions::fetch_subscribed_users(
        $foreact,
        $discussiongroup,
        $modcontext,
        'u.id',
        true
    );

    // Pre-seed the subscribed_discussion caches.
    // Firstly for the foreact being moved to.
    \mod_foreact\subscriptions::fill_subscription_cache($foreactto->id);
    // And also for the discussion being moved.
    \mod_foreact\subscriptions::fill_subscription_cache($foreact->id);
    $subscriptionchanges = array();
    $subscriptiontime = time();
    foreach ($potentialsubscribers as $subuser) {
        $userid = $subuser->id;
        $targetsubscription = \mod_foreact\subscriptions::is_subscribed($userid, $foreactto, null, $cmto);
        $discussionsubscribed = \mod_foreact\subscriptions::is_subscribed($userid, $foreact, $discussion->id);
        $foreactsubscribed = \mod_foreact\subscriptions::is_subscribed($userid, $foreact);

        if ($foreactsubscribed && !$discussionsubscribed && $targetsubscription) {
            // The user has opted out of this discussion and the move would cause them to receive notifications again.
            // Ensure they are unsubscribed from the discussion still.
            $subscriptionchanges[$userid] = \mod_foreact\subscriptions::foreact_DISCUSSION_UNSUBSCRIBED;
        } else if (!$foreactsubscribed && $discussionsubscribed && !$targetsubscription) {
            // The user has opted into this discussion and would otherwise not receive the subscription after the move.
            // Ensure they are subscribed to the discussion still.
            $subscriptionchanges[$userid] = $subscriptiontime;
        }
    }

    $DB->set_field('foreact_discussions', 'foreact', $foreactto->id, array('id' => $discussion->id));
    $DB->set_field('foreact_read', 'foreactid', $foreactto->id, array('discussionid' => $discussion->id));

    // Delete the existing per-discussion subscriptions and replace them with the newly calculated ones.
    $DB->delete_records('foreact_discussion_subs', array('discussion' => $discussion->id));
    $newdiscussion = clone $discussion;
    $newdiscussion->foreact = $foreactto->id;
    foreach ($subscriptionchanges as $userid => $preference) {
        if ($preference != \mod_foreact\subscriptions::foreact_DISCUSSION_UNSUBSCRIBED) {
            // Users must have viewdiscussion to a discussion.
            if (has_capability('mod/foreact:viewdiscussion', $destinationctx, $userid)) {
                \mod_foreact\subscriptions::subscribe_user_to_discussion($userid, $newdiscussion, $destinationctx);
            }
        } else {
            \mod_foreact\subscriptions::unsubscribe_user_from_discussion($userid, $newdiscussion, $destinationctx);
        }
    }

    $params = array(
        'context' => $destinationctx,
        'objectid' => $discussion->id,
        'other' => array(
            'fromforeactid' => $foreact->id,
            'toforeactid' => $foreactto->id,
        )
    );
    $event = \mod_foreact\event\discussion_moved::create($params);
    $event->add_record_snapshot('foreact_discussions', $discussion);
    $event->add_record_snapshot('foreact', $foreact);
    $event->add_record_snapshot('foreact', $foreactto);
    $event->trigger();

    // Delete the RSS files for the 2 foreacts to force regeneration of the feeds
    require_once($CFG->dirroot.'/mod/foreact/rsslib.php');
    foreact_rss_delete_file($foreact);
    foreact_rss_delete_file($foreactto);

    redirect($return.'&move=-1&sesskey='.sesskey());
}
// Pin or unpin discussion if requested.
if ($pin !== -1 && confirm_sesskey()) {
    require_capability('mod/foreact:pindiscussions', $modcontext);

    $params = array('context' => $modcontext, 'objectid' => $discussion->id, 'other' => array('foreactid' => $foreact->id));

    switch ($pin) {
        case foreact_DISCUSSION_PINNED:
            // Pin the discussion and trigger discussion pinned event.
            foreact_discussion_pin($modcontext, $foreact, $discussion);
            break;
        case foreact_DISCUSSION_UNPINNED:
            // Unpin the discussion and trigger discussion unpinned event.
            foreact_discussion_unpin($modcontext, $foreact, $discussion);
            break;
        default:
            echo $OUTPUT->notification("Invalid value when attempting to pin/unpin discussion");
            break;
    }

    redirect(new moodle_url('/mod/foreact/discuss.php', array('d' => $discussion->id)));
}

// Trigger discussion viewed event.
foreact_discussion_view($modcontext, $foreact, $discussion);

unset($SESSION->fromdiscussion);

if ($mode) {
    set_user_preference('foreact_displaymode', $mode);
}

$displaymode = get_user_preferences('foreact_displaymode', $CFG->foreact_displaymode);

if ($parent) {
    // If flat AND parent, then force nested display this time
    if ($displaymode == foreact_MODE_FLATOLDEST or $displaymode == foreact_MODE_FLATNEWEST) {
        $displaymode = foreact_MODE_NESTED;
    }
} else {
    $parent = $discussion->firstpost;
}

if (! $post = foreact_get_post_full($parent)) {
    print_error("notexists", 'foreact', "$CFG->wwwroot/mod/foreact/view.php?f=$foreact->id");
}

if (!foreact_user_can_see_post($foreact, $discussion, $post, null, $cm)) {
    print_error('noviewdiscussionspermission', 'foreact', "$CFG->wwwroot/mod/foreact/view.php?id=$foreact->id");
}

if ($mark == 'read' or $mark == 'unread') {
    if ($CFG->foreact_usermarksread && foreact_tp_can_track_foreacts($foreact) && foreact_tp_is_tracked($foreact)) {
        if ($mark == 'read') {
            foreact_tp_add_read_record($USER->id, $postid);
        } else {
            // unread
            foreact_tp_delete_read_records($USER->id, $postid);
        }
    }
}

$searchform = foreact_search_form($course);

$foreactnode = $PAGE->navigation->find($cm->id, navigation_node::TYPE_ACTIVITY);
if (empty($foreactnode)) {
    $foreactnode = $PAGE->navbar;
} else {
    $foreactnode->make_active();
}
$node = $foreactnode->add(format_string($discussion->name), new moodle_url('/mod/foreact/discuss.php', array('d'=>$discussion->id)));
$node->display = false;
if ($node && $post->id != $discussion->firstpost) {
    $node->add(format_string($post->subject), $PAGE->url);
}

$PAGE->set_title("$course->shortname: ".format_string($discussion->name));
$PAGE->set_heading($course->fullname);
$PAGE->set_button($searchform);
$renderer = $PAGE->get_renderer('mod_foreact');


echo $OUTPUT->header();
echo '<script type="text/javascript" src="reactions.js"></script>';

echo $OUTPUT->heading(format_string($foreact->name), 2);
echo $OUTPUT->heading(format_string($discussion->name), 3, 'discussionname');

// is_guest should be used here as this also checks whether the user is a guest in the current course.
// Guests and visitors cannot subscribe - only enrolled users.
if ((!is_guest($modcontext, $USER) && isloggedin()) && has_capability('mod/foreact:viewdiscussion', $modcontext)) {
    // Discussion subscription.
    if (\mod_foreact\subscriptions::is_subscribable($foreact)) {
        echo html_writer::div(
            foreact_get_discussion_subscription_icon($foreact, $post->discussion, null, true),
            'discussionsubscription'
        );
        echo foreact_get_discussion_subscription_icon_preloaders();
    }
}


/// Check to see if groups are being used in this foreact
/// If so, make sure the current person is allowed to see this discussion
/// Also, if we know they should be able to reply, then explicitly set $canreply for performance reasons

$canreply = foreact_user_can_post($foreact, $discussion, $USER, $cm, $course, $modcontext);
if (!$canreply and $foreact->type !== 'news') {
    if (isguestuser() or !isloggedin()) {
        $canreply = true;
    }
    if (!is_enrolled($modcontext) and !is_viewing($modcontext)) {
        // allow guests and not-logged-in to see the link - they are prompted to log in after clicking the link
        // normal users with temporary guest access see this link too, they are asked to enrol instead
        $canreply = enrol_selfenrol_available($course->id);
    }
}

// Output the links to neighbour discussions.
$neighbours = foreact_get_discussion_neighbours($cm, $discussion, $foreact);
$neighbourlinks = $renderer->neighbouring_discussion_navigation($neighbours['prev'], $neighbours['next']);
echo $neighbourlinks;

/// Print the controls across the top
echo '<div class="discussioncontrols clearfix"><div class="controlscontainer m-b-1">';

if (!empty($CFG->enableportfolios) && has_capability('mod/foreact:exportdiscussion', $modcontext)) {
    require_once($CFG->libdir.'/portfoliolib.php');
    $button = new portfolio_add_button();
    $button->set_callback_options('foreact_portfolio_caller', array('discussionid' => $discussion->id), 'mod_foreact');
    $button = $button->to_html(PORTFOLIO_ADD_FULL_FORM, get_string('exportdiscussion', 'mod_foreact'));
    $buttonextraclass = '';
    if (empty($button)) {
        // no portfolio plugin available.
        $button = '&nbsp;';
        $buttonextraclass = ' noavailable';
    }
    echo html_writer::tag('div', $button, array('class' => 'discussioncontrol exporttoportfolio'.$buttonextraclass));
} else {
    echo html_writer::tag('div', '&nbsp;', array('class'=>'discussioncontrol nullcontrol'));
}

// groups selector not needed here
echo '<div class="discussioncontrol displaymode">';
foreact_print_mode_form($discussion->id, $displaymode);
echo "</div>";

if ($foreact->type != 'single'
            && has_capability('mod/foreact:movediscussions', $modcontext)) {

    echo '<div class="discussioncontrol movediscussion">';
    // Popup menu to move discussions to other foreacts. The discussion in a
    // single discussion foreact can't be moved.
    $modinfo = get_fast_modinfo($course);
    if (isset($modinfo->instances['foreact'])) {
        $foreactmenu = array();
        // Check foreact types and eliminate simple discussions.
        $foreactcheck = $DB->get_records('foreact', array('course' => $course->id),'', 'id, type');
        foreach ($modinfo->instances['foreact'] as $foreactcm) {
            if (!$foreactcm->uservisible || !has_capability('mod/foreact:startdiscussion',
                context_module::instance($foreactcm->id))) {
                continue;
            }
            $section = $foreactcm->sectionnum;
            $sectionname = get_section_name($course, $section);
            if (empty($foreactmenu[$section])) {
                $foreactmenu[$section] = array($sectionname => array());
            }
            $foreactidcompare = $foreactcm->instance != $foreact->id;
            $foreacttypecheck = $foreactcheck[$foreactcm->instance]->type !== 'single';
            if ($foreactidcompare and $foreacttypecheck) {
                $url = "/mod/foreact/discuss.php?d=$discussion->id&move=$foreactcm->instance&sesskey=".sesskey();
                $foreactmenu[$section][$sectionname][$url] = format_string($foreactcm->name);
            }
        }
        if (!empty($foreactmenu)) {
            echo '<div class="movediscussionoption">';
            $select = new url_select($foreactmenu, '',
                    array('/mod/foreact/discuss.php?d=' . $discussion->id => get_string("movethisdiscussionto", "foreact")),
                    'foreactmenu', get_string('move'));
            echo $OUTPUT->render($select);
            echo "</div>";
        }
    }
    echo "</div>";
}

if (has_capability('mod/foreact:pindiscussions', $modcontext)) {
    if ($discussion->pinned == foreact_DISCUSSION_PINNED) {
        $pinlink = foreact_DISCUSSION_UNPINNED;
        $pintext = get_string('discussionunpin', 'foreact');
    } else {
        $pinlink = foreact_DISCUSSION_PINNED;
        $pintext = get_string('discussionpin', 'foreact');
    }
    $button = new single_button(new moodle_url('discuss.php', array('pin' => $pinlink, 'd' => $discussion->id)), $pintext, 'post');
    echo html_writer::tag('div', $OUTPUT->render($button), array('class' => 'discussioncontrol pindiscussion'));
}


echo "</div></div>";

if (foreact_discussion_is_locked($foreact, $discussion)) {
    echo html_writer::div(get_string('discussionlocked', 'foreact'), 'discussionlocked');
}

if (!empty($foreact->blockafter) && !empty($foreact->blockperiod)) {
    $a = new stdClass();
    $a->blockafter  = $foreact->blockafter;
    $a->blockperiod = get_string('secondstotime'.$foreact->blockperiod);
    echo $OUTPUT->notification(get_string('thisforeactisthrottled','foreact',$a));
}

if ($foreact->type == 'qanda' && !has_capability('mod/foreact:viewqandawithoutposting', $modcontext) &&
            !foreact_user_has_posted($foreact->id,$discussion->id,$USER->id)) {
    echo $OUTPUT->notification(get_string('qandanotify', 'foreact'));
}

if ($move == -1 and confirm_sesskey()) {
    echo $OUTPUT->notification(get_string('discussionmoved', 'foreact', format_string($foreact->name,true)), 'notifysuccess');
}

$canrate = has_capability('mod/foreact:rate', $modcontext);
foreact_print_discussion($course, $cm, $foreact, $discussion, $post, $displaymode, $canreply, $canrate);

echo $neighbourlinks;

// Add the subscription toggle JS.
$PAGE->requires->yui_module('moodle-mod_foreact-subscriptiontoggle', 'Y.M.mod_foreact.subscriptiontoggle.init');

echo $OUTPUT->footer();

