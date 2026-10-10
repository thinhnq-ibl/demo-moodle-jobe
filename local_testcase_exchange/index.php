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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Student testcase exchange dashboard.
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

$courseid = required_param('course', PARAM_INT);
$course = get_course($courseid);
$context = context_course::instance($courseid);
require_capability('local/testcase_exchange:view', $context);

$requestedselection = optional_param('question', '', PARAM_RAW_TRIMMED);
$attemptid = optional_param('attempt', 0, PARAM_INT);
$pageparams = ['course' => $courseid];
if ($requestedselection !== '') {
    $pageparams['question'] = $requestedselection;
}
if ($attemptid > 0) {
    $pageparams['attempt'] = $attemptid;
}
$PAGE->set_url(new moodle_url('/local/testcase_exchange/index.php', $pageparams));
$PAGE->set_context($context);
$PAGE->set_title(get_string('nav_testcase_bank', 'local_testcase_exchange'));
$PAGE->set_heading($course->fullname . ' — ' . get_string('nav_testcase_bank', 'local_testcase_exchange'));

$questions = context_service::questions_for_course($courseid);
$questionmap = [];
foreach ($questions as $question) {
    $questionmap[$question->quizid . ':' . $question->questionid] = $question;
}

$studentcode = '';
$returnurl = '';
$selectedquestion = $questionmap[$requestedselection] ?? null;
if ($attemptid > 0 && $selectedquestion) {
    $attempt = $DB->get_record_select(
        'quiz_attempts',
        'id = :id AND quiz = :quiz AND userid = :userid AND state <> :abandoned',
        [
            'id' => $attemptid,
            'quiz' => $selectedquestion->quizid,
            'userid' => $USER->id,
            'abandoned' => 'abandoned',
        ]
    );
    if ($attempt) {
        $studentcode = context_service::get_latest_valid_submission_code(
            (int) $attempt->uniqueid,
            (int) $selectedquestion->questionid
        );
        if ($studentcode !== '') {
            $cm = get_coursemodule_from_instance('quiz', $attempt->quiz, $courseid, false, MUST_EXIST);
            if ($attempt->state === 'finished') {
                $returnurl = (new moodle_url('/mod/quiz/review.php', [
                    'attempt' => $attempt->id,
                    'cmid' => $cm->id,
                ]))->out(false);
            } else {
                $returnurl = (new moodle_url('/mod/quiz/attempt.php', [
                    'attempt' => $attempt->id,
                    'cmid' => $cm->id,
                ]))->out(false);
            }
        }
    }
}

$connection = null;
$service = null;
$databaseerror = '';
try {
    $connection = external_database::connect();
    schema_manager::migrate($connection);
    $service = new testcase_service($connection);
} catch (Throwable $e) {
    $databaseerror = get_string('databaseunavailable', 'local_testcase_exchange');
    debugging($e->getMessage(), DEBUG_DEVELOPER);
}

if ($service && data_submitted()) {
    require_sesskey();
    $action = required_param('action', PARAM_ALPHAEXT);
    try {
        if ($action === 'run') {
            require_capability('local/testcase_exchange:run', $context);
            $selection = required_param('question', PARAM_RAW_TRIMMED);
            if (!isset($questionmap[$selection])) {
                throw new moodle_exception('invalidcontext', 'local_testcase_exchange');
            }
            $questioncontext = $questionmap[$selection];
            $quiz = $DB->get_record('quiz', ['id' => $questioncontext->quizid, 'course' => $courseid], '*', MUST_EXIST);
            $studentcode = required_param('student_code', PARAM_RAW);
            $testinput = required_param('test_input', PARAM_RAW);
            $predictedoutput = optional_param('predicted_output', '', PARAM_RAW);
            $run = $service->create_run(
                $courseid,
                $quiz,
                $questioncontext,
                (int) $USER->id,
                $studentcode,
                $testinput,
                $predictedoutput,
                optional_param('purpose', '', PARAM_TEXT),
                optional_param('category', 'other', PARAM_ALPHANUMEXT),
                optional_param('reflection', '', PARAM_TEXT)
            );
            if ($run['oracle']['outcome'] !== 'success') {
                $message = get_string('runcreatedoraclefailed', 'local_testcase_exchange', $run['oracle']['outcome']);
                $notifytype = \core\output\notification::NOTIFY_ERROR;
            } else if ($run['student']['outcome'] !== 'success') {
                $message = get_string('runcreatedstudenterror', 'local_testcase_exchange');
                $notifytype = \core\output\notification::NOTIFY_INFO;
            } else if ($run['prediction_matches_oracle']) {
                $message = get_string('runcreatedmatch', 'local_testcase_exchange');
                $notifytype = \core\output\notification::NOTIFY_SUCCESS;
            } else if (trim($predictedoutput) !== '') {
                $message = get_string('runcreatedmismatch', 'local_testcase_exchange');
                $notifytype = \core\output\notification::NOTIFY_WARNING;
            } else {
                $message = get_string('runcreatedsaved', 'local_testcase_exchange');
                $notifytype = \core\output\notification::NOTIFY_SUCCESS;
            }
            redirect($PAGE->url, $message, null, $notifytype);
        } else if ($action === 'contribute') {
            require_capability('local/testcase_exchange:contribute', $context);
            $result = $service->submit_contribution(required_param('run_id', PARAM_INT), (int) $USER->id);
            redirect(
                $PAGE->url,
                get_string('contributionstatus', 'local_testcase_exchange', $result['status']),
                null,
                $result['status'] === 'duplicate' ?
                    \core\output\notification::NOTIFY_WARNING : \core\output\notification::NOTIFY_SUCCESS
            );
        } else if ($action === 'explain') {
            require_capability('local/testcase_exchange:contribute', $context);
            $service->resubmit_explanation(
                required_param('contribution_id', PARAM_INT),
                (int) $USER->id,
                required_param('explanation', PARAM_TEXT)
            );
            redirect(
                $PAGE->url,
                get_string('explanationsaved', 'local_testcase_exchange'),
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }
    } catch (Throwable $e) {
        $message = $e instanceof moodle_exception ? $e->getMessage() :
            get_string('unexpectederror', 'local_testcase_exchange');
        if (!($e instanceof moodle_exception)) {
            debugging($e->getMessage(), DEBUG_DEVELOPER);
        }
        redirect($PAGE->url, $message, null, \core\output\notification::NOTIFY_ERROR);
    }
}

$runs = $service ? $service->runs_for_user($courseid, (int) $USER->id) : [];
$rawcontributions = $service ? $service->contributions_for_user($courseid, (int) $USER->id) : [];
$contributions = !empty($rawcontributions) ?
    \local_testcase_exchange\context_service::enrich_contributions($rawcontributions, $courseid) : [];
$rewards = $service ? $service->rewards_for_user($courseid, (int) $USER->id) : [];
$contributionbyrun = [];
foreach ($contributions as $contribution) {
    $contributionbyrun[(int) $contribution['run_id']] = $contribution;
}

if ($databaseerror) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification($databaseerror, 'notifyproblem');
    echo $OUTPUT->footer();
    exit;
}

$cancontribute = has_capability('local/testcase_exchange:contribute', $context);
$canmanage = has_capability('local/testcase_exchange:manage', $context);
$canreview = has_capability('local/testcase_exchange:review', $context);
$isteacher = $canmanage || $canreview;

$coursecontributions = ($service && $isteacher) ? $service->course_contributions($courseid, 'all') : [];
$enrichedcoursecontributions = $isteacher ?
    \local_testcase_exchange\context_service::enrich_contributions($coursecontributions, $courseid) : [];
$counts = ($service && $isteacher) ?
    $service->contribution_counts($courseid) : ['pending' => 0, 'all' => 0];

$hasattemptcode = $studentcode !== '';
$viewquestions = [];
foreach ($questions as $question) {
    $quizsettings = $service->quiz_settings((int) $question->quizid);
    $disabled = empty($quizsettings['enabled']);
    $viewquestions[] = [
        'value' => $question->quizid . ':' . $question->questionid,
        'label' => $question->quizname . ' — ' . $question->questionname .
            ($disabled ? ' (' . get_string('disabled', 'core') . ')' : ''),
        'disabled' => $disabled,
        'selected' => $requestedselection === $question->quizid . ':' . $question->questionid,
    ];
}
foreach ($runs as &$run) {
    $existing = $contributionbyrun[(int) $run['id']] ?? null;
    $runsettings = $service->quiz_settings((int) $run['quiz_id']);
    $run['show_oracle'] = !empty($runsettings['show_oracle_output']);
    $run['display_status'] = $existing['status'] ?? ($run['oracle_outcome'] !== 'success' ? 'invalid' : 'private');
    $run['can_contribute'] = $cancontribute && !$existing && $run['oracle_outcome'] === 'success';
}
unset($run);
foreach ($contributions as &$contribution) {
    $contribution['can_explain'] = $cancontribute && $contribution['status'] === 'needs_explanation';
}
unset($contribution);

$viewcategories = [];
foreach (['normal', 'boundary', 'empty', 'invalid', 'large', 'branch', 'other'] as $category) {
    $viewcategories[] = [
        'value' => $category,
        'label' => get_string('category_' . $category, 'local_testcase_exchange'),
    ];
}
$templatedata = [
    'sesskey' => sesskey(),
    'can_manage' => $canmanage,
    'can_review' => $canreview,
    'can_manage_or_review' => $isteacher,
    'is_teacher' => $isteacher,
    'can_run' => has_capability('local/testcase_exchange:run', $context),
    'policy_url' => (new moodle_url('/local/testcase_exchange/policy.php', ['course' => $courseid]))->out(false),
    'review_url' => (new moodle_url('/local/testcase_exchange/review.php', ['course' => $courseid]))->out(false),
    'pending_contribution_count' => $counts['pending'] ?? 0,
    'course_contributions' => $enrichedcoursecontributions,
    'has_course_contributions' => !empty($enrichedcoursecontributions),
    'course_contribution_count' => count($enrichedcoursecontributions),
    'questions' => $viewquestions,
    'has_questions' => !empty($viewquestions),
    'categories' => $viewcategories,
    'student_code' => $studentcode,
    'has_prefilled_code' => $hasattemptcode,
    'show_run_form' => $hasattemptcode || $canmanage,
    'show_code_editor' => $canmanage && !$hasattemptcode,
    'use_attempt_context' => $hasattemptcode,
    'selected_question_value' => $requestedselection,
    'current_quiz_name' => $selectedquestion->quizname ?? '',
    'current_question_name' => $selectedquestion->questionname ?? '',
    'return_url' => $returnurl,
    'has_return_url' => $returnurl !== '',
    'runs' => $runs,
    'run_count' => count($runs),
    'contributions' => $contributions,
    'contribution_count' => count($contributions),
    'rewards' => $rewards,
    'reward_count' => count($rewards),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_testcase_exchange/dashboard', $templatedata);
if ($connection) {
    $connection->close();
}
echo $OUTPUT->footer();
