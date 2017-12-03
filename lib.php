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
 * Library of interface functions and constants for module skillsaudit
 *
 * All the core Moodle functions, neeeded to allow the module to work
 * integrated in Moodle should be placed here.
 *
 * All the skillsaudit specific functions, needed to implement all the module
 * logic, should go to locallib.php. This will help to save some memory when
 * Moodle is performing actions across all modules.
 *
 * @package    mod_skillsaudit
 * @copyright  2017 Pete Dring <pddring@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/* Moodle core API */

/**
 * Returns the information on whether the module supports a feature
 *
 * See {@link plugin_supports()} for more info.
 *
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed true if the feature is supported, null if unknown
 */
function skillsaudit_supports($feature) {

    switch($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        default:
            return null;
    }
}

/**
 * Saves a new instance of the skillsaudit into the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will create a new instance and return the id number
 * of the new instance.
 *
 * @param stdClass $skillsaudit Submitted data from the form in mod_form.php
 * @param mod_skillsaudit_mod_form $mform The form instance itself (if needed)
 * @return int The id of the newly inserted skillsaudit record
 */
function skillsaudit_add_instance(stdClass $skillsaudit, mod_skillsaudit_mod_form $mform = null) {
    global $DB;

    $skillsaudit->timecreated = time();

    $data = $mform->get_data();
	$skillsaudit->question = $data->confidence_question;
	$skillsaudit->options = $data->confidence_options;

    $skillsaudit->id = $DB->insert_record('skillsaudit', $skillsaudit);
	
	$skillids = preg_split("/[\s,]+/", $data->skills);
	
	foreach($skillids as $skillid) {
		if(!preg_match('/\d+/', $skillid)) {
			continue;
		}
		
		$skillinaudit = new stdClass();
		$skillinaudit->skillid = $skillid;
		$skillinaudit->auditid = $skillsaudit->id;
		$skillinaudit->id = $DB->insert_record('skillsinaudit', $skillinaudit);
	}

    skillsaudit_grade_item_update($skillsaudit);

    return $skillsaudit->id;
}

/**
 * Updates an instance of the skillsaudit in the database
 *
 * Given an object containing all the necessary data,
 * (defined by the form in mod_form.php) this function
 * will update an existing instance with new data.
 *
 * @param stdClass $skillsaudit An object from the form in mod_form.php
 * @param mod_skillsaudit_mod_form $mform The form instance itself (if needed)
 * @return boolean Success/Fail
 */
function skillsaudit_update_instance(stdClass $skillsaudit, mod_skillsaudit_mod_form $mform = null) {
    global $DB, $COURSE;

    $skillsaudit->timemodified = time();
    $skillsaudit->id = $skillsaudit->instance;

    $data = $mform->get_data();
	$skillsaudit->question = $data->confidence_question;
	$skillsaudit->options = $data->confidence_options;

    $result = $DB->update_record('skillsaudit', $skillsaudit);
	
	// get skills currently added
	$current_skills = $DB->get_records('skillsinaudit', array('auditid'=>$skillsaudit->id));
	$already_added = array();
	foreach($current_skills as $current_skill) {
		$already_added[$current_skill->skillid] = $current_skill;
	}
	$to_remove = $already_added;
	
	// ignore skills already added
	$skillids = preg_split("/[\s,]+/", $data->skills);

	foreach($skillids as $skillid) {
		if(!preg_match('/\d+/', $skillid)) {
			continue;
		}
		if(array_key_exists($skillid, $already_added)) {
			// already added - remove from deletion list
			unset($to_remove[$skillid]);
		} else {
			$skillinaudit = new stdClass();
			$skillinaudit->skillid = $skillid;
			$skillinaudit->auditid = $skillsaudit->id;
			$skillinaudit->id = $DB->insert_record('skillsinaudit', $skillinaudit);
		}
	}
	

	// remove all unwanted skills
	foreach($to_remove as $skillinaudit) {
		$DB->delete_records('skillsinaudit', array('id'=>$skillinaudit->id));
	}
	
    skillsaudit_grade_item_update($skillsaudit);

    return $result;
}

/**
 * This standard function will check all instances of this module
 * and make sure there are up-to-date events created for each of them.
 * If courseid = 0, then every skillsaudit event in the site is checked, else
 * only skillsaudit events belonging to the course specified are checked.
 * This is only required if the module is generating calendar events.
 *
 * @param int $courseid Course ID
 * @return bool
 */
function skillsaudit_refresh_events($courseid = 0) {
    global $DB;

    if ($courseid == 0) {
        if (!$skillsaudits = $DB->get_records('skillsaudit')) {
            return true;
        }
    } else {
        if (!$skillsaudits = $DB->get_records('skillsaudit', array('course' => $courseid))) {
            return true;
        }
    }

    foreach ($skillsaudits as $skillsaudit) {
        // Create a function such as the one below to deal with updating calendar events.
        // skillsaudit_update_events($skillsaudit);
    }

    return true;
}

/**
 * Removes an instance of the skillsaudit from the database
 *
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 */
function skillsaudit_delete_instance($id) {
    global $DB;
    if (! $skillsaudit = $DB->get_record('skillsaudit', array('id' => $id))) {
        return false;
    }

    // Delete any dependent records here.
	$DB->delete_records('skillsauditrating', array('auditid' => $skillsaudit->id));
	$DB->delete_records('skillsinaudit', array('auditid' => $skillsaudit->id));
    $DB->delete_records('skillsaudit', array('id' => $skillsaudit->id));

    skillsaudit_grade_item_delete($skillsaudit);
    return true;
}

/**
 * Returns a small object with summary information about what a
 * user has done with a given particular instance of this module
 * Used for user activity reports.
 *
 * $return->time = the time they did it
 * $return->info = a short text description
 *
 * @param stdClass $course The course record
 * @param stdClass $user The user record
 * @param cm_info|stdClass $mod The course module info object or record
 * @param stdClass $skillsaudit The skillsaudit instance record
 * @return stdClass|null
 */
function skillsaudit_user_outline($course, $user, $mod, $skillsaudit) {

    $return = new stdClass();
    $return->time = 0;
    $return->info = '';
    return $return;
}

/**
 * Prints a detailed representation of what a user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * It is supposed to echo directly without returning a value.
 *
 * @param stdClass $course the current course record
 * @param stdClass $user the record of the user we are generating report for
 * @param cm_info $mod course module info
 * @param stdClass $skillsaudit the module instance record
 */
function skillsaudit_user_complete($course, $user, $mod, $skillsaudit) {
}

/**
 * Given a course and a time, this module should find recent activity
 * that has occurred in skillsaudit activities and print it out.
 *
 * @param stdClass $course The course record
 * @param bool $viewfullnames Should we display full names
 * @param int $timestart Print activity since this timestamp
 * @return boolean True if anything was printed, otherwise false
 */
function skillsaudit_print_recent_activity($course, $viewfullnames, $timestart) {
    return false;
}

/**
 * Prepares the recent activity data
 *
 * This callback function is supposed to populate the passed array with
 * custom activity records. These records are then rendered into HTML via
 * {@link skillsaudit_print_recent_mod_activity()}.
 *
 * Returns void, it adds items into $activities and increases $index.
 *
 * @param array $activities sequentially indexed array of objects with added 'cmid' property
 * @param int $index the index in the $activities to use for the next record
 * @param int $timestart append activity since this time
 * @param int $courseid the id of the course we produce the report for
 * @param int $cmid course module id
 * @param int $userid check for a particular user's activity only, defaults to 0 (all users)
 * @param int $groupid check for a particular group's activity only, defaults to 0 (all groups)
 */
function skillsaudit_get_recent_mod_activity(&$activities, &$index, $timestart, $courseid, $cmid, $userid=0, $groupid=0) {
}

/**
 * Prints single activity item prepared by {@link skillsaudit_get_recent_mod_activity()}
 *
 * @param stdClass $activity activity record with added 'cmid' property
 * @param int $courseid the id of the course we produce the report for
 * @param bool $detail print detailed report
 * @param array $modnames as returned by {@link get_module_types_names()}
 * @param bool $viewfullnames display users' full names
 */
function skillsaudit_print_recent_mod_activity($activity, $courseid, $detail, $modnames, $viewfullnames) {
}

/**
 * Function to be run periodically according to the moodle cron
 *
 * This function searches for things that need to be done, such
 * as sending out mail, toggling flags etc ...
 *
 * Note that this has been deprecated in favour of scheduled task API.
 *
 * @return boolean
 */
function skillsaudit_cron () {
    return true;
}

/**
 * Returns all other caps used in the module
 *
 * For example, this could be array('moodle/site:accessallgroups') if the
 * module uses that capability.
 *
 * @return array
 */
function skillsaudit_get_extra_capabilities() {
    return array();
}

/* Gradebook API */

/**
 * Is a given scale used by the instance of skillsaudit?
 *
 * This function returns if a scale is being used by one skillsaudit
 * if it has support for grading and scales.
 *
 * @param int $skillsauditid ID of an instance of this module
 * @param int $scaleid ID of the scale
 * @return bool true if the scale is used by the given skillsaudit instance
 */
function skillsaudit_scale_used($skillsauditid, $scaleid) {
    global $DB;

    if ($scaleid and $DB->record_exists('skillsaudit', array('id' => $skillsauditid, 'grade' => -$scaleid))) {
        return true;
    } else {
        return false;
    }
}

/**
 * Checks if scale is being used by any instance of skillsaudit.
 *
 * This is used to find out if scale used anywhere.
 *
 * @param int $scaleid ID of the scale
 * @return boolean true if the scale is used by any skillsaudit instance
 */
function skillsaudit_scale_used_anywhere($scaleid) {
    global $DB;

    if ($scaleid and $DB->record_exists('skillsaudit', array('grade' => -$scaleid))) {
        return true;
    } else {
        return false;
    }
}

/**
 * Creates or updates grade item for the given skillsaudit instance
 *
 * Needed by {@link grade_update_mod_grades()}.
 *
 * @param stdClass $skillsaudit instance object with extra cmidnumber and modname property
 * @param bool $reset reset grades in the gradebook
 * @return void
 */
function skillsaudit_grade_item_update(stdClass $skillsaudit, $grades=false) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    $item = array();
    $item['itemname'] = clean_param($skillsaudit->name, PARAM_NOTAGS) . ' (Confidence)';
	
    $item['gradetype'] = GRADE_TYPE_VALUE;
	$item['grademin'] = 0;
	$item['grademax'] = 100;
	if ($grades === false) {
        $item['reset'] = true;
		grade_update('mod/skillsaudit', $skillsaudit->course, 'mod', 'skillsaudit',
            $skillsaudit->id, 0, $grades, $item);	
		
		$item['itemname'] = clean_param($skillsaudit->name, PARAM_NOTAGS) . ' (Progress)';
		grade_update('mod/skillsaudit', $skillsaudit->course, 'mod', 'skillsaudit',
            $skillsaudit->id, 1, $grades, $item);
		
		$item['itemname'] = clean_param($skillsaudit->name, PARAM_NOTAGS) . ' (Completed)';	
		grade_update('mod/skillsaudit', $skillsaudit->course, 'mod', 'skillsaudit',
            $skillsaudit->id, 2, $grades, $item);		
		
    }

    if ($grades === false) {
        $item['reset'] = true;
    }


}

/**
 * Delete grade item for given skillsaudit instance
 *
 * @param stdClass $skillsaudit instance object
 * @return grade_item
 */
function skillsaudit_grade_item_delete($skillsaudit) {
    global $CFG;
    require_once($CFG->libdir.'/gradelib.php');

    return grade_update('mod/skillsaudit', $skillsaudit->course, 'mod', 'skillsaudit',
            $skillsaudit->id, 0, null, array('deleted' => 1));
}

/**
 * Update skillsaudit grades in the gradebook
 *
 * Needed by {@link grade_update_mod_grades()}.
 *
 * @param stdClass $skillsaudit instance object with extra cmidnumber and modname property
 * @param int $userid update grade of specific user only, 0 means all participants
 */
function skillsaudit_update_grades(stdClass $skillsaudit, $userid = 0) {
    global $CFG, $DB;
    require_once($CFG->libdir.'/gradelib.php');

    // Populate array of grade objects indexed by userid.
    $grades = array();

    grade_update('mod/skillsaudit', $skillsaudit->course, 'mod', 'skillsaudit', $skillsaudit->id, 0, $grades);
}

/* File API */

/**
 * Returns the lists of all browsable file areas within the given module context
 *
 * The file area 'intro' for the activity introduction field is added automatically
 * by {@link file_browser::get_file_info_context_module()}
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @return array of [(string)filearea] => (string)description
 */
function skillsaudit_get_file_areas($course, $cm, $context) {
    return array();
}

/**
 * File browsing support for skillsaudit file areas
 *
 * @package mod_skillsaudit
 * @category files
 *
 * @param file_browser $browser
 * @param array $areas
 * @param stdClass $course
 * @param stdClass $cm
 * @param stdClass $context
 * @param string $filearea
 * @param int $itemid
 * @param string $filepath
 * @param string $filename
 * @return file_info instance or null if not found
 */
function skillsaudit_get_file_info($browser, $areas, $course, $cm, $context, $filearea, $itemid, $filepath, $filename) {
    return null;
}

/**
 * Serves the files from the skillsaudit file areas
 *
 * @package mod_skillsaudit
 * @category files
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param stdClass $context the skillsaudit's context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 */
function skillsaudit_pluginfile($course, $cm, $context, $filearea, array $args, $forcedownload, array $options=array()) {
    global $DB, $CFG;

    if ($context->contextlevel != CONTEXT_MODULE) {
        send_file_not_found();
    }

    require_login($course, true, $cm);

    send_file_not_found();
}

/* Navigation API */

/**
 * Extends the global navigation tree by adding skillsaudit nodes if there is a relevant content
 *
 * This can be called by an AJAX request so do not rely on $PAGE as it might not be set up properly.
 *
 * @param navigation_node $navref An object representing the navigation tree node of the skillsaudit module instance
 * @param stdClass $course current course record
 * @param stdClass $module current skillsaudit instance record
 * @param cm_info $cm course module information
 */
function skillsaudit_extend_navigation(navigation_node $navref, stdClass $course, stdClass $module, cm_info $cm) {
    // TODO Delete this function and its docblock, or implement it.
}

/**
 * Extends the settings navigation with the skillsaudit settings
 *
 * This function is called when the context for the page is a skillsaudit module. This is not called by AJAX
 * so it is safe to rely on the $PAGE.
 *
 * @param settings_navigation $settingsnav complete settings navigation tree
 * @param navigation_node $skillsauditnode skillsaudit administration node
 */
function skillsaudit_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $skillsauditnode=null) {
    // TODO Delete this function and its docblock, or implement it.
}
