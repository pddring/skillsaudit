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


require_once(dirname(dirname(dirname(__FILE__))).'/config.php');
require_once(dirname(__FILE__).'/lib.php');
require_once(dirname(__FILE__).'/locallib.php');

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

$skills = $DB->get_records_sql('SELECT s.* FROM {skills} s WHERE s.id IN (SELECT skillid FROM {skillsinaudit} WHERE auditid = ?)', array($cm->instance));


$PAGE->requires->js_call_amd('mod_skillsaudit/skillsaudit', 'trackinit', array('course'=>$COURSE->id, 'skills'=>$skills, 'auditid'=>$cm->instance, 'cmid'=>$cm->id));


// Print the page header.

$PAGE->set_url('/mod/skillsaudit/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($skillsaudit->name));
$PAGE->set_heading(format_string($course->fullname));


$skills = $DB->get_records_sql('SELECT s.* FROM {skills} s WHERE s.id IN (SELECT skillid FROM {skillsinaudit} WHERE auditid = ?)', array($cm->instance));


$context = context_module::instance($cm->id);


foreach($skills as $skill) {
	$html = '<div class="ratings">';
	$skill->latest_rating = 0;
	$skill->rating_count = 0;
	if($ratings = $DB->get_records_sql('SELECT r.*, a.id AS auditid, a.name AS auditname FROM {skillsauditrating} AS r, {skillsaudit} AS a WHERE skillid=? AND userid=? AND auditid IN(SELECT id FROM {skillsaudit} WHERE COURSE=?) AND a.id=r.auditid ORDER BY timestamp ASC', array($skill->id, $USER->id, $cm->course))) {
		foreach($ratings as $rating) {
			$skill->confidence = $rating->confidence;
			if($rating->timestamp > $skill->latest_rating) {
				$skill->latest_rating = $rating->timestamp;
			}
			$skill->rating_count += 1;
		}		
	} else {
		$skill->confidence = 0;
	}
	
}

//$PAGE->requires->js_call_amd('mod_skillsaudit/skillsaudit', 'viewinit', array('course'=>$COURSE->id, 'skills'=>$skills, 'auditid'=>$cm->instance, 'cmid'=>$cm->id));

// Output starts here.
echo $OUTPUT->header();
$can_view = has_capability('mod/skillsaudit:view', $context);
if($can_view) {
	echo('<a href="view.php?id=' . $cm->id . '"><button class="btn btn-secondary">Rate my confidence</button></a>');
}

// Conditions to show the intro can change to look for own settings or whatever.
if ($skillsaudit->intro) {
	echo($OUTPUT->heading($cm->name));
    echo $OUTPUT->box(format_module_intro('skillsaudit', $skillsaudit, $cm->id), 'generalbox mod_introbox', 'skillsauditintro');
}

$gmode = groups_get_activity_groupmode($cm);
if($gmode == NOGROUPS) {
	$groups = array(0=>(object)array('id'=>0, 'name' => 'All students'));
} else {
	$groups = groups_get_all_groups($COURSE->id);
}
?>

<div class="form-group row">
<div class="col-xs-2">
<label for="autorefresh">Auto refresh:</label>
<select class="form-control" id="autorefresh">
 <option selected value="0">Never</option>
 <option value="1">Every second</option>
 <option value="5">Every 5 seconds</option>
 <option value="30">Every 30 seconds</option>
 <option value="60">Every minute</option>
</select>
</div>

<div class="col-xs-2">
<label for="highlight">Highlight:</label>
<select class="form-control" id="highlight">
 <option selected value="">No highlighting</option>
 <option value="today">Skills rated today</option>
 <option value="one">Skills with at least 1 rating</option>
 <option value="two">Skills with at least 2 ratings</option>
</select>
</div>

<div class="col-xs-3">
<label for="select_group">Group:</label>
<div class="input-group">
	
    <select class="form-control width100" id="select_group">
      <?php
$first = true;
foreach($groups as $group) {
	echo('<option value="' . $group->id .'"');
	if($first) {
		$first = false;
		echo(' selected');
		$selectedgroup = $group;
	}
	echo('>' . $group->name . '</option>');	
}	  
	  ?>
    </select>
    <span class="input-group-btn">
        <button id="btn_update_progress_tracker" class="btn btn-info">Update</button>
    </span>
  </div>
  </div>
  
  </div>
  <div id="tracker_table">
<?php
$html = skillsaudit_get_tracking_table($cm, $selectedgroup, $skills);
echo($html);
echo('</div>');
if($can_view) {
	echo('<a href="view.php?id=' . $cm->id . '"><button class="btn btn-secondary">Rate my confidence</button></a>');
}
echo $OUTPUT->footer();
