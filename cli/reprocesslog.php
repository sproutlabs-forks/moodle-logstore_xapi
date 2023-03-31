<?php
define('CLI_SCRIPT', true);
require(__DIR__.'/../../../../../../config.php');
require_once($CFG->libdir.'/clilib.php');      // cli only functions




echo "***Starting ***" . \PHP_EOL;




$config = get_config('logstore_xapi','reprocessusers');
$affectedusers = json_decode(base64_decode($config));

global $DB;
foreach ($affectedusers as $affecteduser){
    $email = $affecteduser->Email;
    $courseidnumber = $affecteduser->Code;

    $user = $DB->get_record('user',['email'=>$email]);
  
   if($user && isset($user->id)){
       print_r($user->id);   
   }
    $course = $DB->get_record('course',['idnumber'=>$courseidnumber]);
    if($course && isset($course->id)){
        print_r($course->id);
    }
    if($user && $course){
        $logs = $DB->get_records_sql("SELECT * from {logstore_standard_log} WHERE relateduserid={$user->id} AND courseid = {$course->id} AND( target = 'course_module_completion' OR target = 'course_completion' )");

        if($logs){
            $DB->insert_records('logstore_xapi_log',$logs);
        }
        print_r($email.PHP_EOL);
    }
   
    
}


echo \PHP_EOL . "***End ***";



exit(0);