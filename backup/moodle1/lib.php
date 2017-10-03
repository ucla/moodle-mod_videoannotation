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
 * @package    mod_videoannotation
 * @copyright  2015 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/mod/videoannotation/lib.php');

/**
 * Videoannotation conversion handler
 */
class moodle1_mod_videoannotation_handler extends moodle1_mod_handler {

    /** @var moodle1_file_manager */
    protected $fileman = null;

    /** @var int cmid */
    protected $moduleid = null;

    private $videoannotationid = null;

    // video annotation ID => clip ID => clip
    private $clips = array();

    // clip ID => tag ID => tag
    private $tags = array();

    // tag ID => event ID => event
    private $events = array();

    // clip ID => submission ID => submission
    private $submissions = array();

    /**
     * Declare the paths in moodle.xml we are able to convert
     *
     * The method returns list of {@link convert_path} instances.
     * For each path returned, the corresponding conversion method must be
     * defined.
     *
     * Note that the paths /MOODLE_BACKUP/COURSE/MODULES/MOD/VIDEOANNOTATION do not
     * actually exist in the file. The last element with the module name was
     * appended by the moodle1_converter class.
     *
     * @return array of {@link convert_path} instances
     */
    public function get_paths() {
        return array(
            new convert_path('videoannotation', '/MOODLE_BACKUP/COURSE/MODULES/MOD/VIDEOANNOTATION'),
            new convert_path('videoannotation_clips', '/MOODLE_BACKUP/COURSE/MODULES/MOD/VIDEOANNOTATION/CLIPS'),
            new convert_path('videoannotation_clip', '/MOODLE_BACKUP/COURSE/MODULES/MOD/VIDEOANNOTATION/CLIPS/CLIP'),
            new convert_path('videoannotation_tags', '/MOODLE_BACKUP/COURSE/MODULES/MOD/VIDEOANNOTATION/TAGS'),
            new convert_path('videoannotation_tag', '/MOODLE_BACKUP/COURSE/MODULES/MOD/VIDEOANNOTATION/TAGS/TAG',
                array(
                    'newfields' => array(
                        'level' => 0,
                    ),
                )
            ),
            new convert_path('videoannotation_events', '/MOODLE_BACKUP/COURSE/MODULES/MOD/VIDEOANNOTATION/EVENTS'),
            new convert_path('videoannotation_event', '/MOODLE_BACKUP/COURSE/MODULES/MOD/VIDEOANNOTATION/EVENTS/EVENT',
                array(
                    'newfields' => array(
                        'longitude' => 0,
                        'latitude' => 0,
                        'scope' => null,
                    ),
                )
            ),
            new convert_path('videoannotation_submissions', '/MOODLE_BACKUP/COURSE/MODULES/MOD/VIDEOANNOTATION/SUBMISSIONS'),
            new convert_path('videoannotation_submission', '/MOODLE_BACKUP/COURSE/MODULES/MOD/VIDEOANNOTATION/SUBMISSIONS/SUBMISSION'),
        );
    }

    /**
     * Translates Moodle 1.x group mode to Moodle 2.x group mode
     */
    private function get_group_mode($moodle1groupmode) {
        switch ($moodle1groupmode) {
            case 3:
                return VIDEOANNOTATION_GROUPMODE_USER_USER;
            case 4:
                return VIDEOANNOTATION_GROUPMODE_GROUP_USER;
            case 5:
                return VIDEOANNOTATION_GROUPMODE_GROUP_GROUP;
            case 6:
                return VIDEOANNOTATION_GROUPMODE_ALL_USER;
            case 7:
                return VIDEOANNOTATION_GROUPMODE_ALL_GROUP;
            case 8:
                return VIDEOANNOTATION_GROUPMODE_ALL_ALL;
            default:
                return $moodle1groupmode;
        }
    }

    /**
     * This is executed when we reach the closing </MOD> tag of our 'videoannotation' path
     */
    public function on_videoannotation_end($data) {
        // resume writing videoannotation.xml

        $this->xmlwriter->begin_tag('clips');
        if (isset($this->clips[$data['id']])) {
            foreach ($this->clips[$data['id']] as $clipid => $clip) {
                $this->xmlwriter->begin_tag('clip', array('id' => $clipid));

                // Write clip

                $this->write_full_tag($clip);

                // Write tags

                $this->xmlwriter->begin_tag('tags');
                if (isset($this->tags[$clipid])) {
                    foreach ($this->tags[$clipid] as $tagid => $tag) {
                        $this->xmlwriter->begin_tag('tag', array('id' => $tagid));

                        // Write tag

                        $this->write_full_tag($tag);

                        // Write events

                        $this->xmlwriter->begin_tag('events');
                        if (isset($this->events[$tagid])) {
                            foreach ($this->events[$tagid] as $eventid => $event) {
                                $this->xmlwriter->begin_tag('event', array('id' => $eventid));

                                // Write event

                                $this->write_full_tag($event);

                                $this->xmlwriter->end_tag('event');
                            }
                        }
                        $this->xmlwriter->end_tag('events');

                        $this->xmlwriter->end_tag('tag');
                    }
                }
                $this->xmlwriter->end_tag('tags');

                // Write submissions

                $this->xmlwriter->begin_tag('submissions');
                if (isset($this->submissions[$clipid])) {
                    foreach ($this->submissions[$clipid] as $submissionid => $submission) {
                        $this->xmlwriter->begin_tag('submission', array('id' => $submissionid));

                        // Write submission

                        $this->write_full_tag($submission);

                        $this->xmlwriter->end_tag('submission');
                    }
                }
                $this->xmlwriter->end_tag('submissions');

                $this->xmlwriter->end_tag('clip');
            }
        }
        $this->xmlwriter->end_tag('clips');

        // finish writing videoannotation.xml
        $this->xmlwriter->end_tag('videoannotation');
        $this->xmlwriter->end_tag('activity');
        $this->close_xml_writer();

        // write inforef.xml
        $this->open_xml_writer("activities/videoannotation_{$this->moduleid}/inforef.xml");
        $this->xmlwriter->begin_tag('inforef');
        $this->xmlwriter->begin_tag('fileref');
        foreach ($this->fileman->get_fileids() as $fileid) {
            $this->write_xml('file', array('id' => $fileid));
        }
        $this->xmlwriter->end_tag('fileref');
        $this->xmlwriter->end_tag('inforef');
        $this->close_xml_writer();
    }

    /**
     * Converts /MOODLE_BACKUP/COURSE/MODULES/MOD/VIDEOANNOTATION data
     */
    public function process_videoannotation($data, $raw) {
        global $CFG;

        // Translate group mode

        if (isset($data['groupmode'])) {
            $data['groupmode'] = $this->get_group_mode($data['groupmode']);
        }

        // get the course module id and context id
        $instanceid     = $data['id'];
        $cminfo         = $this->get_cminfo($instanceid);
        $this->moduleid = $cminfo['id'];
        $contextid      = $this->converter->get_contextid(CONTEXT_MODULE, $this->moduleid);

        // get a fresh new file manager for this instance
        $this->fileman = $this->converter->get_file_manager($contextid, 'mod_videoannotation');

        // convert course files embedded into the intro
        $this->fileman->filearea = 'intro';
        $this->fileman->itemid   = 0;
        $data['intro'] = moodle1_converter::migrate_referenced_files($data['intro'], $this->fileman);

        // Convert the introformat if necessary.
        if ($CFG->texteditors !== 'textarea') {
            $data['intro'] = text_to_html($data['intro'], false, false, true);
            $data['introformat'] = FORMAT_HTML;
        }

        // start writing videoannotation.xml
        $this->open_xml_writer("activities/videoannotation_{$this->moduleid}/videoannotation.xml");
        $this->xmlwriter->begin_tag('activity', array('id' => $instanceid, 'moduleid' => $this->moduleid,
            'modulename' => 'videoannotation', 'contextid' => $contextid));
        $this->xmlwriter->begin_tag('videoannotation', array('id' => $instanceid));

        $this->write_full_tag($data);

        return $data;
    }

    public function process_videoannotation_clip($data) {
        $clip = json_decode(json_encode($data), true);
        $videoannotationid = $clip['videoannotationid'];
        unset($clip['videoannotationid']);
        $clipid = $clip['id'];
        unset($clip['id']);
        $this->clips[$videoannotationid][$clipid] = $clip;
    }

    public function process_videoannotation_clips($data) {
        // Do nothing
        // This function must be defined or the call to convert_path->validate_pobject() will complain
    }

    public function process_videoannotation_event($data) {
        $event = json_decode(json_encode($data), true);
        $tagid = $event['tagid'];
        $eventid = $event['id'];
        $this->events[$tagid][$eventid] = $event;
    }

    public function process_videoannotation_events($data) {
        // Do nothing
        // This function must be defined or the call to convert_path->validate_pobject() will complain
    }

    public function process_videoannotation_submission($data) {
      $submission = json_decode(json_encode($data), true);
      $clipid = $submission['clipid'];
      unset($submission['clipid']);
      $submissionid = $submission['id'];
      unset($submission['id']);
      $this->submissions[$clipid][$submissionid] = $submission;
    }

    public function process_videoannotation_submissions($data) {
        // Do nothing
        // This function must be defined or the call to convert_path->validate_pobject() will complain
    }

    public function process_videoannotation_tag($data) {
        $tag = json_decode(json_encode($data), true);
        $clipid = $tag['clipid'];
        unset($tag['clipid']);
        $tagid = $tag['id'];
        unset($tag['id']);
        $this->tags[$clipid][$tagid] = $tag;
    }

    public function process_videoannotation_tags($data) {
        // Do nothing
        // This function must be defined or the call to convert_path->validate_pobject() will complain
    }

    private function write_full_tag($data) {
        foreach ($data as $field => $value) {
            if ($field <> 'id') {
                $this->xmlwriter->full_tag($field, $value);
            }
        }
    }
}

