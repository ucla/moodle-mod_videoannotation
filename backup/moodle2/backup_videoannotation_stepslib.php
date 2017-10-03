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
 * Define all the backup steps that will be used by the backup_videoannotation_activity_task
 *
 * @package   mod_videoannotation
 * @category  backup
 * @copyright 2015 Your Name <your@email.adress>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

/**
 * Define the complete videoannotation structure for backup, with file and id annotations
 *
 * @package   mod_videoannotation
 * @category  backup
 * @copyright 2016 UC Regents
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class backup_videoannotation_activity_structure_step extends backup_activity_structure_step {

    /**
     * Defines the backup structure of the module
     *
     * @return backup_nested_element
     */
    protected function define_structure() {

        // Get know if we are including userinfo.
        $userinfo = $this->get_setting_value('userinfo');

        // Define the root element describing the videoannotation instance.
        $videoannotation = new backup_nested_element('videoannotation', array('id'), array('name', 'intro', 'introformat',
                'clipselect', 'groupmode', 'timecreated', 'timemodified'));
        
        $locks = new backup_nested_element('locks');

        $lock = new backup_nested_element('lock', array('id'), array('userid', 'groupid', 'locktype', 'timecreated',
                'timemodified'));

        $clips = new backup_nested_element('clips');

        $clip = new backup_nested_element('clip', array('id'), array('userid', 'groupid', 'url', 'playabletimestart',
                'playabletimeend', 'videowidth', 'videoheight', 'timecreated', 'timemodified'));

        $submissions = new backup_nested_element('submissions');

        $submission = new backup_nested_element('submission', array('id'), array('userid', 'groupid', 'grade',
                'gradecomment', 'timesubmitted', 'timegraded', 'timecreated', 'timemodified'));

        $tags = new backup_nested_element('tags');

        $tag = new backup_nested_element('tag', array('id'), array('userid', 'groupid', 'name', 'color', 'sortorder', 
                'timecreated', 'timemodified'));

        $events = new backup_nested_element('events');

        $event = new backup_nested_element('event', array('id'), array('userid', 'groupid', 'starttime', 'endtime', 'content',
                'timecreated', 'timemodified', 'latitude', 'longitude', 'scope'));

        // If we had more elements, we would build the tree here.
        $videoannotation->add_child($locks);
        $locks->add_child($lock);
        $videoannotation->add_child($clips);
        $clips->add_child($clip);
        $clip->add_child($submissions);
        $submissions->add_child($submission);
        $clip->add_child($tags);
        $tags->add_child($tag);
        $tag->add_child($events);
        $events->add_child($event);

        // Define data sources.
        $videoannotation->set_source_table('videoannotation', array('id' => backup::VAR_ACTIVITYID));
         
        if ($userinfo) {
            $lock->set_source_table('videoannotation_locks', array('videoannotationid' => backup::VAR_PARENTID));
            $clip->set_source_table('videoannotation_clips', array('videoannotationid' => backup::VAR_PARENTID));
            $submission->set_source_table('videoannotation_submissions', array('videoannotationid' => backup::VAR_PARENTID,
                    'clipid' => '../../id'));
            $tag->set_source_table('videoannotation_tags', array('clipid' => '../../id'));
            $event->set_source_table('videoannotation_events', array('tagid' => '../../id'));
        } else {
            $clip->set_source_sql('SELECT vac.*
                                     FROM {videoannotation_clips} vac
                                     JOIN {videoannotation} va ON va.id = vac.videoannotationid
                                    WHERE va.clipselect = 1
                                          AND vac.videoannotationid = ?',
                                  array(backup::VAR_PARENTID));
        }

        // If we were referring to other tables, we would annotate the relation
        // with the element's annotate_ids() method.
        $lock->annotate_ids('user', 'userid');
        $lock->annotate_ids('group', 'groupid');
        $clip->annotate_ids('user', 'userid');
        $clip->annotate_ids('group', 'groupid');
        $submission->annotate_ids('user', 'userid');
        $submission->annotate_ids('group', 'groupid');
        $tag->annotate_ids('user', 'userid');
        $tag->annotate_ids('group', 'groupid');
        $event->annotate_ids('user', 'userid');
        $event->annotate_ids('group', 'groupid');

        // Define file annotations (we do not use itemid in this example).
        $videoannotation->annotate_files('mod_videoannotation', 'intro', null);

        // Return the root element (videoannotation), wrapped into standard activity structure.
        return $this->prepare_activity_structure($videoannotation);
    }
}
