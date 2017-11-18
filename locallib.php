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
	foreach($all_skills as $skill) {
		if($skill->rated && $skill->inthisaudit) {
			if($skill->latest < $lowest_confidence) {
				$target_id = $skill->id;
				$lowest_confidence = $skill->latest;
			}
			$rated_this_audit++;
			$min_total_confidence += $skill->min;
			$max_total_confidence += $skill->max;
			$latest_total_confidence += $skill->latest;
		}
		if($skill->rated) {
			$rated_course++;
		} else {
			$target_id = $skill->id;
		}
		$min_course += $skill->min;
		$max_course += $skill->max;
		$latest_course += $skill->latest;
	}
	$min_total_confidence = $rated_this_audit == 0?0:round($min_total_confidence / $rated_this_audit);
	$max_total_confidence = $rated_this_audit == 0?0:round($max_total_confidence / $rated_this_audit);
	$latest_total_confidence = $rated_this_audit == 0?0:round($latest_total_confidence / $rated_this_audit);
	
	$min_course = $rated_course == 0?0:round($min_course / $rated_course);
	$max_course = $rated_course == 0?0:round($max_course / $rated_course);
	$total_score = $rated_course == 0?0:round($latest_course / $skills_count_course);
	$latest_course = $rated_course == 0?0:round($latest_course / $rated_course);
	
	$percent_skills_rated = "$rated_this_audit / $skills_count = ". round(100*$rated_this_audit / $skills_count);
	
	// percentage of skills rated (this course)
	$percent_skills_rated_course = "$rated_course / $skills_count_course = " . round(100*$rated_course / $skills_count_course);
	
	// average confidence (this audit)
	$average_confidence = "from $min_total_confidence% to $max_total_confidence%. Latest: $latest_total_confidence";
	
	// average confidence (this course)
	$average_confidence_course = "from $min_course% to $max_course%. Latest: $latest_course";
	
	$html = '<h3>This topic:</h3>';
	$html .= '<div id="percent_skills_rated"><span class="summary_label">Coverage: </span><span class="summary_value">' . $percent_skills_rated . '%</span></div>';
	$html .= '<div id="average_confidence"><span class="summary_label">Confidence: </span><span class="summary_value">' . $average_confidence . '%</span></div>';
	$html .= '<h3>Whole course:</h3>';	
	$html .= '<div id="percent_skills_rated_course"><span class="summary_label">Coverage: </span><span class="summary_value">' . $percent_skills_rated_course . '%</span></div>';
	$html .= '<div id="average_confidence_course"><span class="summary_label">Confidence: </span><span class="summary_value">' . $average_confidence_course . '%</span></div>';
	
	$target = '';
	if($target_id > -1) {
		$r_target = $DB->get_record('skills', array('id'=>$target_id));
		$target = '<div class="target_box"><span class="target_number">' . $r_target->number . '</span> <span class="target_description">' . $r_target->description . '</span></div>';
	}	
	
	$h = 120 * $total_score / 100;
	$d = 180 - (180 * $total_score / 100);
	$style = 'background-color: hsl(' . $h . ',100%,50%);transform:rotate(' . $d . 'deg)';
	$html .= '<div id="total_score"><span class="wrist wiggle"><span class="thumb" style="' . $style . '"></span></span><h3>Total: <span class="summary_value">' . $total_score . '%</span></h3></div>';
	
	$html .= '<h3>Suggested target:</h3>' . $target;
	
	
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
			
	$item = array('itemname'=>$cm->name . ' (Completed)');
	$grade->rawgrade = round(100*$rated_this_audit / $skills_count);
	$grades = array($userid => $grade);
	grade_update('mod/skillsaudit', $cm->course, 'mod', 'skillsaudit',
            $cm->instance, 2, $grades, $item);
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
			$html .= '<button class="btn_clear" id="btn_clear_' . $rating->id . '">' . get_string('clear') . '</button>';
		}
		$html .='</div>';
	}
	if($can_delete_rating) {
		$html .= '<button class="btn_delete" id="btn_delete_' . $rating->id . '">' . get_string('delete') . '</button>';
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
