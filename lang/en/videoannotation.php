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
 * English strings for Video Annotation.
 *
 * @package   mod_videoannotation
 * @copyright 2015 UC Regents
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['videoannotation'] = 'Video Annotation';
$string['pluginname'] = 'Video Annotation';
$string['modulename'] = 'Video Annotation';
$string['modulenameplural'] = 'Video Annotations';

$string['videoannotationintro'] = 'Description';
$string['videoannotationname'] = 'Video annotation name';

$string['preview'] = 'Preview';
$string['clip'] = 'Clip';
$string['clips'] = 'Clips';
$string['clipselect'] = 'Clip source';
$string['studentspickownclips'] = 'Students pick their own clips';
$string['usespecifiedclip'] = 'Use specified clip';
$string['clipurl'] = 'Clip URL';
$string['playabletimestart'] = 'Playable start time (seconds)';
$string['playabletimeend'] = 'Playable end time (seconds)';
$string['videowidth'] = 'Video width (pixels)';
$string['videoheight'] = 'Video height (pixels)';
$string['tagname'] = 'Tag name';
$string['tags'] = 'Tags';
$string['events'] = 'Events';
$string['eventstart'] = 'Event start time (seconds)';
$string['eventend'] = 'Event end time (seconds)';
$string['eventcontent'] = 'Content';
$string['uid'] = 'UID';

$string['submitted'] = 'Submitted';
$string['submissions'] = 'Submissions';
$string['viewsubmissions'] = 'View submitted video annotations';
$string['report'] = 'Report';
$string['viewreport'] = 'View annotation report';
$string['downloadreport'] = 'Download report';
$string['ungraded'] = 'ungraded';
$string['total'] = 'total';
$string['submittedon'] = 'Annotation submitted on ';
$string['timesubmitted'] = 'Time submitted';
$string['timegraded'] = 'Time graded';
$string['notsubmitted'] = 'Not submitted';
$string['notgraded'] = 'Not graded';
$string['nocomment'] = 'No comment';
$string['update'] = 'Update';
$string['grade'] = 'Grade';
$string['comment'] = 'Comment';

$string['addclip'] = 'Add clip';
$string['clipadded'] = 'Clip added.';
$string['editclip'] = 'Update clip';
$string['clipedited'] = 'Clip updated.';
$string['noclipdefined'] = 'No clip defined.';
$string['canceladdclip'] = 'Cancel';

$string['validationpositivenum'] = 'This must be a non-negative number.';
$string['validationpositiveint'] = 'This must be a non-negative integer.';
$string['mustbelessthan'] = 'must be less than';

$string['videoannotation:addinstance'] = 'Add a new video annotation activity';
$string['videoannotation:grade'] = 'Grade video annotations';
$string['videoannotation:view'] = 'View video annotation';
$string['videoannotation:add'] = 'Add video annotation';
$string['videoannotation:delete'] = 'Delete video annotation';
$string['videoannotation:edit'] = 'Edit video annotation';
$string['videoannotation:manage'] = 'Manage video annotation';
$string['videoannotation:submit'] = 'Submit video annotation';

$string['pluginadministration'] = 'VideoAnnotation administration';

$string['group'] = 'Group';
$string['groupmode'] = 'Group mode';
$string['groupmodeoff'] = 'No groups';
$string['groupmodeuseruser'] = 'Individual';
$string['groupmodegroupuser'] = 'Visible groups';
$string['groupmodegroupgroup'] = 'Separate groups';
$string['groupmodealluser'] = 'All/User';
$string['groupmodeallgroup'] = 'All/Group';
$string['groupmodeallall'] = 'All/All';
$string['invalidgroupmode'] = 'Invalid group mode';

$string['streamupdate'] = 'When other group members make changes';
$string['default'] = 'Default';
$string['streamupdateoff'] = 'Do not update screen';
$string['streamupdateon'] = 'Update screen';

$string['color'] = 'Color';
$string['scope'] = 'Scope';
$string['latitude'] = 'Latitude';
$string['longitude'] = 'Longitude';

$string['importannotation'] = 'Import Annotation';
$string['importfile'] = 'Select a \".csv\" File:';
$string['cantimport'] = 'You can\'t import a file for this annotation becuase either the annotation has already been submitted or you don\'t have sufficient permissions.';
$string['importtags'] = 'Import only tags (Do not import events)';
$string['notvalid'] = 'Trying to import tags/events without valid clip';
$string['importsuccess'] = 'The Import Succeeded!';
$string['groupselect'] = 'Choose a group to import to';
$string['groupname'] = 'Group Name';
$string['fullname'] = 'Full Name';

$string['introeditor'] = 'Write carefully and About the Intro Editor';
$string['introeditor_help'] = 'This is the help for the intro editor';
$string['help'] = 'Help on Video Annotation';
$string['help_help'] = 'Help here';

$string['coursemodidincorrect'] = 'Course Module ID was incorrect';
$string['coursemisconfigure'] = 'Course is misconfigured';
$string['coursemodincorrect'] = 'Course module is incorrect';
$string['specifyid'] = 'you must specify a course_module ID or an instance ID';
$string['vaidincorrect'] = 'Video annotation activity ID was incorrect';
$string['vididincorrect'] = 'Video annotation ID was incorrect';
$string['clipnotfound'] = 'Clip not found for video annotation {$a->vaid} and user {$a->userid}';
$string['invalidclipselect'] = 'Invalid clipselect value {$a->clipselect} for video annotation {$a->vaid} and user {$a->$userid}';
$string['groupmustbe'] = 'Group must be given';
$string['notingroup'] = 'Not in given group';
$string['onegroup'] = 'Must have at least one group';
$string['notgroup'] = 'Not a valid group';
$string['importsyntax'] = 'The import file contains sytax errors';

$string['tnastreamerurl'] = 'TNA Streamer URL';
$string['tnastreamerurl_desc'] = '';

$string['search:activity'] = 'Video annotation - activity information';
