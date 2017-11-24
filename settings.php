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
 * @copyright  2009 Petr Skoda (http://skodak.org)
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    require_once($CFG->dirroot.'/mod/foreact/lib.php');

    $settings->add(new admin_setting_configselect('foreact_displaymode', get_string('displaymode', 'foreact'),
                       get_string('configdisplaymode', 'foreact'), foreact_MODE_NESTED, foreact_get_layout_modes()));

    // Less non-HTML characters than this is short
    $settings->add(new admin_setting_configtext('foreact_shortpost', get_string('shortpost', 'foreact'),
                       get_string('configshortpost', 'foreact'), 300, PARAM_INT));

    // More non-HTML characters than this is long
    $settings->add(new admin_setting_configtext('foreact_longpost', get_string('longpost', 'foreact'),
                       get_string('configlongpost', 'foreact'), 600, PARAM_INT));

    // Number of discussions on a page
    $settings->add(new admin_setting_configtext('foreact_manydiscussions', get_string('manydiscussions', 'foreact'),
                       get_string('configmanydiscussions', 'foreact'), 100, PARAM_INT));

    if (isset($CFG->maxbytes)) {
        $maxbytes = 0;
        if (isset($CFG->foreact_maxbytes)) {
            $maxbytes = $CFG->foreact_maxbytes;
        }
        $settings->add(new admin_setting_configselect('foreact_maxbytes', get_string('maxattachmentsize', 'foreact'),
                           get_string('configmaxbytes', 'foreact'), 512000, get_max_upload_sizes($CFG->maxbytes, 0, 0, $maxbytes)));
    }

    // Default number of attachments allowed per post in all foreacts
    $settings->add(new admin_setting_configtext('foreact_maxattachments', get_string('maxattachments', 'foreact'),
                       get_string('configmaxattachments', 'foreact'), 9, PARAM_INT));

    // Default Read Tracking setting.
    $options = array();
    $options[foreact_TRACKING_OPTIONAL] = get_string('trackingoptional', 'foreact');
    $options[foreact_TRACKING_OFF] = get_string('trackingoff', 'foreact');
    $options[foreact_TRACKING_FORCED] = get_string('trackingon', 'foreact');
    $settings->add(new admin_setting_configselect('foreact_trackingtype', get_string('trackingtype', 'foreact'),
                       get_string('configtrackingtype', 'foreact'), foreact_TRACKING_OPTIONAL, $options));

    // Default whether user needs to mark a post as read
    $settings->add(new admin_setting_configcheckbox('foreact_trackreadposts', get_string('trackforeact', 'foreact'),
                       get_string('configtrackreadposts', 'foreact'), 1));

    // Default whether user needs to mark a post as read.
    $settings->add(new admin_setting_configcheckbox('foreact_allowforcedreadtracking', get_string('forcedreadtracking', 'foreact'),
                       get_string('forcedreadtracking_desc', 'foreact'), 0));

    // Default number of days that a post is considered old
    $settings->add(new admin_setting_configtext('foreact_oldpostdays', get_string('oldpostdays', 'foreact'),
                       get_string('configoldpostdays', 'foreact'), 14, PARAM_INT));

    // Default whether user needs to mark a post as read
    $settings->add(new admin_setting_configcheckbox('foreact_usermarksread', get_string('usermarksread', 'foreact'),
                       get_string('configusermarksread', 'foreact'), 0));

    $options = array();
    for ($i = 0; $i < 24; $i++) {
        $options[$i] = sprintf("%02d",$i);
    }
    // Default time (hour) to execute 'clean_read_records' cron
    $settings->add(new admin_setting_configselect('foreact_cleanreadtime', get_string('cleanreadtime', 'foreact'),
                       get_string('configcleanreadtime', 'foreact'), 2, $options));

    // Default time (hour) to send digest email
    $settings->add(new admin_setting_configselect('digestmailtime', get_string('digestmailtime', 'foreact'),
                       get_string('configdigestmailtime', 'foreact'), 17, $options));

    if (empty($CFG->enablerssfeeds)) {
        $options = array(0 => get_string('rssglobaldisabled', 'admin'));
        $str = get_string('configenablerssfeeds', 'foreact').'<br />'.get_string('configenablerssfeedsdisabled2', 'admin');

    } else {
        $options = array(0=>get_string('no'), 1=>get_string('yes'));
        $str = get_string('configenablerssfeeds', 'foreact');
    }
    $settings->add(new admin_setting_configselect('foreact_enablerssfeeds', get_string('enablerssfeeds', 'admin'),
                       $str, 0, $options));

    if (!empty($CFG->enablerssfeeds)) {
        $options = array(
            0 => get_string('none'),
            1 => get_string('discussions', 'foreact'),
            2 => get_string('posts', 'foreact')
        );
        $settings->add(new admin_setting_configselect('foreact_rsstype', get_string('rsstypedefault', 'foreact'),
                get_string('configrsstypedefault', 'foreact'), 0, $options));

        $options = array(
            0  => '0',
            1  => '1',
            2  => '2',
            3  => '3',
            4  => '4',
            5  => '5',
            10 => '10',
            15 => '15',
            20 => '20',
            25 => '25',
            30 => '30',
            40 => '40',
            50 => '50'
        );
        $settings->add(new admin_setting_configselect('foreact_rssarticles', get_string('rssarticles', 'foreact'),
                get_string('configrssarticlesdefault', 'foreact'), 0, $options));
    }

    $settings->add(new admin_setting_configcheckbox('foreact_enabletimedposts', get_string('timedposts', 'foreact'),
                       get_string('configenabletimedposts', 'foreact'), 1));
}

