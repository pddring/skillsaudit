<?php
$services = array(
      'skillsaudit' => array(                                                //the name of the web service
          'functions' => array ('mod_skillsaudit_add_skills', 'mod_skillsaudit_delete_skill', 'mod_skillsaudit_edit_skill'), //web service functions of this service
          'requiredcapability' => 'mod/skillsaudit:editskills',                //if set, the web service user need this capability to access 
                                                                              //any function of this service. For example: 'some/capability:specified'                 
          'restrictedusers' =>0,                                             //if enabled, the Moodle administrator must link some user to this service
                                                                              //into the administration
          'enabled'=>1,                                                       //if enabled, the service can be reachable on a default installation
       )
  );
  
$functions = array(
    'mod_skillsaudit_add_skills' => array(         //web service function name
        'classname'   => 'mod_skillsaudit_external',  //class containing the external function
        'methodname'  => 'add_skills',          //external function name
        'classpath'   => 'mod/skillsaudit/externallib.php',  //file containing the class/external function
        'description' => 'Creates new skills.',    //human readable description of the web service function
        'type'        => 'write',                  //database rights of the web service function (read, write)
		'capabilities'=> 'mod/skillsaudit:editskills',
		'ajax'=>true
    ),
	
	'mod_skillsaudit_delete_skill' => array(         //web service function name
        'classname'   => 'mod_skillsaudit_external',  //class containing the external function
        'methodname'  => 'delete_skill',          //external function name
        'classpath'   => 'mod/skillsaudit/externallib.php',  //file containing the class/external function
        'description' => 'Delete skill',    //human readable description of the web service function
        'type'        => 'write',                  //database rights of the web service function (read, write)
		'capabilities'=> 'mod/skillsaudit:editskills',
		'ajax'=>true
    ),
	
	'mod_skillsaudit_delete_unused_skills' => array(         //web service function name
        'classname'   => 'mod_skillsaudit_external',  //class containing the external function
        'methodname'  => 'delete_unused_skills',          //external function name
        'classpath'   => 'mod/skillsaudit/externallib.php',  //file containing the class/external function
        'description' => 'Delete unused skills',    //human readable description of the web service function
        'type'        => 'write',                  //database rights of the web service function (read, write)
		'capabilities'=> 'mod/skillsaudit:editskills',
		'ajax'=>true
    ),
	
	'mod_skillsaudit_edit_skill' => array(         //web service function name
        'classname'   => 'mod_skillsaudit_external',  //class containing the external function
        'methodname'  => 'edit_skill',          //external function name
        'classpath'   => 'mod/skillsaudit/externallib.php',  //file containing the class/external function
        'description' => 'Edit skill',    //human readable description of the web service function
        'type'        => 'write',                  //database rights of the web service function (read, write)
		'capabilities'=> 'mod/skillsaudit:editskills',
		'ajax'=>true
    ),
	
	'mod_skillsaudit_save_confidence' => array(         //web service function name
        'classname'   => 'mod_skillsaudit_external',  //class containing the external function
        'methodname'  => 'save_confidence',          //external function name
        'classpath'   => 'mod/skillsaudit/externallib.php',  //file containing the class/external function
        'description' => 'Save confidence level',    //human readable description of the web service function
        'type'        => 'write',                  //database rights of the web service function (read, write)
		'ajax'=>true
    ),
	
	'mod_skillsaudit_delete_rating' => array(         //web service function name
        'classname'   => 'mod_skillsaudit_external',  //class containing the external function
        'methodname'  => 'delete_rating',          //external function name
        'classpath'   => 'mod/skillsaudit/externallib.php',  //file containing the class/external function
        'description' => 'Delete confidence rating',    //human readable description of the web service function
        'type'        => 'write',                  //database rights of the web service function (read, write)
		'capabilities'=> 'mod/skillsaudit:deleterating',
		'ajax'=>true
    ),
	
		'mod_skillsaudit_clear_rating' => array(         //web service function name
        'classname'   => 'mod_skillsaudit_external',  //class containing the external function
        'methodname'  => 'clear_rating',          //external function name
        'classpath'   => 'mod/skillsaudit/externallib.php',  //file containing the class/external function
        'description' => 'Clear rating comment',    //human readable description of the web service function
        'type'        => 'write',                  //database rights of the web service function (read, write)
		'capabilities'=> 'mod/skillsaudit:editownrating',
		'ajax'=>true
    ),
);
?>