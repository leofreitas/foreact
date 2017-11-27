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
 * External foreact API
 *
 * @package    mod_foreact
 * @copyright  2012 Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once("$CFG->libdir/externallib.php");

class mod_foreact_external extends external_api {

    /**
     * Describes the parameters for get_foreact.
     *
     * @return external_function_parameters
     * @since Moodle 2.5
     */
    public static function get_foreacts_by_courses_parameters() {
        return new external_function_parameters (
            array(
                'courseids' => new external_multiple_structure(new external_value(PARAM_INT, 'course ID',
                        VALUE_REQUIRED, '', NULL_NOT_ALLOWED), 'Array of Course IDs', VALUE_DEFAULT, array()),
            )
        );
    }

    /**
     * Returns a list of foreacts in a provided list of courses,
     * if no list is provided all foreacts that the user can view
     * will be returned.
     *
     * @param array $courseids the course ids
     * @return array the foreact details
     * @since Moodle 2.5
     */
    public static function get_foreacts_by_courses($courseids = array()) {
        global $CFG;

        require_once($CFG->dirroot . "/mod/foreact/lib.php");

        $params = self::validate_parameters(self::get_foreacts_by_courses_parameters(), array('courseids' => $courseids));

        $courses = array();
        if (empty($params['courseids'])) {
            $courses = enrol_get_my_courses();
            $params['courseids'] = array_keys($courses);
        }

        // Array to store the foreacts to return.
        $arrforeacts = array();
        $warnings = array();

        // Ensure there are courseids to loop through.
        if (!empty($params['courseids'])) {

            list($courses, $warnings) = external_util::validate_courses($params['courseids'], $courses);

            // Get the foreacts in this course. This function checks users visibility permissions.
            $foreacts = get_all_instances_in_courses("foreact", $courses);
            foreach ($foreacts as $foreact) {

                $course = $courses[$foreact->course];
                $cm = get_coursemodule_from_instance('foreact', $foreact->id, $course->id);
                $context = context_module::instance($cm->id);

                // Skip foreacts we are not allowed to see discussions.
                if (!has_capability('mod/foreact:viewdiscussion', $context)) {
                    continue;
                }

                $foreact->name = external_format_string($foreact->name, $context->id);
                // Format the intro before being returning using the format setting.
                list($foreact->intro, $foreact->introformat) = external_format_text($foreact->intro, $foreact->introformat,
                                                                                $context->id, 'mod_foreact', 'intro', null);
                $foreact->introfiles = external_util::get_area_files($context->id, 'mod_foreact', 'intro', false, false);
                // Discussions count. This function does static request cache.
                $foreact->numdiscussions = foreact_count_discussions($foreact, $cm, $course);
                $foreact->cmid = $foreact->coursemodule;
                $foreact->cancreatediscussions = foreact_user_can_post_discussion($foreact, null, -1, $cm, $context);
                $foreact->istracked = foreact_tp_is_tracked($foreact);
                if ($foreact->istracked) {
                    $foreact->unreadpostscount = foreact_tp_count_foreact_unread_posts($cm, $course);
                }

                // Add the foreact to the array to return.
                $arrforeacts[$foreact->id] = $foreact;
            }
        }

        return $arrforeacts;
    }

    /**
     * Describes the get_foreact return value.
     *
     * @return external_single_structure
     * @since Moodle 2.5
     */
    public static function get_foreacts_by_courses_returns() {
        return new external_multiple_structure(
            new external_single_structure(
                array(
                    'id' => new external_value(PARAM_INT, 'foreact id'),
                    'course' => new external_value(PARAM_INT, 'Course id'),
                    'type' => new external_value(PARAM_TEXT, 'The foreact type'),
                    'name' => new external_value(PARAM_RAW, 'foreact name'),
                    'intro' => new external_value(PARAM_RAW, 'The foreact intro'),
                    'introformat' => new external_format_value('intro'),
                    'introfiles' => new external_files('Files in the introduction text', VALUE_OPTIONAL),
                    'assessed' => new external_value(PARAM_INT, 'Aggregate type'),
                    'assesstimestart' => new external_value(PARAM_INT, 'Assess start time'),
                    'assesstimefinish' => new external_value(PARAM_INT, 'Assess finish time'),
                    'scale' => new external_value(PARAM_INT, 'Scale'),
                    'maxbytes' => new external_value(PARAM_INT, 'Maximum attachment size'),
                    'maxattachments' => new external_value(PARAM_INT, 'Maximum number of attachments'),
                    'forcesubscribe' => new external_value(PARAM_INT, 'Force users to subscribe'),
                    'trackingtype' => new external_value(PARAM_INT, 'Subscription mode'),
                    'rsstype' => new external_value(PARAM_INT, 'RSS feed for this activity'),
                    'rssarticles' => new external_value(PARAM_INT, 'Number of RSS recent articles'),
                    'timemodified' => new external_value(PARAM_INT, 'Time modified'),
                    'warnafter' => new external_value(PARAM_INT, 'Post threshold for warning'),
                    'blockafter' => new external_value(PARAM_INT, 'Post threshold for blocking'),
                    'blockperiod' => new external_value(PARAM_INT, 'Time period for blocking'),
                    'completiondiscussions' => new external_value(PARAM_INT, 'Student must create discussions'),
                    'completionreplies' => new external_value(PARAM_INT, 'Student must post replies'),
                    'completionposts' => new external_value(PARAM_INT, 'Student must post discussions or replies'),
                    'cmid' => new external_value(PARAM_INT, 'Course module id'),
                    'numdiscussions' => new external_value(PARAM_INT, 'Number of discussions in the foreact', VALUE_OPTIONAL),
                    'cancreatediscussions' => new external_value(PARAM_BOOL, 'If the user can create discussions', VALUE_OPTIONAL),
                    'lockdiscussionafter' => new external_value(PARAM_INT, 'After what period a discussion is locked', VALUE_OPTIONAL),
                    'istracked' => new external_value(PARAM_BOOL, 'If the user is tracking the foreact', VALUE_OPTIONAL),
                    'unreadpostscount' => new external_value(PARAM_INT, 'The number of unread posts for tracked foreacts',
                        VALUE_OPTIONAL),
                ), 'foreact'
            )
        );
    }

    /**
     * Describes the parameters for get_foreact_discussion_posts.
     *
     * @return external_function_parameters
     * @since Moodle 2.7
     */
    public static function get_foreact_discussion_posts_parameters() {
        return new external_function_parameters (
            array(
                'discussionid' => new external_value(PARAM_INT, 'discussion ID', VALUE_REQUIRED),
                'sortby' => new external_value(PARAM_ALPHA,
                    'sort by this element: id, created or modified', VALUE_DEFAULT, 'created'),
                'sortdirection' => new external_value(PARAM_ALPHA, 'sort direction: ASC or DESC', VALUE_DEFAULT, 'DESC')
            )
        );
    }

    /**
     * Returns a list of foreact posts for a discussion
     *
     * @param int $discussionid the post ids
     * @param string $sortby sort by this element (id, created or modified)
     * @param string $sortdirection sort direction: ASC or DESC
     *
     * @return array the foreact post details
     * @since Moodle 2.7
     */
    public static function get_foreact_discussion_posts($discussionid, $sortby = "created", $sortdirection = "DESC") {
        global $CFG, $DB, $USER, $PAGE;

        $posts = array();
        $warnings = array();

        // Validate the parameter.
        $params = self::validate_parameters(self::get_foreact_discussion_posts_parameters(),
            array(
                'discussionid' => $discussionid,
                'sortby' => $sortby,
                'sortdirection' => $sortdirection));

        // Compact/extract functions are not recommended.
        $discussionid   = $params['discussionid'];
        $sortby         = $params['sortby'];
        $sortdirection  = $params['sortdirection'];

        $sortallowedvalues = array('id', 'created', 'modified');
        if (!in_array($sortby, $sortallowedvalues)) {
            throw new invalid_parameter_exception('Invalid value for sortby parameter (value: ' . $sortby . '),' .
                'allowed values are: ' . implode(',', $sortallowedvalues));
        }

        $sortdirection = strtoupper($sortdirection);
        $directionallowedvalues = array('ASC', 'DESC');
        if (!in_array($sortdirection, $directionallowedvalues)) {
            throw new invalid_parameter_exception('Invalid value for sortdirection parameter (value: ' . $sortdirection . '),' .
                'allowed values are: ' . implode(',', $directionallowedvalues));
        }

        $discussion = $DB->get_record('foreact_discussions', array('id' => $discussionid), '*', MUST_EXIST);
        $foreact = $DB->get_record('foreact', array('id' => $discussion->foreact), '*', MUST_EXIST);
        $course = $DB->get_record('course', array('id' => $foreact->course), '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('foreact', $foreact->id, $course->id, false, MUST_EXIST);

        // Validate the module context. It checks everything that affects the module visibility (including groupings, etc..).
        $modcontext = context_module::instance($cm->id);
        self::validate_context($modcontext);

        // This require must be here, see mod/foreact/discuss.php.
        require_once($CFG->dirroot . "/mod/foreact/lib.php");

        // Check they have the view foreact capability.
        require_capability('mod/foreact:viewdiscussion', $modcontext, null, true, 'noviewdiscussionspermission', 'foreact');

        if (! $post = foreact_get_post_full($discussion->firstpost)) {
            throw new moodle_exception('notexists', 'foreact');
        }

        // This function check groups, qanda, timed discussions, etc.
        if (!foreact_user_can_see_post($foreact, $discussion, $post, null, $cm)) {
            throw new moodle_exception('noviewdiscussionspermission', 'foreact');
        }

        $canviewfullname = has_capability('moodle/site:viewfullnames', $modcontext);

        // We will add this field in the response.
        $canreply = foreact_user_can_post($foreact, $discussion, $USER, $cm, $course, $modcontext);

        $foreacttracked = foreact_tp_is_tracked($foreact);

        $sort = 'p.' . $sortby . ' ' . $sortdirection;
        $allposts = foreact_get_all_discussion_posts($discussion->id, $sort, $foreacttracked);

        foreach ($allposts as $post) {

            if (!foreact_user_can_see_post($foreact, $discussion, $post, null, $cm)) {
                $warning = array();
                $warning['item'] = 'post';
                $warning['itemid'] = $post->id;
                $warning['warningcode'] = '1';
                $warning['message'] = 'You can\'t see this post';
                $warnings[] = $warning;
                continue;
            }

            // Function foreact_get_all_discussion_posts adds postread field.
            // Note that the value returned can be a boolean or an integer. The WS expects a boolean.
            if (empty($post->postread)) {
                $post->postread = false;
            } else {
                $post->postread = true;
            }

            $post->canreply = $canreply;
            if (!empty($post->children)) {
                $post->children = array_keys($post->children);
            } else {
                $post->children = array();
            }

            if (foreact_is_author_hidden($post, $foreact)) {
                $post->userid = null;
                $post->userfullname = null;
                $post->userpictureurl = null;
            } else {
                $user = new stdclass();
                $user->id = $post->userid;
                $user = username_load_fields_from_object($user, $post, null, array('picture', 'imagealt', 'email'));
                $post->userfullname = fullname($user, $canviewfullname);

                $userpicture = new user_picture($user);
                $userpicture->size = 1; // Size f1.
                $post->userpictureurl = $userpicture->get_url($PAGE)->out(false);
            }

            $post->subject = external_format_string($post->subject, $modcontext->id);
            // Rewrite embedded images URLs.
            list($post->message, $post->messageformat) =
                external_format_text($post->message, $post->messageformat, $modcontext->id, 'mod_foreact', 'post', $post->id);

            // List attachments.
            if (!empty($post->attachment)) {
                $post->attachments = external_util::get_area_files($modcontext->id, 'mod_foreact', 'attachment', $post->id);
            }
            $messageinlinefiles = external_util::get_area_files($modcontext->id, 'mod_foreact', 'post', $post->id);
            if (!empty($messageinlinefiles)) {
                $post->messageinlinefiles = $messageinlinefiles;
            }

            $posts[] = $post;
        }

        $result = array();
        $result['posts'] = $posts;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the get_foreact_discussion_posts return value.
     *
     * @return external_single_structure
     * @since Moodle 2.7
     */
    public static function get_foreact_discussion_posts_returns() {
        return new external_single_structure(
            array(
                'posts' => new external_multiple_structure(
                        new external_single_structure(
                            array(
                                'id' => new external_value(PARAM_INT, 'Post id'),
                                'discussion' => new external_value(PARAM_INT, 'Discussion id'),
                                'parent' => new external_value(PARAM_INT, 'Parent id'),
                                'userid' => new external_value(PARAM_INT, 'User id'),
                                'created' => new external_value(PARAM_INT, 'Creation time'),
                                'modified' => new external_value(PARAM_INT, 'Time modified'),
                                'mailed' => new external_value(PARAM_INT, 'Mailed?'),
                                'subject' => new external_value(PARAM_TEXT, 'The post subject'),
                                'message' => new external_value(PARAM_RAW, 'The post message'),
                                'messageformat' => new external_format_value('message'),
                                'messagetrust' => new external_value(PARAM_INT, 'Can we trust?'),
                                'messageinlinefiles' => new external_files('post message inline files', VALUE_OPTIONAL),
                                'attachment' => new external_value(PARAM_RAW, 'Has attachments?'),
                                'attachments' => new external_files('attachments', VALUE_OPTIONAL),
                                'totalscore' => new external_value(PARAM_INT, 'The post message total score'),
                                'mailnow' => new external_value(PARAM_INT, 'Mail now?'),
                                'children' => new external_multiple_structure(new external_value(PARAM_INT, 'children post id')),
                                'canreply' => new external_value(PARAM_BOOL, 'The user can reply to posts?'),
                                'postread' => new external_value(PARAM_BOOL, 'The post was read'),
                                'userfullname' => new external_value(PARAM_TEXT, 'Post author full name'),
                                'userpictureurl' => new external_value(PARAM_URL, 'Post author picture.', VALUE_OPTIONAL)
                            ), 'post'
                        )
                    ),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Describes the parameters for get_foreact_discussions_paginated.
     *
     * @return external_function_parameters
     * @since Moodle 2.8
     */
    public static function get_foreact_discussions_paginated_parameters() {
        return new external_function_parameters (
            array(
                'foreactid' => new external_value(PARAM_INT, 'foreact instance id', VALUE_REQUIRED),
                'sortby' => new external_value(PARAM_ALPHA,
                    'sort by this element: id, timemodified, timestart or timeend', VALUE_DEFAULT, 'timemodified'),
                'sortdirection' => new external_value(PARAM_ALPHA, 'sort direction: ASC or DESC', VALUE_DEFAULT, 'DESC'),
                'page' => new external_value(PARAM_INT, 'current page', VALUE_DEFAULT, -1),
                'perpage' => new external_value(PARAM_INT, 'items per page', VALUE_DEFAULT, 0),
            )
        );
    }

    /**
     * Returns a list of foreact discussions optionally sorted and paginated.
     *
     * @param int $foreactid the foreact instance id
     * @param string $sortby sort by this element (id, timemodified, timestart or timeend)
     * @param string $sortdirection sort direction: ASC or DESC
     * @param int $page page number
     * @param int $perpage items per page
     *
     * @return array the foreact discussion details including warnings
     * @since Moodle 2.8
     */
    public static function get_foreact_discussions_paginated($foreactid, $sortby = 'timemodified', $sortdirection = 'DESC',
                                                    $page = -1, $perpage = 0) {
        global $CFG, $DB, $USER, $PAGE;

        require_once($CFG->dirroot . "/mod/foreact/lib.php");

        $warnings = array();
        $discussions = array();

        $params = self::validate_parameters(self::get_foreact_discussions_paginated_parameters(),
            array(
                'foreactid' => $foreactid,
                'sortby' => $sortby,
                'sortdirection' => $sortdirection,
                'page' => $page,
                'perpage' => $perpage
            )
        );

        // Compact/extract functions are not recommended.
        $foreactid        = $params['foreactid'];
        $sortby         = $params['sortby'];
        $sortdirection  = $params['sortdirection'];
        $page           = $params['page'];
        $perpage        = $params['perpage'];

        $sortallowedvalues = array('id', 'timemodified', 'timestart', 'timeend');
        if (!in_array($sortby, $sortallowedvalues)) {
            throw new invalid_parameter_exception('Invalid value for sortby parameter (value: ' . $sortby . '),' .
                'allowed values are: ' . implode(',', $sortallowedvalues));
        }

        $sortdirection = strtoupper($sortdirection);
        $directionallowedvalues = array('ASC', 'DESC');
        if (!in_array($sortdirection, $directionallowedvalues)) {
            throw new invalid_parameter_exception('Invalid value for sortdirection parameter (value: ' . $sortdirection . '),' .
                'allowed values are: ' . implode(',', $directionallowedvalues));
        }

        $foreact = $DB->get_record('foreact', array('id' => $foreactid), '*', MUST_EXIST);
        $course = $DB->get_record('course', array('id' => $foreact->course), '*', MUST_EXIST);
        $cm = get_coursemodule_from_instance('foreact', $foreact->id, $course->id, false, MUST_EXIST);

        // Validate the module context. It checks everything that affects the module visibility (including groupings, etc..).
        $modcontext = context_module::instance($cm->id);
        self::validate_context($modcontext);

        // Check they have the view foreact capability.
        require_capability('mod/foreact:viewdiscussion', $modcontext, null, true, 'noviewdiscussionspermission', 'foreact');

        $sort = 'd.pinned DESC, d.' . $sortby . ' ' . $sortdirection;
        $alldiscussions = foreact_get_discussions($cm, $sort, true, -1, -1, true, $page, $perpage, foreact_POSTS_ALL_USER_GROUPS);

        if ($alldiscussions) {
            $canviewfullname = has_capability('moodle/site:viewfullnames', $modcontext);

            // Get the unreads array, this takes a foreact id and returns data for all discussions.
            $unreads = array();
            if ($cantrack = foreact_tp_can_track_foreacts($foreact)) {
                if ($foreacttracked = foreact_tp_is_tracked($foreact)) {
                    $unreads = foreact_get_discussions_unread($cm);
                }
            }
            // The foreact function returns the replies for all the discussions in a given foreact.
            $replies = foreact_count_discussion_replies($foreactid, $sort, -1, $page, $perpage);

            foreach ($alldiscussions as $discussion) {

                // This function checks for qanda foreacts.
                // Note that the foreact_get_discussions returns as id the post id, not the discussion id so we need to do this.
                $discussionrec = clone $discussion;
                $discussionrec->id = $discussion->discussion;
                if (!foreact_user_can_see_discussion($foreact, $discussionrec, $modcontext)) {
                    $warning = array();
                    // Function foreact_get_discussions returns foreact_posts ids not foreact_discussions ones.
                    $warning['item'] = 'post';
                    $warning['itemid'] = $discussion->id;
                    $warning['warningcode'] = '1';
                    $warning['message'] = 'You can\'t see this discussion';
                    $warnings[] = $warning;
                    continue;
                }

                $discussion->numunread = 0;
                if ($cantrack && $foreacttracked) {
                    if (isset($unreads[$discussion->discussion])) {
                        $discussion->numunread = (int) $unreads[$discussion->discussion];
                    }
                }

                $discussion->numreplies = 0;
                if (!empty($replies[$discussion->discussion])) {
                    $discussion->numreplies = (int) $replies[$discussion->discussion]->replies;
                }

                $discussion->name = external_format_string($discussion->name, $modcontext->id);
                $discussion->subject = external_format_string($discussion->subject, $modcontext->id);
                // Rewrite embedded images URLs.
                list($discussion->message, $discussion->messageformat) =
                    external_format_text($discussion->message, $discussion->messageformat,
                                            $modcontext->id, 'mod_foreact', 'post', $discussion->id);

                // List attachments.
                if (!empty($discussion->attachment)) {
                    $discussion->attachments = external_util::get_area_files($modcontext->id, 'mod_foreact', 'attachment',
                                                                                $discussion->id);
                }
                $messageinlinefiles = external_util::get_area_files($modcontext->id, 'mod_foreact', 'post', $discussion->id);
                if (!empty($messageinlinefiles)) {
                    $discussion->messageinlinefiles = $messageinlinefiles;
                }

                $discussion->locked = foreact_discussion_is_locked($foreact, $discussion);
                $discussion->canreply = foreact_user_can_post($foreact, $discussion, $USER, $cm, $course, $modcontext);

                if (foreact_is_author_hidden($discussion, $foreact)) {
                    $discussion->userid = null;
                    $discussion->userfullname = null;
                    $discussion->userpictureurl = null;

                    $discussion->usermodified = null;
                    $discussion->usermodifiedfullname = null;
                    $discussion->usermodifiedpictureurl = null;
                } else {
                    $picturefields = explode(',', user_picture::fields());

                    // Load user objects from the results of the query.
                    $user = new stdclass();
                    $user->id = $discussion->userid;
                    $user = username_load_fields_from_object($user, $discussion, null, $picturefields);
                    // Preserve the id, it can be modified by username_load_fields_from_object.
                    $user->id = $discussion->userid;
                    $discussion->userfullname = fullname($user, $canviewfullname);

                    $userpicture = new user_picture($user);
                    $userpicture->size = 1; // Size f1.
                    $discussion->userpictureurl = $userpicture->get_url($PAGE)->out(false);

                    $usermodified = new stdclass();
                    $usermodified->id = $discussion->usermodified;
                    $usermodified = username_load_fields_from_object($usermodified, $discussion, 'um', $picturefields);
                    // Preserve the id (it can be overwritten due to the prefixed $picturefields).
                    $usermodified->id = $discussion->usermodified;
                    $discussion->usermodifiedfullname = fullname($usermodified, $canviewfullname);

                    $userpicture = new user_picture($usermodified);
                    $userpicture->size = 1; // Size f1.
                    $discussion->usermodifiedpictureurl = $userpicture->get_url($PAGE)->out(false);
                }

                $discussions[] = $discussion;
            }
        }

        $result = array();
        $result['discussions'] = $discussions;
        $result['warnings'] = $warnings;
        return $result;

    }

    /**
     * Describes the get_foreact_discussions_paginated return value.
     *
     * @return external_single_structure
     * @since Moodle 2.8
     */
    public static function get_foreact_discussions_paginated_returns() {
        return new external_single_structure(
            array(
                'discussions' => new external_multiple_structure(
                        new external_single_structure(
                            array(
                                'id' => new external_value(PARAM_INT, 'Post id'),
                                'name' => new external_value(PARAM_TEXT, 'Discussion name'),
                                'groupid' => new external_value(PARAM_INT, 'Group id'),
                                'timemodified' => new external_value(PARAM_INT, 'Time modified'),
                                'usermodified' => new external_value(PARAM_INT, 'The id of the user who last modified'),
                                'timestart' => new external_value(PARAM_INT, 'Time discussion can start'),
                                'timeend' => new external_value(PARAM_INT, 'Time discussion ends'),
                                'discussion' => new external_value(PARAM_INT, 'Discussion id'),
                                'parent' => new external_value(PARAM_INT, 'Parent id'),
                                'userid' => new external_value(PARAM_INT, 'User who started the discussion id'),
                                'created' => new external_value(PARAM_INT, 'Creation time'),
                                'modified' => new external_value(PARAM_INT, 'Time modified'),
                                'mailed' => new external_value(PARAM_INT, 'Mailed?'),
                                'subject' => new external_value(PARAM_TEXT, 'The post subject'),
                                'message' => new external_value(PARAM_RAW, 'The post message'),
                                'messageformat' => new external_format_value('message'),
                                'messagetrust' => new external_value(PARAM_INT, 'Can we trust?'),
                                'messageinlinefiles' => new external_files('post message inline files', VALUE_OPTIONAL),
                                'attachment' => new external_value(PARAM_RAW, 'Has attachments?'),
                                'attachments' => new external_files('attachments', VALUE_OPTIONAL),
                                'totalscore' => new external_value(PARAM_INT, 'The post message total score'),
                                'mailnow' => new external_value(PARAM_INT, 'Mail now?'),
                                'userfullname' => new external_value(PARAM_TEXT, 'Post author full name'),
                                'usermodifiedfullname' => new external_value(PARAM_TEXT, 'Post modifier full name'),
                                'userpictureurl' => new external_value(PARAM_URL, 'Post author picture.'),
                                'usermodifiedpictureurl' => new external_value(PARAM_URL, 'Post modifier picture.'),
                                'numreplies' => new external_value(PARAM_TEXT, 'The number of replies in the discussion'),
                                'numunread' => new external_value(PARAM_INT, 'The number of unread discussions.'),
                                'pinned' => new external_value(PARAM_BOOL, 'Is the discussion pinned'),
                                'locked' => new external_value(PARAM_BOOL, 'Is the discussion locked'),
                                'canreply' => new external_value(PARAM_BOOL, 'Can the user reply to the discussion'),
                            ), 'post'
                        )
                    ),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.9
     */
    public static function view_foreact_parameters() {
        return new external_function_parameters(
            array(
                'foreactid' => new external_value(PARAM_INT, 'foreact instance id')
            )
        );
    }

    /**
     * Trigger the course module viewed event and update the module completion status.
     *
     * @param int $foreactid the foreact instance id
     * @return array of warnings and status result
     * @since Moodle 2.9
     * @throws moodle_exception
     */
    public static function view_foreact($foreactid) {
        global $DB, $CFG;
        require_once($CFG->dirroot . "/mod/foreact/lib.php");

        $params = self::validate_parameters(self::view_foreact_parameters(),
                                            array(
                                                'foreactid' => $foreactid
                                            ));
        $warnings = array();

        // Request and permission validation.
        $foreact = $DB->get_record('foreact', array('id' => $params['foreactid']), '*', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($foreact, 'foreact');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        require_capability('mod/foreact:viewdiscussion', $context, null, true, 'noviewdiscussionspermission', 'foreact');

        // Call the foreact/lib API.
        foreact_view($foreact, $course, $cm, $context);

        $result = array();
        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 2.9
     */
    public static function view_foreact_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 2.9
     */
    public static function view_foreact_discussion_parameters() {
        return new external_function_parameters(
            array(
                'discussionid' => new external_value(PARAM_INT, 'discussion id')
            )
        );
    }

    /**
     * Trigger the discussion viewed event.
     *
     * @param int $discussionid the discussion id
     * @return array of warnings and status result
     * @since Moodle 2.9
     * @throws moodle_exception
     */
    public static function view_foreact_discussion($discussionid) {
        global $DB, $CFG, $USER;
        require_once($CFG->dirroot . "/mod/foreact/lib.php");

        $params = self::validate_parameters(self::view_foreact_discussion_parameters(),
                                            array(
                                                'discussionid' => $discussionid
                                            ));
        $warnings = array();

        $discussion = $DB->get_record('foreact_discussions', array('id' => $params['discussionid']), '*', MUST_EXIST);
        $foreact = $DB->get_record('foreact', array('id' => $discussion->foreact), '*', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($foreact, 'foreact');

        // Validate the module context. It checks everything that affects the module visibility (including groupings, etc..).
        $modcontext = context_module::instance($cm->id);
        self::validate_context($modcontext);

        require_capability('mod/foreact:viewdiscussion', $modcontext, null, true, 'noviewdiscussionspermission', 'foreact');

        // Call the foreact/lib API.
        foreact_discussion_view($modcontext, $foreact, $discussion);

        // Mark as read if required.
        if (!$CFG->foreact_usermarksread && foreact_tp_is_tracked($foreact)) {
            foreact_tp_mark_discussion_read($USER, $discussion->id);
        }

        $result = array();
        $result['status'] = true;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 2.9
     */
    public static function view_foreact_discussion_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'status: true if success'),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function add_discussion_post_parameters() {
        return new external_function_parameters(
            array(
                'postid' => new external_value(PARAM_INT, 'the post id we are going to reply to
                                                (can be the initial discussion post'),
                'subject' => new external_value(PARAM_TEXT, 'new post subject'),
                'message' => new external_value(PARAM_RAW, 'new post message (only html format allowed)'),
                'options' => new external_multiple_structure (
                    new external_single_structure(
                        array(
                            'name' => new external_value(PARAM_ALPHANUM,
                                        'The allowed keys (value format) are:
                                        discussionsubscribe (bool); subscribe to the discussion?, default to true
                                        inlineattachmentsid              (int); the draft file area id for inline attachments
                                        attachmentsid       (int); the draft file area id for attachments
                            '),
                            'value' => new external_value(PARAM_RAW, 'the value of the option,
                                                            this param is validated in the external function.'
                        )
                    )
                ), 'Options', VALUE_DEFAULT, array())
            )
        );
    }

    /**
     * Create new posts into an existing discussion.
     *
     * @param int $postid the post id we are going to reply to
     * @param string $subject new post subject
     * @param string $message new post message (only html format allowed)
     * @param array $options optional settings
     * @return array of warnings and the new post id
     * @since Moodle 3.0
     * @throws moodle_exception
     */
    public static function add_discussion_post($postid, $subject, $message, $options = array()) {
        global $DB, $CFG, $USER;
        require_once($CFG->dirroot . "/mod/foreact/lib.php");

        $params = self::validate_parameters(self::add_discussion_post_parameters(),
            array(
                'postid' => $postid,
                'subject' => $subject,
                'message' => $message,
                'options' => $options
            )
        );
        $warnings = array();

        if (!$parent = foreact_get_post_full($params['postid'])) {
            throw new moodle_exception('invalidparentpostid', 'foreact');
        }

        if (!$discussion = $DB->get_record("foreact_discussions", array("id" => $parent->discussion))) {
            throw new moodle_exception('notpartofdiscussion', 'foreact');
        }

        // Request and permission validation.
        $foreact = $DB->get_record('foreact', array('id' => $discussion->foreact), '*', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($foreact, 'foreact');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        // Validate options.
        $options = array(
            'discussionsubscribe' => true,
            'inlineattachmentsid' => 0,
            'attachmentsid' => null
        );
        foreach ($params['options'] as $option) {
            $name = trim($option['name']);
            switch ($name) {
                case 'discussionsubscribe':
                    $value = clean_param($option['value'], PARAM_BOOL);
                    break;
                case 'inlineattachmentsid':
                    $value = clean_param($option['value'], PARAM_INT);
                    break;
                case 'attachmentsid':
                    $value = clean_param($option['value'], PARAM_INT);
                    // Ensure that the user has permissions to create attachments.
                    if (!has_capability('mod/foreact:createattachment', $context)) {
                        $value = 0;
                    }
                    break;
                default:
                    throw new moodle_exception('errorinvalidparam', 'webservice', '', $name);
            }
            $options[$name] = $value;
        }

        if (!foreact_user_can_post($foreact, $discussion, $USER, $cm, $course, $context)) {
            throw new moodle_exception('nopostforeact', 'foreact');
        }

        $thresholdwarning = foreact_check_throttling($foreact, $cm);
        foreact_check_blocking_threshold($thresholdwarning);

        // Create the post.
        $post = new stdClass();
        $post->discussion = $discussion->id;
        $post->parent = $parent->id;
        $post->subject = $params['subject'];
        $post->message = $params['message'];
        $post->messageformat = FORMAT_HTML;   // Force formatting for now.
        $post->messagetrust = trusttext_trusted($context);
        $post->itemid = $options['inlineattachmentsid'];
        $post->attachments   = $options['attachmentsid'];
        $fakemform = $post->attachments;
        if ($postid = foreact_add_new_post($post, $fakemform)) {

            $post->id = $postid;

            // Trigger events and completion.
            $params = array(
                'context' => $context,
                'objectid' => $post->id,
                'other' => array(
                    'discussionid' => $discussion->id,
                    'foreactid' => $foreact->id,
                    'foreacttype' => $foreact->type,
                )
            );
            $event = \mod_foreact\event\post_created::create($params);
            $event->add_record_snapshot('foreact_posts', $post);
            $event->add_record_snapshot('foreact_discussions', $discussion);
            $event->trigger();

            // Update completion state.
            $completion = new completion_info($course);
            if ($completion->is_enabled($cm) &&
                    ($foreact->completionreplies || $foreact->completionposts)) {
                $completion->update_state($cm, COMPLETION_COMPLETE);
            }

            $settings = new stdClass();
            $settings->discussionsubscribe = $options['discussionsubscribe'];
            foreact_post_subscription($settings, $foreact, $discussion);
        } else {
            throw new moodle_exception('couldnotadd', 'foreact');
        }

        $result = array();
        $result['postid'] = $postid;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function add_discussion_post_returns() {
        return new external_single_structure(
            array(
                'postid' => new external_value(PARAM_INT, 'new post id'),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.0
     */
    public static function add_discussion_parameters() {
        return new external_function_parameters(
            array(
                'foreactid' => new external_value(PARAM_INT, 'foreact instance ID'),
                'subject' => new external_value(PARAM_TEXT, 'New Discussion subject'),
                'message' => new external_value(PARAM_RAW, 'New Discussion message (only html format allowed)'),
                'groupid' => new external_value(PARAM_INT, 'The group, default to 0', VALUE_DEFAULT, 0),
                'options' => new external_multiple_structure (
                    new external_single_structure(
                        array(
                            'name' => new external_value(PARAM_ALPHANUM,
                                        'The allowed keys (value format) are:
                                        discussionsubscribe (bool); subscribe to the discussion?, default to true
                                        discussionpinned    (bool); is the discussion pinned, default to false
                                        inlineattachmentsid              (int); the draft file area id for inline attachments
                                        attachmentsid       (int); the draft file area id for attachments
                            '),
                            'value' => new external_value(PARAM_RAW, 'The value of the option,
                                                            This param is validated in the external function.'
                        )
                    )
                ), 'Options', VALUE_DEFAULT, array())
            )
        );
    }

    /**
     * Add a new discussion into an existing foreact.
     *
     * @param int $foreactid the foreact instance id
     * @param string $subject new discussion subject
     * @param string $message new discussion message (only html format allowed)
     * @param int $groupid the user course group
     * @param array $options optional settings
     * @return array of warnings and the new discussion id
     * @since Moodle 3.0
     * @throws moodle_exception
     */
    public static function add_discussion($foreactid, $subject, $message, $groupid = 0, $options = array()) {
        global $DB, $CFG;
        require_once($CFG->dirroot . "/mod/foreact/lib.php");

        $params = self::validate_parameters(self::add_discussion_parameters(),
                                            array(
                                                'foreactid' => $foreactid,
                                                'subject' => $subject,
                                                'message' => $message,
                                                'groupid' => $groupid,
                                                'options' => $options
                                            ));

        $warnings = array();

        // Request and permission validation.
        $foreact = $DB->get_record('foreact', array('id' => $params['foreactid']), '*', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($foreact, 'foreact');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        // Validate options.
        $options = array(
            'discussionsubscribe' => true,
            'discussionpinned' => false,
            'inlineattachmentsid' => 0,
            'attachmentsid' => null
        );
        foreach ($params['options'] as $option) {
            $name = trim($option['name']);
            switch ($name) {
                case 'discussionsubscribe':
                    $value = clean_param($option['value'], PARAM_BOOL);
                    break;
                case 'discussionpinned':
                    $value = clean_param($option['value'], PARAM_BOOL);
                    break;
                case 'inlineattachmentsid':
                    $value = clean_param($option['value'], PARAM_INT);
                    break;
                case 'attachmentsid':
                    $value = clean_param($option['value'], PARAM_INT);
                    // Ensure that the user has permissions to create attachments.
                    if (!has_capability('mod/foreact:createattachment', $context)) {
                        $value = 0;
                    }
                    break;
                default:
                    throw new moodle_exception('errorinvalidparam', 'webservice', '', $name);
            }
            $options[$name] = $value;
        }

        // Normalize group.
        if (!groups_get_activity_groupmode($cm)) {
            // Groups not supported, force to -1.
            $groupid = -1;
        } else {
            // Check if we receive the default or and empty value for groupid,
            // in this case, get the group for the user in the activity.
            if (empty($params['groupid'])) {
                $groupid = groups_get_activity_group($cm);
            } else {
                // Here we rely in the group passed, foreact_user_can_post_discussion will validate the group.
                $groupid = $params['groupid'];
            }
        }

        if (!foreact_user_can_post_discussion($foreact, $groupid, -1, $cm, $context)) {
            throw new moodle_exception('cannotcreatediscussion', 'foreact');
        }

        $thresholdwarning = foreact_check_throttling($foreact, $cm);
        foreact_check_blocking_threshold($thresholdwarning);

        // Create the discussion.
        $discussion = new stdClass();
        $discussion->course = $course->id;
        $discussion->foreact = $foreact->id;
        $discussion->message = $params['message'];
        $discussion->messageformat = FORMAT_HTML;   // Force formatting for now.
        $discussion->messagetrust = trusttext_trusted($context);
        $discussion->itemid = $options['inlineattachmentsid'];
        $discussion->groupid = $groupid;
        $discussion->mailnow = 0;
        $discussion->subject = $params['subject'];
        $discussion->name = $discussion->subject;
        $discussion->timestart = 0;
        $discussion->timeend = 0;
        $discussion->attachments = $options['attachmentsid'];

        if (has_capability('mod/foreact:pindiscussions', $context) && $options['discussionpinned']) {
            $discussion->pinned = foreact_DISCUSSION_PINNED;
        } else {
            $discussion->pinned = foreact_DISCUSSION_UNPINNED;
        }
        $fakemform = $options['attachmentsid'];
        if ($discussionid = foreact_add_discussion($discussion, $fakemform)) {

            $discussion->id = $discussionid;

            // Trigger events and completion.

            $params = array(
                'context' => $context,
                'objectid' => $discussion->id,
                'other' => array(
                    'foreactid' => $foreact->id,
                )
            );
            $event = \mod_foreact\event\discussion_created::create($params);
            $event->add_record_snapshot('foreact_discussions', $discussion);
            $event->trigger();

            $completion = new completion_info($course);
            if ($completion->is_enabled($cm) &&
                    ($foreact->completiondiscussions || $foreact->completionposts)) {
                $completion->update_state($cm, COMPLETION_COMPLETE);
            }

            $settings = new stdClass();
            $settings->discussionsubscribe = $options['discussionsubscribe'];
            foreact_post_subscription($settings, $foreact, $discussion);
        } else {
            throw new moodle_exception('couldnotadd', 'foreact');
        }

        $result = array();
        $result['discussionid'] = $discussionid;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.0
     */
    public static function add_discussion_returns() {
        return new external_single_structure(
            array(
                'discussionid' => new external_value(PARAM_INT, 'New Discussion ID'),
                'warnings' => new external_warnings()
            )
        );
    }

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 3.1
     */
    public static function can_add_discussion_parameters() {
        return new external_function_parameters(
            array(
                'foreactid' => new external_value(PARAM_INT, 'foreact instance ID'),
                'groupid' => new external_value(PARAM_INT, 'The group to check, default to active group.
                                                Use -1 to check if the user can post in all the groups.', VALUE_DEFAULT, null)
            )
        );
    }

    /**
     * Check if the current user can add discussions in the given foreact (and optionally for the given group).
     *
     * @param int $foreactid the foreact instance id
     * @param int $groupid the group to check, default to active group. Use -1 to check if the user can post in all the groups.
     * @return array of warnings and the status (true if the user can add discussions)
     * @since Moodle 3.1
     * @throws moodle_exception
     */
    public static function can_add_discussion($foreactid, $groupid = null) {
        global $DB, $CFG;
        require_once($CFG->dirroot . "/mod/foreact/lib.php");

        $params = self::validate_parameters(self::can_add_discussion_parameters(),
                                            array(
                                                'foreactid' => $foreactid,
                                                'groupid' => $groupid,
                                            ));
        $warnings = array();

        // Request and permission validation.
        $foreact = $DB->get_record('foreact', array('id' => $params['foreactid']), '*', MUST_EXIST);
        list($course, $cm) = get_course_and_cm_from_instance($foreact, 'foreact');

        $context = context_module::instance($cm->id);
        self::validate_context($context);

        $status = foreact_user_can_post_discussion($foreact, $params['groupid'], -1, $cm, $context);

        $result = array();
        $result['status'] = $status;
        $result['canpindiscussions'] = has_capability('mod/foreact:pindiscussions', $context);
        $result['cancreateattachment'] = foreact_can_create_attachment($foreact, $context);
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     * @since Moodle 3.1
     */
    public static function can_add_discussion_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_BOOL, 'True if the user can add discussions, false otherwise.'),
                'canpindiscussions' => new external_value(PARAM_BOOL, 'True if the user can pin discussions, false otherwise.',
                    VALUE_OPTIONAL),
                'cancreateattachment' => new external_value(PARAM_BOOL, 'True if the user can add attachments, false otherwise.',
                    VALUE_OPTIONAL),
                'warnings' => new external_warnings()
            )
        );
    }

}
