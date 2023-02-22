<?php
define('CLI_SCRIPT', true);
require(__DIR__.'/../../../../../../config.php');
require_once($CFG->libdir.'/clilib.php');      // cli only functions




echo "***Starting ***" . \PHP_EOL;




// TODO change me to vacca before running on live.

function getCSVData($filename){
    $CSV = \fopen(__DIR__ ."/{$filename}", 'r');

// Headrow
    $headerRow = \fgetcsv($CSV, 3000, ",");

// Rows
    $csv = [];
    while ($rows = \fgetcsv($CSV, 3000, ",")) {
        $row = \array_combine($headerRow, $rows);
        $csv[] = $row;

    }

    fclose($CSV);
    return $csv;
}

$affectedusers  = getCSVData('data.csv');
global $DB;
foreach ($affectedusers as $affecteduser){
    $email = $affecteduser['email'];
    $courseidnumber = $affecteduser['courseidnumber'];
    print_r($email.PHP_EOL);
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
    }
    
  

die();


}


echo \PHP_EOL . "***End ***";



exit(0);