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

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/mod/foreact/lib.php');
require_once($CFG->libdir . '/rsslib.php');

$id = optional_param('id', 0, PARAM_INT);                   // Course id
$subscribe = optional_param('subscribe', null, PARAM_INT);  // Subscribe/Unsubscribe all foreacts

$url = new moodle_url('/mod/foreact/index.php', array('id' => $id));
if ($subscribe !== null) {
    require_sesskey();
    $url->param('subscribe', $subscribe);
}
$PAGE->set_url($url);

if ($id) {
    if (!$course = $DB->get_record('course', array('id' => $id))) {
        print_error('invalidcourseid');
    }
} else {
    $course = get_site();
}

require_course_login($course);
$PAGE->set_pagelayout('incourse');
$coursecontext = context_course::instance($course->id);

unset($SESSION->fromdiscussion);

$params = array(
    'context' => context_course::instance($course->id)
);
$event = \mod_foreact\event\course_module_instance_list_viewed::create($params);
$event->add_record_snapshot('course', $course);
$event->trigger();

$strforeacts       = get_string('foreacts', 'foreact');
$strforeact        = get_string('foreact', 'foreact');
$strdescription  = get_string('description');
$strdiscussions  = get_string('discussions', 'foreact');
$strsubscribed   = get_string('subscribed', 'foreact');
$strunreadposts  = get_string('unreadposts', 'foreact');
$strtracking     = get_string('tracking', 'foreact');
$strmarkallread  = get_string('markallread', 'foreact');
$strtrackforeact   = get_string('trackforeact', 'foreact');
$strnotrackforeact = get_string('notrackforeact', 'foreact');
$strsubscribe    = get_string('subscribe', 'foreact');
$strunsubscribe  = get_string('unsubscribe', 'foreact');
$stryes          = get_string('yes');
$strno           = get_string('no');
$strrss          = get_string('rss');
$stremaildigest  = get_string('emaildigest');

$searchform = foreact_search_form($course);

// Start of the table for General foreacts.
$generaltable = new html_table();
$generaltable->head  = array ($strforeact, $strdescription, $strdiscussions);
$generaltable->align = array ('left', 'left', 'center');

if ($usetracking = foreact_tp_can_track_foreacts()) {
    $untracked = foreact_tp_get_untracked_foreacts($USER->id, $course->id);

    $generaltable->head[] = $strunreadposts;
    $generaltable->align[] = 'center';

    $generaltable->head[] = $strtracking;
    $generaltable->align[] = 'center';
}

// Fill the subscription cache for this course and user combination.
\mod_foreact\subscriptions::fill_subscription_cache_for_course($course->id, $USER->id);

$usesections = course_format_uses_sections($course->format);

$table = new html_table();

// Parse and organise all the foreacts.  Most foreacts are course modules but
// some special ones are not.  These get placed in the general foreacts
// category with the foreacts in section 0.

$foreacts = $DB->get_records_sql("
    SELECT f.*,
           d.maildigest
      FROM {foreact} f
 LEFT JOIN {foreact_digests} d ON d.foreact = f.id AND d.userid = ?
     WHERE f.course = ?
    ", array($USER->id, $course->id));

$generalforeacts  = array();
$learningforeacts = array();
$modinfo = get_fast_modinfo($course);
$showsubscriptioncolumns = false;

foreach ($modinfo->get_instances_of('foreact') as $foreactid => $cm) {
    if (!$cm->uservisible or !isset($foreacts[$foreactid])) {
        continue;
    }

    $foreact = $foreacts[$foreactid];

    if (!$context = context_module::instance($cm->id, IGNORE_MISSING)) {
        // Shouldn't happen.
        continue;
    }

    if (!has_capability('mod/foreact:viewdiscussion', $context)) {
        // User can't view this one - skip it.
        continue;
    }

    // Determine whether subscription options should be displayed.
    $foreact->cansubscribe = mod_foreact\subscriptions::is_subscribable($foreact);
    $foreact->cansubscribe = $foreact->cansubscribe || has_capability('mod/foreact:managesubscriptions', $context);
    $foreact->issubscribed = mod_foreact\subscriptions::is_subscribed($USER->id, $foreact, null, $cm);

    $showsubscriptioncolumns = $showsubscriptioncolumns || $foreact->issubscribed || $foreact->cansubscribe;

    // Fill two type array - order in modinfo is the same as in course.
    if ($foreact->type == 'news' or $foreact->type == 'social') {
        $generalforeacts[$foreact->id] = $foreact;

    } else if ($course->id == SITEID or empty($cm->sectionnum)) {
        $generalforeacts[$foreact->id] = $foreact;

    } else {
        $learningforeacts[$foreact->id] = $foreact;
    }
}

if ($showsubscriptioncolumns) {
    // The user can subscribe to at least one foreact.
    $generaltable->head[] = $strsubscribed;
    $generaltable->align[] = 'center';

    $generaltable->head[] = $stremaildigest . ' ' . $OUTPUT->help_icon('emaildigesttype', 'mod_foreact');
    $generaltable->align[] = 'center';

}

if ($show_rss = (($showsubscriptioncolumns || $course->id == SITEID) &&
                 isset($CFG->enablerssfeeds) && isset($CFG->foreact_enablerssfeeds) &&
                 $CFG->enablerssfeeds && $CFG->foreact_enablerssfeeds)) {
    $generaltable->head[] = $strrss;
    $generaltable->align[] = 'center';
}


// Do course wide subscribe/unsubscribe if requested
if (!is_null($subscribe)) {
    if (isguestuser() or !$showsubscriptioncolumns) {
        // There should not be any links leading to this place, just redirect.
        redirect(
                new moodle_url('/mod/foreact/index.php', array('id' => $id)),
                get_string('subscribeenrolledonly', 'foreact'),
                null,
                \core\output\notification::NOTIFY_ERROR
            );
    }
    // Can proceed now, the user is not guest and is enrolled
    foreach ($modinfo->get_instances_of('foreact') as $foreactid => $cm) {
        $foreact = $foreacts[$foreactid];
        $modcontext = context_module::instance($cm->id);
        $cansub = false;

        if (has_capability('mod/foreact:viewdiscussion', $modcontext)) {
            $cansub = true;
        }
        if ($cansub && $cm->visible == 0 &&
            !has_capability('mod/foreact:managesubscriptions', $modcontext))
        {
            $cansub = false;
        }
        if (!\mod_foreact\subscriptions::is_forcesubscribed($foreact)) {
            $subscribed = \mod_foreact\subscriptions::is_subscribed($USER->id, $foreact, null, $cm);
            $canmanageactivities = has_capability('moodle/course:manageactivities', $coursecontext, $USER->id);
            if (($canmanageactivities || \mod_foreact\subscriptions::is_subscribable($foreact)) && $subscribe && !$subscribed && $cansub) {
                \mod_foreact\subscriptions::subscribe_user($USER->id, $foreact, $modcontext, true);
            } else if (!$subscribe && $subscribed) {
                \mod_foreact\subscriptions::unsubscribe_user($USER->id, $foreact, $modcontext, true);
            }
        }
    }
    $returnto = foreact_go_back_to(new moodle_url('/mod/foreact/index.php', array('id' => $course->id)));
    $shortname = format_string($course->shortname, true, array('context' => context_course::instance($course->id)));
    if ($subscribe) {
        redirect(
                $returnto,
                get_string('nowallsubscribed', 'foreact', $shortname),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
    } else {
        redirect(
                $returnto,
                get_string('nowallunsubscribed', 'foreact', $shortname),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
    }
}

if ($generalforeacts) {
    // Process general foreacts.
    foreach ($generalforeacts as $foreact) {
        $cm      = $modinfo->instances['foreact'][$foreact->id];
        $context = context_module::instance($cm->id);

        $count = foreact_count_discussions($foreact, $cm, $course);

        if ($usetracking) {
            if ($foreact->trackingtype == foreact_TRACKING_OFF) {
                $unreadlink  = '-';
                $trackedlink = '-';

            } else {
                if (isset($untracked[$foreact->id])) {
                        $unreadlink  = '-';
                } else if ($unread = foreact_tp_count_foreact_unread_posts($cm, $course)) {
                    $unreadlink = '<span class="unread"><a href="view.php?f='.$foreact->id.'">'.$unread.'</a>';
                    $icon = $OUTPUT->pix_icon('t/markasread', $strmarkallread);
                    $unreadlink .= '<a title="'.$strmarkallread.'" href="markposts.php?f='.
                                   $foreact->id.'&amp;mark=read&amp;sesskey=' . sesskey() . '">' . $icon . '</a></span>';
                } else {
                    $unreadlink = '<span class="read">0</span>';
                }

                if (($foreact->trackingtype == foreact_TRACKING_FORCED) && ($CFG->foreact_allowforcedreadtracking)) {
                    $trackedlink = $stryes;
                } else if ($foreact->trackingtype === foreact_TRACKING_OFF || ($USER->trackforeacts == 0)) {
                    $trackedlink = '-';
                } else {
                    $aurl = new moodle_url('/mod/foreact/settracking.php', array(
                            'id' => $foreact->id,
                            'sesskey' => sesskey(),
                        ));
                    if (!isset($untracked[$foreact->id])) {
                        $trackedlink = $OUTPUT->single_button($aurl, $stryes, 'post', array('title' => $strnotrackforeact));
                    } else {
                        $trackedlink = $OUTPUT->single_button($aurl, $strno, 'post', array('title' => $strtrackforeact));
                    }
                }
            }
        }

        $foreact->intro = shorten_text(format_module_intro('foreact', $foreact, $cm->id), $CFG->foreact_shortpost);
        $foreactname = format_string($foreact->name, true);

        if ($cm->visible) {
            $style = '';
        } else {
            $style = 'class="dimmed"';
        }
        $foreactlink = "<a href=\"view.php?f=$foreact->id\" $style>".format_string($foreact->name,true)."</a>";
        $discussionlink = "<a href=\"view.php?f=$foreact->id\" $style>".$count."</a>";

        $row = array ($foreactlink, $foreact->intro, $discussionlink);
        if ($usetracking) {
            $row[] = $unreadlink;
            $row[] = $trackedlink;    // Tracking.
        }

        if ($showsubscriptioncolumns) {
            $row[] = foreact_get_subscribe_link($foreact, $context, array('subscribed' => $stryes,
                'unsubscribed' => $strno, 'forcesubscribed' => $stryes,
                'cantsubscribe' => '-'), false, false, true);
            $row[] = foreact_index_get_foreact_subscription_selector($foreact);
        }

        // If this foreact has RSS activated, calculate it.
        if ($show_rss) {
            if ($foreact->rsstype and $foreact->rssarticles) {
                //Calculate the tooltip text
                if ($foreact->rsstype == 1) {
                    $tooltiptext = get_string('rsssubscriberssdiscussions', 'foreact');
                } else {
                    $tooltiptext = get_string('rsssubscriberssposts', 'foreact');
                }

                if (!isloggedin() && $course->id == SITEID) {
                    $userid = guest_user()->id;
                } else {
                    $userid = $USER->id;
                }
                //Get html code for RSS link
                $row[] = rss_get_link($context->id, $userid, 'mod_foreact', $foreact->id, $tooltiptext);
            } else {
                $row[] = '&nbsp;';
            }
        }

        $generaltable->data[] = $row;
    }
}


// Start of the table for Learning foreacts
$learningtable = new html_table();
$learningtable->head  = array ($strforeact, $strdescription, $strdiscussions);
$learningtable->align = array ('left', 'left', 'center');

if ($usetracking) {
    $learningtable->head[] = $strunreadposts;
    $learningtable->align[] = 'center';

    $learningtable->head[] = $strtracking;
    $learningtable->align[] = 'center';
}

if ($showsubscriptioncolumns) {
    $learningtable->head[] = $strsubscribed;
    $learningtable->align[] = 'center';

    $learningtable->head[] = $stremaildigest . ' ' . $OUTPUT->help_icon('emaildigesttype', 'mod_foreact');
    $learningtable->align[] = 'center';
}

if ($show_rss = (($showsubscriptioncolumns || $course->id == SITEID) &&
                 isset($CFG->enablerssfeeds) && isset($CFG->foreact_enablerssfeeds) &&
                 $CFG->enablerssfeeds && $CFG->foreact_enablerssfeeds)) {
    $learningtable->head[] = $strrss;
    $learningtable->align[] = 'center';
}

// Now let's process the learning foreacts.
if ($course->id != SITEID) {    // Only real courses have learning foreacts
    // 'format_.'$course->format only applicable when not SITEID (format_site is not a format)
    $strsectionname  = get_string('sectionname', 'format_'.$course->format);
    // Add extra field for section number, at the front
    array_unshift($learningtable->head, $strsectionname);
    array_unshift($learningtable->align, 'center');


    if ($learningforeacts) {
        $currentsection = '';
            foreach ($learningforeacts as $foreact) {
            $cm      = $modinfo->instances['foreact'][$foreact->id];
            $context = context_module::instance($cm->id);

            $count = foreact_count_discussions($foreact, $cm, $course);

            if ($usetracking) {
                if ($foreact->trackingtype == foreact_TRACKING_OFF) {
                    $unreadlink  = '-';
                    $trackedlink = '-';

                } else {
                    if (isset($untracked[$foreact->id])) {
                        $unreadlink  = '-';
                    } else if ($unread = foreact_tp_count_foreact_unread_posts($cm, $course)) {
                        $unreadlink = '<span class="unread"><a href="view.php?f='.$foreact->id.'">'.$unread.'</a>';
                        $icon = $OUTPUT->pix_icon('t/markasread', $strmarkallread);
                        $unreadlink .= '<a title="'.$strmarkallread.'" href="markposts.php?f='.
                                       $foreact->id.'&amp;mark=read&sesskey=' . sesskey() . '">' . $icon . '</a></span>';
                    } else {
                        $unreadlink = '<span class="read">0</span>';
                    }

                    if (($foreact->trackingtype == foreact_TRACKING_FORCED) && ($CFG->foreact_allowforcedreadtracking)) {
                        $trackedlink = $stryes;
                    } else if ($foreact->trackingtype === foreact_TRACKING_OFF || ($USER->trackforeacts == 0)) {
                        $trackedlink = '-';
                    } else {
                        $aurl = new moodle_url('/mod/foreact/settracking.php', array('id' => $foreact->id));
                        if (!isset($untracked[$foreact->id])) {
                            $trackedlink = $OUTPUT->single_button($aurl, $stryes, 'post', array('title' => $strnotrackforeact));
                        } else {
                            $trackedlink = $OUTPUT->single_button($aurl, $strno, 'post', array('title' => $strtrackforeact));
                        }
                    }
                }
            }

            $foreact->intro = shorten_text(format_module_intro('foreact', $foreact, $cm->id), $CFG->foreact_shortpost);

            if ($cm->sectionnum != $currentsection) {
                $printsection = get_section_name($course, $cm->sectionnum);
                if ($currentsection) {
                    $learningtable->data[] = 'hr';
                }
                $currentsection = $cm->sectionnum;
            } else {
                $printsection = '';
            }

            $foreactname = format_string($foreact->name,true);

            if ($cm->visible) {
                $style = '';
            } else {
                $style = 'class="dimmed"';
            }
            $foreactlink = "<a href=\"view.php?f=$foreact->id\" $style>".format_string($foreact->name,true)."</a>";
            $discussionlink = "<a href=\"view.php?f=$foreact->id\" $style>".$count."</a>";

            $row = array ($printsection, $foreactlink, $foreact->intro, $discussionlink);
            if ($usetracking) {
                $row[] = $unreadlink;
                $row[] = $trackedlink;    // Tracking.
            }

            if ($showsubscriptioncolumns) {
                $row[] = foreact_get_subscribe_link($foreact, $context, array('subscribed' => $stryes,
                    'unsubscribed' => $strno, 'forcesubscribed' => $stryes,
                    'cantsubscribe' => '-'), false, false, true);
                $row[] = foreact_index_get_foreact_subscription_selector($foreact);
            }

            //If this foreact has RSS activated, calculate it
            if ($show_rss) {
                if ($foreact->rsstype and $foreact->rssarticles) {
                    //Calculate the tolltip text
                    if ($foreact->rsstype == 1) {
                        $tooltiptext = get_string('rsssubscriberssdiscussions', 'foreact');
                    } else {
                        $tooltiptext = get_string('rsssubscriberssposts', 'foreact');
                    }
                    //Get html code for RSS link
                    $row[] = rss_get_link($context->id, $USER->id, 'mod_foreact', $foreact->id, $tooltiptext);
                } else {
                    $row[] = '&nbsp;';
                }
            }

            $learningtable->data[] = $row;
        }
    }
}

// Output the page.
$PAGE->navbar->add($strforeacts);
$PAGE->set_title("$course->shortname: $strforeacts");
$PAGE->set_heading($course->fullname);
$PAGE->set_button($searchform);
echo $OUTPUT->header();

if (!isguestuser() && isloggedin() && $showsubscriptioncolumns) {
    // Show the subscribe all options only to non-guest, enrolled users.
    echo $OUTPUT->box_start('subscription');

    $subscriptionlink = new moodle_url('/mod/foreact/index.php', [
        'id'        => $course->id,
        'sesskey'   => sesskey(),
    ]);

    // Subscribe all.
    $subscriptionlink->param('subscribe', 1);
    echo html_writer::tag('div', html_writer::link($subscriptionlink, get_string('allsubscribe', 'foreact')), [
            'class' => 'helplink',
        ]);

    // Unsubscribe all.
    $subscriptionlink->param('subscribe', 0);
    echo html_writer::tag('div', html_writer::link($subscriptionlink, get_string('allunsubscribe', 'foreact')), [
            'class' => 'helplink',
        ]);

    echo $OUTPUT->box_end();
    echo $OUTPUT->box('&nbsp;', 'clearer');
}

if ($generalforeacts) {
    echo $OUTPUT->heading(get_string('generalforeacts', 'foreact'), 2);
    echo html_writer::table($generaltable);
}

if ($learningforeacts) {
    echo $OUTPUT->heading(get_string('learningforeacts', 'foreact'), 2);
    echo html_writer::table($learningtable);
}

echo $OUTPUT->footer();

/**
 * Get the content of the foreact subscription options for this foreact.
 *
 * @param   stdClass    $foreact      The foreact to return options for
 * @return  string
 */
function foreact_index_get_foreact_subscription_selector($foreact) {
    global $OUTPUT, $PAGE;

    if ($foreact->cansubscribe || $foreact->issubscribed) {
        if ($foreact->maildigest === null) {
            $foreact->maildigest = -1;
        }

        $renderer = $PAGE->get_renderer('mod_foreact');
        return $OUTPUT->render($renderer->render_digest_options($foreact, $foreact->maildigest));
    } else {
        // This user can subscribe to some foreacts. Add the empty fields.
        return '';
    }
};
