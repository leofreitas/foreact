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

/**
 * Define all the restore steps that will be used by the restore_foreact_activity_task
 */

/**
 * Structure step to restore one foreact activity
 */
class restore_foreact_activity_structure_step extends restore_activity_structure_step {

    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('foreact', '/activity/foreact');
        if ($userinfo) {
            $paths[] = new restore_path_element('foreact_discussion', '/activity/foreact/discussions/discussion');
            $paths[] = new restore_path_element('foreact_post', '/activity/foreact/discussions/discussion/posts/post');
            $paths[] = new restore_path_element('foreact_tag', '/activity/foreact/poststags/tag');
            $paths[] = new restore_path_element('foreact_discussion_sub', '/activity/foreact/discussions/discussion/discussion_subs/discussion_sub');
            $paths[] = new restore_path_element('foreact_rating', '/activity/foreact/discussions/discussion/posts/post/ratings/rating');
            $paths[] = new restore_path_element('foreact_subscription', '/activity/foreact/subscriptions/subscription');
            $paths[] = new restore_path_element('foreact_digest', '/activity/foreact/digests/digest');
            $paths[] = new restore_path_element('foreact_read', '/activity/foreact/readposts/read');
            $paths[] = new restore_path_element('foreact_track', '/activity/foreact/trackedprefs/track');
        }

        // Return the paths wrapped into standard activity structure
        return $this->prepare_activity_structure($paths);
    }

    protected function process_foreact($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $data->assesstimestart = $this->apply_date_offset($data->assesstimestart);
        $data->assesstimefinish = $this->apply_date_offset($data->assesstimefinish);
        if ($data->scale < 0) { // scale found, get mapping
            $data->scale = -($this->get_mappingid('scale', abs($data->scale)));
        }

        $newitemid = $DB->insert_record('foreact', $data);
        $this->apply_activity_instance($newitemid);

        // Add current enrolled user subscriptions if necessary.
        $data->id = $newitemid;
        $ctx = context_module::instance($this->task->get_moduleid());
        foreact_instance_created($ctx, $data);
    }

    protected function process_foreact_discussion($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();

        $data->foreact = $this->get_new_parentid('foreact');
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $data->timestart = $this->apply_date_offset($data->timestart);
        $data->timeend = $this->apply_date_offset($data->timeend);
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->groupid = $this->get_mappingid('group', $data->groupid);
        $data->usermodified = $this->get_mappingid('user', $data->usermodified);

        $newitemid = $DB->insert_record('foreact_discussions', $data);
        $this->set_mapping('foreact_discussion', $oldid, $newitemid);
    }

    protected function process_foreact_post($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->discussion = $this->get_new_parentid('foreact_discussion');
        $data->created = $this->apply_date_offset($data->created);
        $data->modified = $this->apply_date_offset($data->modified);
        $data->userid = $this->get_mappingid('user', $data->userid);
        // If post has parent, map it (it has been already restored)
        if (!empty($data->parent)) {
            $data->parent = $this->get_mappingid('foreact_post', $data->parent);
        }

        $newitemid = $DB->insert_record('foreact_posts', $data);
        $this->set_mapping('foreact_post', $oldid, $newitemid, true);

        // If !post->parent, it's the 1st post. Set it in discussion
        if (empty($data->parent)) {
            $DB->set_field('foreact_discussions', 'firstpost', $newitemid, array('id' => $data->discussion));
        }
    }

    protected function process_foreact_tag($data) {
        $data = (object)$data;

        if (!core_tag_tag::is_enabled('mod_foreact', 'foreact_posts')) { // Tags disabled in server, nothing to process.
            return;
        }

        $tag = $data->rawname;
        if (!$itemid = $this->get_mappingid('foreact_post', $data->itemid)) {
            // Some orphaned tag, we could not find the restored post for it - ignore.
            return;
        }

        $context = context_module::instance($this->task->get_moduleid());
        core_tag_tag::add_item_tag('mod_foreact', 'foreact_posts', $itemid, $context, $tag);
    }

    protected function process_foreact_rating($data) {
        global $DB;

        $data = (object)$data;

        // Cannot use ratings API, cause, it's missing the ability to specify times (modified/created)
        $data->contextid = $this->task->get_contextid();
        $data->itemid    = $this->get_new_parentid('foreact_post');
        if ($data->scaleid < 0) { // scale found, get mapping
            $data->scaleid = -($this->get_mappingid('scale', abs($data->scaleid)));
        }
        $data->rating = $data->value;
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);

        // We need to check that component and ratingarea are both set here.
        if (empty($data->component)) {
            $data->component = 'mod_foreact';
        }
        if (empty($data->ratingarea)) {
            $data->ratingarea = 'post';
        }

        $newitemid = $DB->insert_record('rating', $data);
    }

    protected function process_foreact_subscription($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->foreact = $this->get_new_parentid('foreact');
        $data->userid = $this->get_mappingid('user', $data->userid);

        $newitemid = $DB->insert_record('foreact_subscriptions', $data);
        $this->set_mapping('foreact_subscription', $oldid, $newitemid, true);

    }

    protected function process_foreact_discussion_sub($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->discussion = $this->get_new_parentid('foreact_discussion');
        $data->foreact = $this->get_new_parentid('foreact');
        $data->userid = $this->get_mappingid('user', $data->userid);

        $newitemid = $DB->insert_record('foreact_discussion_subs', $data);
        $this->set_mapping('foreact_discussion_sub', $oldid, $newitemid, true);
    }

    protected function process_foreact_digest($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->foreact = $this->get_new_parentid('foreact');
        $data->userid = $this->get_mappingid('user', $data->userid);

        $newitemid = $DB->insert_record('foreact_digests', $data);
    }

    protected function process_foreact_read($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->foreactid = $this->get_new_parentid('foreact');
        $data->discussionid = $this->get_mappingid('foreact_discussion', $data->discussionid);
        $data->postid = $this->get_mappingid('foreact_post', $data->postid);
        $data->userid = $this->get_mappingid('user', $data->userid);

        $newitemid = $DB->insert_record('foreact_read', $data);
    }

    protected function process_foreact_track($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->foreactid = $this->get_new_parentid('foreact');
        $data->userid = $this->get_mappingid('user', $data->userid);

        $newitemid = $DB->insert_record('foreact_track_prefs', $data);
    }

    protected function after_execute() {
        // Add foreact related files, no need to match by itemname (just internally handled context)
        $this->add_related_files('mod_foreact', 'intro', null);

        // Add post related files, matching by itemname = 'foreact_post'
        $this->add_related_files('mod_foreact', 'post', 'foreact_post');
        $this->add_related_files('mod_foreact', 'attachment', 'foreact_post');
    }

    protected function after_restore() {
        global $DB;

        // If the foreact is of type 'single' and no discussion has been ignited
        // (non-userinfo backup/restore) create the discussion here, using foreact
        // information as base for the initial post.
        $foreactid = $this->task->get_activityid();
        $foreactrec = $DB->get_record('foreact', array('id' => $foreactid));
        if ($foreactrec->type == 'single' && !$DB->record_exists('foreact_discussions', array('foreact' => $foreactid))) {
            // Create single discussion/lead post from foreact data
            $sd = new stdClass();
            $sd->course   = $foreactrec->course;
            $sd->foreact    = $foreactrec->id;
            $sd->name     = $foreactrec->name;
            $sd->assessed = $foreactrec->assessed;
            $sd->message  = $foreactrec->intro;
            $sd->messageformat = $foreactrec->introformat;
            $sd->messagetrust  = true;
            $sd->mailnow  = false;
            $sdid = foreact_add_discussion($sd, null, null, $this->task->get_userid());
            // Mark the post as mailed
            $DB->set_field ('foreact_posts','mailed', '1', array('discussion' => $sdid));
            // Copy all the files from mod_foum/intro to mod_foreact/post
            $fs = get_file_storage();
            $files = $fs->get_area_files($this->task->get_contextid(), 'mod_foreact', 'intro');
            foreach ($files as $file) {
                $newfilerecord = new stdClass();
                $newfilerecord->filearea = 'post';
                $newfilerecord->itemid   = $DB->get_field('foreact_discussions', 'firstpost', array('id' => $sdid));
                $fs->create_file_from_storedfile($newfilerecord, $file);
            }
        }
    }
}
