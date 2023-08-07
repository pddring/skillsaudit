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

$id = required_param('id', PARAM_INT); // Course.
$nohead = optional_param('nohead', 0, PARAM_INT);
$userid = optional_param('user', $USER->id, PARAM_INT);
$groupid = optional_param('group', 0, PARAM_INT);

$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);

require_course_login($course);
error_reporting(0);
$context = context_course::instance($course->id);
$params = array(
    'context' => $context
);
$event = \mod_skillsaudit\event\course_module_instance_list_viewed::create($params);
$event->add_record_snapshot('course', $course);
$event->trigger();


$strname = get_string('modulenameplural', 'mod_skillsaudit');

if(!$nohead) {
	$PAGE->set_url('/mod/skillsaudit/index.php', array('id' => $id));
	$PAGE->navbar->add($strname);
	$PAGE->set_title("$course->shortname: $strname");
	$PAGE->set_heading($course->fullname);
	$PAGE->set_pagelayout('incourse');
	
	echo $OUTPUT->header();
	echo $OUTPUT->heading($strname);
}
$showing_user = $USER;

$can_track = has_capability('mod/skillsaudit:trackratings', $context);
if($can_track) {
	?>
	<div>
		<a class="btn btn-primary" href="<?php echo(new moodle_url('/mod/skillsaudit/export.php', array('course' => $course->id)));?>">Download all ratings</a>
	</div>
	<?php

	echo('Group:');
	$groups = groups_get_all_groups($COURSE->id);
	echo('<div>');
	echo('<form class="inline">');
	echo('<select name="group">');
	$all_users = new stdClass();
	$all_users->id = 0;
	$all_users->name = "All users";
	array_unshift($groups, $all_users);
	foreach($groups as $group) {
		if($group->id == $groupid) {
			echo('<option value="' . $group->id . '" selected>' . s($group->name) . '</option>');
		} else {
			echo('<option value="' . $group->id . '">' . s($group->name) . '</option>');
		}
		
	}
	echo('</select>');
	echo('<input type="hidden" value="' . $id . '" name="id">');
	echo('<button type="submit" class="btn btn-primary">Update</button>');
	echo('</form> ');

	echo('User:');
	$users = get_enrolled_users($context, 'mod/skillsaudit:submit', $groupid);
	echo('<form class="inline">');
	echo('<input type="hidden" value="' . $id . '" name="id">');
	echo('<select name="user">');
	
	foreach($users as $user) {
		if($user->id == $userid) {
			echo('<option value="' . $user->id . '" selected>' . s($user->firstname . " " . $user->lastname) . '</option>');
			$showing_user = $user;
		} else {
			echo('<option value="' . $user->id . '">' . s($user->firstname . " " . $user->lastname) . '</option>');
		}
		
	}
	echo('</select>');
	echo('<button type="submit" class="btn btn-primary">View</button>');
	echo('</form>');
	echo('</div>');
//	print_r($users);
} else {
	$userid = $USER->id;
}



if (! $skillsaudits = get_all_instances_in_course('skillsaudit', $course)) {
    notice(get_string('noskillsaudits', 'skillsaudit'), new moodle_url('/course/view.php', array('id' => $course->id)));
}

function get_rating_bar($percentage) {
	$background = 'linear-gradient(to right,red,hsl(' . round($percentage * 120.0 / 100.0) .',100%,50%))';
	return '<span class="conf_ind_cont" title="' . $percentage . '"><span class="conf_ind" style="width:' . $percentage . '%; background: ' . $background . '"></span>';
}

$usesections = false;

$table = new html_table();
$table->attributes['class'] = 'generaltable mod_index';


$table->head  = array ($strname, 'Coverage', 'Confidence', 'Competence');
$table->align = array ('left');


$modinfo = get_fast_modinfo($course);
$target_confidence = 100;
$target_html = '';
foreach ($modinfo->instances['skillsaudit'] as $cm) {
    $row = array();
    if ($usesections) {
        if ($cm->sectionnum !== $currentsection) {
            if ($cm->sectionnum) {
                $row[] = get_section_name($course, $cm->sectionnum);
            }
            if ($currentsection !== '') {
                $table->data[] = 'hr';
            }
            $currentsection = $cm->sectionnum;
        }
    }

    $class = $cm->visible ? null : array('class' => 'dimmed');

    $row[] = html_writer::link(new moodle_url('view.php', array('id' => $cm->id)),
                '<img src="' . $cm->get_icon_url() . '"> ' . $cm->get_formatted_name(), $class);
    $summary_html = skillsaudit_get_summary_html($cm, $showing_user->id, true);

				
	$grading_info = grade_get_grades($course->id, 'mod', 'skillsaudit', $cm->instance, array($showing_user->id));
 
	$grade_item_grademax = $grading_info->items[0]->grademax;
	$confidence = intval($grading_info->items[0]->grades[$showing_user->id]->grade);
	if($confidence < $target_confidence) {
		$target_confidence = $confidence;
		$target_html = $summary_html;
	}

	$coverage = intval($grading_info->items[2]->grades[$showing_user->id]->grade);
	$progress = intval($grading_info->items[1]->grades[$showing_user->id]->grade);
	$total = $coverage * $confidence / 100;
	$row[] = get_rating_bar($coverage);
	$row[] = get_rating_bar($confidence);
	$row[] = get_rating_bar($progress);
    $table->data[] = $row;
}
echo('<h2>Summary for ' . s($showing_user->firstname . " " . $showing_user->lastname) . '</h2>');
echo(skillsaudit_get_user_summary($COURSE, $showing_user));
echo('<div class="skillsaudit_user_summary">' . $target_html . '</div>');

echo('<h2>Topic by topic</h2>');
echo(html_writer::table($table));


$history = $DB->get_records_sql("SELECT gh.timemodified, gh.rawgrade, gi.iteminstance, gi.itemnumber, gi.itemname FROM {grade_grades_history} AS gh
 	JOIN {grade_items} AS gi ON gi.id = gh.itemid
 	WHERE gi.courseid=? AND gh.userid=?  AND gi.itemmodule='skillsaudit' AND gi.itemnumber=0 AND gh.source='mod/skillsaudit' AND NOT ISNULL(gi.itemname)
 	ORDER BY gh.timemodified
 	", [$course->id, $showing_user->id]);

$confidence = ["titles"=>[], "ratings"=>[]];
$earliest = time();
$confidence["titles"]["Average"] = 'Average';
foreach ($history as $h) {
	if($h->timemodified < $earliest) {
		$earliest = $h->timemodified;
	}

	$confidence["titles"][$h->iteminstance] = str_replace(" (Confidence)", "", $h->itemname);
}

$times = [];
$data = [];
for($t = time(); $t >= $earliest - 604800; $t-=604800) {
	array_unshift($times, array($t, date("j M Y", $t)));
	$data[] = 0;
}

foreach(array_keys($confidence["titles"]) as $t) {
	$confidence["ratings"][$t] = $data;
}

function get_time_id($timestamp) {
	global $times;
	for($i = 0; $i < count($times); $i++) {
		if($times[$i][0] > $timestamp) {
			return $i;
		}
	}
	return $i;
}

foreach($history as $h) {
	$h->timeid = get_time_id($h->timemodified);
	for($i = $h->timeid; $i < count($times); $i++) {
		$confidence["ratings"][$h->iteminstance][$i] = round($h->rawgrade);	
	}	
}

for($i = 0; $i < count($times); $i++) {
	
	$sum = 0;
	foreach(array_keys($confidence["titles"]) as $t) {
		$sum += $confidence["ratings"][$t][$i];
	}
	if(count($confidence["titles"]) > 1) {
		$confidence["ratings"]["Average"][$i] = $sum / (count($confidence["titles"]) - 1);
	}
	
}

$chart = new \core\chart_line();
$chart->set_title("Confidence:");

$axis = $chart->get_yaxis(0, true);
$axis->set_min(0);
$axis->set_max(100);



$labels = [];
$data = [];
for($i = 0; $i < count($times); $i++) {
	$labels[] = $times[$i][1];
	$data[] = $i;
}

foreach(array_keys($confidence["titles"]) as $t) {
	$chart->add_series(new core\chart_series($confidence["titles"][$t], $confidence["ratings"][$t]));
}

$chart->set_labels($labels);
echo $OUTPUT->render($chart);



if(!$nohead) {
	echo $OUTPUT->footer();
}
