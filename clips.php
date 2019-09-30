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
require_once($CFG->libdir.'/moodlelib.php');
require_once($CFG->dirroot .'/lib/formslib.php');

$id   = optional_param('id', 0, PARAM_INT);          // Course module ID
$a    = optional_param('a', 0, PARAM_INT);           // Video annotation ID
$mode = optional_param('mode', 'all', PARAM_ALPHA);  // What mode are we in?
$groupid = optional_param('group', null, PARAM_INT);
$isadmin = is_siteadmin();

global $DB;

if ($id) {
    if (! $cm = get_coursemodule_from_id('videoannotation', $id)) {
        print_error('coursemodidincorrect', 'videoannotation');
    }

    if (! $videoannotation = $DB->get_record("videoannotation", array("id" => $cm->instance))) {
        print_error('vididincorrect', 'videoannotation');
    }

    if (! $course = $DB->get_record("course", array("id" => $videoannotation->course))) {
        print_error('coursemisconfigure', 'videoannotation');
    }
} else {
    if (!$videoannotation = $DB->get_record("videoannotation", array("id" => $a))) {
        print_error('coursemodincorrect', 'videoannotation');
    }
    if (! $course = $DB->get_record("course", array("id" => $videoannotation->course))) {
        print_error('coursemisconfigure', 'videoannotation');
    }
    if (! $cm = get_coursemodule_from_instance("videoannotation", $videoannotation->id, $course->id)) {
        print_error('coursemodidincorrect', 'videoannotation');
    }
}

// Print page header.
$strvideoannotations = get_string('modulenameplural', 'videoannotation');
$strvideoannotation  = get_string('modulename', 'videoannotation');
require_login($course->id, false, $cm);
$PAGE->set_url('/mod/videoannotation/clips.php', array('id' => $cm->id));
$PAGE->set_heading('');
$PAGE->set_title(format_string($videoannotation->name));
$modulecontext = context_module::instance($cm->id);
require_capability('mod/videoannotation:view', $modulecontext);

add_to_log($course->id, "videoannotation", "view", "view.php?id=$cm->id", "$videoannotation->id");

if ($videoannotation->groupmode == NOGROUPS) {
    $groupid = null;
    $clip = $DB->get_record('videoannotation_clips',
        array('videoannotationid' => $videoannotation->id, 'userid' => $USER->id, 'groupid' => null));
} else {
    if (!$isadmin && !$groupid) {
        print_error('groupmustbe', 'videoannotation');
    }
    if (!$isadmin && !groups_is_member($groupid)) {
        print_error('notingroup', 'videoannotation');
    }
    $clip = $DB->get_record('videoannotation_clips', array('videoannotationid' => $videoannotation->id, 'groupid' => $groupid));
}

class clip_form extends moodleform {
    public function definition() {
        global $COURSE, $CFG;
        $mform = & $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'group');
        $mform->setType('group', PARAM_INT);

        // Clip URL.

        $mform->addElement('text', 'clipurl', get_string('clipurl', 'videoannotation'));
        $mform->setDefault('clipurl', '');
        $mform->addRule('clipurl', null, 'required', null, 'client');
        $mform->setType('clipurl', PARAM_TEXT);

        // Playable start time.

        $mform->addElement('text', 'playabletimestart', get_string('playabletimestart', 'videoannotation'));
        $mform->setDefault('playabletimestart', '');
        $mform->addRule('playabletimestart', null, 'required', null, 'client');
        $mform->addRule('playabletimestart',
            get_string('validationpositivenum', 'videoannotation'), 'regex', '/^\d+(\.\d+)?$/', 'client');
        $mform->setType('playabletimestart', PARAM_INT);

        // Playable end time.

        $mform->addElement('text', 'playabletimeend', get_string('playabletimeend', 'videoannotation'));
        $mform->setDefault('playabletimeend', '');
        $mform->addRule('playabletimeend', null, 'required', null, 'client');
        $mform->addRule('playabletimeend',
            get_string('validationpositivenum', 'videoannotation'), 'regex', '/^\d+(\.\d+)?$/', 'client');
        $mform->setType('playabletimeend', PARAM_INT);

        // Video width.

        $mform->addElement('text', 'videowidth', get_string('videowidth', 'videoannotation'));
        $mform->setDefault('videowidth', '');
        $mform->addRule('videowidth', null, 'required', null, 'client');
        $mform->addRule('videowidth', get_string('validationpositiveint', 'videoannotation'), 'regex', '/^\d+$/', 'client');
        $mform->setType('videowidth', PARAM_INT);

        // Video height.

        $mform->addElement('text', 'videoheight', get_string('videoheight', 'videoannotation'));
        $mform->setDefault('videoheight', '');
        $mform->addRule('videoheight', null, 'required', null, 'client');
        $mform->addRule('videoheight', get_string('validationpositiveint', 'videoannotation'), 'regex', '/^\d+$/', 'client');
        $mform->setType('videoheight', PARAM_INT);

        $mform->addElement('button', 'preview', get_string('preview', 'videoannotation'));

        $mform->addElement('html', '<div id="flashPlayerArea1"></div>');

        $this->add_action_buttons(true, get_string('submit'));

        // SSC-568,668,799.
        // Insert JavaScript code for JWPlayer and the preview logic into the form.

        $mform->addElement('html', '<script type="text/javascript" src="' . $CFG->wwwroot .
            '/mod/videoannotation/jquery-ui-1.8.2.custom/js/jquery-1.4.2.min.js"></script>');
        $mform->addElement('html', '<script type="text/javascript" src="' . $CFG->wwwroot .
            '/mod/videoannotation/jwplayer-6.12/jwplayer.js"></script>' . "\n");
        $mform->addElement('html', '<script type="text/javascript">wwwroot = "' . $CFG->wwwroot . '";</script>' . "\n");
        $mform->addElement('html', '<script type="text/javascript" src="' . $CFG->wwwroot .
            '/mod/videoannotation/mod_form_js.php"></script>' . "\n");
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, null);

        if (!($data['playabletimestart'] < $data['playabletimeend'])) {
            $errors['playabletimestart'] = $errors['playabletimeend'] = get_string('playabletimestart', 'videoannotation') . ' ' .
            get_string('mustbelessthan', 'videoannotation') . ' ' .
            get_string('playabletimeend', 'videoannotation');
        }
        return $errors;
    }
}

global $USER, $DB;

$mform = new clip_form();

if ($mform->is_cancelled()) {
    redirect("view.php?id=" . $cm->id . ($groupid ? '&group=' . $groupid : ''));
} else if ($data = $mform->get_data()) {
    if ($clip) {
        $clip->url = $data->clipurl;
        $clip->playabletimestart = $data->playabletimestart;
        $clip->playabletimeend = $data->playabletimeend;
        $clip->videowidth = $data->videowidth;
        $clip->videoheight = $data->videoheight;
        $clip->timemodified = time();
        $DB->update_record('videoannotation_clips', $clip);
        redirect("view.php?id=" . $cm->id . ($groupid ? '&group=' . $groupid : ''));
        echo get_string('clipedited', 'videoannotation');
    } else {
        $clip = new stdClass();
        $clip->videoannotationid = $videoannotation->id;
        $clip->userid = $USER->id;
        $clip->groupid = $groupid;
        $clip->url = $data->clipurl;
        $clip->playabletimestart = $data->playabletimestart;
        $clip->playabletimeend = $data->playabletimeend;
        $clip->videowidth = $data->videowidth;
        $clip->videoheight = $data->videoheight;
        $clip->timecreated = time();
        $clip->timemodified = time();
        $DB->insert_record('videoannotation_clips', $clip);
        redirect("view.php?id=" . $cm->id . ($groupid ? '&group=' . $groupid : ''));
        echo get_string('clipadded', 'videoannotation');
    }
} else {
    echo $OUTPUT->header();
    if ($clip) {
        echo $OUTPUT->heading(get_string('editclip', 'videoannotation'));
        $mform->set_data(array(
        'id' => $id,
        'clipurl' => $clip->url,
        'playabletimestart' => $clip->playabletimestart,
        'playabletimeend' => $clip->playabletimeend,
        'videowidth' => $clip->videowidth,
        'videoheight' => $clip->videoheight,
        'group' => $groupid
        ));
        $mform->display();
    } else {
        echo $OUTPUT->heading(get_string('addclip', 'videoannotation'));
        $mform->set_data(array('id' => $id));
        $mform->set_data(array('group' => $groupid));
        $mform->display();
    }
}

// Finish the page.
echo $OUTPUT->footer();
