<?php


namespace logstore_xapi\task;

defined('MOODLE_INTERNAL') || die();

use logstore_xapi\log\store;
if (!isset($CFG)) {
    $CFG = (object) [ 'libdir' => 'utils' ];
}
require_once($CFG->libdir . '/filelib.php');
class resend_failed_integration extends \core\task\scheduled_task {

    private $sentcount;

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskresendfailed2', 'logstore_xapi');
    }

  
    public function execute() {
        global $DB;
        
        $forwardendpoint = get_config('logstore_xapi','forwardendpoint');
        if($forwardendpoint){
            $failed_logs = $DB->get_records('logstore_xapi_forward_failed_log');
            $count = count($failed_logs);
            print_r("Processing {$count} failed logs");
            foreach ($failed_logs as $failed_log){
                $newrequest = new \curl();
                $newresponsetext = $newrequest->post($forwardendpoint, $failed_log->statements, [
                    'CURLOPT_HTTPHEADER' => [
                        'Content-Type: application/json',
                    ],
                ]);
                $newresponsecode = $newrequest->info['http_code'];
                if ($newresponsecode == 200) {
                    $params = [
                        'id' => $failed_log->id,
                    ];
                    $DB->delete_records('logstore_xapi_forward_failed_log', $params);
                }
            }
            
        }

    }

}
