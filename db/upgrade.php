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
 * This file keeps track of upgrades to
 * the foreact module
 *
 * Sometimes, changes between versions involve
 * alterations to database structures and other
 * major things that may break installations.
 *
 * The upgrade function in this file will attempt
 * to perform all the necessary actions to upgrade
 * your older installation to the current version.
 *
 * If there's something it cannot do itself, it
 * will tell you what you need to do.
 *
 * The commands in here will all be database-neutral,
 * using the methods of database_manager class
 *
 * Please do not forget to use upgrade_set_timeout()
 * before any action that may take longer time to finish.
 *
 * @package   mod_foreact
 * @copyright 2003 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_foreact_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager(); // Loads ddl manager and xmldb classes.

    if ($oldversion < 2014051201) {

        // Incorrect values that need to be replaced.
        $replacements = array(
            11 => 20,
            12 => 50,
            13 => 100
        );

        // Run the replacements.
        foreach ($replacements as $old => $new) {
            $DB->set_field('foreact', 'maxattachments', $new, array('maxattachments' => $old));
        }

        // foreact savepoint reached.
        upgrade_mod_savepoint(true, 2014051201, 'foreact');
    }

    if ($oldversion < 2014081500) {

        // Define index course (not unique) to be added to foreact_discussions.
        $table = new xmldb_table('foreact_discussions');
        $index = new xmldb_index('course', XMLDB_INDEX_NOTUNIQUE, array('course'));

        // Conditionally launch add index course.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }

        // foreact savepoint reached.
        upgrade_mod_savepoint(true, 2014081500, 'foreact');
    }

    if ($oldversion < 2014081900) {

        // Define table foreact_discussion_subs to be created.
        $table = new xmldb_table('foreact_discussion_subs');

        // Adding fields to table foreact_discussion_subs.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('foreact', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('discussion', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('preference', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');

        // Adding keys to table foreact_discussion_subs.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('foreact', XMLDB_KEY_FOREIGN, array('foreact'), 'foreact', array('id'));
        $table->add_key('userid', XMLDB_KEY_FOREIGN, array('userid'), 'user', array('id'));
        $table->add_key('discussion', XMLDB_KEY_FOREIGN, array('discussion'), 'foreact_discussions', array('id'));
        $table->add_key('user_discussions', XMLDB_KEY_UNIQUE, array('userid', 'discussion'));

        // Conditionally launch create table for foreact_discussion_subs.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // foreact savepoint reached.
        upgrade_mod_savepoint(true, 2014081900, 'foreact');
    }

    if ($oldversion < 2014103000) {
        // Find records with multiple userid/postid combinations and find the lowest ID.
        // Later we will remove all those which don't match this ID.
        $sql = "
            SELECT MIN(id) as lowid, userid, postid
            FROM {foreact_read}
            GROUP BY userid, postid
            HAVING COUNT(id) > 1";

        if ($duplicatedrows = $DB->get_recordset_sql($sql)) {
            foreach ($duplicatedrows as $row) {
                $DB->delete_records_select('foreact_read', 'userid = ? AND postid = ? AND id <> ?', array(
                    $row->userid,
                    $row->postid,
                    $row->lowid,
                ));
            }
        }
        $duplicatedrows->close();

        // foreact savepoint reached.
        upgrade_mod_savepoint(true, 2014103000, 'foreact');
    }

    if ($oldversion < 2014110300) {

        // Changing precision of field preference on table foreact_discussion_subs to (10).
        $table = new xmldb_table('foreact_discussion_subs');
        $field = new xmldb_field('preference', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1', 'discussion');

        // Launch change of precision for field preference.
        $dbman->change_field_precision($table, $field);

        // foreact savepoint reached.
        upgrade_mod_savepoint(true, 2014110300, 'foreact');
    }

    // Moodle v2.8.0 release upgrade line.
    // Put any upgrade step following this.

    // Moodle v2.9.0 release upgrade line.
    // Put any upgrade step following this.
    if ($oldversion < 2015102900) {
        // Groupid = 0 is never valid.
        $DB->set_field('foreact_discussions', 'groupid', -1, array('groupid' => 0));

        // foreact savepoint reached.
        upgrade_mod_savepoint(true, 2015102900, 'foreact');
    }

    // Moodle v3.0.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2015120800) {

        // Add support for pinned discussions.
        $table = new xmldb_table('foreact_discussions');
        $field = new xmldb_field('pinned', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'timeend');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // foreact savepoint reached.
        upgrade_mod_savepoint(true, 2015120800, 'foreact');
    }
    // Moodle v3.1.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2016091200) {

        // Define field lockdiscussionafter to be added to foreact.
        $table = new xmldb_table('foreact');
        $field = new xmldb_field('lockdiscussionafter', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'displaywordcount');

        // Conditionally launch add field lockdiscussionafter.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // foreact savepoint reached.
        upgrade_mod_savepoint(true, 2016091200, 'foreact');
    }

    // Automatically generated Moodle v3.2.0 release upgrade line.
    // Put any upgrade step following this.

    // Automatically generated Moodle v3.3.0 release upgrade line.
    // Put any upgrade step following this.

    if ($oldversion < 2017051608) { //versão atual é maior que a antiga versão?


        
        $table = new xmldb_table('foreact_reactions_type'); //instancia do objeto

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('name', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, '0', 'name');
        $table->add_field('description', XMLDB_TYPE_TEXT, null, null, null, null, '0', 'description');
        $table->add_field('type', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, '0', 'type');
        


        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        
        

        // Conditionally launch add field lockdiscussionafter.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);    
        }

        // foreact savepoint reached.
        upgrade_mod_savepoint(true, 2017051608, 'foreact');
    }

    if ($oldversion < 2017051610) { //versão atual é maior que a antiga versão?


        
        $table = new xmldb_table('foreact_reactions'); //instancia do objeto

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('post', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'id');
        $table->add_field('reaction', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0', 'reaction');
        $table->add_field('user', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0', 'user');
        $table->add_field('vote', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0','vote');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('post', XMLDB_KEY_FOREIGN, array('post'),'foreact_posts', array('id'));
        $table->add_key('reaction', XMLDB_KEY_FOREIGN, array('reaction'),'foreact_reactions_type', array('id'));
        $table->add_key('user', XMLDB_KEY_FOREIGN, array('user'),'user', array('id'));
        

        // Conditionally launch add field lockdiscussionafter.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);    
        }

        // foreact savepoint reached.
        upgrade_mod_savepoint(true, 2017051610, 'foreact');
    }
    if ($oldversion < 2017051614) { //versão atual é maior que a antiga versão?


        
        $tableR = new xmldb_table('foreact_reactions_type'); //instancia do objeto

        $tableR->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $tableR->add_field('type', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, '0', 'type');
        $tableR->add_field('name', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, '0', 'name');
        $tableR->add_field('description', XMLDB_TYPE_TEXT, null, null, null, null, '0', 'description');
        
        
        $tableR->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        
        
        // Conditionally launch add field lockdiscussionafter.
        if (!$dbman->table_exists($tableR)) {
            $dbman->create_table($tableR);    
        }


        $table = new xmldb_table('foreact_reactions_votes'); //instancia do objeto

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('post', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'id');
        $table->add_field('reaction', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0', 'reaction');
        $table->add_field('user', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0', 'user');

        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('post', XMLDB_KEY_FOREIGN, array('post'),'foreact_posts', array('id'));
        $table->add_key('reaction', XMLDB_KEY_FOREIGN, array('reaction'),'foreact_reactions_type', array('id'));
        $table->add_key('user', XMLDB_KEY_FOREIGN, array('user'),'user', array('id'));
        

        // Conditionally launch add field lockdiscussionafter.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);    
        }

        $tableF = new xmldb_table('foreact_reactions'); //instancia do objeto

        $tableF->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $tableF->add_field('foreact', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'foreact');
        $tableF->add_field('reaction', XMLDB_TYPE_INTEGER, '4', null, XMLDB_NOTNULL, null, '0', 'reaction');

        $tableF->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $tableF->add_key('foreact', XMLDB_KEY_FOREIGN, array('foreact'),'foreact', array('id'));
        $tableF->add_key('reaction', XMLDB_KEY_FOREIGN, array('reaction'),'foreact_reactions_type', array('id'));

        

        // Conditionally launch add field lockdiscussionafter.
        if (!$dbman->table_exists($tableF)) {
            $dbman->create_table($tableF);    
        }

        // foreact savepoint reached.
        upgrade_mod_savepoint(true, 2017051614, 'foreact');
    }
    if ($oldversion < 2018010504) { //versão atual é maior que a antiga versão?

        
        $table = new xmldb_table('foreact_stack'); //instancia do objeto

        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('foreact', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'foreact');
        $table->add_field('stack', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, '0', 'stack');
        
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_key('foreact', XMLDB_KEY_FOREIGN, array('foreact'),'foreact', array('id'));


        // Conditionally launch add field lockdiscussionafter.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);    
        }

        // foreact savepoint reached.
        upgrade_mod_savepoint(true, 2018010504, 'foreact');
    }
    

    return true;
}
