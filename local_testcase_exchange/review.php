<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// CodeRunner is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Contribution review page.
 *
 * @package    local_testcase_exchange
 * @copyright  2026 Nguyen Quoc Thinh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
use local_testcase_exchange\context_service;
use local_testcase_exchange\external_database;
use local_testcase_exchange\schema_manager;
use local_testcase_exchange\testcase_service;

require_once(__DIR__ . '/../../config.php');

require_login();
$courseid = optional_param('course', 0, PARAM_INT);

if ($courseid === 0) {
    $context = context_system::instance();
    $PAGE->set_url(new moodle_url('/local/testcase_exchange/review.php'));
    $PAGE->set_context($context);
    $PAGE->set_title(get_string('coursehub_review_title', 'local_testcase_exchange'));
    $PAGE->set_heading(get_string('coursehub_review_title', 'local_testcase_exchange'));

    $coursesummaries = [];
    $connection = null;
    try {
        $connection = external_database::connect();
        schema_manager::migrate($connection);
        $service = new testcase_service($connection);
        $coursesummaries = $service->courses_summary();
    } catch (Throwable $e) {
        debugging($e->getMessage(), DEBUG_DEVELOPER);
    }

    $rawcourses = context_service::courses_with_coderunner();
    $courseids_found = [];
    foreach ($rawcourses as $rc) {
        $courseids_found[(int) $rc->courseid] = true;
    }

    foreach (array_keys($coursesummaries) as $cid) {
        if (!isset($courseids_found[$cid])) {
            $cobj = $DB->get_record('course', ['id' => $cid], 'id, fullname, shortname');
            if ($cobj) {
                $rawcourses[] = (object) [
                    'courseid' => $cobj->id,
                    'fullname' => $cobj->fullname,
                    'shortname' => $cobj->shortname,
                    'quiz_count' => 0,
                    'question_count' => 0,
                ];
                $courseids_found[$cid] = true;
            }
        }
    }

    $issiteadmin = is_siteadmin();
    $availablecourses = [];
    $totalallcontributions = 0;
    $totalpendingcontributions = 0;
    $totalapprovedcontributions = 0;

    foreach ($rawcourses as $c) {
        $cid = (int) $c->courseid;
        $ccontext = context_course::instance($cid);
        if (!$issiteadmin && !has_capability('local/testcase_exchange:review', $ccontext)) {
            continue;
        }

        $cstats = $coursesummaries[$cid] ?? [
            'total_count' => 0,
            'pending_count' => 0,
            'approved_count' => 0,
        ];

        $canmanage = $issiteadmin || has_capability('local/testcase_exchange:manage', $ccontext);

        $totalallcontributions += $cstats['total_count'];
        $totalpendingcontributions += $cstats['pending_count'];
        $totalapprovedcontributions += $cstats['approved_count'];

        $availablecourses[] = [
            'courseid' => $cid,
            'fullname' => $c->fullname,
            'shortname' => $c->shortname,
            'quiz_count' => (int) $c->quiz_count,
            'question_count' => (int) $c->question_count,
            'total_contributions' => (int) $cstats['total_count'],
            'pending_contributions' => (int) $cstats['pending_count'],
            'approved_contributions' => (int) $cstats['approved_count'],
            'has_pending' => ((int) $cstats['pending_count'] > 0),
            'dashboard_url' => (new moodle_url('/local/testcase_exchange/index.php', ['course' => $cid]))->out(false),
            'review_url' => (new moodle_url('/local/testcase_exchange/review.php', ['course' => $cid]))->out(false),
            'policy_url' => (new moodle_url('/local/testcase_exchange/policy.php', ['course' => $cid]))->out(false),
            'can_review' => true,
            'can_manage' => $canmanage,
        ];
    }

    if (!$issiteadmin && count($availablecourses) === 1) {
        redirect(new moodle_url('/local/testcase_exchange/review.php', ['course' => $availablecourses[0]['courseid']]));
    }

    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('local_testcase_exchange/course_hub', [
        'hub_title' => get_string('coursehub_review_title', 'local_testcase_exchange'),
        'hub_desc' => get_string('coursehub_review_desc', 'local_testcase_exchange'),
        'is_review_mode' => true,
        'index_hub_url' => (new moodle_url('/local/testcase_exchange/index.php'))->out(false),
        'review_hub_url' => (new moodle_url('/local/testcase_exchange/review.php'))->out(false),
        'has_courses' => !empty($availablecourses),
        'total_courses_count' => count($availablecourses),
        'total_all_contributions' => $totalallcontributions,
        'total_pending_contributions' => $totalpendingcontributions,
        'total_approved_contributions' => $totalapprovedcontributions,
        'courses' => $availablecourses,
    ]);
    if (isset($connection) && $connection) {
        $connection->close();
    }
    echo $OUTPUT->footer();
    exit;
}

$course = get_course($courseid);
$context = context_course::instance($courseid);
require_capability('local/testcase_exchange:review', $context);

$connection = null;
try {
    $connection = external_database::connect();
    schema_manager::migrate($connection);
} catch (Throwable $e) {
    debugging($e->getMessage(), DEBUG_DEVELOPER);
    throw new moodle_exception('databaseunavailable', 'local_testcase_exchange');
}
$service = new testcase_service($connection);


if (data_submitted()) {
    require_sesskey();
    try {
        $service->review(
            required_param('contribution_id', PARAM_INT),
            $courseid,
            (int) $USER->id,
            required_param('decision', PARAM_ALPHAEXT),
            optional_param('review_note', '', PARAM_TEXT),
            optional_param('rating', 0, PARAM_INT)
        );
        redirect(
            $PAGE->url,
            get_string('reviewsaved', 'local_testcase_exchange'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } catch (Throwable $e) {
        $message = $e instanceof moodle_exception ? $e->getMessage() :
            get_string('unexpectederror', 'local_testcase_exchange');
        if (!($e instanceof moodle_exception)) {
            debugging($e->getMessage(), DEBUG_DEVELOPER);
        }
        redirect($PAGE->url, $message, null, \core\output\notification::NOTIFY_ERROR);
    }
}

// Question filter.
$questionparam = optional_param('question', '', PARAM_RAW_TRIMMED);
$filterquizid = 0;
$filterquestionid = 0;
if ($questionparam !== '' && strpos($questionparam, ':') !== false) {
    [$filterquizid, $filterquestionid] = array_map('intval', explode(':', $questionparam, 2));
}

// Status counts.
$counts = $service->contribution_counts($courseid, $filterquizid, $filterquestionid);

// Status filter.
$statusparam = optional_param('status', '', PARAM_ALPHAEXT);
if ($statusparam === '') {
    $statusparam = ($counts['pending'] > 0) ? 'pending' : 'all';
}

$pageparams = ['course' => $courseid];
if ($statusparam !== 'all') {
    $pageparams['status'] = $statusparam;
}
if ($questionparam !== '') {
    $pageparams['question'] = $questionparam;
}

$PAGE->set_url(new moodle_url('/local/testcase_exchange/review.php', $pageparams));
$PAGE->set_context($context);
$PAGE->set_title(get_string('reviewcontributions', 'local_testcase_exchange'));
$PAGE->set_heading($course->fullname . ' — ' . get_string('reviewcontributions', 'local_testcase_exchange'));

$items = $service->course_contributions($courseid, $statusparam, $filterquizid, $filterquestionid);
$enricheditems = context_service::enrich_contributions($items, $courseid);

// Build status tabs.
$tabdefs = [
    'all' => [get_string('all', 'core'), $counts['all']],
    'pending' => [get_string('pending', 'local_testcase_exchange'), $counts['pending']],
    'approved' => [get_string('status_approved', 'local_testcase_exchange'), $counts['approved']],
    'duplicate' => [get_string('status_duplicate', 'local_testcase_exchange'), $counts['duplicate']],
    'rejected' => [get_string('status_rejected', 'local_testcase_exchange'), $counts['rejected']],
];
$statustabs = [];
foreach ($tabdefs as $code => [$label, $cnt]) {
    $tparams = ['course' => $courseid, 'status' => $code];
    if ($questionparam !== '') {
        $tparams['question'] = $questionparam;
    }
    $statustabs[] = [
        'code' => $code,
        'label' => $label,
        'count' => $cnt,
        'url' => (new moodle_url('/local/testcase_exchange/review.php', $tparams))->out(false),
        'is_active' => ($statusparam === $code),
    ];
}

// Build question options for filter.
$questions = context_service::questions_for_course($courseid);
$questionoptions = [
    [
        'value' => '',
        'label' => get_string('allquestions', 'local_testcase_exchange'),
        'selected' => ($questionparam === ''),
    ],
];
foreach ($questions as $q) {
    $val = $q->quizid . ':' . $q->questionid;
    $questionoptions[] = [
        'value' => $val,
        'label' => $q->quizname . ' — ' . $q->questionname,
        'selected' => ($questionparam === $val),
    ];
}

$rawcourses = context_service::courses_with_coderunner();
$availablecourselist = [];
$issiteadmin = is_siteadmin();
foreach ($rawcourses as $rc) {
    $cid = (int) $rc->courseid;
    if (!$issiteadmin && !has_capability('local/testcase_exchange:review', context_course::instance($cid))) {
        continue;
    }
    $availablecourselist[] = [
        'courseid' => $cid,
        'fullname' => $rc->fullname,
        'shortname' => $rc->shortname,
        'switch_url' => (new moodle_url('/local/testcase_exchange/review.php', ['course' => $cid]))->out(false),
        'is_current' => ($cid === $courseid),
    ];
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_testcase_exchange/review', [
    'sesskey' => sesskey(),
    'course_id' => $courseid,
    'course_hub_url' => (new moodle_url('/local/testcase_exchange/review.php'))->out(false),
    'current_course_name' => $course->fullname,
    'current_course_shortname' => $course->shortname,
    'available_courses' => $availablecourselist,
    'show_course_switcher' => (count($availablecourselist) > 1 || $issiteadmin),
    'dashboard_url' => (new moodle_url('/local/testcase_exchange/index.php', ['course' => $courseid]))->out(false),
    'current_status' => $statusparam,
    'status_tabs' => $statustabs,
    'question_options' => $questionoptions,
    'has_question_filter' => count($questionoptions) > 1,
    'selected_question' => $questionparam,
    'items' => $enricheditems,
    'has_items' => !empty($enricheditems),
    'total_count' => count($enricheditems),
]);
$connection->close();
echo $OUTPUT->footer();
