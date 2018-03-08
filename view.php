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

require_once('../../config.php');
require_once("$CFG->libdir/formslib.php");
require_once('lib.php');
require_once('locallib.php');

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
			$html  .= '<button class="btn btn-primary btn_confidence btn_anim" id="btn_confidence_' . $i . '"><span class="wrist"><span class="thumb_small" style="transform: rotate(' . (180 - $r) . 'deg); background-color: hsl(' . $h . ',100%,50%)"></span></span> ' . $option . ' </button>';
			$i++;
			$r = round($r + $degperoption);
			$h = round($h + $hueperoption);
		}
		$html .= '</div>';
		$mform->addElement('html', $html);
		
		$mform->addElement('editor', 'comment', get_string("comment", "skillsaudit"));
		$mform->setType('comment', PARAM_RAW);
		
		$mform->addElement('html', '<button class="btn btn-primary" id="btn_save_confidence">Save</button>
      						<button class="btn btn-primary" id="btn_show_all">Cancel</button>
      						<button class="btn btn-primary" id="btn_show_next">Save and show next</button></div>');
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

$skills = $DB->get_records_sql('SELECT s.* FROM {skills} s WHERE s.id IN (SELECT skillid FROM {skillsinaudit} WHERE auditid = ?) ORDER BY s.number ASC', array($cm->instance));


$context = context_module::instance($cm->id);
$can_clear_rating = has_capability('mod/skillsaudit:editownrating', $context);
$can_delete_rating = has_capability('mod/skillsaudit:deleterating', $context);


foreach($skills as $skill) {
	$html = '<div class="ratings">';
	$skill->latest_rating = 0;
	$skill->rating_count = 0;
	$skill->ratings = '';
	//if($ratings = $DB->get_records('skillsauditrating', array('auditid'=>$cm->instance, 'skillid'=>$skill->id, 'userid'=>$USER->id), 'timestamp ASC')) {
	if($ratings = $DB->get_records_sql('SELECT r.*, a.id AS auditid, a.name AS auditname FROM {skillsauditrating} AS r, {skillsaudit} AS a WHERE skillid=? AND userid=? AND auditid IN(SELECT id FROM {skillsaudit} WHERE COURSE=?) AND a.id=r.auditid ORDER BY timestamp ASC', array($skill->id, $USER->id, $cm->course))) {
		foreach($ratings as $rating) {
			$skill->confidence = $rating->confidence;
			$html .= skillsaudit_get_rating_html($rating, $can_clear_rating, $can_delete_rating, $cm->instance);
			if($rating->timestamp > $skill->latest_rating) {
				$skill->latest_rating = $rating->timestamp;
			}
			$skill->rating_count += 1;
		}
		
	} else {
		$skill->confidence = 0;
	}
	$html .= '<div class="new_ratings"></div>';
	if($skill->link) {
		$html .= '<a target="_blank" href="' . $skill->link . '" title="Click here for more info"><span class="info_icon"></span></a>';
	}
	$html .= '<button class="btn btn-primary btn_hide_comments">Hide comments</button> <button class="btn btn-primary btn_cancel">Cancel</button></div>';
	$skill->ratings = $html;
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
$can_track = has_capability('mod/skillsaudit:trackratings', $context);
if($can_track) {
	echo('<a href="track.php?id=' . $cm->id . '"><button class="btn btn-secondary"><span class="glyphicon glyphicon-signal"></span> Track students\' progress</button></a>');
}

if ($skillsaudit->intro) {
	echo($OUTPUT->heading($cm->name));
    echo $OUTPUT->box(format_module_intro('skillsaudit', $skillsaudit, $cm->id), 'generalbox mod_introbox', 'skillsauditintro');
}

echo($OUTPUT->heading('Summary'));
echo('<div class="skillsaudit_user_summary">' . skillsaudit_get_summary_html($cm, $USER->id) . '</div>');

// get teacher feedback
$feedback = $DB->get_records_sql('SELECT * FROM {skillsauditfeedback} WHERE auditid = ? AND userid = ? ORDER BY timestamp ASC',
                    array($cm->instance, $USER->id));

if(count($feedback) > 0) {
    $html = $OUTPUT->heading('Teacher Feedback:');
    foreach($feedback as $f) {
        $fromuser = $DB->get_record('user', array('id'=>$f->fromid));
        $html .= '<div class="teacher_feedback">';
        if($f->skillid > 0) {
            $skill = $DB->get_record('skills', array('id'=>$f->skillid));
            $html .= '<div class="feedback_skill">';
            if(str_len($skill->link) > 0) {
                $html .= '<a href="' . $skill->link . '"><span class="info_icon"></span></a>';
            };
            $html .= '<b>' . $cm->name . ': ' . $skill->number . '</b> ' . $skill->description . '</div>';
        } else {
            $html .= '<div class="feedback_skill">' . $cm->name . ':</div>';
        }
        $html .=  '<div class="feedback_message">' . $f->message . '</div>';
        $html .= '<div class="feedback_from">From ' . $fromuser->firstname . ' ' . $fromuser->lastname . ' on ' . date("D jS M Y", $f->timestamp) . '</div>';
        $html .= '</div>';
    }
    echo($html);
}

// Replace the following lines with you own code.
echo $OUTPUT->heading('Skills');

// get all skills for this audit
//$skills = $DB->get_records_sql('SELECT * FROM {skills} WHERE id IN (SELECT skillid FROM {skillsinaudit} WHERE auditid = ?)', array($cm->instance));

function formatDateDiff($timestamp) { 
    $start  = new DateTime();
	$start->setTimestamp($timestamp);
    
    $end = new DateTime(); 
	$end->setTimestamp(time());
    
    $interval = $end->diff($start); 
	
	$str = '';
	if($interval->d == 0) {
		$str = 'today';
	} else {
		if($interval->d >= 7) {
			$weeks = floor($interval->d / 7);
			$str = $weeks . " week" . ($weeks > 1?"s":"") . " ago";
		} else {
			$str = $interval->d . " day" . ($interval->d > 1?"s":"") . " ago";
		}
		
	}
	return $str;
}

echo('<table class="generaltable"><tr><th>Number</th><th>Skill</th><th>Ratings</th><th>Confidence</th></tr>');
foreach($skills as $skill) {
	$skill->hue = round($skill->confidence * 120.0 / 100.0);
	echo('<tr class="skill_row" id="skill_row_' . $skill->id . '"><td class="skillnumber">' . $skill->number . '</td>');
	echo('<td>' . $skill->description . $skill->ratings . '<div class="latest_rating_time">Last rated: ');
	if($skill->rating_count > 0) {
		echo(formatDateDiff($skill->latest_rating));
	} else {
		echo('never');
	}
	
	echo('</div></td>');
	echo('<td id="rating_stats_' . $skill->id . '">');
	echo('<span class="rating_count">' . $skill->rating_count . '</span>');
	echo('</td>');
	echo('<td><span class="conf_ind_cont" title="' . $skill->confidence . '%"><span class="conf_ind" id="conf_ind_' . $skill->id .'" style="width:' . $skill->confidence . '%; background: linear-gradient(to right,red,hsl(' . $skill->hue .',100%,50%))"></span></span></td></tr>');
}
echo('</table>');

?>

      
    
      
      
      <?php 
	  
	  $mform = new confidence_rating_form('javascript:;', array('skillsaudit'=>$skillsaudit));
	  $mform->display();
	  
	  ?>

  <!-- Modal -->
<div id="dlg" class="modal fade" role="dialog">
  <div class="modal-dialog">

    <!-- Modal content-->
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title" id="dlg_title">Skills</h4>
      </div>
      <div class="modal-body" id="dlg_body">        
      </div>
      <div class="modal-footer" id="dlg_footer">
      </div>
    </div>
  </div>
</div>
      
      
      
<?php
if($can_track) {
	echo('<a href="track.php?id=' . $cm->id . '"><button class="btn btn-secondary"><span class="glyphicon glyphicon-signal"></span> Track students\' progress</button></a>');
}
// Finish the page.
echo $OUTPUT->footer();
