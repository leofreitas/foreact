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
 * @copyright Jamie Pratt <me@jamiep.org>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once ($CFG->dirroot.'/course/moodleform_mod.php');
require_once('libreactions.php');

class mod_foreact_mod_form extends moodleform_mod {

    function definition() {
        global $CFG, $COURSE, $DB, $PAGE;

        $mform    =& $this->_form;
        
        $record = new stdClass();
        
//-------------------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('foreactname', 'foreact'), array('size'=>'64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');

        $this->standard_intro_elements(get_string('foreactintro', 'foreact'));

        $foreacttypes = foreact_get_foreact_types();
        core_collator::asort($foreacttypes, core_collator::SORT_STRING);
        $mform->addElement('select', 'type', get_string('foreacttype', 'foreact'), $foreacttypes);
        $mform->addHelpButton('type', 'foreacttype', 'foreact');
        $mform->setDefault('type', 'general');
        ////////////////////////////////
        $libreactions = new Reactions();
        $libreactions->add_new_icon();
        $iconoptions = $libreactions->stack_names();

        $stackdefault=$libreactions->get_default_stack($PAGE->activityrecord->id,$iconoptions);

       	$mform->addElement('select', 'iconoptions', get_string('iconoptions', 'foreact'), $iconoptions);
        $mform->addHelpButton('iconoptions', 'iconoptions', 'foreact');
        $mform->setDefault('iconoptions', $stackdefault);
        ///////////////////////////////////
        // Attachments and word count.
        $mform->addElement('header', 'attachmentswordcounthdr', get_string('attachmentswordcount', 'foreact'));

        $choices = get_max_upload_sizes($CFG->maxbytes, $COURSE->maxbytes, 0, $CFG->foreact_maxbytes);
        $choices[1] = get_string('uploadnotallowed');
        $mform->addElement('select', 'maxbytes', get_string('maxattachmentsize', 'foreact'), $choices);
        $mform->addHelpButton('maxbytes', 'maxattachmentsize', 'foreact');
        $mform->setDefault('maxbytes', $CFG->foreact_maxbytes);

        $choices = array(
            0 => 0,
            1 => 1,
            2 => 2,
            3 => 3,
            4 => 4,
            5 => 5,
            6 => 6,
            7 => 7,
            8 => 8,
            9 => 9,
            10 => 10,
            20 => 20,
            50 => 50,
            100 => 100
        );
        $mform->addElement('select', 'maxattachments', get_string('maxattachments', 'foreact'), $choices);
        $mform->addHelpButton('maxattachments', 'maxattachments', 'foreact');
        $mform->setDefault('maxattachments', $CFG->foreact_maxattachments);

        $mform->addElement('selectyesno', 'displaywordcount', get_string('displaywordcount', 'foreact'));
        $mform->addHelpButton('displaywordcount', 'displaywordcount', 'foreact');
        $mform->setDefault('displaywordcount', 0);

        // Subscription and tracking.
        $mform->addElement('header', 'subscriptionandtrackinghdr', get_string('subscriptionandtracking', 'foreact'));

        $options = array();
        $options[foreact_CHOOSESUBSCRIBE] = get_string('subscriptionoptional', 'foreact');
        $options[foreact_FORCESUBSCRIBE] = get_string('subscriptionforced', 'foreact');
        $options[foreact_INITIALSUBSCRIBE] = get_string('subscriptionauto', 'foreact');
        $options[foreact_DISALLOWSUBSCRIBE] = get_string('subscriptiondisabled','foreact');
        $mform->addElement('select', 'forcesubscribe', get_string('subscriptionmode', 'foreact'), $options);
        $mform->addHelpButton('forcesubscribe', 'subscriptionmode', 'foreact');

        $options = array();
        $options[foreact_TRACKING_OPTIONAL] = get_string('trackingoptional', 'foreact');
        $options[foreact_TRACKING_OFF] = get_string('trackingoff', 'foreact');
        if ($CFG->foreact_allowforcedreadtracking) {
            $options[foreact_TRACKING_FORCED] = get_string('trackingon', 'foreact');
        }
        $mform->addElement('select', 'trackingtype', get_string('trackingtype', 'foreact'), $options);
        $mform->addHelpButton('trackingtype', 'trackingtype', 'foreact');
        $default = $CFG->foreact_trackingtype;
        if ((!$CFG->foreact_allowforcedreadtracking) && ($default == foreact_TRACKING_FORCED)) {
            $default = foreact_TRACKING_OPTIONAL;
        }
        $mform->setDefault('trackingtype', $default);

        if ($CFG->enablerssfeeds && isset($CFG->foreact_enablerssfeeds) && $CFG->foreact_enablerssfeeds) {
//-------------------------------------------------------------------------------
            $mform->addElement('header', 'rssheader', get_string('rss'));
            $choices = array();
            $choices[0] = get_string('none');
            $choices[1] = get_string('discussions', 'foreact');
            $choices[2] = get_string('posts', 'foreact');
            $mform->addElement('select', 'rsstype', get_string('rsstype'), $choices);
            $mform->addHelpButton('rsstype', 'rsstype', 'foreact');
            if (isset($CFG->foreact_rsstype)) {
                $mform->setDefault('rsstype', $CFG->foreact_rsstype);
            }

            $choices = array();
            $choices[0] = '0';
            $choices[1] = '1';
            $choices[2] = '2';
            $choices[3] = '3';
            $choices[4] = '4';
            $choices[5] = '5';
            $choices[10] = '10';
            $choices[15] = '15';
            $choices[20] = '20';
            $choices[25] = '25';
            $choices[30] = '30';
            $choices[40] = '40';
            $choices[50] = '50';
            $mform->addElement('select', 'rssarticles', get_string('rssarticles'), $choices);
            $mform->addHelpButton('rssarticles', 'rssarticles', 'foreact');
            $mform->disabledIf('rssarticles', 'rsstype', 'eq', '0');
            if (isset($CFG->foreact_rssarticles)) {
                $mform->setDefault('rssarticles', $CFG->foreact_rssarticles);
            }
        }

        $mform->addElement('header', 'discussionlocking', get_string('discussionlockingheader', 'foreact'));
        $options = [
            0               => get_string('discussionlockingdisabled', 'foreact'),
            1   * DAYSECS   => get_string('numday', 'core', 1),
            1   * WEEKSECS  => get_string('numweek', 'core', 1),
            2   * WEEKSECS  => get_string('numweeks', 'core', 2),
            30  * DAYSECS   => get_string('nummonth', 'core', 1),
            60  * DAYSECS   => get_string('nummonths', 'core', 2),
            90  * DAYSECS   => get_string('nummonths', 'core', 3),
            180 * DAYSECS   => get_string('nummonths', 'core', 6),
            1   * YEARSECS  => get_string('numyear', 'core', 1),
        ];
        $mform->addElement('select', 'lockdiscussionafter', get_string('lockdiscussionafter', 'foreact'), $options);
        $mform->addHelpButton('lockdiscussionafter', 'lockdiscussionafter', 'foreact');
        $mform->disabledIf('lockdiscussionafter', 'type', 'eq', 'single');

//-------------------------------------------------------------------------------
        $mform->addElement('header', 'blockafterheader', get_string('blockafter', 'foreact'));
        $options = array();
        $options[0] = get_string('blockperioddisabled','foreact');
        $options[60*60*24]   = '1 '.get_string('day');
        $options[60*60*24*2] = '2 '.get_string('days');
        $options[60*60*24*3] = '3 '.get_string('days');
        $options[60*60*24*4] = '4 '.get_string('days');
        $options[60*60*24*5] = '5 '.get_string('days');
        $options[60*60*24*6] = '6 '.get_string('days');
        $options[60*60*24*7] = '1 '.get_string('week');
        $mform->addElement('select', 'blockperiod', get_string('blockperiod', 'foreact'), $options);
        $mform->addHelpButton('blockperiod', 'blockperiod', 'foreact');

        $mform->addElement('text', 'blockafter', get_string('blockafter', 'foreact'));
        $mform->setType('blockafter', PARAM_INT);
        $mform->setDefault('blockafter', '0');
        $mform->addRule('blockafter', null, 'numeric', null, 'client');
        $mform->addHelpButton('blockafter', 'blockafter', 'foreact');
        $mform->disabledIf('blockafter', 'blockperiod', 'eq', 0);

        $mform->addElement('text', 'warnafter', get_string('warnafter', 'foreact'));
        $mform->setType('warnafter', PARAM_INT);
        $mform->setDefault('warnafter', '0');
        $mform->addRule('warnafter', null, 'numeric', null, 'client');
        $mform->addHelpButton('warnafter', 'warnafter', 'foreact');
        $mform->disabledIf('warnafter', 'blockperiod', 'eq', 0);

        $coursecontext = context_course::instance($COURSE->id);
        plagiarism_get_form_elements_module($mform, $coursecontext, 'mod_foreact');

//-------------------------------------------------------------------------------

        $this->standard_grading_coursemodule_elements();

        $this->standard_coursemodule_elements();
//-------------------------------------------------------------------------------
// buttons
        $this->add_action_buttons();

    }

    function definition_after_data() {
        parent::definition_after_data();
        $mform     =& $this->_form;
        $type      =& $mform->getElement('type');
        $typevalue = $mform->getElementValue('type');

        //we don't want to have these appear as possible selections in the form but
        //we want the form to display them if they are set.
        if ($typevalue[0]=='news') {
            $type->addOption(get_string('namenews', 'foreact'), 'news');
            $mform->addHelpButton('type', 'namenews', 'foreact');
            $type->freeze();
            $type->setPersistantFreeze(true);
        }
        if ($typevalue[0]=='social') {
            $type->addOption(get_string('namesocial', 'foreact'), 'social');
            $type->freeze();
            $type->setPersistantFreeze(true);
        }

    }

    function data_preprocessing(&$default_values) {
        parent::data_preprocessing($default_values);

        // Set up the completion checkboxes which aren't part of standard data.
        // We also make the default value (if you turn on the checkbox) for those
        // numbers to be 1, this will not apply unless checkbox is ticked.
        $default_values['completiondiscussionsenabled']=
            !empty($default_values['completiondiscussions']) ? 1 : 0;
        if (empty($default_values['completiondiscussions'])) {
            $default_values['completiondiscussions']=1;
        }
        $default_values['completionrepliesenabled']=
            !empty($default_values['completionreplies']) ? 1 : 0;
        if (empty($default_values['completionreplies'])) {
            $default_values['completionreplies']=1;
        }
        // Tick by default if Add mode or if completion posts settings is set to 1 or more.
        if (empty($this->_instance) || !empty($default_values['completionposts'])) {
            $default_values['completionpostsenabled'] = 1;
        } else {
            $default_values['completionpostsenabled'] = 0;
        }
        if (empty($default_values['completionposts'])) {
            $default_values['completionposts']=1;
        }
    }

    /**
     * Add custom completion rules.
     *
     * @return array Array of string IDs of added items, empty array if none
     */
    public function add_completion_rules() {
        $mform =& $this->_form;
        
        $group=array();
        $group[] =& $mform->createElement('checkbox', 'completionpostsenabled', '', get_string('completionposts','foreact'));
        $group[] =& $mform->createElement('text', 'completionposts', '', array('size'=>3));
        $mform->setType('completionposts',PARAM_INT);
        $mform->addGroup($group, 'completionpostsgroup', get_string('completionpostsgroup','foreact'), array(' '), false);
        $mform->disabledIf('completionposts','completionpostsenabled','notchecked');

        $group=array();
        $group[] =& $mform->createElement('checkbox', 'completiondiscussionsenabled', '', get_string('completiondiscussions','foreact'));
        $group[] =& $mform->createElement('text', 'completiondiscussions', '', array('size'=>3));
        $mform->setType('completiondiscussions',PARAM_INT);
        $mform->addGroup($group, 'completiondiscussionsgroup', get_string('completiondiscussionsgroup','foreact'), array(' '), false);
        $mform->disabledIf('completiondiscussions','completiondiscussionsenabled','notchecked');

        $group=array();
        $group[] =& $mform->createElement('checkbox', 'completionrepliesenabled', '', get_string('completionreplies','foreact'));
        $group[] =& $mform->createElement('text', 'completionreplies', '', array('size'=>3));
        $mform->setType('completionreplies',PARAM_INT);
        $mform->addGroup($group, 'completionrepliesgroup', get_string('completionrepliesgroup','foreact'), array(' '), false);
        $mform->disabledIf('completionreplies','completionrepliesenabled','notchecked');

        return array('completiondiscussionsgroup','completionrepliesgroup','completionpostsgroup');
    }

    function completion_rule_enabled($data) {
        return (!empty($data['completiondiscussionsenabled']) && $data['completiondiscussions']!=0) ||
            (!empty($data['completionrepliesenabled']) && $data['completionreplies']!=0) ||
            (!empty($data['completionpostsenabled']) && $data['completionposts']!=0);
    }

    /**
     * Allows module to modify the data returned by form get_data().
     * This method is also called in the bulk activity completion form.
     *
     * Only available on moodleform_mod.
     *
     * @param stdClass $data the form data to be modified.
     */
    public function data_postprocessing($data) {
        parent::data_postprocessing($data);
        // Turn off completion settings if the checkboxes aren't ticked
        if (!empty($data->completionunlocked)) {
            $autocompletion = !empty($data->completion) && $data->completion==COMPLETION_TRACKING_AUTOMATIC;
            if (empty($data->completiondiscussionsenabled) || !$autocompletion) {
                $data->completiondiscussions = 0;
            }
            if (empty($data->completionrepliesenabled) || !$autocompletion) {
                $data->completionreplies = 0;
            }
            if (empty($data->completionpostsenabled) || !$autocompletion) {
                $data->completionposts = 0;
            }
        }
    }
   

}

