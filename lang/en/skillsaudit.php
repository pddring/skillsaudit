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
 * English strings for skillsaudit
 *
 * You can have a rather longer description of the file as well,
 * if you like, and it can span multiple lines.
 *
 * @package    mod_skillsaudit
 * @copyright  2017 Pete Dring <pddring@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['modulename'] = 'Skills audit';
$string['modulenameplural'] = 'Skills audits';
$string['modulename_help'] = '<p>A Skills audit allows students to rate their confidence in a list of skills.</p><p>Teachers can see how the confidence rating of each student changes over time.</p>';
$string['skillsaudit:addinstance'] = 'Add a new skillsaudit';
$string['skillsaudit:submit'] = 'Submit skills audit';
$string['skillsaudit:view'] = 'View skills audit';
$string['skillsaudit:editownrating'] = 'Clear the comment from their own skills rating';
$string['skillsaudit:deleterating'] = 'Delete anyone\'s skills rating';

$string['skills'] = 'Skills';
$string['skillsauditname'] = 'Skills audit name';
$string['skillsauditname_help'] = 'This is the content of the help tooltip associated with the skillsauditname field. Markdown syntax is supported.';
$string['skillsaudit'] = 'Skills audit';
$string['pluginadministration'] = 'skills audit administration';
$string['pluginname'] = 'skillsaudit';
$string['newskills'] = 'Add new skills';
$string['newskills_help'] = 'Add new skills to this course. You can add multiple skills at the same time - put each one on a new line';
$string['editskills'] = "Edit skills list";
$string['number'] = "Spec. number";
$string['description'] = "Description";
$string['skillsinthiscourse'] = 'Skills in this course';
$string['included'] = "Included in this audit";
$string['loadling'] = 'Loading... please wait';
$string['selectall'] = 'Select all skills';
$string['selectnone'] = 'Deselect all skills';
$string['deleteunused'] = 'Delete unused skills';
$string['confidence'] = 'Confidence';
$string['confidencequestion'] = 'Question to ask when rating confidence';
$string['defconfidencequestion'] = 'Confidence level';
$string['confidenceoptions'] = 'Skill rating confidence options';
$string['defconfidenceoptions'] = 'Very low, Low, Medium, High, Very high';
$string['comment'] = 'Comment: State your target and describe what you\'ve done to meet it.';
$string['error_missingid'] = 'Missing course module id or instance id';