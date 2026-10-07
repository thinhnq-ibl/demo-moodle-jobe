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

$PAGE->set_url(new moodle_url('/local/testcase_exchange/index.php', ['course' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('nav_testcase_bank', 'local_testcase_exchange'));
$PAGE->set_heading($course->fullname . ' — ' . get_string('nav_testcase_bank', 'local_testcase_exchange'));

$questions = context_service::questions_for_course($courseid);
$questionmap = [];
foreach ($questions as $question) {
    $questionmap[$question->quizid . ':' . $question->questionid] = $question;
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
            $run = $service->create_run(
                $courseid,
                $quiz,
                $questioncontext,
                (int) $USER->id,
                required_param('student_code', PARAM_RAW),
                required_param('test_input', PARAM_RAW),
                optional_param('predicted_output', '', PARAM_RAW),
                optional_param('purpose', '', PARAM_TEXT),
                optional_param('category', 'other', PARAM_ALPHANUMEXT),
                optional_param('reflection', '', PARAM_TEXT)
            );
            $message = $run['prediction_matches_oracle'] ?
                get_string('runcreatedmatch', 'local_testcase_exchange') :
                get_string('runcreatedmismatch', 'local_testcase_exchange');
            redirect($PAGE->url, $message, null, \core\output\notification::NOTIFY_SUCCESS);
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
$contributions = $service ? $service->contributions_for_user($courseid, (int) $USER->id) : [];
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
$viewquestions = [];
foreach ($questions as $question) {
    $quizsettings = $service->quiz_settings((int) $question->quizid);
    $disabled = empty($quizsettings['enabled']);
    $viewquestions[] = [
        'value' => $question->quizid . ':' . $question->questionid,
        'label' => $question->quizname . ' — ' . $question->questionname .
            ($disabled ? ' (' . get_string('disabled', 'core') . ')' : ''),
        'disabled' => $disabled,
    ];
}
foreach ($runs as &$run) {
    $existing = $contributionbyrun[(int) $run['id']] ?? null;
    $runsettings = $service->quiz_settings((int) $run['quiz_id']);
    $run['show_oracle'] = !empty($runsettings['show_oracle_output']);
    $run['display_status'] = $existing['status'] ?? 'private';
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
    'can_manage' => has_capability('local/testcase_exchange:manage', $context),
    'can_review' => has_capability('local/testcase_exchange:review', $context),
    'can_run' => has_capability('local/testcase_exchange:run', $context),
    'policy_url' => (new moodle_url('/local/testcase_exchange/policy.php', ['course' => $courseid]))->out(false),
    'review_url' => (new moodle_url('/local/testcase_exchange/review.php', ['course' => $courseid]))->out(false),
    'questions' => $viewquestions,
    'has_questions' => !empty($viewquestions),
    'categories' => $viewcategories,
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
