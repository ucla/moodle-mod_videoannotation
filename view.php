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

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');
require_once($CFG->dirroot .'/lib/formslib.php');
require_once($CFG->libdir.'/gradelib.php');

$id = optional_param('id', 0, PARAM_INT); // Course_module ID.
$a  = optional_param('a', 0, PARAM_INT);  // Videoannotation instance ID.
$userid = optional_param('user', $USER->id, PARAM_INT);
$groupid = optional_param('group', null, PARAM_INT);
global $DB, $PAGE, $CFG;
if ($id) {
    if (! $cm = get_coursemodule_from_id('videoannotation', $id)) {
        print_error('coursemodidincorrect', 'videoannotation');
    }

    if (! $course = $DB->get_record('course', array('id' => $cm->course))) {
        print_error('coursemisconfigure', 'videoannotation');
    }

    if (! $videoannotation = $DB->get_record('videoannotation', array('id' => $cm->instance))) {
        print_error('coursemodincorrect', 'videoannotation');
    }

} else if ($a) {
    if (! $videoannotation = $DB->get_record('videoannotation', array('id' => $a))) {
        print_error('coursemodincorrect', 'videoannotation');
    }
    if (! $course = $DB->get_record('course', array('id' => $videoannotation->course))) {
        print_error('coursemisconfigure', 'videoannotation');
    }
    if (! $cm = get_coursemodule_from_instance('videoannotation', $videoannotation->id, $course->id)) {
        print_error('coursemodidincorrect', 'videoannotation');
    }

} else {
    print_error('specifyid', 'videoannotation');
}
 // Print the page header.

require_login($course, true, $cm);
$PAGE->set_url('/mod/videoannotation/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($videoannotation->name));
$PAGE->set_pagelayout('incourse');
$PAGE->set_pagetype('course');
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();
// SSC-829
// The user must have mod/videoannotation:view capability to view this page.
// This implies that users not logged in or are guests cannot view this page.

$modulecontext = context_module::instance($cm->id);
$canview = require_capability('mod/videoannotation:view', $modulecontext);
$cansubmit = has_capability('mod/videoannotation:submit', $modulecontext);
$canmanage = has_capability('mod/videoannotation:manage', $modulecontext);
$isadmin = is_siteadmin($userid);

$params = array(
    'objectid' => $PAGE->cm->instance,
    'context' => $PAGE->context
);
$event = \mod_videoannotation\event\course_module_viewed::create($params);
$event->add_record_snapshot('course', $course);
$event->add_record_snapshot($PAGE->cm->modname, $videoannotation);
$event->trigger();

// SSC-1058
// If group mode = NOGROUPS:
//   * needs to have "manage" capability (then we use the given user ID) or "view" capability (then we use this user's ID)
//   * ignore the group ID passed in and treat groupid = null
// If group mode = SEPARATEGROUPS or VISIBLEGROUPS:
//   * deny access unless user has "manage" or "view" capability
//   * "manage": if groupid given, then use that group, else use the first group in the course; there must be at least
//      one group in the course
//   * "view": if groupid given, then use that group, else use the first group the user is in; the user must be in this
//      group and there must be at least one group in the course.

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

    // Case VIDEOANNOTATION_GROUPMODE_USER_USER.
    case VIDEOANNOTATION_GROUPMODE_GROUP_USER:

        if ($isadmin or $canmanage) {
            $groups = groups_get_all_groups($course->id, null, $cm->groupingid);
            if (!$groups) {
                print_error('onegroup', 'videoannotation');
            }
            if ($groupid) {
                if (!isset($groups[$groupid])) {
                    print_error('notgroup', 'videoannotation');
                }
            } else {
                foreach (array_keys($groups) as $groupid) {
                    break;
                }
            }
            $readonly = (!$isadmin and !groups_is_member($groupid, $USER->id));
        } else if ($cansubmit or $canview) {
            $groups = groups_get_all_groups($course->id, null, $cm->groupingid);
            if (!$groups) {
                print_error('onegroup', 'videoannotation');
            }
            if ($groupid) {
                if (!isset($groups[$groupid])) {
                    print_error('notingroup', 'videoannotation');
                }
            } else {
                foreach (array_keys($groups) as $groupid) {
                    break;
                }
            }
            $readonly = (!groups_is_member($groupid, $USER->id));
        } else {
            require_capability('mod/videoannotation:view', $modulecontext);
        }
        break;

    case VIDEOANNOTATION_GROUPMODE_GROUP_GROUP:

        if ($isadmin or $canmanage) {
            $groups = groups_get_all_groups($course->id, null, $cm->groupingid);
            if (!$groups) {
                print_error('onegroup', 'videoannotation');
            }
            if ($groupid) {
                if (!isset($groups[$groupid])) {
                    print_error('notgroup', 'videoannotation');
                }
            } else {
                foreach (array_keys($groups) as $groupid) {
                    break;
                }
            }
            $readonly = (!$isadmin and !groups_is_member($groupid, $USER->id));
        } else if ($cansubmit or $canview) {
            $groups = groups_get_all_groups($course->id, $USER->id, $cm->groupingid);
            if (!$groups) {
                print_error('onegroup', 'videoannotation');
            }
            if ($groupid) {
                if (!isset($groups[$groupid])) {
                    print_error('notingroup', 'videoannotation');
                }
            } else {
                foreach (array_keys($groups) as $groupid) {
                    break;
                }
            }
            $readonly = (!groups_is_member($groupid, $USER->id));
        } else {
            require_capability('mod/videoannotation:view', $modulecontext);
        }
        break;
    default:
        print_error('invalidgroupmode', 'videoannotation');
}

// See if there is a submission record (created either if the student has submitted something
// Or if the instructor has graded on the activity).

if ($videoannotation->groupmode == NOGROUPS) {
    $submission = $DB->get_record('videoannotation_submissions', array('videoannotationid' => $videoannotation->id,
     'userid' => $userid, 'groupid' => null));
} else {
    $submission = $DB->get_record('videoannotation_submissions', array('videoannotationid' => $videoannotation->id,
     'groupid' => $groupid));
}

// SSC-1185 Fix $readonly to be true for certain users if the annotation has been submitted.
if (!$isadmin and !$canmanage) {
    $readonly = ($readonly or $submission);
}

// If clip select is 0 (use student's clip), find it in in the database, ask to create one if not found.
// If clip select is 1 (professor specified clip), find it in the database, complain if not found.

switch ($videoannotation->clipselect) {
    case 0:
        if ($videoannotation->groupmode == NOGROUPS) {
            $clip = $DB->get_record('videoannotation_clips', array('videoannotationid' => $videoannotation->id,
                    'userid' => $userid, 'groupid' => null));
        } else {
            $clip = $DB->get_record('videoannotation_clips', array('videoannotationid' => $videoannotation->id,
                    'groupid' => $groupid));
        }
        break;

    case 1:
        $clip = $DB->get_record('videoannotation_clips', array('videoannotationid' => $videoannotation->id,
                'userid' => null, 'groupid' => null));
        if (!$clip) {
            $a = new stdClass();
            $a->vaid = $videoannotation->id;
            $a->userid = $userid;
            print_error('clipnotfound', 'videoannotation', '', $a);
        }
        break;

    default:
        $a = new stdClass();
        $a->clipselect = $videoannotation->clipselect;
        $a->vaid = $videoannotation->id;
        $a->userid = $userid;
        print_error('invalidclipselect', 'videoannotation', '', $a);
}

// Get start position for clip.
if ($clip) {
    $starttime = $clip->playabletimestart;
}

// If the URL is a TNA permalink,
// Use the web service to translate it into a RTMP link.

foreach ($CFG->tnapermalinkurl as $idx => $tnapermalinkurl) {
    if ($clip and stripos($clip->url, $tnapermalinkurl) === 0) {
        @list($uuid, $offset) = explode(',', substr($clip->url, strlen($tnapermalinkurl)));
        if ($uuid) {
            $content = file_get_contents($CFG->tnawebserviceurl[$idx] . '?action=uuidToFileName&uuid=' . urlencode($uuid));
            $contentobj = json_decode($content);
            if (preg_match('/^(\d{4})\-(\d{2})\-(\d{2})_(\d{2})(\d{2})/', $contentobj->filename, $matches)) {
                $rtmpurl = $CFG->tnastreamerurl . "mp4:{$matches[1]}/{$matches[1]}-{$matches[2]}/{$matches[1]}-{$matches[2]}-{$matches[3]}/" . basename($contentobj->filename, '.txt') . '.mp4';
                $clip->url = $rtmpurl;
                break;
            }
        }
    }
}

// If a submission was made, insert the submission into the database and prevent further changes to the submission
// by anyone.
if (has_capability('mod/videoannotation:submit', $modulecontext) &&
        !has_capability('mod/videoannotation:grade', $modulecontext)) {
    // If not read-only, there is no submission record, the clip is defined, and the student has pressed the submit
    // button, create a submission record with the current time as the submission time.
    if (!$readonly && !$submission && $clip &&
            optional_param('submit', "", PARAM_RAW) == "Submit annotation for grading") {
        $submission = new stdClass();
        $submission->videoannotationid = $videoannotation->id;
        $submission->userid = $userid;
        $submission->groupid = $groupid;
        $submission->clipid = $clip->id;
        $submission->timesubmitted = $submission->timecreated = time();
        $submission->timemodified = time();
        $DB->insert_record('videoannotation_submissions', $submission);
        $readonly = true;
    }
}

// After submitting, no one should be able to edit the annotations.
if ($submission) {
   $readonly = true;
}

// Print the main part of the page.

// Show the help button.
echo "<div style='float: left; padding: 3px;'>";
echo $OUTPUT->help_icon('help', 'videoannotation');
echo "</div>";
echo "<div style='float: left; padding: 3px 3px 3px 100px;'>";
if (optional_param('printable', false, PARAM_BOOL)) {
    echo "<a href='?id=" . $id . "'>Exit Full Screen</a>";
} else {
    echo "<a href='?id=" . $id . "&printable=1'>Full Screen</a>";
    //echo "<br />";
    echo "<div id='groupmodetext'></div>";
    // SSC-1176
    // Adding a list of the last few changes made by other users.
    echo "<div id='lastchanges'></div>";
    // SSC-1191: add a notification when the clip has changed.
    echo "<div id='clipchanged'></div>";

    // SSC-1084.
    // Print description/instruction field.
    echo "<br />";
    echo $videoannotation->intro;
}
echo "</div>";

if (!optional_param('printable', false, PARAM_BOOL)) {
    echo "<div style=\"float: right; text-align: right; margin: 5px;\">";

    // Show the user or group drop-down/selection box.
    switch ($videoannotation->groupmode) {
        case NOGROUPS:
        case VIDEOANNOTATION_GROUPMODE_USER_USER:
            // No drop-down/selection box.
            break;
        case VIDEOANNOTATION_GROUPMODE_ALL_USER:
            // User drop-down/selection box.
            break;
        case VIDEOANNOTATION_GROUPMODE_GROUP_USER:
        case VIDEOANNOTATION_GROUPMODE_GROUP_GROUP:
        case VIDEOANNOTATION_GROUPMODE_GROUP_USER:
        case VIDEOANNOTATION_GROUPMODE_ALL_GROUP:
        case VIDEOANNOTATION_GROUPMODE_ALL_ALL:
            // Group drop-down/selection box.

            if (isset($groups) and count($groups) > 1 and $groupid) {
                echo "<form>";
                echo "<input type='hidden' name='id' value='" . $id . "' />";
                echo "Group: <select name='group' onchange='this.form.submit()'>";
                foreach ($groups as $g) {
                    echo "<option value='" . $g->id . "' " . ($g->id == $groupid ? ' selected' : '') . ">{$g->name}</option>";
                }
                echo "</select>";
                echo "</form>";
            }
            break;
    }

    // Show the "View submitted video annotations" link if the user can grade submissions.

    $modulecontext = context_module::instance($cm->id);
    if (has_capability('mod/videoannotation:grade', $modulecontext)) {
        list($totalsubmissioncount, $ungradedsubmissioncount) =
            videoannotation_get_submission_count($videoannotation->id, $groupid);
        echo "<a href='submissions.php?id={$id}'>"
        . get_string('viewsubmissions', 'videoannotation') . " ("
        . $totalsubmissioncount . ' ' . get_string('total', 'videoannotation') . ', '
        . $ungradedsubmissioncount . ' ' . get_string('ungraded', 'videoannotation') . ')</a><br />';
    }

    // If a clip is defined (either by the instructor or the student), show the link to the report.

    if ($clip):
    ?>


        <a href='report.php?id=<?php echo $id; ?>&group=<?php echo $groupid; ?>'>
            <?php echo get_string('viewreport', 'videoannotation'); ?></a> |

    <?php
    endif;

    // SSC-1190: adding annotation import link.
    ?>
    <a href='import.php?id=<?php echo $id; ?>&group=<?php echo $groupid; ?>'>
        <?php echo get_string('importannotation', 'videoannotation'); ?></a>

    <?php

    // If the user can grade, print the grade form.

    $modulecontext = context_module::instance($cm->id);
    if (has_capability('mod/videoannotation:grade', $modulecontext)) {

        global $CFG, $COURSE;

        if (optional_param('grade', null, PARAM_INT) != null || optional_param('feedback', 0, PARAM_RAW) != null) {
            if ($videoannotation->groupmode == NOGROUPS) {
                $gradeuserids = array($userid);
            } else {
                $members = groups_get_members($groupid);
                $gradeuserids = array_keys($members);
            }

            foreach ($gradeuserids as $gradeuserid) {
                grade_update('mod/videoannotation', $cm->course, 'mod', 'videoannotation', $videoannotation->id, (int) $groupid,
                    array(
                    'userid' => $gradeuserid,
                    'rawgrade' => optional_param('grade', null, PARAM_INT),
                    'feedback' => optional_param('feedback', 0, PARAM_RAW)
                    ), array (
                    'itemname' => $videoannotation->name . (isset($groups[$groupid]) ? ' (' . $groups[$groupid]->name . ')' : '')
                    ));

                if ($submission) {
                    $submission->grade = optional_param('grade', 0, PARAM_INT);
                    $submission->gradecomment = optional_param('feedback', 0, PARAM_RAW);
                    $submission->timegraded = $submission->timemodified = time();
                    $DB->update_record('videoannotation_submissions', $submission);
                } else {
                    $submission = new stdClass();
                    $submission->videoannotationid = $videoannotation->id;
                    $submission->userid = $gradeuserid;
                    $submission->groupid = $groupid;
                    $submission->clipid = $clip ? $clip->id : null;
                    $submission->grade = optional_param('grade', null, PARAM_INT);
                    $submission->gradecomment = optional_param('feedback', 0, PARAM_RAW);
                    $submission->timegraded = $submission->timecreated = time();
                    $submission->timemodified = time();
                    $DB->insert_record('videoannotation_submissions', $submission);
                }
            }
        }

        ?>
        <br style='clear: both;' />
        <a href="#" id="gradingbtn">Grading</a>
        <form action="<?php global $ME; echo $ME . '?id=' . $cm->id; ?>" method="POST">

        <table id="grading" style="display:none;">
            <tr>
                <td>Existing score:</td>
                <td>
                    <?php echo isset($submission->grade) ? $submission->grade : '(not graded)'; ?>
                </td>
            </tr>
            <tr>
                <td>Existing feedback: 
                <td>
                    <?php echo isset($submission->gradecomment) ? $submission->gradecomment : '(none)'; ?>
                </td>
            </tr>
            <tr>
                <td>Score:</td>
                <td>
                    <input type="text" name="grade" value="<?php echo isset($submission->grade) ? $submission->grade : ''; ?>" />
                </td>
            </tr>
            <tr>
                <td>Feedback:</td>
                <td style="text-align:left">
                    <?php print_textarea(false, 6, 50, 0, 0, 'feedback', strip_tags(isset($submission->gradecomment) ?
                    $submission->gradecomment : ''), $COURSE->id); ?>
                </td>
            </tr>
            <tr>
                <td></td>
                <td>
                    <input type="hidden" name="user" value="<?php echo $userid; ?>" />
                    <input type="hidden" name="group" value="<?php echo $groupid; ?>" />
                    <input type="submit" value="Grade" />
                </td>
            </tr>
        </table>
        </form>
        <?php
    }

    echo '</div>';
}

//  If no clip is defined , show a message and (if the user has not submitted) show the option to add one.

if (!$clip):
?>

    <div><?php echo get_string('noclipdefined', 'videoannotation'); ?></div>

    <?php if (!$submission):
    ?>
    <div>
        <a href="clips.php?id=<?php echo $id; echo ($groupid ? '&group=' . $groupid : ''); ?>">
            <?php echo get_string('addclip', 'videoannotation'); ?>
        </a>
    </div>

    <div>
        <a href="<?php echo $CFG->wwwroot . "/course/view.php?id=" . $course->id; ?>">
            <?php echo get_string('canceladdclip', 'videoannotation'); ?>
        </a>
    </div>
    <?php endif;
    ?>

<?php endif;
?>

<?php
// If users pick their own videos, a clip is defined for this user and if the user has not submitted, show the option to edit it.

if ($clip and $videoannotation->clipselect == 0 and !$submission):
?>

    <div style='text-align: right'>
        <input type="button" class="playerbutton dark"
        onclick="window.location='clips.php?id=<?php 
        echo $id;
        echo ($groupid ? '&group=' . $groupid : ''); ?>'" value="<?php 
        echo get_string('editclip', 'videoannotation'); ?>"/>
    </div>

<?php endif; ?>

<?php
// If a clip is defined, show the video player and the annotation UI.

if ($clip): ?>
    <link type="text/css" href="jquery-ui-1.8.2.custom/css/ui-lightness/jquery-ui-1.8.2.custom.css" rel="stylesheet" />
    <link type="text/css" href="timeline.css" rel="stylesheet" />
    <link type="text/css" href="vat.css" rel="stylesheet" />

    <!-- adding color picker script -->
    <script type="text/javascript" src="jscolor/jscolor.js"></script>

    <script type="text/javascript"
        src="https://maps.googleapis.com/maps/api/js?&sensor=false">
    </script>
    <script src="geotag.js" type="text/javascript"></script> 
    <script src="timeline.js" type="text/javascript"></script>
    <script src="jquery.validate.js" type="text/javascript"></script>
    <script src="jquery.text-overflow.min.js" type="text/javascript"></script>
    <script src="jwplayer-6.12/jwplayer.js" type="text/javascript"></script>

    <script type="text/javascript">
    $(document).ready( function () {
        $('#gradingbtn').click(function (e) {
            e.preventDefault();
            $('#grading').toggle('fast');
        });
    });
    var playabletimestart = <?php echo $clip->playabletimestart; ?>;
    var playabletimeend = <?php echo $clip->playabletimeend; ?>;
    var starttime = <?php echo $starttime; ?>;
    var clipurl = <?php echo json_encode($clip->url); ?>;
    var videoheight = <?php echo $clip->videoheight; ?>;
    var videowidth = <?php echo $clip->videowidth; ?>;
    var groupmode = <?php echo $videoannotation->groupmode; ?>;
    var readonly = <?php echo json_encode($readonly); ?>;
    var groupid = <?php echo json_encode($groupid); ?>;
    var userid = <?php echo $userid; ?>;
    var clipid = <?php echo $clip->id; ?>;
    var VIDEOANNOTATION_GROUPMODE_GROUP_GROUP = <?php echo VIDEOANNOTATION_GROUPMODE_GROUP_GROUP ?>;
    var VIDEOANNOTATION_GROUPMODE_GROUP_USER = <?php echo VIDEOANNOTATION_GROUPMODE_GROUP_USER ?>;

    // Extract the file, provider and streamer from the URL given.
    // Provider and streamers are used by RTMP clips.
    <?php if (preg_match('/^(rtmp:\/\/.*?\/.*?)\/(.*)/', $clip->url, $matches)): ?>
    var rtmp = true;
    var jwplayerparamsfile = '<?php echo $matches[1];?>mp4:<?php echo $matches[2];?>';
    <?php else: ?>
    var rtmp = false;
    var jwplayerparamsfile = '<?php echo $clip->url; ?>';
    <?php endif; ?>

    </script>
    <?php
    $PAGE->requires->js_amd_inline("
    require(['jquery', 'jqueryui'], function() {
        jQuery(document).ready(function() {
            function updateJWPlayerControls(time) {
                // Display the current position and the duration.

                var formatTime = function(time) {
                    var minutes = Math.floor(time / 60);
                    var seconds = Math.floor(time % 60);
                    return minutes + ':' + (seconds >= 10 ? seconds : ('0' + seconds));
                }

                jQuery('#flashPlayerTime').text((Math.floor(time * 10) / 10) + '/' + (Math.floor(timeline.maxTime * 10) / 10));
    
                // Set the value of the scrub bar.
    
                jQuery('#flashPlayerScrubBar').slider('option', 'value', Math.round(time * 1000));
            }

            function seekTo(time, absolute, upJWPlayer, upTimeline) {
                var currentTime = timeline.getCurrentTime();
    
                if (typeof jwplayer() === 'undefined' || typeof currentTime === 'undefined')
                    return;

                if (absolute) {
                    currentTime = time;
                } else {
                    currentTime += time;
                }

                currentTime = Math.max(currentTime, playabletimestart);
                currentTime = Math.min(currentTime, playabletimeend);

                if (upJWPlayer) {
                    if (jwplayer().getState() != 'BUFFERING') {
                        if (typeof lastSeekCallTime == 'undefined' || new Date().getTime() - lastSeekCallTime >= 200) {                            
                            jwplayer().seek(currentTime);
    
                            lastSeekCallTime = new Date().getTime();
                            updateJWPlayerControls(time);
                        }
                    }

                }

                if (upTimeline)
                    updateTimeline(currentTime);
            }

            function updateTimeline(positionGiven) {
                var position = (typeof positionGiven != 'undefined') ? positionGiven : jwplayer().getPosition();

                if (typeof timeline !== 'undefined' && timeline instanceof CSAVTimeline && position >= 0) {
                    timeline.setCurrentTime(position);
                    delete timeline.noRedraw.currentTime;
                    timeline.redraw();
                }
            };

            // Unload the timeline.

            $('.TimelineFrame').children().remove();
            $('.TimelineFrame').removeClass('.TimelineFrame');
            timeline = undefined;

            // Create the timeline.

            blockTimeUpdate = false;
            originalMuteState = false;

            var params = {
                'id': clipid,
                'clipId': clipid,
                'userId': userid ? userid : 'undefined',
                'groupId': groupid ? groupid : 'undefined',
                'selector': '#TimelineFrame_Timeline1',
                'dialogBoxSelector': '#dialogbox',
                'minTime': playabletimestart,
                'maxTime': playabletimeend,
                'currentScaleIndex': 1,
                'possibleScales': [0.5, 1, 2, 4, 8, 16],
                'pixelPerSecond': 20,
                'minorMarkerInterval': 1,
                'majorMarkerInterval': 10,
                'zoomFactor': 1,
                'readOnly': readonly,
                'streamUpdate': groupmode == VIDEOANNOTATION_GROUPMODE_GROUP_GROUP ? 10000 : 0,
                'readOnlyGroup': groupmode == VIDEOANNOTATION_GROUPMODE_GROUP_USER ? true : false
            }
            if (!(params['userId'] > 0)) delete params['userId'];
            if (!(params['groupId'] > 0)) delete params['groupId'];

            timeline = new CSAVTimeline(params);

            timeline.addListener('currentTimeDragStart', function() {
                blockTimeUpdate = true;
                if (jwplayer().getState() == 'PAUSED')
                    pausePosition = 0;
            });

            timeline.addListener('currentTimeDragStop', function() {
                blockTimeUpdate = false;
            });

            timeline.addListener('eventBarResizeStart', function() {
                blockTimeUpdate = true;
                if (jwplayer().getState() == 'PAUSED')
                    pausePosition = 0;
            });

            timeline.addListener('eventBarResizeStop', function() {
                blockTimeUpdate = false;
            });


            timeline.addListener('timeChanged', function(time) {
                if (typeof time != 'undefined' && time >= 0 && this === timeline) {
                    if (jwplayer().getState() == 'PAUSED')
                        pausePosition = 0;

                    seekTo(time, true, true, false);
                }
            });

            timeline.addListener('onlineUsersUpdated', function(onlineUsers) {
                var str;
                if (typeof onlineUsers.length == 'undefined')
                    return;
                else if (onlineUsers.length == 0)
                    str = '(none)';
                else if (onlineUsers.length > 3)
                    str = onlineUsers[0] + ', ' + onlineUsers[1] + ', ' + onlineUsers[2] + ', ...';
                else
                    str = onlineUsers.join(', ');
                jQuery('#groupmodetext').text('Group members online: ' + str);
            });

            timeline.addListener('tagAdded', function(tagObj) {
                var name = tagObj.name; 
                if (name == undefined)
                    return;
                if (name.length > 22)
                    name = name.slice(0,21) + ' ...';

                timeline.lastChanges.unshift('Added Tag \'' + name + '\'');
                if (timeline.lastChanges.length > 3)
                    timeline.lastChanges.pop();
                var str = timeline.lastChanges.join(', ');
                jQuery('#lastchanges').html('Last Changes: ' + str);
            });

            timeline.addListener('tagRemoved', function(tagName, conflict) {
                var name = tagName; 
                if (name == undefined)
                    return;
                if (name.length > 22)
                    name = name.slice(0,21) + ' ...';

                if (conflict == true)
                    timeline.lastChanges.unshift('<span style=\'color:red\'>Removed Tag \'' + name + '\'</span>');
                else
                    timeline.lastChanges.unshift('<span style=\'color:black\'>Removed Tag \'' + name + '\'</span>');

                if (timeline.lastChanges.length > 3)
                    timeline.lastChanges.pop();
                var str = timeline.lastChanges.join(', ');
                jQuery('#lastchanges').html('Last Changes: ' + str);
            });

            //SSC-1191: add a notification when the clip has changed.

            timeline.addListener('clipChanged', function() {
                jQuery('#clipchanged').html('<span style=\'color:red\'>The clip has been modified! Please reload the page to refresh the clip.</span>');
            });

            // Scrub slider.

            jQuery('#flashPlayerScrubBar').slider({
                range: 'min',
                min: playabletimestart * 1000,
                max: playabletimeend * 1000,
                start: function() {
                    originalMuteState = jwplayer().getMute();
                    jwplayer().setMute(true);
                    blockTimeUpdate = true;
                    if (jwplayer().getState() == 'PAUSED')
                        pausePosition = 0;
                },
                slide: function(event, ui) {
                    seekTo(ui.value / 1000, true, true, true);
                },
                stop: function() {
                    jwplayer().setMute(originalMuteState);
                    blockTimeUpdate = false;
                }
            });

            // Mute/unmute button.

            jQuery('#flashPlayerMute').click(function() {
                if (!jQuery('#flashPlayerVolumeBar').slider('option', 'disabled'))
                    jwplayer().setMute(!jwplayer().getMute());
            });

            // Volume slider.

            jQuery('#flashPlayerVolumeBar').slider({
                range: 'min',
                min: 0,
                max: 100,
                slide: function(event, ui) {
                    jwplayer().setVolume(ui.value);
                }
            });
            jQuery('#flashPlayerVolumeBar').find('.ui-slider-range').css('background-color', '#F6A828');

            // Control buttons.

            $('#rewindbutton').click(function(event) {
                if (jwplayer().getState() == 'PAUSED' || jwplayer().getState() == 'IDLE')
                    pausePosition = playabletimestart;
                seekTo(playabletimestart, true, true, true);
                if (jwplayer().getState() == 'IDLE')
                    jwplayer().play();
                event.stopPropagation();
            });

            $('#backward30button').click(function(event) {
                if (jwplayer().getState() == 'PAUSED' || jwplayer().getState() == 'IDLE')
                    pausePosition = playabletimestart;
                seekTo(-30, false, true, true);
                if (jwplayer().getState() == 'IDLE')
                    jwplayer().play();
                event.stopPropagation();
            });

            $('#backward5button').click(function(event) {
                if (jwplayer().getState() == 'PAUSED' || jwplayer().getState() == 'IDLE')
                    pausePosition = playabletimestart;
                seekTo(-5, false, true, true);
                if (jwplayer().getState() == 'IDLE')
                    jwplayer().play();
                event.stopPropagation();
            });

            $('#playpausebutton').click(function(event) {
                jwplayer().play();
                event.stopPropagation();
            });

            $('#forward025button').click(function(event) {
                if (jwplayer().getState() == 'PAUSED' || jwplayer().getState() == 'IDLE')
                    pausePosition = playabletimestart;
                seekTo(1/4, false, true, true);
                event.stopPropagation();
            });

            $('#forward05button').click(function(event) {
               if (jwplayer().getState() == 'PAUSED' || jwplayer().getState() == 'IDLE')
                    pausePosition = playabletimestart;
                seekTo(1/2, false, true, true);
                event.stopPropagation();
            });

            $('#forward1button').click(function(event) {
               if (jwplayer().getState() == 'PAUSED' || jwplayer().getState() == 'IDLE')
                    pausePosition = playabletimestart;
                seekTo(1, false, true, true);
                event.stopPropagation();
            });

            $('#forward5button').click(function(event) {
                if (jwplayer().getState() == 'PAUSED' || jwplayer().getState() == 'IDLE')
                    pausePosition = playabletimestart;
                seekTo(5, false, true, true);
                event.stopPropagation();
            });

            $('#forward30button').click(function(event) {
                if (jwplayer().getState() == 'PAUSED' || jwplayer().getState() == 'IDLE')
                    pausePosition = playabletimestart;
                seekTo(30, false, true, true);
                event.stopPropagation();
            });

            // Instruction dialog and link.

            jQuery('#instructioncontent').dialog({
                modal: true,
                autoOpen: false,
                width: 750,
                buttons: {
                    'Ok': function() {
                        jQuery(this).dialog('close');
                    }
                }
            }).bind('dialogopen', function () {
                jwplayer().resize(0, jwplayer().getHeight());
            }).bind('dialogclose', function () {
                jwplayer().resize(videowidth, jwplayer().getHeight());
            });

            jQuery('#instructionlink').click(function(event) {
                jQuery('#instructioncontent').dialog('open');
                event.preventDefault();
            });

            // Controls should be disabled by default.
            // When enabling controls, set the values of the scrub bar and the volume bar because they might not be initialized.

            var enableControls = function() {
                jQuery('#flashPlayerScrubBar,#flashPlayerVolumeBar').slider('enable');
                jQuery('#flashPlayerVolumeBar').slider('option', 'value', jwplayer().getMute() ? 0 : jwplayer().getVolume());
                jQuery('#rewindbutton,#backward30button,#backward5button,#forward025button,#forward05button,#forward1button,#forward5button,#forward30button').removeAttr('disabled');
            };

            var disableControls = function() {
                jQuery('#flashPlayerScrubBar,#flashPlayerVolumeBar').slider('disable');
                jQuery('#playpausebutton').find('.IconPlay').removeClass('IconPause').addClass('IconPlay');
                jQuery('#rewindbutton,#backward30button,#backward5button,#forward025button,#forward05button,#forward1button,#forward5button,#forward30button').attr('disabled', 'disabled');
            };

            disableControls();

            // Create JWPlayer's initialization parameters.
            var jwplayerParams = {
                height: videoheight,
                width: videowidth,
                controls: true,
                autostart: true,
                allowscriptaccess: 'always',
                allowfullscreen: 'true',
                volume: 66,
                mute: false,
                events: {
                    onIdle: function(evt) {
                        // Change the play/pause button's icon to play.

                        jQuery('#playpausebutton').find('.IconPause').removeClass('IconPause').addClass('IconPlay');
                    },

                    onMute: function(evt) {
                        // Change to mute/unmute icon depending on the mute state.
    
                        if (evt.mute)
                            jQuery('#flashPlayerMute').removeClass('IconUnmute').addClass('IconMute');
                        else
                            jQuery('#flashPlayerMute').removeClass('IconMute').addClass('IconUnmute');
                    },

                    onPause: function(evt) {
                        // Make sure that controls are enabled.

                        enableControls();

                        jQuery('#playpausebutton').find('.IconPause').removeClass('IconPause').addClass('IconPlay');
                    },

                    onPlay: function(evt) {                        
                        // Enable controls.

                        enableControls();

                        // Change the play/pause button's icon to pause.

                        jQuery('#playpausebutton').find('.IconPlay').removeClass('IconPlay').addClass('IconPause');
                    },

                    onIdle: function(evt) {
                        // Stop recording of all tags.

                        timeline.stopRecording(timeline.tags);
                        timeline.redraw();
                    },

                    onTime: function(evt) {
                        // If pausePosition is set and that time has passed, pause the player and clear the variable.

                        if (typeof pausePosition != 'undefined' && pausePosition <= evt.position) {
                            if (!blockTimeUpdate) {
                                delete pausePosition;
                            }
                            jwplayer().pause();
                        }

                        if (blockTimeUpdate)
                            return;

                        updateJWPlayerControls(evt.position);
                        updateTimeline(evt.position);

                        // SSC-995: Show text of 'current' events in divs under #textbox.

                        var prevPlayingEvents = (typeof timeline.playingEvents != 'undefined' ? timeline.playingEvents : []);
                        var currentPlayingEvents = [];
                        for (var idx in timeline.events) {
                            if (timeline.events[idx].getStartTime() <= timeline.getCurrentTime() &&
                                timeline.getCurrentTime() < timeline.events[idx].getEndTime())
                                currentPlayingEvents.push(timeline.events[idx]);
                        }

                        // For each item in currentPlayingEvents that is not in prevPlayingEvents,
                        // Add the item's text to the text box.

                        for (var idx in currentPlayingEvents) {
                            if (timeline.inArray(currentPlayingEvents[idx], prevPlayingEvents,
                                function (a,b) {return a.getId() == b.getId();}) === undefined) {
                                jQuery('#textbox').append('<div id=\'textboxitem_event' + currentPlayingEvents[idx].getId() + '\' style=\'padding: 3px;\'><span></span>: <span style=\'font-size: 85%\'></span></div>');
                                jQuery('#textboxitem_event' + currentPlayingEvents[idx].getId())
                                .css('background-color', currentPlayingEvents[idx].getTag().getColor() || 'gray')
                                .css('color', 'white')
                                .css('padding', '3px');

                                jQuery('#textboxitem_event' + currentPlayingEvents[idx].getId())
                                .find('span').eq(0)
                                .text(currentPlayingEvents[idx].getTag().getName());

                                jQuery('#textboxitem_event' + currentPlayingEvents[idx].getId())
                                .find('span').eq(1)
                                .text(currentPlayingEvents[idx].getComment());
                            }
                        }

                        // For each item in prevPlayingEvents that is not in currentPlayingEvents,
                        // Remove the item's text from the text box.

                        for (var idx in prevPlayingEvents) {
                            if (timeline.inArray(prevPlayingEvents[idx], currentPlayingEvents,
                                function (a,b) {return a.getId() == b.getId();}) === undefined) {
                                jQuery('#textboxitem_event' + prevPlayingEvents[idx].getId()).remove();
                            }
                        }

                        timeline.playingEvents = currentPlayingEvents;
                    },

                    onVolume: function(evt) {
                        // Set the value of the volume bar.

                        jQuery('#flashPlayerVolumeBar').slider('option', 'value', evt.volume);
                    }
                }
            };

            if (rtmp) {
                jwplayerParams['provider'] = 'rtmp';
            }
            jwplayerParams['file'] = jwplayerparamsfile;

            // Create a JWPlayer instance.

            jwplayer('flashPlayerArea1').setup(jwplayerParams);
            var startflag = true;
            jwplayer('flashPlayerArea1').onPlay( function(evt) {
                if (startflag) {
                    jwplayer().seek(Number(starttime));
                    startflag = false;
                }          
            });
        });
    });
    "); ?>

    <br style="clear: both;" />
    <table align="center" style="padding: 10px; width: 86%;">
                    <tr valign="bottom">
                            <td align="center" nowrap width="10%">
                                <div id="flashPlayerArea1" style="clear: both;"></div>
                    <div id="flashPlayerArea2" style="clear: both;">
                    <div id="flashPlayerScrubBar" title="Slide to change position" style="width: 60%; height: 16px; float: left;"></div>
                    <div id="flashPlayerTime" style="width: 25%; float: left; font-size: 80%;"></div>
                    <div id="flashPlayerMute" class="IconUnmute" title="Click to mute/unmute" style="float: left; width: 16px; height: 16px;
                    background-image: url(jquery-ui-1.8.2.custom/css/ui-lightness/images/ui-icons_228ef1_256x240.png);"></div>
                    <div id="flashPlayerVolumeBar" style="width: 10%; height: 16px; float: left;" title="Slide to change volume"></div>
                </div>
                <div id="flashPlayerArea3" style="clear: both;">
                    <button type="button" id="rewindbutton" class="playerbutton dark" title="Go to the start position of the clip">
                        <div class="PlayerIcon IconRewind" style=""></div>
                        <div style="float: right;">Rewind</div>
                    </button>
                    <button type="button" id="backward30button" class="playerbutton dark" title="Go back 30 seconds">
                        <div class="PlayerIcon IconBackward" style=""></div>
                        <div style="float: right;">30s</div>
                    </button>
                    <button type="button" id="backward5button" class="playerbutton dark" title="Go back 5 seconds">
                        <div class="PlayerIcon IconBackward" style=""></div>
                        <div style="float: right;">5s</div>
                    </button>
                    <button type="button" id="playpausebutton" class="playerbutton dark" title="Play/pause">
                        <div class="PlayerIcon IconPlay" style=""></div>
                        <div style="float: right;"></div>
                    </button>
                    <button type="button" id="forward025button" class="playerbutton dark" title="Go forward 1/4 second and pause">
                        <div class="PlayerIcon IconForwardPause" style=""></div>
                        <div style="float: right;">&frac14;s</div>
                    </button>
                    <button type="button" id="forward05button" class="playerbutton dark" title="Go forward 1/2 second and pause">
                        <div class="PlayerIcon IconForwardPause" style=""></div>
                        <div style="float: right;">&frac12;s</div>
                    </button>
                    <button type="button" id="forward1button" class="playerbutton dark" title="Go forward 1 second and pause">
                        <div class="PlayerIcon IconForwardPause" style=""></div>
                        <div style="float: right;">1s</div>
                    </button>
                    <button type="button" id="forward5button" class="playerbutton dark" title="Go forward 5 seconds">
                        <div class="PlayerIcon IconForward" style=""></div>
                        <div style="float: right;">5s</div>
                    </button>
                    <button type="button" id="forward30button" class="playerbutton dark" title="Go forward 30 seconds">
                        <div class="PlayerIcon IconForward" style=""></div>
                        <div style="float: right;">30s</div>
                    </button>
                </div>
                            </td>
            <td id="textbox" align="left" style="vertical-align: middle; padding-left: 5%;">

            </td>
                    </tr>
            </table>

    <br style="clear: both;" />

    <div id="TimelineFrame_Timeline1" class="TimelineFrame"></div>

    <br style="clear: both;" />

<?php
endif;

if (has_capability('mod/videoannotation:submit', $modulecontext)
and !has_capability('mod/videoannotation:grade', $modulecontext)) {
    // If not read-only, there is no submission record and the clip is defined, the student can still submit.

    if (!$readonly && !$submission && $clip) {
        global $ME;
        $submitaction = $ME . '?id=' . $cm->id;
        if ($groupid !== null) {
            $submitaction .= '&group=' . $groupid;
        }
        ?>
        <form action="<?php echo $submitaction ?>" method="POST">
        <div><input type="submit" name="submit" value="Submit annotation for grading" /></div>
        </form>
        <?php
    }

    // If the there is a time submitted in the submission record,
    // the student has submitted before and we should display the time.

    if ($submission && isset($submission->timesubmitted) && $submission->timesubmitted) {
        ?>
        <div><?php echo get_string('submittedon', 'videoannotation'), userdate($submission->timesubmitted); ?></div>
        <?php
    }

    // If there is grading (score and feedback) available,
    // display them.

    if ($submission) {
        ?>

        <div><h4>Feedback:</h4></div>
        <div><b>Score:</b> <p> <?php if (isset($submission->grade)) {
            echo $submission->grade;
        } ?> </p> </div>
        <div><b>Comment:</b> <?php if (isset($submission->gradecomment)) {
            echo $submission->gradecomment;
        } ?> </div>
        <?php
    }
}

// Finish the page.
echo $OUTPUT->footer();
