<?php
require_once('../../config.php');
$id = required_param('id', PARAM_INT);          // Course module ID
$itemnumber = optional_param('itemnumber', 0, PARAM_INT); // Item number, may be != 0 for activities that allow more than one grade per user
$userid = optional_param('userid', 0, PARAM_INT); // Graded user ID (optional)
redirect($CFG->wwwroot . '/mod/skillsaudit/view.php?id=' . $id);
?>
