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


function videoannotation_restore_mods($mod, $restore) {
    global $CFG;

    $status = true;

    // Get record from mdl_backup_ids.

    $data = backup_getid($restore->backup_unique_code, $mod->modtype, $mod->id);
    if (!$data) {
        error("In videoannotation_restore_mods(): {$mod->modtype} #{$mod->id} does not have a record in backup_ids table.");
    }

    // Build the videoannotation record structure.

    $datainfo = $data->info;

    $videoannotation->course = $restore->course_id;
    $videoannotation->name = backup_todb($datainfo['MOD']['#']['NAME']['0']['#']);
    $videoannotation->intro = backup_todb($datainfo['MOD']['#']['INTRO']['0']['#']);
    $videoannotation->introformat = backup_todb($datainfo['MOD']['#']['INTROFORMAT']['0']['#']);
    $videoannotation->clipselect = backup_todb($datainfo['MOD']['#']['CLIPSELECT']['0']['#']);
    $videoannotation->groupmode = backup_todb($datainfo['MOD']['#']['GROUPMODE']['0']['#']);
    $videoannotation->timecreated = backup_todb($datainfo['MOD']['#']['TIMECREATED']['0']['#']);
    $videoannotation->timemodified = backup_todb($datainfo['MOD']['#']['TIMEMODIFIED']['0']['#']);

    $newid = insert_record('videoannotation', $videoannotation);
    if (!$newid) {
        return false;
    }

    backup_putid($restore->backup_unique_code, $mod->modtype, $mod->id, $newid);

    $status &= videoannotation_restore_clips($mod, $restore, $datainfo);

    if (restore_userdata_selected($restore, 'videoannotation', $mod->id)) {
        $status &= videoannotation_restore_tags($mod, $restore, $datainfo);
        $status &= videoannotation_restore_events($mod, $restore, $datainfo);
        $status &= videoannotation_restore_submissions($mod, $restore, $datainfo);
    }

    return $status;
}

function videoannotation_restore_clips($mod, $restore, $datainfo) {
    global $CFG;

    $status = true;

    if (!isset($datainfo['MOD']['#']['CLIPS']['0']['#']['CLIP'])) {
        return $status;
    }

    foreach ($datainfo['MOD']['#']['CLIPS']['0']['#']['CLIP'] as $clipinfo) {
        $clip = new stdClass();
        $clip->videoannotationid = backup_todb($clipinfo['#']['VIDEOANNOTATIONID']['0']['#']);
        $clip->userid = backup_todb($clipinfo['#']['USERID']['0']['#']);
        $clip->groupid = backup_todb($clipinfo['#']['GROUPID']['0']['#']);
        $clip->url = backup_todb($clipinfo['#']['URL']['0']['#']);
        $clip->playabletimestart = backup_todb($clipinfo['#']['PLAYABLETIMESTART']['0']['#']);
        $clip->playabletimeend = backup_todb($clipinfo['#']['PLAYABLETIMEEND']['0']['#']);
        $clip->videowidth = backup_todb($clipinfo['#']['VIDEOWIDTH']['0']['#']);
        $clip->videoheight = backup_todb($clipinfo['#']['VIDEOHEIGHT']['0']['#']);
        $clip->timecreated = backup_todb($clipinfo['#']['TIMECREATED']['0']['#']);
        $clip->timemodified = backup_todb($clipinfo['#']['TIMEMODIFIED']['0']['#']);

        // Get the new video annotation instance ID from mdl_backup_ids table.

        $videoannotation = backup_getid($restore->backup_unique_code, 'videoannotation', $clip->videoannotationid);
        if ($videoannotation) {
            $clip->videoannotationid = $videoannotation->new_id;
        } else {
            error("In videoannotation_restore_clips(): Videoannotation #{$clip->videoannotation} "
            . "does not have a record in backup_ids table.");
        }

        // Get the new user ID and group ID from mdl_backup_ids table.

        if ($clip->userid) {
            $user = backup_getid($restore->backup_unique_code, 'user', $clip->userid);
            if ($user) {
                $clip->userid = $user->new_id;
            } else {
                error("In videoannotation_restore_clips(): User #{$clip->userid} does not have a record in backup_ids table.");
            }
        }
        if ($clip->groupid) {
            $group = backup_getid($restore->backup_unique_code, 'groups', $clip->groupid);
            if ($group) {
                $clip->groupid = $group->new_id;
            } else {
                error("In videoannotation_restore_clips(): Group #{$clip->groupid} does not have a record in backup_ids table.");
            }
        }

        $newid = insert_record('videoannotation_clips', $clip);
        if (!$newid) {
            return false;
        }
        $oldid = backup_todb($clipinfo['#']['ID']['0']['#']);
        backup_putid($restore->backup_unique_code, 'videoannotation_clips', $oldid, $newid);
    }

    return $status;
}

function videoannotation_restore_events($mod, $restore, $datainfo) {
    global $CFG;

    $status = true;

    if (!isset($datainfo['MOD']['#']['EVENTS']['0']['#']['EVENT'])) {
        return $status;
    }
    foreach ($datainfo['MOD']['#']['EVENTS']['0']['#']['EVENT'] as $eventinfo) {
        $event = new stdClass();
        $event->tagid = backup_todb($eventinfo['#']['TAGID']['0']['#']);
        $event->userid = backup_todb($eventinfo['#']['USERID']['0']['#']);
        $event->groupid = backup_todb($eventinfo['#']['GROUPID']['0']['#']);
        $event->starttime = backup_todb($eventinfo['#']['STARTTIME']['0']['#']);
        $event->endtime = backup_todb($eventinfo['#']['ENDTIME']['0']['#']);
        $event->content = backup_todb($eventinfo['#']['CONTENT']['0']['#']);
        $event->timecreated = backup_todb($eventinfo['#']['TIMECREATED']['0']['#']);
        $event->timemodified = backup_todb($eventinfo['#']['TIMEMODIFIED']['0']['#']);
        $event->scope = backup_todb($eventinfo['#']['SCOPE']['0']['#']);
        $event->latitude = backup_todb($eventinfo['#']['LATITUDE']['0']['#']);
        $event->longitude = backup_todb($eventinfo['#']['LONGITUDE']['0']['#']);
        // Get the new tag ID from mdl_backup_ids table.

        $tag = backup_getid($restore->backup_unique_code, 'videoannotation_tags', $event->tagid);
        if ($tag) {
            $event->tagid = $tag->new_id;
        } else {
            error("In videoannotation_restore_events(): Tag #{$event->tagid} does not have a record in backup_ids table.");
        }

        // Get the new user ID and group ID from mdl_backup_ids table.

        if ($event->userid) {
            $user = backup_getid($restore->backup_unique_code, 'user', $event->userid);
            if ($user) {
                $event->userid = $user->new_id;
            } else {
                error("In videoannotation_restore_events(): User #{$event->userid} does not have a record in backup_ids table.");
            }
        }
        if ($event->groupid) {
            $group = backup_getid($restore->backup_unique_code, 'groups', $event->groupid);
            if ($group) {
                $event->groupid = $group->new_id;
            } else {
                error("In videoannotation_restore_events(): Group #{$event->groupid} does not have a record in backup_ids table.");
            }
        }

        $newid = insert_record('videoannotation_events', $event);
        if (!$newid) {
            return false;
        }

        $oldid = backup_todb($eventinfo['#']['ID']['0']['#']);
        backup_putid($restore->backup_unique_code, 'videoannotation_events', $oldid, $newid);
    }
    return $status;
}

function videoannotation_restore_submissions($mod, $restore, $datainfo) {
    global $CFG;

    $status = true;

    if (!isset($datainfo['MOD']['#']['SUBMISSIONS']['0']['#']['SUBMISSION'])) {
        return $status;
    }

    foreach ($datainfo['MOD']['#']['SUBMISSIONS']['0']['#']['SUBMISSION'] as $submissioninfo) {
        $submission = new stdClass();
        $submission->videoannotationid = backup_todb($submissioninfo['#']['VIDEOANNOTATIONID']['0']['#']);
        $submission->userid = backup_todb($submissioninfo['#']['USERID']['0']['#']);
        $submission->groupid = backup_todb($submissioninfo['#']['GROUPID']['0']['#']);
        $submission->clipid = backup_todb($submissioninfo['#']['CLIPID']['0']['#']);
        $submission->grade = backup_todb($submissioninfo['#']['GRADE']['0']['#']);
        $submission->gradecomment = backup_todb($submissioninfo['#']['GRADECOMMENT']['0']['#']);
        $submission->timesubmitted = backup_todb($submissioninfo['#']['TIMESUBMITTED']['0']['#']);
        $submission->timegraded = backup_todb($submissioninfo['#']['TIMEGRADED']['0']['#']);
        $submission->timecreated = backup_todb($submissioninfo['#']['TIMECREATED']['0']['#']);
        $submission->timemodified = backup_todb($submissioninfo['#']['TIMEMODIFIED']['0']['#']);

        // Get the new video annotation instance ID from mdl_backup_ids table.

        $videoannotation = backup_getid($restore->backup_unique_code, 'videoannotation', $submission->videoannotationid);
        if ($videoannotation) {
            $submission->videoannotationid = $videoannotation->new_id;
        } else {
            error("In videoannotation_restore_submissions(): Video annotation #{$submission->videoannotationid} "
            . "does not have a record in backup_ids table.");
        }

        // Get the new user ID and group ID from mdl_backup_ids table.

        if ($submission->userid) {
            $user = backup_getid($restore->backup_unique_code, 'user', $submission->userid);
            if ($user) {
                $submission->userid = $user->new_id;
            } else {
                error("In videoannotation_restore_submissions(): User #{$submission->userid} "
                . "does not have a record in backup_ids table.");
            }
        }
        if ($submission->groupid) {
            $group = backup_getid($restore->backup_unique_code, 'groups', $submission->groupid);
            if ($group) {
                $submission->groupid = $group->new_id;
            } else {
                error("In videoannotation_restore_submissions(): Group #{$submission->groupid} "
                . "does not have a record in backup_ids table.");
            }
        }

        // Get the new clip ID from mdl_backup_ids table.

        $clip = backup_getid($restore->backup_unique_code, 'videoannotation_clips', $submission->clipid);
        if ($clip) {
            $submission->clipid = $clip->new_id;
        } else {
            error("In videoannotation_restore_submissions(): Clip #{$submission->clipid} "
            . "does not have a record in backup_ids table.");
        }

        $newid = insert_record('videoannotation_submissions', $submission);
        if (!$newid) {
            return false;
        }

        $oldid = backup_todb($submissioninfo['#']['ID']['0']['#']);
        backup_putid($restore->backup_unique_code, 'videoannotation_submissions', $oldid, $newid);
    }

    return $status;
}

function videoannotation_restore_tags($mod, $restore, $datainfo) {
    global $CFG;

    $status = true;

    if (!isset($datainfo['MOD']['#']['TAGS']['0']['#']['TAG'])) {
        return $status;
    }

    foreach ($datainfo['MOD']['#']['TAGS']['0']['#']['TAG'] as $taginfo) {
        $tag = new stdClass();
        $tag->clipid = backup_todb($taginfo['#']['CLIPID']['0']['#']);
        $tag->userid = backup_todb($taginfo['#']['USERID']['0']['#']);
        $tag->groupid = backup_todb($taginfo['#']['GROUPID']['0']['#']);
        $tag->name = backup_todb($taginfo['#']['NAME']['0']['#']);
        $tag->color = backup_todb($taginfo['#']['COLOR']['0']['#']);
        $tag->sortorder = backup_todb($taginfo['#']['SORTORDER']['0']['#']);
        $tag->timecreated = backup_todb($taginfo['#']['TIMECREATED']['0']['#']);
        $tag->timemodified = backup_todb($taginfo['#']['TIMEMODIFIED']['0']['#']);

        // Get the new clip ID from mdl_backup_ids table.

        $clip = backup_getid($restore->backup_unique_code, 'videoannotation_clips', $tag->clipid);
        if ($clip) {
            $tag->clipid = $clip->new_id;
        } else {
            error("In videoannotation_restore_tags(): Clip #{$tag->clipid} "
            . "does not have a record in backup_ids table.");
        }

        // Get the new user ID and group ID from mdl_backup_ids table.

        if ($tag->userid) {
            $user = backup_getid($restore->backup_unique_code, 'user', $tag->userid);
            if ($user) {
                $tag->userid = $user->new_id;
            } else {
                error("In videoannotation_restore_tags(): User #{$tag->userid} "
                . "does not have a record in backup_ids table.");
            }
        }
        if ($tag->groupid) {
            $group = backup_getid($restore->backup_unique_code, 'groups', $tag->groupid);
            if ($group) {
                $tag->groupid = $group->new_id;
            } else {
                error("In videoannotation_restore_tags(): Group #{$tag->groupid} "
                . "does not have a record in backup_ids table.");
            }
        }

        $newid = insert_record('videoannotation_tags', $tag);
        if (!$newid) {
            return false;
        }

        $oldid = backup_todb($taginfo['#']['ID']['0']['#']);
        backup_putid($restore->backup_unique_code, 'videoannotation_tags', $oldid, $newid);
    }

    return $status;
}