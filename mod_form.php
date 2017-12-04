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
 * The main skillsaudit configuration form
 *
 * It uses the standard core Moodle formslib. For more info about them, please
 * visit: http://docs.moodle.org/en/Development:lib/formslib.php
 *
 * @package    mod_skillsaudit
 * @copyright  2017 Pete Dring <pddring@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/course/moodleform_mod.php');

/**
 * Module instance settings form
 *
 * @package    mod_skillsaudit
 * @copyright  2017 Pete Dring <pddring@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_skillsaudit_mod_form extends moodleform_mod {

    /**
     * Defines forms elements
     */
    public function definition() {
        global $CFG, $PAGE, $COURSE, $DB;

        $mform = $this->_form;

        // Adding the "general" fieldset, where all the common settings are showed.
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Adding the standard "name" field.
        $mform->addElement('text', 'name', get_string('skillsauditname', 'skillsaudit'), array('size' => '64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('name', 'skillsauditname', 'skillsaudit');

        // Adding the standard "intro" and "introformat" fields.
        if ($CFG->branch >= 29) {
            $this->standard_intro_elements();
        } else {
            $this->add_intro_editor();
        }
		
		$mform->addElement('header', 'h_confidence', get_string('confidence', 'skillsaudit'));
		$mform->addElement('text', 'confidence_question', get_string('confidencequestion', 'skillsaudit'));
		$mform->setDefault('confidence_question', get_string('defconfidencequestion', 'skillsaudit'));
		
		$mform->addElement('text', 'confidence_options', get_string('confidenceoptions', 'skillsaudit'));
		$mform->setDefault('confidence_options', get_string('defconfidenceoptions', 'skillsaudit'));
        
        $mform->addElement('header', 'h_skills', get_string('skills', 'skillsaudit'));
		$skills = $DB->get_records('skills', array('courseid'=>$COURSE->id), 'number ASC');
		
		$html = '<h2>' . get_string('skillsinthiscourse', 'skillsaudit') . '</h2>';
		$html .= '<table class="generaltable" id="tbl_skills"><tr><th>' . get_string('number', 'skillsaudit') . '</th>';
		$html .= '<th>' . get_string('description', 'skillsaudit') . '</th>';
		$html .= '<th>' . get_string('included', 'skillsaudit') . '</th></tr>';
		$src = $CFG->wwwroot . '/mod/skillsaudit/pix/';
		
		// get all skills in this audit
		$skills_included = array();
		$ids = array();
		$instance = $this->get_instance();
		if($instance > 0) {
			$skillsinaudit = $DB->get_records('skillsinaudit', array('auditid'=>$instance));
			foreach($skillsinaudit as $skillinaudit) {
				$skills_included[$skillinaudit->skillid] = $skillinaudit;
			}
		}
		
		foreach($skills as $skill) {
			$html .= '<tr class="skill_row" id="skill_row_' . $skill->id . '"><td class="skill_number"><a class="skill_help_link" href="'  . $skill->link . '" target="_blank">' . $skill->number . '</a></td><td class="skill_description">' . $skill->description . '</td><td>';
			if(array_key_exists($skill->id, $skills_included)) {
				$html .= '<span id="skill_included_' . $skill->id . '" class="skill_included skill_included_yes"></span></td></tr>';
				$ids[] = $skill->id;
			} else {
				$html .= '<span id="skill_included_' . $skill->id . '" class="skill_included skill_included_no"></span></td></tr>';
			}
		}
		$html .= '</table>';
		$html .= '<!-- Modal -->
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
  ';
		$mform->addElement('html', $html);
		
		$mform->addElement('hidden', 'skills', implode(',', $ids));
		
		
		// show edit skills section
		$context = context_course::instance($COURSE->id);
		if(has_capability('mod/skillsaudit:editskills', $context)) {
			$mform->addElement('button', 'selectall', get_string("selectall", "skillsaudit"));
			$mform->addElement('button', 'selectnone', get_string("selectnone", "skillsaudit"));
			$mform->addElement('button', 'deleteunused', get_string("deleteunused", "skillsaudit"));
			$mform->addElement('textarea', 'newskills', get_string("newskills", "skillsaudit"), 'wrap="virtual" rows="5" cols="50"');
			$mform->addElement('button', 'addnew', get_string("newskills", "skillsaudit"));

			$mform->addHelpButton('newskills', 'newskills', 'skillsaudit');
		}

		
		$PAGE->requires->js_call_amd('mod_skillsaudit/skillsaudit', 'forminit', array('course'=>$COURSE->id, 'modpath'=>$CFG->wwwroot . '/mod/skillsaudit'));


        // Add standard grading elements.
        $this->standard_grading_coursemodule_elements();

        // Add standard elements, common to all modules.
        $this->standard_coursemodule_elements();

        // Add standard buttons, common to all modules.
        $this->add_action_buttons();
    }
}
