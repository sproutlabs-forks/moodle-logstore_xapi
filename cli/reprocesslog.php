<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../../../../config.php');
require_once($CFG->libdir . '/clilib.php');      // cli only functions


echo "***Starting ***" . \PHP_EOL;


$config = get_config('logstore_xapi', 'reprocessusers');
$affectedusers = json_decode(base64_decode($config));

if (empty($config) || !$config) {
    exit(0);
}

global $DB;
$string = '';
foreach ($affectedusers as $affecteduser) {
    $email = $affecteduser->Email;
    $courseidnumber = $affecteduser->Code;


    $user = $DB->get_record('user', ['email' => $email]);


    if ($user && isset($user->id)) {
        print_r($user->id . "    ");
    }
    $course = $DB->get_record('course', ['idnumber' => $courseidnumber]);
    if ($course && isset($course->id)) {
        print_r($course->id . "    ");
    }
    $logs = '';
    if ($user && $course) {
        // Enable debugging for database operations
        $DB->set_debug(true);
        
        if (strtolower($affecteduser->CourseCompletionOnly) == 'true') {
            $sql = "SELECT * FROM {logstore_standard_log} 
            WHERE relateduserid = :relateduserid 
              AND courseid = :courseid 
              AND objecttable = 'course_completions'";
            $params = [
                'relateduserid' => $user->id,
                'courseid' => $course->id
            ];
        } else {
            $sql = "SELECT * FROM {logstore_standard_log} 
            WHERE relateduserid = :relateduserid 
              AND courseid = :courseid 
              AND (objecttable = 'course_modules_completion' OR objecttable = 'course_completions')";
            $params = [
                'relateduserid' => $user->id,
                'courseid' => $course->id
            ];
        }

// Fetch records using get_recordset_sql
        $recordset = $DB->get_recordset_sql($sql, $params);

// Debugging step: Print the number of records retrieved
        $count = 0;
        foreach ($recordset as $log) {
            $count++;
            // Debugging step: Print the record being processed
            // Remove the id field if present
            unset($log->id);

            // Insert the record into logstore_xapi_log
            $DB->insert_record('logstore_xapi_log', $log);
        }

        echo "Number of records retrieved: " . $count . "\n";

    }

}
$new_process_value = ''; // Replace 'new_value_here' with the new value you want to set
$config->reprocessusers = $new_process_value;

// Save the updated configuration settings
set_config('reprocessusers', $new_process_value, 'logstore_xapi');


echo \PHP_EOL . "***End ***";


exit(0);
