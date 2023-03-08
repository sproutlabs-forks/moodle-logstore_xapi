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

namespace src\loader\moodle_curl_lrs;
defined('MOODLE_INTERNAL') || die();

global $CFG;
if (!isset($CFG)) {
    $CFG = (object) [ 'libdir' => 'utils' ];
}
require_once($CFG->libdir . '/filelib.php');

use src\loader\utils as utils;

function load(array $config, array $events) {
    $sendhttpstatements = function (array $config, array $statements) {
        global $DB;
        
        $endpoint = $config['lrs_endpoint'];
        $username = $config['lrs_username'];
        $password = $config['lrs_password'];
        $forwardendpoint = $config['forwardendpoint'];
        
        $url = utils\correct_endpoint($endpoint).'/statements';
        $auth = base64_encode($username.':'.$password);
        $postdata = json_encode($statements);

        $request = new \curl();
        $responsetext = $request->post($url, $postdata, [
            'CURLOPT_HTTPHEADER' => [
                'Authorization: Basic '.$auth,
                'X-Experience-API-Version: 1.0.0',
                'Content-Type: application/json',
            ],
        ]);
        $responsecode = $request->info['http_code'];
        if($forwardendpoint){
            $statements = array_filter($statements,function($statement){
                return ($statement['verb']['id']=='http://id.tincanapi.com/verb/completed' || $statement['verb']['id']=='http://adlnet.gov/expapi/verbs/completed');
            });

            $newpostdata = json_encode($statements);
            $newrequest = new \curl();
            $newresponsetext = $newrequest->post($forwardendpoint, $newpostdata, [
                'CURLOPT_HTTPHEADER' => [
                    'Content-Type: application/json',
                ],
            ]);
            $newresponsecode = $newrequest->info['http_code'];
            if ($newresponsecode !== 200) {
                try {
                    $DB->insert_record('logstore_xapi_forward_failed_log', array('statements' => $newpostdata,'timemodified'=>time()));
                }catch (\Exception $e) {
                    $DB->insert_record('logstore_xapi_forward_failed_log', array('statements' => 'Failed to store statements','timemodified'=>time()));
                }
            }
        }
        
        if ($responsecode !== 200) {
            throw new \Exception($responsetext);
        }
    };
    return utils\load_in_batches($config, $events, $sendhttpstatements);
}
