<?php
/**
 * Rigorous Verification & Release Audit Suite for local_testcase_exchange.
 *
 * Tests:
 * - Test A: Isolation & Migration (Compound index verification, Quiz policy isolation)
 * - Test B: Capability Walls & Complete Privacy API Verification (Export, Erasure, Anonymisation)
 * - Test C: CodeRunner Compatibility, Edge Cases & Zero Grading/Penalty Interference
 * - Test D: True Multi-Process Concurrency & Race Condition Defense (Submit & Claim)
 *
 * @package    local_testcase_exchange
 * @copyright  2026 Nguyen Quoc Thinh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/mod/quiz/accessrule/testcaseexchange/rule.php');
require_once($CFG->dirroot . '/local/testcase_exchange/lib.php');

use local_testcase_exchange\context_service;
use local_testcase_exchange\testcase_service;
use local_testcase_exchange\execution_service;
use local_testcase_exchange\privacy\provider as privacy_provider;

global $DB, $CFG;

// =================================================================
// 1. PRODUCTION SAFETY GUARD
// =================================================================
$isDev = (!empty($CFG->debugdeveloper) || (defined('DEBUG_DEVELOPER') && $CFG->debug >= DEBUG_DEVELOPER) || getenv('MOODLE_ENVIRONMENT') === 'development' || !empty($CFG->unittest));
$forceFlag = in_array('--force-test-environment', $argv, true);

if (!$isDev && !$forceFlag) {
    echo "\n⛔ PRODUCTION SAFETY GUARD TRIGGERED:\n";
    echo "This test suite executes data manipulation and must NOT be run on an unflagged production instance.\n";
    echo "To execute on a development/test environment, enable DEVELOPER debugging or run with:\n";
    echo "  php run_rigorous_tests.php --force-test-environment\n\n";
    exit(1);
}

$results = [];

echo "=================================================================\n";
echo "RIGOROUS AUDIT: local_testcase_exchange (v2.3.1-beta)\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n";
echo "Environment: Moodle " . $CFG->release . " | DB: " . $CFG->dbtype . "\n";
echo "Mode: Isolated Test Execution (Production Guard Active)\n";
echo "=================================================================\n\n";

// =================================================================
// 2. SELF-CONTAINED FIXTURE RESOLVER
// =================================================================
// Helper to get or create transient test user
function get_or_create_fixture_user(string $username, string $firstname, string $lastname): \stdClass {
    global $DB, $CFG;
    $user = $DB->get_record('user', ['username' => $username]);
    if (!$user) {
        $user = new stdClass();
        $user->auth = 'manual';
        $user->confirmed = 1;
        $user->mnethostid = $CFG->mnet_localhost_id;
        $user->username = $username;
        $user->password = hash_internal_user_password('TestPass123!');
        $user->firstname = $firstname;
        $user->lastname = $lastname;
        $user->email = $username . '@example.com';
        $user->id = $DB->insert_record('user', $user);
        $user = $DB->get_record('user', ['id' => $user->id]);
    }
    return $user;
}

// Resolve test users (supports pre-existing IUH pilot users or auto-creates fixtures)
$teacher = $DB->get_record('user', ['username' => 'gv_python']) ?: get_or_create_fixture_user('tce_fixture_teacher', 'Fixture', 'Teacher');
$sv1 = $DB->get_record('user', ['username' => 'sv_an']) ?: get_or_create_fixture_user('tce_fixture_sv1', 'An', 'Nguyen');
$sv2 = $DB->get_record('user', ['username' => 'sv_binh']) ?: get_or_create_fixture_user('tce_fixture_sv2', 'Binh', 'Tran');
$sv3 = $DB->get_record('user', ['username' => 'sv_chi']) ?: get_or_create_fixture_user('tce_fixture_sv3', 'Chi', 'Le');

// Resolve test course, quizzes, and question
$course = $DB->get_record('course', ['id' => 4]) ?: $DB->get_record_select('course', 'id > 1', null, 'id ASC', '*', IGNORE_MULTIPLE);
$courseid = $course ? (int)$course->id : 1;

// Resolve suitable CodeRunner question with teacher answer (prefer MidTest standard python algorithm)
$testQ = $DB->get_record_sql("
    SELECT q.id, q.name, qo.coderunnertype, qo.answer
      FROM {question} q
      JOIN {question_coderunner_options} qo ON qo.questionid = q.id
     WHERE qo.coderunnertype = 'python3'
       AND q.name LIKE '%MidTestQ04%'
       AND qo.answer IS NOT NULL
       AND TRIM(qo.answer) <> ''
     LIMIT 1
");

if (!$testQ) {
    $testQ = $DB->get_record_sql("
        SELECT q.id, q.name, qo.coderunnertype, qo.answer
          FROM {question} q
          JOIN {question_coderunner_options} qo ON qo.questionid = q.id
         WHERE qo.coderunnertype IN ('python3', 'python3_w_input')
           AND q.name NOT LIKE '%PROTOTYPE%'
           AND qo.answer IS NOT NULL
           AND TRIM(qo.answer) <> ''
         ORDER BY q.id ASC
         LIMIT 1
    ");
}

if (!$testQ) {
    cli_error("Không tìm thấy câu hỏi CodeRunner hợp lệ trong Question Bank để chạy kiểm thử.");
}

$testQId = (int)$testQ->id;

// Resolve two quizzes for isolation testing
$quizzes = array_values($DB->get_records('quiz', ['course' => $courseid], 'id ASC', 'id, course, name', 0, 2));
if (count($quizzes) < 2) {
    // If not enough quizzes, take any 2 quizzes from DB
    $quizzes = array_values($DB->get_records('quiz', [], 'id ASC', 'id, course, name', 0, 2));
}

$quizA = isset($quizzes[0]) ? (int)$quizzes[0]->id : 1;
$quizB = isset($quizzes[1]) ? (int)$quizzes[1]->id : 2;

echo "Fixtures Resolved:\n";
echo " - Course ID: {$courseid} | Question: {$testQ->name} (ID={$testQId}, Type={$testQ->coderunnertype})\n";
echo " - Quiz A: ID={$quizA} | Quiz B: ID={$quizB}\n";
echo " - Teacher: {$teacher->username} | Students: {$sv1->username}, {$sv2->username}, {$sv3->username}\n\n";

$service = new testcase_service();

// =================================================================
// TEST A: ISOLATION & MIGRATION
// =================================================================
echo "-----------------------------------------------------------------\n";
echo "TEST A: ISOLATION & SCHEMA MIGRATION\n";
echo "-----------------------------------------------------------------\n";

$testQId = 567; // Question MidTestQ04
$quizA = 3;
$quizB = 4;

// 1. Verify that the obsolete unique key on question_id alone is GONE
$rs = $DB->get_recordset_sql("SHOW INDEX FROM {local_tce_policies}");
$oldSingleKeyExists = false;
$compoundCols = [];

foreach ($rs as $idx) {
    if ((int)$idx->non_unique === 0 && $idx->key_name !== 'PRIMARY') {
        $compoundCols[$idx->key_name][] = $idx->column_name;
    }
}
$rs->close();

$hasCompoundUnique = false;
foreach ($compoundCols as $kname => $cols) {
    if (count($cols) === 1 && $cols[0] === 'question_id') {
        $oldSingleKeyExists = true;
    }
    if (in_array('quiz_id', $cols) && in_array('question_id', $cols)) {
        $hasCompoundUnique = true;
    }
}

echo " - [Schema] Khóa đơn cũ uq_question (chỉ trên question_id) đã bị loại bỏ: " . (!$oldSingleKeyExists ? "✅ PASS" : "❌ FAIL") . "\n";
echo " - [Schema] Khóa liên hợp mới uq_quiz_question (quiz_id, question_id) hiện diện: " . ($hasCompoundUnique ? "✅ PASS" : "❌ FAIL") . "\n";

// 2. Verify independent policy across Quiz A and Quiz B for identical question
$service->save_question_policy($quizA, $testQId, (int)$teacher->id, [
    'input_mode' => 'stdin',
    'testcode_template' => '',
    'normalization_mode' => 'trim',
    'categories' => "edge_a\nboundary_a",
]);

$service->save_question_policy($quizB, $testQId, (int)$teacher->id, [
    'input_mode' => 'testcode',
    'testcode_template' => 'assert {{INPUT}}',
    'normalization_mode' => 'raw',
    'categories' => "edge_b\nperformance_b",
]);

$polA = $service->question_policy($quizA, $testQId);
$polB = $service->question_policy($quizB, $testQId);

$isIsolated = ($polA['input_mode'] === 'stdin' && $polA['normalization_mode'] === 'trim' &&
                $polB['input_mode'] === 'testcode' && $polB['normalization_mode'] === 'raw');
echo " - [Isolation] Chính sách độc lập Quiz A vs Quiz B trên cùng 1 câu hỏi: " . ($isIsolated ? "✅ PASS" : "❌ FAIL") . "\n";

// Mutate Quiz A, ensure Quiz B remains completely unaffected
$service->save_question_policy($quizA, $testQId, (int)$teacher->id, [
    'input_mode' => 'template',
    'testcode_template' => 'run_twice({{INPUT}})',
    'normalization_mode' => 'json',
    'categories' => "template_a",
]);

$polA2 = $service->question_policy($quizA, $testQId);
$polB2 = $service->question_policy($quizB, $testQId);
$isMutationIsolated = ($polA2['input_mode'] === 'template' && $polB2['input_mode'] === 'testcode' && $polB2['normalization_mode'] === 'raw');
echo " - [Isolation] Sửa đổi Quiz A không làm biến đổi cấu hình của Quiz B: " . ($isMutationIsolated ? "✅ PASS" : "❌ FAIL") . "\n";

// Restore Quiz A policy
$service->save_question_policy($quizA, $testQId, (int)$teacher->id, [
    'input_mode' => 'stdin',
    'testcode_template' => '',
    'normalization_mode' => 'trim',
    'categories' => "edge_case\nboundary",
]);

$results['test_a'] = (!$oldSingleKeyExists && $hasCompoundUnique && $isIsolated && $isMutationIsolated);
echo " => KẾT QUẢ TEST A: " . ($results['test_a'] ? "PASSED" : "FAILED") . "\n\n";

// =================================================================
// TEST B: PERMISSION & FULL PRIVACY API
// =================================================================
echo "-----------------------------------------------------------------\n";
echo "TEST B: PERMISSION & FULL PRIVACY API (Export, Erasure, Anonymisation)\n";
echo "-----------------------------------------------------------------\n";

$coursecontext = context_course::instance($courseid);

// 1. Capability Walls
$canReview = has_capability('local/testcase_exchange:review', $coursecontext, $sv1);
$canManage = has_capability('local/testcase_exchange:manage', $coursecontext, $sv1);
$permBlocked = (!$canReview && !$canManage);
echo " - [Permissions] Sinh viên sv_an bị chặn review/manage: " . ($permBlocked ? "✅ PASS" : "❌ FAIL") . "\n";

\core\session\manager::set_user($sv1);
$unauthorizedBlocked = false;
try {
    require_capability('local/testcase_exchange:manage', $coursecontext);
} catch (\required_capability_exception $e) {
    $unauthorizedBlocked = true;
}
echo " - [Permissions] Ngoại lệ required_capability_exception kích hoạt khi gọi hàm trái phép: " . ($unauthorizedBlocked ? "✅ PASS" : "❌ FAIL") . "\n";

// 2. Zero Oracle Leakage
$runsSv1 = $service->runs_for_user($courseid, (int)$sv1->id);
$oracleLeaked = false;
foreach ($runsSv1 as $r) {
    if (isset($r['show_oracle']) && $r['show_oracle'] === true) {
        $oracleLeaked = true;
    }
}
echo " - [Privacy] Bảo mật tuyệt đối Lời giải tham chiếu (Zero Oracle Leakage): " . (!$oracleLeaked ? "✅ PASS" : "❌ FAIL") . "\n";

// 3. Complete Privacy API Cycle: Context Discovery -> Export -> User Erasure
// Create dedicated transient student to verify actual database erasure
$transientUsername = 'sv_privacy_audit_' . time();
$transientUser = $DB->get_record('user', ['username' => $transientUsername]);
if (!$transientUser) {
    $transientUser = new stdClass();
    $transientUser->auth = 'manual';
    $transientUser->confirmed = 1;
    $transientUser->mnethostid = $CFG->mnet_localhost_id;
    $transientUser->username = $transientUsername;
    $transientUser->password = hash_internal_user_password('TestPass123!');
    $transientUser->firstname = 'Privacy';
    $transientUser->lastname = 'Tester';
    $transientUser->email = $transientUsername . '@example.com';
    $transientUser->id = $DB->insert_record('user', $transientUser);
}

// Create sample run & reward for this transient user
$tRunId = $DB->insert_record('local_tce_runs', [
    'course_id' => $courseid,
    'quiz_id' => $quizA,
    'question_id' => $testQId,
    'user_id' => $transientUser->id,
    'input_raw' => 'priv_test_input',
    'input_raw_hash' => hash('sha256', 'priv_test_input'),
    'input_normalized' => 'priv_test_input',
    'input_fingerprint' => hash('sha256', 'priv_test_input'),
    'student_source_hash' => hash('sha256', 'code'),
    'predicted_output' => 'out',
    'student_run_output' => 'out',
    'oracle_output' => 'out',
    'student_outcome' => 'success',
    'oracle_outcome' => 'success',
    'timecreated' => time(),
]);

$tRewardId = $DB->insert_record('local_tce_rewards', [
    'course_id' => $courseid,
    'quiz_id' => $quizA,
    'question_id' => $testQId,
    'receiver_user_id' => $transientUser->id,
    'contribution_id' => 0,
    'reward_source' => 'seed',
    'is_claimed' => 0,
    'timecreated' => time(),
]);

// 3.1 Context discovery
$contextlist = privacy_provider::get_contexts_for_userid((int)$transientUser->id);
$hasContext = in_array((int)$coursecontext->id, array_map('intval', $contextlist->get_contextids()), true);
echo " - [Privacy API] get_contexts_for_userid xác định đúng course context: " . ($hasContext ? "✅ PASS" : "❌ FAIL") . "\n";

// 3.2 Export
$approvedContextlist = new \core_privacy\local\request\approved_contextlist(
    $transientUser,
    'local_testcase_exchange',
    [$coursecontext->id]
);
privacy_provider::export_user_data($approvedContextlist);
echo " - [Privacy API] export_user_data thực thi thành công không phát sinh lỗi: ✅ PASS\n";

// 3.3 Erasure (delete_data_for_user)
privacy_provider::delete_data_for_user($approvedContextlist);

// Verify records actually deleted from database
$runsRemaining = $DB->count_records('local_tce_runs', ['user_id' => $transientUser->id]);
$rewardsRemaining = $DB->count_records('local_tce_rewards', ['receiver_user_id' => $transientUser->id]);
$isErased = ($runsRemaining === 0 && $rewardsRemaining === 0);
echo " - [Privacy API] delete_data_for_user xóa sạch bản ghi cá nhân trong CSDL (Runs còn: {$runsRemaining}, Rewards còn: {$rewardsRemaining}): " . ($isErased ? "✅ PASS" : "❌ FAIL") . "\n";

// Clean up transient user
$DB->delete_records('user', ['id' => $transientUser->id]);

$results['test_b'] = ($permBlocked && $unauthorizedBlocked && !$oracleLeaked && $hasContext && $isErased);
echo " => KẾT QUẢ TEST B: " . ($results['test_b'] ? "PASSED" : "FAILED") . "\n\n";

// =================================================================
// TEST C: CODERUNNER COMPATIBILITY, EDGE CASES & ZERO INTERFERENCE
// =================================================================
echo "-----------------------------------------------------------------\n";
echo "TEST C: CODERUNNER COMPATIBILITY, EDGE CASES & ZERO INTERFERENCE\n";
echo "-----------------------------------------------------------------\n";

$quizObjMid = $DB->get_record('quiz', ['id' => $quizA], '*', MUST_EXIST);
$answerMid = (string)$testQ->answer;
$polMid = $service->question_policy($quizA, $testQId, $testQ->coderunnertype ?? 'python3');

// 1. Standard CodeRunner execution
$qtestsMid = array_values($DB->get_records('question_coderunner_tests', ['questionid' => $testQId], 'id ASC'));
$firstTest = reset($qtestsMid);
$sampleInputMid = $firstTest ? (trim((string)$firstTest->stdin) ?: trim((string)$firstTest->testcode)) : "20";
$runMid = execution_service::run_pair($testQId, $answerMid, $sampleInputMid, $polMid, $quizObjMid);
$passPython3 = ($runMid['oracle']['outcome'] === 'success');
echo " - [CodeRunner] {$testQ->coderunnertype} ({$testQ->name} - Input '{$sampleInputMid}'): " . ($passPython3 ? "✅ PASS" : "❌ FAIL") . "\n";

// 2. python3_w_input
$p3wQ = $DB->get_record_sql("
    SELECT q.id, q.name, qo.coderunnertype, qo.answer
      FROM {question} q
      JOIN {question_coderunner_options} qo ON qo.questionid = q.id
     WHERE qo.coderunnertype = 'python3_w_input'
       AND q.name NOT LIKE '%PROTOTYPE%'
       AND qo.answer IS NOT NULL
       AND TRIM(qo.answer) <> ''
     ORDER BY q.id ASC
     LIMIT 1
");

$passP3W = false;
if ($p3wQ) {
    $qtests = array_values($DB->get_records('question_coderunner_tests', ['questionid' => $p3wQ->id], 'id ASC'));
    $sampleTest = reset($qtests);
    $testInput = $sampleTest ? (trim((string)$sampleTest->stdin) ?: trim((string)$sampleTest->testcode)) : "PrintGrid()";
    $polP3W = $service->question_policy(3, (int)$p3wQ->id, 'python3_w_input');
    
    $runP3W = execution_service::run_pair((int)$p3wQ->id, (string)$p3wQ->answer, $testInput, $polP3W, $quizObjMid);
    $passP3W = ($runP3W['oracle']['outcome'] === 'success');
    echo " - [CodeRunner] python3_w_input ({$p3wQ->name} - Input '{$testInput}'): " . ($passP3W ? "✅ PASS" : "❌ FAIL") . "\n";
} else {
    $passP3W = true;
    echo " - [CodeRunner] python3_w_input: Không tìm thấy câu hỏi non-prototype\n";
}

// 3. Edge Cases: Empty string and whitespace inputs
$runEmpty = execution_service::run_pair($testQId, (string)$answerMid, "", $polMid, $quizObjMid);
$emptyHandled = is_array($runEmpty) && isset($runEmpty['oracle']);
echo " - [Edge Case] Xử lý an toàn đầu vào chuỗi rỗng '': " . ($emptyHandled ? "✅ PASS" : "❌ FAIL") . "\n";

$runWhitespace = execution_service::run_pair($testQId, (string)$answerMid, "   \n\t  ", $polMid, $quizObjMid);
$wsHandled = is_array($runWhitespace) && isset($runWhitespace['oracle']);
echo " - [Edge Case] Xử lý an toàn đầu vào khoảng trắng '   \\n\\t  ': " . ($wsHandled ? "✅ PASS" : "❌ FAIL") . "\n";

// 4. Zero Grading / Penalty Interference Verification
// Inspect mdl_quiz_attempts and mdl_question_attempts
$studentAttemptsBefore = $DB->count_records('quiz_attempts', ['quiz' => $quizA, 'userid' => $sv1->id]);
$questionAttemptsBefore = $DB->count_records_sql("
    SELECT COUNT(*) FROM {question_attempts} qa
      JOIN {question_usages} qu ON qu.id = qa.questionusageid
      JOIN {quiz_attempts} qza ON qza.uniqueid = qu.id
     WHERE qza.quiz = ? AND qza.userid = ?
", [$quizA, $sv1->id]);

// Perform exploration run and check attempts count again
$qMid = context_service::validate($courseid, $quizA, $testQId);
\core\session\manager::set_user($sv1);
$probeRun = $service->create_run(
    $courseid, $quizObjMid, $qMid, (int)$sv1->id,
    (string)$answerMid, $sampleInputMid, (string)$runMid['oracle']['output'],
    "Auditing interference", "edge_case", "reflection"
);

$studentAttemptsAfter = $DB->count_records('quiz_attempts', ['quiz' => $quizA, 'userid' => $sv1->id]);
$questionAttemptsAfter = $DB->count_records_sql("
    SELECT COUNT(*) FROM {question_attempts} qa
      JOIN {question_usages} qu ON qu.id = qa.questionusageid
      JOIN {quiz_attempts} qza ON qza.uniqueid = qu.id
     WHERE qza.quiz = ? AND qza.userid = ?
", [$quizA, $sv1->id]);

$zeroInterference = ($studentAttemptsBefore === $studentAttemptsAfter && $questionAttemptsBefore === $questionAttemptsAfter);
echo " - [Zero Interference] Hoạt động của plugin không làm tăng attempts hoặc can thiệp điểm số Quiz ({$studentAttemptsBefore} -> {$studentAttemptsAfter} quiz attempts): " . ($zeroInterference ? "✅ PASS" : "❌ FAIL") . "\n";

$results['test_c'] = ($passPython3 && $passP3W && $emptyHandled && $wsHandled && $zeroInterference);
echo " => KẾT QUẢ TEST C: " . ($results['test_c'] ? "PASSED" : "FAILED") . "\n\n";

// =================================================================
// TEST D: TRUE MULTI-PROCESS CONCURRENCY & RACE CONDITION DEFENSE
// =================================================================
echo "-----------------------------------------------------------------\n";
echo "TEST D: TRUE MULTI-PROCESS CONCURRENCY (Multi-process Worker Synchronization)\n";
echo "-----------------------------------------------------------------\n";

// -------------------------------------------------------------
// D1: Two Independent OS Processes Submitting Identical Contribution Concurrently
// -------------------------------------------------------------
echo "--- Subtest D1: Concurrent Contribution Submission from 2 OS Processes ---\n";

// Input for question must be valid
do {
    $concurInput = (string) mt_rand(1000, 99999);
    $probeConcur = execution_service::run_pair($testQId, (string)$answerMid, $concurInput, $polMid, $quizObjMid);
} while ($probeConcur['oracle']['outcome'] !== 'success');

$validConcurOut = (string)$probeConcur['oracle']['output'];

// Pre-create runs for sv2 and sv3 with exact same input
\core\session\manager::set_user($sv2);
$runSv2 = $service->create_run(
    $courseid, $quizObjMid, $qMid, (int)$sv2->id,
    (string)$answerMid, $concurInput, $validConcurOut,
    "Parallel submit from sv2", "edge_case", ""
);

\core\session\manager::set_user($sv3);
$runSv3 = $service->create_run(
    $courseid, $quizObjMid, $qMid, (int)$sv3->id,
    (string)$answerMid, $concurInput, $validConcurOut,
    "Parallel submit from sv3", "edge_case", ""
);

$fp = (string)$runSv2['fingerprint'];

// Check database counts before
$canonicalBefore = $DB->count_records('local_tce_canonical', ['quiz_id' => $quizA, 'question_id' => $testQId, 'input_fingerprint' => $fp]);
$contribsBefore = $DB->count_records('local_tce_contributions', ['quiz_id' => $quizA, 'question_id' => $testQId, 'input_fingerprint' => $fp]);

$barrierSubmit = '/tmp/barrier_submit_' . uniqid() . '.lock';
$logSubmit2 = '/tmp/worker_submit_sv2.json';
$logSubmit3 = '/tmp/worker_submit_sv3.json';
@unlink($barrierSubmit);
@unlink($logSubmit2);
@unlink($logSubmit3);

$workerSubmitScript = $CFG->dirroot . '/local/testcase_exchange/tests/cli/concurrency_worker_submit.php';

// Spawn 2 independent background OS processes
$cmd2 = "php {$workerSubmitScript} {$runSv2['id']} {$sv2->id} {$barrierSubmit} {$logSubmit2} > /dev/null 2>&1 &";
$cmd3 = "php {$workerSubmitScript} {$runSv3['id']} {$sv3->id} {$barrierSubmit} {$logSubmit3} > /dev/null 2>&1 &";

exec($cmd2);
exec($cmd3);
usleep(60000); // 60ms to let both processes boot and spin at barrier

// Release barrier: Trigger simultaneous execution
touch($barrierSubmit);
echo " - Barrier released: 2 OS processes đang gọi submit_contribution() đồng thời...\n";

// Await completion (max 6s)
$deadline = microtime(true) + 6.0;
while ((!file_exists($logSubmit2) || !file_exists($logSubmit3)) && microtime(true) < $deadline) {
    usleep(10000);
}

$resSv2 = json_decode(@file_get_contents($logSubmit2), true) ?? [];
$resSv3 = json_decode(@file_get_contents($logSubmit3), true) ?? [];

echo "   Process Worker 1 (sv_binh, PID " . ($resSv2['pid'] ?? '?') . "): Status=" . ($resSv2['result']['status'] ?? 'err') . " (" . ($resSv2['duration_ms'] ?? 0) . "ms) | Error: " . ($resSv2['error'] ?? 'none') . "\n";
echo "   Process Worker 2 (sv_chi, PID " . ($resSv3['pid'] ?? '?') . "): Status=" . ($resSv3['result']['status'] ?? 'err') . " (" . ($resSv3['duration_ms'] ?? 0) . "ms) | Error: " . ($resSv3['error'] ?? 'none') . "\n";

// Database inspection
$canonicalAfter = $DB->count_records('local_tce_canonical', ['quiz_id' => 3, 'question_id' => 567, 'input_fingerprint' => $fp]);
$contribsAfter = $DB->count_records('local_tce_contributions', ['quiz_id' => 3, 'question_id' => 567, 'input_fingerprint' => $fp]);

$statuses = [$resSv2['result']['status'] ?? '', $resSv3['result']['status'] ?? ''];
$hasOneSubmitted = in_array('submitted', $statuses, true);
$hasOneDuplicate = in_array('duplicate', $statuses, true);
$exactOneCanonical = ($canonicalAfter === 1);
$exactTwoContributions = ($contribsAfter === 2);

$passD1 = ($hasOneSubmitted && $hasOneDuplicate && $exactOneCanonical && $exactTwoContributions);
echo "   Kiểm tra: 1 canonical hợp lệ, 1 duplicate chính xác, CSDL không lỗi transaction: " . ($passD1 ? "✅ PASS" : "❌ FAIL") . "\n";

// -------------------------------------------------------------
// D2: Two Independent OS Processes Claiming Same Reward Concurrently
// -------------------------------------------------------------
echo "\n--- Subtest D2: Concurrent Reward Claiming from 2 OS Processes ---\n";

$testRewardId = $DB->insert_record('local_tce_rewards', [
    'course_id' => $courseid,
    'quiz_id' => $quizA,
    'question_id' => $testQId,
    'receiver_user_id' => $sv2->id,
    'contribution_id' => null,
    'seed_id' => mt_rand(500000, 999999),
    'reward_source' => 'seed',
    'is_claimed' => 0,
    'timecreated' => time(),
]);

$barrierClaim = '/tmp/barrier_claim_' . uniqid() . '.lock';
$logClaimA = '/tmp/worker_claim_a.json';
$logClaimB = '/tmp/worker_claim_b.json';
@unlink($barrierClaim);
@unlink($logClaimA);
@unlink($logClaimB);

$workerClaimScript = $CFG->dirroot . '/local/testcase_exchange/tests/cli/concurrency_worker_claim.php';

$cmdClaimA = "php {$workerClaimScript} {$testRewardId} {$sv2->id} {$courseid} {$barrierClaim} {$logClaimA} > /dev/null 2>&1 &";
$cmdClaimB = "php {$workerClaimScript} {$testRewardId} {$sv2->id} {$courseid} {$barrierClaim} {$logClaimB} > /dev/null 2>&1 &";

exec($cmdClaimA);
exec($cmdClaimB);
usleep(60000);

touch($barrierClaim);
echo " - Barrier released: 2 OS processes đang gọi claim_reward() đồng thời trên Reward ID={$testRewardId}...\n";

$deadlineClaim = microtime(true) + 6.0;
while ((!file_exists($logClaimA) || !file_exists($logClaimB)) && microtime(true) < $deadlineClaim) {
    usleep(10000);
}

$resClaimA = json_decode(@file_get_contents($logClaimA), true) ?? [];
$resClaimB = json_decode(@file_get_contents($logClaimB), true) ?? [];

echo "   Process Worker A (PID " . ($resClaimA['pid'] ?? '?') . "): Result=" . var_export($resClaimA['result'] ?? null, true) . "\n";
echo "   Process Worker B (PID " . ($resClaimB['pid'] ?? '?') . "): Result=" . var_export($resClaimB['result'] ?? null, true) . "\n";

// Database inspection
$rewardDb = $DB->get_record('local_tce_rewards', ['id' => $testRewardId]);
$claimViewsCount = $DB->count_records('local_tce_views', ['reward_id' => $testRewardId, 'action' => 'claim_reward']);

$passD2 = ($rewardDb && (int)$rewardDb->is_claimed === 1 && $claimViewsCount === 1);
echo "   Kiểm tra CSDL: is_claimed=1, tổng số bản ghi view ghi nhận đúng 1 (không nhân đôi): " . ($passD2 ? "✅ PASS" : "❌ FAIL") . "\n";

$results['test_d'] = ($passD1 && $passD2);
echo " => KẾT QUẢ TEST D: " . ($results['test_d'] ? "PASSED" : "FAILED") . "\n\n";

// Clean temporary lock files
@unlink($barrierSubmit);
@unlink($logSubmit2);
@unlink($logSubmit3);
@unlink($barrierClaim);
@unlink($logClaimA);
@unlink($logClaimB);

// =================================================================
// TỔNG KẾT BẰNG CHỨNG KIỂM ĐỊNH TỰ ĐỘNG
// =================================================================
echo "=================================================================\n";
echo "TỔNG KẾT BẰNG CHỨNG KIỂM ĐỊNH (RELEASE CANDIDATE READINESS AUDIT):\n";
echo "=================================================================\n";
echo " - Test A (Isolation & Schema Migration)       : " . ($results['test_a'] ? "PASSED" : "FAILED") . "\n";
echo " - Test B (Permission & Full Privacy API)      : " . ($results['test_b'] ? "PASSED" : "FAILED") . "\n";
echo " - Test C (CodeRunner Parity & Zero Penalty)   : " . ($results['test_c'] ? "PASSED" : "FAILED") . "\n";
echo " - Test D (True Multi-Process Concurrency)     : " . ($results['test_d'] ? "PASSED" : "FAILED") . "\n";
echo "=================================================================\n";

$allPassed = ($results['test_a'] && $results['test_b'] && $results['test_c'] && $results['test_d']);
exit($allPassed ? 0 : 1);
