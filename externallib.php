<?php
require_once("$CFG->libdir/externallib.php");
require_once(dirname(__FILE__).'/locallib.php');
class mod_skillsaudit_external extends external_api {
 
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
        return new external_value(PARAM_INT, 'rating id deleted');
    }
	
	public static function delete_rating($cmid, $ratingid) {
		// check we have access rights to change skills
		global $CFG, $USER, $DB;
		
		$params = self::validate_parameters(self::delete_rating_parameters(), array('cmid' => $cmid, 'ratingid' => $ratingid));
		
		$cm = get_coursemodule_from_id('skillsaudit', $cmid, 0, false, MUST_EXIST);		
		$context = context_module::instance($cm->id);
		require_capability('mod/skillsaudit:submit', $context);
				
		if($result = $DB->get_record('skillsauditrating', array('id'=>$ratingid, 'auditid'=>$cm->instance))) {
			$result->comment = "";
			$DB->update_record('skillsauditrating', $result);
		}
		
		
		return json_encode($ratingid);
		
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
        return new external_value(PARAM_RAW, 'json test value');
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
		$can_delete_rating = has_capability('mod/skillsaudit:deleteownrating', $context);
		
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

		return skillsaudit_get_rating_html($rating, $can_delete_rating);
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
                            'description' => new external_value(PARAM_RAW, 'description of the skill')
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
				'description' => new external_value(PARAM_TEXT, 'Description of the skill')
            )
        );
    }
	
	 public static function edit_skill_returns() {
        return new external_value(PARAM_INT, '1 on success');
    }
	
	public static function edit_skill($courseid, $skillid, $number, $description) {
		// check we have access rights to change skills
		global $CFG, $USER, $DB;
		
		$params = self::validate_parameters(self::edit_skill_parameters(), array('courseid' => $courseid, 'skillid' => $skillid, 'number'=>$number, 'description'=>$description));
		
		$transaction = $DB->start_delegated_transaction();
		
		$context = context_course::instance($courseid);
		$updatedSkills = array();
		require_capability('mod/skillsaudit:editskills', $context);
		
		$skill = $DB->get_record('skills', array('courseid'=>$courseid, 'id'=>$skillid));
		$skill->number = $number;
		$skill->description = $description;
		$DB->update_record('skills', $skill);
		$transaction->allow_commit();
		
		return 1;
	}
}
?>