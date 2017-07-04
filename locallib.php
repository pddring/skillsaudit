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
defined('MOODLE_INTERNAL') || die();

function skillsaudit_get_rating_html($rating, $can_delete_rating) {
	$html = '';
	$r = 180 - ($rating->confidence * 180.0 / 100.0);
	$h = round($rating->confidence * 120.0 / 100.0);
	$html .= '<div class="rating" id="rating_' . $rating->id .'"><span class="wrist"><span class="minithumb" style="transform: rotate(' . $r . 'deg); background-color: hsl(' . $h . ',100%,50%)"></span></span><span class="rating_date">' . date("D jS M g:ia", $rating->timestamp) . '</span>';
	if(strlen($rating->comment) > 0) {
		$html .= '<div class="rating_comment">' . format_text($rating->comment, FORMAT_MOODLE, NULL, true);
		if($can_delete_rating) {
			$html .= '<button class="btn_delete" id="btn_delete_' . $rating->id . '">' . get_string('delete') . '</button>';
		}
		$html .='</div>';
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
