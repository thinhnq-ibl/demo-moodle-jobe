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
 * Testcase exchange dashboard with strict Teacher View and Student View separation.
 *
 * @package    local_testcase_exchange
 * @copyright  2026 Nguyen Quoc Thinh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
use local_testcase_exchange\context_service;
use local_testcase_exchange\testcase_service;

require_once(__DIR__ . '/../../config.php');

require_login();

$courseid = optional_param('course', 0, PARAM_INT);

if ($courseid === 0) {
    $context = context_system::instance();
    $PAGE->set_url(new moodle_url('/local/testcase_exchange/index.php'));
    $PAGE->set_context($context);
    $PAGE->set_title(get_string('coursehub', 'local_testcase_exchange'));
    $PAGE->set_heading(get_string('coursehub', 'local_testcase_exchange'));

    $coursesummaries = [];
    try {
        $service = new testcase_service();
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
        if (!$issiteadmin && !has_capability('local/testcase_exchange:view', $ccontext)) {
            continue;
        }

        $cstats = $coursesummaries[$cid] ?? [
            'total_count' => 0,
            'pending_count' => 0,
            'approved_count' => 0,
        ];

        $canreview = $issiteadmin || has_capability('local/testcase_exchange:review', $ccontext);
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
            'can_review' => $canreview,
            'can_manage' => $canmanage,
        ];
    }

    if (!$issiteadmin && count($availablecourses) === 1) {
        redirect(new moodle_url('/local/testcase_exchange/index.php', ['course' => $availablecourses[0]['courseid']]));
    }

    echo $OUTPUT->header();
    echo $OUTPUT->render_from_template('local_testcase_exchange/course_hub', [
        'hub_title' => get_string('coursehub', 'local_testcase_exchange'),
        'hub_desc' => get_string('coursehub_desc', 'local_testcase_exchange'),
        'is_review_mode' => false,
        'index_hub_url' => (new moodle_url('/local/testcase_exchange/index.php'))->out(false),
        'review_hub_url' => (new moodle_url('/local/testcase_exchange/review.php'))->out(false),
        'has_courses' => !empty($availablecourses),
        'total_courses_count' => count($availablecourses),
        'total_all_contributions' => $totalallcontributions,
        'total_pending_contributions' => $totalpendingcontributions,
        'total_approved_contributions' => $totalapprovedcontributions,
        'courses' => $availablecourses,
    ]);
    echo $OUTPUT->footer();
    exit;
}

$course = get_course($courseid);
$context = context_course::instance($courseid);
require_capability('local/testcase_exchange:view', $context);

// Role and capability detection: strictly via capabilities in course context.
$issiteadmin = is_siteadmin();
$canmanage = $issiteadmin || has_capability('local/testcase_exchange:manage', $context);
$canreview = $issiteadmin || has_capability('local/testcase_exchange:review', $context);
$canmanageactivities = has_capability('moodle/course:manageactivities', $context);
$canrun = has_capability('local/testcase_exchange:run', $context);
$cancontribute = has_capability('local/testcase_exchange:contribute', $context);

$isteacher = $canmanage || $canreview || $canmanageactivities;
$ispreview = $isteacher && (optional_param('preview', 0, PARAM_INT) === 1);

// Teacher default view vs Student view.
$is_teacher_view = $isteacher && !$ispreview;
$is_student_view = !$is_teacher_view;

$requestedselection = optional_param('question', '', PARAM_RAW_TRIMMED);
$attemptid = optional_param('attempt', 0, PARAM_INT);
$quizid = optional_param('quiz', 0, PARAM_INT);
$cmid = optional_param('cmid', 0, PARAM_INT);
$activetab = optional_param('tab', $is_teacher_view ? 'overview' : 'solve', PARAM_ALPHAEXT);

$pageparams = ['course' => $courseid];
if ($requestedselection !== '') {
    $pageparams['question'] = $requestedselection;
}
if ($attemptid > 0) {
    $pageparams['attempt'] = $attemptid;
}
if ($quizid > 0) {
    $pageparams['quiz'] = $quizid;
}
if ($ispreview) {
    $pageparams['preview'] = 1;
}
if ($activetab !== '') {
    $pageparams['tab'] = $activetab;
}
$PAGE->set_url(new moodle_url('/local/testcase_exchange/index.php', $pageparams));
$PAGE->set_context($context);

$title = $is_teacher_view ? get_string('teacherview_title', 'local_testcase_exchange') : get_string('studentview_title', 'local_testcase_exchange');
$PAGE->set_title($title);
$PAGE->set_heading($course->fullname);

$service = null;
$databaseerror = '';
try {
    $service = new testcase_service();
} catch (Throwable $e) {
    $databaseerror = get_string('databaseunavailable', 'local_testcase_exchange');
    debugging($e->getMessage(), DEBUG_DEVELOPER);
}

// Load all CodeRunner questions in the course.
$rawquestions = context_service::questions_for_course($courseid);
$modinfo = get_fast_modinfo($courseid);

$questions = [];
foreach ($rawquestions as $q) {
    $cm = $modinfo->instances['quiz'][(int) $q->quizid] ?? null;
    $hasaccess = $cm ? (bool) $cm->uservisible : false;
    $qquizsettings = $service ? $service->quiz_settings((int) $q->quizid) : ['enabled' => 0];
    $isenabled = !empty($qquizsettings['enabled']);

    // Exclude quizzes where testcase exchange is disabled (for both teacher and student views).
    if (!$isenabled) {
        continue;
    }
    // In student view: also exclude quizzes where user cannot access in Moodle.
    if ($is_student_view && !$hasaccess) {
        continue;
    }
    $questions[] = $q;
}

$questionmap = [];
foreach ($questions as $question) {
    $questionmap[$question->quizid . ':' . $question->questionid] = $question;
}

if (empty($requestedselection) && !empty($questions)) {
    if ($quizid > 0) {
        foreach ($questions as $q) {
            if ((int) $q->quizid === $quizid) {
                $requestedselection = $q->quizid . ':' . $q->questionid;
                break;
            }
        }
    }
    if (empty($requestedselection)) {
        $requestedselection = $questions[0]->quizid . ':' . $questions[0]->questionid;
    }
}

$selectedquestion = $questionmap[$requestedselection] ?? null;
if (!$selectedquestion && is_numeric($requestedselection)) {
    // If user passed question ID directly like question=119
    foreach ($questions as $q) {
        if ((int) $q->questionid === (int) $requestedselection) {
            $selectedquestion = $q;
            break;
        }
    }
} else if (!$selectedquestion && strpos($requestedselection, '-') !== false) {
    // If user passed quizid-questionid like 2-119
    $normkey = str_replace('-', ':', $requestedselection);
    $selectedquestion = $questionmap[$normkey] ?? null;
}
if (!$selectedquestion && !empty($questions)) {
    $selectedquestion = $questions[0];
}

$selected_quizid = $selectedquestion ? (int) $selectedquestion->quizid : ($quizid > 0 ? $quizid : 0);
$selected_questionid = $selectedquestion ? (int) $selectedquestion->questionid : 0;

$studentcode = '';
$returnurl = '';
$returnquizname = '';

if ($selectedquestion) {
    if ($attemptid > 0) {
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
    } else {
        $attempt = $DB->get_record_sql(
            "SELECT *
               FROM {quiz_attempts}
              WHERE quiz = :quiz
                    AND userid = :userid
                    AND state <> :abandoned
           ORDER BY attempt DESC",
            [
                'quiz' => $selectedquestion->quizid,
                'userid' => $USER->id,
                'abandoned' => 'abandoned',
            ],
            IGNORE_MULTIPLE
        );
        if ($attempt) {
            $attemptid = (int) $attempt->id;
        }
    }
    if ($attempt) {
        $studentcode = context_service::get_latest_valid_submission_code(
            (int) $attempt->uniqueid,
            (int) $selectedquestion->questionid
        );
        if ($studentcode !== '') {
            context_service::record_code_revision_if_changed(
                (int) $courseid,
                (int) $selectedquestion->quizid,
                (int) $selectedquestion->questionid,
                (int) $USER->id,
                $studentcode,
                (int) $attempt->id,
                (int) $attempt->uniqueid,
                0,
                'check'
            );
        }
        $cm = get_coursemodule_from_instance('quiz', $attempt->quiz, $courseid, false, IGNORE_MISSING);
        if ($cm) {
            $returnquizname = $selectedquestion->quizname ?? '';
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

if ($returnurl === '') {
    $targetquizid = $selected_quizid;
    if ($targetquizid === 0 && $cmid > 0) {
        $cmobj = get_coursemodule_from_id('quiz', $cmid, $courseid, false, IGNORE_MISSING);
        if ($cmobj) {
            $targetquizid = (int) $cmobj->instance;
        }
    }
    if ($targetquizid > 0) {
        $cm = get_coursemodule_from_instance('quiz', $targetquizid, $courseid, false, IGNORE_MISSING);
        if ($cm) {
            $returnquizname = $DB->get_field('quiz', 'name', ['id' => $targetquizid]) ?: '';
            $userattempt = $DB->get_record_sql(
                "SELECT * FROM {quiz_attempts}
                  WHERE quiz = :quiz AND userid = :userid AND state <> :abandoned
               ORDER BY attempt DESC",
                ['quiz' => $targetquizid, 'userid' => $USER->id, 'abandoned' => 'abandoned'],
                IGNORE_MULTIPLE
            );
            if ($userattempt) {
                if ($userattempt->state === 'finished') {
                    $returnurl = (new moodle_url('/mod/quiz/review.php', ['attempt' => $userattempt->id, 'cmid' => $cm->id]))->out(false);
                } else {
                    $returnurl = (new moodle_url('/mod/quiz/attempt.php', ['attempt' => $userattempt->id, 'cmid' => $cm->id]))->out(false);
                }
            } else {
                $returnurl = (new moodle_url('/mod/quiz/view.php', ['id' => $cm->id]))->out(false);
            }
        }
    }
}

// Service initialized above.

// Handle POST actions with strict sesskey and capability checks.
if ($service && data_submitted()) {
    require_sesskey();
    $action = required_param('action', PARAM_ALPHAEXT);
    try {
        if ($action === 'run') {
            if ($ispreview) {
                redirect($PAGE->url, get_string('previewmode_run_disabled_notice', 'local_testcase_exchange'), null, \core\output\notification::NOTIFY_INFO);
            }
            require_capability('local/testcase_exchange:run', $context);
            $selection = required_param('question', PARAM_RAW_TRIMMED);
            if (!isset($questionmap[$selection])) {
                throw new moodle_exception('invalidcontext', 'local_testcase_exchange');
            }
            $questioncontext = $questionmap[$selection];
            $quiz = $DB->get_record('quiz', ['id' => $questioncontext->quizid, 'course' => $courseid], '*', MUST_EXIST);
            $studentcode = optional_param('student_code', '', PARAM_RAW);
            $testinput = required_param('test_input', PARAM_RAW);
            $predictedoutput = required_param('predicted_output', PARAM_RAW);
            $run = $service->create_run(
                $courseid,
                $quiz,
                $questioncontext,
                (int) $USER->id,
                $studentcode,
                $testinput,
                $predictedoutput,
                optional_param('purpose', '', PARAM_TEXT),
                optional_param('category', 'normal', PARAM_ALPHANUMEXT),
                optional_param('reflection', '', PARAM_TEXT)
            );
            if (!empty($run['is_duplicate_run'])) {
                $message = 'Bạn đã chạy thử nghiệm testcase này ở lượt chạy #' . $run['id'] .
                    ' rồi. Để giảm tải hệ thống, hệ thống không chạy lại trên Sandbox.';
                $notifytype = \core\output\notification::NOTIFY_WARNING;
            } else if ($run['oracle']['outcome'] !== 'success') {
                $message = get_string('runcreatedoraclefailed', 'local_testcase_exchange', $run['oracle']['outcome']);
                $notifytype = \core\output\notification::NOTIFY_ERROR;
            } else if (!empty($run['prediction_matches_oracle'])) {
                $message = 'Kết quả kỳ vọng đã khớp chính xác với Lời giải mẫu (Solution)! Bạn đã đủ điều kiện đóng góp testcase này.';
                $notifytype = \core\output\notification::NOTIFY_SUCCESS;
            } else {
                $message = 'Kết quả kỳ vọng không khớp với Lời giải mẫu (Solution). Vui lòng kiểm tra lại testcase!';
                $notifytype = \core\output\notification::NOTIFY_WARNING;
            }
            redirect($PAGE->url, $message, null, $notifytype);
        } else if ($action === 'contribute') {
            if ($ispreview) {
                redirect($PAGE->url, get_string('previewmode_contribute_notice', 'local_testcase_exchange'), null, \core\output\notification::NOTIFY_INFO);
            }
            require_capability('local/testcase_exchange:contribute', $context);
            $result = $service->submit_contribution(required_param('run_id', PARAM_INT), (int) $USER->id);
            if (!empty($result['is_own_duplicate'])) {
                $message = get_string('already_contributed_notice', 'local_testcase_exchange', $result['canonical_id']);
                $notifytype = \core\output\notification::NOTIFY_WARNING;
            } else if (($result['status'] ?? '') === 'submitted') {
                $message = get_string('contributionsubmitted', 'local_testcase_exchange');
                $notifytype = \core\output\notification::NOTIFY_SUCCESS;
            } else if (($result['status'] ?? '') === 'approved') {
                $message = get_string('contributionapproved', 'local_testcase_exchange');
                $notifytype = \core\output\notification::NOTIFY_SUCCESS;
            } else {
                $message = get_string('contributionstatus', 'local_testcase_exchange', $result['status'] ?? '');
                $notifytype = (($result['status'] ?? '') === 'duplicate') ?
                    \core\output\notification::NOTIFY_WARNING : \core\output\notification::NOTIFY_SUCCESS;
            }
            redirect($PAGE->url, $message, null, $notifytype);
        } else if ($action === 'explain') {
            if ($ispreview) {
                redirect($PAGE->url, 'Chế độ xem thử: Không thể gửi giải trình trong chế độ xem thử.', null, \core\output\notification::NOTIFY_INFO);
            }
            require_capability('local/testcase_exchange:contribute', $context);
            $service->resubmit_explanation(
                required_param('contribution_id', PARAM_INT),
                (int) $USER->id,
                required_param('explanation', PARAM_TEXT)
            );
            redirect($PAGE->url, get_string('explanationsaved', 'local_testcase_exchange'), null, \core\output\notification::NOTIFY_SUCCESS);
        } else if ($action === 'claim_reward') {
            if ($ispreview) {
                redirect($PAGE->url, get_string('previewmode_reward_notice', 'local_testcase_exchange'), null, \core\output\notification::NOTIFY_INFO);
            }
            require_capability('local/testcase_exchange:view', $context);
            $rewardid = required_param('reward_id', PARAM_INT);
            $ok = $service->claim_reward($rewardid, (int) $USER->id, $courseid);
            if ($ok) {
                redirect($PAGE->url, get_string('rewardclaimedsuccess', 'local_testcase_exchange'), null, \core\output\notification::NOTIFY_SUCCESS);
            } else {
                redirect($PAGE->url, get_string('invalidreward', 'local_testcase_exchange'), null, \core\output\notification::NOTIFY_ERROR);
            }
        } else if ($action === 'teacher_review') {
            require_capability('local/testcase_exchange:review', $context);
            $contribid = required_param('contribution_id', PARAM_INT);
            // Strict course scope validation: verify contribution belongs to this course
            $contribexists = $DB->record_exists('local_tce_contributions', ['id' => $contribid, 'course_id' => $courseid]);
            if (!$contribexists) {
                throw new moodle_exception('invalidcontribution', 'local_testcase_exchange', '', 'Đóng góp không thuộc môn học hiện tại.');
            }
            $service->review(
                $contribid,
                $courseid,
                (int) $USER->id,
                required_param('decision', PARAM_ALPHAEXT),
                optional_param('review_note', '', PARAM_TEXT),
                optional_param('rating', 0, PARAM_INT)
            );
            $returntaburl = new moodle_url($PAGE->url, ['tab' => 'review']);
            redirect($returntaburl, get_string('reviewsaved', 'local_testcase_exchange'), null, \core\output\notification::NOTIFY_SUCCESS);
        } else if ($action === 'save_policy') {
            require_capability('local/testcase_exchange:manage', $context);
            $targetquizid = required_param('target_quiz_id', PARAM_INT);
            $targetquestionid = optional_param('target_question_id', 0, PARAM_INT);
            // Strict course scope validation: verify quiz belongs to this course
            $quiz = $DB->get_record('quiz', ['id' => $targetquizid, 'course' => $courseid], '*', MUST_EXIST);
            if ($targetquestionid > 0) {
                context_service::validate($courseid, $targetquizid, $targetquestionid);
            } else if (!empty($questions)) {
                foreach ($questions as $q) {
                    if ((int) $q->quizid === $targetquizid) {
                        $targetquestionid = (int) $q->questionid;
                        break;
                    }
                }
            }
            $values = [
                'enabled' => optional_param('enabled', 0, PARAM_BOOL),
                'review_mode' => required_param('review_mode', PARAM_ALPHA),
                'reward_policy' => required_param('reward_policy', PARAM_ALPHAEXT),
                'show_oracle_output' => optional_param('show_oracle_output', 0, PARAM_BOOL),
                'leaderboard_enabled' => optional_param('leaderboard_enabled', 0, PARAM_BOOL),
                'max_runs_per_minute' => required_param('max_runs_per_minute', PARAM_INT),
                'max_input_bytes' => required_param('max_input_bytes', PARAM_INT),
            ];
            $service->save_quiz_settings($targetquizid, (int) $USER->id, $values);
            if ($targetquestionid > 0) {
                // Read submitted fields only; do not overwrite input_mode, template, or categories with defaults
                $qvalues = [];
                $normalization = optional_param('normalization_mode', '', PARAM_ALPHA);
                if ($normalization !== '') {
                    $qvalues['normalization_mode'] = $normalization;
                }
                $inputmode = optional_param('input_mode', '', PARAM_ALPHA);
                if ($inputmode !== '') {
                    $qvalues['input_mode'] = $inputmode;
                }
                $template = optional_param('testcode_template', null, PARAM_RAW);
                if ($template !== null) {
                    $qvalues['testcode_template'] = $template;
                }
                $categories = optional_param('categories', null, PARAM_TEXT);
                if ($categories !== null) {
                    $qvalues['categories'] = $categories;
                }
                if (!empty($qvalues)) {
                    $service->save_question_policy($targetquizid, $targetquestionid, (int) $USER->id, $qvalues);
                }
            }
            $returntaburl = new moodle_url($PAGE->url, ['tab' => 'policy']);
            redirect($returntaburl, get_string('policysaved', 'local_testcase_exchange'), null, \core\output\notification::NOTIFY_SUCCESS);
        } else if ($action === 'add_manual_seed') {
            require_capability('local/testcase_exchange:manage', $context);
            $targetquizid = required_param('seed_quiz_id', PARAM_INT);
            $targetquestionid = required_param('seed_question_id', PARAM_INT);
            // Strict course scope validation: verify quiz and question belong to this course
            $quiz = $DB->get_record('quiz', ['id' => $targetquizid, 'course' => $courseid], '*', MUST_EXIST);
            context_service::validate($courseid, $targetquizid, $targetquestionid);
            $testcode = required_param('seed_testcode', PARAM_RAW);
            $stdin = optional_param('seed_stdin', '', PARAM_RAW);
            $expected = required_param('seed_expected', PARAM_RAW);
            $purpose = optional_param('seed_purpose', '', PARAM_TEXT);
            $service->add_manual_seed($courseid, $targetquizid, $targetquestionid, $testcode, $stdin, $expected, $purpose, 'manual');
            $returntaburl = new moodle_url($PAGE->url, ['tab' => 'bank']);
            redirect($returntaburl, 'Đã thêm testcase hạt giống thủ công thành công!', null, \core\output\notification::NOTIFY_SUCCESS);
        } else if ($action === 'delete_seed') {
            require_capability('local/testcase_exchange:manage', $context);
            $seedid = required_param('seed_id', PARAM_INT);
            // Strict course scope validation: verify seed belongs to this course
            $seedexists = $DB->record_exists('local_tce_seeds', ['id' => $seedid, 'course_id' => $courseid]);
            if (!$seedexists) {
                throw new moodle_exception('invalidcontext', 'local_testcase_exchange', '', 'Testcase hạt giống không thuộc môn học hiện tại.');
            }
            $service->delete_seed_testcase($seedid, $courseid);
            $returntaburl = new moodle_url($PAGE->url, ['tab' => 'bank']);
            redirect($returntaburl, 'Đã xóa testcase hạt giống thành công!', null, \core\output\notification::NOTIFY_SUCCESS);
        }
    } catch (Throwable $e) {
        $message = $e instanceof moodle_exception ? $e->getMessage() : get_string('unexpectederror', 'local_testcase_exchange');
        if (!($e instanceof moodle_exception)) {
            debugging($e->getMessage(), DEBUG_DEVELOPER);
        }
        redirect($PAGE->url, $message, null, \core\output\notification::NOTIFY_ERROR);
    }
}

if ($databaseerror) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification($databaseerror, 'notifyproblem');
    echo $OUTPUT->footer();
    exit;
}


// Form options for question selection preserving state across tabs.
$viewquestions = [];
foreach ($questions as $question) {
    $qquizsettings = $service ? $service->quiz_settings((int) $question->quizid) : ['enabled' => 0];
    $disabled = empty($qquizsettings['enabled']);
    $val = $question->quizid . ':' . $question->questionid;
    $viewquestions[] = [
        'value' => $val,
        'quiz_id' => (int) $question->quizid,
        'question_id' => (int) $question->questionid,
        'label' => trim($question->quizname) . ' — ' . trim($question->questionname) . ($disabled ? ' (' . get_string('disabled', 'core') . ')' : ''),
        'disabled' => $disabled,
        'selected' => ($requestedselection === $val),
    ];
}

$coderunnertype = $selectedquestion->coderunnertype ?? 'python3';
$is_program_input = (stripos($coderunnertype, 'input') !== false || stripos($coderunnertype, 'stdin') !== false);
$coderunnertype_display = $coderunnertype;
$is_vi = (current_language() === 'vi');
if (stripos($coderunnertype, 'python') !== false) {
    if ($is_vi) {
        $coderunnertype_display = $is_program_input ? 'Python (Dữ liệu stdin)' : 'Python (Hàm / Biểu thức)';
    } else {
        $coderunnertype_display = $is_program_input ? 'Python (stdin input)' : 'Python (Function / Expression)';
    }
} else if (stripos($coderunnertype, 'java') !== false) {
    $coderunnertype_display = $is_vi ? 'Java (Phương thức)' : 'Java (Method)';
} else if (stripos($coderunnertype, 'c') !== false) {
    $coderunnertype_display = $is_vi ? 'C/C++ (Chương trình)' : 'C/C++ (Program)';
}

$input_label = $is_program_input ?
    get_string('input_label_stdin', 'local_testcase_exchange') :
    get_string('input_label_testcode', 'local_testcase_exchange');

$input_placeholder = $is_program_input ?
    get_string('input_placeholder_stdin', 'local_testcase_exchange') :
    get_string('input_placeholder_testcode', 'local_testcase_exchange');

// Common template data.
$templatedata = [
    'sesskey' => sesskey(),
    'course_id' => $courseid,
    'quiz_id' => $selected_quizid,
    'question_id' => $selected_questionid,
    'selected_question_value' => $requestedselection,
    'current_course_name' => $course->fullname,
    'current_course_shortname' => $course->shortname,
    'show_course_switcher' => false,
    'course_hub_url' => (new moodle_url('/local/testcase_exchange/index.php'))->out(false),
    'return_url' => $returnurl,
    'has_return_url' => ($returnurl !== ''),
    'return_quiz_name' => $returnquizname,
    'questions' => $viewquestions,
    'has_questions' => !empty($viewquestions),
    'current_quiz_name' => $selectedquestion->quizname ?? '',
    'current_question_name' => $selectedquestion->questionname ?? '',
    'coderunnertype' => $coderunnertype,
    'coderunnertype_display' => $coderunnertype_display,
    'is_program_input' => $is_program_input,
    'input_label' => $input_label,
    'input_placeholder' => $input_placeholder,

    // Role view flags
    'is_teacher_view' => $is_teacher_view,
    'is_student_view' => $is_student_view,
    'is_teacher_preview' => $ispreview,
    'can_manage' => $canmanage,
    'can_review' => $canreview,
    'can_run' => $canrun,
    'can_contribute' => $cancontribute,
];

// URLs for teacher preview mode and switching.
$templatedata['preview_mode_url'] = (new moodle_url('/local/testcase_exchange/index.php', [
    'course' => $courseid,
    'quiz' => $selected_quizid,
    'question' => $requestedselection,
    'preview' => 1,
]))->out(false);

$templatedata['exit_preview_url'] = (new moodle_url('/local/testcase_exchange/index.php', [
    'course' => $courseid,
    'quiz' => $selected_quizid,
    'question' => $requestedselection,
]))->out(false);

// Format Question Text HTML for Question Prompt presentation.
$questiontext_html = '';
if ($selectedquestion && !empty($selectedquestion->questiontext)) {
    $questiontext_html = format_text($selectedquestion->questiontext, $selectedquestion->questiontextformat ?? FORMAT_HTML);
}
$templatedata['questiontext_html'] = $questiontext_html;
$templatedata['has_questiontext'] = ($questiontext_html !== '');

// =========================================================================
// TEACHER VIEW DATA PREPARATION (Management Hub with 5 Tabs)
// =========================================================================
if ($is_teacher_view) {
    $templatedata['tab_overview_active'] = ($activetab === 'overview');
    $templatedata['tab_review_active'] = ($activetab === 'review');
    $templatedata['tab_bank_active'] = ($activetab === 'bank');
    $templatedata['tab_activity_active'] = ($activetab === 'activity');
    $templatedata['tab_policy_active'] = ($activetab === 'policy');

    // Extract authentic enrolled students (excluding teachers and site managers) to isolate metrics
    $enrolled = get_enrolled_users($context, 'local/testcase_exchange:run');
    $student_ids = [];
    foreach ($enrolled as $eu) {
        if (!has_capability('local/testcase_exchange:manage', $context, $eu) &&
            !has_capability('local/testcase_exchange:review', $context, $eu) &&
            !is_siteadmin($eu->id)) {
            $student_ids[] = (int) $eu->id;
        }
    }

    // Tab 1: Overview stats (isolated to enrolled students)
    $totalruns = $service ? $service->total_runs_count($courseid, $selected_quizid, $selected_questionid, $student_ids) : 0;
    $contributioncounts = $service ? $service->contribution_counts($courseid, $selected_quizid, $selected_questionid) : [];
    $rewardstats = $service ? $service->total_rewards_count($courseid, $selected_quizid, $selected_questionid, $student_ids) : ['total' => 0, 'claimed' => 0, 'unclaimed' => 0];
    $currentquizsettings = $selected_quizid > 0 && $service ? $service->quiz_settings($selected_quizid) : ['enabled' => 0];

    $scopelabel = get_string('scope_course', 'local_testcase_exchange');
    if ($selected_questionid > 0) {
        $scopelabel = get_string('scope_question', 'local_testcase_exchange');
    } else if ($selected_quizid > 0) {
        $scopelabel = get_string('scope_quiz', 'local_testcase_exchange');
    }

    $templatedata['overview_stats'] = [
        'is_quiz_enabled' => !empty($currentquizsettings['enabled']),
        'total_runs' => $totalruns,
        'total_contributions' => $contributioncounts['all'] ?? 0,
        'pending_contributions' => $contributioncounts['pending'] ?? 0,
        'approved_contributions' => $contributioncounts['approved'] ?? 0,
        'rejected_contributions' => $contributioncounts['rejected'] ?? 0,
        'duplicate_contributions' => $contributioncounts['duplicate'] ?? 0,
        'total_rewards' => $rewardstats['total'],
        'claimed_rewards' => $rewardstats['claimed'],
        'unclaimed_rewards' => $rewardstats['unclaimed'],
        'active_questions_count' => count($questions),
        'scope_label' => $scopelabel,
    ];

    // Tab 2: Review (All student contributions with filters)
    $review_status = optional_param('review_status', 'all', PARAM_ALPHAEXT);
    $review_student = optional_param('review_student', 0, PARAM_INT);
    $raw_course_contributions = $service ? $service->course_contributions($courseid, $review_status, $selected_quizid, $selected_questionid) : [];
    if ($review_student > 0) {
        $raw_course_contributions = array_values(array_filter($raw_course_contributions, function($c) use ($review_student) {
            return (int) $c['user_id'] === $review_student;
        }));
    }
    $enriched_contributions = context_service::enrich_contributions($raw_course_contributions, $courseid);

    // Extract student filter list
    $filter_students = [];
    $all_class_contributions = $service ? $service->course_contributions($courseid, 'all', $selected_quizid, $selected_questionid) : [];
    $enriched_for_students = context_service::enrich_contributions($all_class_contributions, $courseid);
    $seen_uids = [];
    foreach ($enriched_for_students as $ec) {
        $uid = (int) $ec['user_id'];
        if (!isset($seen_uids[$uid])) {
            $seen_uids[$uid] = true;
            $filter_students[] = [
                'user_id' => $uid,
                'name' => $ec['student_name'] . ' (' . $ec['student_username'] . ')',
                'selected' => ($uid === $review_student),
            ];
        }
    }

    $templatedata['teacher_contributions'] = $enriched_contributions;
    $templatedata['teacher_contribution_count'] = count($enriched_contributions);
    $templatedata['has_teacher_contributions'] = !empty($enriched_contributions);
    $templatedata['review_filter_students'] = $filter_students;
    $templatedata['review_status_' . $review_status] = true;
    $templatedata['current_review_status'] = $review_status;
    $templatedata['current_review_student'] = $review_student;

    // Tab 3: Testcase Bank (Distinguish Public Samples vs Seeds vs Approved Peer)
    $public_samples = [];
    if ($selected_questionid > 0) {
        try {
            $questionobj = \question_bank::load_question($selected_questionid, true);
            if (!empty($questionobj->testcases)) {
                $sidx = 1;
                foreach ($questionobj->testcases as $tc) {
                    if (!empty($tc->useasexample)) {
                        $public_samples[] = [
                            'index' => $sidx++,
                            'input' => ($tc->stdin !== '') ? $tc->stdin : $tc->testcode,
                            'expected' => $tc->expected,
                            'mark' => $tc->mark,
                            'display' => $tc->display,
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            debugging('Error loading question for public samples: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }
    $seed_testcases = ($service && $selected_quizid > 0 && $selected_questionid > 0) ?
        $service->list_seed_testcases($courseid, $selected_quizid, $selected_questionid) : [];
    $raw_approved = ($service && $selected_quizid > 0 && $selected_questionid > 0) ?
        $service->course_contributions($courseid, 'approved', $selected_quizid, $selected_questionid) : [];
    $approved_peer_testcases = context_service::enrich_contributions($raw_approved, $courseid);

    $templatedata['public_samples'] = $public_samples;
    $templatedata['has_public_samples'] = !empty($public_samples);
    $templatedata['public_samples_count'] = count($public_samples);

    $templatedata['seed_testcases'] = $seed_testcases;
    $templatedata['has_seed_testcases'] = !empty($seed_testcases);
    $templatedata['seed_testcases_count'] = count($seed_testcases);

    $templatedata['approved_peer_testcases'] = $approved_peer_testcases;
    $templatedata['has_approved_peer_testcases'] = !empty($approved_peer_testcases);
    $templatedata['approved_peer_testcases_count'] = count($approved_peer_testcases);

    // Tab 4: Student Activity and Export (strictly isolated to genuine students)
    $raw_activities = $service ? $service->student_activity_summary($courseid, $selected_quizid, $selected_questionid, $student_ids) : [];
    $enriched_activities = context_service::enrich_student_activity($raw_activities, $courseid);
    $templatedata['student_activities'] = $enriched_activities;
    $templatedata['student_activity_rows'] = $enriched_activities;
    $templatedata['has_student_activities'] = !empty($enriched_activities);
    $templatedata['student_activities_count'] = count($enriched_activities);

    $total_active_students = count($enriched_activities);
    $total_student_runs = 0;
    $total_student_contribs = 0;
    $total_student_approved = 0;
    $total_student_stars = 0;
    foreach ($enriched_activities as $act) {
        $total_student_runs += (int)($act['total_runs'] ?? 0);
        $total_student_contribs += (int)($act['total_contributions'] ?? 0);
        $total_student_approved += (int)($act['approved_contributions'] ?? 0);
        $total_student_stars += (int)($act['total_stars'] ?? 0);
    }
    $templatedata['activity_stats'] = [
        'active_students' => $total_active_students,
        'total_runs' => $total_student_runs,
        'total_contributions' => $total_student_contribs,
        'total_approved' => $total_student_approved,
        'total_stars' => $total_student_stars,
    ];

    $templatedata['export_activity_csv_url'] = (new moodle_url('/local/testcase_exchange/export.php', [
        'course' => $courseid, 'quiz' => $selected_quizid, 'format' => 'csv',
    ]))->out(false);
    $templatedata['export_activity_code_csv_url'] = (new moodle_url('/local/testcase_exchange/export.php', [
        'course' => $courseid, 'quiz' => $selected_quizid, 'format' => 'csv', 'include_code' => 1,
    ]))->out(false);
    $templatedata['export_activity_json_url'] = (new moodle_url('/local/testcase_exchange/export.php', [
        'course' => $courseid, 'quiz' => $selected_quizid, 'format' => 'json', 'include_code' => 1,
    ]))->out(false);

    // Tab 5: Policy configuration (exact backend enum alignment)
    $quizsettings = $service && $selected_quizid > 0 ? $service->quiz_settings($selected_quizid) : [];
    $qpolicy = $service && $selected_quizid > 0 && $selected_questionid > 0 ?
        $service->question_policy($selected_quizid, $selected_questionid, $coderunnertype) : [];
    $templatedata['policy'] = [
        'target_quiz_id' => $selected_quizid,
        'target_question_id' => $selected_questionid,
        'enabled' => !empty($quizsettings['enabled']),
        'review_mode_teacher' => (($quizsettings['review_mode'] ?? 'teacher') === 'teacher'),
        'review_mode_auto' => (($quizsettings['review_mode'] ?? '') === 'auto'),
        'reward_policy_one_for_one' => (($quizsettings['reward_policy'] ?? 'one_for_one') === 'one_for_one'),
        'reward_policy_disabled' => (($quizsettings['reward_policy'] ?? '') === 'disabled'),
        'max_runs_per_minute' => $quizsettings['max_runs_per_minute'] ?? 10,
        'max_input_bytes' => $quizsettings['max_input_bytes'] ?? 8192,
        'norm_trim' => (($qpolicy['normalization_mode'] ?? 'trim') === 'trim'),
        'norm_raw' => (($qpolicy['normalization_mode'] ?? '') === 'raw'),
        'show_oracle_output' => !empty($quizsettings['show_oracle_output']),
        'leaderboard_enabled' => !empty($quizsettings['leaderboard_enabled']),
    ];
}

// =========================================================================
// STUDENT VIEW DATA PREPARATION (Solving, Playground, History, Rewards)
// =========================================================================
if ($is_student_view) {
    $student_quizid = !empty($selectedquestion) ? (int) $selectedquestion->quizid : (int) $selected_quizid;
    $quizsettings = ($service && $student_quizid > 0) ? $service->quiz_settings($student_quizid) : [];
    $runs = $service ? $service->runs_for_user($courseid, (int) $USER->id) : [];
    $rawcontributions = $service ? $service->contributions_for_user($courseid, (int) $USER->id) : [];
    $contributions = !empty($rawcontributions) ?
        context_service::enrich_contributions($rawcontributions, $courseid) : [];
    $rewards = $service ? $service->rewards_for_user($courseid, (int) $USER->id) : [];
    $contributionbyrun = [];
    $user_contrib_by_run_id = [];
    $user_contrib_by_exact_io = [];
    $user_contrib_by_input = [];
    foreach ($contributions as $contribution) {
        $run_id_val = (int) ($contribution['run_id'] ?? 0);
        if ($run_id_val > 0) {
            $user_contrib_by_run_id[$run_id_val] = $contribution;
            $contributionbyrun[$run_id_val] = $contribution;
        }
        $fp = (string) ($contribution['input_fingerprint'] ?? '');
        if ($fp !== '') {
            $qid = (int) $contribution['question_id'];
            $exp = trim((string) ($contribution['expected_output'] ?? ''));
            $exact_key = $qid . ':' . $fp . ':' . md5($exp);
            $input_key = $qid . ':' . $fp;

            if (!isset($user_contrib_by_exact_io[$exact_key])) {
                $user_contrib_by_exact_io[$exact_key] = $contribution;
            } else if (($user_contrib_by_exact_io[$exact_key]['status'] ?? '') === 'duplicate' && ($contribution['status'] ?? '') !== 'duplicate') {
                $user_contrib_by_exact_io[$exact_key] = $contribution;
            }

            if (!isset($user_contrib_by_input[$input_key])) {
                $user_contrib_by_input[$input_key] = $contribution;
            } else if (($user_contrib_by_input[$input_key]['status'] ?? '') === 'duplicate' && ($contribution['status'] ?? '') !== 'duplicate') {
                $user_contrib_by_input[$input_key] = $contribution;
            }
        }
    }
    $existingfingerprints = $service ? $service->existing_fingerprints_for_course($courseid) : [];

    $lasttestinput = '';
    $lastpredictedoutput = '';
    $lastpurpose = '';
    $lastcategory = 'normal';
    $coderevisions = [];

    if ($selectedquestion && $service) {
        $latestrunctx = $service->get_latest_student_run_context(
            $courseid,
            (int) $selectedquestion->quizid,
            (int) $selectedquestion->questionid,
            (int) $USER->id
        );
        if ($latestrunctx) {
            if ($studentcode === '') {
                $studentcode = $latestrunctx['revision_code'] ?? ($latestrunctx['student_code_snapshot'] ?? '');
            }
            $lasttestinput = $latestrunctx['input_raw'] ?? '';
            $lastpredictedoutput = $latestrunctx['predicted_output'] ?? '';
            $lastpurpose = $latestrunctx['purpose'] ?? '';
            $lastcategory = $latestrunctx['category'] ?? 'normal';
        }

        $studentcode = testcase_service::sanitize_multiline_text($studentcode);
        $lasttestinput = testcase_service::sanitize_multiline_text($lasttestinput);
        $lastpredictedoutput = testcase_service::sanitize_multiline_text($lastpredictedoutput);

        $coderevisions = $service->code_revisions_summary(
            $courseid,
            (int) $selectedquestion->quizid,
            (int) $selectedquestion->questionid,
            (int) $USER->id
        );
    }

    // Step 1: Pre-evaluate individual run metrics & identify newest eligible run per duplicate group
    $group_rep_run_id = []; // $fp_key => ID of newest eligible run
    foreach ($runs as &$run) {
        $oracle_valid = ($run['oracle_outcome'] === 'success');
        $run['oracle_valid'] = $oracle_valid;
        $student_out = trim((string) ($run['student_run_output'] ?? ''));
        $predicted_out = trim((string) ($run['predicted_output'] ?? ''));
        $oracle_out = trim((string) ($run['oracle_output'] ?? ''));

        // Never disclose reference-solution output in the student workflow.
        // Reward testcases are prepared separately and remain the only student-facing source of it.
        $run['show_oracle'] = false;
        $run['oracle_output'] = '';

        $run['has_student_output'] = ($student_out !== '');
        $run['student_matches_expected'] = ($student_out !== '' && $student_out === $predicted_out);
        $run['student_differs_expected'] = ($student_out !== '' && $student_out !== $predicted_out);

        // Expected matches solution binary truth
        $testcase_valid = ($oracle_valid && $predicted_out !== '' && $predicted_out === $oracle_out);
        $run['expected_matches_oracle'] = $testcase_valid;

        $is_eligible = ($cancontribute && $testcase_valid);
        $run['is_eligible'] = $is_eligible;

        $run_fp = (string) ($run['input_fingerprint'] ?? '');
        $fp_key = (int) $run['question_id'] . ':' . $run_fp;
        $run['group_key'] = $fp_key;

        // $runs is sorted DESC by id, so first eligible run seen in this group is the latest eligible run
        if ($is_eligible && $run_fp !== '' && !isset($group_rep_run_id[$fp_key])) {
            $group_rep_run_id[$fp_key] = (int) $run['id'];
        }
    }
    unset($run);

    // Step 2: Presentation decoration adhering to duplicate grouping & contribution gates
    $run_count_total = count($runs);
    $run_idx = 0;
    foreach ($runs as &$run) {
        $run_idx++;
        $run['run_index'] = $run_idx;
        $run['is_extra_run'] = ($run_idx > 5);

        $run_id = (int) $run['id'];
        $fp_key = $run['group_key'];
        $run_fp = (string) ($run['input_fingerprint'] ?? '');
        $run_exp = trim((string) ($run['predicted_output'] ?? ''));
        $run_io_key = (int) $run['question_id'] . ':' . $run_fp . ':' . md5($run_exp);

        $direct_contrib = null;
        if (isset($user_contrib_by_run_id[$run_id])) {
            $direct_contrib = $user_contrib_by_run_id[$run_id];
        } else if ($run['is_eligible'] && isset($user_contrib_by_exact_io[$run_io_key])) {
            $direct_contrib = $user_contrib_by_exact_io[$run_io_key];
        }

        $earlier_input_contrib = null;
        if (!$direct_contrib && isset($user_contrib_by_input[$fp_key])) {
            $earlier_input_contrib = $user_contrib_by_input[$fp_key];
        }

        $existing_canonical = ($run['input_fingerprint'] !== '' && isset($existingfingerprints[$fp_key])) ? $existingfingerprints[$fp_key] : null;
        $rep_id = $group_rep_run_id[$fp_key] ?? null;

        $run['can_contribute'] = false;
        $run['is_older_duplicate'] = false;
        $run['rep_id'] = null;
        $run['has_eligible_peer'] = false;
        $run['pending_code_fix'] = false;
        $run['has_user_contrib'] = false;
        $run['contrib_id'] = null;
        $run['contrib_status'] = null;
        $run['earlier_contrib_notice'] = null;

        // 1. If this run itself matches an already submitted contribution
        if ($direct_contrib) {
            $run['has_user_contrib'] = true;
            $run['contrib_id'] = (int) $direct_contrib['id'];
            $st = (string) $direct_contrib['status'];
            $run['contrib_status'] = $st;

            if ($st === 'submitted') {
                $run['display_status'] = 'Chờ duyệt';
                $run['display_status_class'] = 'badge badge-info';
            } else if ($st === 'approved') {
                $run['display_status'] = 'Đóng góp đã được duyệt';
                $run['display_status_class'] = 'badge badge-success';
            } else if ($st === 'rejected') {
                $comment = !empty($direct_contrib['teacher_comment']) ? ': ' . $direct_contrib['teacher_comment'] : '';
                $run['display_status'] = 'Đóng góp chưa được chấp thuận' . $comment;
                $run['display_status_class'] = 'badge badge-danger';
            } else if ($st === 'needs_explanation') {
                $run['display_status'] = 'Đóng góp cần giải trình thêm';
                $run['display_status_class'] = 'badge badge-warning text-dark';
            } else {
                $status_lbl = $direct_contrib['status_label'] ?? $st;
                $run['display_status'] = 'Đã đóng góp #' . $direct_contrib['id'] . ' (' . $status_lbl . ')';
                $run['display_status_class'] = 'badge badge-info';
            }
        }
        // 2. Ineligible run due to Oracle error or Expected Mismatch against Oracle
        // IMPORTANT: Must be evaluated BEFORE bank duplicate checks to prevent misclassifying wrong predictions as duplicate testcases
        else if (!$run['oracle_valid']) {
            $run['display_status'] = 'Lỗi thực thi đề bài';
            $run['display_status_class'] = 'badge badge-danger';
        }
        else if (!$run['expected_matches_oracle']) {
            $run['display_status'] = 'Kết quả kỳ vọng không khớp';
            $run['display_status_class'] = 'badge badge-warning text-dark';
        }
        // 3. Duplicate against teacher seeds or peer bank (Only for runs with valid expected output)
        else if ($existing_canonical && $existing_canonical['type'] === 'seed') {
            $run['display_status'] = 'Trùng testcase mẫu công khai của đề bài';
            $run['display_status_class'] = 'badge badge-secondary';
        }
        else if ($earlier_input_contrib) {
            $run['display_status'] = 'Bạn đã gửi đóng góp cùng đầu vào (#' . (int) $earlier_input_contrib['id'] . ')';
            $run['display_status_class'] = 'badge badge-secondary';
        }
        else if ($existing_canonical) {
            $run['display_status'] = 'Đã có trong kho';
            $run['display_status_class'] = 'badge badge-secondary';
        }
        // 4. Unclaimed: Latest eligible run in group receives the contribution proposal button
        else if ($rep_id !== null && $run_id === $rep_id) {
            $run['can_contribute'] = true;
            $run['display_status'] = 'Đủ điều kiện đóng góp';
            $run['display_status_class'] = 'badge badge-success';
        }
        // 5. Unclaimed: Older eligible run in same group links to representative run
        else if ($run['is_eligible']) {
            $run['is_older_duplicate'] = true;
            $run['rep_id'] = $rep_id;
            $run['display_status'] = 'Trùng đầu vào ở lượt #' . $rep_id;
            $run['display_status_class'] = 'badge badge-secondary';
        }
        // 6. Other Ineligible
        else {
            $run['display_status'] = 'Chưa đủ điều kiện đóng góp';
            $run['display_status_class'] = 'badge badge-secondary';
        }

        // Informative note when user contributed an earlier run with the same input
        if (!$direct_contrib && $earlier_input_contrib) {
            $earlier_run_id = (int) ($earlier_input_contrib['run_id'] ?? 0);
            $earlier_exp = trim((string) ($earlier_input_contrib['expected_output'] ?? ''));
            $run['earlier_contrib_notice'] = get_string(
                'earlier_contrib_notice_text',
                'local_testcase_exchange',
                (object)['run_id' => $earlier_run_id, 'expected' => $earlier_exp]
            );
        }

        // Run outcome display badge (independent from contribution status)
        if (!$run['oracle_valid']) {
            $run['run_outcome_badge'] = 'badge badge-danger';
            $run['run_outcome_label'] = 'Lỗi thực thi';
        } else if (!$run['expected_matches_oracle']) {
            $run['run_outcome_badge'] = 'badge badge-warning text-dark';
            $run['run_outcome_label'] = 'Không khớp Solution';
        } else {
            $run['run_outcome_badge'] = 'badge badge-success';
            $run['run_outcome_label'] = 'Khớp';
        }

        $run['has_revision'] = !empty($run['revision_step']);
        $run['revision_badge'] = !empty($run['revision_step']) ? 'Code v' . $run['revision_step'] : null;

        // Định dạng thời gian tạo lượt chạy: ưu tiên timecreated int, không hiển thị 01/01/1970 nếu thiếu.
        $createdtimestamp = !empty($run['timecreated']) ? (int) $run['timecreated'] : (!empty($run['created_at']) ? strtotime($run['created_at']) : 0);
        if ($createdtimestamp > 0) {
            $run['created_at_formatted'] = userdate($createdtimestamp, get_string('strftimedatetimeshort', 'core_langconfig'));
        } else {
            $run['created_at_formatted'] = get_string('noinfo', 'local_testcase_exchange');
        }
        $run['has_code_snapshot'] = !empty($run['student_code_snapshot']);
    }
    unset($run);

    // Tách kết quả lần chạy mới nhất (Latest Run) phục vụ khối hiển thị phản hồi tức thì
    // Đảm bảo 2 nhóm thông tin ĐỘC LẬP: Kết quả chạy vs Trạng thái đóng góp
    $latestrun = null;
    $target_run = null;
    if (!empty($selectedquestion)) {
        foreach ($runs as $r) {
            if ((int) $r['question_id'] === (int) $selectedquestion->questionid) {
                $target_run = $r;
                break;
            }
        }
    }
    if ($target_run === null && !empty($runs)) {
        $target_run = $runs[0];
    }

    if ($target_run !== null) {
        $first = $target_run;
        $fp_key = $first['group_key'];
        $first_fp = (string) ($first['input_fingerprint'] ?? '');
        $first_exp = trim((string) ($first['predicted_output'] ?? ''));
        $first_io_key = (int) $first['question_id'] . ':' . $first_fp . ':' . md5($first_exp);

        $direct_contrib = null;
        $first_id = (int) $first['id'];
        if (isset($user_contrib_by_run_id[$first_id])) {
            $direct_contrib = $user_contrib_by_run_id[$first_id];
        } else if ($first['is_eligible'] && isset($user_contrib_by_exact_io[$first_io_key])) {
            $direct_contrib = $user_contrib_by_exact_io[$first_io_key];
        }

        $earlier_input_contrib = null;
        if (!$direct_contrib && isset($user_contrib_by_input[$fp_key])) {
            $earlier_input_contrib = $user_contrib_by_input[$fp_key];
        }
        $existing_canonical = ($first['input_fingerprint'] !== '' && isset($existingfingerprints[$fp_key])) ? $existingfingerprints[$fp_key] : null;

        // 1. NHÓM THÔNG TIN 1: KẾT QUẢ CHẠY THỬ
        $run_res_title = '';
        $run_res_guidance = '';
        $run_res_badge = '';
        $run_res_icon = '';

        if (!$first['oracle_valid']) {
            $run_res_title = 'Lỗi thực thi / cú pháp với đề bài';
            $run_res_guidance = 'Testcase gây ra lỗi thực thi hoặc không phù hợp với quy ước của câu hỏi. Vui lòng xem chi tiết lỗi kỹ thuật bên dưới.';
            $run_res_badge = 'badge badge-danger';
            $run_res_icon = 'fa-times-circle text-danger';
        } else if (!$first['expected_matches_oracle']) {
            $run_res_title = 'Kết quả kỳ vọng không khớp lời giải tham chiếu';
            $run_res_guidance = 'Kết quả kỳ vọng bạn dự đoán không khớp với kết quả từ lời giải tham chiếu. Vui lòng kiểm tra lại đề bài, dữ liệu đầu vào và kết quả kỳ vọng.';
            $run_res_badge = 'badge badge-warning text-dark';
            $run_res_icon = 'fa-exclamation-triangle text-warning';
        } else if (!$first['expected_matches_oracle']) {
            $run_res_title = 'Kết quả kỳ vọng không khớp Lời giải mẫu';
            $run_res_guidance = 'Kết quả kỳ vọng bạn dự đoán không trùng khớp với kết quả từ Lời giải mẫu (Solution) của Thầy/Cô. Vui lòng kiểm tra lại dữ liệu đầu vào và kết quả kỳ vọng.';
            $run_res_badge = 'badge badge-warning text-dark';
            $run_res_icon = 'fa-exclamation-triangle text-warning';
        } else {
            $run_res_title = 'Khớp chính xác với Lời giải mẫu';
            $run_res_guidance = 'Kết quả kỳ vọng của bạn hoàn toàn trùng khớp với kết quả thực thi từ Lời giải mẫu (Solution) của Thầy/Cô.';
            $run_res_badge = 'badge badge-success';
            $run_res_icon = 'fa-check-circle text-success';
        }

        // 2. NHÓM THÔNG TIN 2: TRẠNG THÁI ĐÓNG GÓP
        $cancontributelatest = false;
        $hascontriblink = false;
        $contribid = null;
        $contrib_title = '';
        $contrib_badge = '';
        $contrib_guidance = '';
        $contrib_icon = '';
        $latest_earlier_notice = null;

        // Priority 1: User's own contribution already submitted for this exact run or testcase
        if ($direct_contrib) {
            $cancontributelatest = false;
            $hascontriblink = true;
            $contribid = (int) $direct_contrib['id'];
            $st = (string) $direct_contrib['status'];

            if ($st === 'submitted') {
                $contrib_title = 'Chờ duyệt';
                $contrib_badge = 'badge badge-info';
                $contrib_icon = 'fa-hourglass-half text-info';
                $contrib_guidance = 'Bạn đã gửi đóng góp testcase này thành công (Mã #' . $contribid . '). Vui lòng theo dõi tiến độ duyệt tại tab "Đóng góp của tôi".';
            } else if ($st === 'approved') {
                $contrib_title = 'Đóng góp đã được duyệt';
                $contrib_badge = 'badge badge-success';
                $contrib_icon = 'fa-check-circle text-success';
                $contrib_guidance = 'Đóng góp của bạn đã được giảng viên phê duyệt và bổ sung vào ngân hàng testcase chung của lớp học!';
            } else if ($st === 'rejected') {
                $comment = !empty($direct_contrib['teacher_comment']) ? ' Nhận xét: ' . $direct_contrib['teacher_comment'] : '';
                $contrib_title = 'Đóng góp chưa được chấp thuận';
                $contrib_badge = 'badge badge-danger';
                $contrib_icon = 'fa-times-circle text-danger';
                $contrib_guidance = 'Đóng góp chưa được chấp thuận.' . $comment;
            } else if ($st === 'needs_explanation') {
                $contrib_title = 'Đóng góp cần giải trình thêm';
                $contrib_badge = 'badge badge-warning text-dark';
                $contrib_icon = 'fa-question-circle text-warning';
                $contrib_guidance = 'Giảng viên đã yêu cầu bạn bổ sung giải trình về tính độc đáo của testcase tại tab "Đóng góp của tôi".';
            } else {
                $contrib_title = 'Đã đóng góp #' . $contribid;
                $contrib_badge = 'badge badge-info';
                $contrib_icon = 'fa-check text-info';
                $contrib_guidance = 'Đóng góp #' . $contribid . ' đang được ghi nhận trên hệ thống.';
            }
        }
        // Priority 2: Ineligible run due to Oracle error or Expected Output mismatch against reference solution
        // Must be evaluated BEFORE bank duplicate checks to prevent misclassifying incorrect predictions as duplicates
        else if (!$first['oracle_valid']) {
            $cancontributelatest = false;
            $contrib_title = 'Chưa đủ điều kiện đóng góp';
            $contrib_badge = 'badge badge-danger';
            $contrib_icon = 'fa-times-circle text-danger';
            $contrib_guidance = 'Chưa đủ điều kiện đóng góp: testcase gây lỗi thực thi hoặc cú pháp đối với đề bài.';
        }
        else if (!$first['expected_matches_oracle']) {
            $cancontributelatest = false;
            $contrib_title = 'Chưa đủ điều kiện đóng góp';
            $contrib_badge = 'badge badge-warning text-dark';
            $contrib_icon = 'fa-exclamation-triangle text-warning';
            $contrib_guidance = 'Chưa đủ điều kiện đóng góp: kết quả kỳ vọng không khớp lời giải tham chiếu.';
        }
        // Priority 3: Unclaimed & fully eligible
        else if ($first['can_contribute']) {
            $cancontributelatest = true;
            $contrib_title = 'Đủ điều kiện đóng góp';
            $contrib_badge = 'badge badge-success';
            $contrib_icon = 'fa-paper-plane text-success';
            $contrib_guidance = 'Testcase đã khớp chính xác với Lời giải mẫu của Thầy/Cô. Bạn đủ điều kiện gửi đóng góp testcase này cho lớp học!';
        }
        // Priority 4: Duplicate against seed testcase
        else if ($existing_canonical && $existing_canonical['type'] === 'seed') {
            $cancontributelatest = false;
            $contrib_title = 'Trùng testcase mẫu công khai của đề bài';
            $contrib_badge = 'badge badge-secondary';
            $contrib_icon = 'fa-ban text-secondary';
            $contrib_guidance = 'Testcase này trùng với testcase mẫu trong đề thi hoặc kho hạt giống của giáo viên.';
        }
        // Priority 5: User already contributed this input in an earlier run
        else if ($earlier_input_contrib) {
            $cancontributelatest = false;
            $contrib_title = 'Bạn đã đóng góp testcase cùng đầu vào';
            $contrib_badge = 'badge badge-secondary';
            $contrib_icon = 'fa-check-double text-secondary';
            $contrib_guidance = 'Bạn đã gửi đóng góp testcase có cùng đầu vào ở lượt #' . (int) ($earlier_input_contrib['run_id'] ?? $earlier_input_contrib['id']) . '. Bạn có thể theo dõi tiến độ duyệt tại tab "Đóng góp của tôi".';
        }
        // Priority 6: Duplicate against another student's contribution in peer bank
        else if ($existing_canonical) {
            $cancontributelatest = false;
            $contrib_title = 'Đã có trong kho';
            $contrib_badge = 'badge badge-secondary';
            $contrib_icon = 'fa-ban text-secondary';
            $contrib_guidance = 'Testcase này đã được sinh viên khác đóng góp trước đó trong kho chung của lớp.';
        }
        // Priority 7: Older duplicate of own run
        else if ($first['is_older_duplicate']) {
            $cancontributelatest = false;
            $contrib_title = 'Trùng đầu vào ở lượt #' . $first['rep_id'];
            $contrib_badge = 'badge badge-secondary';
            $contrib_icon = 'fa-copy text-secondary';
            $contrib_guidance = 'Đầu vào này đã từng được chạy và đủ điều kiện ở lượt #' . $first['rep_id'] . '. Bạn có thể chuyển tới lượt đạt để đóng góp.';
        }
        // Priority 8: Other Ineligible
        else {
            $cancontributelatest = false;
            $contrib_title = 'Chưa đủ điều kiện đóng góp';
            $contrib_badge = 'badge badge-secondary';
            $contrib_icon = 'fa-info-circle text-muted';
            $contrib_guidance = 'Testcase chưa thỏa mãn toàn bộ các điều kiện để đề xuất đóng góp. Vui lòng kiểm tra lại kết quả đối chiếu.';
        }

        // Informative note when user contributed an earlier run with the same input
        if (!$direct_contrib && $earlier_input_contrib) {
            $earlier_run_id = (int) ($earlier_input_contrib['run_id'] ?? 0);
            $earlier_exp = trim((string) ($earlier_input_contrib['expected_output'] ?? ''));
            $latest_earlier_notice = get_string(
                'earlier_contrib_notice_text',
                'local_testcase_exchange',
                (object)['run_id' => $earlier_run_id, 'expected' => $earlier_exp]
            );
        }

        $latestrun = array_merge($first, [
            // Run outcome fields
            'run_result_title' => $run_res_title,
            'run_result_guidance' => $run_res_guidance,
            'run_result_badge' => $run_res_badge,
            'run_result_icon' => $run_res_icon,
            // Contribution status fields
            'contrib_title' => $contrib_title,
            'contrib_badge' => $contrib_badge,
            'contrib_guidance' => $contrib_guidance,
            'contrib_icon' => $contrib_icon,
            'can_contribute_latest' => $cancontributelatest,
            'has_contrib_link' => $hascontriblink,
            'contrib_id' => $contribid,
            'earlier_contrib_notice' => $latest_earlier_notice,
        ]);
    }
    $templatedata['latest_run'] = $latestrun;
    $templatedata['has_latest_run'] = ($latestrun !== null);

    foreach ($contributions as &$contribution) {
        $contribution['can_explain'] = $cancontribute && $contribution['status'] === 'needs_explanation';
    }
    unset($contribution);

    $viewcategories = [];
    foreach (['normal', 'boundary', 'empty', 'invalid', 'large', 'branch', 'other'] as $category) {
        $viewcategories[] = [
            'value' => $category,
            'label' => get_string('category_' . $category, 'local_testcase_exchange'),
            'selected' => ($category === $lastcategory),
        ];
    }

    $templatedata['categories'] = $viewcategories;
    $templatedata['student_code'] = $studentcode;
    $templatedata['last_test_input'] = $lasttestinput;
    $templatedata['last_predicted_output'] = $lastpredictedoutput;
    $templatedata['last_purpose'] = $lastpurpose;
    $templatedata['code_revisions'] = $coderevisions;
    $templatedata['has_code_revisions'] = !empty($coderevisions);
    $templatedata['code_revisions_count'] = count($coderevisions);
    $templatedata['has_prefilled_code'] = ($studentcode !== '');
    $templatedata['runs'] = $runs;
    $templatedata['run_count'] = count($runs);
    $templatedata['has_extra_runs'] = (count($runs) > 5);
    $templatedata['extra_runs_count'] = max(0, count($runs) - 5);

    // Calculate detailed statistics for Tab 2: My Contributions
    $contrib_total = count($contributions);
    $contrib_approved = 0;
    $contrib_pending = 0;
    $contrib_rejected = 0;
    $contrib_needs_explanation = 0;
    $contrib_stars = 0;
    foreach ($contributions as $cb) {
        $st = $cb['status'] ?? '';
        if ($st === 'approved') {
            $contrib_approved++;
            $contrib_stars += (int)($cb['rating_val'] ?? 0);
        } else if ($st === 'pending') {
            $contrib_pending++;
        } else if ($st === 'rejected') {
            $contrib_rejected++;
        } else if ($st === 'needs_explanation') {
            $contrib_needs_explanation++;
        }
    }
    $templatedata['contributions'] = $contributions;
    $templatedata['contribution_count'] = $contrib_total;
    $templatedata['contrib_stats'] = [
        'total' => $contrib_total,
        'approved' => $contrib_approved,
        'pending' => $contrib_pending,
        'rejected' => $contrib_rejected,
        'needs_explanation' => $contrib_needs_explanation,
        'total_stars' => $contrib_stars,
        'approval_rate' => $contrib_total > 0 ? round(($contrib_approved / $contrib_total) * 100) : 0,
        'has_contributions' => ($contrib_total > 0),
    ];

    // Calculate detailed statistics and enrich items for Tab 3: Reward Center
    $unclaimedrewards = 0;
    $claimedrewards = 0;
    $seedrewards = 0;
    $peerrewards = 0;
    foreach ($rewards as &$rw) {
        $is_claimed = !empty($rw['is_claimed_bool']);
        $rw['is_claimed'] = $is_claimed;
        $rw['is_unclaimed'] = !$is_claimed;
        if ($is_claimed) {
            $claimedrewards++;
        } else {
            $unclaimedrewards++;
        }

        $source = $rw['reward_source'] ?? '';
        if ($source === 'seed') {
            $seedrewards++;
            $rw['source_is_seed'] = true;
            $rw['source_is_peer'] = false;
            $rw['source_badge_text'] = 'Ngân hàng mầm (Thầy/Cô)';
            $rw['source_badge_class'] = 'badge-primary';
            $rw['source_icon'] = 'fa-university';
        } else {
            $peerrewards++;
            $rw['source_is_seed'] = false;
            $rw['source_is_peer'] = true;
            $rw['source_badge_text'] = 'Đóng góp xuất sắc từ Bạn học';
            $rw['source_badge_class'] = 'badge-success';
            $rw['source_icon'] = 'fa-users';
        }

        $cat = $rw['category'] ?? 'normal';
        $rw['category_label'] = get_string_manager()->string_exists('category_' . $cat, 'local_testcase_exchange') ?
            get_string('category_' . $cat, 'local_testcase_exchange') : ucfirst($cat);
        $rw['formatted_created'] = !empty($rw['timecreated']) ? userdate($rw['timecreated'], '%d/%m/%Y %H:%M') : '';
        $rw['formatted_claimed'] = !empty($rw['claimed_at']) ? userdate($rw['claimed_at'], '%d/%m/%Y %H:%M') : '';
    }
    unset($rw);

    $total_rw = count($rewards);
    $templatedata['rewards'] = $rewards;
    $templatedata['reward_count'] = $total_rw;
    $templatedata['unclaimed_rewards_count'] = (string) $unclaimedrewards;
    $templatedata['claimed_rewards_count'] = (string) $claimedrewards;
    $templatedata['total_rewards_count'] = (string) $total_rw;
    $templatedata['has_unclaimed_rewards'] = ($unclaimedrewards > 0);
    $templatedata['has_rewards'] = ($total_rw > 0);
    $templatedata['reward_stats'] = [
        'total' => $total_rw,
        'claimed' => $claimedrewards,
        'unclaimed' => $unclaimedrewards,
        'from_seeds' => $seedrewards,
        'from_peers' => $peerrewards,
        'claim_rate' => $total_rw > 0 ? round(($claimedrewards / $total_rw) * 100) : 0,
        'has_rewards' => ($total_rw > 0),
        'has_unclaimed' => ($unclaimedrewards > 0),
    ];

    // Calculate detailed statistics and podium for Tab 4: Leaderboard
    $leaderboarddata = $service ? $service->course_leaderboard($courseid, (int) $USER->id) : [
        'leaderboard' => [], 'my_rank' => 0, 'my_stars' => 0, 'my_count' => 0,
        'top_user_name' => '', 'top_stars' => 0, 'top_count' => 0, 'has_leaderboard' => false,
    ];

    $lb_list = $leaderboarddata['leaderboard'] ?? [];
    $podium_first = null;
    $podium_second = null;
    $podium_third = null;

    foreach ($lb_list as &$lb_entry) {
        $rk = (int)($lb_entry['rank'] ?? 0);
        $is_me = !empty($lb_entry['is_current_user']) || ((int)($lb_entry['user_id'] ?? 0) === (int)$USER->id);
        $lb_entry['is_me'] = $is_me;
        $lb_entry['rank_1'] = ($rk === 1);
        $lb_entry['rank_2'] = ($rk === 2);
        $lb_entry['rank_3'] = ($rk === 3);
        $lb_entry['rank_other'] = ($rk > 3);

        if ($rk === 1 && !$podium_first) {
            $podium_first = $lb_entry;
        } else if ($rk === 2 && !$podium_second) {
            $podium_second = $lb_entry;
        } else if ($rk === 3 && !$podium_third) {
            $podium_third = $lb_entry;
        }
    }
    unset($lb_entry);
    $leaderboarddata['leaderboard'] = $lb_list;

    $templatedata = array_merge($templatedata, $leaderboarddata);
    $templatedata['has_leaderboard'] = !empty($lb_list);
    $templatedata['podium'] = [
        'first' => $podium_first,
        'second' => $podium_second,
        'third' => $podium_third,
        'has_first' => ($podium_first !== null),
        'has_second' => ($podium_second !== null),
        'has_third' => ($podium_third !== null),
    ];
    $templatedata['has_podium'] = ($podium_first !== null);

    // Tab: Published Seed Inputs & Duplicate Search
    $public_seeds = [];
    $raw_seeds = ($service && $selected_questionid > 0) ?
        $service->list_seed_testcases($courseid, $selected_quizid, $selected_questionid) : [];

    $idx = 0;
    foreach ($raw_seeds as $sd) {
        $idx++;
        $in_raw = trim((string) ($sd['input_raw'] ?? ''));
        if ($in_raw === '') {
            $in_raw = trim((string) ($sd['testcode'] ?? ($sd['stdin'] ?? '')));
        }
        $in_norm = \local_testcase_exchange\normalizer::normalize($in_raw, 'trim');
        $cat = (string) ($sd['category'] ?? 'normal');
        $cat_label = get_string_manager()->string_exists('category_' . $cat, 'local_testcase_exchange') ?
            get_string('category_' . $cat, 'local_testcase_exchange') : ucfirst($cat);

        $public_seeds[] = [
            'index' => $idx,
            'id' => (int) $sd['id'],
            'input' => $in_raw,
            'input_normalized' => $in_norm,
            'input_fingerprint' => \local_testcase_exchange\normalizer::fingerprint($in_norm),
            'category' => $cat,
            'category_label' => $cat_label,
            'formatted_created' => !empty($sd['timecreated']) ? userdate($sd['timecreated'], '%d/%m/%Y') : '',
        ];
    }

    $approved_peer_inputs = [];
    if ($service && $selected_questionid > 0) {
        $approved_rows = $DB->get_records_sql(
            'SELECT c.id, c.input_normalized, c.input_fingerprint, c.category, c.timecreated
               FROM {local_tce_contributions} c
              WHERE c.course_id = ? AND c.question_id = ? AND c.status = ?
              ORDER BY c.id ASC',
            [$courseid, $selected_questionid, 'approved']
        );
        $ap_idx = 0;
        foreach ($approved_rows as $ap) {
            $ap_idx++;
            $cat = (string) ($ap->category ?? 'normal');
            $cat_label = get_string_manager()->string_exists('category_' . $cat, 'local_testcase_exchange') ?
                get_string('category_' . $cat, 'local_testcase_exchange') : ucfirst($cat);
            $approved_peer_inputs[] = [
                'index' => $ap_idx,
                'id' => (int) $ap->id,
                'input' => $ap->input_normalized,
                'input_normalized' => $ap->input_normalized,
                'input_fingerprint' => $ap->input_fingerprint,
                'category' => $cat,
                'category_label' => $cat_label,
                'formatted_created' => !empty($ap->timecreated) ? userdate($ap->timecreated, '%d/%m/%Y') : '',
            ];
        }
    }

    $all_known_for_search = [];
    foreach ($public_seeds as $s) {
        $all_known_for_search[] = [
            'id' => $s['id'],
            'type' => 'seed',
            'source_label' => 'Kho Seed',
            'badge_class' => 'badge-primary',
            'input' => $s['input'],
            'input_normalized' => $s['input_normalized'],
            'fingerprint' => $s['input_fingerprint'],
            'category_label' => $s['category_label'],
        ];
    }
    foreach ($approved_peer_inputs as $p) {
        $all_known_for_search[] = [
            'id' => $p['id'],
            'type' => 'peer',
            'source_label' => 'Đã duyệt từ bạn học',
            'badge_class' => 'badge-info',
            'input' => $p['input'],
            'input_normalized' => $p['input_normalized'],
            'fingerprint' => $p['input_fingerprint'],
            'category_label' => $p['category_label'],
        ];
    }

    $templatedata['public_seeds'] = $public_seeds;
    $templatedata['public_seeds_count'] = count($public_seeds);
    $templatedata['has_public_seeds'] = !empty($public_seeds);
    $templatedata['approved_peer_inputs'] = $approved_peer_inputs;
    $templatedata['approved_peer_inputs_count'] = count($approved_peer_inputs);
    $templatedata['has_approved_peer_inputs'] = !empty($approved_peer_inputs);
    $templatedata['total_known_inputs_count'] = count($all_known_for_search);
    $templatedata['all_known_inputs_json'] = json_encode($all_known_for_search, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

    $templatedata['export_timeline_csv_url'] = (new moodle_url('/local/testcase_exchange/export.php', [
        'course' => $courseid, 'quiz' => $selected_quizid, 'format' => 'csv',
    ]))->out(false);
    $templatedata['export_timeline_json_url'] = (new moodle_url('/local/testcase_exchange/export.php', [
        'course' => $courseid, 'quiz' => $selected_quizid, 'format' => 'json',
    ]))->out(false);
}

if ($selectedquestion) {
    $PAGE->requires->strings_for_js([
    'draft_saved_device',
    'draft_saved_device_justnow',
    'draft_restored_device',
    'draft_earlier',
    'running',
    'revision_loaded',
    'run_code_loaded',
    'testcase_cleared',
    'show_more_runs',
    'hide_older_runs',
    'collapse',
    'expand',
], 'local_testcase_exchange');

if ($PAGE->requires->should_create_one_time_item_now('local_testcase_exchange-ace-scripts')) {
        $PAGE->requires->js(new moodle_url('/local/testcase_exchange/ace/ace.js'));
        $PAGE->requires->js(new moodle_url('/local/testcase_exchange/ace/ext-language_tools.js'));
        $PAGE->requires->js(new moodle_url('/local/testcase_exchange/ace/ext-modelist.js'));
        $PAGE->requires->js(new moodle_url('/local/testcase_exchange/ace/ext-searchbox.js'));
    }
}

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_testcase_exchange/dashboard', $templatedata);
echo $OUTPUT->footer();
