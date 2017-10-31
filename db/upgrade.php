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


// This file keeps track of upgrades to
// the videoannotation module
//
// Sometimes, changes between versions involve
// alterations to database structures and other
// major things that may break installations.
//
// The upgrade function in this file will attempt
// to perform all the necessary actions to upgrade
// your older installtion to the current version.
//
// If there's something it cannot do itself, it
// will tell you what you need to do.
//
// The commands in here will all be database-neutral,
// using the functions defined in lib/ddllib.php.

defined('MOODLE_INTERNAL') || die();

function xmldb_videoannotation_upgrade($oldversion) {

    global $CFG, $DB;

    $dbman = $DB->get_manager(); // Loads ddl manager and xmldb classes.

    if ($oldversion < 2012051600) {

        // Define field groupmode to be added to videoannotation.
        $table = new xmldb_table('videoannotation');
        $field = new xmldb_field('groupmode');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '2', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0', 'clipselect');

        // Launch add field groupmode.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
    }

    if ($oldversion < 2012051600) {

        // Define field groupid to be added to videoannotation_clips.
        $table = new xmldb_table('videoannotation_clips');
        $field = new xmldb_field('groupid');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null, null, null, 'userid');

        // Launch add field groupid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
    }

    if ($oldversion < 2012051600) {

        // Define index videoannotationid_userid_groupid (not unique) to be added to videoannotation_clips.
        $table = new xmldb_table('videoannotation_clips');
        $index = new xmldb_index('videoannotationid_userid_groupid');
        $index->set_attributes(XMLDB_INDEX_NOTUNIQUE, array('videoannotationid', 'userid', 'groupid'));

        // Launch add index videoannotationid_userid_groupid.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
    }

    if ($oldversion < 2012051600) {

        // Changing type of field userid on table videoannotation_events to int.
        $table = new xmldb_table('videoannotation_events');
        $field = new xmldb_field('userid');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null, null, null, 'tagid');

        // Launch change of type for field userid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->change_field_type($table, $field);
        }
    }

    if ($oldversion < 2012051600) {

        // Define field groupid to be added to videoannotation_events.
        $table = new xmldb_table('videoannotation_events');
        $field = new xmldb_field('groupid');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null, null, null, 'userid');

        // Launch add field groupid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
    }

    if ($oldversion < 2012051600) {

        // Define index tagid_userid_groupid (not unique) to be added to videoannotation_events.
        $table = new xmldb_table('videoannotation_events');
        $index = new xmldb_index('tagid_userid_groupid');
        $index->set_attributes(XMLDB_INDEX_NOTUNIQUE, array('tagid', 'userid', 'groupid'));

        // Launch add index tagid_userid_groupid.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
    }

    if ($oldversion < 2012051600) {

        // Define table videoannotation_locks to be created.
        $table = new xmldb_table('videoannotation_locks');

        // Adding fields to table videoannotation_locks.
        $table->addFieldInfo('id', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, XMLDB_SEQUENCE, null, null, null);
        $table->addFieldInfo('videoannotationid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0');
        $table->addFieldInfo('userid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null, null, null);
        $table->addFieldInfo('groupid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null, null, null);
        $table->addFieldInfo('locktype', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, null, null, '0');
        $table->addFieldInfo('timecreated', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null, null, null);
        $table->addFieldInfo('timemodified', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null, null, null);

        // Adding keys to table videoannotation_locks.
        $table->addKeyInfo('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Adding indexes to table videoannotation_locks.
        $table->addIndexInfo('videoannotationid_userid_groupid', XMLDB_INDEX_NOTUNIQUE, array('videoannotationid', 'userid', 'groupid'));

        // Launch create table for videoannotation_locks.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
    }

    if ($oldversion < 2012051600) {

        // Changing type of field userid on table videoannotation_submissions to int.
        $table = new xmldb_table('videoannotation_submissions');
        $field = new xmldb_field('userid');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null, null, null, 'videoannotationid');

        // Launch change of type for field userid.
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_type($table, $field);
        }
    }

    if ($oldversion < 2012051600) {

        // Define field groupid to be added to videoannotation_submissions.
        $table = new xmldb_table('videoannotation_submissions');
        $field = new xmldb_field('groupid');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null, null, null, 'userid');

        // Launch add field groupid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
    }

    if ($oldversion < 2012051600) {

        // Define index videoannotationid_userid_groupid (not unique) to be dropped form videoannotation_submissions.
        $table = new xmldb_table('videoannotation_submissions');
        $index = new xmldb_index('videoannotationid_userid_groupid');
        $index->set_attributes(XMLDB_INDEX_NOTUNIQUE, array('videoannotationid', 'userid', 'groupid'));

        // Launch drop index videoannotationid_userid_groupid.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }
    }

    if ($oldversion < 2012051600) {

        // Define index videoannotationid_userid_groupid (not unique) to be added to videoannotation_submissions.
        $table = new xmldb_table('videoannotation_submissions');
        $index = new xmldb_index('videoannotationid_userid_groupid');
        $index->set_attributes(XMLDB_INDEX_NOTUNIQUE, array('videoannotationid', 'userid', 'groupid'));

        // Launch add index videoannotationid_userid_groupid.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
    }

    if ($oldversion < 2012051600) {

        // Changing type of field userid on table videoannotation_tags to int.
        $table = new xmldb_table('videoannotation_tags');
        $field = new xmldb_field('userid');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null, null, null, 'clipid');

        // Launch change of type for field userid.
        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_type($table, $field);
        }
    }

    if ($oldversion < 2012051600) {

        // Define field groupid to be added to videoannotation_tags.
        $table = new xmldb_table('videoannotation_tags');
        $field = new xmldb_field('groupid');
        $field->set_attributes(XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, null, null, null, null, null, 'userid');

        // Launch add field groupid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
    }

    if ($oldversion < 2012051600) {

        // Define index clipid_userid_groupid (not unique) to be dropped form videoannotation_tags.
        $table = new xmldb_table('videoannotation_tags');
        $index = new xmldb_index('clipid_userid_groupid');
        $index->set_attributes(XMLDB_INDEX_NOTUNIQUE, array('clipid', 'userid', 'groupid'));

        // Launch drop index clipid_userid_groupid.
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }
    }
    if ($oldversion < 2012051600) {

        // Define index clipid_userid_groupid (not unique) to be added to videoannotation_tags.
        $table = new xmldb_table('videoannotation_tags');
        $index = new xmldb_index('clipid_userid_groupid');
        $index->set_attributes(XMLDB_INDEX_NOTUNIQUE, array('clipid', 'userid', 'groupid'));

        // Launch add index clipid_userid_groupid.
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
    }

    if ($oldversion < 2014040800) {

        // Define field latitude to be added to videoannotation_events.
        $table = new xmldb_table('videoannotation_events');
        $field = new xmldb_field('latitude');
        $field->set_attributes(XMLDB_TYPE_NUMBER, '10, 7', null, null, null, null, null, null, 'timemodified');

        // Launch add field latitude.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
    }
    if ($oldversion < 2014040800) {

        // Define field longitude to be added to videoannotation_events.
        $table = new xmldb_table('videoannotation_events');
        $field = new xmldb_field('longitude');
        $field->set_attributes(XMLDB_TYPE_NUMBER, '10, 7', null, null, null, null, null, null, 'latitude');

        // Launch add field longitude.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
    }

    if ($oldversion < 2014040800) {

        // Define field scope to be added to videoannotation_events.
        $table = new xmldb_table('videoannotation_events');
        $field = new xmldb_field('scope');
        $field->set_attributes(XMLDB_TYPE_CHAR, '50', null, null, null, null, null, null, 'timemodified');

        // Launch add field scope.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
    }

    if ($oldversion < 2017022400) {

        // Define field level to be added to videoannotation_events.
        $table = new xmldb_table('videoannotation_events');
        $field = new xmldb_field('level', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0', 'scope');

        // Launch add field level.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Change precision of field latitude.
        $field = new xmldb_field('latitude', XMLDB_TYPE_NUMBER, '10, 5', null, null, null, '0', 'timemodified');

        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_precision($table, $field);
        }

        // Change precision of field longitude.
        $field = new xmldb_field('longitude', XMLDB_TYPE_NUMBER, '10, 5', null, null, null, '0', 'timemodified');

        if ($dbman->field_exists($table, $field)) {
            $dbman->change_field_precision($table, $field);
        }

        // Define field level to be added to videoannotation_tags.
        $table = new xmldb_table('videoannotation_tags');
        $field = new xmldb_field('level', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1', 'timemodified');

        // Launch add field level.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_mod_savepoint(true, 2017022400, 'videoannotation');
    }

    return true;
}
