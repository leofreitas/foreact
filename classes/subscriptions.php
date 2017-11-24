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
 * foreact subscription manager.
 *
 * @package    mod_foreact
 * @copyright  2014 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_foreact;

defined('MOODLE_INTERNAL') || die();

/**
 * foreact subscription manager.
 *
 * @copyright  2014 Andrew Nicols <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class subscriptions {

    /**
     * The status value for an unsubscribed discussion.
     *
     * @var int
     */
    const foreact_DISCUSSION_UNSUBSCRIBED = -1;

    /**
     * The subscription cache for foreacts.
     *
     * The first level key is the user ID
     * The second level is the foreact ID
     * The Value then is bool for subscribed of not.
     *
     * @var array[] An array of arrays.
     */
    protected static $foreactcache = array();

    /**
     * The list of foreacts which have been wholly retrieved for the foreact subscription cache.
     *
     * This allows for prior caching of an entire foreact to reduce the
     * number of DB queries in a subscription check loop.
     *
     * @var bool[]
     */
    protected static $fetchedforeacts = array();

    /**
     * The subscription cache for foreact discussions.
     *
     * The first level key is the user ID
     * The second level is the foreact ID
     * The third level key is the discussion ID
     * The value is then the users preference (int)
     *
     * @var array[]
     */
    protected static $foreactdiscussioncache = array();

    /**
     * The list of foreacts which have been wholly retrieved for the foreact discussion subscription cache.
     *
     * This allows for prior caching of an entire foreact to reduce the
     * number of DB queries in a subscription check loop.
     *
     * @var bool[]
     */
    protected static $discussionfetchedforeacts = array();

    /**
     * Whether a user is subscribed to this foreact, or a discussion within
     * the foreact.
     *
     * If a discussion is specified, then report whether the user is
     * subscribed to posts to this particular discussion, taking into
     * account the foreact preference.
     *
     * If it is not specified then only the foreact preference is considered.
     *
     * @param int $userid The user ID
     * @param \stdClass $foreact The record of the foreact to test
     * @param int $discussionid The ID of the discussion to check
     * @param $cm The coursemodule record. If not supplied, this will be calculated using get_fast_modinfo instead.
     * @return boolean
     */
    public static function is_subscribed($userid, $foreact, $discussionid = null, $cm = null) {
        // If foreact is force subscribed and has allowforcesubscribe, then user is subscribed.
        if (self::is_forcesubscribed($foreact)) {
            if (!$cm) {
                $cm = get_fast_modinfo($foreact->course)->instances['foreact'][$foreact->id];
            }
            if (has_capability('mod/foreact:allowforcesubscribe', \context_module::instance($cm->id), $userid)) {
                return true;
            }
        }

        if ($discussionid === null) {
            return self::is_subscribed_to_foreact($userid, $foreact);
        }

        $subscriptions = self::fetch_discussion_subscription($foreact->id, $userid);

        // Check whether there is a record for this discussion subscription.
        if (isset($subscriptions[$discussionid])) {
            return ($subscriptions[$discussionid] != self::foreact_DISCUSSION_UNSUBSCRIBED);
        }

        return self::is_subscribed_to_foreact($userid, $foreact);
    }

    /**
     * Whether a user is subscribed to this foreact.
     *
     * @param int $userid The user ID
     * @param \stdClass $foreact The record of the foreact to test
     * @return boolean
     */
    protected static function is_subscribed_to_foreact($userid, $foreact) {
        return self::fetch_subscription_cache($foreact->id, $userid);
    }

    /**
     * Helper to determine whether a foreact has it's subscription mode set
     * to forced subscription.
     *
     * @param \stdClass $foreact The record of the foreact to test
     * @return bool
     */
    public static function is_forcesubscribed($foreact) {
        return ($foreact->forcesubscribe == foreact_FORCESUBSCRIBE);
    }

    /**
     * Helper to determine whether a foreact has it's subscription mode set to disabled.
     *
     * @param \stdClass $foreact The record of the foreact to test
     * @return bool
     */
    public static function subscription_disabled($foreact) {
        return ($foreact->forcesubscribe == foreact_DISALLOWSUBSCRIBE);
    }

    /**
     * Helper to determine whether the specified foreact can be subscribed to.
     *
     * @param \stdClass $foreact The record of the foreact to test
     * @return bool
     */
    public static function is_subscribable($foreact) {
        return (isloggedin() && !isguestuser() &&
                !\mod_foreact\subscriptions::is_forcesubscribed($foreact) &&
                !\mod_foreact\subscriptions::subscription_disabled($foreact));
    }

    /**
     * Set the foreact subscription mode.
     *
     * By default when called without options, this is set to foreact_FORCESUBSCRIBE.
     *
     * @param \stdClass $foreact The record of the foreact to set
     * @param int $status The new subscription state
     * @return bool
     */
    public static function set_subscription_mode($foreactid, $status = 1) {
        global $DB;
        return $DB->set_field("foreact", "forcesubscribe", $status, array("id" => $foreactid));
    }

    /**
     * Returns the current subscription mode for the foreact.
     *
     * @param \stdClass $foreact The record of the foreact to set
     * @return int The foreact subscription mode
     */
    public static function get_subscription_mode($foreact) {
        return $foreact->forcesubscribe;
    }

    /**
     * Returns an array of foreacts that the current user is subscribed to and is allowed to unsubscribe from
     *
     * @return array An array of unsubscribable foreacts
     */
    public static function get_unsubscribable_foreacts() {
        global $USER, $DB;

        // Get courses that $USER is enrolled in and can see.
        $courses = enrol_get_my_courses();
        if (empty($courses)) {
            return array();
        }

        $courseids = array();
        foreach($courses as $course) {
            $courseids[] = $course->id;
        }
        list($coursesql, $courseparams) = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'c');

        // Get all foreacts from the user's courses that they are subscribed to and which are not set to forced.
        // It is possible for users to be subscribed to a foreact in subscription disallowed mode so they must be listed
        // here so that that can be unsubscribed from.
        $sql = "SELECT f.id, cm.id as cm, cm.visible, f.course
                FROM {foreact} f
                JOIN {course_modules} cm ON cm.instance = f.id
                JOIN {modules} m ON m.name = :modulename AND m.id = cm.module
                LEFT JOIN {foreact_subscriptions} fs ON (fs.foreact = f.id AND fs.userid = :userid)
                WHERE f.forcesubscribe <> :forcesubscribe
                AND fs.id IS NOT NULL
                AND cm.course
                $coursesql";
        $params = array_merge($courseparams, array(
            'modulename'=>'foreact',
            'userid' => $USER->id,
            'forcesubscribe' => foreact_FORCESUBSCRIBE,
        ));
        $foreacts = $DB->get_recordset_sql($sql, $params);

        $unsubscribableforeacts = array();
        foreach($foreacts as $foreact) {
            if (empty($foreact->visible)) {
                // The foreact is hidden - check if the user can view the foreact.
                $context = \context_module::instance($foreact->cm);
                if (!has_capability('moodle/course:viewhiddenactivities', $context)) {
                    // The user can't see the hidden foreact to cannot unsubscribe.
                    continue;
                }
            }

            $unsubscribableforeacts[] = $foreact;
        }
        $foreacts->close();

        return $unsubscribableforeacts;
    }

    /**
     * Get the list of potential subscribers to a foreact.
     *
     * @param context_module $context the foreact context.
     * @param integer $groupid the id of a group, or 0 for all groups.
     * @param string $fields the list of fields to return for each user. As for get_users_by_capability.
     * @param string $sort sort order. As for get_users_by_capability.
     * @return array list of users.
     */
    public static function get_potential_subscribers($context, $groupid, $fields, $sort = '') {
        global $DB;

        // Only active enrolled users or everybody on the frontpage.
        list($esql, $params) = get_enrolled_sql($context, 'mod/foreact:allowforcesubscribe', $groupid, true);
        if (!$sort) {
            list($sort, $sortparams) = users_order_by_sql('u');
            $params = array_merge($params, $sortparams);
        }

        $sql = "SELECT $fields
                FROM {user} u
                JOIN ($esql) je ON je.id = u.id
            ORDER BY $sort";

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Fetch the foreact subscription data for the specified userid and foreact.
     *
     * @param int $foreactid The foreact to retrieve a cache for
     * @param int $userid The user ID
     * @return boolean
     */
    public static function fetch_subscription_cache($foreactid, $userid) {
        if (isset(self::$foreactcache[$userid]) && isset(self::$foreactcache[$userid][$foreactid])) {
            return self::$foreactcache[$userid][$foreactid];
        }
        self::fill_subscription_cache($foreactid, $userid);

        if (!isset(self::$foreactcache[$userid]) || !isset(self::$foreactcache[$userid][$foreactid])) {
            return false;
        }

        return self::$foreactcache[$userid][$foreactid];
    }

    /**
     * Fill the foreact subscription data for the specified userid and foreact.
     *
     * If the userid is not specified, then all subscription data for that foreact is fetched in a single query and used
     * for subsequent lookups without requiring further database queries.
     *
     * @param int $foreactid The foreact to retrieve a cache for
     * @param int $userid The user ID
     * @return void
     */
    public static function fill_subscription_cache($foreactid, $userid = null) {
        global $DB;

        if (!isset(self::$fetchedforeacts[$foreactid])) {
            // This foreact has not been fetched as a whole.
            if (isset($userid)) {
                if (!isset(self::$foreactcache[$userid])) {
                    self::$foreactcache[$userid] = array();
                }

                if (!isset(self::$foreactcache[$userid][$foreactid])) {
                    if ($DB->record_exists('foreact_subscriptions', array(
                        'userid' => $userid,
                        'foreact' => $foreactid,
                    ))) {
                        self::$foreactcache[$userid][$foreactid] = true;
                    } else {
                        self::$foreactcache[$userid][$foreactid] = false;
                    }
                }
            } else {
                $subscriptions = $DB->get_recordset('foreact_subscriptions', array(
                    'foreact' => $foreactid,
                ), '', 'id, userid');
                foreach ($subscriptions as $id => $data) {
                    if (!isset(self::$foreactcache[$data->userid])) {
                        self::$foreactcache[$data->userid] = array();
                    }
                    self::$foreactcache[$data->userid][$foreactid] = true;
                }
                self::$fetchedforeacts[$foreactid] = true;
                $subscriptions->close();
            }
        }
    }

    /**
     * Fill the foreact subscription data for all foreacts that the specified userid can subscribe to in the specified course.
     *
     * @param int $courseid The course to retrieve a cache for
     * @param int $userid The user ID
     * @return void
     */
    public static function fill_subscription_cache_for_course($courseid, $userid) {
        global $DB;

        if (!isset(self::$foreactcache[$userid])) {
            self::$foreactcache[$userid] = array();
        }

        $sql = "SELECT
                    f.id AS foreactid,
                    s.id AS subscriptionid
                FROM {foreact} f
                LEFT JOIN {foreact_subscriptions} s ON (s.foreact = f.id AND s.userid = :userid)
                WHERE f.course = :course
                AND f.forcesubscribe <> :subscriptionforced";

        $subscriptions = $DB->get_recordset_sql($sql, array(
            'course' => $courseid,
            'userid' => $userid,
            'subscriptionforced' => foreact_FORCESUBSCRIBE,
        ));

        foreach ($subscriptions as $id => $data) {
            self::$foreactcache[$userid][$id] = !empty($data->subscriptionid);
        }
        $subscriptions->close();
    }

    /**
     * Returns a list of user objects who are subscribed to this foreact.
     *
     * @param stdClass $foreact The foreact record.
     * @param int $groupid The group id if restricting subscriptions to a group of users, or 0 for all.
     * @param context_module $context the foreact context, to save re-fetching it where possible.
     * @param string $fields requested user fields (with "u." table prefix).
     * @param boolean $includediscussionsubscriptions Whether to take discussion subscriptions and unsubscriptions into consideration.
     * @return array list of users.
     */
    public static function fetch_subscribed_users($foreact, $groupid = 0, $context = null, $fields = null,
            $includediscussionsubscriptions = false) {
        global $CFG, $DB;

        if (empty($fields)) {
            $allnames = get_all_user_name_fields(true, 'u');
            $fields ="u.id,
                      u.username,
                      $allnames,
                      u.maildisplay,
                      u.mailformat,
                      u.maildigest,
                      u.imagealt,
                      u.email,
                      u.emailstop,
                      u.city,
                      u.country,
                      u.lastaccess,
                      u.lastlogin,
                      u.picture,
                      u.timezone,
                      u.theme,
                      u.lang,
                      u.trackforeacts,
                      u.mnethostid";
        }

        // Retrieve the foreact context if it wasn't specified.
        $context = foreact_get_context($foreact->id, $context);

        if (self::is_forcesubscribed($foreact)) {
            $results = \mod_foreact\subscriptions::get_potential_subscribers($context, $groupid, $fields, "u.email ASC");

        } else {
            // Only active enrolled users or everybody on the frontpage.
            list($esql, $params) = get_enrolled_sql($context, '', $groupid, true);
            $params['foreactid'] = $foreact->id;

            if ($includediscussionsubscriptions) {
                $params['sforeactid'] = $foreact->id;
                $params['dsforeactid'] = $foreact->id;
                $params['unsubscribed'] = self::foreact_DISCUSSION_UNSUBSCRIBED;

                $sql = "SELECT $fields
                        FROM (
                            SELECT userid FROM {foreact_subscriptions} s
                            WHERE
                                s.foreact = :sforeactid
                                UNION
                            SELECT userid FROM {foreact_discussion_subs} ds
                            WHERE
                                ds.foreact = :dsforeactid AND ds.preference <> :unsubscribed
                        ) subscriptions
                        JOIN {user} u ON u.id = subscriptions.userid
                        JOIN ($esql) je ON je.id = u.id
                        ORDER BY u.email ASC";

            } else {
                $sql = "SELECT $fields
                        FROM {user} u
                        JOIN ($esql) je ON je.id = u.id
                        JOIN {foreact_subscriptions} s ON s.userid = u.id
                        WHERE
                          s.foreact = :foreactid
                        ORDER BY u.email ASC";
            }
            $results = $DB->get_records_sql($sql, $params);
        }

        // Guest user should never be subscribed to a foreact.
        unset($results[$CFG->siteguest]);

        // Apply the activity module availability resetrictions.
        $cm = get_coursemodule_from_instance('foreact', $foreact->id, $foreact->course);
        $modinfo = get_fast_modinfo($foreact->course);
        $info = new \core_availability\info_module($modinfo->get_cm($cm->id));
        $results = $info->filter_user_list($results);

        return $results;
    }

    /**
     * Retrieve the discussion subscription data for the specified userid and foreact.
     *
     * This is returned as an array of discussions for that foreact which contain the preference in a stdClass.
     *
     * @param int $foreactid The foreact to retrieve a cache for
     * @param int $userid The user ID
     * @return array of stdClass objects with one per discussion in the foreact.
     */
    public static function fetch_discussion_subscription($foreactid, $userid = null) {
        self::fill_discussion_subscription_cache($foreactid, $userid);

        if (!isset(self::$foreactdiscussioncache[$userid]) || !isset(self::$foreactdiscussioncache[$userid][$foreactid])) {
            return array();
        }

        return self::$foreactdiscussioncache[$userid][$foreactid];
    }

    /**
     * Fill the discussion subscription data for the specified userid and foreact.
     *
     * If the userid is not specified, then all discussion subscription data for that foreact is fetched in a single query
     * and used for subsequent lookups without requiring further database queries.
     *
     * @param int $foreactid The foreact to retrieve a cache for
     * @param int $userid The user ID
     * @return void
     */
    public static function fill_discussion_subscription_cache($foreactid, $userid = null) {
        global $DB;

        if (!isset(self::$discussionfetchedforeacts[$foreactid])) {
            // This foreact hasn't been fetched as a whole yet.
            if (isset($userid)) {
                if (!isset(self::$foreactdiscussioncache[$userid])) {
                    self::$foreactdiscussioncache[$userid] = array();
                }

                if (!isset(self::$foreactdiscussioncache[$userid][$foreactid])) {
                    $subscriptions = $DB->get_recordset('foreact_discussion_subs', array(
                        'userid' => $userid,
                        'foreact' => $foreactid,
                    ), null, 'id, discussion, preference');
                    foreach ($subscriptions as $id => $data) {
                        self::add_to_discussion_cache($foreactid, $userid, $data->discussion, $data->preference);
                    }
                    $subscriptions->close();
                }
            } else {
                $subscriptions = $DB->get_recordset('foreact_discussion_subs', array(
                    'foreact' => $foreactid,
                ), null, 'id, userid, discussion, preference');
                foreach ($subscriptions as $id => $data) {
                    self::add_to_discussion_cache($foreactid, $data->userid, $data->discussion, $data->preference);
                }
                self::$discussionfetchedforeacts[$foreactid] = true;
                $subscriptions->close();
            }
        }
    }

    /**
     * Add the specified discussion and user preference to the discussion
     * subscription cache.
     *
     * @param int $foreactid The ID of the foreact that this preference belongs to
     * @param int $userid The ID of the user that this preference belongs to
     * @param int $discussion The ID of the discussion that this preference relates to
     * @param int $preference The preference to store
     */
    protected static function add_to_discussion_cache($foreactid, $userid, $discussion, $preference) {
        if (!isset(self::$foreactdiscussioncache[$userid])) {
            self::$foreactdiscussioncache[$userid] = array();
        }

        if (!isset(self::$foreactdiscussioncache[$userid][$foreactid])) {
            self::$foreactdiscussioncache[$userid][$foreactid] = array();
        }

        self::$foreactdiscussioncache[$userid][$foreactid][$discussion] = $preference;
    }

    /**
     * Reset the discussion cache.
     *
     * This cache is used to reduce the number of database queries when
     * checking foreact discussion subscription states.
     */
    public static function reset_discussion_cache() {
        self::$foreactdiscussioncache = array();
        self::$discussionfetchedforeacts = array();
    }

    /**
     * Reset the foreact cache.
     *
     * This cache is used to reduce the number of database queries when
     * checking foreact subscription states.
     */
    public static function reset_foreact_cache() {
        self::$foreactcache = array();
        self::$fetchedforeacts = array();
    }

    /**
     * Adds user to the subscriber list.
     *
     * @param int $userid The ID of the user to subscribe
     * @param \stdClass $foreact The foreact record for this foreact.
     * @param \context_module|null $context Module context, may be omitted if not known or if called for the current
     *      module set in page.
     * @param boolean $userrequest Whether the user requested this change themselves. This has an effect on whether
     *     discussion subscriptions are removed too.
     * @return bool|int Returns true if the user is already subscribed, or the foreact_subscriptions ID if the user was
     *     successfully subscribed.
     */
    public static function subscribe_user($userid, $foreact, $context = null, $userrequest = false) {
        global $DB;

        if (self::is_subscribed($userid, $foreact)) {
            return true;
        }

        $sub = new \stdClass();
        $sub->userid  = $userid;
        $sub->foreact = $foreact->id;

        $result = $DB->insert_record("foreact_subscriptions", $sub);

        if ($userrequest) {
            $discussionsubscriptions = $DB->get_recordset('foreact_discussion_subs', array('userid' => $userid, 'foreact' => $foreact->id));
            $DB->delete_records_select('foreact_discussion_subs',
                    'userid = :userid AND foreact = :foreactid AND preference <> :preference', array(
                        'userid' => $userid,
                        'foreactid' => $foreact->id,
                        'preference' => self::foreact_DISCUSSION_UNSUBSCRIBED,
                    ));

            // Reset the subscription caches for this foreact.
            // We know that the there were previously entries and there aren't any more.
            if (isset(self::$foreactdiscussioncache[$userid]) && isset(self::$foreactdiscussioncache[$userid][$foreact->id])) {
                foreach (self::$foreactdiscussioncache[$userid][$foreact->id] as $discussionid => $preference) {
                    if ($preference != self::foreact_DISCUSSION_UNSUBSCRIBED) {
                        unset(self::$foreactdiscussioncache[$userid][$foreact->id][$discussionid]);
                    }
                }
            }
        }

        // Reset the cache for this foreact.
        self::$foreactcache[$userid][$foreact->id] = true;

        $context = foreact_get_context($foreact->id, $context);
        $params = array(
            'context' => $context,
            'objectid' => $result,
            'relateduserid' => $userid,
            'other' => array('foreactid' => $foreact->id),

        );
        $event  = event\subscription_created::create($params);
        if ($userrequest && $discussionsubscriptions) {
            foreach ($discussionsubscriptions as $subscription) {
                $event->add_record_snapshot('foreact_discussion_subs', $subscription);
            }
            $discussionsubscriptions->close();
        }
        $event->trigger();

        return $result;
    }

    /**
     * Removes user from the subscriber list
     *
     * @param int $userid The ID of the user to unsubscribe
     * @param \stdClass $foreact The foreact record for this foreact.
     * @param \context_module|null $context Module context, may be omitted if not known or if called for the current
     *     module set in page.
     * @param boolean $userrequest Whether the user requested this change themselves. This has an effect on whether
     *     discussion subscriptions are removed too.
     * @return boolean Always returns true.
     */
    public static function unsubscribe_user($userid, $foreact, $context = null, $userrequest = false) {
        global $DB;

        $sqlparams = array(
            'userid' => $userid,
            'foreact' => $foreact->id,
        );
        $DB->delete_records('foreact_digests', $sqlparams);

        if ($foreactsubscription = $DB->get_record('foreact_subscriptions', $sqlparams)) {
            $DB->delete_records('foreact_subscriptions', array('id' => $foreactsubscription->id));

            if ($userrequest) {
                $discussionsubscriptions = $DB->get_recordset('foreact_discussion_subs', $sqlparams);
                $DB->delete_records('foreact_discussion_subs',
                        array('userid' => $userid, 'foreact' => $foreact->id, 'preference' => self::foreact_DISCUSSION_UNSUBSCRIBED));

                // We know that the there were previously entries and there aren't any more.
                if (isset(self::$foreactdiscussioncache[$userid]) && isset(self::$foreactdiscussioncache[$userid][$foreact->id])) {
                    self::$foreactdiscussioncache[$userid][$foreact->id] = array();
                }
            }

            // Reset the cache for this foreact.
            self::$foreactcache[$userid][$foreact->id] = false;

            $context = foreact_get_context($foreact->id, $context);
            $params = array(
                'context' => $context,
                'objectid' => $foreactsubscription->id,
                'relateduserid' => $userid,
                'other' => array('foreactid' => $foreact->id),

            );
            $event = event\subscription_deleted::create($params);
            $event->add_record_snapshot('foreact_subscriptions', $foreactsubscription);
            if ($userrequest && $discussionsubscriptions) {
                foreach ($discussionsubscriptions as $subscription) {
                    $event->add_record_snapshot('foreact_discussion_subs', $subscription);
                }
                $discussionsubscriptions->close();
            }
            $event->trigger();
        }

        return true;
    }

    /**
     * Subscribes the user to the specified discussion.
     *
     * @param int $userid The userid of the user being subscribed
     * @param \stdClass $discussion The discussion to subscribe to
     * @param \context_module|null $context Module context, may be omitted if not known or if called for the current
     *     module set in page.
     * @return boolean Whether a change was made
     */
    public static function subscribe_user_to_discussion($userid, $discussion, $context = null) {
        global $DB;

        // First check whether the user is subscribed to the discussion already.
        $subscription = $DB->get_record('foreact_discussion_subs', array('userid' => $userid, 'discussion' => $discussion->id));
        if ($subscription) {
            if ($subscription->preference != self::foreact_DISCUSSION_UNSUBSCRIBED) {
                // The user is already subscribed to the discussion. Ignore.
                return false;
            }
        }
        // No discussion-level subscription. Check for a foreact level subscription.
        if ($DB->record_exists('foreact_subscriptions', array('userid' => $userid, 'foreact' => $discussion->foreact))) {
            if ($subscription && $subscription->preference == self::foreact_DISCUSSION_UNSUBSCRIBED) {
                // The user is subscribed to the foreact, but unsubscribed from the discussion, delete the discussion preference.
                $DB->delete_records('foreact_discussion_subs', array('id' => $subscription->id));
                unset(self::$foreactdiscussioncache[$userid][$discussion->foreact][$discussion->id]);
            } else {
                // The user is already subscribed to the foreact. Ignore.
                return false;
            }
        } else {
            if ($subscription) {
                $subscription->preference = time();
                $DB->update_record('foreact_discussion_subs', $subscription);
            } else {
                $subscription = new \stdClass();
                $subscription->userid  = $userid;
                $subscription->foreact = $discussion->foreact;
                $subscription->discussion = $discussion->id;
                $subscription->preference = time();

                $subscription->id = $DB->insert_record('foreact_discussion_subs', $subscription);
                self::$foreactdiscussioncache[$userid][$discussion->foreact][$discussion->id] = $subscription->preference;
            }
        }

        $context = foreact_get_context($discussion->foreact, $context);
        $params = array(
            'context' => $context,
            'objectid' => $subscription->id,
            'relateduserid' => $userid,
            'other' => array(
                'foreactid' => $discussion->foreact,
                'discussion' => $discussion->id,
            ),

        );
        $event  = event\discussion_subscription_created::create($params);
        $event->trigger();

        return true;
    }
    /**
     * Unsubscribes the user from the specified discussion.
     *
     * @param int $userid The userid of the user being unsubscribed
     * @param \stdClass $discussion The discussion to unsubscribe from
     * @param \context_module|null $context Module context, may be omitted if not known or if called for the current
     *     module set in page.
     * @return boolean Whether a change was made
     */
    public static function unsubscribe_user_from_discussion($userid, $discussion, $context = null) {
        global $DB;

        // First check whether the user's subscription preference for this discussion.
        $subscription = $DB->get_record('foreact_discussion_subs', array('userid' => $userid, 'discussion' => $discussion->id));
        if ($subscription) {
            if ($subscription->preference == self::foreact_DISCUSSION_UNSUBSCRIBED) {
                // The user is already unsubscribed from the discussion. Ignore.
                return false;
            }
        }
        // No discussion-level preference. Check for a foreact level subscription.
        if (!$DB->record_exists('foreact_subscriptions', array('userid' => $userid, 'foreact' => $discussion->foreact))) {
            if ($subscription && $subscription->preference != self::foreact_DISCUSSION_UNSUBSCRIBED) {
                // The user is not subscribed to the foreact, but subscribed from the discussion, delete the discussion subscription.
                $DB->delete_records('foreact_discussion_subs', array('id' => $subscription->id));
                unset(self::$foreactdiscussioncache[$userid][$discussion->foreact][$discussion->id]);
            } else {
                // The user is not subscribed from the foreact. Ignore.
                return false;
            }
        } else {
            if ($subscription) {
                $subscription->preference = self::foreact_DISCUSSION_UNSUBSCRIBED;
                $DB->update_record('foreact_discussion_subs', $subscription);
            } else {
                $subscription = new \stdClass();
                $subscription->userid  = $userid;
                $subscription->foreact = $discussion->foreact;
                $subscription->discussion = $discussion->id;
                $subscription->preference = self::foreact_DISCUSSION_UNSUBSCRIBED;

                $subscription->id = $DB->insert_record('foreact_discussion_subs', $subscription);
            }
            self::$foreactdiscussioncache[$userid][$discussion->foreact][$discussion->id] = $subscription->preference;
        }

        $context = foreact_get_context($discussion->foreact, $context);
        $params = array(
            'context' => $context,
            'objectid' => $subscription->id,
            'relateduserid' => $userid,
            'other' => array(
                'foreactid' => $discussion->foreact,
                'discussion' => $discussion->id,
            ),

        );
        $event  = event\discussion_subscription_deleted::create($params);
        $event->trigger();

        return true;
    }

}
