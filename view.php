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
 * Prints a particular instance of skillsaudit
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
require_once("$CFG->libdir/formslib.php");
require_once(dirname(__FILE__).'/lib.php');
require_once(dirname(__FILE__).'/locallib.php');

class confidence_rating_form extends moodleform {
	function definition() {
		global $CFG;
		$mform = $this->_form;
		$skillsaudit = $this->_customdata['skillsaudit'];
		$html = '<div id="controls"><h3>' . $skillsaudit->question . '</h3>
      <span id="chart_confidence"><div id="main_scale"></div><div id="main_indicator"><span class="wrist wiggle"><span class="thumb" style="transform: rotate(0deg); background-color: hsl(0,100%,50%)"></span></span></div></span><div class="conf_buttons">';
	  
	  	$options = explode(',', $skillsaudit->options);
		$degperoption = (180.0 / (count($options)-1));
		$i = 0;
		$r = 0;
		$h = 0;
		$hueperoption = 120.0 / (count($options)-1);
		foreach($options as $option) {
			$option = trim($option);
			$html  .= '<button class="btn_confidence btn_anim" id="btn_confidence_' . $i . '"><span class="wrist"><span class="thumb_small" style="transform: rotate(' . (180 - $r) . 'deg); background-color: hsl(' . $h . ',100%,50%)"></span></span> ' . $option . ' </button>';
			$i++;
			$r = round($r + $degperoption);
			$h = round($h + $hueperoption);
		}
		$html .= '</div>';
		$mform->addElement('html', $html);
		
		$mform->addElement('editor', 'comment', get_string("comment", "skillsaudit"));
		$mform->setType('comment', PARAM_RAW);
		
		$mform->addElement('html', '<button id="btn_save_confidence">Save</button>
      						<button id="btn_show_all">Cancel</button>
      						<button id="btn_show_next">Save and show next</button></div>');
	}
}

$id = optional_param('id', 0, PARAM_INT); // Course_module ID, or
$n  = optional_param('n', 0, PARAM_INT);  // ... skillsaudit instance ID - it should be named as the first character of the module.

if ($id) {
    $cm         = get_coursemodule_from_id('skillsaudit', $id, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $skillsaudit  = $DB->get_record('skillsaudit', array('id' => $cm->instance), '*', MUST_EXIST);
} else if ($n) {
    $skillsaudit  = $DB->get_record('skillsaudit', array('id' => $n), '*', MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $skillsaudit->course), '*', MUST_EXIST);
    $cm         = get_coursemodule_from_instance('skillsaudit', $skillsaudit->id, $course->id, false, MUST_EXIST);
} else {
    print_error('error_missingid');
}

require_login($course, true, $cm);

$event = \mod_skillsaudit\event\course_module_viewed::create(array(
    'objectid' => $PAGE->cm->instance,
    'context' => $PAGE->context,
));
$event->add_record_snapshot('course', $PAGE->course);
$event->add_record_snapshot($PAGE->cm->modname, $skillsaudit);
$event->trigger();

// Print the page header.

$PAGE->set_url('/mod/skillsaudit/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($skillsaudit->name));
$PAGE->set_heading(format_string($course->fullname));

/*$skills = $DB->get_records_sql('SELECT s.*, r.confidence, r.comment, r.timestamp FROM {skills} s LEFT JOIN {skillsauditrating} r ON s.id = r.skillid WHERE s.id IN (SELECT skillid FROM {skillsinaudit} WHERE auditid = ?) AND (r.userid=? OR r.userid IS NULL)', array($cm->instance, $USER->id));


*/

$skills = $DB->get_records_sql('SELECT s.* FROM {skills} s WHERE s.id IN (SELECT skillid FROM {skillsinaudit} WHERE auditid = ?)', array($cm->instance));


$context = context_module::instance($cm->id);
$can_clear_rating = has_capability('mod/skillsaudit:editownrating', $context);
$can_delete_rating = has_capability('mod/skillsaudit:deleterating', $context);

foreach($skills as $skill) {
	$html = '<div class="ratings">';
	//if($ratings = $DB->get_records('skillsauditrating', array('auditid'=>$cm->instance, 'skillid'=>$skill->id, 'userid'=>$USER->id), 'timestamp ASC')) {
	if($ratings = $DB->get_records_sql('SELECT r.*, a.id AS auditid, a.name AS auditname FROM {skillsauditrating} AS r, {skillsaudit} AS a WHERE skillid=? AND userid=? AND auditid IN(SELECT id FROM {skillsaudit} WHERE COURSE=?) AND a.id=r.auditid ORDER BY timestamp ASC', array($skill->id, $USER->id, $cm->course))) {
		foreach($ratings as $rating) {
			$skill->confidence = $rating->confidence;
			$html .= skillsaudit_get_rating_html($rating, $can_clear_rating, $can_delete_rating, $cm->instance);
		}
		$html .= '<div class="new_ratings"></div>';
		$html .= '<button class="btn_hide_comments">Hide comments</button> <button class="btn_cancel">Cancel</button></div>';
		$skill->ratings = $html;
	
		
	} else {
		$skill->confidence = 0;
	}
	
}

$PAGE->requires->js_call_amd('mod_skillsaudit/skillsaudit', 'viewinit', array('course'=>$COURSE->id, 'skills'=>$skills, 'auditid'=>$cm->instance, 'cmid'=>$cm->id));

/*
 * Other things you may want to set - remove if not needed.
 * $PAGE->set_cacheable(false);
 * $PAGE->set_focuscontrol('some-html-id');
 * $PAGE->add_body_class('skillsaudit-'.$somevar);
 */

// Output starts here.
echo $OUTPUT->header();


// Conditions to show the intro can change to look for own settings or whatever.
if ($skillsaudit->intro) {
    echo $OUTPUT->box(format_module_intro('skillsaudit', $skillsaudit, $cm->id), 'generalbox mod_introbox', 'skillsauditintro');
}

echo($OUTPUT->heading('Summary'));
echo('<div class="skillsaudit_user_summary">' . skillsaudit_get_summary_html($cm, $USER->id) . '</div>');

// Replace the following lines with you own code.
echo $OUTPUT->heading('Skills');

// get all skills for this audit
//$skills = $DB->get_records_sql('SELECT * FROM {skills} WHERE id IN (SELECT skillid FROM {skillsinaudit} WHERE auditid = ?)', array($cm->instance));


echo('<table class="generaltable"><tr><th>Number</th><th>Skill</th><th>Confidence</th></tr>');
foreach($skills as $skill) {
	$skill->hue = round($skill->confidence * 120.0 / 100.0);
	echo('<tr class="skill_row" id="skill_row_' . $skill->id . '"><td class="skillnumber">' . $skill->number . '</td><td>' . $skill->description . $skill->ratings .'</td><td><span class="conf_ind_cont"><span class="conf_ind" id="conf_ind_' . $skill->id .'" style="width:' . $skill->confidence . '%; background: linear-gradient(to right,red,hsl(' . $skill->hue .',100%,50%))"></span></span></td></tr>');
}
echo('</table>');

?>

      
    
      
      
      <?php 
	  
	  $mform = new confidence_rating_form('javascript:;', array('skillsaudit'=>$skillsaudit));
	  $mform->display();
	  
	  ?>
      
      
      
<?php

// Finish the page.
echo $OUTPUT->footer();
