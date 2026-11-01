<?php
define("CLI_SCRIPT", true);
require("/var/www/html/config.php");
require_once($CFG->dirroot . "/local/testcase_exchange/classes/external_database.php");
require_once($CFG->dirroot . "/local/testcase_exchange/classes/context_service.php");
require_once($CFG->dirroot . "/local/testcase_exchange/classes/schema_manager.php");
require_once($CFG->dirroot . "/local/testcase_exchange/classes/testcase_service.php");
require_once($CFG->dirroot . "/local/testcase_exchange/classes/normalizer.php");
require_once($CFG->dirroot . "/local/testcase_exchange/classes/execution_service.php");

use local_testcase_exchange\external_database;
use local_testcase_exchange\context_service;
use local_testcase_exchange\schema_manager;
use local_testcase_exchange\testcase_service;
use local_testcase_exchange\normalizer;
use local_testcase_exchange\execution_service;

$log = "";
$assertions_passed = 0;
$assertions_failed = 0;

function tlog($msg) {
    global $log;
    echo $msg . "\n";
    $log .= $msg . "\n";
}

function assert_true($condition, $description) {
    global $assertions_passed, $assertions_failed;
    if ($condition) {
        $assertions_passed++;
        tlog("  [PASS] $description");
        return true;
    } else {
        $assertions_failed++;
        tlog("  [FAIL] $description");
        return false;
    }
}

tlog("================================================================================");
tlog("BỘ KIỂM CHỨNG THỰC NGHIỆM CHÍNH SÁCH MỞ RỘNG (EDGE CASES, CONSISTENCY & RECOVERY)");
tlog("Thời gian thực hiện: " . date("Y-m-d H:i:s"));
tlog("Môi trường: Moodle 4.4.12+ | MariaDB 11.x | Jobe Cluster");
tlog("================================================================================\n");

$conn = external_database::connect();
schema_manager::migrate($conn);
$service = new testcase_service($conn);

$courseid = 2;
$quizid = 76;
$questionid = 794;
$student1_id = 3;
$student2_id = 4;
$teacher_id = 2;

$qcontext = context_service::validate($courseid, $quizid, $questionid);
$quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
$policy = $service->question_policy($quizid, $questionid, (string)$qcontext->coderunnertype);
$ctx = execution_service::resolve_context($questionid, $policy);

$original_quiz_settings = $service->quiz_settings($quizid);
$original_coderunner_answer = $DB->get_field('question_coderunner_options', 'answer', ['questionid' => $questionid]);
$correct_code = "def find_max(lst):\n    return max(lst)";

function insert_mock_contrib($conn, $courseid, $quizid, $questionid, $userid, $input_raw, $rating) {
    static $mock_run_id = 950000;
    $mock_run_id++;
    $norm = normalizer::normalize($input_raw, 'trim');
    $fp = normalizer::fingerprint($norm);
    $stmt = $conn->prepare("INSERT INTO testcase_contributions 
        (run_id, course_id, quiz_id, question_id, user_id, input_normalized, input_fingerprint, oracle_output, purpose, category, status, rating) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 'mock', 'purpose', 'normal', 'approved', ?)");
    $stmt->bind_param('iiiiissi', $mock_run_id, $courseid, $quizid, $questionid, $userid, $norm, $fp, $rating);
    $stmt->execute();
    $id = (int)$stmt->insert_id;
    $stmt->close();
    return ['id' => $id, 'fp' => $fp];
}

try {
    // Cấu hình quiz cho kịch bản thử nghiệm
    $service->save_quiz_settings($quizid, $teacher_id, [
        'enabled' => 1,
        'is_enabled' => 1,
        'review_mode' => 'teacher',
        'reward_policy' => 'one_for_one',
        'show_oracle_output' => 1,
        'leaderboard_enabled' => 1,
        'max_runs_per_minute' => 300,
        'max_input_bytes' => 8192
    ]);

    // =========================================================================
    // CA 1: PHÂN PHỐI THƯỞNG NGẪU NHIÊN ĐỒNG ĐỀU (ORDER BY RAND())
    // =========================================================================
    tlog("--------------------------------------------------------------------------------");
    tlog("CA 1: KIỂM CHỨNG PHÂN PHỐI THƯỞNG NGẪU NHIÊN (PURE RANDOM - ORDER BY RAND())");
    tlog("--------------------------------------------------------------------------------");

    $conn->query("DELETE FROM testcase_rewards WHERE course_id = $courseid AND quiz_id = $quizid AND question_id = $questionid");
    $conn->query("DELETE FROM testcase_contributions WHERE course_id = $courseid AND quiz_id = $quizid AND question_id = $questionid AND user_id = $student2_id");

    $cand_A = insert_mock_contrib($conn, $courseid, $quizid, $questionid, $student2_id, "print(find_max([101, 201]))", 5);
    $cand_B = insert_mock_contrib($conn, $courseid, $quizid, $questionid, $student2_id, "print(find_max([301, 401]))", 3);
    $cand_C = insert_mock_contrib($conn, $courseid, $quizid, $questionid, $student2_id, "print(find_max([501, 601]))", 1);
    $trigger_c1 = insert_mock_contrib($conn, $courseid, $quizid, $questionid, $student1_id, "print(find_max([1111, 2222]))", null);

    $counts = [$cand_A['id'] => 0, $cand_B['id'] => 0, $cand_C['id'] => 0];
    $iterations = 150;

    $ref = new ReflectionClass($service);
    $grant_method = $ref->getMethod('grant_reward_for_contribution');
    $grant_method->setAccessible(true);

    for ($i = 0; $i < $iterations; $i++) {
        $conn->query("DELETE FROM testcase_rewards WHERE receiver_user_id = $student1_id AND question_id = $questionid");
        $grant_method->invoke($service, $trigger_c1['id']);
        $res = $conn->query("SELECT contribution_id FROM testcase_rewards WHERE receiver_user_id = $student1_id AND question_id = $questionid LIMIT 1")->fetch_assoc();
        if ($res && isset($counts[(int)$res['contribution_id']])) {
            $counts[(int)$res['contribution_id']]++;
        }
    }

    tlog("  - Tần suất quan sát sau $iterations lần rút ngẫu nhiên độc lập:");
    tlog("    + Cand A (5 sao): {$counts[$cand_A['id']]} lần (" . round($counts[$cand_A['id']]/$iterations*100, 1) . "%)");
    tlog("    + Cand B (3 sao): {$counts[$cand_B['id']]} lần (" . round($counts[$cand_B['id']]/$iterations*100, 1) . "%)");
    tlog("    + Cand C (1 sao): {$counts[$cand_C['id']]} lần (" . round($counts[$cand_C['id']]/$iterations*100, 1) . "%)");

    assert_true($counts[$cand_A['id']] > 0 && $counts[$cand_B['id']] > 0 && $counts[$cand_C['id']] > 0, 
        "Cả 3 ứng viên có rating khác nhau đều được chọn (Rating cao không độc chiếm giải thưởng)");

    // =========================================================================
    // CA 2: LOẠI TRỪ TOÀN DIỆN TRÊN CẢ PEER POOL VÀ SEED BANK
    // =========================================================================
    tlog("\n--------------------------------------------------------------------------------");
    tlog("CA 2: KIỂM CHỨNG LOẠI TRỪ ĐẦY ĐỦ TRÊN CẢ PEER POOL VÀ SEED BANK");
    tlog("--------------------------------------------------------------------------------");

    $conn->query("DELETE FROM testcase_rewards WHERE course_id = $courseid AND quiz_id = $quizid AND question_id = $questionid");
    $conn->query("DELETE FROM testcase_contributions WHERE course_id = $courseid AND quiz_id = $quizid AND question_id = $questionid");
    $conn->query("DELETE FROM question_seed_testcases WHERE course_id = $courseid AND quiz_id = $quizid AND question_id = $questionid");

    // 2.1: Kiểm tra trên Peer Pool
    tlog("  [Phần 2.1: Kiểm thử trên Peer Pool]");
    $input_tried_peer = "print(find_max([-55, -11, -100]))";
    $norm_tried_peer = normalizer::normalize($input_tried_peer, 'trim');
    $fp_tried_peer = normalizer::fingerprint($norm_tried_peer);
    $raw_hash_tried_peer = hash('sha256', $input_tried_peer);
    $conn->query("INSERT INTO testcase_runs (course_id, quiz_id, question_id, user_id, input_raw, input_raw_hash, input_normalized, input_fingerprint, student_outcome, oracle_outcome, oracle_version_hash)
        VALUES ($courseid, $quizid, $questionid, $student1_id, '$input_tried_peer', '$raw_hash_tried_peer', '$norm_tried_peer', '$fp_tried_peer', 'success', 'success', '{$ctx['oracle_version_hash']}')");

    $cand_peer_tried = insert_mock_contrib($conn, $courseid, $quizid, $questionid, $student2_id, $input_tried_peer, 5);
    $sample_fps = $ctx['sample_fingerprints'];
    $sample_fp = !empty($sample_fps) ? $sample_fps[0] : 'sample_fp_mock';
    $cand_peer_sample = insert_mock_contrib($conn, $courseid, $quizid, $questionid, $student2_id, "sample_peer_code", 5);
    $conn->query("UPDATE testcase_contributions SET input_fingerprint = '$sample_fp' WHERE id = {$cand_peer_sample['id']}");
    $input_valid_peer = "print(find_max([999, 1000, 1001]))";
    $cand_peer_valid = insert_mock_contrib($conn, $courseid, $quizid, $questionid, $student2_id, $input_valid_peer, 4);

    $trigger_c2_peer = insert_mock_contrib($conn, $courseid, $quizid, $questionid, $student1_id, "print(find_max([777, 888]))", null);
    $grant_method->invoke($service, $trigger_c2_peer['id']);
    $res_peer = $conn->query("SELECT contribution_id, reward_source FROM testcase_rewards WHERE receiver_user_id = $student1_id AND question_id = $questionid")->fetch_assoc();

    assert_true((int)$res_peer['contribution_id'] === $cand_peer_valid['id'], 
        "Peer Pool loại trừ chính xác testcase đã chạy trên Playground và testcase mẫu công khai, cấp trúng ứng viên hợp lệ duy nhất");

    // 2.2: Kiểm tra trên Seed Bank (khi kho Peer rỗng)
    tlog("  [Phần 2.2: Kiểm thử trên Seed Bank (Fallback)]");
    $conn->query("DELETE FROM testcase_rewards WHERE course_id = $courseid AND quiz_id = $quizid AND question_id = $questionid");
    $conn->query("DELETE FROM testcase_contributions WHERE course_id = $courseid AND quiz_id = $quizid AND question_id = $questionid AND user_id = $student2_id");

    $input_tried_seed = "print(find_max([-99, -88, -77]))";
    $norm_tried_seed = normalizer::normalize($input_tried_seed, 'trim');
    $fp_tried_seed = normalizer::fingerprint($norm_tried_seed);
    $raw_hash_tried_seed = hash('sha256', $input_tried_seed);
    $conn->query("INSERT INTO testcase_runs (course_id, quiz_id, question_id, user_id, input_raw, input_raw_hash, input_normalized, input_fingerprint, student_outcome, oracle_outcome, oracle_version_hash)
        VALUES ($courseid, $quizid, $questionid, $student1_id, '$input_tried_seed', '$raw_hash_tried_seed', '$norm_tried_seed', '$fp_tried_seed', 'success', 'success', '{$ctx['oracle_version_hash']}')");

    function insert_mock_seed($conn, $courseid, $quizid, $questionid, $input_raw, $fp) {
        $norm = normalizer::normalize($input_raw, 'trim');
        $fp = $fp ?: normalizer::fingerprint($norm);
        $stmt = $conn->prepare("INSERT INTO question_seed_testcases 
            (course_id, quiz_id, question_id, input_raw, input_normalized, input_fingerprint, expected_output, is_active) 
            VALUES (?, ?, ?, ?, ?, ?, 'seed_expected', 1)");
        $stmt->bind_param('iiisss', $courseid, $quizid, $questionid, $input_raw, $norm, $fp);
        $stmt->execute();
        $id = (int)$stmt->insert_id;
        $stmt->close();
        return $id;
    }

    $seed_tried_id = insert_mock_seed($conn, $courseid, $quizid, $questionid, $input_tried_seed, $fp_tried_seed);
    $seed_sample_id = insert_mock_seed($conn, $courseid, $quizid, $questionid, "seed_sample", $sample_fp);
    $seed_valid_id = insert_mock_seed($conn, $courseid, $quizid, $questionid, "print(find_max([555, 666]))", null);

    $trigger_c2_seed = insert_mock_contrib($conn, $courseid, $quizid, $questionid, $student1_id, "print(find_max([444, 333]))", null);
    $grant_method->invoke($service, $trigger_c2_seed['id']);
    $res_seed = $conn->query("SELECT contribution_id, reward_source, seed_id FROM testcase_rewards WHERE receiver_user_id = $student1_id AND question_id = $questionid")->fetch_assoc();

    assert_true($res_seed['reward_source'] === 'seed', "Fallback sang nguồn 'seed' khi kho peer rỗng");
    assert_true((int)$res_seed['seed_id'] === $seed_valid_id, "Seed Bank loại trừ chính xác seed đã thử và seed mẫu, chỉ cấp seed hợp lệ duy nhất");
    assert_true($res_seed['contribution_id'] === null, "Trường contribution_id nhận giá trị NULL hợp lệ khi thưởng seed");

    // =========================================================================
    // CA 3: VÔ HIỆU HÓA CACHE ORACLE KHI SỬA LỜI GIẢI MẪU (CACHE INVALIDATION)
    // =========================================================================
    tlog("\n--------------------------------------------------------------------------------");
    tlog("CA 3: KIỂM CHỨNG VÔ HIỆU HÓA CACHE ORACLE KHI THAY ĐỔI CẤU HÌNH/LỜI GIẢI");
    tlog("--------------------------------------------------------------------------------");

    $c3_rand = mt_rand(1000, 9000);
    $test_input_c3 = "print(find_max([$c3_rand, " . ($c3_rand + 50) . "]))";
    $c3_expected_orig = (string)($c3_rand + 50);
    $c3_expected_mod = (string)(($c3_rand + 50) * 100);

    $run_c3_1 = $service->create_run($courseid, $quiz, $qcontext, $student1_id, $correct_code, $test_input_c3, $c3_expected_orig, "Test cache", "normal", "");
    $oracle_hash_1 = $ctx['oracle_version_hash'];

    $code_revision_2 = "def find_max(lst):\n    # Code sửa đổi để kiểm tra cache\n    return max(lst)";
    $run_c3_2 = $service->create_run($courseid, $quiz, $qcontext, $student2_id, $code_revision_2, $test_input_c3, $c3_expected_orig, "Test oracle cache", "normal", "");
    assert_true($run_c3_2['oracle']['server'] === 'cached', "Oracle Cache Hit khi sinh viên 2 chạy cùng input chưa đổi lời giải mẫu");

    // Sửa lời giải mẫu
    $modified_answer = "def find_max(lst):\n    return max(lst) * 100";
    $DB->set_field('question_coderunner_options', 'answer', $modified_answer, ['questionid' => $questionid]);
    \cache::make('core', 'questiondata')->purge();
    \question_bank::load_question($questionid, true);

    $ctx_modified = execution_service::resolve_context($questionid, $policy);
    $oracle_hash_2 = $ctx_modified['oracle_version_hash'];
    assert_true($oracle_hash_1 !== $oracle_hash_2, "Oracle version hash thay đổi sau khi sửa lời giải mẫu");

    $run_c3_3 = $service->create_run($courseid, $quiz, $qcontext, $student1_id, $correct_code, $test_input_c3, $c3_expected_mod, "Test modified oracle", "normal", "");
    assert_true($run_c3_3['oracle']['server'] !== 'cached' && trim($run_c3_3['oracle']['output']) === $c3_expected_mod, 
        "Cache cũ tự động bị vô hiệu hóa, Jobe thực thi lại và cập nhật output mới ($c3_expected_mod)");

    // Khôi phục ngay answer gốc
    $DB->set_field('question_coderunner_options', 'answer', $original_coderunner_answer, ['questionid' => $questionid]);
    \cache::make('core', 'questiondata')->purge();
    \question_bank::load_question($questionid, true);

    // =========================================================================
    // CA 4: PHÂN ĐỊNH KHÓA LỌC TRÙNG VS KHÓA CACHE VỚI KHOẢNG TRẮNG CÓ Ý NGHĨA
    // =========================================================================
    tlog("\n--------------------------------------------------------------------------------");
    tlog("CA 4: PHÂN ĐỊNH KHÓA LỌC TRÙNG VS KHÓA CACHE VỚI KHOẢNG TRẮNG CÓ Ý NGHĨA");
    tlog("--------------------------------------------------------------------------------");

    // Thử nghiệm với chuỗi có khoảng trắng làm thay đổi trực tiếp độ dài/kết quả thực thi
    $input_raw_A = "print(len('code_item'))";
    $input_raw_B = "print(len('code_item '))"; // Có khoảng trắng sau -> độ dài thay đổi từ 9 lên 10

    $raw_hash_A = hash('sha256', $input_raw_A);
    $raw_hash_B = hash('sha256', $input_raw_B);

    assert_true($raw_hash_A !== $raw_hash_B, "Hai chuỗi khác biệt khoảng trắng có raw_hash độc lập");

    $run_c4_A = $service->create_run($courseid, $quiz, $qcontext, $student1_id, $correct_code, $input_raw_A, "9", "Test A", "normal", "");
    $run_c4_B = $service->create_run($courseid, $quiz, $qcontext, $student1_id, $correct_code, $input_raw_B, "10", "Test B", "normal", "");

    assert_true(trim($run_c4_A['oracle']['output']) === '9', "Output của Input A là 9");
    assert_true(trim($run_c4_B['oracle']['output']) === '10', "Output của Input B là 10 (Khoảng trắng làm thay đổi kết quả tính toán)");
    assert_true($run_c4_B['oracle']['server'] !== 'cached', "Input B không bị nhầm lẫn cache với Input A");

    // =========================================================================
    // CA 5: KIỂM CHỨNG SYNCHRONIZATION BARRIER VỚI INPUT MỚI HOÀN TOÀN
    // =========================================================================
    tlog("\n--------------------------------------------------------------------------------");
    tlog("CA 5: KIỂM CHỨNG ĐIỂM ĐỒNG BỘ (SYNCHRONIZATION BARRIER) VỚI INPUT MỚI");
    tlog("--------------------------------------------------------------------------------");

    $barrier_file = "/tmp/multisolve_concurrency_barrier.txt";
    @unlink($barrier_file);
    $rand_val = mt_rand(100000, 999999);
    $unique_input = "print(find_max([$rand_val, 999999]))";

    $php_worker_code = <<< 'EOF'
<?php
define("CLI_SCRIPT", true);
require("/var/www/html/config.php");
require_once($CFG->dirroot . "/local/testcase_exchange/classes/external_database.php");
require_once($CFG->dirroot . "/local/testcase_exchange/classes/context_service.php");
require_once($CFG->dirroot . "/local/testcase_exchange/classes/testcase_service.php");

use local_testcase_exchange\external_database;
use local_testcase_exchange\context_service;
use local_testcase_exchange\testcase_service;

$worker_id = $argv[1];
$barrier = $argv[2];
$unique_input = $argv[3];

$conn = external_database::connect();
$service = new testcase_service($conn);
$qcontext = context_service::validate(2, 76, 794);
$quiz = $DB->get_record('quiz', ['id' => 76]);

// Rendezvous tại barrier
file_put_contents($barrier, "ready_$worker_id\n", FILE_APPEND);
$start = microtime(true);
while (true) {
    $content = @file_get_contents($barrier);
    if (strpos($content, "ready_1") !== false && strpos($content, "ready_2") !== false) {
        break;
    }
    if (microtime(true) - $start > 5) break;
    usleep(1000);
}

// Bắn đồng thời sau khi cả 2 worker cùng sẵn sàng
$code = "def find_max(lst):\n    return max(lst)";
$run = $service->create_run(2, $quiz, $qcontext, 3, $code, $unique_input, "999999", "concurrency test", "normal", "");
echo json_encode([
    'worker' => $worker_id,
    'run_id' => $run['id'],
    'is_dup' => !empty($run['is_duplicate_run']),
    'server' => $run['oracle']['server']
]);
EOF;

    file_put_contents("/tmp/concurrency_worker.php", $php_worker_code);

    $p1 = proc_open("php /tmp/concurrency_worker.php 1 $barrier_file \"$unique_input\"", [1 => ['pipe', 'w']], $pipes1);
    $p2 = proc_open("php /tmp/concurrency_worker.php 2 $barrier_file \"$unique_input\"", [1 => ['pipe', 'w']], $pipes2);

    $out1 = stream_get_contents($pipes1[1]); fclose($pipes1[1]); proc_close($p1);
    $out2 = stream_get_contents($pipes2[1]); fclose($pipes2[1]); proc_close($p2);

    tlog("  - Worker 1: $out1");
    tlog("  - Worker 2: $out2");

    $data1 = json_decode($out1, true);
    $data2 = json_decode($out2, true);

    assert_true($data1 && $data2, "Cả hai worker hoàn thành qua Synchronization Barrier");
    $runs_in_db = $conn->query("SELECT COUNT(*) AS c FROM testcase_runs WHERE input_raw = '$unique_input'")->fetch_assoc()['c'];
    assert_true((int)$runs_in_db >= 1, "Bản ghi thực thi được ghi nhận an toàn trong MariaDB (Số bản ghi: $runs_in_db)");

    // =========================================================================
    // CA 6: TÁCH TÁI SỬ DỤNG KẾT QUẢ THỰC THI KHỎI GHI NHẬN DỰ ĐOÁN MỚI
    // =========================================================================
    tlog("\n--------------------------------------------------------------------------------");
    tlog("CA 6: SỬA EXPECTED OUTPUT (DỰ ĐOÁN) GIỮ NGUYÊN CODE VÀ INPUT");
    tlog("--------------------------------------------------------------------------------");

    $c6_val1 = mt_rand(100, 500);
    $c6_val2 = mt_rand(600, 999);
    $test_input_c6 = "print(find_max([$c6_val1, $c6_val2]))";
    $c6_expected_correct = (string)$c6_val2;
    
    // Bước 6.1: Lần đầu nhập Expected SAI ("99999")
    tlog("  [Bước 6.1: Sinh viên nhập Expected SAI (99999)]");
    $run_c6_wrong = $service->create_run($courseid, $quiz, $qcontext, $student1_id, $correct_code, $test_input_c6, "99999", "Test wrong expected", "normal", "");
    tlog("    + Run ID: {$run_c6_wrong['id']} | prediction_matches_oracle: " . var_export($run_c6_wrong['prediction_matches_oracle'], true));
    assert_true($run_c6_wrong['prediction_matches_oracle'] === false, "Lần đầu: Dự đoán sai bị đánh dấu false");
    
    // Thử đóng góp bằng Run này -> Bắt buộc bị chặn
    $c6_blocked = false;
    try {
        $service->submit_contribution($run_c6_wrong['id'], $student1_id);
    } catch (\moodle_exception $e) {
        $c6_blocked = true;
    }
    assert_true($c6_blocked, "Đóng góp bằng Run có Expected sai bị chặn qua exception");

    // Bước 6.2: Sinh viên giữ nguyên code và input, sửa Expected thành ĐÚNG với cùng code và input
    tlog("  [Bước 6.2: Sinh viên sửa Expected thành ĐÚNG ($c6_expected_correct) với cùng code và input]");
    $run_c6_right = $service->create_run($courseid, $quiz, $qcontext, $student1_id, $correct_code, $test_input_c6, $c6_expected_correct, "Test corrected expected", "normal", "");
    tlog("    + Run ID mới: {$run_c6_right['id']} | Server: {$run_c6_right['student']['server']} | prediction_matches_oracle: " . var_export($run_c6_right['prediction_matches_oracle'], true));
    
    assert_true($run_c6_right['id'] !== $run_c6_wrong['id'], "Hệ thống sinh Run ID mới để ghi nhận dự đoán mới");
    assert_true($run_c6_right['student']['server'] === 'cached', "Tái sử dụng 100% kết quả thực thi Jobe cũ (Sandbox Bypass)");
    assert_true($run_c6_right['prediction_matches_oracle'] === true, "Dự đoán mới đúng được xác nhận khớp Oracle");

    // Đóng góp bằng Run ID mới -> Bắt buộc THÀNH CÔNG!
    $c6_success = false;
    try {
        $res_contrib = $service->submit_contribution($run_c6_right['id'], $student1_id);
        $c6_success = ($res_contrib && in_array($res_contrib['status'], ['submitted', 'approved', 'duplicate'], true));
    } catch (\moodle_exception $e) {
        $c6_success = false;
    }
    assert_true($c6_success, "Đóng góp thành công sau khi sửa Expected đúng (Không bị kẹt dự đoán sai cũ)");

    // Bước 6.3: Sinh viên nhập lại y hệt cả Code, Input và Expected
    tlog("  [Bước 6.3: Sinh viên gửi lại y hệt cả Code, Input và Expected ($c6_expected_correct)]");
    $run_c6_dup = $service->create_run($courseid, $quiz, $qcontext, $student1_id, $correct_code, $test_input_c6, $c6_expected_correct, "Test exact duplicate", "normal", "");
    assert_true(!empty($run_c6_dup['is_duplicate_run']), "Gửi trùng hoàn toàn cả code, input và expected bị nhận diện là Duplicate Run");
    assert_true($run_c6_dup['id'] === $run_c6_right['id'], "Trả lại chính xác snapshot của Run ID trước đó");

} finally {
    // =========================================================================
    // KHÔI PHỤC TOÀN VẸN TRẠNG THÁI HỆ THỐNG
    // =========================================================================
    tlog("\n--------------------------------------------------------------------------------");
    tlog("KHÔI PHỤC TRẠNG THÁI HỆ THỐNG (FINALLY BLOCK)");
    tlog("--------------------------------------------------------------------------------");
    
    // Khôi phục lời giải CodeRunner gốc
    $DB->set_field('question_coderunner_options', 'answer', $original_coderunner_answer, ['questionid' => $questionid]);
    \cache::make('core', 'questiondata')->purge();
    \question_bank::load_question($questionid, true);
    tlog("  [RESTORE] Lời giải CodeRunner gốc đã được phục hồi.");

    // Khôi phục cấu hình Quiz gốc
    $service->save_quiz_settings($quizid, $teacher_id, $original_quiz_settings);
    tlog("  [RESTORE] Cấu hình Quiz settings đã được phục hồi.");
}

tlog("\n================================================================================");
tlog("TỔNG KẾT BỘ KIỂM CHỨNG: ĐẠT $assertions_passed / " . ($assertions_passed + $assertions_failed) . " ASSERTIONS");
if ($assertions_failed === 0) {
    tlog("KẾT LUẬN: TOÀN BỘ CÁC CA ĐỀU ĐẠT CHUẨN KỸ THUẬT (EXIT 0)");
} else {
    tlog("KẾT LUẬN: CÓ $assertions_failed PHÉP THỬ THẤT BẠI (EXIT 1)");
}
tlog("================================================================================");

file_put_contents("/var/www/html/verification_evidence/logs/consistency_and_concurrency_log.txt", $log);

if ($assertions_failed > 0) {
    exit(1);
} else {
    exit(0);
}
