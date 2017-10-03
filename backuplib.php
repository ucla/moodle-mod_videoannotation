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

function videoannotation_backup_clips($bf, $preferences, $videoannotation, $activityclipsonly) {
    global $CFG;

    $status = true;

    // If $activityclips_only is set,
    // then include only clips associated with this activity (i.e. user is NULL and group is NULL)
    // otherwise include all clips in this activity.

    if ($activityclipsonly) {
        $sql = "SELECT *
                  FROM {$CFG->prefix}videoannotationclips
                 WHERE videoannotationid = " . (int) $videoannotation . "
                   AND userid IS NULL AND groupid IS NULL
                 ORDER BY id"
        $videoannotationclips = get_records_sql($sql);
    } else {
        $videoannotationclips = get_records('videoannotationclips', 'videoannotationid', $videoannotation, "id");
    }

    // If there are clips...
    if ($videoannotationclips) {
        // Write start tag.
        $status = fwrite($bf, start_tag("CLIPS", 4, true));
        // Iterate over each clip.
        foreach ($videoannotationclips as $videoannotationclip) {
            // Start clip.
            $status = fwrite($bf, start_tag("CLIP", 5, true));
            // Print clip contents.
            fwrite ($bf, full_tag("ID", 6, false, $videoannotationclip->id));
            fwrite ($bf, full_tag("VIDEOANNOTATIONID", 6, false, $videoannotationclip->videoannotationid));
            fwrite ($bf, full_tag("USERID", 6, false, $videoannotationclip->userid));
            fwrite ($bf, full_tag("GROUPID", 6, false, $videoannotationclip->groupid));
            fwrite ($bf, full_tag("URL", 6, false, $videoannotationclip->url));
            fwrite ($bf, full_tag("PLAYABLETIMESTART", 6, false, $videoannotationclip->playabletimestart));
            fwrite ($bf, full_tag("PLAYABLETIMEEND", 6, false, $videoannotationclip->playabletimeend));
            fwrite ($bf, full_tag("VIDEOWIDTH", 6, false, $videoannotationclip->videowidth));
            fwrite ($bf, full_tag("VIDEOHEIGHT", 6, false, $videoannotationclip->videoheight));
            fwrite ($bf, full_tag("TIMECREATED", 6, false, $videoannotationclip->timecreated));
            fwrite ($bf, full_tag("TIMEMODIFIED", 6, false, $videoannotationclip->timemodified));
            // End clip.
            $status = fwrite($bf, end_tag("CLIP", 5, true));
        }
        // Write end tag.
        $status = fwrite($bf, end_tag("CLIPS", 4, true));
    }

    return $status;
}

function videoannotation_backup_events($bf, $preferences, $videoannotation) {
    global $CFG;

    $status = true;

    $sql = "SELECT e.*
              FROM {$CFG->prefix}videoannotationclips c
              JOIN {$CFG->prefix}videoannotationtags t ON c.id = t.clipid
              JOIN {$CFG->prefix}videoannotationevents e ON t.id = e.tagid
             WHERE c.videoannotationid = " . (int) $videoannotation . "
             ORDER BY t.id"
    $videoannotationevents = get_records_sql($sql);

    // If there are events...
    if ($videoannotationevents) {
        // Write start tag.
        $status = fwrite($bf, start_tag("EVENTS", 4, true));
        // Iterate over each event.
        foreach ($videoannotationevents as $videoannotationevent) {
            // Start event.
            $status = fwrite($bf, start_tag("EVENT", 5, true));
            // Print event contents.
            fwrite ($bf, full_tag("ID", 6, false, $videoannotationevent->id));
            fwrite ($bf, full_tag("TAGID", 6, false, $videoannotationevent->tagid));
            fwrite ($bf, full_tag("USERID", 6, false, $videoannotationevent->userid));
            fwrite ($bf, full_tag("GROUPID", 6, false, $videoannotationevent->groupid));
            fwrite ($bf, full_tag("STARTTIME", 6, false, $videoannotationevent->starttime));
            fwrite ($bf, full_tag("ENDTIME", 6, false, $videoannotationevent->endtime));
            fwrite ($bf, full_tag("CONTENT", 6, false, $videoannotationevent->content));
            fwrite ($bf, full_tag("TIMECREATED", 6, false, $videoannotationevent->timecreated));
            fwrite ($bf, full_tag("TIMEMODIFIED", 6, false, $videoannotationevent->timemodified));
            fwrite ($bf, full_tag("SCOPE", 6, false, $videoannotationevent->scope));
            fwrite ($bf, full_tag("LATITUDE", 6, false, $videoannotationevent->latitude));
            fwrite ($bf, full_tag("LONGITUDE", 6, false, $videoannotationevent->longitude));
            // End event.
            $status = fwrite($bf, end_tag("EVENT", 5, true));
        }
        // Write end tag.
        $status = fwrite($bf, end_tag("EVENTS", 4, true));
    }

    return $status;
}

function videoannotation_backup_mods($bf, $preferences) {
    global $CFG;

    $status = true;

    // Iterate over videoannotation table.
    if ($videoannotations = get_records("videoannotation", "course", $preferences->backup_course, "id")) {
        foreach ($videoannotations as $videoannotation) {
            if (backup_mod_selected($preferences, 'videoannotation', $videoannotation->id)) {
                $status = videoannotation_backup_one_mod($bf, $preferences, $videoannotation);
                // Backup files happens in backup_one_mod now too.
            }
        }
    }

    return $status;
}

function videoannotation_backup_one_mod($bf, $preferences, $videoannotation) {
    global $CFG;

    if (is_numeric($videoannotation)) {
        $videoannotation = get_record('videoannotation', 'id', $videoannotation);
    }

    $status = true;

    fwrite ($bf, start_tag("MOD", 3, true));

    // Print videoannotation data.
    fwrite ($bf, full_tag("ID", 4, false, $videoannotation->id));
    fwrite ($bf, full_tag("MODTYPE", 4, false, "videoannotation"));
    fwrite ($bf, full_tag("NAME", 4, false, $videoannotation->name));
    fwrite ($bf, full_tag("INTRO", 4, false, $videoannotation->intro));
    fwrite ($bf, full_tag("INTROFORMAT", 4, false, $videoannotation->introformat));
    fwrite ($bf, full_tag("CLIPSELECT", 4, false, $videoannotation->clipselect));
    fwrite ($bf, full_tag("GROUPMODE", 4, false, $videoannotation->groupmode));
    fwrite ($bf, full_tag("TIMECREATED", 4, false, $videoannotation->timecreated));
    fwrite ($bf, full_tag("TIMEMODIFIED", 4, false, $videoannotation->timemodified));

    // If we've selected to backup users info,
    // then backup mdl_videoannotationclips, mdl_videoannotationevents, mdl_videoannotationsubmissions and
    // mdl_videoannotationtags tables also.

    if (backup_userdata_selected($preferences, 'videoannotation', $videoannotation->id)) {
        $status = videoannotation_backup_clips($bf, $preferences, $videoannotation->id, false);
        if ($status) {
            $status = videoannotation_backup_events($bf, $preferences, $videoannotation->id);
        }
        if ($status) {
            $status = videoannotation_backup_submissions($bf, $preferences, $videoannotation->id);
        }
        if ($status) {
            $status = videoannotation_backup_tags($bf, $preferences, $videoannotation->id);
        }
    } else {
        $status = videoannotation_backup_clips($bf, $preferences, $videoannotation->id, true);
    }

    $status = fwrite ($bf, end_tag("MOD", 3, true));

    return $status;
}

function videoannotation_backup_submissions($bf, $preferences, $videoannotation) {
    global $CFG;

    $status = true;

    $videoannotationsubmissions = get_records('videoannotationsubmissions', 'videoannotationid', $videoannotation, 'id');

    // If there are submissions...
    if ($videoannotationsubmissions) {
        // Write start tag.
        $status = fwrite($bf, start_tag("SUBMISSIONS", 4, true));
        // Iterate over each clip.
        foreach ($videoannotationsubmissions as $videoannotationsubmission) {
            // Start clip.
            $status = fwrite($bf, start_tag("SUBMISSION", 5, true));
            // Print clip contents.
            fwrite ($bf, full_tag("ID", 6, false, $videoannotationsubmission->id));
            fwrite ($bf, full_tag("VIDEOANNOTATIONID", 6, false, $videoannotationsubmission->videoannotationid));
            fwrite ($bf, full_tag("USERID", 6, false, $videoannotationsubmission->userid));
            fwrite ($bf, full_tag("GROUPID", 6, false, $videoannotationsubmission->groupid));
            fwrite ($bf, full_tag("CLIPID", 6, false, $videoannotationsubmission->clipid));
            fwrite ($bf, full_tag("GRADE", 6, false, $videoannotationsubmission->grade));
            fwrite ($bf, full_tag("GRADECOMMENT", 6, false, $videoannotationsubmission->gradecomment));
            fwrite ($bf, full_tag("TIMESUBMITTED", 6, false, $videoannotationsubmission->timesubmitted));
            fwrite ($bf, full_tag("TIMEGRADED", 6, false, $videoannotationsubmission->timegraded));
            fwrite ($bf, full_tag("TIMECREATED", 6, false, $videoannotationsubmission->timecreated));
            fwrite ($bf, full_tag("TIMEMODIFIED", 6, false, $videoannotationsubmission->timemodified));
            // End clip.
            $status = fwrite($bf, end_tag("SUBMISSION", 5, true));
        }
        // Write end tag.
        $status = fwrite($bf, end_tag("SUBMISSIONS", 4, true));
    }

    return $status;
}

function videoannotation_backup_tags($bf, $preferences, $videoannotation) {
    global $CFG;

    $status = true;

    $sql = "SELECT t.*
              FROM {$CFG->prefix}videoannotationclips c
              JOIN {$CFG->prefix}videoannotationtags t ON c.id = t.clipid
             WHERE c.videoannotationid = " . (int) $videoannotation . "
             ORDER BY t.id"
    $videoannotationtags = get_records_sql($sql);

    // If there are tags...
    if ($videoannotationtags) {
        // Write start tag.
        $status = fwrite($bf, start_tag("TAGS", 4, true));
        // Iterate over each clip.
        foreach ($videoannotationtags as $videoannotationtag) {
            // Start clip.
            $status = fwrite($bf, start_tag("TAG", 5, true));
            // Print clip contents.
            fwrite ($bf, full_tag("ID", 6, false, $videoannotationtag->id));
            fwrite ($bf, full_tag("CLIPID", 6, false, $videoannotationtag->clipid));
            fwrite ($bf, full_tag("USERID", 6, false, $videoannotationtag->userid));
            fwrite ($bf, full_tag("GROUPID", 6, false, $videoannotationtag->groupid));
            fwrite ($bf, full_tag("NAME", 6, false, $videoannotationtag->name));
            fwrite ($bf, full_tag("COLOR", 6, false, $videoannotationtag->color));
            fwrite ($bf, full_tag("SORTORDER", 6, false, $videoannotationtag->sortorder));
            fwrite ($bf, full_tag("TIMECREATED", 6, false, $videoannotationtag->timecreated));
            fwrite ($bf, full_tag("TIMEMODIFIED", 6, false, $videoannotationtag->timemodified));
            // End clip.
            $status = fwrite($bf, end_tag("TAG", 5, true));
        }
        // Write end tag.
        $status = fwrite($bf, end_tag("TAGS", 4, true));
    }

    return $status;
}

function videoannotation_check_backup_mods($course, $userdata=false, $backupuniquecode, $instances=null) {
    if (!empty($instances) && is_array($instances) && count($instances)) {
        $info = array();
        foreach ($instances as $id => $instance) {
            $info += videoannotation_check_backup_mods_instances($instance, $backupuniquecode);
        }
        return $info;
    }

    // First, the course data.
    $info[0][0] = get_string("modulenameplural", "videoannotation");
    $info[0][1] = (int) videoannotation_count_activities_by_course($course);

    // Now, if requested, the userdata.
    if ($userdata) {
        $info[1][0] = get_string("submissions", "videoannotation");
        $info[1][1] = (int) videoannotation_count_submissions_by_course($course);
    }

    return $info;
}

function videoannotation_check_backup_mods_instances($instance, $backupuniquecode) {
    $info[$instance->id.'0'][0] = '<b>' . $instance->name . '</b>';
    $info[$instance->id.'0'][1] = '';
    if (!empty($instance->userdata)) {
        $info[$instance->id.'1'][0] = get_string("clips", "videoannotation");
        $info[$instance->id.'1'][1] = (int) videoannotation_count_clips_by_instance($instance->id);
        $info[$instance->id.'2'][0] = get_string("tags", "videoannotation");
        $info[$instance->id.'2'][1] = (int) videoannotation_count_tags_by_instance($instance->id);
        $info[$instance->id.'3'][0] = get_string("events", "videoannotation");
        $info[$instance->id.'3'][1] = (int) videoannotation_count_events_by_instance($instance->id);
        $info[$instance->id.'4'][0] = get_string("submissions", "videoannotation");
        $info[$instance->id.'4'][1] = (int) videoannotation_count_submissions_by_instance($instance->id);
    }
    return $info;
}

function videoannotation_count_activities_by_course($course) {
    return count_records('videoannotation', 'course', $course);
}

function videoannotation_count_clips_by_course($course) {
    global $CFG;
    $sql = "SELECT count(*)
              FROM {$CFG->prefix}videoannotation va
              JOIN {$CFG->prefix}videoannotationclips c ON va.id = c.videoannotationid
             WHERE va.course = " . (int) $course;
    return count_records_sql($sql);
}

function videoannotation_count_clips_by_instance($instance) {
    return count_records('videoannotationclips', 'videoannotationid', $instance);
}

function videoannotation_count_events_by_course($instance) {
    global $CFG;
    $sql = "SELECT count(*)
              FROM {$CFG->prefix}videoannotationclips c
              JOIN {$CFG->prefix}videoannotationtags t ON c.id = t.clipid
              JOIN {$CFG->prefix}videoannotationevents e ON t.id = e.tagid
             WHERE c.videoannotationid = " . (int) $instance;
    return count_records_sql($sql);
}

function videoannotation_count_events_by_instance($instance) {
    global $CFG;
    $sql = "SELECT count(*)
              FROM {$CFG->prefix}videoannotationclips c
              JOIN {$CFG->prefix}videoannotationtags t ON c.id = t.clipid
              JOIN {$CFG->prefix}videoannotationevents e ON t.id = e.tagid
             WHERE c.videoannotationid = " . (int) $instance;
    return count_records_sql($sql);
}

function videoannotation_count_submissions_by_course($course) {
    global $CFG;
    $sql = "SELECT count(*)
              FROM {$CFG->prefix}videoannotation va
              JOIN {$CFG->prefix}videoannotationsubmissions s ON va.id = s.videoannotationid
             WHERE va.course = " . (int) $course;
    return count_records_sql($sql);
}

function videoannotation_count_submissions_by_instance($instance) {
    return count_records('videoannotationsubmissions', 'videoannotationid', $instance);
}

function videoannotation_count_tags_by_course($course) {
    global $CFG;
    $sql = "SELECT count(*)
              FROM {$CFG->prefix}videoannotation va
              JOIN {$CFG->prefix}videoannotationclips c ON va.id = c.videoannotationid
              JOIN {$CFG->prefix}videoannotationtags t ON c.id = t.clipid
             WHERE va.course = " . (int) $course;
    return count_records_sql($sql);
}

function videoannotation_count_tags_by_instance($instance) {
    global $CFG;
    $sql = "SELECT count(*)
              FROM {$CFG->prefix}videoannotationclips c
              JOIN {$CFG->prefix}videoannotationtags t ON c.id = t.clipid
             WHERE c.videoannotationid = " . (int) $instance;
    return count_records_sql($sql);
}

