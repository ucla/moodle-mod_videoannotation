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
 * Library of functions and constants for module videoannotation
 * This file should have two well differenced parts:
 *   - All the core Moodle functions, neeeded to allow
 *     the module to work integrated in Moodle.
 *   - All the videoannotation specific functions, needed
 *     to implement all the module logic. Please, note
 *     that, if the module become complex and this lib
 *     grows a lot, it's HIGHLY recommended to move all
 *     these module specific functions to a new php file,
 *     called "locallib.php" (see forum, quiz...). This will
 *     help to save some memory when Moodle is performing
 *     actions across all modules.
 */

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot . '/mod/videoannotation/locallib.php');

define('VIDEOANNOTATION_GROUPMODE_USER_USER', 3);
define('VIDEOANNOTATION_GROUPMODE_GROUP_USER', 2);
define('VIDEOANNOTATION_GROUPMODE_GROUP_GROUP', 1);
define('VIDEOANNOTATION_GROUPMODE_ALL_USER', 6);
define('VIDEOANNOTATION_GROUPMODE_ALL_GROUP', 7);
define('VIDEOANNOTATION_GROUPMODE_ALL_ALL', 8);

/**
 * Returns the information on whether the module supports a feature
 *
 * See {@link plugin_supports()} for more info.
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed True if module supports feature, null if doesn't know
 */
function videoannotation_supports($feature) {
    switch($feature) {
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_CONTROLS_GRADE_VISIBILITY:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_IDNUMBER:
            return true;
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_GROUPMEMBERSONLY:
            return true;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_COMMENT:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        default:
            return null;
    }
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param object $videoannotation An object from the form in mod_form.php
 * @return int The id of the newly inserted videoannotation record
 */
function videoannotation_add_instance($videoannotation) {
    global $DB;
    $videoannotation->timecreated = time();
    $videoannotation->timemodified = time();
    $videoannotation->id = $DB->insert_record('videoannotation', $videoannotation);
    if (!$videoannotation->id) {
        return false;
    }

    if ($videoannotation->clipselect == 1) {
        $clip = new stdClass();
        $clip->videoannotationid = $videoannotation->id;
        $clip->userid = null;
        $clip->url = $videoannotation->clipurl;
        $clip->playabletimestart = $videoannotation->playabletimestart;
        $clip->playabletimeend = $videoannotation->playabletimeend;
        $clip->videowidth = $videoannotation->videowidth;
        $clip->videoheight = $videoannotation->videoheight;

        $clip->timecreated = time();
        $clip->timemodified = time();
        $clipid = $DB->insert_record('videoannotation_clips', $clip);
        if (!$clipid) {
            return false;
        }
    }

    // Set groupmode field of the record in mdl_course_modules table.
    // Valid values are: NOGROUPS (group mode off) or SEPARATEGROUPS (group mode on) or VISIBLEGROUPS (group mode on visible).
    $coursemodule = videoannotation_get_course_module_by_video_annotation($videoannotation->id);
    if ($videoannotation->groupmode == 0) {
        $gm = NOGROUPS;
        $videoannotation->groupmode = NOGROUPS;
    } else if ($videoannotation->groupmode == 1) {
        $gm = SEPARATEGROUPS;
        $videoannotation->groupmode = SEPARATEGROUPS;
    } else {
        $gm = VISIBLEGROUPS;
        $videoannotation->groupmode = VISIBLEGROUPS;
    }
    $DB->set_field('course_modules', 'groupmode', $gm, array('instance' => $videoannotation->id));

    return $videoannotation->id;
}

/**
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param object $videoannotation An object from the form in mod_form.php
 * @return boolean Success/Fail
 */
function videoannotation_update_instance($videoannotation) {
    global $DB;
    $videoannotation->timemodified = time();
    $videoannotation->id = $videoannotation->instance;
    if (!$DB->update_record('videoannotation', $videoannotation)) {
        return false;
    }

    if ($videoannotation->clipselect == 1) {
        $clip = new stdClass();
        $clip->videoannotationid = $videoannotation->instance;
        $clip->userid = null;
        $clip->url = $videoannotation->clipurl;
        $clip->playabletimestart = $videoannotation->playabletimestart;
        $clip->playabletimeend = $videoannotation->playabletimeend;
        $clip->videowidth = $videoannotation->videowidth;
        $clip->videoheight = $videoannotation->videoheight;

        if ($clipid = $DB->get_field('videoannotation_clips', 'id', array(
            'videoannotationid' => $videoannotation->instance, 'userid' => null))) {
            $clip->id = $clipid;
            $clip->timemodified = time();
            if (!$DB->update_record('videoannotation_clips', $clip)) {
                return false;
            }
        } else {
            $clip->timecreated = time();
            $clip->timemodified = time();
            if (!($clipid = $DB->insert_record('videoannotation_clips', $clip))) {
                return false;
            }
        }
    }

    // Set groupmode field of the record in mdl_course_modules table.
    // Valid values are: NOGROUPS (group mode off) or SEPARATEGROUPS (group mode on) or VISIBLEGROUPS (group mode on visible).
    $coursemodule = videoannotation_get_course_module_by_video_annotation($videoannotation->id);
    if ($videoannotation->groupmode == 0) {
        $gm = NOGROUPS;
        $videoannotation->groupmode = NOGROUPS;
    } else if ($videoannotation->groupmode == 1) {
        $gm = SEPARATEGROUPS;
        $videoannotation->groupmode = SEPARATEGROUPS;
    } else {
        $gm = VISIBLEGROUPS;
        $videoannotation->groupmode = VISIBLEGROUPS;
    }
    $DB->set_field('course_modules', 'groupmode', $gm, array('instance' => $videoannotation->id));

    return true;
}

/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 */
function videoannotation_delete_instance($id) {
    global $CFG, $DB;
    if (!$videoannotation = $DB->get_record('videoannotation', array('id' => $id))) {
        return false;
    }
    $result = true;

    // Delete any dependent records here.
    // Delete events.
    $sql = "DELETE ve
              FROM {videoannotation_clips} vc
              JOIN {videoannotation_tags} vt ON vc.id = vt.clipid
              JOIN {videoannotation_events} ve ON vt.id = ve.tagid
             WHERE vc.videoannotationid =" . $videoannotation->id;

    if (!$DB->execute($sql)) {
        $result = false;
    }

    // Delete tags.
    $sql = "DELETE vt
              FROM {videoannotation_clips} vc
              JOIN {videoannotation_tags} vt ON vc.id = vt.clipid
             WHERE vc.videoannotationid =" . $videoannotation->id;
    if (!$DB->execute($sql)) {
        $result = false;
    }

    // Delete module, clips, locks, submissions.
    // Each operation can be done independently.
    if (!$DB->delete_records('videoannotation_clips', array('videoannotationid' => $videoannotation->id))) {
        $result = false;
    }

    if (!$DB->delete_records('videoannotation_locks', array('videoannotationid' => $videoannotation->id))) {
        $result = false;
    }

    if (!$DB->delete_records('videoannotation_submissions', array('videoannotationid' => $videoannotation->id))) {
        $result = false;
    }

    // Delete grades.
    videoannotation_grade_item_delete($videoannotation);
    if (!$DB->delete_records('videoannotation', array('id' => $videoannotation->id))) {
        $result = false;
    }

    return $result;
}

/**
 * Delete grade item for given videoannotation
 *
 * @param object $videoannotation object
 * @return object videoannotation
 */
function videoannotation_grade_item_delete($va) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');
    return grade_update('mod/videoannotation', $va->course, 'mod', 'videoannotation', $va->id, 0, null, array('deleted' => 1));
}

/**
 * Return a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @return null
 * @todo Finish documenting this function
 */
function videoannotation_user_outline($course, $user, $mod, $videoannotation) {
    return $return;
}

/**
 * Print a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @return boolean
 * @todo Finish documenting this function
 */
function videoannotation_user_complete($course, $user, $mod, $videoannotation) {
    return true;
}

/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in videoannotation activities and print it out.
 * Return true if there was output, or false is there was none.
 *
 * @return boolean
 * @todo Finish documenting this function
 */
function videoannotation_print_recent_activity($course, $isteacher, $timestart) {
    return false;  // True if anything was printed, otherwise false.
}

/**
 * Function to be run periodically according to the moodle cron
 * This function searches for things that need to be done, such
 * as sending out mail, toggling flags etc ...
 *
 * @return boolean
 * @todo Finish documenting this function
 **/
function videoannotation_cron () {
    return true;
}

/**
 * Must return an array of user records (all data) who are participants
 * for a given instance of videoannotation. Must include every user involved
 * in the instance, independient of his role (student, teacher, admin...)
 * See other modules as example.
 *
 * @param int $videoannotationid ID of an instance of this module
 * @return mixed boolean/array of students
 */
function videoannotation_get_participants($videoannotationid) {
    return false;
}

/**
 * This function returns if a scale is being used by one videoannotation
 * if it has support for grading and scales. Commented code should be
 * modified if necessary. See forum, glossary or journal modules
 * as reference.
 *
 * @param int $videoannotationid ID of an instance of this module
 * @return mixed
 * @todo Finish documenting this function
 */
function videoannotation_scale_used($videoannotationid, $scaleid) {
    $return = false;

    // $rec = get_record("videoannotation","id","$videoannotationid","scale","-$scaleid");
    //
    // if (!empty($rec) && !empty($scaleid)) {
    //    $return = true;
    // }

    return $return;
}

/**
 * Checks if scale is being used by any instance of videoannotation.
 * This function was added in 1.9
 *
 * This is used to find out if scale used anywhere
 * @param $scaleid int
 * @return boolean True if the scale is used by any videoannotation
 */
function videoannotation_scale_used_anywhere($scaleid) {
    return false;
}

/**
 * Execute post-install custom actions for the module
 * This function was added in 1.9
 *
 * @return boolean true if success, false on error
 */
function videoannotation_install() {
    return true;
}

/**
 * Execute post-uninstall custom actions for the module
 * This function was added in 1.9
 *
 * @return boolean true if success, false on error
 */
function videoannotation_uninstall() {
    return true;
}

// Any other videoannotation functions go here.  Each of them must have a name that
// starts with videoannotation_
// Remember (see note in first lines) that, if this section grows, it's HIGHLY
// recommended to move all funcions below to a new "localib.php" file.

function videoannotation_get_submission_count($videoannotationid, $groupid=null) {
    global $CFG, $DB;

    $basesql = "SELECT COUNT(*)
                   FROM {$CFG->prefix}videoannotation_submissions
                  WHERE videoannotationid = " . (int) $videoannotationid . '
                    AND timesubmitted IS NOT NULL';
    if ($groupid == 'all') {
        $basesql .= ' AND groupid IS NOT NULL';
    } else if ($groupid) {
        $basesql .= ' AND groupid = ' . (int) $groupid;
    } else {
        $basesql .= ' AND groupid IS NULL';
    }

    $totalsubmissioncount = $DB->count_records_sql($basesql);
    $ungradedsubmissioncount = $DB->count_records_sql($basesql . ' AND timegraded IS NULL');

    return array($totalsubmissioncount, $ungradedsubmissioncount);
}

function videoannotation_get_clip_info($clipurl) {
    global $CFG;

    $result = array();

    // If the URL is a TNA permalink, use the web service to translate it into a RTMP link.
    foreach ($CFG->tnapermalinkurl as $idx => $tnapermalinkurl) {
        if ($clipurl and stripos($clipurl, $tnapermalinkurl) === 0) {
            @list($uuid, $offset) = explode(',', substr($clipurl, strlen($tnapermalinkurl)));
            if ($uuid) {
                $content = file_get_contents($CFG->tnawebserviceurl[$idx] .
                    '?action=uuidToFileName&uuid=' . urlencode(trim($uuid)));
                $contentobj = json_decode($content);
                if (preg_match('/^(\d{4})\-(\d{2})\-(\d{2})_(\d{2})(\d{2})/', $contentobj->filename, $matches)) {
                    return array(
                    'streamer' => $CFG->tnastreamerurl,
                    'file' => "{$matches[1]}/{$matches[1]}-{$matches[2]}/{$matches[1]}-{$matches[2]}-{$matches[3]}/" .
                        basename($contentobj->filename, '.txt') . '.mp4',
                    'start' => $offset > 0 ? $offset : 0
                    );
                }
            }
        }
    }

    if (preg_match('/^(rtmp:\/\/.*?\/.*?)\/(.*)/', $clipurl, $matches)) {
        return array('streamer' => $matches[1], 'file' => $matches[2]);
    }

    if (stripos($clipurl, 'http://www.youtube.com/watch?') === 0) {
        return array('file' => $clipurl, 'width' => 640, 'height' => 385);
    }

    return array('file' => $clipurl);
}

function videoannotation_get_course_module_by_video_annotation($videoannotationid) {
    global $CFG, $DB;
    $sql = "SELECT cm.*
              FROM mdl_course_modules cm
              JOIN mdl_modules m ON cm.module = m.id and m.name = 'videoannotation'
              JOIN mdl_videoannotation v ON cm.instance = v.id AND v.id = " . (int) $videoannotationid;
    return $DB->get_record_sql($sql);
}

// Define json_encode and json_decode if they are not defined (i.e. PHP < 5.2).

if (!function_exists('json_decode')) {
    require_once(dirname(__FILE__) . '/JSON.php');
    function json_encode($data) {
        $value = new Services_JSON();
        return $value->encode($data);
    }
    function json_decode($data, $assoc = false) {
        if ($assoc) {
            $value = new Services_JSON(SERVICES_JSON_LOOSE_TYPE);
        } else {
            $value = new Services_JSON();
        }
        return $value->decode($data);
    }
}
