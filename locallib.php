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
	public static function get_rating_bar($percentage, $label, $size="normal") {
		/*$background = 'linear-gradient(to right,red,hsl(' . round($percentage * 120.0 / 100.0) .',100%,50%))';
		return '<span class="conf_ind_cont" title="' . $percentage . '"><span class="conf_ind" style="width:' . $percentage . '%; background: ' . $background . '"></span>';*/
		
		$h = 120 * $percentage / 100;
		$d = 180 - (180 * $percentage / 100);
		$style = 'background-color: hsl(' . $h . ',100%,50%);transform:rotate(' . $d . 'deg)';
		$thumbclass = "thumb";
		if($size == "small") {
			$thumbclass = "minithumb";
		}
		$html = '<span class="wrist"><span class="' . $thumbclass . '" style="' . $style . '"></span></span><p>' . $label . ' <span class="summary_value">' . $percentage . '%</span></p>';
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
		
		$row[] = skillsaudit::get_rating_bar($coverage, get_string('coverage', 'mod_skillsaudit'));
		$row[] = skillsaudit::get_rating_bar($confidence, get_string('confidence', 'mod_skillsaudit'));
		
		
		
		if($total < $weakest['total']) {
			$weakest['total'] = $total;
			$weakest['row'] = $row;
		} 
			if(count($modinfo->instances['skillsaudit']) > 1) {


				if($total > $strongest['total']) {
					$strongest['total'] = $total;				
					$strongest['row'] = $row;
				}
			}
		
	}
	array_unshift($weakest['row'], '<h3>' . get_string('areatofocuson', 'mod_skillsaudit') . ':</h3> ');
	if($strongest['total'] > 0) {
		array_unshift($strongest['row'], '<h3>' . get_string('strongestarea', 'mod_skillsaudit') . ':</h3> ');
		$table->data[] = $strongest['row'];	
	}
	
	$table->data[] = $weakest['row'];
	/*$coverage = 0;
	$confidence = 0;
	$table->data[] = array('<h3>Total</h3>', '', filter_skillsaudit::get_rating_bar($coverage, 'Coverage'), filter_skillsaudit::get_rating_bar($coverage, 'Coverage'));*/
	
	return html_writer::table($table);
}

function skillsaudit_get_activity_summary($cm, $userid, $skillid) {
	global $DB;
	
	$html = skillsaudit_get_summary_html($cm, $userid, false);
	ob_start();
	// specific skill
	if($skillid > 0) {
		$ratings = $DB->get_records_sql("SELECT * FROM {skillsauditrating} WHERE skillid=? AND userid=? ORDER BY timestamp ASC", array($skillid, $userid));
		$audit = $DB->get_record('skillsaudit', array('id' => $cm->instance));
		$options = explode(",", $audit->options);
		$num_options = count($options) - 1;
		$html .= '<table class="table"><thead><tr><th>' . get_string('date', 'mod_skillsaudit') . ':</th><th>' . get_string('confidence', 'mod_skillsaudit') . ':</th></tr></thead><tbody>';
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
		$html .= '<table class="table"><thead><tr><th></th><th colspan="2">This topic:</th><th colspan="2">' . get_string('wholecourse', 'mod_skillsaudit') . ':</th></tr><tr><th>' . get_string('date', 'mod_skillsaudit') . ':</th><th>' . get_string('coverage', 'mod_skillsaudit') . ':</th><th>' . get_string('confidence', 'mod_skillsaudit') . ':</th><th>' . get_string('coverage', 'mod_skillsaudit') . ':</th><th>' . get_string('confidence', 'mod_skillsaudit') . ':</th></tr></thead><tbody>';
		
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
			$html .= '<td title="' . get_string('coverage', 'mod_skillsaudit') . ' (' . get_string('thistopic', 'mod_skillsaudit') . ')">';
			$html .= get_rating_html($stat->coverage);			
			$html .= $stat->coverage . '%';
			$html .= '</td>';
			
			$html .= '<td title="' . get_string('confidence', 'mod_skillsaudit') . ' (' . get_string('thistopic', 'mod_skillsaudit') . ')">';
			$html .= get_rating_html($stat->confidence);
			$html .= $stat->confidence . '%';
			$html .= '</td>';
			
			$html .= '<td title="' . get_string('coverage', 'mod_skillsaudit') . ' (' . get_string('wholecourse', 'mod_skillsaudit') . ')">';
			$html .= get_rating_html($stat->course_coverage);			
			$html .= $stat->course_coverage . '%';
			$html .= '</td>';
			
			$html .= '<td title="' . get_string('confidence', 'mod_skillsaudit') . ' (' . get_string('wholecourse', 'mod_skillsaudit') . ')">';
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

function skillsaudit_time_ago($timestamp) {
	if($timestamp > 0) { 
		$diff = (time() - $timestamp) / 86400;
		if($diff < 1) {
			$i = round($diff * 24);
			return $i . ' hour' . ($i > 1?'s':'') . ' ago';
		} else {
			if($diff > 14) {
				$i = round($diff / 7);
				return $i . ' week' . ($i > 1?'s':'') . ' ago';
			} else {
				$i = round($diff);
				return $i . ' day' . ($i > 1?'s':'') . ' ago';
			}
		} 
	} else {
		return "never";
	}
}

function skillsaudit_get_tracking_table($cm, $group, $skills, $highlight = "") {
	function get_rating_bar($percentage) {
		$background = 'linear-gradient(to right,red,hsl(' . round($percentage * 120.0 / 100.0) .',100%,50%))';
		return '<span class="conf_ind_cont" title="' . $percentage . '"><span class="conf_ind" style="width:' . $percentage . '%; background: ' . $background . '"></span></span>';
	}

	$show_status = false;
	if($highlight !== "") {
		$show_status = true;
	}

	global $DB, $CFG;
	$start = microtime(true);

	$html = '<table class="rating_table table table-bordered"><thead>';
	$html .= '<tr><th>Student</th><th colspan="4">' . get_string('thistopic', 'mod_skillsaudit') . '</th><th colspan="' . count($skills) . '">' . get_string('individualskills', 'mod_skillsaudit') . '</th></tr>';
	$html .= '<tr><th class="r_sortable" data-col="name">Name</th><th class="r_sortable" data-col="confidence">' . get_string('confidence', 'mod_skillsaudit') . '</th><th class="r_sortable" data-col="competence">' . get_string('competence', 'mod_skillsaudit') . '</th><th class="r_sortable" data-col="coverage">' . get_string('coverage', 'mod_skillsaudit') . '</th><th class="r_sortable" data-col="target">' . get_string('target', 'mod_skillsaudit') . '</th>';
	foreach($skills as $skill) {
		$link = s($skill->number);
		if(strlen($skill->link) > 1) {
			$link = '<a href="' . $skill->link . '" target="_blank">' . $skill->number . '</a>';
		}
		$html .= '<th data-toggle="tooltip" id="skill_title_' . $skill->id . '" class="r_sortable" data-col="skill_' . $skill->id . '" title="' . s($skill->description) . '"> ' . $link . '</th>';
	}
	$html .= '</tr></thead>';
	$html .= '<tbody>';


	// get users in group
	$context = context_module::instance($cm->id);
	$users = get_enrolled_users($context, 'mod/skillsaudit:submit', $group->id);
	$course = $DB->get_record('course', array('id' => $cm->course));

	foreach($users as $user) {
		$user_url = $CFG->wwwroot . '/mod/skillsaudit/?id=' . $cm->course . '&user=' . $user->id;
		$html .= '<tr><td class="rating_td " data-sortable="' . s($user->lastname . ' ' . $user->firstname) .'" data-col="name" id="rating_td_0_' . $user->id . '_name"><a href="' . $user_url . '" target="_blank">' . s($user->firstname . " " . $user->lastname) . '</a></td>';

		//$this_topic = skillsaudit_calculate_scores($cm->course, $user->id, $cm);
		$scores = skillsaudit_get_scores($cm->course, $user->id, $cm);
		$this_topic = $scores['topic'];
		
		if(!isset($this_topic['confidence'])) {
			$html .= '<td></td><td></td><td></td>';
			foreach($skills as $skill) {
				$html .= '<td></td>';
			}
			continue;
		}

		$html .= '<td class="rating_td" data-col="confidence" data-sortable="' . $this_topic['confidence'] . '" id="rating_td_0_' . $user->id . '_confidence">' . get_rating_bar($this_topic['confidence']) . ' ' .$this_topic['confidence'] . '%</td>';

		$html .= '<td class="rating_td" data-col="competence" data-sortable="' . $this_topic['competence'] . '" id="rating_td_0_' . $user->id . '_competence">' . get_rating_bar($this_topic['competence']) . ' ' .$this_topic['competence'] . '%';

		$html .= '</td>';

		$html .= '<td class="rating_td" data-col="coverage" data-sortable="' . $this_topic['coverage'] . '" id="rating_td_0_' . $user->id . '_coverage">' . get_rating_bar($this_topic['coverage']) . ' ' . $this_topic['coverage'] . '%</td>';
		
///TODO target

		$target = '';
		$target_id = '';
		$scores = skillsaudit_get_scores($cm->course, $user->id, $cm);
		//$this_topic = skillsaudit_calculate_scores($cm->course, $userid, $cm);
		$this_topic = $scores['topic'];


		if(array_key_exists('target', $this_topic)) {
			$help = '';
			if($this_topic['target']->link) {
				$help .= '<a title="' . get_string('targetlinktooltip', 'mod_skillsaudit') . '" href="' . $this_topic['target']->link . '" target="_blank"><span class="info_icon"></span></a>';
			}
			$target_id = $this_topic['target']->number;
			//$target = '<div class="target_box"><div class="target_icon"></div><span class="target_number">' . $this_topic['target']->number . '</span> <span class="target_description">' . $this_topic['target']->description . '</span>' . $help . '</div>';
			$target = '<div  class="target_box"><span class="target_number">';
			if($this_topic['target']->link) {
				$target .= '<a data-toggle="tooltip" title="' . htmlspecialchars($this_topic['target']->description). '" href="' . $this_topic['target']->link . '" target="_blank">';
			}
			$target .= $this_topic['target']->number;
			if($this_topic['target']->link) {
				$target .= '</a>';
			}
			$target .= '</span></div>';
		}

		$html .= '<td data-col="target" id="target_' . $user->id . '_target" data-sortable="' . $target_id . '">' . $target . '</td>';

		foreach($skills as $skill) {
			$r = '';
			$confidence = 0;
			$time_ago = '';
			$num_ratings = 0;

			$status = "no";
			if(isset($this_topic['ratings'][$skill->id])) {
				$r = $this_topic['ratings'][$skill->id];
				$confidence = $r->confidence;
			}
			
			$html .= '<td class="rating_td" data-sortable="' . $confidence . '" data-col="skill_' . $skill->id . '" id="rating_td_' . $skill->id .'_' . $user->id . '" data-toggle="tooltip" title="' . s($skill->description) . '">';
		
			if(isset($this_topic['ratings'][$skill->id]->timestamp)) {
				$time_ago = skillsaudit_time_ago($r->timestamp);
				$num_ratings = $r->ratings;

				$latest_hue = 'hsl(' . round($r->confidence * 120.0 / 100.0) . ', 100%, 50%)';
				$background = 'linear-gradient(to right,red,hsl(' . $latest_hue .',100%,50%))';
				$lowest_hue = 'hsl(' . round($r->lowest * 120.0 / 100.0) . ', 100%, 50%)';
				$html .= '<span class="conf_ind_cont"><span class="conf_ind" id="conf_ind_' . $skill->id . '_' . $user->id . '" style="width:' . $r->confidence . '%; background: ' . $latest_hue . '"></span>';
				$html .= '<div class="conf_ind_lowest" id="conf_ind_lowest_' . $skill->id . '_' . $user->id . '" style="left:' . $r->lowest . '%;background: ' . $lowest_hue . '"></div>';
							
				$html .= '</span>';

				$diff = (time() - $r->timestamp) / 86400;
				if($highlight == "today") {
					if($diff < 1) {
						$status = "yes";
					}
				}
				
				if($highlight == "one") {
					if($r->ratings >=1) {
						$status = "yes";
					}
				}
				
				if($highlight == "two") {
					if($r->ratings >=2) {
						$status = "yes";
					}
				}
			}

			$html .= '<span class="num_ratings">' . $num_ratings . '</span> ';

			if($show_status) {
				$html .= '<div class="conf_ind_status conf_ind_status_' . $status . '"></div>';
			}
			
			$html .= '<div class="latest_rating_time">' . $time_ago . '</div>';
			
			$html .= '</td>';
		}

		$html .= '</tr>';
	}


	$html .= '</tbody></table>';
	$html .= 'Time: ' . round(microtime(true) - $start, 5) . "s";
	return $html;
}

function skillsaudit_get_scores($courseid, $userid, $cm) {
	global $DB;
	$start = microtime(true);
	$skills = $DB->get_records_sql('SELECT id, description, number, link FROM {skills} WHERE courseid=?', array($courseid));
	$topicskills = $DB->get_records_sql('SELECT skillid FROM {skillsinaudit} WHERE auditid=?', array($cm->instance));
	$skillids = join(',', array_keys($topicskills));
	$ratings = $DB->get_records_sql('SELECT id, skillid, confidence, timestamp FROM {skillsauditrating} WHERE skillid IN (SELECT id FROM {skills} WHERE courseid=?) AND userid=?', array($courseid, $userid));
	foreach($skills as $skill) {
		if(array_key_exists($skill->id, $topicskills)) {
			$skill->intopic = true;
		} else {
			$skill->intopic = false;
		}
	}
	
	$course = array(
		'confidence' => 0,
		'coverage' => 0,
		'competence' => 0,
		'breakdown' => [],
		'target' => [],
		'lastupdated' => 0,
		'ratings' => []
	);
	
	$t = new stdClass();
	$t->link = '';
	$t->number = '';
	$t->description = get_string('noratingstarget', 'mod_skillsaudit');
	
	
	$topic = array(
		'confidence' => 0,
		'coverage' => 0,
		'competence' => 0,
		'breakdown' => [],
		'target' => $t,
		'lastupdated' => 0,
		'ratings' => []
	);
	
	foreach($ratings as $r) {
		if(!array_key_exists($r->skillid, $course['ratings'])) {
			// skill not rated yet - save current rating
			$r->lowest = $r->confidence;
			$r->highest = $r->confidence;
			$r->ratings = 1;
			$r->description = $skills[$r->skillid]->description;
			$r->number = $skills[$r->skillid]->number;
			$r->link = $skills[$r->skillid]->link;
			$course['ratings'][$r->skillid] = $r;
		} else {
			
			// skill already rated - check if it needs updating
			if($r->confidence > $course['ratings'][$r->skillid]->highest) {
				$course['ratings'][$r->skillid]->confidence = $r->confidence;
			}
			
			if($r->confidence < $course['ratings'][$r->skillid]->lowest) {
				$course['ratings'][$r->skillid]->highest = $r->confidence;
			}
			
			if($r->timestamp > $course['ratings'][$r->skillid]->timestamp) {
				$course['ratings'][$r->skillid]->timestamp = $r->timestamp;
			}
			
			$course['ratings'][$r->skillid]->ratings++;
		} 
	}

	// add any skills that haven't been rated yet	
	foreach($skills as $s) {
		if(!array_key_exists($s->id, $course['ratings'])) {
			$course['ratings'][$s->id] = new stdClass();
			$course['ratings'][$s->id]->skillid = $s->id;
			$course['ratings'][$s->id]->confidence = NULL;
		}
	}
	
	// copy stats for skills in this topic
	foreach($course['ratings'] as $r) {
		if(array_key_exists($r->skillid, $topicskills)) {
			$topic['ratings'][$r->skillid] = $r;
		}
	}
	
	// calcuate course averages
	$total_coverage = 0;
	$total_confidence = 0;
	$total_skills = 0;
	foreach($course['ratings'] as $r) {
		if(!is_null($r->confidence)) {
			$total_confidence += $r->confidence;
			$total_coverage++;
		}
		$total_skills++;
	}
	if($total_skills > 0) {
		$course['confidence'] = round($total_confidence / $total_skills, 1);
		$course['coverage'] = round($total_coverage * 100 / $total_skills, 1);
	}
	
	// calcuate topic averages
	$total_coverage = 0;
	$total_confidence = 0;
	$total_skills = 0;
	$lowest_confidence = 101;
	foreach($topic['ratings'] as $r) {
		if(!is_null($r->confidence)) {
			$total_confidence += $r->confidence;
			$total_coverage++;
			if($r->confidence < $lowest_confidence) {
				$lowest_confidence = $r->confidence;
				$topic['target'] = $r;
			}
		}
		$total_skills++;
	}
	
	if($lowest_confidence == 101) {
		$topic['target'] = $t;
	}
	
	
	if($total_skills > 0) {
		$topic['confidence'] = round($total_confidence / $total_skills, 1);
		$topic['coverage'] = round($total_coverage * 100 / $total_skills, 1);
	}
	
	// get grades for course
	$grades = $DB->get_records_sql("SELECT gi.id, gi.courseid, g.finalgrade, g.rawgrade, gi.itemname, gi.itemtype, gi.itemmodule 
			FROM {grade_items} AS gi 
			LEFT JOIN {grade_grades} AS g ON gi.id = g.itemid
			WHERE gi.courseid=? AND (gi.itemmodule <> ? OR (gi.itemmodule IS NULL AND gi.itemtype ='manual')) AND (g.userid=? OR g.userid IS NULL)", 
			[$courseid, 'skillsaudit', $userid]);
	$course['breakdown'] = $grades;
	
	// calculate average grade for course
	$count = 0;
	$total_grade = 0;
	foreach($grades as $grade) {
		if(!is_null($grade->finalgrade)) {
			$total_grade += $grade->finalgrade;
			$count += 1;
		}	
	}
	if($count > 0) {
		$course['competence'] = round($total_grade / $count,1);
	}
	
	
	// get all grade items in the same category that aren't for a skillsaudit
	$categoryid = $DB->get_field_sql("SELECT categoryid FROM {grade_items} 
		WHERE courseid=? AND itemmodule=? AND iteminstance=? AND itemnumber=0", 
		[$courseid, 'skillsaudit', $cm->instance]);
			
	// get grade for topic
	$grades = $DB->get_records_sql("SELECT gi.id, gi.iteminstance, gi.courseid, g.finalgrade, g.rawgrade, gi.itemname, gi.itemtype, gi.itemmodule
			FROM {grade_items} AS gi 
			LEFT JOIN {grade_grades} AS g ON gi.id = g.itemid
			WHERE gi.courseid=? AND gi.categoryid=? AND (gi.itemmodule <> ? OR (gi.itemmodule IS NULL AND gi.itemtype ='manual')) AND (g.userid=? OR g.userid IS NULL)", 
			[$courseid, $categoryid, 'skillsaudit', $userid]);
	$topic['breakdown'] = $grades;
			
	// calculate average grade for course
	$count = 0;
	$total_grade = 0;
	foreach($grades as $grade) {
		if(!is_null($grade->finalgrade)) {
			$total_grade += $grade->finalgrade;
			$count += 1;
		}
		
	}
	if($count > 0) {
		$topic['competence'] = round($total_grade / $count,1);
	}
	
	$newtime = microtime(true) - $start;
	/*$start = microtime(true);
	$oldcourse = skillsaudit_calculate_scores($courseid, $userid);
	$oldtopic = skillsaudit_calculate_scores($courseid, $userid, $cm);
	$oldtime = microtime(true) - $start;*/
	return array(
		'course'=>$course,
		'topic'=>$topic	
	);
}

// if $cm is NULL then stats will be calculated for the whole course
function skillsaudit_calculate_scores($courseid, $userid, $cm = NULL) {
	global $DB;
	$t = new stdClass();
	$t->link = '';
	$t->number = '';
	$t->description = get_string('noratingstarget', 'mod_skillsaudit');
	$scores = array(
			"confidence" => 0, 
			"coverage" => 0, 
			"competence" => 0,
			"breakdown" => array(),
			"target" => $t,
			"lastupdated" => time(),
			"ratings" => array()
		);	
		
	$competence = 0;
	$lastupdated = 0;

	if($cm != NULL) { // get skills for a specific skilsaudit
			
			$skills = $DB->get_records_sql("SELECT s.skillid, sk.description, sk.number, sk.link,
				(SELECT COUNT(id) FROM {skillsauditrating} WHERE skillid=sk.id AND userid=?) AS ratings,
			    (SELECT MIN(confidence) FROM {skillsauditrating} WHERE skillid=sk.id AND userid=?) AS lowest,
			    (SELECT MAX(confidence) FROM {skillsauditrating} WHERE skillid=sk.id AND userid=?) AS highest,
			    (SELECT confidence FROM {skillsauditrating} WHERE skillid=sk.id AND userid=? ORDER BY timestamp DESC LIMIT 1) AS confidence,
			    (SELECT timestamp FROM {skillsauditrating} WHERE skillid=sk.id AND userid=? ORDER BY timestamp DESC LIMIT 1) AS timestamp
			FROM {skillsinaudit} AS s 
    			JOIN {skills} AS sk ON s.skillid=sk.id
		    WHERE s.auditid=?", [$userid, $userid, $userid, $userid, $userid, $cm->instance]);

		// get all grade items in the same category that aren't for a skillsaudit
		$categoryid = $DB->get_field_sql("SELECT categoryid FROM {grade_items} 
			WHERE courseid=? AND itemmodule=? AND iteminstance=? AND itemnumber=0", 
			[$courseid, 'skillsaudit', $cm->instance]);
		
		$grades = $DB->get_records_sql("SELECT gi.id, gi.iteminstance, gi.courseid, g.finalgrade, g.rawgrade, gi.itemname, gi.itemtype, gi.itemmodule
			FROM {grade_items} AS gi 
			LEFT JOIN {grade_grades} AS g ON gi.id = g.itemid
			WHERE gi.courseid=? AND gi.categoryid=? AND (gi.itemmodule <> ? OR (gi.itemmodule IS NULL AND gi.itemtype ='manual')) AND (g.userid=? OR g.userid IS NULL)", 
			[$courseid, $categoryid, 'skillsaudit', $userid]);
		
		$total_grade = 0;
		// calculate average grade
		$count = count($grades);
		$valid_grades = 0;
		if($count > 0) {
			foreach($grades as $grade) {
				$total_grade += $grade->finalgrade;
				if(!is_null($grade->finalgrade)) {
					$valid_grades++;
				}
			}
			if($valid_grades > 0) {
				$competence = $total_grade / $valid_grades;	
			}
			
		}

	} else {
		//return $scores;
		// get all learning objectives
		/*$skills = $DB->get_records_sql("SELECT sk.id AS skillid, sk.description, sk.number, sk.link,
				(SELECT COUNT(id) FROM {skillsauditrating} WHERE skillid=sk.id AND userid=?) AS ratings,
			    (SELECT MIN(confidence) FROM {skillsauditrating} WHERE skillid=sk.id AND userid=?) AS lowest,
			    (SELECT MAX(confidence) FROM {skillsauditrating} WHERE skillid=sk.id AND userid=?) AS highest,
			    (SELECT confidence FROM {skillsauditrating} WHERE skillid=sk.id AND userid=? ORDER BY timestamp DESC LIMIT 1) AS confidence,
			    (SELECT timestamp FROM {skillsauditrating} WHERE skillid=sk.id AND userid=? ORDER BY timestamp DESC LIMIT 1) AS timestamp
    			FROM {skills} AS sk 
		    WHERE sk.courseid=?", [$userid, $userid, $userid, $userid, $userid, $courseid]);
			*/
//			return $scores;
			
			$skills = $DB->get_records_sql("SELECT sk.id AS skillid, 
			    (SELECT confidence FROM {skillsauditrating} WHERE skillid=sk.id AND userid=? ORDER BY timestamp DESC LIMIT 1) AS confidence
    			FROM {skills} AS sk 
		    WHERE sk.courseid=?", [$userid, $courseid]);

		$grades = $DB->get_records_sql("SELECT gi.id, gi.courseid, g.finalgrade, g.rawgrade, gi.itemname, gi.itemtype, gi.itemmodule 
			FROM {grade_items} AS gi 
			LEFT JOIN {grade_grades} AS g ON gi.id = g.itemid
			WHERE gi.courseid=? AND (gi.itemmodule <> ? OR (gi.itemmodule IS NULL AND gi.itemtype ='manual')) AND (g.userid=? OR g.userid IS NULL)", 
			[$courseid, 'skillsaudit', $userid]);
		
		$total_grade = 0;
		// calculate average grade
		$count = count($grades);
		if($count > 0) {
			foreach($grades as $grade) {
				$total_grade += $grade->finalgrade;
			}
			$competence = $total_grade / $count;
		}
	}

	$count = count($skills);
	if($count > 0) {
		$total_confidence = 0;
		$total_coverage = 0;

		$lowest_confidence = 101;
		$target = [];

		foreach ($skills as $lo) {
			if(!is_null($lo->confidence)) {
				$total_confidence += $lo->confidence;
				$total_coverage += 1;	
			}
			if($lo->confidence < $lowest_confidence) {
				$target = $lo;
				$lowest_confidence = $lo->confidence;
			}
			if(isset($lo->timestamp)) {
				if($lo->timestamp > $lastupdated) {
					$lastupdated = $lo->timestamp;
				}
			}
		}

		$scores = array(
			"confidence" => round($total_confidence / $count, 1), 
			"coverage" => round(100 * $total_coverage / $count, 1), 
			"competence" => round($competence, 1),
			"breakdown" => $grades,
			"target" => $target,
			"lastupdated" => $lastupdated,
			"ratings" => $skills
		);	
	}
	

	return $scores;
}

function skillsaudit_get_summary_html($cm, $userid, $includechart=true){
	global $DB, $CFG, $OUTPUT, $PAGE;
	$html = '';
		
	$target = '';
	$scores = skillsaudit_get_scores($cm->course, $userid, $cm);
	//$this_topic = skillsaudit_calculate_scores($cm->course, $userid, $cm);
	$this_topic = $scores['topic'];

	if(array_key_exists('target', $this_topic)) {
		$help = '';
		if($this_topic['target']->link) {
			$help .= '<a title="' . get_string('targetlinktooltip', 'mod_skillsaudit') . '" href="' . $this_topic['target']->link . '" target="_blank"><span class="info_icon"></span></a>';
		}
		$target = '<div class="target_box"><div class="target_icon"></div><span class="target_number">' . $this_topic['target']->number . '</span> <span class="target_description">' . $this_topic['target']->description . '</span>' . $help . '</div>';
	} 

	//$totals = skillsaudit_calculate_scores($cm->course, $userid);
	$totals = $scores['course'];
	//$html .= '<pre>' . print_r($scores, true) . '</pre>';
	
	$html .= '<h3>' . get_string('suggestedtarget', 'mod_skillsaudit') . ':</h3>' . $target;
	$html .= '<div class="summary_target">';
	$h = 120 * $totals['confidence'] / 100;
	$d = 180 - (180 * $totals['confidence'] / 100);
	$style = 'background-color: hsl(' . $h . ',100%,50%);transform:rotate(' . $d . 'deg)';
	$html .= '<div id="total_score"><span class="wrist wiggle"><span class="thumb" style="' . $style . '"></span></span><h3>' . get_string('total', 'mod_skillsaudit') . ': <span class="summary_value">' . $totals['confidence'] . '%</span></h3></div>';

	$html .= '<p>' . get_string('aboutconfidence', 'mod_skillsaudit') . '</p><p>' . get_string('aboutcoverage', 'mod_skillsaudit') . '</p>';
	$PAGE->set_cm($cm);	

	if(count($totals["breakdown"]) > 0 && count($this_topic["breakdown"]) > 0) {
		$html .= '<p>' . get_string('aboutcompetence', 'mod_skillsaudit') . ':</p>';
		$html .= '<table class="table"><tr><th>Activity</th><th>Grade</th></tr>';
		foreach($this_topic['breakdown'] as $grade) {
			
			$grade_str = get_string('nograde', 'mod_skillsaudit');
			if(!is_null($grade->finalgrade)) {
				$grade_str = round($grade->finalgrade, 1) . "%";
			}
			if(!is_null($grade->itemmodule)) {
				$mod = get_coursemodule_from_instance($grade->itemmodule, $grade->iteminstance);
				$url = $CFG->wwwroot . '/mod/' . $grade->itemmodule . '/view.php?id=' . $mod->id;
				$html .= '<tr><td><a href="' . $url . '" target="_blank"><img class="iconlarge activityicon" src="' . $OUTPUT->pix_url('icon', 'mod_' . $grade->itemmodule) . '"> ' . $grade->itemname .'</a></td><td>' . $grade_str . '</td></tr>';
			} else {
				$html .= '<tr><td>' . $grade->itemname .'</td><td>' . $grade_str . '</td></tr>';
			}
			
		}
		$html .= '</table>';
	}
	$html .= '<a href="' . $CFG->wwwroot . '/mod/skillsaudit/?id=' . $cm->course . '">' . get_string('viewcoursesummary', 'mod_skillsaudit') . '</a>';
	$html .= '</div>';

	
	if($includechart) {
		$chart = new core\chart_bar();
		$chart->set_title(get_string('summary', 'mod_skillsaudit') . ":");

		if(count($totals["breakdown"]) > 0 && count($this_topic["breakdown"]) > 0) {
			$chart->add_series(new core\chart_series(get_string('thistopic', 'mod_skillsaudit'), [$this_topic["confidence"], $this_topic["coverage"], $this_topic["competence"]]));
			$chart->add_series(new core\chart_series(get_string('wholecourse', 'mod_skillsaudit'), [$totals["confidence"], $totals["coverage"], $totals["competence"]]));
			$chart->set_labels([get_string('confidence', 'mod_skillsaudit') . " (%)", 
				get_string('coverage', 'mod_skillsaudit') . " (%)", 
				get_string('competence', 'mod_skillsaudit') . " (%)"]);
		} else {
			$chart->add_series(new core\chart_series(get_string('thistopic', 'mod_skillsaudit'), [$this_topic["confidence"], $this_topic["coverage"]]));
			$chart->add_series(new core\chart_series(get_string('wholecourse', 'mod_skillsaudit'), [$totals["confidence"], $totals["coverage"]]));	
			$chart->set_labels([get_string('confidence', 'mod_skillsaudit') . " (%)", get_string('coverage', 'mod_skillsaudit') . " (%)"]);
		}
		global $OUTPUT;	
		$charthtml = $OUTPUT->render($chart);
	} else {
		$charthtml = '';
		

		$charthtml .= '<table class="table"><thead><tr><th>' . get_string('thistopic', 'mod_skillsaudit') . '</th><th>' . get_string('wholecourse', 'mod_skillsaudit') . '</th></tr>';
		$charthtml .= '<tbody>';
		$charthtml .= '<tr><td>' . skillsaudit::get_rating_bar($this_topic['confidence'], get_string('confidence', 'mod_skillsaudit'), 'small') . '</td>';
		$charthtml .= '<td>' . skillsaudit::get_rating_bar($totals['confidence'], get_string('confidence', 'mod_skillsaudit'), 'small') . '</td></tr>';

		$charthtml .= '<tr><td>' . skillsaudit::get_rating_bar($this_topic['coverage'], get_string('coverage', 'mod_skillsaudit'), 'small') . '</td>';
		$charthtml .= '<td>' . skillsaudit::get_rating_bar($totals['coverage'], get_string('coverage', 'mod_skillsaudit'), 'small') . '</td></tr>';
		if(count($totals["breakdown"]) > 0 && count($this_topic["breakdown"]) > 0) {
			$charthtml .= '<tr><td>' . skillsaudit::get_rating_bar($this_topic['competence'], get_string('competence', 'mod_skillsaudit'), 'small') . '</td>';
			$charthtml .= '<td>' . skillsaudit::get_rating_bar($totals['competence'], get_string('competence', 'mod_skillsaudit'), 'small') . '</td></tr>';
		}
		$charthtml .= '</tbody></table>';
	}
	$html .= '<div class="summary_chart">' . $charthtml . '</div>';
	

	
	// update grade
	$grade = new stdClass;
	$grade->dategraded = time();
	$grade->datesubmitted = $this_topic['lastupdated'];
	$grade->rawgrade = $this_topic['confidence'];
	$grade->userid = $userid;
	$grade->feedback = $this_topic['target']->number . ': ' . $this_topic['target']->description;
	$grades = array($userid => $grade);
	
	$item = array('itemname'=>$cm->name . ' (Confidence)');    
	ob_start();
	grade_update('mod/skillsaudit', $cm->course, 'mod', 'skillsaudit',
            $cm->instance, 0, $grades, $item);
	$grade->feedback = NULL;
			
	$item = array('itemname'=>$cm->name . ' (Competence)');
	$grade->rawgrade = $this_topic['competence'];
	$grades = array($userid => $grade);
	grade_update('mod/skillsaudit', $cm->course, 'mod', 'skillsaudit',
            $cm->instance, 1, $grades, $item);
			
	$item = array('itemname'=>$cm->name . ' (Coverage)');
	$grade->rawgrade = $this_topic['coverage'];
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
		$grade->finalgrade = $totals['confidence'];
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
		$grade->finalgrade = $totals['coverage'];
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
