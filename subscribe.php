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
 * Subscribe to or unsubscribe from a foreact or manage foreact subscription mode
 *
 * This script can be used by either individual users to subscribe to or
 * unsubscribe from a foreact (no 'mode' param provided), or by foreact managers
 * to control the subscription mode (by 'mode' param).
 * This script can be called from a link in email so the sesskey is not
 * required parameter. However, if sesskey is missing, the user has to go
 * through a confirmation page that redirects the user back with the
 * sesskey.
 *
 * @package   mod_foreact
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__.'/../../config.php');
require_once($CFG->dirroot.'/mod/foreact/lib.php');

$id             = required_param('id', PARAM_INT);             // The foreact to set subscription on.
$mode           = optional_param('mode', null, PARAM_INT);     // The foreact's subscription mode.
$user           = optional_param('user', 0, PARAM_INT);        // The userid of the user to subscribe, defaults to $USER.
$discussionid   = optional_param('d', null, PARAM_INT);        // The discussionid to subscribe.
$sesskey        = optional_param('sesskey', null, PARAM_RAW);
$returnurl      = optional_param('returnurl', null, PARAM_RAW);

$url = new moodle_url('/mod/foreact/subscribe.php', array('id'=>$id));
if (!is_null($mode)) {
    $url->param('mode', $mode);
}
if ($user !== 0) {
    $url->param('user', $user);
}
if (!is_null($sesskey)) {
    $url->param('sesskey', $sesskey);
}
if (!is_null($discussionid)) {
    $url->param('d', $discussionid);
    if (!$discussion = $DB->get_record('foreact_discussions', array('id' => $discussionid, 'foreact' => $id))) {
        print_error('invaliddiscussionid', 'foreact');
    }
}
$PAGE->set_url($url);

$foreact   = $DB->get_record('foreact', array('id' => $id), '*', MUST_EXIST);
$course  = $DB->get_record('course', array('id' => $foreact->course), '*', MUST_EXIST);
$cm      = get_coursemodule_from_instance('foreact', $foreact->id, $course->id, false, MUST_EXIST);
$context = context_module::instance($cm->id);

if ($user) {
    require_sesskey();
    if (!has_capability('mod/foreact:managesubscriptions', $context)) {
        print_error('nopermissiontosubscribe', 'foreact');
    }
    $user = $DB->get_record('user', array('id' => $user), '*', MUST_EXIST);
} else {
    $user = $USER;
}

if (isset($cm->groupmode) && empty($course->groupmodeforce)) {
    $groupmode = $cm->groupmode;
} else {
    $groupmode = $course->groupmode;
}

$issubscribed = \mod_foreact\subscriptions::is_subscribed($user->id, $foreact, $discussionid, $cm);

// For a user to subscribe when a groupmode is set, they must have access to at least one group.
if ($groupmode && !$issubscribed && !has_capability('moodle/site:accessallgroups', $context)) {
    if (!groups_get_all_groups($course->id, $USER->id)) {
        print_error('cannotsubscribe', 'foreact');
    }
}

require_login($course, false, $cm);

if (is_null($mode) and !is_enrolled($context, $USER, '', true)) {   // Guests and visitors can't subscribe - only enrolled
    $PAGE->set_title($course->shortname);
    $PAGE->set_heading($course->fullname);
    if (isguestuser()) {
        echo $OUTPUT->header();
        echo $OUTPUT->confirm(get_string('subscribeenrolledonly', 'foreact').'<br /><br />'.get_string('liketologin'),
                     get_login_url(), new moodle_url('/mod/foreact/view.php', array('f'=>$id)));
        echo $OUTPUT->footer();
        exit;
    } else {
        // There should not be any links leading to this place, just redirect.
        redirect(
                new moodle_url('/mod/foreact/view.php', array('f'=>$id)),
                get_string('subscribeenrolledonly', 'foreact'),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
    }
}

$returnto = optional_param('backtoindex',0,PARAM_INT)
    ? "index.php?id=".$course->id
    : "view.php?f=$id";

if ($returnurl) {
    $returnto = $returnurl;
}

if (!is_null($mode) and has_capability('mod/foreact:managesubscriptions', $context)) {
    require_sesskey();
    switch ($mode) {
        case foreact_CHOOSESUBSCRIBE : // 0
            \mod_foreact\subscriptions::set_subscription_mode($foreact->id, foreact_CHOOSESUBSCRIBE);
            redirect(
                    $returnto,
                    get_string('everyonecannowchoose', 'foreact'),
                    null,
                    \core\output\notification::NOTIFY_SUCCESS
                );
            break;
        case foreact_FORCESUBSCRIBE : // 1
            \mod_foreact\subscriptions::set_subscription_mode($foreact->id, foreact_FORCESUBSCRIBE);
            redirect(
                    $returnto,
                    get_string('everyoneisnowsubscribed', 'foreact'),
                    null,
                    \core\output\notification::NOTIFY_SUCCESS
                );
            break;
        case foreact_INITIALSUBSCRIBE : // 2
            if ($foreact->forcesubscribe <> foreact_INITIALSUBSCRIBE) {
                $users = \mod_foreact\subscriptions::get_potential_subscribers($context, 0, 'u.id, u.email', '');
                foreach ($users as $user) {
                    \mod_foreact\subscriptions::subscribe_user($user->id, $foreact, $context);
                }
            }
            \mod_foreact\subscriptions::set_subscription_mode($foreact->id, foreact_INITIALSUBSCRIBE);
            redirect(
                    $returnto,
                    get_string('everyoneisnowsubscribed', 'foreact'),
                    null,
                    \core\output\notification::NOTIFY_SUCCESS
                );
            break;
        case foreact_DISALLOWSUBSCRIBE : // 3
            \mod_foreact\subscriptions::set_subscription_mode($foreact->id, foreact_DISALLOWSUBSCRIBE);
            redirect(
                    $returnto,
                    get_string('noonecansubscribenow', 'foreact'),
                    null,
                    \core\output\notification::NOTIFY_SUCCESS
                );
            break;
        default:
            print_error(get_string('invalidforcesubscribe', 'foreact'));
    }
}

if (\mod_foreact\subscriptions::is_forcesubscribed($foreact)) {
    redirect(
            $returnto,
            get_string('everyoneisnowsubscribed', 'foreact'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
}

$info = new stdClass();
$info->name  = fullname($user);
$info->foreact = format_string($foreact->name);

if ($issubscribed) {
    if (is_null($sesskey)) {
        // We came here via link in email.
        $PAGE->set_title($course->shortname);
        $PAGE->set_heading($course->fullname);
        echo $OUTPUT->header();

        $viewurl = new moodle_url('/mod/foreact/view.php', array('f' => $id));
        if ($discussionid) {
            $a = new stdClass();
            $a->foreact = format_string($foreact->name);
            $a->discussion = format_string($discussion->name);
            echo $OUTPUT->confirm(get_string('confirmunsubscribediscussion', 'foreact', $a),
                    $PAGE->url, $viewurl);
        } else {
            echo $OUTPUT->confirm(get_string('confirmunsubscribe', 'foreact', format_string($foreact->name)),
                    $PAGE->url, $viewurl);
        }
        echo $OUTPUT->footer();
        exit;
    }
    require_sesskey();
    if ($discussionid === null) {
        if (\mod_foreact\subscriptions::unsubscribe_user($user->id, $foreact, $context, true)) {
            redirect(
                    $returnto,
                    get_string('nownotsubscribed', 'foreact', $info),
                    null,
                    \core\output\notification::NOTIFY_SUCCESS
                );
        } else {
            print_error('cannotunsubscribe', 'foreact', get_local_referer(false));
        }
    } else {
        if (\mod_foreact\subscriptions::unsubscribe_user_from_discussion($user->id, $discussion, $context)) {
            $info->discussion = $discussion->name;
            redirect(
                    $returnto,
                    get_string('discussionnownotsubscribed', 'foreact', $info),
                    null,
                    \core\output\notification::NOTIFY_SUCCESS
                );
        } else {
            print_error('cannotunsubscribe', 'foreact', get_local_referer(false));
        }
    }

} else {  // subscribe
    if (\mod_foreact\subscriptions::subscription_disabled($foreact) && !has_capability('mod/foreact:managesubscriptions', $context)) {
        print_error('disallowsubscribe', 'foreact', get_local_referer(false));
    }
    if (!has_capability('mod/foreact:viewdiscussion', $context)) {
        print_error('noviewdiscussionspermission', 'foreact', get_local_referer(false));
    }
    if (is_null($sesskey)) {
        // We came here via link in email.
        $PAGE->set_title($course->shortname);
        $PAGE->set_heading($course->fullname);
        echo $OUTPUT->header();

        $viewurl = new moodle_url('/mod/foreact/view.php', array('f' => $id));
        if ($discussionid) {
            $a = new stdClass();
            $a->foreact = format_string($foreact->name);
            $a->discussion = format_string($discussion->name);
            echo $OUTPUT->confirm(get_string('confirmsubscribediscussion', 'foreact', $a),
                    $PAGE->url, $viewurl);
        } else {
            echo $OUTPUT->confirm(get_string('confirmsubscribe', 'foreact', format_string($foreact->name)),
                    $PAGE->url, $viewurl);
        }
        echo $OUTPUT->footer();
        exit;
    }
    require_sesskey();
    if ($discussionid == null) {
        \mod_foreact\subscriptions::subscribe_user($user->id, $foreact, $context, true);
        redirect(
                $returnto,
                get_string('nowsubscribed', 'foreact', $info),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
    } else {
        $info->discussion = $discussion->name;
        \mod_foreact\subscriptions::subscribe_user_to_discussion($user->id, $discussion, $context);
        redirect(
                $returnto,
                get_string('discussionnowsubscribed', 'foreact', $info),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
    }
}
