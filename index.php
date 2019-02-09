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

$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);

require_course_login($course);

$params = array(
    'context' => context_course::instance($course->id)
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
				
	$grading_info = grade_get_grades($course->id, 'mod', 'skillsaudit', $cm->instance, array($USER->id));
 
	$grade_item_grademax = $grading_info->items[0]->grademax;
	$confidence = intval($grading_info->items[0]->grades[$USER->id]->grade);
	$coverage = intval($grading_info->items[2]->grades[$USER->id]->grade);
	$progress = intval($grading_info->items[1]->grades[$USER->id]->grade);
	$total = $coverage * $confidence / 100;
	$row[] = get_rating_bar($coverage);
	$row[] = get_rating_bar($confidence);
	$row[] = get_rating_bar($progress);
    $table->data[] = $row;
}
echo('<h2>Summary</h2>');
echo(skillsaudit_get_user_summary($COURSE, $USER));

echo('<h2>Topic by topic</h2>');
echo(html_writer::table($table));


$history = $DB->get_records_sql("SELECT gh.timemodified, gh.rawgrade, gi.iteminstance, gi.itemnumber, gi.itemname FROM grade_grades_history AS gh
 	JOIN grade_items AS gi ON gi.id = gh.itemid
 	WHERE gi.courseid=? AND gh.userid=?  AND gi.itemmodule='skillsaudit' AND gi.itemnumber=0 AND gh.source='mod/skillsaudit' AND NOT ISNULL(gi.itemname)
 	ORDER BY gh.timemodified
 	", [$course->id, $USER->id]);

$confidence = ["titles"=>[], "ratings"=>[]];
$earliest = time();
foreach ($history as $h) {
	if($h->timemodified < $earliest) {
		$earliest = $h->timemodified;
	}

	$confidence["titles"][$h->iteminstance] = $h->itemname;
	
}

$times = [];
$data = [];
for($t = $earliest; $t <= time() + 604800; $t+=604800) {
	$times[] = array($t, date("j M Y", $t));
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



/*$timeid = 0;
foreach($history as $h) {
	//while($h->timemodified > $times[$timeid][0]) {
	//	$timeid++;
	//}/
	if($h->itemnumber == 0) {
		$coverage["ratings"][$h->iteminstance][$timeid] = $h->rawgrade;
	} 

	/*if($h->itemnumber == 2) {
		$confidence["titles"][$h->iteminstance] = $h->itemname;
	}
}*/

//echo('<pre>');
//print_r($history);
//print_r($confidence);
 //echo('</pre>');

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
