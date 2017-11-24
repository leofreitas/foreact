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
 * foreact external functions and service definitions.
 *
 * @package    mod_foreact
 * @copyright  2012 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$functions = array(

    'mod_foreact_get_foreacts_by_courses' => array(
        'classname' => 'mod_foreact_external',
        'methodname' => 'get_foreacts_by_courses',
        'classpath' => 'mod/foreact/externallib.php',
        'description' => 'Returns a list of foreact instances in a provided set of courses, if
            no courses are provided then all the foreact instances the user has access to will be
            returned.',
        'type' => 'read',
        'capabilities' => 'mod/foreact:viewdiscussion',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_foreact_get_foreact_discussion_posts' => array(
        'classname' => 'mod_foreact_external',
        'methodname' => 'get_foreact_discussion_posts',
        'classpath' => 'mod/foreact/externallib.php',
        'description' => 'Returns a list of foreact posts for a discussion.',
        'type' => 'read',
        'capabilities' => 'mod/foreact:viewdiscussion, mod/foreact:viewqandawithoutposting',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_foreact_get_foreact_discussions_paginated' => array(
        'classname' => 'mod_foreact_external',
        'methodname' => 'get_foreact_discussions_paginated',
        'classpath' => 'mod/foreact/externallib.php',
        'description' => 'Returns a list of foreact discussions optionally sorted and paginated.',
        'type' => 'read',
        'capabilities' => 'mod/foreact:viewdiscussion, mod/foreact:viewqandawithoutposting',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_foreact_view_foreact' => array(
        'classname' => 'mod_foreact_external',
        'methodname' => 'view_foreact',
        'classpath' => 'mod/foreact/externallib.php',
        'description' => 'Trigger the course module viewed event and update the module completion status.',
        'type' => 'write',
        'capabilities' => 'mod/foreact:viewdiscussion',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_foreact_view_foreact_discussion' => array(
        'classname' => 'mod_foreact_external',
        'methodname' => 'view_foreact_discussion',
        'classpath' => 'mod/foreact/externallib.php',
        'description' => 'Trigger the foreact discussion viewed event.',
        'type' => 'write',
        'capabilities' => 'mod/foreact:viewdiscussion',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_foreact_add_discussion_post' => array(
        'classname' => 'mod_foreact_external',
        'methodname' => 'add_discussion_post',
        'classpath' => 'mod/foreact/externallib.php',
        'description' => 'Create new posts into an existing discussion.',
        'type' => 'write',
        'capabilities' => 'mod/foreact:replypost',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_foreact_add_discussion' => array(
        'classname' => 'mod_foreact_external',
        'methodname' => 'add_discussion',
        'classpath' => 'mod/foreact/externallib.php',
        'description' => 'Add a new discussion into an existing foreact.',
        'type' => 'write',
        'capabilities' => 'mod/foreact:startdiscussion',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),

    'mod_foreact_can_add_discussion' => array(
        'classname' => 'mod_foreact_external',
        'methodname' => 'can_add_discussion',
        'classpath' => 'mod/foreact/externallib.php',
        'description' => 'Check if the current user can add discussions in the given foreact (and optionally for the given group).',
        'type' => 'read',
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE)
    ),
);
