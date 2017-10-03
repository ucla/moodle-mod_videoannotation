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

require_once("../../config.php");
require_once("lib.php");
require_once($CFG->libdir.'/moodlelib.php');
require_once($CFG->libdir.'/formslib.php');
require_once($CFG->libdir.'/tablelib.php');

$id   = optional_param('id', 0, PARAM_INT);          // Course module ID
$a    = optional_param('a', 0, PARAM_INT);           // Video annotation ID
$mode = optional_param('mode', 'all', PARAM_ALPHA);  // What mode are we in?
$groupid = optional_param('group', null, PARAM_INT);

global $DB;

if ($id) {
    if (!$cm = get_coursemodule_from_id('videoannotation', $id)) {
        print_error("coursemodidincorrect", "videoannotation");
    }
} else {
    if (!$videoannotation = $DB->get_record("videoannotation", array("id" => $a))) {
        print_error('coursemodincorrect', 'videoannotation');
    }
}

if (!$videoannotation = $DB->get_record("videoannotation", array("id" => $cm->instance))) {
    print_error('vaidincorrect', 'videoannotation');
}

if (!$course = $DB->get_record("course", array("id" => $videoannotation->course))) {
    print_error('coursemisconfigure', 'videoannotation');
}

if (!$cm = get_coursemodule_from_instance("videoannotation", $videoannotation->id, $course->id)) {
    print_error("coursemodidincorrect", "videoannotation");
}

if (!$modulecontext = get_context_instance(CONTEXT_MODULE, $cm->id)) {
    print_error('coursemisconfigure', 'videoannotation');
}

require_login($course->id, false, $cm);

require_capability('mod/videoannotation:view', get_context_instance(CONTEXT_MODULE, $cm->id));

add_to_log($course->id, "videoannotation", "report_download", "report_download.php?id=$cm->id", "$videoannotation->id");

$hasgradecapability = has_capability('mod/videoannotation:grade', $modulecontext);
$hassubmitcapability = has_capability('mod/videoannotation:submit', $modulecontext);

// Add null events for each tag.
switch ($videoannotation->groupmode) {
    case NOGROUPS:
        $sql = "SELECT u.id AS user_id, u.firstname, u.lastname,
                       c.url AS clip_url, c.playabletimestart AS clip_start,
                       c.playabletimeend AS clip_end, t.name AS tag_name,
                       t.color AS color
                  FROM {$CFG->prefix}videoannotation_clips c
                  JOIN {$CFG->prefix}videoannotation_tags t ON c.id = t.clipid
                  JOIN {$CFG->prefix}user u
                 WHERE c.videoannotationid = " . (int) $videoannotation->id . "
                   AND u.id = t.userid AND t.groupid IS NULL";

        if (!$hasgradecapability) {
            $sql .= ' AND u.id = ' . (int) $USER->id;
        }

        $sql .= ' ORDER BY u.lastname, u.firstname';
        break;
    case VIDEOANNOTATION_GROUPMODE_GROUP_GROUP:
    case VIDEOANNOTATION_GROUPMODE_GROUP_USER:
        $sql = "SELECT g.id AS group_id, g.name AS group_name,
                       c.url AS clip_url, c.playabletimestart AS clip_start,
                       c.playabletimeend AS clip_end, t.name AS tag_name,
                       t.color AS color
                  FROM {$CFG->prefix}videoannotation_clips c
                  JOIN {$CFG->prefix}videoannotation_tags t ON c.id = t.clipid
                  JOIN {$CFG->prefix}groups g
                 WHERE c.videoannotationid = " . (int) $videoannotation->id;

        // Changing to only output information for the selected group.
        $sql .= ' AND g.id = ' . $groupid . ' AND t.groupid = g.id';
        break;

        $sql .= ' ORDER BY g.name';
    default:
        print_error('invalidgroupmode', 'videoannotation');
}

$headeritems = array(
    get_string('groupname', 'videoannotation'),
    get_string('uid', 'videoannotation'),
    get_string('fullname', 'videoannotation'),
    get_string('clipurl', 'videoannotation'),
    get_string('playabletimestart', 'videoannotation'),
    get_string('playabletimeend', 'videoannotation'),
    get_string('tagname', 'videoannotation'),
    get_string('eventstart', 'videoannotation'),
    get_string('eventend', 'videoannotation'),
    get_string('eventcontent', 'videoannotation'),
    get_string('color', 'videoannotation'),
    get_string('scope', 'videoannotation'),
    get_string('latitude', 'videoannotation'),
    get_string('longitude', 'videoannotation')
);

$keys = array(
    'group_name', 'idnumber', 'fullname', 'clip_url', 'clip_start',
    'clip_end', 'tag_name', 'event_start', 'event_end', 'event_content',
    'color', 'scope', 'latitude', 'longitude');

if ($rs = $DB->get_recordset_sql($sql)) {
    header('Content-type: text/csv');
    header('Content-disposition: attachment;filename=' . urlencode($videoannotation->name) . '.csv');

    // Print the headers.
    $line = '';
    foreach ($headeritems as $headeritem) {
        if ($line != '') {
            $line .= ',';
        }
        $line .= '"' . str_replace('"', '\"', $headeritem) . '"';
    }
    echo $line, "\r\n";

    // Print the data.
    foreach ($rs as $record) {
        $line = '';

        foreach ($keys as $key) {
            if ($line != '') {
                $line .= ',';
            }
            if ($key == 'fullname') {
                if ($hasgradecapability) {
                    $value = fullname($record);
                } else {
                    $value = '';
                }
            } else if ($key == 'idnumber') {
                if ($hasgradecapability) {
                    if (isset($record->user_id)) {
                        $value = '_' . $record->user_id;
                    } else {
                        $value = '';
                    }
                } else {
                    $value = '';
                }
            } else if ($key == 'group_name') {
                if ($videoannotation->groupmode != NOGROUPS) {
                    $value = $record->$key;
                } else {
                    $value = '';
                }
            } else {
                if (isset($record->$key)) {
                    $value = $record->$key;
                } else {
                    $value = null;
                }
            }
            $line .= '"' . str_replace('"', '\"', $value) . '"';
        }
        echo $line, "\r\n";
    }
    $rs->close();
}

// Add the events.
switch ($videoannotation->groupmode) {
    case NOGROUPS:
        $sql = "SELECT u.id AS user_id, u.firstname, u.lastname,
                       c.url AS clip_url, c.playabletimestart AS clip_start,
                       c.playabletimeend AS clip_end, t.name AS tag_name,
                       t.color AS color,  e.starttime AS event_start,
                       e.endtime AS event_end, e.content AS event_content,
                       e.scope AS scope, e.latitude AS latitude,
                       e.longitude AS longitude
                  FROM {$CFG->prefix}videoannotation_clips c
                  JOIN {$CFG->prefix}videoannotation_tags t ON c.id = t.clipid
                  JOIN {$CFG->prefix}videoannotation_events e ON t.id = e.tagid
                  JOIN {$CFG->prefix}user u
                 WHERE c.videoannotationid = " . (int) $videoannotation->id . "
                   AND u.id = t.userid AND t.userid = e.userid AND t.groupid IS NULL AND e.groupid IS NULL";

        if (!$hasgradecapability) {
            $sql .= ' AND u.id = ' . (int) $USER->id;
        }

        $sql .= ' ORDER BY u.lastname, u.firstname';
        break;
    case VIDEOANNOTATION_GROUPMODE_GROUP_GROUP:
    case VIDEOANNOTATION_GROUPMODE_GROUP_USER:
        $sql = "SELECT g.id AS group_id, g.name AS group_name,
                       c.url AS clip_url, c.playabletimestart AS clip_start,
                       c.playabletimeend AS clip_end, t.name AS tag_name,
                       t.color AS color, e.starttime AS event_start,
                       e.endtime AS event_end, e.content AS event_content,
                       e.scope AS scope, e.latitude AS latitude,
                       e.longitude AS longitude
                  FROM {$CFG->prefix}videoannotation_clips c
                  JOIN {$CFG->prefix}videoannotation_tags t ON c.id = t.clipid
                  JOIN {$CFG->prefix}videoannotation_events e ON t.id = e.tagid
                  JOIN {$CFG->prefix}groups g
                 WHERE c.videoannotationid = " . (int) $videoannotation->id;

        global $USER;

        // Changing to only output information for the selected group.
        $sql .= ' AND g.id = ' . $groupid . ' AND t.groupid = g.id AND e.groupid = g.id';
        break;

        $sql .= ' ORDER BY g.name';
    default:
        error('Invalid group mode');
}

$rs = $DB->get_recordset_sql($sql);
if ($rs->valid()) {
    foreach ($rs as $record) {
        $line = '';
        foreach ($keys as $key) {
            if ($line != '') {
                $line .= ',';
            }
            if ($key == 'fullname') {
                if ($hasgradecapability) {
                    $value = fullname($record);
                } else {
                    $value = '';
                }
            } else if ($key == 'idnumber') {
                if ($hasgradecapability) {
                    if (isset($record->user_id)) {
                        $value = '_' . $record->user_id;
                    } else {
                        $value = '';
                    }
                } else {
                    $value = '';
                }
            } else if ($key == 'group_name') {
                if ($videoannotation->groupmode != NOGROUPS) {
                    $value = $record->$key;
                } else {
                    $value = '';
                }
            } else {
                $value = $record->$key;
            }
            $line .= '"' . str_replace('"', '\"', $value) . '"';
        }
        echo $line, "\r\n";
    }
    $rs->close();
}