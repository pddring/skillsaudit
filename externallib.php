<?php
require_once("$CFG->libdir/externallib.php");
require_once(dirname(__FILE__).'/locallib.php');
class mod_skillsaudit_external extends external_api {
    
    public static function delete_feedback($cmid, $feedbackid) {
        global $DB, $USER;
        $params = self::validate_parameters(self::delete_feedback_parameters(), array('cmid' => $cmid, 'feedbackid' => $feedbackid));
        $cm = get_coursemodule_from_id('skillsaudit', $cmid, 0, false, MUST_EXIST);		
        $context = context_module::instance($cm->id);
        require_capability('mod/skillsaudit:postfeedback', $context);
        $DB->delete_records('skillsauditfeedback', array('auditid'=>$cm->instance, 'id'=>$feedbackid));
        return $feedbackid;
    }
    
    public static function delete_feedback_parameters() {
            return new external_function_parameters(
                    array(
                            'cmid' => new external_value(PARAM_INT, 'course module id'),
                            'feedbackid' => new external_value(PARAM_INT, 'feedback id'),
                    )
            );
    }
    
    public static function delete_feedback_returns() {
            return new external_value(PARAM_INT, 'feedback id if successful or 0 if not');
    }
    
    
    public static function post_feedback($cmid, $skillid, $userid, $comment) {
        global $DB, $USER;
        $params = self::validate_parameters(self::post_feedback_parameters(), array('cmid' => $cmid, 'skillid' => $skillid, 'userid' => $userid, 'comment' => $comment));
        $cm = get_coursemodule_from_id('skillsaudit', $cmid, 0, false, MUST_EXIST);		
        $context = context_module::instance($cm->id);
        require_capability('mod/skillsaudit:postfeedback', $context);
        $feedback = new stdClass();
        $feedback->userid = $userid;
        $feedback->fromid = $USER->id;
        $feedback->auditid = $cm->instance;
        $feedback->skillid = $skillid;
        $feedback->timestamp = time();
        $feedback->message = $comment;
        $DB->insert_record('skillsauditfeedback', $feedback);
        $html = print_r($feedback, true);
        return $html;
    }
    
    public static function post_feedback_parameters() {
            return new external_function_parameters(
                    array(
                            'cmid' => new external_value(PARAM_INT, 'course module id'),
                            'skillid' => new external_value(PARAM_INT, 'skill id'),
                            'userid' => new external_value(PARAM_INT, 'user id'),
                            'comment' => new external_value(PARAM_TEXT, 'Comment')
                    )
            );
    }

    public static function post_feedback_returns() {
            return new external_value(PARAM_RAW, 'HTML version of comment');
    }
    
    public static function get_activity_summary($cmid, $userid, $skillid) {
            global $DB;
            $params = self::validate_parameters(self::get_activity_summary_parameters(), array('cmid' => $cmid, 'userid' => $userid, 'skillid' => $skillid));
            $cm = get_coursemodule_from_id('skillsaudit', $cmid, 0, false, MUST_EXIST);		
            $context = context_module::instance($cm->id);
            require_capability('mod/skillsaudit:trackratings', $context);
            $html = skillsaudit_get_activity_summary($cm, $userid, $skillid);
            
            $sql = 'SELECT * FROM {skillsauditfeedback} WHERE auditid = ? AND userid = ? AND skillid IN (0, ?) ORDER BY timestamp ASC';
            if($skillid == 0) {
                $sql = 'SELECT * FROM {skillsauditfeedback} WHERE auditid = ? AND userid = ? ORDER BY timestamp ASC';
            }
            $feedback = $DB->get_records_sql($sql,
                    array($cm->instance, $userid, $skillid));
            if(count($feedback) > 0) {
                $html .= '<h4>Teacher Feedback:</h4>';
                foreach($feedback as $f) {
                    $fromuser = $DB->get_record('user', array('id'=>$f->fromid));
                    $html .= '<div class="teacher_feedback" id="teacher_feedback_' . $f->id . '">';
                    if($f->skillid > 0) {
                        $skill = $DB->get_record('skills', array('id'=>$f->skillid));
                        $html .= '<div class="feedback_skill">';
                        if(str_len($skill->link) > 0) {
                            $html .= '<a href="' . $skill->link . '"><span class="info_icon"></span></a>';
                        }
                        $html .= '<b>' . $cm->name . ': ' . $skill->number . '</b> ' . $skill->description . ':</div>';
                    } else {
                        $html .= '<div class="feedback_skill">' . $cm->name . ':</div>';
                    }
                    $html .=  '<div class="feedback_message">' . format_text($f->message, FORMAT_MOODLE, array('context'=>$context)) . '</div>';
                    $html .= '<div class="feedback_from">From ' . $fromuser->firstname . ' ' . $fromuser->lastname . ' on ' . date("D jS M Y", $f->timestamp) . '</div>';
                    $html .= '<button class="btn btn-secondary btn_delete_feedback" id="btn_delete_feedback_' . $f->id . '">Delete</button>';
                    $html .= '</div>';
                }
            }

            if(has_capability('mod/skillsaudit:postfeedback', $context)) {
                $html .= '<h4>Add Comment:</h4><textarea class="form-control rating_comment_editor" id="rating_comment_' . $cmid . '_' . $userid . '_' . $skillid . '"></textarea>';
            }
            return $html; 
    }

    public static function get_activity_summary_parameters() {
            return new external_function_parameters(
                    array(
                            'cmid' => new external_value(PARAM_INT, 'course module id'),
                            'userid' => new external_value(PARAM_INT, 'user id'),
                            'skillid' => new external_value(PARAM_INT, 'skill id')
                    )
            );
    }

    public static function get_activity_summary_returns() {
            return new external_value(PARAM_RAW, 'HTML with activity summary');
    }

    public static function update_tracker_parameters() {
            return new external_function_parameters(
                    array(
                            'cmid' => new external_value(PARAM_INT, 'course module id'),
                            'groupid' => new external_value(PARAM_INT, 'group id'),
                            'highlight' => new external_value(PARAM_TEXT, 'skills to highlight')
                    )
            );
    }

    public static function update_tracker_returns() {
            return new external_value(PARAM_RAW, 'HTML with summary of ratings');
    }

    public static function update_tracker($cmid, $groupid, $highlight){
            global $DB;
            $params = self::validate_parameters(self::update_tracker_parameters(), array('cmid' => $cmid, 'groupid' => $groupid, 'highlight' => $highlight));

            $cm = get_coursemodule_from_id('skillsaudit', $cmid, 0, false, MUST_EXIST);		
            $context = context_module::instance($cm->id);
            require_capability('mod/skillsaudit:trackratings', $context);
            $group = $DB->get_record('groups', array('id' => $groupid));
            $skills = $DB->get_records_sql('SELECT s.* FROM {skills} s WHERE s.id IN (SELECT skillid FROM {skillsinaudit} WHERE auditid = ?) ORDER BY number ASC', array($cm->instance));
            return skillsaudit_get_tracking_table($cm, $group, $skills, $highlight);
    }

    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function clear_rating_parameters() {
        return new external_function_parameters(
            array(
				'cmid' => new external_value(PARAM_INT, 'course module id'),
				'ratingid' => new external_value(PARAM_INT, 'confidence rating id')
            )
        );
    }
	
	public static function clear_rating_returns() {
        return new external_value(PARAM_INT, 'rating id deleted');
    }
	
	public static function clear_rating($cmid, $ratingid) {
		// check we have access rights to change skills
		global $CFG, $USER, $DB;
		
		$params = self::validate_parameters(self::delete_rating_parameters(), array('cmid' => $cmid, 'ratingid' => $ratingid));
		
		$cm = get_coursemodule_from_id('skillsaudit', $cmid, 0, false, MUST_EXIST);		
		$context = context_module::instance($cm->id);
		require_capability('mod/skillsaudit:editownrating', $context);
				
		if($result = $DB->get_record('skillsauditrating', array('userid'=>$user->id, 'id'=>$ratingid, 'auditid'=>$cm->instance))) {
			$result->comment = "";
			$DB->update_record('skillsauditrating', $result);
		}
		
		return $ratingid;
		
	}
	
	/**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function delete_rating_parameters() {
        return new external_function_parameters(
            array(
				'cmid' => new external_value(PARAM_INT, 'course module id'),
				'ratingid' => new external_value(PARAM_INT, 'confidence rating id')
            )
        );
    }
	
	public static function delete_rating_returns() {
		return new external_single_structure(
			array(
				'summaryHtml' => new external_value(PARAM_RAW, 'Summary html'),
				'ratingID' => new external_value(PARAM_INT, 'rating id deleted')
			)
		);
    }
	
	public static function delete_rating($cmid, $ratingid) {
		// check we have access rights to change skills
		global $CFG, $USER, $DB;
		
		$params = self::validate_parameters(self::delete_rating_parameters(), array('cmid' => $cmid, 'ratingid' => $ratingid));
		
		$cm = get_coursemodule_from_id('skillsaudit', $cmid, 0, false, MUST_EXIST);		
		$context = context_module::instance($cm->id);
		require_capability('mod/skillsaudit:deleterating', $context);
				
		$DB->delete_records('skillsauditrating', array('id'=>$ratingid));
		return array(
			'ratingID' => $ratingid,
			'summaryHtml' => skillsaudit_get_summary_html($cm, $USER->id, false)
		);
		
	}
	
	/**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function save_confidence_parameters() {
        return new external_function_parameters(
            array(
				'courseid' => new external_value(PARAM_INT, 'id of course'),
                'skillid' => new external_value(PARAM_INT, 'id of skill'),
				'confidence' => new external_value(PARAM_INT, 'confidence (0-100)'),
				'comment' => new external_value(PARAM_RAW, 'comment'),
				'auditid' => new external_value(PARAM_INT, 'id of skillsaudit')
            )
        );
    }
	
	public static function save_confidence_returns() {
        return new external_single_structure(
			array(
				'summaryHtml' => new external_value(PARAM_RAW, 'Summary html'),
				'ratingHtml' => new external_value(PARAM_RAW, 'Rating html')
			)
		);
    }
	
	public static function save_confidence($courseid, $skillid, $confidence, $comment, $auditid) {
		// check we have access rights to change skills
		global $CFG, $USER, $DB;
		
		$params = self::validate_parameters(self::save_confidence_parameters(), array('courseid' => $courseid, 'skillid' => $skillid, 'confidence' => $confidence, 'comment' => $comment, 'auditid' => $auditid));
		
		$transaction = $DB->start_delegated_transaction();
		
		$context = context_course::instance($courseid);
        require_capability('mod/skillsaudit:submit', $context);
		
		$cm = get_coursemodule_from_instance('skillsaudit', $auditid, $courseid, false, MUST_EXIST);
		$context = context_module::instance($cm->id);
		$can_delete_rating = has_capability('mod/skillsaudit:deleterating', $context);
		$can_clear_rating = has_capability('mod/skillsaudit:editownrating', $context);
		
		$createnew = true;
		if($ratings = $DB->get_records('skillsauditrating', array('skillid'=>$skillid, 'auditid'=>$auditid, 'userid'=>$USER->id))) {
			foreach($ratings as $rating) {
				if($rating->timestamp > (time() - 600) && $rating->comment == $comment) {
					$rating->confidence = $confidence;
					$rating->comment = $comment;
					$rating->timestamp = time();
					$DB->update_record('skillsauditrating', $rating);
					$createnew = false;
					break;
				} 
			}
		} 
		if($createnew) {
			$rating = new stdClass();
			$rating->auditid = $auditid;
			$rating->skillid = $skillid;
			$rating->userid = $USER->id;
			$rating->confidence = $confidence;
			$rating->timestamp = time();
			$rating->comment = $comment;
			$rating->id = $DB->insert_record('skillsauditrating', $rating);
		}
		
		
		
		// search for an existing record
		
		
		
		
		
		$transaction->allow_commit();
		
		// update grades for other audits also using this skill
		if($result = $DB->get_records('skillsinaudit', array('skillid'=>$rating->skillid))) {
			foreach($result as $record) {
				if($record->auditid != $rating->auditid) {
					skillsaudit_get_rating_html($rating, $can_clear_rating, $can_delete_rating, $record->auditid);
				}
			}
		}
		
		
		$result = array(
			'ratingHtml' => skillsaudit_get_rating_html($rating, $can_clear_rating, $can_delete_rating, $cm->instance),
			'summaryHtml' => skillsaudit_get_summary_html($cm, $USER->id, false)
		);
		return $result;
	}
 
    /**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function add_skills_parameters() {
        return new external_function_parameters(
            array(
				'courseid' => new external_value(PARAM_INT, 'id of course'),
                'skills' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'number' => new external_value(PARAM_TEXT, 'skill spec number'),
                            'description' => new external_value(PARAM_RAW, 'description of the skill'),
							'link' => new external_value(PARAM_URL, 'help link for this skill')
                        )
                    )
                )
            )
        );
    }
	
	
	
	 public static function add_skills_returns() {
        return new external_multiple_structure(
            new external_single_structure(
				array(
					'id' => new external_value(PARAM_INT, 'id of the skill'),
					'number' => new external_value(PARAM_TEXT, 'PoS or Spec reference number (e.g. 1.2)'),
					'description' => new external_value(PARAM_RAW, 'description of the skill'),
					'link' => new external_value(PARAM_URL, 'help link for this skill'),
					'courseid' => new external_value(PARAM_INT, 'id of the course')
				)
			)
        );
    }
	
	public static function add_skills($courseid, $skills) {
		// check we have access rights to change skills
		global $CFG, $USER, $DB;
		
		$params = self::validate_parameters(self::add_skills_parameters(), array('courseid' => $courseid, 'skills' => $skills));
		
		$transaction = $DB->start_delegated_transaction();
		
		$context = context_course::instance($courseid);
		$updatedSkills = array();
		require_capability('mod/skillsaudit:editskills', $context);
		
		foreach($skills as $skill) {
			$skill['courseid'] = $courseid;
			$skill['id'] = $DB->insert_record('skills', $skill);
			$updatedSkills[] = $skill;
		}
		
		
		$transaction->allow_commit();
		
		return $updatedSkills;
	}
	
	/**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function delete_skill_parameters() {
        return new external_function_parameters(
            array(
				'courseid' => new external_value(PARAM_INT, 'id of course'),
                'skillid' =>  new external_value(PARAM_INT, 'id of skill')
            )
        );
    }
	
	 public static function delete_skill_returns() {
        return new external_value(PARAM_INT, '1 on success');
    }
	
	public static function delete_skill($courseid, $skillid) {
		// check we have access rights to change skills
		global $CFG, $USER, $DB;
		
		$params = self::validate_parameters(self::delete_skill_parameters(), array('courseid' => $courseid, 'skillid' => $skillid));
		
		$transaction = $DB->start_delegated_transaction();
		
		$context = context_course::instance($courseid);
		$updatedSkills = array();
		require_capability('mod/skillsaudit:editskills', $context);
		$DB->delete_records('skillsinaudit', array('skillid'=>$skillid));
		$DB->delete_records('skillsauditrating', array('skillid'=>$skillid));
		$DB->delete_records('skills', array('courseid'=>$courseid, 'id'=>$skillid));
		
		$transaction->allow_commit();
		
		return 1;
	}
	
	/**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function delete_unused_skills_parameters() {
        return new external_function_parameters(
            array(
				'courseid' => new external_value(PARAM_INT, 'id of course'),
                'skillids' =>  new external_value(PARAM_SEQUENCE, 'ids of skill (CSV)')
            )
        );
    }
	
	 public static function delete_unused_skills_returns() {
        return new external_value(PARAM_SEQUENCE, 'ids of skills deleted');
    }
	
	public static function delete_unused_skills($courseid, $skillids) {
		// check we have access rights to change skills
		global $CFG, $USER, $DB;
		
		$params = self::validate_parameters(self::delete_unused_skills_parameters(), array('courseid' => $courseid, 'skillids' => $skillids));
		
		$transaction = $DB->start_delegated_transaction();
		
		$context = context_course::instance($courseid);
		$updatedSkills = array();
		require_capability('mod/skillsaudit:editskills', $context);
		$deleted = array();
		$skillids = explode(',', $skillids);
		foreach($skillids as $skillid) {
			if($DB->record_exists('skillsinaudit', array('skillid'=>$skillid))) {
			} else {
				$deleted[] = $skillid;
				$DB->delete_records('skills', array('courseid'=>$courseid, 'id'=>$skillid));
			}
		}
		$transaction->allow_commit();
		
		return join(',', $deleted);
	}
	
	/**
     * Returns description of method parameters
     * @return external_function_parameters
     */
    public static function edit_skill_parameters() {
        return new external_function_parameters(
            array(
				'courseid' => new external_value(PARAM_INT, 'id of course'),
                'skillid' =>  new external_value(PARAM_INT, 'id of skill'),
				'number' => new external_value(PARAM_TEXT, 'Spec. number'),
				'description' => new external_value(PARAM_TEXT, 'Description of the skill'),
				'link' => new external_value(PARAM_URL, 'help link')
            )
        );
    }
	
	 public static function edit_skill_returns() {
        return new external_value(PARAM_INT, '1 on success');
    }
	
	public static function edit_skill($courseid, $skillid, $number, $description, $link) {
		// check we have access rights to change skills
		global $CFG, $USER, $DB;
		
		$params = self::validate_parameters(self::edit_skill_parameters(), array('courseid' => $courseid, 'skillid' => $skillid, 'number'=>$number, 'description'=>$description, 'link'=>$link));
		
		$transaction = $DB->start_delegated_transaction();
		
		$context = context_course::instance($courseid);
		$updatedSkills = array();
		require_capability('mod/skillsaudit:editskills', $context);
		
		$skill = $DB->get_record('skills', array('courseid'=>$courseid, 'id'=>$skillid));
		$skill->number = $number;
		$skill->description = $description;
		$skill->link = $link;
		$DB->update_record('skills', $skill);
		$transaction->allow_commit();
		
		return 1;
	}
}
?>