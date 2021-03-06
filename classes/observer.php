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
 * Event observers used in foreact.
 *
 * @package    mod_foreact
 * @copyright  2013 Rajesh Taneja <rajesh@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Event observer for mod_foreact.
 */
class mod_foreact_observer {

    /**
     * Triggered via user_enrolment_deleted event.
     *
     * @param \core\event\user_enrolment_deleted $event
     */
    public static function user_enrolment_deleted(\core\event\user_enrolment_deleted $event) {
        global $DB;

        // NOTE: this has to be as fast as possible.
        // Get user enrolment info from event.
        $cp = (object)$event->other['userenrolment'];
        if ($cp->lastenrol) {
            if (!$foreacts = $DB->get_records('foreact', array('course' => $cp->courseid), '', 'id')) {
                return;
            }
            list($foreactselect, $params) = $DB->get_in_or_equal(array_keys($foreacts), SQL_PARAMS_NAMED);
            $params['userid'] = $cp->userid;

            $DB->delete_records_select('foreact_digests', 'userid = :userid AND foreact '.$foreactselect, $params);
            $DB->delete_records_select('foreact_subscriptions', 'userid = :userid AND foreact '.$foreactselect, $params);
            $DB->delete_records_select('foreact_track_prefs', 'userid = :userid AND foreactid '.$foreactselect, $params);
            $DB->delete_records_select('foreact_read', 'userid = :userid AND foreactid '.$foreactselect, $params);
        }
    }

    /**
     * Observer for role_assigned event.
     *
     * @param \core\event\role_assigned $event
     * @return void
     */
    public static function role_assigned(\core\event\role_assigned $event) {
        global $CFG, $DB;

        $context = context::instance_by_id($event->contextid, MUST_EXIST);

        // If contextlevel is course then only subscribe user. Role assignment
        // at course level means user is enroled in course and can subscribe to foreact.
        if ($context->contextlevel != CONTEXT_COURSE) {
            return;
        }

        // foreact lib required for the constant used below.
        require_once($CFG->dirroot . '/mod/foreact/lib.php');

        $userid = $event->relateduserid;
        $sql = "SELECT f.id, f.course as course, cm.id AS cmid, f.forcesubscribe
                  FROM {foreact} f
                  JOIN {course_modules} cm ON (cm.instance = f.id)
                  JOIN {modules} m ON (m.id = cm.module)
             LEFT JOIN {foreact_subscriptions} fs ON (fs.foreact = f.id AND fs.userid = :userid)
                 WHERE f.course = :courseid
                   AND f.forcesubscribe = :initial
                   AND m.name = 'foreact'
                   AND fs.id IS NULL";
        $params = array('courseid' => $context->instanceid, 'userid' => $userid, 'initial' => foreact_INITIALSUBSCRIBE);

        $foreacts = $DB->get_records_sql($sql, $params);
        foreach ($foreacts as $foreact) {
            // If user doesn't have allowforcesubscribe capability then don't subscribe.
            $modcontext = context_module::instance($foreact->cmid);
            if (has_capability('mod/foreact:allowforcesubscribe', $modcontext, $userid)) {
                \mod_foreact\subscriptions::subscribe_user($userid, $foreact, $modcontext);
            }
        }
    }

    /**
     * Observer for \core\event\course_module_created event.
     *
     * @param \core\event\course_module_created $event
     * @return void
     */
    public static function course_module_created(\core\event\course_module_created $event) {
        global $CFG;

        if ($event->other['modulename'] === 'foreact') {
            // Include the foreact library to make use of the foreact_instance_created function.
            require_once($CFG->dirroot . '/mod/foreact/lib.php');

            $foreact = $event->get_record_snapshot('foreact', $event->other['instanceid']);
            foreact_instance_created($event->get_context(), $foreact);
        }
    }

    /**
     * Observer for \core\event\course_created event.
     *
     * @param \core\event\course_created $event
     * @return void
     */
    public static function course_created(\core\event\course_created $event) {
        global $CFG;

        $course = $event->get_record_snapshot('course', $event->objectid);
        $format = course_get_format($course);
        if ($format->supports_news() && !empty($course->newsitems)) {
            require_once($CFG->dirroot . '/mod/foreact/lib.php');
            // Disable Auto create the announcements foreact.
            //foreact_get_course_foreact($event->objectid, 'news');
        }
    }

    /**
     * Observer for \core\event\course_updated event.
     *
     * @param \core\event\course_updated $event
     * @return void
     */
    public static function course_updated(\core\event\course_updated $event) {
        global $CFG;

        $course = $event->get_record_snapshot('course', $event->objectid);
        $format = course_get_format($course);
        if ($format->supports_news() && !empty($course->newsitems)) {
            require_once($CFG->dirroot . '/mod/foreact/lib.php');
            // Disable create the announcements foreact.
            //foreact_get_course_foreact($event->objectid, 'news');
        }
    }
}
