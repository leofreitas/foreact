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
 * @package    mod_foreact
 * @subpackage backup-moodle2
 * @copyright  2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/foreact/backup/moodle2/restore_foreact_stepslib.php'); // Because it exists (must)

/**
 * foreact restore task that provides all the settings and steps to perform one
 * complete restore of the activity
 */
class restore_foreact_activity_task extends restore_activity_task {

    /**
     * Define (add) particular settings this activity can have
     */
    protected function define_my_settings() {
        // No particular settings for this activity
    }

    /**
     * Define (add) particular steps this activity can have
     */
    protected function define_my_steps() {
        // Choice only has one structure step
        $this->add_step(new restore_foreact_activity_structure_step('foreact_structure', 'foreact.xml'));
    }

    /**
     * Define the contents in the activity that must be
     * processed by the link decoder
     */
    static public function define_decode_contents() {
        $contents = array();

        $contents[] = new restore_decode_content('foreact', array('intro'), 'foreact');
        $contents[] = new restore_decode_content('foreact_posts', array('message'), 'foreact_post');

        return $contents;
    }

    /**
     * Define the decoding rules for links belonging
     * to the activity to be executed by the link decoder
     */
    static public function define_decode_rules() {
        $rules = array();

        // List of foreacts in course
        $rules[] = new restore_decode_rule('FOREACTINDEX', '/mod/foreact/index.php?id=$1', 'course');
        // foreact by cm->id and foreact->id
        $rules[] = new restore_decode_rule('FOREACTVIEWBYID', '/mod/foreact/view.php?id=$1', 'course_module');
        $rules[] = new restore_decode_rule('FOREACTVIEWBYF', '/mod/foreact/view.php?f=$1', 'foreact');
        // Link to foreact discussion
        $rules[] = new restore_decode_rule('FOREACTDISCUSSIONVIEW', '/mod/foreact/discuss.php?d=$1', 'foreact_discussion');
        // Link to discussion with parent and with anchor posts
        $rules[] = new restore_decode_rule('FOREACTDISCUSSIONVIEWPARENT', '/mod/foreact/discuss.php?d=$1&parent=$2',
                                           array('foreact_discussion', 'foreact_post'));
        $rules[] = new restore_decode_rule('FOREACTDISCUSSIONVIEWINSIDE', '/mod/foreact/discuss.php?d=$1#$2',
                                           array('foreact_discussion', 'foreact_post'));

        return $rules;
    }

    /**
     * Define the restore log rules that will be applied
     * by the {@link restore_logs_processor} when restoring
     * foreact logs. It must return one array
     * of {@link restore_log_rule} objects
     */
    static public function define_restore_log_rules() {
        $rules = array();

        $rules[] = new restore_log_rule('foreact', 'add', 'view.php?id={course_module}', '{foreact}');
        $rules[] = new restore_log_rule('foreact', 'update', 'view.php?id={course_module}', '{foreact}');
        $rules[] = new restore_log_rule('foreact', 'view', 'view.php?id={course_module}', '{foreact}');
        $rules[] = new restore_log_rule('foreact', 'view foreact', 'view.php?id={course_module}', '{foreact}');
        $rules[] = new restore_log_rule('foreact', 'mark read', 'view.php?f={foreact}', '{foreact}');
        $rules[] = new restore_log_rule('foreact', 'start tracking', 'view.php?f={foreact}', '{foreact}');
        $rules[] = new restore_log_rule('foreact', 'stop tracking', 'view.php?f={foreact}', '{foreact}');
        $rules[] = new restore_log_rule('foreact', 'subscribe', 'view.php?f={foreact}', '{foreact}');
        $rules[] = new restore_log_rule('foreact', 'unsubscribe', 'view.php?f={foreact}', '{foreact}');
        $rules[] = new restore_log_rule('foreact', 'subscriber', 'subscribers.php?id={foreact}', '{foreact}');
        $rules[] = new restore_log_rule('foreact', 'subscribers', 'subscribers.php?id={foreact}', '{foreact}');
        $rules[] = new restore_log_rule('foreact', 'view subscribers', 'subscribers.php?id={foreact}', '{foreact}');
        $rules[] = new restore_log_rule('foreact', 'add discussion', 'discuss.php?d={foreact_discussion}', '{foreact_discussion}');
        $rules[] = new restore_log_rule('foreact', 'view discussion', 'discuss.php?d={foreact_discussion}', '{foreact_discussion}');
        $rules[] = new restore_log_rule('foreact', 'move discussion', 'discuss.php?d={foreact_discussion}', '{foreact_discussion}');
        $rules[] = new restore_log_rule('foreact', 'delete discussi', 'view.php?id={course_module}', '{foreact}',
                                        null, 'delete discussion');
        $rules[] = new restore_log_rule('foreact', 'delete discussion', 'view.php?id={course_module}', '{foreact}');
        $rules[] = new restore_log_rule('foreact', 'add post', 'discuss.php?d={foreact_discussion}&parent={foreact_post}', '{foreact_post}');
        $rules[] = new restore_log_rule('foreact', 'update post', 'discuss.php?d={foreact_discussion}#p{foreact_post}&parent={foreact_post}', '{foreact_post}');
        $rules[] = new restore_log_rule('foreact', 'update post', 'discuss.php?d={foreact_discussion}&parent={foreact_post}', '{foreact_post}');
        $rules[] = new restore_log_rule('foreact', 'prune post', 'discuss.php?d={foreact_discussion}', '{foreact_post}');
        $rules[] = new restore_log_rule('foreact', 'delete post', 'discuss.php?d={foreact_discussion}', '[post]');

        return $rules;
    }

    /**
     * Define the restore log rules that will be applied
     * by the {@link restore_logs_processor} when restoring
     * course logs. It must return one array
     * of {@link restore_log_rule} objects
     *
     * Note this rules are applied when restoring course logs
     * by the restore final task, but are defined here at
     * activity level. All them are rules not linked to any module instance (cmid = 0)
     */
    static public function define_restore_log_rules_for_course() {
        $rules = array();

        $rules[] = new restore_log_rule('foreact', 'view foreacts', 'index.php?id={course}', null);
        $rules[] = new restore_log_rule('foreact', 'subscribeall', 'index.php?id={course}', '{course}');
        $rules[] = new restore_log_rule('foreact', 'unsubscribeall', 'index.php?id={course}', '{course}');
        $rules[] = new restore_log_rule('foreact', 'user report', 'user.php?course={course}&id={user}&mode=[mode]', '{user}');
        $rules[] = new restore_log_rule('foreact', 'search', 'search.php?id={course}&search=[searchenc]', '[search]');

        return $rules;
    }
}
