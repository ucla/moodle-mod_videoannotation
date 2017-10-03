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
require_once($CFG->libdir . '/gradelib.php');

$id = optional_param('id', 0, PARAM_INT);          // Course module ID
$a = optional_param('a', 0, PARAM_INT);           // Assignment ID
$mode = optional_param('mode', 'all', PARAM_ALPHA);  // What mode are we in?
$groupid = optional_param('group', null, PARAM_INT);
global $DB, $PAGE, $CFG;
if ($id) {
    if (!$cm = get_coursemodule_from_id('videoannotation', $id)) {
        print_error("coursemodidincorrect", 'videoannotation');
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
    print_error("coursemodidincorrect", 'videoannotation');
}

if (!$modulecontext = context_module::instance($cm->id)) {
    print_error("coursemisconfigure", 'videoannotation');
}

require_login($course->id, false, $cm);
$PAGE->set_title('SUBMISSIONS');
$PAGE->set_pagetype('course');
$PAGE->set_heading('Submissions');
$PAGE->set_url('/mod/videoannotation/submissions.php', array('id' => $cm->id));
echo $OUTPUT->header();
require_capability('mod/videoannotation:grade', $modulecontext);

add_to_log($course->id, "videoannotation", "view", "view.php?id=$cm->id", "$videoannotation->id");

// Print the page header.
$strvideoannotations = get_string('modulenameplural', 'videoannotation');
$strvideoannotation = get_string('modulename', 'videoannotation');

$navlinks = array();
$navlinks[] = array('name' => $strvideoannotations, 'link' => "index.php?id=$course->id", 'type' => 'activity');
$navlinks[] = array('name' => format_string($videoannotation->name), 'link' => 'view.php?id='
    . $cm->id, 'type' => 'activityinstance');
$navlinks[] = array('name' => get_string('submissions', 'videoannotation'), 'link' => '',
    'type' => 'activityinstance');

$navigation = $PAGE->navbar;
?>

<style type="text/css">
    table.submissions td, table.submissions th {
        border:1px solid #DDDDDD;
        padding-left:5px;
        padding-right:5px;
        vertical-align:middle;
    }

    .flexible th {
        white-space:nowrap;
    }
</style>

<div style='text-align: right'><a href="report.php?id=<?php echo $id; ?>">
    <?php echo get_string('viewreport', 'videoannotation'); ?></a></div>

<?php
// Find out current groups mode.
require_once($CFG->libdir . '/grouplib.php');
switch ($videoannotation->groupmode) {
    case NOGROUPS:
        $currentgroup = 0;
        break;
    case VIDEOANNOTATION_GROUPMODE_USER_USER:
    case VIDEOANNOTATION_GROUPMODE_GROUP_USER:
    case VIDEOANNOTATION_GROUPMODE_GROUP_GROUP:
    case VIDEOANNOTATION_GROUPMODE_ALL_USER:
    case VIDEOANNOTATION_GROUPMODE_ALL_GROUP:
    case VIDEOANNOTATION_GROUPMODE_ALL_ALL:
        $groups = groups_get_all_groups($cm->course, 0, $cm->groupingid);
        if (!$groups) {
            error("There is no group for this activity.");
        }
        if ($groupid) {
            if (!in_array($groupid, array_keys($groups))) {
                error("The given group is not a group of this course.");
            }
            $currentgroup = $groupid;
        } else {
            foreach ($groups as $group) {
                $currentgroup = $group->id;
                break;
            }
        }
        break;
    default:
        error("Invalid group mode");
}

if (isset($groups) and count($groups) > 0 and $currentgroup) {
    echo "<form>";
    echo "<input type='hidden' name='id' value='" . $id . "' />";
    echo "Group: <select name='group'>";
    foreach ($groups as $g) {
        echo "<option value='" . $g->id . "' " . ($g->id == $currentgroup ? ' selected' : '') . ">{$g->name}</option>";
    }
    echo "</select>";
    echo "<input type='submit' value='Change' />";
    echo "</form>";
}

// If group mode on and there's a current group.
// then the users displayed are interset(members of the group, users who have "submit" capability).
// If group mode off.
// then the users displayed are (users who have "submit" capability).

$usersallowedtosubmit = get_users_by_capability($modulecontext,
        'mod/videoannotation:submit', 'u.id', '', '', '', $currentgroup, '', false);
if ($currentgroup) {
    $groupmembers = groups_get_members($currentgroup);
    $users = array_intersect(array_keys($groupmembers), array_keys($usersallowedtosubmit));
} else {
    $users = array_keys($usersallowedtosubmit);
}

if (!empty($CFG->enableoutcomes) and ! empty($gradinginfo->outcomes)) {
    $usesoutcomes = true;
} else {
    $usesoutcomes = false;
}

$tablecolumns = array('picture', 'fullname', 'grade', 'submissioncomment', 'timemodified', 'timemarked', 'status', 'finalgrade');
if ($usesoutcomes) {
    $tablecolumns[] = 'outcome'; // No sorting based on outcomes column.
}

$tableheaders = array('',
    get_string('fullname'),
    get_string('grade'),
    get_string('comment', 'videoannotation'),
    get_string('timesubmitted', 'videoannotation'),
    get_string('timegraded', 'videoannotation'),
    get_string('status'),
    get_string('finalgrade', 'grades'));
if ($usesoutcomes) {
    $tableheaders[] = get_string('outcome', 'grades');
}

require_once($CFG->libdir . '/tablelib.php');
$table = new flexible_table('mod-assignment-submissions');
$table->define_columns($tablecolumns);
$table->define_headers($tableheaders);
$table->define_baseurl($CFG->wwwroot . '/mod/videoannotation/submissions.php?id=' . $cm->id . '&amp;currentgroup=' . $currentgroup);

$table->sortable(false);
$table->collapsible(true);
$table->initialbars(true);

$table->column_suppress('picture');
$table->column_suppress('fullname');

$table->set_attribute('cellspacing', '0');
$table->set_attribute('id', 'attempts');
$table->set_attribute('class', 'submissions');
$table->set_attribute('width', '100%');

$table->no_sorting('finalgrade');
$table->no_sorting('outcome');

// Start working -- this is necessary as soon as the niceties are over.
$table->setup();

$table->pagesize(10, count($users));


$extrauserfields = get_extra_user_fields_sql($modulecontext);
$mainuserfields = user_picture::fields('u');

$userfields = explode(",", $mainuserfields);

$mainuserfields = implode(", ", $userfields);



if ($currentgroup) {
    $sql = "SELECT " . $mainuserfields . " , s.timesubmitted
                FROM mdl_user u
                LEFT JOIN mdl_videoannotation_submissions s ON s.videoannotationid = " .
            $videoannotation->id . " AND s.groupid = " . $currentgroup . "
                WHERE u.id IN (" . implode(',', $users) . ")";
} else {

    $sql = "SELECT " . $mainuserfields . " , s.timesubmitted
                FROM mdl_user u
                LEFT JOIN mdl_videoannotation_submissions s ON s.videoannotationid = " .
            $videoannotation->id . " AND u.id = s.userid AND s.groupid IS NULL
                WHERE u.id IN (" . implode(',', $users) . ")";
}

$ausers = $DB->get_records_sql($sql);

if ($ausers) {
    $gradinginfo = grade_get_grades($course->id, 'mod', 'videoannotation', $videoannotation->id, array_keys($ausers));

    foreach ($ausers as $auser) {
        $picture = $OUTPUT->user_picture($auser, array('courseid' => $course->id, 'popup' => false, 'alttext' => true));
        $userlink = '<a href="' . $CFG->wwwroot . '/user/view.php?id=' . $auser->id . '&amp;course='
                . $course->id . '">' . fullname($auser) . '</a>';
        if (isset($gradinginfo->items[0]->grades[$auser->id]->str_long_grade)) {
            $grade = $gradinginfo->items[0]->grades[$auser->id]->str_long_grade;
        } else {
            $grade = get_string('notgraded', 'videoannotation');
        }
        if (isset($gradinginfo->items[0]->grades[$auser->id]->str_feedback)) {
            $comment = $gradinginfo->items[0]->grades[$auser->id]->str_feedback;
        } else {
            $comment = get_string('nocomment', 'videoannotation');
        }
        if ($auser->timesubmitted) {
            $timesubmitted = userdate($auser->timesubmitted);
        } else {
            $timesubmitted = get_string('notsubmitted', 'videoannotation');
        }
        if (isset($gradinginfo->items[0]->grades[$auser->id]->dategraded)) {
            $timegraded = userdate($gradinginfo->items[0]->grades[$auser->id]->dategraded);
        } else {
            $timegraded = get_string('notgraded', 'videoannotation');
        }
        $userorgroupidstr = ($currentgroup ? '&group=' . $currentgroup : '&user=' . $auser->id);
        if (isset($gradinginfo->items[0]->grades[$auser->id]->dategraded)) {
            $status = '<a href="' . $CFG->wwwroot . '/mod/videoannotation/view.php?id='
                    . $id . $userorgroupidstr . '">' . get_string('update', 'videoannotation') . '</a>';
        } else {
            $status = '<a href="' . $CFG->wwwroot . '/mod/videoannotation/view.php?id='
                    . $id . $userorgroupidstr . '">' . get_string('grade', 'videoannotation') . '</a>';
        }
        $table->add_data(array($picture, $userlink, $grade, $comment, $timesubmitted, $timegraded, $status, ''));
    }
}

$table->print_html();
echo $OUTPUT->footer();