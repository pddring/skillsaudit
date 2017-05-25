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
	
	if ($oldversion < 2017052300) {

        // Define table skills to be created.
        $table = new xmldb_table('skills');

        // Adding fields to table skills.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('number', XMLDB_TYPE_CHAR, '64', null, null, null, null);
        $table->add_field('description', XMLDB_TYPE_TEXT, null, null, null, null, null);

        // Adding keys to table skills.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));

        // Conditionally launch create table for skills.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Skillsaudit savepoint reached.
        upgrade_mod_savepoint(true, XXXXXXXXXX, 'skillsaudit');
    }


    /*
     * And that's all. Please, examine and understand the 3 example blocks above. Also
     * it's interesting to look how other modules are using this script. Remember that
     * the basic idea is to have "blocks" of code (each one being executed only once,
     * when the module version (version.php) is updated.
     *
     * Lines above (this included) MUST BE DELETED once you get the first version of
     * yout module working. Each time you need to modify something in the module (DB
     * related, you'll raise the version and add one upgrade block here.
     *
     * Finally, return of upgrade result (true, all went good) to Moodle.
     */
    return true;
}
