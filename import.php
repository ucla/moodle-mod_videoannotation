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
require_once($CFG->libdir . '/moodlelib.php');
require_once($CFG->dirroot . '/lib/formslib.php');

/**
 * Parse the CSV file
 * CSV files should follow the format listed
 * @link https://redmine.sscnet.ucla.edu/projects/videoannotation/wiki/CSV_Import_format
 *
 * @paramgn array An array of the parsed CSV values
 */
function parse_csv($mform, $userid) {
    $text = $mform->get_file_content('import');
    $text = str_replace("\r", '', $text);
    $strings = array();
    $i = 0;
    $text = explode("\n", $text);
    foreach ($text as $line) {
        if (empty($line)) {
            continue;
        }
        preg_match_all('/"(.*?)"/', $line, $arr);
        $strings[$i] = $arr[1];
        $i++;
    }
    // Check to see that the csv file imported was formatted correctly.
    // Any undefined offsets will mean the file was incorrect syntax.
    for ($i = 0; $i < count($strings); $i++) {
        for ($j = 0; $j < count($strings[$i]); $j++) {
            if (!isset($strings[$i][$j]) || $j >= 14) {
                print_error("importsyntax", 'videoannotation');
            }
        }
    }
    return $strings;
}

$id = optional_param('id', 0, PARAM_INT);          // Course module ID
$a = optional_param('a', 0, PARAM_INT);           // Video annotation ID
$mode = optional_param('mode', 'all', PARAM_ALPHA);  // What mode are we in?
$groupid = optional_param('group', null, PARAM_INT);

$userid = $USER->id;
global $DB;
if ($id) {
    if (!$cm = get_coursemodule_from_id('videoannotation', $id)) {
        print_error("coursemodidincorrect", 'videoannotation');
    }

    if (!$videoannotation = $DB->get_record("videoannotation", array("id" => $cm->instance))) {
        print_error("vididincorrect", 'videoannotation');
    }

    if (!$course = $DB->get_record("course", array("id" => $videoannotation->course))) {
        print_error("coursemisconfigure", 'videoannotation');
    }
} else {
    if (!$videoannotation = $DB->get_record("videoannotation", arraY("id" => $a))) {
        print_error("coursemodincorrect", 'videoannotation');
    }
    if (!$course = $DB->get_record("course", array("id" => $videoannotation->course))) {
        print_error("coursemisconfigure", 'videoannotation');
    }
    if (!$cm = get_coursemodule_from_instance("videoannotation", $videoannotation->id, $course->id)) {
        print_error("coursemodidincorrect", 'videoannotation');
    }
}

require_login($course->id, false, $cm);

// Print the page header.
$PAGE->set_url('/mod/videoannotation/import.php', array('id' => $cm->id));
$PAGE->set_title('IMPORT');
$PAGE->set_pagetype('course');
$PAGE->set_heading('Import');
echo $OUTPUT->header();

$modulecontext = get_context_instance(CONTEXT_MODULE, $cm->id);
$canview = require_capability('mod/videoannotation:view', $modulecontext);
$cansubmit = has_capability('mod/videoannotation:submit', $modulecontext);
$canmanage = has_capability('mod/videoannotation:manage', $modulecontext);
$isadmin = is_siteadmin($USER->id);

// Change id and readonly variables depending on the groupmode and user permissions.
$groups = array();
switch ($videoannotation->groupmode) {
    case NOGROUPS:
        if ($isadmin or $canmanage) {
            $groupid = null;
            $readonly = ($isadmin and $userid != $USER->id);
        } else if ($cansubmit or $canview) {
            $userid = $USER->id;
            $groupid = null;
            $readonly = ($userid != $USER->id);
        } else {
            require_capability('mod/videoannotation:view', $modulecontext);
        }
        break;
    case VIDEOANNOTATION_GROUPMODE_GROUP_USER:
    case VIDEOANNOTATION_GROUPMODE_GROUP_GROUP:
        if ($isadmin or $canmanage) {
            $groups = groups_get_all_groups($course->id, null, $cm->groupingid);
            if (!$groups) {
                print_error('onegroup', 'videoannotation');
            }
            if ($groupid) {
                if (!isset($groups[$groupid])) {
                    print_error('notgroup', 'videoannotation');
                } else {
                    foreach (array_keys($groups) as $groupid) {
                        break;
                    }
                }
            }
            $readonly = (!$isadmin and ! groups_is_member($groupid, $USER->id));
        } else if ($cansubmit or $canview) {
            $groups = groups_get_all_groups($course->id, $USER->id, $cm->groupingid);
            if (!$groups) {
                print_error('onegroup', 'videoannotation');
            }
            if ($groupid) {
                if (!isset($groups[$groupid])) {
                    print_error('notgroup', 'videoannotation');
                } else {
                    foreach (array_keys($groups) as $groupid) {
                        break;
                    }
                }
            }
            $readonly = (!groups_is_member($groupid, $USER->id));
        } else {
            require_capability('mod/videoannotation:view', $modulecontext);
        }
        break;
    default:
        error('Invalid group mode');
}

if ($videoannotation->groupmode == NOGROUPS) {
    $submission = $DB->get_record('videoannotation_submissions', array('videoannotationid' => $videoannotation->id,
        'userid' => $userid,
        'groupid' => null));
} else {
    $submission = $DB->get_record('videoannotation_submissions', array('videoannotationid' => $videoannotation->id,
        'groupid' => $groupid));
}

if (!$isadmin and !$canmanage) {
    $readonly = ($readonly or $submission);
}

add_to_log($course->id, "videoannotation", "view", "view.php?id=$cm->id", "$videoannotation->id");

// Define the import form.

// Create an array of the group names.
$groupnames = array();
foreach ($groups as $key => $group) {
    $groupnames[] = $group->name;
}

class import_form extends moodleform {

    public function definition() {
        global $COURSE, $CFG, $DB;
        $groupid = optional_param('group', null, PARAM_INT);
        $mform = & $this->_form;

        $id = optional_param('id', 0, PARAM_INT);
        $cm = get_coursemodule_from_id('videoannotation', $id);
        $videoannotation = $DB->get_record("videoannotation", array(
            "id" => $cm->instance));

        $mform->addElement('hidden', 'id');

        $mform->addElement('filepicker', 'import', get_string('file'), null);

        $mform->addElement('advcheckbox', 'importtags', get_string('importtags', 'videoannotation'));
        if ($groupid) {
            $mform->addElement('hidden', 'group', $groupid);
        }

        // Do not show the box which allows imports for separate groups when
        // there is a specified clip, because we will import all
        // tags/events/clip for that option.
        if ($this->_customdata['groupmode'] == VIDEOANNOTATION_GROUPMODE_GROUP_GROUP
                || $this->_customdata['groupmode'] == VIDEOANNOTATION_GROUPMODE_GROUP_USER) {
            $mform->addElement('select', 'groupselect', get_string('groupselect',
                    'videoannotation'), $this->_customdata['groupnames']);
        }
        $this->add_action_buttons(true, get_string('submit'));
    }
}

// Display the form.
$mform = new import_form(null, array('groupmode' => $videoannotation->groupmode, 'groupnames' => $groupnames));

if ($mform->is_cancelled()) {
    redirect("view.php?id=" . $cm->id . ($groupid ? "&group=" . $groupid : ''), '', 0);
} else if ($data = $mform->get_data()) {
    $importtags = $data->importtags;
    if (isset($data->groupselect)) {
        $groupselect = $data->groupselect;
    }

    $strings = parse_csv($mform, $userid);
    // Organize the data.
    $importgroups = array();
    foreach ($strings as $key => $value) {
        if ($key == 0) {
            continue;
        }
        $groupname = $strings[$key][0];
        // The group data has already been created.
        if (isset($importgroups[$groupname])) {
            $data = $importgroups[$groupname];
            $tagname = $strings[$key][6];
            $event = new stdClass();
            $event->start = (double) $strings[$key][7];
            $event->end = (double) $strings[$key][8];
            $event->content = $strings[$key][9];
            $event->scope = $strings[$key][11];
            $event->lat = (double) $strings[$key][12];
            $event->long = (double) $strings[$key][13];
            // Add the event to the group's events.
            if (isset($data->tagevents[$tagname])) {
                if ($event->start != null) {
                    $data->tagevents[$tagname][] = $event;
                }
            } else {
                $data->tagcolors[$tagname] = $strings[$key][10];
                $data->tagevents[$tagname] = array();
                if ($event->start != null) {
                    $data->tagevents[$tagname][] = $event;
                }
            }
        } else {
            // Create the group data.
            $data = new stdClass();
            $data->videoannotationid = $videoannotation->id;
            $data->groupname = $strings[$key][0];
            if ($strings[$key][1] != '') {
                $data->userid = $strings[$key][1];
            } else {
                $data->userid = $USER->id;
            }
            if ($strings[$key][2] != '') {
                $data->fullname = $strings[$key][2];
            }
            $data->url = $strings[$key][3];
            $data->playabletimestart = (int) $strings[$key][4];
            $data->playabletimeend = (int) $strings[$key][5];

            $tagname = $strings[$key][6];
            $event = new stdClass();
            $event->start = (double) $strings[$key][7];
            $event->end = (double) $strings[$key][8];
            $event->content = $strings[$key][9];
            $event->scope = $strings[$key][11];
            $event->lat = (double) $strings[$key][12];
            $event->long = (double) $strings[$key][13];
            $data->tagcolors = array($tagname => $strings[$key][10]);
            $data->tagevents = array($tagname => array());
            if ($event->start != null) {
                $data->tagevents[$tagname][] = $event;
            }
            $importgroups[$groupname] = $data;
        }
    }
    // Insert the data into the database.
    // Only insert for one group.
    $runflag = 0;
    isset($groupselect) ? $groupselectid = groups_get_group_by_name($COURSE->id, $groupnames[$groupselect]) : $groupselectid = null;
    $groupid = groups_get_all_groups($course->id, null, $cm->groupingid);
    foreach ($importgroups as $key => $data) {
        if ($runflag == 1) {
            continue;
        }
        $runflag = 1;
        $clipid = null;
        $tagid = null;
        $data->groupid = $groupselectid;

        if ($videoannotation->clipselect == 1) {
            $clip = $DB->get_record('videoannotation_clips', array('videoannotationid' => $data->videoannotationid,
                                    'userid' => null, 'groupid' => null));
        } else if ($videoannotation->groupmode == NOGROUPS) {
            $clip = $DB->get_record('videoannotation_clips', array('videoannotationid' => $data->videoannotationid,
                                    'userid' => $userid));
        } else {
            $clip = $DB->get_record('videoannotation_clips', array('videoannotationid' => $data->videoannotationid,
                                    'groupid' => $data->groupid));
        }
        if ($clip) {
            $clipid = $clip->id;
        } else {
            print_error("notvalid", 'videoannotation');
        }

        $rs = $DB->get_recordset('videoannotation_tags', array('clipid' => $clipid));
        $oldtags = array();
        foreach ($rs as $record) {
            array_push($oldtags, $record);
        }
        foreach ($data->tagevents as $key => $tag) {
            $count = 0;
            $tagname = $key;
            // If the video annotation has a specified clip, the tags/events must go across all groups when imported.
            // Otherwise, only import tags/events for one group.
            foreach ($groupid as $key => $gid) {
                if ($count == 0) {
                    $count = 1;
                } else {
                    continue;
                }
                if ($oldtags != null) {
                    $olditerator = 0;
                    // Check if the tagname already exists, case insensitive.
                    while ($olditerator != count($oldtags)) {
                        if (strtolower($tagname) == strtolower($oldtags[$olditerator]->name) &&
                                                               $data->groupid == $oldtags[$olditerator]->groupid) {
                            // Rename the old tag.
                            $i = 1;
                            $newname = $tagname . $i;
                            while ($record = $DB->get_record('videoannotation_tags', array('clipid' => $clipid,
                                    'groupid' => $data->groupid, 'name' => addslashes($newname)))) {
                                $i++;
                                $newname = $tagname . $i;
                            }
                            $oldtags[$olditerator]->name = $newname;
                            $DB->update_record('videoannotation_tags', $oldtags[$olditerator]);
                        }
                        $olditerator++;
                    }
                }
                // Insert the new tag.
                $tagobj = new stdClass();
                $tagobj->clipid = $clipid;
                if (!is_numeric($data->userid)) {
                    $tagobj->userid = $USER->id;
                } else {
                    $tagobj->userid = $data->userid;
                }
                $tagobj->groupid = $data->groupid;
                if ($data->tagcolors[$tagname] != "") {
                    $tagobj->color = $data->tagcolors[$tagname];
                }
                $tagobj->name = addslashes($tagname);
                $tagobj->timecreated = time();
                if (!($tagid = $DB->insert_record('videoannotation_tags', $tagobj))) {
                    error("Adding tag \"" . stripslashes($tagname) . "\" failed!");
                    continue;
                }
                // Videoannotation_events
                if (!$importtags) {            // Don't insert events if requested not to.
                    foreach ($data->tagevents[$tagname] as $key => $event) {
                        $eventobj = new stdClass();
                        $eventobj->tagid = $tagid;
                        if (!is_numeric($data->userid)) {
                            $eventobj->userid = $USER->id;
                        } else {
                            $eventobj->userid = $data->userid;
                        }
                        $eventobj->groupid = $data->groupid;
                        $eventobj->starttime = $event->start;
                        $eventobj->endtime = $event->end;
                        $eventobj->content = addslashes($event->content);
                        $eventobj->timecreated = time();
                        $eventobj->scope = $event->scope;
                        $eventobj->latitude = $event->lat;
                        $eventobj->longitude = $event->long;
                        if (!($result = $DB->insert_record('videoannotation_events', $eventobj))) {
                            error("Adding event for tag \"" . stripslashes($tagname) . "\" failed!");
                            continue;
                        }
                    }
                }
            }
        }
    }
    $OUTPUT->heading(get_string('importsuccess', 'videoannotation'));
    redirect("view.php?id=" . $cm->id . ($groupid ? "&group=" . $groupid : ''), '');
} else {
    $OUTPUT->heading(get_string('importannotation', 'videoannotation'));
    if ($readonly) {
        print_error('cantimport', 'videoannotation');
        redirect("view.php?id=" . $cm->id . ($groupid ? "&group=" . $groupid : ''), '', 0);
    }

    $mform->set_data(array('id' => $id));

    $mform->display();
}

// Print the footer.
echo $OUTPUT->footer();