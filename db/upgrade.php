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
 * This file keeps track of upgrades to the skillsaudit module
 *
 * Sometimes, changes between versions involve alterations to database
 * structures and other major things that may break installations. The upgrade
 * function in this file will attempt to perform all the necessary actions to
 * upgrade your older installation to the current version. If there's something
 * it cannot do itself, it will tell you what you need to do.  The commands in
 * here will all be database-neutral, using the functions defined in DLL libraries.
 *
 * @package    mod_skillsaudit
 * @copyright  2017 Pete Dring <pddring@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Execute skillsaudit upgrade from the given old version
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_skillsaudit_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager(); // Loads ddl manager and xmldb classes.
	
	if ($oldversion < 2017061300) {

		// Define field options to be added to skillsaudit.
		$table = new xmldb_table('skillsaudit');
		$field = new xmldb_field('options', XMLDB_TYPE_TEXT, null, null, null, null, null, 'grade');

		// Conditionally launch add field options.
		if (!$dbman->field_exists($table, $field)) {
			$dbman->add_field($table, $field);
		}
		
		$field = new xmldb_field('question', XMLDB_TYPE_TEXT, null, null, null, null, null, 'options');

		// Conditionally launch add field question.
		if (!$dbman->field_exists($table, $field)) {
			$dbman->add_field($table, $field);
		}


		// Skillsaudit savepoint reached.
		upgrade_mod_savepoint(true, 2017061300, 'skillsaudit');
	}
	
	// add link for each skill 
	if ($oldversion < 2017120400) {

		// Define field options to be added to skillsaudit.
		$table = new xmldb_table('skills');
		$field = new xmldb_field('link', XMLDB_TYPE_TEXT, null, null, null, null, null, 'description');

		// Conditionally launch add field options.
		if (!$dbman->field_exists($table, $field)) {
			$dbman->add_field($table, $field);
		}
		
		// Skillsaudit savepoint reached.
		upgrade_mod_savepoint(true, 2017120400, 'skillsaudit');
	}
	
	if ($oldversion < 2018030500) {

        // Define table skillsauditfeedback to be created.
        $table = new xmldb_table('skillsauditfeedback');

        // Adding fields to table skillsauditfeedback.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('fromid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('auditid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('skillid', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('timestamp', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('message', XMLDB_TYPE_TEXT, null, null, null, null, null);

        // Adding keys to table skillsauditfeedback.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for skillsauditfeedback.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Skillsaudit savepoint reached.
        upgrade_mod_savepoint(true, 2018030500, 'skillsaudit');
    }


	if ($oldversion < 2021061600) {

		// Define key course (foreign) to be added to skills.
		$table = new xmldb_table('skills');
		$key = new xmldb_key('course', XMLDB_KEY_FOREIGN, ['courseid'], 'course', ['id']);

		// Launch add key course.
		$dbman->add_key($table, $key);

		// Define key skill (foreign) to be added to skillsinaudit.
		$table = new xmldb_table('skillsinaudit');
		$key = new xmldb_key('skill', XMLDB_KEY_FOREIGN, ['skillid'], 'skills', ['id']);

		// Launch add key skill.
		$dbman->add_key($table, $key);
		$key = new xmldb_key('audit', XMLDB_KEY_FOREIGN, ['auditid'], 'skillsaudit', ['id']);

        // Launch add key audit.
        $dbman->add_key($table, $key);

		// Define key user (foreign) to be added to skillsauditrating.
		$table = new xmldb_table('skillsauditrating');
		$key = new xmldb_key('user', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

		// Launch add key user.
		$dbman->add_key($table, $key);

		$key = new xmldb_key('skill', XMLDB_KEY_FOREIGN, ['skillid'], 'skills', ['id']);

        // Launch add key skill.
        $dbman->add_key($table, $key);

		$key = new xmldb_key('audit', XMLDB_KEY_FOREIGN, ['auditid'], 'skillsaudit', ['id']);

        // Launch add key audit.
        $dbman->add_key($table, $key);

		
		/// TABLE: skillsauditfeedback
		$table = new xmldb_table('skillsauditfeedback');
		$key = new xmldb_key('from', XMLDB_KEY_FOREIGN, ['fromid'], 'user', ['id']);

        // Launch add key from.
        $dbman->add_key($table, $key);
		$key = new xmldb_key('audit', XMLDB_KEY_FOREIGN, ['auditid'], 'skillsaudit', ['id']);

        // Launch add key audit.
        $dbman->add_key($table, $key);
		$key = new xmldb_key('skill', XMLDB_KEY_FOREIGN, ['skillid'], 'skills', ['id']);

        // Launch add key skill.
        $dbman->add_key($table, $key);
 

        // Skillsaudit savepoint reached.
        upgrade_mod_savepoint(true, 2021061600, 'skillsaudit');
    }




    
    return true;
}
