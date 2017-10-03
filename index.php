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
 * This script lists all the instances of videoannotation in a particular course
 *
 * @package    mod_videoannotation
 * @copyright  2015 UC Regents
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'/../../lib/moodlelib.php');

$id = required_param('id', PARAM_INT);

$PAGE->set_url('/mod/videoannotation/index.php', array('id' => $id));

if (!$course = $DB->get_record('course', array('id' => $id))) {
    print_error('invalidcourseid');
}

require_login($course);

$coursecontext = context_course::instance($course->id);
$params = array(
    'context' => $coursecontext
);
$event = \mod_videoannotation\event\course_module_instance_list_viewed::create($params);
$event->add_record_snapshot('course', $course);
$event->trigger();

// Print the header.
$strvideoannotations = get_string('modulenameplural', 'videoannotation');

$PAGE->navbar->add($strvideoannotations);
$PAGE->set_title($strvideoannotations);
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');

echo $OUTPUT->header();
echo $OUTPUT->heading($strvideoannotations);

// Get all the appropriate data.
if (!$videoannotations = get_all_instances_in_course('videoannotation', $course)) {
    notice(get_string('thereareno', 'moodle', $strvideoannotations),
        new moodle_url('/course/view.php', array('id' => $course->id)));
    die;
}

$canviewsomesubmissions = false;
$canviewsomereports = false;
foreach ($videoannotations as $key => $videoannotation) {
    $modulecontext = context_module::instance($videoannotation->coursemodule);
    $hasgradecapability = has_capability('mod/videoannotation:grade', $modulecontext);
    $hassubmitcapability = has_capability('mod/videoannotation:submit', $modulecontext);

    $canviewsubmission[$key] = $hasgradecapability;
    $canviewsomesubmissions |= $hasgradecapability;

    $canviewreport[$key] = $hasgradecapability || $hassubmitcapability;
    $canviewsomereports |= $hasgradecapability || $hassubmitcapability;
}

// Print the list of instances (your module will probably extend this).
$timenow  = time();
$strname  = get_string('name');
$strweek  = get_string('week');
$strtopic = get_string('topic');

$table = new html_table();

if ($course->format == 'weeks') {
    $table->head = array($strweek, $strname);
    $table->align = array('center', 'left');
} else if ($course->format == 'topics') {
    $table->head  = array($strtopic, $strname);
    $table->align = array('center', 'left');
} else {
    $table->head  = array($strname);
    $table->align = array('left');
}
if ($canviewsomesubmissions) {
    $table->head[] = get_string('viewsubmissions', 'videoannotation');
    $table->align[] = 'left';
}

if ($canviewsomereports) {
    $table->head[] = get_string('viewreport', 'videoannotation');
    $table->align[] = 'left';
}

foreach ($videoannotations as $key => $videoannotation) {
    if (!$videoannotation->visible) {
        // Show dimmed if the mod is hidden.
        $link = "<a class=\"dimmed\" href=\"view.php?id=$videoannotation->coursemodule\">$videoannotation->name</a>";
    } else {
        // Show normal if the mod is visible.
        $link = "<a href=\"view.php?id=$videoannotation->coursemodule\">$videoannotation->name</a>";
    }

    if ($course->format == 'weeks' or $course->format == 'topics') {
        $data = array($videoannotation->section, $link);
    } else {
        $data = array($link);
    }

    if ($canviewsubmission[$key]) {
        $cm = videoannotation_get_course_module_by_video_annotation($videoannotation->id);
        switch ($videoannotation->groupmode) {
            case NOGROUPS:
                $groupid = null;
                break;
            default:
                $groupid = 'all';
        }
        list($totalsubmissioncount, $ungradedsubmissioncount) =
            videoannotation_get_submission_count($videoannotation->id, $groupid);

        $viewsubmissionslink = "<a href='{$CFG->wwwroot}/mod/videoannotation/submissions.php?id={$videoannotation->coursemodule}'>"
        . $totalsubmissioncount . ' ' . get_string('total', 'videoannotation') . ', '
        . $ungradedsubmissioncount . ' ' . get_string('ungraded', 'videoannotation') . '</a>';

        $data[] = $viewsubmissionslink;
    } else if ($canviewsomesubmissions) {
        $data[] = '';
    }

    if ($canviewreport[$key]) {
        $viewreportlink = "<a href='{$CFG->wwwroot}/mod/videoannotation/report.php?id={$videoannotation->coursemodule}'>"
        . get_string('viewreport', 'videoannotation') . '</a>';

        $data[] = $viewreportlink;
    } else if ($canviewsomereports) {
        $data[] = '';
    }

    $table->data[] = $data;
}

// Display the table.
echo html_writer::table($table);

// Finish the page.
echo $OUTPUT->footer($course);
