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
 * @package   mod_foreact
 * @copyright 1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

    require_once('../../config.php');
    require_once('lib.php');
    require_once($CFG->libdir.'/completionlib.php');

    $id          = optional_param('id', 0, PARAM_INT);       // Course Module ID
    $f           = optional_param('f', 0, PARAM_INT);        // foreact ID
    $mode        = optional_param('mode', 0, PARAM_INT);     // Display mode (for single foreact)
    $showall     = optional_param('showall', '', PARAM_INT); // show all discussions on one page
    $changegroup = optional_param('group', -1, PARAM_INT);   // choose the current group
    $page        = optional_param('page', 0, PARAM_INT);     // which page to show
    $search      = optional_param('search', '', PARAM_CLEAN);// search string

    $params = array();
    if ($id) {
        $params['id'] = $id;
    } else {
        $params['f'] = $f;
    }
    if ($page) {
        $params['page'] = $page;
    }
    if ($search) {
        $params['search'] = $search;
    }
    $PAGE->set_url('/mod/foreact/view.php', $params);

    if ($id) {
        if (! $cm = get_coursemodule_from_id('foreact', $id)) {
            print_error('invalidcoursemodule');
        }
        if (! $course = $DB->get_record("course", array("id" => $cm->course))) {
            print_error('coursemisconf');
        }
        if (! $foreact = $DB->get_record("foreact", array("id" => $cm->instance))) {
            print_error('invalidforeactid', 'foreact');
        }
        if ($foreact->type == 'single') {
            $PAGE->set_pagetype('mod-foreact-discuss');
        }
        // move require_course_login here to use forced language for course
        // fix for MDL-6926
        require_course_login($course, true, $cm);
        $strforeacts = get_string("modulenameplural", "foreact");
        $strforeact = get_string("modulename", "foreact");
    } else if ($f) {

        if (! $foreact = $DB->get_record("foreact", array("id" => $f))) {
            print_error('invalidforeactid', 'foreact');
        }
        if (! $course = $DB->get_record("course", array("id" => $foreact->course))) {
            print_error('coursemisconf');
        }

        if (!$cm = get_coursemodule_from_instance("foreact", $foreact->id, $course->id)) {
            print_error('missingparameter');
        }
        // move require_course_login here to use forced language for course
        // fix for MDL-6926
        require_course_login($course, true, $cm);
        $strforeacts = get_string("modulenameplural", "foreact");
        $strforeact = get_string("modulename", "foreact");
    } else {
        print_error('missingparameter');
    }

    if (!$PAGE->button) {
        $PAGE->set_button(foreact_search_form($course, $search));
    }

    $context = context_module::instance($cm->id);
    $PAGE->set_context($context);

    if (!empty($CFG->enablerssfeeds) && !empty($CFG->foreact_enablerssfeeds) && $foreact->rsstype && $foreact->rssarticles) {
        require_once("$CFG->libdir/rsslib.php");

        $rsstitle = format_string($course->shortname, true, array('context' => context_course::instance($course->id))) . ': ' . format_string($foreact->name);
        rss_add_http_header($context, 'mod_foreact', $foreact, $rsstitle);
    }

/// Print header.

    $PAGE->set_title($foreact->name);
    $PAGE->add_body_class('foreacttype-'.$foreact->type);
    $PAGE->set_heading($course->fullname);

/// Some capability checks.
    if (empty($cm->visible) and !has_capability('moodle/course:viewhiddenactivities', $context)) {
        notice(get_string("activityiscurrentlyhidden"));
    }

    if (!has_capability('mod/foreact:viewdiscussion', $context)) {
        notice(get_string('noviewdiscussionspermission', 'foreact'));
    }

    // Mark viewed and trigger the course_module_viewed event.
    foreact_view($foreact, $course, $cm, $context);

    echo $OUTPUT->header();

    echo $OUTPUT->heading(format_string($foreact->name), 2);
    if (!empty($foreact->intro) && $foreact->type != 'single' && $foreact->type != 'teacher') {
        echo $OUTPUT->box(format_module_intro('foreact', $foreact, $cm->id), 'generalbox', 'intro');
    }

/// find out current groups mode
    groups_print_activity_menu($cm, $CFG->wwwroot . '/mod/foreact/view.php?id=' . $cm->id);

    $SESSION->fromdiscussion = qualified_me();   // Return here if we post or set subscription etc


/// Print settings and things across the top

    // If it's a simple single discussion foreact, we need to print the display
    // mode control.
    if ($foreact->type == 'single') {
        $discussion = NULL;
        $discussions = $DB->get_records('foreact_discussions', array('foreact'=>$foreact->id), 'timemodified ASC');
        if (!empty($discussions)) {
            $discussion = array_pop($discussions);
        }
        if ($discussion) {
            if ($mode) {
                set_user_preference("foreact_displaymode", $mode);
            }
            $displaymode = get_user_preferences("foreact_displaymode", $CFG->foreact_displaymode);
            foreact_print_mode_form($foreact->id, $displaymode, $foreact->type);
        }
    }

    if (!empty($foreact->blockafter) && !empty($foreact->blockperiod)) {
        $a = new stdClass();
        $a->blockafter = $foreact->blockafter;
        $a->blockperiod = get_string('secondstotime'.$foreact->blockperiod);
        echo $OUTPUT->notification(get_string('thisforeactisthrottled', 'foreact', $a));
    }

    if ($foreact->type == 'qanda' && !has_capability('moodle/course:manageactivities', $context)) {
        echo $OUTPUT->notification(get_string('qandanotify','foreact'));
    }

    switch ($foreact->type) {
        case 'single':
            if (!empty($discussions) && count($discussions) > 1) {
                echo $OUTPUT->notification(get_string('warnformorepost', 'foreact'));
            }
            if (! $post = foreact_get_post_full($discussion->firstpost)) {
                print_error('cannotfindfirstpost', 'foreact');
            }
            if ($mode) {
                set_user_preference("foreact_displaymode", $mode);
            }

            $canreply    = foreact_user_can_post($foreact, $discussion, $USER, $cm, $course, $context);
            $canrate     = has_capability('mod/foreact:rate', $context);
            $displaymode = get_user_preferences("foreact_displaymode", $CFG->foreact_displaymode);

            echo '&nbsp;'; // this should fix the floating in FF
            foreact_print_discussion($course, $cm, $foreact, $discussion, $post, $displaymode, $canreply, $canrate);
            break;

        case 'eachuser':
            echo '<p class="mdl-align">';
            if (foreact_user_can_post_discussion($foreact, null, -1, $cm)) {
                print_string("allowsdiscussions", "foreact");
            } else {
                echo '&nbsp;';
            }
            echo '</p>';
            if (!empty($showall)) {
                foreact_print_latest_discussions($course, $foreact, 0, 'header', '', -1, -1, -1, 0, $cm);
            } else {
                foreact_print_latest_discussions($course, $foreact, -1, 'header', '', -1, -1, $page, $CFG->foreact_manydiscussions, $cm);
            }
            break;

        case 'teacher':
            if (!empty($showall)) {
                foreact_print_latest_discussions($course, $foreact, 0, 'header', '', -1, -1, -1, 0, $cm);
            } else {
                foreact_print_latest_discussions($course, $foreact, -1, 'header', '', -1, -1, $page, $CFG->foreact_manydiscussions, $cm);
            }
            break;

        case 'blog':
            echo '<br />';
            if (!empty($showall)) {
                foreact_print_latest_discussions($course, $foreact, 0, 'plain', 'd.pinned DESC, p.created DESC', -1, -1, -1, 0, $cm);
            } else {
                foreact_print_latest_discussions($course, $foreact, -1, 'plain', 'd.pinned DESC, p.created DESC', -1, -1, $page,
                    $CFG->foreact_manydiscussions, $cm);
            }
            break;

        default:
            echo '<br />';
            if (!empty($showall)) {
                foreact_print_latest_discussions($course, $foreact, 0, 'header', '', -1, -1, -1, 0, $cm);
            } else {
                foreact_print_latest_discussions($course, $foreact, -1, 'header', '', -1, -1, $page, $CFG->foreact_manydiscussions, $cm);
            }


            break;
    }

    // Add the subscription toggle JS.
    $PAGE->requires->yui_module('moodle-mod_foreact-subscriptiontoggle', 'Y.M.mod_foreact.subscriptiontoggle.init');

    echo $OUTPUT->footer($course);
