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

namespace src\transformer\events\mod_assign;

defined('MOODLE_INTERNAL') || die();

use src\transformer\utils as utils;

function assignment_graded(array $config, \stdClass $event) {
    $repo = $config['repo'];
    $grade = $repo->read_record_by_id($event->objecttable, $event->objectid);
    $user = $repo->read_record_by_id('user', $grade->userid);
    $course = $repo->read_record_by_id('course', $event->courseid);
    $instructor = $repo->read_record_by_id('user', $event->userid);
    $assignment = $repo->read_record_by_id('assign', $grade->assignment);
    $lang = utils\get_course_lang($course);

    $gradecomment = $repo->read_record('assignfeedback_comments', [
        'assignment' => $grade->assignment,
        'grade' => $grade->id
    ])->commenttext;
    $gradeitems = $repo->read_record('grade_items', [
        'itemmodule' => 'assign',
        'iteminstance' => $grade->assignment
    ]);

    $scoreraw = (float) ($grade->grade ?: 0);
    $scoremin = (float) ($gradeitems->grademin ?: 0);
    $scoremax = (float) ($gradeitems->grademax ?: 0);
    $scorepass = (float) ($gradeitems->gradepass ?: null);

    $completion = 'unknown';

    if ($scoreraw >= $scorepass) {
        $completion = true;
    }

    // Calculate scaled score as the distance from zero towards the max (or min for negative scores).
    if ($scoreraw >= 0) {
        $scorescaled = $scoreraw / $scoremax;
    } else {
        $scorescaled = $scoreraw / $scoremin;
    }

    return [[
        'actor' => utils\get_user($config, $user),
        'verb' => [
            'id' => 'http://adlnet.gov/expapi/verbs/scored',
            'display' => [
                $lang => 'attained grade for'
            ],
        ],
        'object' => utils\get_activity\course_assignment($config, $event->contextinstanceid, $assignment->name, $lang),
        'result' => [
            'score' => [
                'raw' => $scoreraw,
                'min' => $scoremin,
                'max' => $scoremax,
                'scaled' => $scorescaled
            ],
            'completion' => $completion,
            'response' => $gradecomment
        ],
        'timestamp' => utils\get_event_timestamp($event),
        'context' => [
            'instructor' => utils\get_user($config, $instructor),
            'platform' => $config['source_name'],
            'language' => $lang,
            'extensions' => [
                utils\INFO_EXTENSION => utils\get_info($config, $event),
                utils\EVENT_EXTENSION => $event,
            ],
            'contextActivities' => [
                'grouping' => [
                    utils\get_activity\site($config),
                    utils\get_activity\course($config, $course)
                ],
                'category' => [
                    utils\get_activity\source($config),
                ],
            ],
        ]
    ]];
}
