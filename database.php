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

error_reporting(E_ALL);
ini_set('display_errors', true);

require_once(dirname(dirname(dirname(__FILE__))) . '/config.php');
require_once(dirname(__FILE__) . '/lib.php');

$inputs = array_merge($_GET, $_POST);

if (is_array($inputs)) {
    $outputs = array();
    for ($commandnum = 1; isset($inputs["c{$commandnum}_command"]); $commandnum++) {
        $input = array();
        foreach ($inputs as $key => $value) {
            if (stripos($key, "c{$commandnum}_") === 0) {
                $input[substr($key, strlen("c{$commandnum}_"))] = $value;
            }
        }

        $outputs[] = handle_command($input, $commandnum);
    }
    echo json_encode($outputs);
} else {
    echo json_encode(array("success" => false, "message" => "Data not given"));
}

function get_context_coursemodule($coursemoduleid) {
    global $CFG, $DB;
    $sql = "SELECT ctx.*
              FROM {context} ctx
             WHERE ctx.contextlevel = " . CONTEXT_MODULE . "AND ctx.instanceid = " . (int) $coursemoduleid;
    return $DB->get_record_sql($sql);
}

function get_context_videoannotation($videoannotationid) {
    global $CFG, $DB;
    $sql = "SELECT ctx.*
              FROM {context} ctx
              JOIN {course_modules} cm ON ctx.contextlevel = " . CONTEXT_MODULE . " AND ctx.instanceid = cm.id
              JOIN {modules} m ON cm.module = m.id
              JOIN {videoannotation} va ON cm.instance = va.id
             WHERE m.name = 'videoannotation'
                   AND va.id = " . (int) $videoannotationid;
    return $DB->get_record_sql($sql);
}

function get_coursemodule_clip($clipid) {
    global $CFG, $DB;
    $sql = "SELECT cm.*
              FROM mdl_course_modules cm
              JOIN mdl_modules m ON cm.module = m.id and m.name = 'videoannotation'
              JOIN mdl_videoannotation v ON cm.instance = v.id
              JOIN mdl_videoannotation_clips vc ON v.id = vc.videoannotationid
                   AND vc.id = " . (int) $clipid;
    return $DB->get_record_sql($sql);
}

function get_videoannotation_clip($clipid) {
    global $CFG, $DB;
    $sql = "SELECT v.*
              FROM {videoannotation} v
              JOIN {videoannotation_clips} vc ON v.id = vc.videoannotationid
             WHERE vc.id = " . (int) $clipid;
    return $DB->get_record_sql($sql);
}

function get_videoannotation_event($eventid) {
    global $CFG, $DB;
    $sql = "SELECT v.*
              FROM {videoannotation} v
              JOIN {videoannotation_clips} vc ON v.id = vc.videoannotationid
              JOIN {videoannotation_tags} vt ON vc.id = vt.clipid
              JOIN {videoannotation_events} ve ON vt.id = ve.tagid
             WHERE ve.id = " . (int) $eventid;
    return $DB->get_record_sql($sql);
}

function get_videoannotation_tag($tagid) {
    global $CFG, $DB;
    $sql = "SELECT v.*
              FROM {videoannotation} v
              JOIN {videoannotation_clips} vc ON v.id = vc.videoannotationid
              JOIN {videoannotation_tags} vt ON vc.id = vt.clipid
             WHERE vt.id = " . (int) $tagid;
    return $DB->get_record_sql($sql);
}

function handle_command($input, $commandnum) {
    global $CFG, $USER, $db, $DB;
    switch ($input['command']) {
        case 'test':
            return array("success" => true, "test" => "testing");

        case 'gettagsevents':
            if (!isset($input['clipid'])) {
                return array("success" => false, "message" => "clipid must be given.");
            }

            $videoannotation = get_videoannotation_clip($input['clipid']);
            $coursemodule = get_coursemodule_clip($input['clipid']);
            $modulecontext = context_module::instance($coursemodule->id);
            $canmanage = has_capability('mod/videoannotation:manage', $modulecontext);
            $canview = has_capability('mod/videoannotation:view', $modulecontext);
            $isadmin = is_siteadmin($USER->id);

            // Require mod/videoannotation:manage or mod/videoannotation:view capability.
            if (!$canmanage and ! $canview) {
                return array(
                    "success" => false,
                    "message" => "mod/videoannotation:manage or mod/videoannotation:view capability required."
                );
            }

            // Grant access only in one of these cases:
            // 1. Requestor has "manage" capability (instructor, etc.)
            // 2. Requestor has "view" capability, is in the given group, and the course has a group mode of SEPARATEGROUPS
            // 3. Requestor has "view" capability, is in any of the groups in the course, and the course has a group mode of VISIBLEGROUPS
            // Now that we have the course module and the context, let's determine if the requester can access the data he asks for

            $onlineusers = array();
            switch ($videoannotation->groupmode) {
                case NOGROUPS:
                    // "userid" parameter is required; we don't care about "groupid" parameter

                    if (!isset($input['userid'])) {
                        return array("success" => false, "message" => "userid is required.");
                    }

                    // If only "view" capability present, the requestor's user ID must be the same as the given user ID

                    if (!$isadmin and ! $canmanage and $USER->id != $input['userid']) {
                        return array("success" => false, "message" => "Access denied (cannot access other users' tags).");
                    }

                    // Get and return data: all tags and events "owned" by
                    // the user (given userid and null groupid)

                    $tagusergroupclause = '(t.userid = ' . (int) $input['userid'] . ' AND t.groupid IS NULL )';
                    $eventusergroupclause = '(e.userid = ' . (int) $input['userid'] . ' AND e.groupid IS NULL )';

                    $groupid = null;

                    $onlineusers = null;
                    break;

                case VIDEOANNOTATION_GROUPMODE_USER_USER:
                case VIDEOANNOTATION_GROUPMODE_GROUP_USER:
                case VIDEOANNOTATION_GROUPMODE_GROUP_GROUP:
                case VIDEOANNOTATION_GROUPMODE_ALL_USER:
                case VIDEOANNOTATION_GROUPMODE_ALL_GROUP:
                case VIDEOANNOTATION_GROUPMODE_ALL_ALL:

                    // "groupid" parameter is required; we don't care about "userid" parameter

                    if (!isset($input['groupid'])) {
                        return array("success" => false, "message" => "groupid is required.");
                    }

                    // If only "view" capability present, the requestor's must be in
                    // * the given group (for separate group), or
                    // * one of the groups in the course (for visible group)

                    if (!$isadmin and ! $canmanage) {
                        if ($videoannotation->groupmode == VIDEOANNOTATION_GROUPMODE_GROUP_USER) {
                            $groups = groups_get_all_groups($coursemodule->course, null, $coursemodule->groupingid);
                        } else {
                            $groups = groups_get_all_groups($coursemodule->course, $USER->id, $coursemodule->groupingid);
                        }

                        if (in_array($videoannotation->groupmode, array(VIDEOANNOTATION_GROUPMODE_USER_USER, VIDEOANNOTATION_GROUPMODE_GROUP_USER, VIDEOANNOTATION_GROUPMODE_GROUP_GROUP)) and ! isset($groups[$input['groupid']])) {
                            return array("success" => false, "message" => "Access denied (not in specified group).");
                        }

                        if (in_array($videoannotation->groupmode, array(VIDEOANNOTATION_GROUPMODE_ALL_USER, VIDEOANNOTATION_GROUPMODE_ALL_GROUP, VIDEOANNOTATION_GROUPMODE_ALL_ALL)) and ! $groups) {
                            return array("success" => false, "message" => "Access denied (not in any group).");
                        }

                        if (isset($groups[$input['groupid']])) {
                            $lock = $DB->get_record('videoannotation_locks', array('videoannotationid' => $videoannotation->id, 'userid' => $USER->id, 'groupid' => $input['groupid']));
                            if ($lock) {
                                $DB->update_record('videoannotation_locks', (object) array('id' => $lock->id, 'timemodified' => time()));
                            } else {
                                $DB->insert_record('videoannotation_locks', (object) array('videoannotationid' => $videoannotation->id, 'userid' => $USER->id, 'groupid' => $input['groupid'], 'timecreated' => time(), 'timemodified' => time()));
                            }
                        }
                    } else {
                        // SSC-1231: Add user info to videoannotation_locks for admin users too
                        $lock = $DB->get_record('videoannotation_locks', array('videoannotationid' => $videoannotation->id, 'userid' => $USER->id, 'groupid' => $input['groupid']));
                        if ($lock) {
                            $DB->update_record('videoannotation_locks', (object) array('id' => $lock->id, 'timemodified' => time()));
                        } else {
                            $DB->insert_record('videoannotation_locks', (object) array('videoannotationid' => $videoannotation->id, 'userid' => $USER->id, 'groupid' => $input['groupid'], 'timecreated' => time(), 'timemodified' => time()));
                        }
                    }

                    $DB->delete_records('videoannotation_locks', array('timemodified' => (time() - (.25 * 60))));

                    $sql = "SELECT u.id, u.lastname FROM {user} u
                              JOIN {videoannotation_locks} vl ON u.id = vl.userid
                             WHERE vl.videoannotationid = " . $videoannotation->id . "
                                   AND vl.groupid = " . (int) $input['groupid'] . "
                                   AND vl.userid != " . $USER->id;
                    $onlineuserrecords = $DB->get_records_sql($sql);
                    $onlineusers = array();
                    if ($onlineuserrecords) {
                        foreach ($onlineuserrecords as $onlineuserrecord) {
                            $onlineusers[] = $onlineuserrecord->lastname;
                        }
                    }

                    // Get and return data
                    // For "individual" group mode: all tags and events "owned" by the user and the group (the given userid and groupid)
                    // For other group modes: all tags and events "owned" by the group (null/non-null userid and the given groupid)

                    if ($videoannotation->groupmode == VIDEOANNOTATION_GROUPMODE_USER_USER) {
                        $tagusergroupclause = '(t.userid = ' . (int) $USER->id . ' AND t.groupid = ' . (int) $input['groupid'] . ')';
                        $eventusergroupclause = '(e.userid = ' . (int) $USER->id . ' AND e.groupid = ' . (int) $input['groupid'] . ')';
                    } else {
                        $tagusergroupclause = '(t.groupid = ' . (int) $input['groupid'] . ')';
                        $eventusergroupclause = '(e.groupid = ' . (int) $input['groupid'] . ')';
                    }

                    $groupid = (int) $input['groupid'];

                    break;

                default:
                    return array("success" => false, "message" => "Access denied (invalid group mode).");
            }

            if (ini_get('max_execution_time') == 0 or $input['timeout'] + 5 >= ini_get('max_execution_time')) {
                $timeout = 0;
            } else {
                $timeout = $input['timeout'];
            }

            // Start the timer

            $starttime = time();

            // Keep fetching new tags and events (see break condition near the end of the loop)

            do {
                // Get tags

                $sql = "SELECT t.id, t.name, t.color, t.timecreated, t.timemodified, t.level
                          FROM {videoannotation_tags} t
                         WHERE t.clipid = " . (int) $input['clipid'] . '
                               AND ' . $tagusergroupclause;
                if (isset($input['timestamp'])) {
                    $sql .= ' AND (t.timecreated > ' . (int) $input['timestamp'] . ' OR t.timemodified > ' . (int) $input['timestamp'] . ')';
                }
                $sql .= ' ORDER BY t.sortorder';
                $rs = $DB->get_recordset_sql($sql);
                // Fill tags into array, even if there are none (new video annotation has no tags). Only error on database error, not empty rs
                try {
                    $data = array();
                    foreach ($rs as $record) {
                        array_push($data, $record);
                    }
                    $rs->close();
                    $tags = $data;
                } catch (dml_exception $e) {
                    return array("success" => false, "message" => "Database error ");
                }

                // Get events

                $sql = "SELECT e.id, e.tagid, e.starttime, e.endtime, e.content, e.timecreated, e.timemodified, e.latitude, e.longitude, e.scope, e.level
                          FROM {videoannotation_events} e
                          JOIN {videoannotation_tags} t ON e.tagid = t.id
                         WHERE t.clipid = " . (int) $input['clipid'] . '
                               AND ' . $tagusergroupclause . '
                               AND ' . $eventusergroupclause;
                if (isset($input['timestamp'])) {
                    $sql .= ' AND (e.timecreated > ' . (int) $input['timestamp'] . ' OR e.timemodified > ' . (int) $input['timestamp'] . ')';
                }
                $rs = $DB->get_recordset_sql($sql);
                try {
                    $rsarray = array();
                    foreach ($rs as $record) {
                        array_push($rsarray, $record);
                    }
                    $rs->close();
                    $events = $rsarray;
                } catch (dml_exception $e) {
                    return array("success" => false, "message" => "Database error ");
                }

                // Get deleted tags.

                if (isset($input['tags']) and preg_match('/^\d+(\,\d+)*$/', $input['tags'])) {
                    $oldexistingtags = explode(',', $input['tags']);
                    $newexistingtags = $DB->get_records_sql("SELECT id FROM {videoannotation_tags} t WHERE id IN (" . $input['tags'] . ") AND " . $tagusergroupclause);
                    if ($newexistingtags) {
                        $newexistingtags = array_keys($newexistingtags);
                    } else {
                        $newexistingtags = array();
                    }
                    $deletedtags = array_values(array_diff($oldexistingtags, $newexistingtags));
                } else {
                    $deletedtags = array();
                }

                // Get deleted events.

                if (isset($input['events']) and preg_match('/^\d+(\,\d+)*$/', $input['events'])) {
                    $oldexistingevents = explode(',', $input['events']);
                    $newexistingevents = $DB->get_records_sql("SELECT id FROM {videoannotation_events} e WHERE id IN (" . $input['events'] . ")");
                    if ($newexistingevents) {
                        $newexistingevents = array_keys($newexistingevents);
                    } else {
                        $newexistingevents = array();
                    }
                    $deletedevents = array_values(array_diff($oldexistingevents, $newexistingevents));
                } else {
                    $deletedevents = array();
                }

                // Determine new timestmp

                $newtimestamp = isset($input['timestamp']) ? $input['timestamp'] : 0;
                foreach ($tags as &$tag) {
                    $newtimestamp = max($newtimestamp, (int) $tag->timecreated, (int) $tag->timemodified);
                    unset($tag->timecreated);
                    unset($tag->timemodified);
                }
                foreach ($events as &$event) {
                    $newtimestamp = max($newtimestamp, (int) $event->timecreated, (int) $event->timemodified);
                    unset($event->timecreated);
                    unset($event->timemodified);
                }

                if ((time() - $starttime >= $timeout) or $tags or $events or $deletedtags or $deletedevents) {
                    break;
                }

                sleep(1);
            } while (true);

            // Return tags and events

            $result = array(
                "success" => true,
                "tags" => $tags,
                'events' => $events,
                'deletedtags' => $deletedtags,
                'deletedevents' => $deletedevents,
                'timestamp' => $newtimestamp
            );
            if ($onlineusers !== null) {
                $result['onlineusers'] = $onlineusers;
            }
            return $result;

        case 'getclipinfo':
            if (isset($input['clipurl'])) {
                $info = videoannotation_get_clip_info($input['clipurl']);
                $info['success'] = true;
                return $info;
            } else {
                return array("success" => false, "message" => "clipurl is not given.");
            }

        // SSC-1191: Detect changes to the clip during editing
        case 'getclipdata':
            if (!isset($input['clipid'])) {
                return array("success" => false, "message" => "clipid is not given.");
            }
            $sql = "SELECT c.videoannotationid, c.groupid, c.url, c.playabletimestart, c.playabletimeend,
                           c.videowidth, c.videoheight, c.timecreated, c.timemodified
                      FROM {videoannotation_clips} c
                     WHERE c.id =" . (int) $input['clipid'];

            $rs = $DB->get_recordset_sql($sql);
            if (!$rs->valid()) {
                return array("success" => false, "message" => "Database error. ");
            }
            $data = array();
            foreach ($rs as $record) {
                array_push($data, $record);
            }
            $rs->close();
            $result = array(
                "success" => true,
                "data" => $data
            );
            return $result;
        // END SSC-1191

        case 'addtag':
            if (!isset($input['clipid']) or ! isset($input['name'])) {
                return array("success" => false, "message" => "clipid or name is not given.");
            }

            $videoannotation = get_videoannotation_clip($input['clipid']);
            $coursemodule = videoannotation_get_course_module_by_video_annotation($videoannotation->id);
            $modulecontext = context_module::instance($coursemodule->id);
            $canmanage = has_capability('mod/videoannotation:manage', $modulecontext);
            $cansubmit = has_capability('mod/videoannotation:submit', $modulecontext);
            $canview = has_capability('mod/videoannotation:view', $modulecontext);
            $isadmin = is_siteadmin($USER->id);

            // Security check

            if (!$isadmin) {

                // Case 1: "userid" not given, "groupid" not given (tag will be owned by the activity)
                if (!isset($input['userid']) and ! isset($input['groupid'])) {
                    // The user needs to have "manage" capability.

                    if (!$canmanage) {
                        return array("success" => false, "message" => "mod/videoannotation:manage capability required.");
                    }

                    // Case 2: "userid" given, "groupid" given (tag will be owned by the group)
                } else if (isset($input['userid']) and isset($input['groupid'])) {

                    // The user needs to have "manage" or "submit" capability.

                    if (!$canmanage and ! $cansubmit) {
                        return array("success" => false, "message" => "mod/videoannotation:manage or mod/videoannotation:submit capability required.");
                    }

                    // The group has not submitted this activity yet.

                    if ($DB->record_exists('videoannotation_submissions', array('videoannotationid' => $videoannotation->id, 'groupid' => $input['groupid']))) {
                        return array("success" => false, "Cannot add event in a timeline that has already been submitted.");
                    }

                    // The activity needs to have group mode on.

                    if ($videoannotation->groupmode == NOGROUPS) {
                        return array("success" => false, "message" => "Group mode must be on.");
                    }

                    // The user ID given (required) needs to equal the requestor's user ID.

                    if ($USER->id != $input['userid']) {
                        return array("success" => false, "message" => "userid, if given, must be the requestor's user ID.");
                    }

                    // The user have to be in the given group.

                    if (!groups_is_member($input['groupid'], $input['userid'])) {
                        return array("success" => false, "message" => "The given user must be in the given group.");
                    }

                    // Case 3: "userid" given, "groupid" not given (tag will be owned by the user)
                } else if (isset($input['userid'])) {

                    // The user needs to have "manage" or "submit" capability

                    if (!$canmanage and ! $cansubmit) {
                        return array("success" => false, "message" => "mod/videoannotation:manage or mod/videoannotation:submit capability required.");
                    }

                    // The user must not have submitted this activity yet.

                    if ($DB->record_exists('videoannotation_submissions', array('videoannotationid' => $videoannotation->id, 'userid' => $input['userid'], 'groupid' => null))) {
                        return array("success" => false, "Cannot add event in a timeline that has already been submitted.");
                    }

                    // The activity must have group mode off.

                    if ($videoannotation->groupmode != NOGROUPS) {
                        return array("success" => false, "message" => "Group mode must be off.");
                    }

                    // The user ID given needs to equal the requestor's user ID.

                    if ($USER->id != $input['userid']) {
                        return array("success" => false, "message" => "userid, if given, must be the requestor's user ID.");
                    }

                    // Case 4: "userid" not given, "groupid" given
                } else {

                    // Not acceptable; complain and abort.

                    return array("success" => false, "message" => "userid must be given if groupid is given.");
                }
            }

            // Insert record.

            $data = (object) array('clipid' => $input['clipid'], 'name' => $input['name'], 'timecreated' => time());
            if (isset($input['userid'])) {
                $data->userid = $input['userid'];
            }
            if (isset($input['groupid'])) {
                $data->groupid = $input['groupid'];
            }
            if (isset($input['color'])) {
                $data->color = $input['color'];
            }
            if (isset($input['level'])) {
                $data->level = $input['level']; 
            }
            $lastid = $DB->insert_record('videoannotation_tags', $data);
            if ($lastid !== false) {
                return array("success" => true, "id" => $lastid);
            } else {
                return array("success" => false, "message" => "Database error ");
            }

        case 'edittag':
            // id must be given

            if (!isset($input['id'])) {
                return array("success" => false, "message" => "id is not given.");
            }

            if (!isset($input['name']) and ! isset($input['color'])) {
                return array("success" => false, "message" => "name or color must be given.");
            }

            $videoannotation = get_videoannotation_tag($input['id']);
            $coursemodule = videoannotation_get_course_module_by_video_annotation($videoannotation->id);
            $modulecontext = context_module::instance($coursemodule->id);
            $canmanage = has_capability('mod/videoannotation:manage', $modulecontext);
            $cansubmit = has_capability('mod/videoannotation:submit', $modulecontext);
            $isadmin = is_siteadmin($USER->id);

            if (!$isadmin) {
                // Security check

                switch ($videoannotation->groupmode) {
                    // Case 1: group mode is off

                    case NOGROUPS:
                        // The tag must belong to the requestor and not a group.

                        if (!$DB->record_exists('videoannotation_tags', array('id' => $input['id'], 'userid' => $USER->id, 'groupid' => null))) {
                            return array("success" => false, "message" => "Access denied (not owner of the tag).");
                        }

                        // The requestor must not have submitted the activity.

                        if ($DB->record_exists('videoannotation_submissions', array('videoannotationid' => $videoannotation->id, 'userid' => $USER->id, 'groupid' => null))) {
                            return array("success" => false, "Cannot edit tag in a timeline that has already been submitted.");
                        }

                        break;

                    // Case 2: group mode is "separate" or "visible"

                    case VIDEOANNOTATION_GROUPMODE_USER_USER:
                    case VIDEOANNOTATION_GROUPMODE_GROUP_USER:
                    case VIDEOANNOTATION_GROUPMODE_GROUP_GROUP:
                    case VIDEOANNOTATION_GROUPMODE_ALL_USER:
                    case VIDEOANNOTATION_GROUPMODE_ALL_GROUP:
                    case VIDEOANNOTATION_GROUPMODE_ALL_ALL:

                        // The tag must belong to a group that the requestor belongs.
                        // Also, if group mode is "individual", "read all", the tag must be belong to the requestor.

                        $sql = "SELECT g.*
                                  FROM {videoannotation_tags} vt
                                  JOIN {groups_members} gm ON vt.groupid = gm.groupid
                                  JOIN {groups} g ON gm.groupid = g.id
                                 WHERE vt.id = " . (int) $input['id'] . " AND gm.userid = " . $USER->id;
                        if (in_array($videoannotation->groupmode, array(VIDEOANNOTATION_GROUPMODE_USER_USER, VIDEOANNOTATION_GROUPMODE_ALL_USER))) {
                            $sql .= " AND vt.userid = " . $USER->id;
                        }
                        $group = $DB->get_record_sql($sql);
                        if (!$group) {
                            return array("success" => false, "message" => "Access denied (not member the of the owner group of the tag).");
                        }

                        // The requestor must not have submitted the activity.

                        if ($DB->record_exists('videoannotation_submissions', array('videoannotationid' => $videoannotation->id, 'groupid' => $group->id))) {
                            return array("success" => false, "Cannot edit tag in a timeline that has already been submitted.");
                        }

                        break;

                    // Case 3: group mode is something else

                    default:
                        // Complain and abort.

                        return array("success" => false, "message" => "Access denied (invalid group mode).");
                }
            }

            // Update record.

            $dataobject = new stdClass();
            $dataobject->id = $input['id'];
            if (isset($input['name'])) {
                $dataobject->name = $input['name'];
            }
            if (isset($input['color'])) {
                $dataobject->color = $input['color'];
            }
            if (isset($input['level'])) {
                $dataobject->level = $input['level'];
            }
            try {
                $rs = $DB->update_record('videoannotation_tags', $dataobject);
                if (!$rs) {
                    return array("success" => false, "errortype" => "writeconflict", "message" => "Another user might have edited or deleted the tag you are editing.");
                }
            } catch (dml_exeception $e) {
                return array("success" => false, "errortype" => "database", "message" => "Database error ");
            }
            return array("success" => true);

        case 'deletetag':
            // id must be given

            if (!isset($input['id'])) {
                return array("success" => false, "message" => "id is not given.");
            }

            $videoannotation = get_videoannotation_tag($input['id']);
            $coursemodule = videoannotation_get_course_module_by_video_annotation($videoannotation->id);
            $modulecontext = context_module::instance($coursemodule->id);
            $canmanage = has_capability('mod/videoannotation:manage', $modulecontext);
            $cansubmit = has_capability('mod/videoannotation:submit', $modulecontext);
            $isadmin = is_siteadmin($USER->id);

            // Security check.

            if (!$isadmin) {
                switch ($videoannotation->groupmode) {
                    // Case 1: group mode is off

                    case NOGROUPS:
                        // The tag must belong to the requestor and not a group.

                        if (!$DB->record_exists('videoannotation_tags', array('id' => $input['id'], 'userid' => $USER->id, 'groupid' => null))) {
                            return array("success" => false, "message" => "Access denied (not owner of the tag).");
                        }

                        // The requestor must not have submitted the activity.

                        if ($DB->record_exists('videoannotation_submissions', array('videoannotationid' => $videoannotation->id, 'userid' => $USER->id, 'groupid' => null))) {
                            return array("success" => false, "Cannot edit tag in a timeline that has already been submitted.");
                        }

                        break;

                    // Case 2: group mode is "separate" or "visible"

                    case VIDEOANNOTATION_GROUPMODE_USER_USER:
                    case VIDEOANNOTATION_GROUPMODE_GROUP_USER:
                    case VIDEOANNOTATION_GROUPMODE_GROUP_GROUP:
                    case VIDEOANNOTATION_GROUPMODE_ALL_USER:
                    case VIDEOANNOTATION_GROUPMODE_ALL_GROUP:
                    case VIDEOANNOTATION_GROUPMODE_ALL_ALL:
                        // The tag must belong to a group that the requestor belongs.
                        // Also, if group mode is "individua" or "read all", the tag must be belong to the requestor.

                        $sql = "SELECT g.*
                                  FROM {videoannotation_tags} vt
                                  JOIN {groups_members} gm ON vt.groupid = gm.groupid
                                  JOIN {groups} g ON gm.groupid = g.id
                                 WHERE vt.id = " . (int) $input['id'] . " AND gm.userid = " . $USER->id;
                        if (in_array($videoannotation->groupmode, array(VIDEOANNOTATION_GROUPMODE_USER_USER, VIDEOANNOTATION_GROUPMODE_ALL_USER))) {
                            $sql .= " AND vt.userid = " . $USER->id;
                        }
                        $group = $DB->get_record_sql($sql);
                        if (!$group) {
                            return array("success" => false, "message" => "Access denied (not member of the owner of the tag).");
                        }

                        // The requestor must not have submitted the activity.

                        if ($DB->record_exists('videoannotation_submissions', array('videoannotationid' => $videoannotation->id, 'groupid' => $group->id))) {
                            return array("success" => false, "Cannot edit tag in a timeline that has already been submitted.");
                        }

                        break;

                    // Case 3: group mode is something else

                    default:
                        // Complain and abort.

                        return array("success" => false, "message" => "Access denied (invalid group mode).");
                }
            }

            // Delete record.

            $rs = $DB->delete_records('videoannotation_tags', array('id' => $input['id']));
            if (!$rs) {
                return array("success" => false, "errortype" => "database", "message" => "Database error ");
            }

            $rs = $DB->delete_records('videoannotation_events', array('tagid' => $input['id']));
            if (!$rs) {
                return array("success" => false, "errortype" => "database", "message" => "Database error ");
            }

            return array("success" => true);

        case 'reordertags':
            // clipid and orders must be given

            if (!isset($input['clipid']) or ! isset($input['orders'])) {
                return array("success" => false, "message" => "clipid or orders is not given.");
            }

            // Require mod/videoannotation:manage or mod/videoannotation:submit capability.
            // If only "submit" capability exists, there cannot have a submission for this activity and user.
            $videoannotation = get_videoannotation_clip($input['clipid']);
            $coursemodule = get_coursemodule_clip($input['clipid']);
            $modulecontext = context_module::instance($coursemodule->id);
            $canmanage = has_capability('mod/videoannotation:manage', $modulecontext);
            $cansubmit = has_capability('mod/videoannotation:submit', $modulecontext);
            $isadmin = is_siteadmin($USER->id);
            $dataobject = new stdClass();
            if ($isadmin || $canmanage) {
                // OK
            } else if ($cansubmit) {
                if ($DB->record_exists('videoannotation_submissions', array('videoannotationid' => $videoannotation->id, 'userid' => $USER->id))) {
                    return array("success" => false, "Cannot reorder tags in a timeline that has already been submitted.");
                }
            } else {
                return array("success" => false, "message" => "mod/videoannotation:manage or mod/videoannotation:submit capability required.");
            }

            // Access is granted in two cases
            // 1. If "userid" is given and the group mode is off,
            //    then the requestor can reorder her own tags
            // 2. If "groupid" is given and the group mode is "separate" or "visible",
            //    and the user is in the given group,
            //    then the requestor can reorder her group's tags
            // Either "userid" or "groupid", but not both, should be given

            switch ($videoannotation->groupmode) {
                case NOGROUPS:
                    $dataobject->userid = (int) $USER->id;
                    break;
                case VIDEOANNOTATION_GROUPMODE_USER_USER:
                case VIDEOANNOTATION_GROUPMODE_GROUP_USER:
                case VIDEOANNOTATION_GROUPMODE_GROUP_GROUP:
                case VIDEOANNOTATION_GROUPMODE_ALL_USER:
                case VIDEOANNOTATION_GROUPMODE_ALL_GROUP:
                case VIDEOANNOTATION_GROUPMODE_ALL_ALL:
                    if (!isset($input['groupid'])) {
                        return array("success" => false, "message" => "groupid must be given.");
                    }

                    if (!$isadmin and ! $canmanage and ! groups_is_member($input['groupid'], $USER->id)) {
                        return array("success" => false, "message" => "Access denied (user not in group).");
                    }

                    if (in_array($videoannotation->groupmode, array(VIDEOANNOTATION_GROUPMODE_USER_USER, VIDEOANNOTATION_GROUPMODE_GROUP_USER, VIDEOANNOTATION_GROUPMODE_ALL_USER))) {
                        $dataobject->userid = (int) $USER->id;
                        $dataobject->groupid = (int) $input['groupid'];
                    } else {
                        $dataobject->groupid = (int) $input['groupid'];
                    }
                    break;
                default:
                    return array("success" => false, "message" => "Access denied (invalid group mode).");
            }

            // Update records.

            $i = 0;
            try {
                $arr = explode(",", $input['orders']);
                foreach ($arr as $id) {
                    $dataobject->id = $id;
                    $dataobject->sortorder = $i;
                    $result = $DB->update_record('videoannotation_tags', $dataobject);
                    $i++;
                }
            } catch (dml_exeception $e) {
                return array("success" => false, "message" => "Database error");
            }
            return array("success" => true);

        case 'addevent':
            if (!isset($input['tagid']) or ! isset($input['starttime']) or ! isset($input['endtime'])) {
                return array("success" => false, "message" => "tagid or starttime or endtime is not given.");
            }

            if (!$DB->record_exists('videoannotation_tags', array('id' => $input['tagid']))) {
                return array("success" => false, "message" => "Tag does not exist.");
            }

            $videoannotation = get_videoannotation_tag($input['tagid']);
            $coursemodule = videoannotation_get_course_module_by_video_annotation($videoannotation->id);
            $modulecontext = context_module::instance($coursemodule->id);
            $canmanage = has_capability('mod/videoannotation:manage', $modulecontext);
            $cansubmit = has_capability('mod/videoannotation:submit', $modulecontext);
            $isadmin = is_siteadmin($USER->id);

            // Security check.

            if (!$isadmin) {
                // Case 1: "userid" not given, "groupid" not given (event will be owned by the activity)

                if (!isset($input['userid']) and !isset($input['groupid'])) {
                    // The user needs to have "manage" capability.

                    if (!$isadmin and !$canmanage) {
                        return array("success" => false, "message" => "mod/videoannotation:manage capability required.");
                    }

                // Case 2: "userid" given, "groupid" given (event will be owned by the group)
                } else if (isset($input['userid']) and isset($input['groupid'])) {

                    // The user needs to have "manage" or "submit" capability.

                    if (!$isadmin and !$canmanage and !$cansubmit) {
                        return array("success" => false, "message" => "mod/videoannotation:manage or mod/videoannotation:submit capability required.");
                    }

                    // The group has not submitted this activity yet.

                    if ($DB->record_exists('videoannotation_submissions', array('videoannotationid' => $videoannotation->id, 'groupid' => $input['groupid']))) {
                        return array("success" => false, "Cannot add event in a timeline that has already been submitted.");
                    }

                    // The activity needs to have group mode on.

                    if ($videoannotation->groupmode == NOGROUPS) {
                        return array("success" => false, "message" => "Group mode must be on.");
                    }

                    // The user ID given (required) needs to equal the requestor's user ID.

                    if ($USER->id != $input['userid']) {
                        return array("success" => false, "message" => "userid, if given, must be the requestor's user ID.");
                    }

                    // The user have to be in the given group.

                    if (!$DB->record_exists('groups_members', array('groupid' => $input['groupid'], 'userid' => $input['userid']))) {
                        return array("success" => false, "message" => "The given user must be in the given group.");
                    }

                // Case 3: "userid" given, "groupid" not given (tag will be owned by the user)
                } else if (isset($input['userid'])) {

                    // The user needs to have "manage" or "submit" capability.

                    if (!$isadmin and !$canmanage and !$cansubmit) {
                        return array("success" => false, "message" => "mod/videoannotation:manage or mod/videoannotation:submit capability required.");
                    }

                    // The user must not have submitted this activity yet.

                    if ($DB->record_exists('videoannotation_submissions', array('videoannotationid' => $videoannotation->id, 'userid' => $input['userid'], 'groupid' => null))) {
                        return array("success" => false, "Cannot add event in a timeline that has already been submitted.");
                    }

                    // The activity must have group mode off.

                    if ($videoannotation->groupmode != NOGROUPS) {
                        return array("success" => false, "message" => "Group mode must be off.");
                    }

                    // The user ID given needs to equal the requestor's user ID.

                    if ($USER->id != $input['userid']) {
                        return array("success" => false, "message" => "userid, if given, must be the requestor's user ID.");
                    }

                // Case 4: "userid" not given, "groupid" given
                } else {
                    // Not acceptable; complain and abort.

                    return array("success" => false, "message" => "userid must be given if groupid is given.");
                }
            }

            // Make sure that the tag record is still there.

            if (!$DB->record_exists('videoannotation_tags', array('id' => $input['tagid']))) {
                return array("success" => false, "errortype" => "writeconflict", "message" => "Another user might have deleted the tag you are editing.");
            }

            // Insert event record.

            $data = (object) array('tagid' => $input['tagid'], 'starttime' => $input['starttime'], 'endtime' => $input['endtime'], 'timecreated' => time());
            if (isset($input['content'])) {
                $data->content = $input['content'];
            } else {
                $data->content = "";
            }
            if (isset($input['userid'])) {
                $data->userid = $input['userid'];
            }
            if (isset($input['groupid'])) {
                $data->groupid = $input['groupid'];
            }
            if (isset($input['latitude'])) {
                $data->latitude = $input['latitude'];
            }
            if (isset($input['longitude'])) {
                $data->longitude = $input['longitude'];
            }
            if (isset($input['scope'])) {
                $data->scope = $input['scope'];
            }
            if (isset($input['level'])) {
                $data->level = $input['level'];
            }

            $lastid = $DB->insert_record('videoannotation_events', $data);
            if ($lastid !== false) {
                return array("success" => true, "id" => $lastid);
            } else {
                return array("success" => false, "message" => "Database error w");
            }

        case 'editevent':
            // id must be given

            if (!isset($input['id'])) {
                return array("success" => false, "message" => "id is not given.");
            }

            if (!$DB->record_exists('videoannotation_events', array('id' => $input['id']))) {
                return array("success" => false, "message" => "Event does not exist.");
            }

            $videoannotation = get_videoannotation_event($input['id']);
            $coursemodule = videoannotation_get_course_module_by_video_annotation($videoannotation->id);
            $modulecontext = context_module::instance($coursemodule->id);
            $canmanage = has_capability('mod/videoannotation:manage', $modulecontext);
            $cansubmit = has_capability('mod/videoannotation:submit', $modulecontext);
            $isadmin = is_siteadmin($USER->id);

            if (!$isadmin) {
                // Security check.

                switch ($videoannotation->groupmode) {
                    // Case 1: group mode is off

                    case NOGROUPS:
                        // The event must belong to the requestor and not a group.

                        if (!$DB->record_exists('videoannotation_events', array('id' => $input['id'], 'userid' => $USER->id, 'groupid' => null))) {
                            return array("success" => false, "message" => "Access denied (not owner of the event).");
                        }

                        // The requestor must not have submitted the activity.

                        if ($DB->record_exists('videoannotation_submissions', array('videoannotationid' => $videoannotation->id, 'userid' => $USER->id, 'groupid' => null))) {
                            return array("success" => false, "Cannot edit event in a timeline that has already been submitted.");
                        }

                        break;

                    // Case 2: group mode is "separate" or "visible"

                    case VIDEOANNOTATION_GROUPMODE_USER_USER:
                    case VIDEOANNOTATION_GROUPMODE_GROUP_USER:
                    case VIDEOANNOTATION_GROUPMODE_GROUP_GROUP:
                    case VIDEOANNOTATION_GROUPMODE_ALL_USER:
                    case VIDEOANNOTATION_GROUPMODE_ALL_GROUP:
                    case VIDEOANNOTATION_GROUPMODE_ALL_ALL:
                        // The event must belong to a group that the requestor belongs.
                        // Also, if group mode is "individual" or "read all", the tag must be belong to the requestor.

                        $sql = "SELECT g.*
                                  FROM {videoannotation_events} ve
                                  JOIN {groups_members} gm ON ve.groupid = gm.groupid
                                  JOIN {groups} g ON gm.groupid = g.id
                                 WHERE ve.id = " . (int) $input['id'] . " AND gm.userid = " . $USER->id;
                        $group = $DB->get_record_sql($sql);
                        if (!$group) {
                            return array("success" => false, "message" => "Access denied (not member of the owner of the event).");
                        }
                        if (in_array($videoannotation->groupmode, array(VIDEOANNOTATION_GROUPMODE_USER_USER, VIDEOANNOTATION_GROUPMODE_GROUP_USER, VIDEOANNOTATION_GROUPMODE_ALL_USER))) {
                            $sql .= " AND ve.userid = " . $USER->id;
                        }

                        // The requestor must not have submitted the activity.

                        if ($DB->record_exists('videoannotation_submissions', array('videoannotationid' => $videoannotation->id, 'groupid' => $group->id))) {
                            return array("success" => false, "Cannot edit event in a timeline that has already been submitted.");
                        }

                        break;

                    // Case 3: group mode is something else

                    default:
                        // Complain and abort

                        return array("success" => false, "message" => "Access denied (invalid group mode).");
                }
            }

            // Update record.

            $dataobject = new stdClass();
            $dataobject->timemodified = time();
            if (isset($input['tagid'])) {
                $dataobject->tagid = $input['tagid'];
            }
            if (isset($input['starttime'])) {
                $dataobject->starttime = $input['starttime'];
            }
            if (isset($input['endtime'])) {
                $dataobject->endtime = $input['endtime'];
            }
            if (isset($input['content'])) {
                $dataobject->content = $input['content'];
            }
            if (isset($input['latitude'])) {
                $dataobject->latitude = $input['latitude'];
            }
            if (isset($input['longitude'])) {
                $dataobject->longitude = $input['longitude'];
            }
            if (isset($input['scope'])) {
                $dataobject->scope = $input['scope'];
            }
            if (isset($input['level'])) {
                $dataobject->level = $input['level'];
            }

            $dataobject->id = $input['id'];
            try {
                $rs = $DB->update_record('videoannotation_events', (object) $dataobject);
                if (!$rs) {
                    return array("success" => false, "errortype" => "database", "message" => "Database error i");
                }
            } catch (dml_exception $e) {
                return array("success" => false, "errortype" => "writeconflict", "message" => " Another user might have deleted the tag or edited the event you are editing.");
            }
            return array("success" => true);

        case 'deleteevent':
            if (!isset($input['id'])) {
                return array("success" => false, "message" => "id is not given.");
            }

            $videoannotation = get_videoannotation_event($input['id']);
            $context = get_context_videoannotation($videoannotation->id);
            $coursemodule = videoannotation_get_course_module_by_video_annotation($videoannotation->id);
            $canmanage = has_capability('mod/videoannotation:manage', get_context_instance(CONTEXT_SYSTEM));
            $cansubmit = has_capability('mod/videoannotation:submit', get_context_instance(CONTEXT_SYSTEM));
            $isadmin = is_siteadmin($USER->id);

            // Security check.

            if (!$isadmin) {
                switch ($videoannotation->groupmode) {
                    // Case 1: group mode is off

                    case NOGROUPS:
                        // The event must belong to the requestor and not a group.

                        if (!$DB->record_exists('videoannotation_events', array('id' => $input['id'], 'userid' => $USER->id, 'groupid' => null))) {
                            return array("success" => false, "message" => "Access denied (not owner of the event).");
                        }

                        // The requestor must not have submitted the activity.

                        if ($DB->record_exists('videoannotation_submissions', array('videoannotationid' => $videoannotation->id, 'userid' => $USER->id, 'groupid' => null))) {
                            return array("success" => false, "Cannot edit event in a timeline that has already been submitted.");
                        }

                        break;

                    // Case 2: group mode is "separate" or "visible"

                    case VIDEOANNOTATION_GROUPMODE_USER_USER:
                    case VIDEOANNOTATION_GROUPMODE_GROUP_USER:
                    case VIDEOANNOTATION_GROUPMODE_GROUP_GROUP:
                    case VIDEOANNOTATION_GROUPMODE_ALL_USER:
                    case VIDEOANNOTATION_GROUPMODE_ALL_GROUP:
                    case VIDEOANNOTATION_GROUPMODE_ALL_ALL:
                        // The event must belong to a group that the requestor belongs.
                        // Also, if group mode is "individual" or  "read all", the event must be belong to the requestor.

                        $sql = "SELECT g.*
                                  FROM {videoannotation_events} ve
                                  JOIN {groups_members} gm ON ve.groupid = gm.groupid
                                  JOIN {groups} g ON gm.groupid = g.id
                                 WHERE ve.id = " . (int) $input['id'] . " AND gm.userid = " . $USER->id;
                        if (in_array($videoannotation->groupmode, array(VIDEOANNOTATION_GROUPMODE_USER_USER, VIDEOANNOTATION_GROUPMODE_GROUP_USER, VIDEOANNOTATION_GROUPMODE_ALL_USER))) {
                            $sql .= " AND ve.userid = " . $USER->id;
                        }
                        $group = $DB->get_record_sql($sql);
                        if (!$group) {
                            return array("success" => false, "message" => "Access denied (not member of the owner of the event).");
                        }

                        // The requestor must not have submitted the activity.

                        if ($DB->record_exists('videoannotation_submissions', array('videoannotationid' => $videoannotation->id, 'groupid' => $group->id))) {
                            return array("success" => false, "Cannot edit event in a timeline that has already been submitted.");
                        }

                        break;

                    // Case 3: group mode is something else

                    default:
                        // Complain and abort.

                        return array("success" => false, "message" => "Access denied (invalid group mode).");
                }
            }

            // Delete record.

            $rs = $DB->delete_records('videoannotation_events', array('id' => $input['id']));
            if (!$rs) {
                return array("success" => false, "message" => "Database error q");
            }

            return array("success" => true);

        default:
            return array("success" => false, "message" => "Unknown command \"${input['command']}\"");
    }
}
