<?php

/**
 *
 * @package
 * @subpackage
 * @copyright   2019 Olumuyiwa Taiwo <muyi.taiwo@logicexpertise.com>
 * @author      Olumuyiwa Taiwo {@link https://moodle.org/user/view.php?id=416594}
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace logstore_xapi\task;

defined('MOODLE_INTERNAL') || die();

use logstore_xapi\log\store;

class resend_failed_task extends \core\task\scheduled_task {

    private $sentcount;

    /**
     * Get a descriptive name for this task (shown to admins).
     *
     * @return string
     */
    public function get_name() {
        return get_string('taskresendfailed', 'logstore_xapi');
    }

    private function extract_events($limitfrom, $limitnum) {
        global $DB;

        $params = [];
        $select = '';
        $sort = '';
        $fields = '*';
        $extractedevents = $DB->get_records_select('logstore_xapi_failed_log', $select, $params, $sort, $fields, $limitfrom, $limitnum);
        return $extractedevents;
    }

    private function get_event_ids($events) {
        return array_map(function ($event) {
            return $event->id;
        }, $events);
    }

    private function delete_events($events) {
        global $DB;
        $eventids = $this->get_event_ids($events);
        $DB->delete_records_list('logstore_xapi_failed_log', 'id', $eventids);
    }

    private function get_successful_events($events) {
        $loadedevents = array_filter($events, function ($loadedevent) {
            return $loadedevent['loaded'] === true;
        });
        $successfulevents = array_map(function ($loadedevent) {
            return $loadedevent['event'];
        }, $loadedevents);
        return $successfulevents;
    }

    private function get_failed_events($events) {
        $nonloadedevents = array_filter($events, function ($loadedevent) {
            return $loadedevent['loaded'] === false;
        });
        $failedevents = array_map(function ($nonloadedevent) {
            return $nonloadedevent['event'];
        }, $nonloadedevents);
        return $failedevents;
    }

    private function report_successful_events($events) {
        mtrace(count($events) . " event(s) have been successfully sent to the LRS.");
        $eventids = $this->get_event_ids($events);
        mtrace("Events (" . implode(', ', $eventids) . ") have been successfully sent to LRS.");
        $this->sentcount += count($events);
    }

    private function report_failed_events($events) {
        mtrace(count($events) . " event(s) have failed to send to the LRS.");
    }

    private function notify_admin() {
        $site = get_site();
        $admin = get_admin();
        $message = "";
        $prefix = "[" . format_string($site->shortname, true, array('context' => \context_course::instance(SITEID))) . "] ";
        $subject = $prefix . $this->get_name();
        $message .= get_string('sentsuccess', 'logstore_xapi', $this->sentcount) . PHP_EOL;
        $message .= get_string('recordsleft', 'logstore_xapi', count($this->extract_events(0, 0)));

        //Send the message
        $eventdata = new \stdClass();
        $eventdata->modulename = 'moodle';
        $eventdata->userfrom = $admin;
        $eventdata->userto = $admin;
        $eventdata->subject = $subject;
        $eventdata->fullmessage = $message;
        $eventdata->fullmessageformat = FORMAT_PLAIN;
        $eventdata->fullmessagehtml = '';
        $eventdata->smallmessage = '';
        $eventdata->component = 'logstore_xapi';
        $eventdata->name = 'resendfailed';

        message_send($eventdata);
    }

    /**
     * Do the job.
     * Throw exceptions on errors (the job will be retried).
     */
    public function execute() {

        $this->sentcount = 0;

        $manager = get_log_manager();
        $store = new store($manager);

        $batchsize = $store->get_max_batch_size();
        $failed = true;
        while ($batchsize > 1 && $failed) {
            $limitfrom = 0;
            while ($extractedevents = $this->extract_events($limitfrom, $batchsize)) {
                $loadedevents = $store->process_events($extractedevents);

                $failedevents = $this->get_failed_events($loadedevents);
                $this->report_failed_events($failedevents);

                $successfulevents = $this->get_successful_events($loadedevents);
                $this->report_successful_events($successfulevents);
                $this->delete_events($successfulevents);

                $limitfrom += count($extractedevents);
            }
            $batchsize = round($batchsize / 2);
            if (count($failedevents) <= 0) {
                $failed = false;
            }
        }

       // $this->notify_admin();
    }

}
