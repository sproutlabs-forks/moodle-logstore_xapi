<?php
define('CLI_SCRIPT', true);
require(__DIR__.'/../../../../../../config.php');
require_once($CFG->libdir.'/clilib.php');      // cli only functions




echo "***Starting ***" . \PHP_EOL;




$config = get_config('logstore_xapi','reprocessusers');
$affectedusers = json_decode(base64_decode($config));

if(empty($config) || !$config) {
    exit(0);
}

global $DB;
$string = '';
foreach ($affectedusers as $affecteduser){
    $email = $affecteduser->Email;
    $courseidnumber = $affecteduser->Code;


    $user = $DB->get_record('user',['email'=>$email]);


    if($user && isset($user->id)){
        print_r($user->id."    ");
    }
    $course = $DB->get_record('course',['idnumber'=>$courseidnumber]);
    if($course && isset($course->id)){
        print_r($course->id."    ");
    }
    $logs = '';
    if($user && $course){
        if(strtolower($affecteduser->CourseCompletionOnly)==true){
            $logs = $DB->get_records_sql("SELECT * from {logstore_standard_log} WHERE relateduserid={$user->id} AND courseid = {$course->id} AND(objecttable = 'course_completions' )");

        }else{
            $logs = $DB->get_records_sql("SELECT * from {logstore_standard_log} WHERE relateduserid={$user->id} AND courseid = {$course->id} AND( objecttable = 'course_modules_completion' OR objecttable = 'course_completions' )");

        }
        if($logs){
            $DB->insert_records('logstore_xapi_log',$logs);
        }
        print_r("Count  ".count($logs)."    ");
        print_r($email.PHP_EOL);
    }


}
$new_process_value = ''; // Replace 'new_value_here' with the new value you want to set
$config->reprocessusers = $new_process_value;

// Save the updated configuration settings
set_config('reprocessusers', $new_process_value, 'logstore_xapi');


echo \PHP_EOL . "***End ***";



exit(0);
