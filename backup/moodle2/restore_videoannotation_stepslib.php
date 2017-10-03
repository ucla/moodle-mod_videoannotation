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
 * Define all the restore steps that will be used by the restore_videoannotation_activity_task
 *
 * @package   mod_videoannotation
 * @category  backup
 * @copyright 2016 UC Regents
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Structure step to restore one videoannotation activity
 *
 * @package   mod_videoannotation
 * @category  backup
 * @copyright 2016 UC Regents
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */ 
class restore_videoannotation_activity_structure_step extends restore_activity_structure_step {

    /**
     * Defines structure of path elements to be processed during the restore
     *
     * @return array of {@link restore_path_element}
     */
    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('userinfo');

        $paths[] = new restore_path_element('videoannotation', '/activity/videoannotation');
        $paths[] = new restore_path_element('videoannotation_clip', '/activity/videoannotation/clips/clip');
        if ($userinfo) {
            $paths[] = new restore_path_element('videoannotation_lock', '/activity/videoannotation/locks/lock');
            $paths[] = new restore_path_element('videoannotation_submission', '/activity/videoannotation/clips/clip/submissions/submission');
            $paths[] = new restore_path_element('videoannotation_tag', '/activity/videoannotation/clips/clip/tags/tag');
            $paths[] = new restore_path_element('videoannotation_event', '/activity/videoannotation/clips/clip/tags/tag/events/event');
        }

        // Return the paths wrapped into standard activity structure.
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Process the given restore path element data
     *
     * @param array $data parsed element data
     */
    protected function process_videoannotation($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();
        
        $data->timecreated = time();
        $data->timemodified = time();

        // Create the videoannotation instance.
        $newitemid = $DB->insert_record('videoannotation', $data);
        $this->apply_activity_instance($newitemid);
    }

    protected function process_videoannotation_lock($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        
        $data->videoannotationid = $this->get_new_parentid('videoannotation');
        $data->timecreated = time();
        $data->timemodified = time();

        $newitemid = $DB->insert_record('videoannotation_locks', $data);
    }

    protected function process_videoannotation_clip($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->videoannotationid = $this->get_new_parentid('videoannotation');
        $data->timecreated = time();
        $data->timemodified = time();

        $newitemid = $DB->insert_record('videoannotation_clips', $data);
        $this->set_mapping('videoannotation_clip', $oldid, $newitemid);
    }

    protected function process_videoannotation_submission($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->videoannotationid = $this->get_new_parentid('videoannotation');
        $data->clipid = $this->get_mappingid('videoannotation_clip', $data->clipid);
        $data->timecreated = time();
        $data->timemodified = time();

        $newitemid = $DB->insert_record('videoannotation_submissions', $data);
    }

    protected function process_videoannotation_tag($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->clipid = $this->get_mappingid('videoannotation_clip', $data->clipid);
        $data->timecreated = time();
        $data->timemodified = time();

        $newitemid = $DB->insert_record('videoannotation_tags', $data);
        $this->set_mapping('videoannotation_tag', $oldid, $newitemid);
    }

    protected function process_videoannotation_event($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        $data->tagid = $this->get_mappingid('videoannotation_tag', $data->tagid);
        $data->timecreated = time();
        $data->timemodified = time();

        $newitemid = $DB->insert_record('videoannotation_events', $data);
    }

    /**
     * Post-execution actions
     */
    protected function after_execute() {
        // Add videoannotation related files, no need to match by itemname (just internally handled context).
        $this->add_related_files('mod_videoannotation', 'intro', null);
    }
}
