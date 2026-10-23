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
 * Quiz and question policy page.
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
    redirect(new moodle_url('/local/testcase_exchange/index.php'));
}
$course = get_course($courseid);
$context = context_course::instance($courseid);
require_capability('local/testcase_exchange:manage', $context);

$PAGE->set_url(new moodle_url('/local/testcase_exchange/policy.php', ['course' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('managepolicies', 'local_testcase_exchange'));
$PAGE->set_heading($course->fullname . ' — ' . get_string('managepolicies', 'local_testcase_exchange'));

$connection = null;
try {
    $connection = external_database::connect();
    schema_manager::migrate($connection);
} catch (Throwable $e) {
    debugging($e->getMessage(), DEBUG_DEVELOPER);
    throw new moodle_exception('databaseunavailable', 'local_testcase_exchange');
}
$service = new testcase_service($connection);
$questions = context_service::questions_for_course($courseid);
$questionmap = [];
foreach ($questions as $question) {
    $questionmap[$question->quizid . ':' . $question->questionid] = $question;
}

$selection = optional_param('question', array_key_first($questionmap) ?? '', PARAM_RAW_TRIMMED);
$selected = $questionmap[$selection] ?? null;
if (data_submitted()) {
    require_sesskey();
    if (!$selected) {
        throw new moodle_exception('invalidcontext', 'local_testcase_exchange');
    }
    try {
        $action = optional_param('action', 'save_policy', PARAM_ALPHAEXT);
        if ($action === 'add_manual_seed') {
            $testcode = required_param('seed_testcode', PARAM_RAW);
            $stdin = optional_param('seed_stdin', '', PARAM_RAW);
            $expected = required_param('seed_expected', PARAM_RAW);
            $purpose = optional_param('seed_purpose', '', PARAM_TEXT);
            $service->add_manual_seed(
                $courseid,
                (int) $selected->quizid,
                (int) $selected->questionid,
                $testcode,
                $stdin,
                $expected,
                $purpose,
                'manual'
            );
            redirect(
                new moodle_url($PAGE->url, ['question' => $selection]),
                'Đã thêm testcase hạt giống thủ công thành công!',
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }

        if ($action === 'delete_seed') {
            $seedid = required_param('seed_id', PARAM_INT);
            $service->delete_seed_testcase($seedid, $courseid);
            redirect(
                new moodle_url($PAGE->url, ['question' => $selection]),
                'Đã xóa testcase hạt giống thành công!',
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }

        if ($action === 'import_xml_seeds') {
            if (!empty($_FILES['xml_file']['tmp_name']) && is_uploaded_file($_FILES['xml_file']['tmp_name'])) {
                $content = file_get_contents($_FILES['xml_file']['tmp_name']);
                $count = $service->import_seeds_from_xml_content(
                    $courseid,
                    (int) $selected->quizid,
                    (int) $selected->questionid,
                    $content
                );
                redirect(
                    new moodle_url($PAGE->url, ['question' => $selection]),
                    "Đã trích xuất và nhập thành công {$count} hạt giống từ file XML Moodle!",
                    null,
                    \core\output\notification::NOTIFY_SUCCESS
                );
            } else {
                throw new moodle_exception('nofile', 'local_testcase_exchange', '', 'Vui lòng chọn file XML hợp lệ!');
            }
        }

        if ($action === 'sync_seeds') {
            $count = $service->sync_question_testsuites($courseid, (int) $selected->quizid, (int) $selected->questionid);
            redirect(
                new moodle_url($PAGE->url, ['question' => $selection]),
                "Đã quét và đồng bộ thành công {$count} testcase hạt giống (Suite 2) từ câu hỏi vào Ngân hàng thưởng của môn học!",
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        } else if ($action === 'auto_partition') {
            // Auto partition legacy tests into 3 suites: Test 1 -> example, Test 2,3 -> seed reward (S), Test 4,5 -> HIDE
            $tests = $DB->get_records('question_coderunner_tests', ['questionid' => $selected->questionid], 'id ASC');
            $idx = 1;
            foreach ($tests as $t) {
                if ($idx === 1) {
                    $t->useasexample = 1;
                    $t->display = 'SHOW';
                    $t->extra = '';
                } else if ($idx <= 3) {
                    $t->useasexample = 0;
                    $t->display = 'HIDE';
                    $t->extra = 'S';
                } else {
                    $t->useasexample = 0;
                    $t->display = 'HIDE';
                    $t->extra = '';
                }
                $DB->update_record('question_coderunner_tests', $t);
                $idx++;
            }
            $synced = $service->sync_question_testsuites($courseid, (int) $selected->quizid, (int) $selected->questionid);
            redirect(
                new moodle_url($PAGE->url, ['question' => $selection]),
                "Đã tự động phân tách 3 bộ testcase cho câu hỏi (Test 1 làm Mẫu, Test 2-3 làm Thưởng, Test 4-5 làm Chấm điểm HIDE) và đồng bộ {$synced} ca vào ngân hàng thưởng!",
                null,
                \core\output\notification::NOTIFY_SUCCESS
            );
        }

        $service->save_quiz_settings((int) $selected->quizid, (int) $USER->id, [
            'enabled' => optional_param('enabled', 0, PARAM_BOOL),
            'review_mode' => required_param('review_mode', PARAM_ALPHA),
            'reward_policy' => required_param('reward_policy', PARAM_ALPHAEXT),
            'show_oracle_output' => optional_param('show_oracle_output', 0, PARAM_BOOL),
            'leaderboard_enabled' => optional_param('leaderboard_enabled', 0, PARAM_BOOL),
            'max_runs_per_minute' => required_param('max_runs_per_minute', PARAM_INT),
            'max_input_bytes' => required_param('max_input_bytes', PARAM_INT),
        ]);
        $service->save_question_policy((int) $selected->quizid, (int) $selected->questionid, (int) $USER->id, [
            'input_mode' => required_param('input_mode', PARAM_ALPHA),
            'testcode_template' => optional_param('testcode_template', '', PARAM_RAW),
            'normalization_mode' => required_param('normalization_mode', PARAM_ALPHA),
            'categories' => optional_param('categories', '', PARAM_TEXT),
        ]);
        redirect(
            new moodle_url($PAGE->url, ['question' => $selection]),
            get_string('policysaved', 'local_testcase_exchange'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    } catch (Throwable $e) {
        $message = $e instanceof moodle_exception ? $e->getMessage() :
            get_string('unexpectederror', 'local_testcase_exchange');
        if (!($e instanceof moodle_exception)) {
            debugging($e->getMessage(), DEBUG_DEVELOPER);
        }
        redirect(
            new moodle_url($PAGE->url, ['question' => $selection]),
            $message,
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }
}

$quizsettings = $selected ? $service->quiz_settings((int) $selected->quizid) : [];
$policy = $selected ? $service->question_policy(
    (int) $selected->quizid,
    (int) $selected->questionid,
    (string) $selected->coderunnertype
) : [];
$seedlist = $selected ? $service->list_seed_testcases($courseid, (int) $selected->quizid, (int) $selected->questionid) : [];

echo $OUTPUT->header();
echo html_writer::link(
    new moodle_url('/local/testcase_exchange/index.php', ['course' => $courseid]),
    get_string('backtodashboard', 'local_testcase_exchange'),
    ['class' => 'btn btn-secondary mb-3']
);
$viewquestions = [];
foreach ($questions as $question) {
    $value = $question->quizid . ':' . $question->questionid;
    $viewquestions[] = [
        'value' => $value,
        'label' => $question->quizname . ' — ' . $question->questionname,
        'selected' => $value === $selection,
    ];
}
$templatedata = [
    'courseid' => $courseid,
    'sesskey' => sesskey(),
    'selection' => $selection,
    'has_questions' => !empty($questions),
    'questions' => $viewquestions,
    'enabled' => !empty($quizsettings['enabled']),
    'show_oracle_output' => !empty($quizsettings['show_oracle_output']),
    'leaderboard_enabled' => !empty($quizsettings['leaderboard_enabled']),
    'review_teacher' => ($quizsettings['review_mode'] ?? '') === 'teacher',
    'review_auto' => ($quizsettings['review_mode'] ?? '') === 'auto',
    'reward_one_for_one' => ($quizsettings['reward_policy'] ?? '') === 'one_for_one',
    'reward_disabled' => ($quizsettings['reward_policy'] ?? '') === 'disabled',
    'max_runs_per_minute' => $quizsettings['max_runs_per_minute'] ?? 10,
    'max_input_bytes' => $quizsettings['max_input_bytes'] ?? 8192,
    'input_stdin' => ($policy['input_mode'] ?? '') === 'stdin',
    'input_testcode' => ($policy['input_mode'] ?? '') === 'testcode',
    'input_template' => ($policy['input_mode'] ?? '') === 'template',
    'testcode_template' => $policy['testcode_template'] ?? '',
    'normalization_raw' => ($policy['normalization_mode'] ?? '') === 'raw',
    'normalization_trim' => ($policy['normalization_mode'] ?? '') === 'trim',
    'normalization_json' => ($policy['normalization_mode'] ?? '') === 'json',
    'categories' => $policy['categories'] ?? '',
    'seeds' => $seedlist,
    'has_seeds' => !empty($seedlist),
    'seeds_count' => count($seedlist),
];
echo $OUTPUT->render_from_template('local_testcase_exchange/policy', $templatedata);
$connection->close();
echo $OUTPUT->footer();
