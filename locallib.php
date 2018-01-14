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
 * Internal library of functions for module skillsaudit
 *
 * All the skillsaudit specific functions, needed to implement the module
 * logic, should go here. Never include this file from your lib.php!
 *
 * @package    mod_skillsaudit
 * @copyright  2017 Pete Dring <pddring@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("$CFG->libdir/weblib.php");
require_once("$CFG->libdir/gradelib.php");
defined('MOODLE_INTERNAL') || die();

class skillsaudit {
	public static function get_rating_bar($percentage, $label) {
		/*$background = 'linear-gradient(to right,red,hsl(' . round($percentage * 120.0 / 100.0) .',100%,50%))';
		return '<span class="conf_ind_cont" title="' . $percentage . '"><span class="conf_ind" style="width:' . $percentage . '%; background: ' . $background . '"></span>';*/
		
		$h = 120 * $percentage / 100;
		$d = 180 - (180 * $percentage / 100);
		$style = 'background-color: hsl(' . $h . ',100%,50%);transform:rotate(' . $d . 'deg)';
		$html = '<span class="wrist"><span class="thumb" style="' . $style . '"></span></span><p>' . $label . ' <span class="summary_value">' . $percentage . '%</span></p>';
		return $html;
	}
}

function skillsaudit_get_user_summary($course, $user) {
	$table = new html_table();
	$table->attributes['class'] = 'generaltable mod_index';
	
	$strname = get_string('modulenameplural', 'mod_skillsaudit');
	$table->align = array ('center', 'left', 'center', 'center', 'center');
	

	$modinfo = get_fast_modinfo($course);
	
	$strongest = array('row'=>array(), 'total'=>0);
	$weakest = array('row'=>array(), 'total'=>100);
	
	foreach ($modinfo->instances['skillsaudit'] as $cm) {
		$row = array();	
		$class = $cm->visible ? null : array('class' => 'dimmed');
	
		$row[] = html_writer::link(new moodle_url('/mod/skillsaudit/view.php', array('id' => $cm->id)),
					'<img src="' . $cm->get_icon_url() . '"> ' . $cm->get_formatted_name(), $class);
					
		$grading_info = grade_get_grades($course->id, 'mod', 'skillsaudit', $cm->instance, array($user->id));
 
		$grade_item_grademax = $grading_info->items[0]->grademax;
		$confidence = intval($grading_info->items[0]->grades[$user->id]->grade);
		$coverage = intval($grading_info->items[2]->grades[$user->id]->grade);
		$total = $coverage * $confidence / 100;
		
		$row[] = skillsaudit::get_rating_bar($coverage, 'Coverage');
		$row[] = skillsaudit::get_rating_bar($confidence, 'Confidence');
		
		if($total > $strongest['total']) {
			$strongest['total'] = $total;
			array_unshift($row, '<h3>Your strongest area:</h3> ');
			$strongest['row'] = $row;
		}
		
		if($total < $weakest['total']) {
			$weakest['total'] = $total;
			array_unshift($row, '<h3>Suggested target:</h3> ');
			$weakest['row'] = $row;
		}
	}
	
	$table->data[] = $strongest['row'];
	$table->data[] = $weakest['row'];
	/*$coverage = 0;
	$confidence = 0;
	$table->data[] = array('<h3>Total</h3>', '', filter_skillsaudit::get_rating_bar($coverage, 'Coverage'), filter_skillsaudit::get_rating_bar($coverage, 'Coverage'));*/
	
	return html_writer::table($table);
}

function skillsaudit_get_activity_summary($cm, $userid, $skillid) {
	global $DB;
	
	$html = skillsaudit_get_summary_html($cm, $userid);
	ob_start();
	// specific skill
	if($skillid > 0) {
		$ratings = $DB->get_records_sql("SELECT * FROM {skillsauditrating} WHERE skillid=? AND userid=? ORDER BY timestamp ASC", array($skillid, $userid));
		$audit = $DB->get_record('skillsaudit', array('id' => $cm->instance));
		$options = explode(",", $audit->options);
		$num_options = count($options) - 1;
		$html .= '<table class="table"><thead><tr><th>Date:</th><th>Confidence:</th></tr></thead><tbody>';
		foreach($ratings as $rating) {
			$html .= '<tr><td>' . date("D jS M Y g:i a", $rating->timestamp) . '</td>';		
			$html .= '<td>';
			$hue = round($rating->confidence * 120.0 / 100.0);
			$html .= '<span class="conf_ind_cont" title="' . $rating->confidence . '%"><span class="conf_ind" style="width:' . $rating->confidence . '%; background: linear-gradient(to right,red,hsl(' . $hue .',100%,50%))"></span></span>';
			$i = round($rating->confidence * $num_options) / 100;
			$text_confidence = $options[$i];
			$html .= '<span class="text_confidence">' . $text_confidence . '</span>';
			$html .= '</td></tr>';
			if($rating->comment) {
				$html .= '<tr><td colspan="2"><div class="rating_comment">' . $rating->comment . '</div></td></tr>';
			}
	
		}

	// general overview
	} else {
		$stats = array();
		$ratings = $DB->get_records_sql("SELECT * FROM {skillsauditrating} WHERE skillid IN (SELECT skillid FROM {skillsinaudit} WHERE auditid=?) AND userid=? ORDER BY timestamp ASC", array($cm->instance, $userid));
		$skills = $DB->get_records_sql("SELECT * FROM {skills} WHERE id IN (SELECT skillid FROM {skillsinaudit} WHERE auditid=?)", array($cm->instance), 'number ASC');
		$course_skills = $DB->get_records('skills', array('courseid' => $cm->course));
		$course_ratings = $DB->get_records_sql("SELECT * FROM {skillsauditrating} WHERE skillid IN (SELECT id FROM {skills} WHERE courseid=?) AND userid=? ORDER BY timestamp ASC", array($cm->course, $userid));
		$audit = $DB->get_record('skillsaudit', array('id' => $cm->instance));
		$options = explode(",", $audit->options);
		$num_options = count($options) - 1;
		$html .= '<table class="table"><thead><tr><th></th><th colspan="2">This topic:</th><th colspan="2">Whole course:</th></tr><tr><th>Date:</th><th>Coverage:</th><th>Confidence:</th><th>Coverage:</th><th>Confidence:</th></tr></thead><tbody>';
		
		$coverage = 0;
		$confidence = 0;
		$coverage_course = 0;
		$confidence_course = 0;
		function update_stats(&$skills, $rating, &$confidence, &$coverage) {
			$rated = 0;
			$skillcount = 0;
			$totalconfidence = 0;
			foreach($skills as $skill) {
				$skillcount += 1;
				if(!isset($skill->latestdate)) {
					$skill->latestdate = 0;
					$skill->latestconfidence = 0;
				}
				if($rating->skillid == $skill->id) {
					$skill->rated = true;
					if($skill->latestdate < $rating->timestamp) {
						$skill->latestconfidence = $rating->confidence;
						$skill->latestdate = $rating->timestamp;
					}
				}
				if(isset($skill->rated)) {
					$rated += 1;
				}
				$totalconfidence += $skill->latestconfidence;
			}
			$coverage = round($rated * 100 / $skillcount);
			$confidence = round($totalconfidence / $skillcount);
		}
		
		function get_rating_html($percentage) {
			$hue = round($percentage * 120.0 / 100.0);
			$html = '<span class="conf_ind_cont" title="' . $percentage . '"><span class="conf_ind" style="width:' . $percentage . '%; background: linear-gradient(to right,red,hsl(' . $hue .',100%,50%))"></span></span> ';
			return $html;
		}
		
		foreach($course_ratings as $rating) {
			if(array_key_exists($rating->skillid, $skills)) {
				update_stats($skills, $rating, $confidence, $coverage);
			}
			update_stats($course_skills, $rating, $course_confidence, $course_coverage);
			$date = date("D jS M Y", $rating->timestamp);
			if(!array_key_exists($date, $stats)) {
				$stats[$date] = new stdClass;
				$stats[$date]->comments = '';
			}
			$stats[$date]->confidence = $confidence;
			$stats[$date]->coverage = $coverage;
			$stats[$date]->course_confidence = $course_confidence;
			$stats[$date]->course_coverage = $course_coverage;
			
			if(strlen($rating->comment) > 0) {
				$skill = $course_skills[$rating->skillid];
				$stats[$date]->comments .= '<span class="skillnumber">' . $skill->number . "</span> " . $skill->description . '<div class="rating_comment">' . $rating->comment . '</div>';	
			}
			
			$stats[$date]->date = $rating->timestamp;
		}
		foreach($stats as $stat) {
			//update_stats
			$html .= '<tr><td>' . date("D jS M Y", $stat->date) . '</td>';		
			$html .= '<td title="Coverage (This topic)">';
			$html .= get_rating_html($stat->coverage);			
			$html .= $stat->coverage . '%';
			$html .= '</td>';
			
			$html .= '<td title="Confidence (This topic)">';
			$html .= get_rating_html($stat->confidence);
			$html .= $stat->confidence . '%';
			$html .= '</td>';
			
			$html .= '<td title="Coverage (Whole course)">';
			$html .= get_rating_html($stat->course_coverage);			
			$html .= $stat->course_coverage . '%';
			$html .= '</td>';
			
			$html .= '<td title="Confidence (Whole course)">';
			$html .= get_rating_html($stat->course_confidence);
			$html .= $stat->course_confidence . '%';
			$html .= '</td>';
			
			$html .='</tr>';
			if(strlen($stat->comments) > 0) {
				$html .= '<tr><td></td><td colspan="4">' . $stat->comments . '</td></tr>';
			}
	
		}

	}
	$html .= '</tbody></table>';
	ob_end_clean();
	return $html;
}

function skillsaudit_get_tracking_table($cm, $group, $skills, $highlight = "") {
	function get_rating_bar($percentage) {
		$background = 'linear-gradient(to right,red,hsl(' . round($percentage * 120.0 / 100.0) .',100%,50%))';
		return '<span class="conf_ind_cont" title="' . $percentage . '"><span class="conf_ind" style="width:' . $percentage . '%; background: ' . $background . '"></span>';
	}
	global $DB;
	ob_start();	
	$context = context_module::instance($cm->id);
	$users = get_enrolled_users($context, 'mod/skillsaudit:submit', $group->id);
	$course = $DB->get_record('course', array('id' => $cm->course));
	require_login($course, true, $cm);
	$grading_info = grade_get_grades($cm->course, 'mod', 'skillsaudit', $cm->instance, array_keys($users));
	$html = '<table class="rating_table table table-bordered"><thead>';
	$html .= '<tr><th>Student</th><th colspan="3">This topic</th><th colspan="' . count($skills) . '">Individual skills</th><th colspan="2">Whole course</th></tr>';
	$html .= '<tr><th>Name</th><th>Confidence</th><th>Progress</th><th>Coverage</th>';
	foreach($skills as $skill) {
		$html .= '<th data-toggle="tooltip" title="' . htmlspecialchars($skill->description) . '">. ' . $skill->number . '</th>';
	}
	$html .= '<th>Confidence</th><th>Coverage</th>';
	$html .= '</thead></tr>';
	$html .= '<tbody>';
	$show_status = false;
	if($highlight !== "") {
		$show_status = true;
	}
	
	$totals = array();
	
	function remember($name, &$totals, $value) {
		if(array_key_exists($name, $totals)) {
			$totals[$name] += $value;
		} else {
			$totals[$name] = $value;
		}
	}
	
	foreach($users as $user) {
		$confidence = intval($grading_info->items[0]->grades[$user->id]->str_grade);
		remember('confidence', $totals, $confidence);
		
		$progress = intval($grading_info->items[1]->grades[$user->id]->str_grade);
		remember('progress', $totals, $progress);
		
		$coverage = intval($grading_info->items[2]->grades[$user->id]->str_grade);
		remember('coverage', $totals, $coverage);
		
		$html .= '<tr><th class="rating_td" id="rating_td_0_' . $user->id . '_name">' . $user->firstname . ' ' . $user->lastname . '</th>';
		$html .= '<td class="rating_td" id="rating_td_0_' . $user->id . '_confidence">' . get_rating_bar($confidence) . $confidence . '%</td>';
		$html .= '<td class="rating_td" id="rating_td_0_' . $user->id . '_progress">' . get_rating_bar($progress) . $progress . '%</td>';
		$html .= '<td class="rating_td" id="rating_td_0_' . $user->id . '_coverage">' . get_rating_bar($coverage) .$coverage . '%</td>';
		
		foreach($skills as $skill) {
			$rating = $DB->get_record_sql('SELECT id, confidence AS latest, timestamp, 
				(SELECT confidence FROM {skillsauditrating} WHERE skillid=? AND userid=? ORDER BY confidence ASC LIMIT 1) AS lowest,
				(SELECT COUNT(confidence) FROM {skillsauditrating} WHERE skillid=? AND userid=?) AS numratings
				FROM {skillsauditrating}
				WHERE skillid=? AND userid=?
				ORDER BY timestamp DESC LIMIT 1', 
				array($skill->id, $user->id, $skill->id, $user->id, $skill->id, $user->id));
			
			if(!$rating) {
				$rating = new stdClass;
				$rating->lowest = 0;
				$rating->latest = 0;
				$rating->timestamp = -1;
				$rating->numratings = 0;
			}
			$lowest_left = round($rating->lowest * 50 / 100);
			
			$diff = (time() - $rating->timestamp) / 86400;
			
	
			$html .= '<td class="rating_td" id="rating_td_' . $skill->id .'_' . $user->id . '" data-toggle="tooltip" title="' . htmlspecialchars($skill->description) . '">';
			
			$status = "no";
			if($highlight == "today") {
				if($diff < 1) {
					$status = "yes";
				}
			}
			
			if($highlight == "one") {
				if($rating->numratings >=1) {
					$status = "yes";
				}
			}
			
			if($highlight == "two") {
				if($rating->numratings >=2) {
					$status = "yes";
				}
			}
			
			if($show_status) {
				$html .= '<div class="conf_ind_status conf_ind_status_' . $status . '"></div>';
			}
			$html .= '<span class="num_ratings">' . $rating->numratings . '</span> ';
			
			remember($skill->number, $totals, $rating->latest);
			$latest_hue = 'hsl(' . round($rating->latest * 120.0 / 100.0) . ', 100%, 50%)';
			$background = 'linear-gradient(to right,red,hsl(' . $latest_hue .',100%,50%))';
			$lowest_hue = 'hsl(' . round($rating->lowest * 120.0 / 100.0) . ', 100%, 50%)';
			$html .= '<span class="conf_ind_cont"><span class="conf_ind" id="conf_ind_' . $skill->id . '_' . $user->id . '" style="width:' . $rating->latest . '%; background: ' . $latest_hue . '"></span>';
			$html .= '<div class="conf_ind_lowest" id="conf_ind_lowest_' . $skill->id . '_' . $user->id . '" style="left:' . $rating->lowest . '%;background: ' . $lowest_hue . '"></div>';
						
			$html .= '</span>';
			
			$html .= '<div class="latest_rating_time">';
			if($rating->timestamp > 0) { 
				if($diff < 1) {
					$i = round($diff * 24);
					$html .= $i . ' hour' . ($i > 1?'s':'') . ' ago';
				} else {
					if($diff > 14) {
						$i = round($diff / 24);
						$html .= $i . ' week' . ($i > 1?'s':'') . ' ago';
					} else {
						$i = round($diff);
						$html .= $i . ' day' . ($i > 1?'s':'') . ' ago';
					}
				} 
			}
			$html .= '</div>';
			$html .= '</td>';
		}
		
		$total_confidence = intval($DB->get_field_sql('SELECT finalgrade FROM {grade_grades} WHERE userid=? AND itemid=(SELECT id FROM {grade_items} WHERE courseid=? AND itemtype=\'manual\' AND itemmodule=\'skillsaudit\' AND itemname=\'Total confidence\')', array($user->id, $cm->course)));
		$total_coverage = intval($DB->get_field_sql('SELECT finalgrade FROM {grade_grades} WHERE userid=? AND itemid=(SELECT id FROM {grade_items} WHERE courseid=? AND itemtype=\'manual\' AND itemmodule=\'skillsaudit\' AND itemname=\'Total coverage\')', array($user->id, $cm->course)));
		
		$html .= '<td class="rating_td" id="rating_td_0_' . $user->id . '_totalconfidence">';
		$html .= get_rating_bar($total_confidence);
		remember('total_confidence', $totals, $total_confidence);
		$html .= $total_confidence;
		$html .= '%</td>';
		
		$html .= '<td class="rating_td" id="rating_td_0_' . $user->id . '_totalcoverage">';
		$html .= get_rating_bar($total_coverage);
		remember('total_coverage', $totals, $total_coverage);
		$html .= $total_coverage;
		$html .= '%</td>';
		
		$html .= '</tr>';
		
	}
	
	// add mean values
	$html .= '<tr>';
	$html .= '<th class="rating_td">Average</th>';
	foreach(array_keys($totals) as $name) {
		$average = round($totals[$name] / count($users));
		$html .= '<td class="rating_td">' . get_rating_bar($average) . $average . '%</td>';
	}
	$html .= '</tr>';
	$html .= '</tbody>';
	$html .= '</table>';
	ob_end_clean();
	return $html;
}

function skillsaudit_get_summary_html($cm, $userid){
	global $DB;
	// get all skills
	$last_update = 0;
	$all_skills = $DB->get_records_sql("SELECT id FROM {skills} WHERE courseid=?", array($cm->course));
	foreach($all_skills as $skill) {
		$skill->rated = false;
		$skill->min = 100;
		$skill->max = 0;
		$skill->latesttimestamp = 0;
		$skill->latest = 0;
		$skill->inthisaudit = false;
	}
	$skills_count_course = count($all_skills);
	
	// get all skills in this audit
	$skills = $DB->get_records_sql("SELECT skillid FROM {skillsinaudit} WHERE auditid = ?", array($cm->instance));
	$skills_count = count($skills);	
	foreach($skills as $skill) {
		$all_skills[$skill->skillid]->inthisaudit = true;
		$all_skills[$skill->skillid]->ratingcount = 0;
	}
	
	// get all ratings
	$ratings = $DB->get_records_sql("SELECT id, skillid, confidence, timestamp FROM {skillsauditrating} WHERE auditid IN (SELECT id FROM {skillsaudit} WHERE course=?) AND userid=?", array($cm->course, $userid));

	foreach ($ratings as $rating) {
		
		$all_skills[$rating->skillid]->rated = true;
		if($rating->confidence > $all_skills[$rating->skillid]->max) {
			$all_skills[$rating->skillid]->max = $rating->confidence;
		}
		if($rating->confidence < $all_skills[$rating->skillid]->min) {
			$all_skills[$rating->skillid]->min = $rating->confidence;
		}
		
		$all_skills[$rating->skillid]->ratingcount++;
		if($rating->timestamp > $all_skills[$rating->skillid]->latesttimestamp) {
			$all_skills[$rating->skillid]->latest = $rating->confidence;
			$all_skills[$rating->skillid]->latesttimestamp = $rating->timestamp;
			if($rating->timestamp > $last_update) {
				$last_update = $rating->timestamp;
			}
		}
	};
	$target_id = -1;
	$min_total_confidence = 0;
	$max_total_confidence = 0;
	$latest_total_confidence = 0;
	$rated_this_audit = 0;
	$rated_course = 0;
	$min_course = 0;
	$max_course = 0;
	$latest_course = 0;
	$lowest_confidence = 100;
	$chosen_unrated_target = false;
	//$debug = '';
	foreach($all_skills as $skill) {
		if($skill->rated && $skill->inthisaudit) {
			if($skill->latest < $lowest_confidence) {
				$target_id = $skill->id;
				$lowest_confidence = $skill->latest;
			}
			$min_total_confidence += $skill->min;
			$rated_this_audit++;
			$max_total_confidence += $skill->max;
			$latest_total_confidence += $skill->latest;
		}
		if($skill->rated) {
			$rated_course++;
			$min_course += $skill->min;
		} else {
			if($skill->inthisaudit && !$chosen_unrated_target) {
				$target_id = $skill->id;
				$chosen_unrated_target = true;
			}
		}
		$max_course += $skill->max;
		$latest_course += $skill->latest;
		//$debug .= print_r($skill, true);
	}
	$min_total_confidence = $rated_this_audit == 0?0:round($min_total_confidence / $rated_this_audit);
	$max_total_confidence = $rated_this_audit == 0?0:round($max_total_confidence / $rated_this_audit);
	$latest_total_confidence = $rated_this_audit == 0?0:round($latest_total_confidence / $rated_this_audit);

	$min_course = $rated_course == 0?0:round($min_course / $rated_course);
	$max_course = $rated_course == 0?0:round($max_course / $rated_course);
	$total_score = $rated_course == 0?0:round($latest_course / $skills_count_course);
	$latest_course = $rated_course == 0?0:round($latest_course / $rated_course);
	
	$percent_skills_rated = "$rated_this_audit / $skills_count = ". ($skills_count > 0?round(100*$rated_this_audit / $skills_count):0);
	
	$total_coverage = round(100*$rated_course / $skills_count_course);
	// percentage of skills rated (this course)
	$percent_skills_rated_course = "$rated_course / $skills_count_course = " . $total_coverage;
	
	// average confidence (this audit)
	$average_confidence = "from $min_total_confidence% to $max_total_confidence%. Latest: $latest_total_confidence";

	// average confidence (this course)
	$average_confidence_course = "from $min_course% to $max_course%. Latest: $latest_course";
	
	$html = '<h3>This topic:</h3>';
	$html .= '<div id="percent_skills_rated"><span class="summary_label">Coverage: </span><span class="summary_value">' . $percent_skills_rated . '%</span></div>';
	$html .= '<div id="average_confidence"><span class="summary_label">Average Confidence: </span><span class="summary_value">' . $average_confidence . '%</span></div>';
	$html .= '<h3>Whole course:</h3>';	
	$html .= '<div id="percent_skills_rated_course"><span class="summary_label">Coverage: </span><span class="summary_value">' . $percent_skills_rated_course . '%</span></div>';

	$html .= '<div id="average_confidence_course"><span class="summary_label">Average Confidence: </span><span class="summary_value">' . $average_confidence_course . '%</span></div>';
	
	$target = '';
	if($target_id > -1) {
		$r_target = $DB->get_record('skills', array('id'=>$target_id));
		$help = '';
		if($r_target->link) {
			$help .= '<a title="Click here for more info" href="' . $r_target->link . '" target="_blank"><span class="info_icon"></span></a>';
		}
		$target = '<div class="target_box"><div class="target_icon"></div><span class="target_number">' . $r_target->number . '</span> <span class="target_description">' . $r_target->description . '</span>' . $help . '</div>';
	}	
	
	$h = 120 * $total_score / 100;
	$d = 180 - (180 * $total_score / 100);
	$style = 'background-color: hsl(' . $h . ',100%,50%);transform:rotate(' . $d . 'deg)';
	$html .= '<div id="total_score"><span class="wrist wiggle"><span class="thumb" style="' . $style . '"></span></span><h3>Total: <span class="summary_value">' . $total_score . '%</span></h3></div>';
	
	$html .= '<h3>Suggested target:</h3>' . $target;
//	$html .= '<pre>' . $debug . '</pre>';
	
	// update grade
	$grade = new stdClass;
	$grade->dategraded = time();
	$grade->datesubmitted = $last_update;
	$grade->rawgrade = $latest_total_confidence;
	$grade->userid = $userid;
	$grades = array($userid => $grade);
	
	$item = array('itemname'=>$cm->name . ' (Confidence)');    
	ob_start();
	grade_update('mod/skillsaudit', $cm->course, 'mod', 'skillsaudit',
            $cm->instance, 0, $grades, NULL);
			
	$item = array('itemname'=>$cm->name . ' (Progress)');
	$grade->rawgrade = $latest_total_confidence - $min_total_confidence;
	$grades = array($userid => $grade);
	grade_update('mod/skillsaudit', $cm->course, 'mod', 'skillsaudit',
            $cm->instance, 1, $grades, $item);
			
	$item = array('itemname'=>$cm->name . ' (Coverage)');
	$grade->rawgrade = ($skills_count > 0?round(100*$rated_this_audit / $skills_count):0);
	$grades = array($userid => $grade);
	grade_update('mod/skillsaudit', $cm->course, 'mod', 'skillsaudit',
            $cm->instance, 2, $grades, $item);
			
	$categoryid = $DB->get_field('grade_items', 'categoryid', array('iteminstance' => $cm->instance, 'itemmodule' => 'skillsaudit', 'itemtype' => 'mod', 'itemnumber' => 0));
			
	// check if total confidence exists
	if($id = $DB->get_field('grade_items', 'id', array('courseid'=>$cm->course, 'itemname'=>'Total confidence', 'itemtype'=>'manual', 'itemmodule' => 'skillsaudit'))) {
	} else {	
		$params = array(
			'itemid' => $id,
			'needsupdate' => 0,
			'itemtype' => 'manual',
			'itemname' => 'Total confidence',
			'itemmodule' => 'skillsaudit',
			'categoryid' => $categoryid,
			'rawgrademin' => 0,
			'rawgrademax' => 100,
			'timemodified' => time(),
			'timecreated' => time(),
			'courseid' => $cm->course
			
			);
		$grade = new grade_item($params);
		$id = $DB->insert_record('grade_items', $grade);
	}
	if($grade = $DB->get_record('grade_grades', array('itemid' => $id, 'userid' => $userid))) {
		$grade->finalgrade = $total_score;
		$grade->timemodified = time();
		$DB->update_record('grade_grades', $grade);
	} else {	
		$grade = new stdClass();
		$grade->itemid = $id;
		$grade->userid = $userid;
		$grade->finalgrade = $total_score;
		$grade->rawgrademax = 100;
		$grade->rawgrademin = 0;
		$grade->timecreated = time();
		$grade->timemodified = time();
		$DB->insert_record('grade_grades', $grade);
	}
	
	// check if total coverage exists
	if($id = $DB->get_field('grade_items', 'id', array('courseid'=>$cm->course, 'itemname'=>'Total coverage', 'itemtype'=>'manual', 'itemmodule' => 'skillsaudit'))) {
	} else {	
		$params = array(
			'itemid' => $id,
			'needsupdate' => 0,
			'itemtype' => 'manual',
			'itemname' => 'Total coverage',
			'itemmodule' => 'skillsaudit',
			'categoryid' => $categoryid,
			'rawgrademin' => 0,
			'rawgrademax' => 100,
			'timemodified' => time(),
			'timecreated' => time(),
			'courseid' => $cm->course
			);
		$grade = new grade_item($params);
		$id = $DB->insert_record('grade_items', $grade);
	}
	if($grade = $DB->get_record('grade_grades', array('itemid' => $id, 'userid' => $userid))) {
		$grade->finalgrade = $total_coverage;
		$grade->timemodified = time();
		$DB->update_record('grade_grades', $grade);
	} else {	
		$grade = new stdClass();
		$grade->itemid = $id;
		$grade->userid = $userid;
		$grade->finalgrade = $total_coverage;
		$grade->rawgrademax = 100;
		$grade->rawgrademin = 0;
		$grade->timecreated = time();
		$grade->timemodified = time();
		$DB->insert_record('grade_grades', $grade);
	}
	
	ob_end_clean();
	return $html;
}

function skillsaudit_get_rating_html($rating, $can_clear_rating, $can_delete_rating, $auditid) {
	global $CFG;
	$html = '';
	$r = 180 - ($rating->confidence * 180.0 / 100.0);
	$h = round($rating->confidence * 120.0 / 100.0);
	$html .= '<div class="rating" id="rating_' . $rating->id .'"><span class="wrist"><span data-confidence="' . $rating->confidence . '" class="minithumb" style="transform: rotate(' . $r . 'deg); background-color: hsl(' . $h . ',100%,50%)"></span></span>';
	if($rating->auditid != $auditid) {
		$html .= '<div class="other_topic">From other topic';
		if(isset($rating->auditname)) {
			$cm = get_coursemodule_from_instance('skillsaudit', $rating->auditid, 0, false, MUST_EXIST);		
			$link = $CFG->wwwroot . '/mod/skillsaudit/view.php?id=' . $cm->id;
			$html .= ': <a href="' . $link . '">' . $rating->auditname . '</a>';			
		}
		$html .= '</div>';
	}
	$html .= '<span class="rating_date">' . date("D jS M g:ia", $rating->timestamp) . '</span>';
	if(strlen($rating->comment) > 0) {
		$html .= '<div class="rating_comment">' . format_text($rating->comment, FORMAT_MOODLE, NULL, true);
		if($can_clear_rating) {
			$html .= '<button class="btn btn-secondary btn_clear" id="btn_clear_' . $rating->id . '">' . get_string('clear') . '</button>';
		}
		$html .='</div>';
	}
	if($can_delete_rating) {
		$html .= '<button class="btn btn-secondary btn_delete" id="btn_delete_' . $rating->id . '">' . get_string('delete') . '</button>';
	}
	$html .='</div>';
	return $html;
}


/*
 * Does something really useful with the passed things
 *
 * @param array $things
 * @return object
 *function skillsaudit_do_something_useful(array $things) {
 *    return new stdClass();
 *}
 */
