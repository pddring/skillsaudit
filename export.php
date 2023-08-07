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
 * This is a one-line short description of the file
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_skillsaudit
 * @copyright  2017 Pete Dring <pddring@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Replace skillsaudit with the name of your module and remove this line.

require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/locallib.php');
require_once(dirname(__FILE__).'/lib.php');
require_once("$CFG->libdir/formslib.php");
$id = required_param('course', PARAM_INT); // Course.
$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);
$context = context_course::instance($course->id);
require_course_login($course);

class skillsaudit_exportform extends moodleform {
    public function definition() {
        global $CFG;
        global $course;
        $mform = $this->_form;
        $groups = groups_get_all_groups($course->id);
        $groupOptions = array(0 => 'All students');
        foreach($groups as $g) {
            $groupOptions[$g->id] = $g->name;
        }

        $mform->addElement('date_selector', 'effectivedate', 'Effective date');
        
        $select = $mform->addElement('select', 'groups', 'Groups', $groupOptions);
        $select->setMultiple(true);

        $mform->addElement('hidden', 'course', $course->id);

        $mform->addElement('advcheckbox', 'shownames', 'Show skill names', 'Show skill names as well as numbers');
        $mform->addElement('advcheckbox', 'showindividuals', 'Show individual students', 'Show individual names as well as group summaries');

        $mform->addElement('select', 'type', 'Format', array('show'=>'Show on screen', 'csv'=>'Download CSV file'));

        $mform->addElement('submit', 'submit', 'Export');
    }
}

$strname = get_string('modulenameplural', 'mod_skillsaudit');

$PAGE->set_url('/mod/skillsaudit/index.php', array('id' => $id));
$PAGE->navbar->add($strname);
$PAGE->set_title("$course->shortname: $strname");
$PAGE->set_heading($course->fullname);
$PAGE->set_pagelayout('incourse');



$can_track = has_capability('mod/skillsaudit:trackratings', $context);
if($can_track) {
    $mform = new skillsaudit_exportform();
    if($mform->is_cancelled()) {
    } else if($fromform = $mform->get_data()) {
        // get all skills
        $skills = $ratings = $DB->get_records_sql("SELECT * FROM {skills} WHERE courseid=? ORDER BY number ASC", array($course->id));
        $cols = array('First name', 'Last name', 'Email', 'Group');
        foreach($skills as $s) {
            if($fromform->shownames) {
                $cols[] = $s->number . " - " . $s->description;
            } else {
                $cols[] = $s->number;
            }
            
        }
        $headings = $cols;
        $data = array();
        // process each selected group
        foreach($fromform->groups as $groupid) {
            if($groupid == 0) {
                $groupname = "All Students";
            } else {
                $groupname = groups_get_group_name($groupid);
            }
            $group_users = get_enrolled_users($context, 'mod/skillsaudit:submit', $groupid);
            $averages = array('total'=>array(), 'count'=>array(), 'mean'=>array());
            foreach($group_users as $u) {    
                $cols = array($u->firstname, $u->lastname, $u->email, $groupname);
                foreach($skills as $s) {
                    $confidence = $DB->get_field_sql("SELECT confidence from {skillsauditrating} WHERE skillid=? AND timestamp<=? AND userid=? ORDER BY timestamp DESC LIMIT 1", array($s->id, $fromform->effectivedate + 86400, $u->id));
                    if($confidence != "") {
                        if(isset($averages['total'][$s->id])) {
                            $averages['total'][$s->id] += $confidence; 
                            $averages['count'][$s->id]++;
                        } else {
                            $averages['total'][$s->id] = $confidence;
                            $averages['count'][$s->id] = 1;
                            $averages['mean'][$s->id] = $confidence;
                        }
                    }
                    $cols[] = $confidence;
                }
                if($fromform->showindividuals) {
                    $data[] = $cols;
                }
            }
            $cols = array('Mean', 'Average', '', $groupname);
            foreach($skills as $s) {
                if(isset($averages['mean'][$s->id])) {
                    if($averages['count'][$s->id] > 0) {
                        $averages['mean'][$s->id] = $averages['total'][$s->id] / $averages['count'][$s->id];
                    }
                } else {
                    $averages['mean'][$s->id] = 0;
                }
                $cols[] = round($averages['mean'][$s->id]);
            }
            $data[] = $cols;

        }
        $table = new html_table();
        $table->head = $headings;
        $table->data = $data;
        if($fromform->type == "show") {
            echo($OUTPUT->header());
            echo($OUTPUT->heading("Export all ratings"));
            echo(html_writer::table($table));
            echo($OUTPUT->footer());

        } else {
            core\dataformat::download_data('skillsaudit', 'csv', $headings, $data);
        }

    } else {
        echo($OUTPUT->header());
        echo($OUTPUT->heading("Export all ratings"));            
        $mform->display();
        echo($OUTPUT->footer());
    }
} else {
    echo($OUTPUT->header());
    echo($OUTPUT->heading("Export all ratings"));
    echo("Sorry - you don't have permissions to export all confidence ratings");
    echo($OUTPUT->footer());
}


?>