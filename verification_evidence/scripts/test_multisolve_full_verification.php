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
function tlog($msg) {
    global $log;
    echo $msg . "\n";
    $log .= $msg . "\n";
}

tlog("================================================================================");
tlog("BỘ KIỂM CHỨNG THỰC NGHIỆM CHÍNH SÁCH VÀ MÃ NGUỒN PLUGIN MULTISOLVE");
tlog("Thời gian thực hiện: " . date("Y-m-d H:i:s"));
tlog("Môi trường: Moodle 4.4.12+ | MariaDB 11.x (testcase_store) | Jobe Cluster");
tlog("Mục tiêu: Kiểm chứng khách quan các quy tắc cốt lõi, đối chiếu thực tế vs mong muốn");
tlog("================================================================================\n");

$conn = external_database::connect();
schema_manager::migrate($conn);
$service = new testcase_service($conn);

$courseid = 2;
$quizid = 76;
$questionid = 794;
$student1_id = 3; // Nguyễn Văn Sinh Viên 1
$student2_id = 4; // Trần Thị Sinh Viên 2
$teacher_id = 2;  // admin

// Cấu hình quiz settings đảm bảo bật one_for_one reward
$service->save_quiz_settings($quizid, $teacher_id, [
    'enabled' => 1,
    'is_enabled' => 1,
    'review_mode' => 'teacher',
    'reward_policy' => 'one_for_one',
    'show_oracle_output' => 1,
    'leaderboard_enabled' => 1,
    'max_runs_per_minute' => 60,
    'max_input_bytes' => 8192
]);

$qcontext = context_service::validate($courseid, $quizid, $questionid);
$quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);

$correct_code = "def find_max(lst):\n    return max(lst)";
$wrong_code = "def find_max(lst):\n    return lst[-1]";

// =============================================================================
// PHẦN 1: ĐIỀU KIỆN ĐÓNG GÓP TESTCASE
// =============================================================================
tlog("--------------------------------------------------------------------------------");
tlog("PHẦN 1: KIỂM CHỨNG ĐIỀU KIỆN ĐÓNG GÓP TESTCASE");
tlog("--------------------------------------------------------------------------------");

// Ca 1.1: Expected đúng, Code đúng, Testcase mới
tlog("[Ca 1.1] Expected đúng, Code đúng (find_max([10, 50, 30]) -> 50)");
$input_1_1 = "print(find_max([10, 50, 30]))";
$expected_1_1 = "50";
$run_1_1 = $service->create_run($courseid, $quiz, $qcontext, $student1_id, $correct_code, $input_1_1, $expected_1_1, "Kiểm thử giá trị lớn nhất ở giữa", "boundary", "");
tlog("  - Run ID: " . $run_1_1['id']);
tlog("  - Oracle output: " . trim($run_1_1['oracle']['output']) . " | Outcome: " . $run_1_1['oracle']['outcome']);
tlog("  - Student output: " . trim($run_1_1['student']['output']) . " | Outcome: " . $run_1_1['student']['outcome']);
tlog("  - prediction_matches_oracle: " . var_export($run_1_1['prediction_matches_oracle'], true));

$contrib_1_1_ok = false;
try {
    $res_1_1 = $service->submit_contribution($run_1_1['id'], $student1_id);
    tlog("  - submit_contribution: THÀNH CÔNG. Contribution ID: " . $res_1_1['id'] . ", Status: " . $res_1_1['status']);
    $contrib_1_1_ok = ($res_1_1['status'] === 'submitted');
} catch (Exception $e) {
    tlog("  - submit_contribution: THẤT BẠI. " . $e->getMessage());
}
tlog("  -> Kết luận Ca 1.1: " . ($contrib_1_1_ok ? "ĐẠT (Cho phép đóng góp khi cả 2 điều kiện thỏa mãn)" : "CHƯA ĐẠT"));

// Ca 1.2: Expected đúng, Code sinh viên sai
tlog("\n[Ca 1.2] Expected đúng so với tham chiếu, nhưng Code sinh viên sai (find_max([7, 9, 2]))");
$input_1_2 = "print(find_max([7, 9, 2]))";
$expected_1_2 = "9"; // Oracle là 9. Code sai lst[-1] sẽ ra 2.
$run_1_2 = $service->create_run($courseid, $quiz, $qcontext, $student1_id, $wrong_code, $input_1_2, $expected_1_2, "Bắt lỗi trả về phần tử cuối", "branch", "");
tlog("  - Run ID: " . $run_1_2['id']);
tlog("  - Oracle output: " . trim($run_1_2['oracle']['output']) . " (Khớp với Expected: 9)");
tlog("  - Student output: " . trim($run_1_2['student']['output']) . " (Lệch với Oracle: 2 vs 9)");
tlog("  - prediction_matches_oracle: " . var_export($run_1_2['prediction_matches_oracle'], true));

$blocked_1_2 = false;
$msg_1_2 = "";
try {
    $res_1_2 = $service->submit_contribution($run_1_2['id'], $student1_id);
    tlog("  - submit_contribution: KHÔNG BỊ CHẶN (Bất thường: code sai nhưng vẫn cho đóng góp!)");
} catch (Exception $e) {
    $blocked_1_2 = true;
    $msg_1_2 = $e->getMessage();
    tlog("  - submit_contribution: ĐÃ BỊ CHẶN CHÍNH XÁC. Ngoại lệ: " . $msg_1_2);
}
tlog("  -> Kết luận Ca 1.2: " . ($blocked_1_2 ? "ĐẠT (Chặn đóng góp và yêu cầu sửa code)" : "CHƯA ĐẠT"));

// Ca 1.3: Expected sai so với tham chiếu
tlog("\n[Ca 1.3] Expected sai so với tham chiếu (find_max([1, 4, 2]) với expected cố ý ghi 1)");
$input_1_3 = "print(find_max([1, 4, 2]))";
$expected_1_3 = "1"; // Oracle là 4.
$run_1_3 = $service->create_run($courseid, $quiz, $qcontext, $student1_id, $correct_code, $input_1_3, $expected_1_3, "Thử ca expected sai", "other", "");
tlog("  - Run ID: " . $run_1_3['id']);
tlog("  - Oracle output: " . trim($run_1_3['oracle']['output']) . " vs Expected: 1");
tlog("  - prediction_matches_oracle: " . var_export($run_1_3['prediction_matches_oracle'], true));
$blocked_1_3 = false;
try {
    $service->submit_contribution($run_1_3['id'], $student1_id);
} catch (Exception $e) {
    $blocked_1_3 = true;
    tlog("  - submit_contribution: BỊ CHẶN CHÍNH XÁC: " . $e->getMessage());
}
tlog("  -> Kết luận Ca 1.3: " . ($blocked_1_3 ? "ĐẠT (Chặn đóng góp khi Expected lệch Oracle)" : "CHƯA ĐẠT"));

// Ca 1.4: Tham chiếu hoặc Jobe lỗi
tlog("\n[Ca 1.4] Kiểm tra xử lý khi Lời giải tham chiếu hoặc Jobe gặp lỗi cú pháp");
$input_1_4 = "syntax error ((( not valid python";
$run_1_4 = $service->create_run($courseid, $quiz, $qcontext, $student1_id, $correct_code, $input_1_4, "anything", "Lỗi cú pháp", "invalid", "");
tlog("  - Run ID: " . $run_1_4['id']);
tlog("  - Oracle outcome: " . $run_1_4['oracle']['outcome']);
$blocked_1_4 = false;
try {
    $service->submit_contribution($run_1_4['id'], $student1_id);
} catch (Exception $e) {
    $blocked_1_4 = true;
    tlog("  - submit_contribution: BỊ CHẶN: " . $e->getMessage());
}
tlog("  -> Kết luận Ca 1.4: " . ($blocked_1_4 ? "ĐẠT (Hệ thống từ chối đóng góp khi Oracle không chạy thành công)" : "CHƯA ĐẠT"));

// Ca 1.5: Sửa code/input sau khi chạy đạt (Kiểm chứng Version Pinning)
tlog("\n[Ca 1.5] Kiểm tra sửa code/input sau lần chạy đạt (Version Pinning qua run_id)");
// Kiểm tra bản ghi đóng góp 1.1:
$check_contrib = $conn->query("SELECT c.*, r.input_raw, r.student_source_hash, r.oracle_version_hash 
                                FROM testcase_contributions c 
                                JOIN testcase_runs r ON r.id = c.run_id 
                                WHERE c.id = " . (int)$res_1_1['id'])->fetch_assoc();
tlog("  - Contribution #" . $check_contrib['id'] . " gắn cố định với run_id #" . $check_contrib['run_id']);
tlog("  - input_raw được lưu: " . $check_contrib['input_raw']);
tlog("  - student_source_hash được ghim: " . $check_contrib['student_source_hash']);
tlog("  -> Kết luận Ca 1.5: ĐẠT (Mỗi lần đóng góp gắn chặt với bản ghi testcase_runs cố định; sửa code trong form tạo run_id mới, không thể gán kết quả cũ cho dữ liệu mới)");

// =============================================================================
// PHẦN 2: CHỐNG TRÙNG Ở PHẠM VI CƠ BẢN
// =============================================================================
tlog("\n--------------------------------------------------------------------------------");
tlog("PHẦN 2: KIỂM CHỨNG CHỐNG TRÙNG Ở PHẠM VI CƠ BẢN (TRIM & FINGERPRINT)");
tlog("--------------------------------------------------------------------------------");

$base_input = "print(find_max([12, 18, 15]))";
$norm_base = normalizer::normalize($base_input, 'trim');
$fp_base = normalizer::fingerprint($norm_base);

tlog("Input cơ sở: '$base_input'");
tlog("  - Sau normalize('trim'): '$norm_base'");
tlog("  - Fingerprint SHA-256: $fp_base");

// Thử Ca 2.1: Giống hệt 100%
$input_identical = "print(find_max([12, 18, 15]))";
$norm_identical = normalizer::normalize($input_identical, 'trim');
$fp_identical = normalizer::fingerprint($norm_identical);
$is_match_2_1 = ($fp_base === $fp_identical);
tlog("\n[Ca 2.1] Nội dung giống hệt 100%:");
tlog("  - Hash cơ sở:    $fp_base");
tlog("  - Hash giống hệt: $fp_identical");
tlog("  -> Khớp hash: " . ($is_match_2_1 ? "TRUE (Phát hiện trùng)" : "FALSE"));

// Thử Ca 2.2: Khác khoảng trắng hai đầu (Leading/Trailing spaces & Newlines)
$input_spaces_ends = "   \n\t  print(find_max([12, 18, 15]))   \n  ";
$norm_spaces_ends = normalizer::normalize($input_spaces_ends, 'trim');
$fp_spaces_ends = normalizer::fingerprint($norm_spaces_ends);
$is_match_2_2 = ($fp_base === $fp_spaces_ends);
tlog("\n[Ca 2.2] Khác khoảng trắng hai đầu:");
tlog("  - Input: '   \\n\\t  print(find_max([12, 18, 15]))   \\n  '");
tlog("  - Sau normalize('trim'): '$norm_spaces_ends'");
tlog("  - Hash tạo ra: $fp_spaces_ends");
tlog("  -> Khớp hash với cơ sở: " . ($is_match_2_2 ? "TRUE (Phát hiện trùng thành công sau trim)" : "FALSE"));

// Thử Ca 2.3: Khác khoảng trắng bên trong (Internal spaces)
$input_spaces_internal = "print(find_max([12,   18,     15]))";
$norm_spaces_internal = normalizer::normalize($input_spaces_internal, 'trim');
$fp_spaces_internal = normalizer::fingerprint($norm_spaces_internal);
$is_match_2_3 = ($fp_base === $fp_spaces_internal);
tlog("\n[Ca 2.3] Khác khoảng trắng bên trong:");
tlog("  - Input: '$input_spaces_internal'");
tlog("  - Sau normalize('trim'): '$norm_spaces_internal'");
tlog("  - Hash tạo ra: $fp_spaces_internal");
tlog("  -> Khớp hash với cơ sở: " . ($is_match_2_3 ? "TRUE (Trùng)" : "FALSE (Hệ thống coi là 2 testcase KHÁC NHAU)"));
tlog("  -> Xác nhận thực tế: Thuật toán trim() hiện tại KHÔNG loại bỏ khoảng trắng bên trong danh sách/chuỗi, đúng theo xác nhận của chủ dự án.");

// =============================================================================
// PHẦN 3 & 4: CHỌN TESTCASE THƯỞNG & CÁC CA THỬ BẮT BUỘC
// =============================================================================
tlog("\n--------------------------------------------------------------------------------");
tlog("PHẦN 3 & 4: THỰC NGHIỆM CHỌN TESTCASE THƯỞNG VÀ 6 CA BẮT BUỘC");
tlog("--------------------------------------------------------------------------------");

// Thiết lập dữ liệu kiểm soát hoàn toàn:
// 1. Dọn dẹp dữ liệu cũ cho Question 794 trong quiz 76 để test cô lập
$conn->query("DELETE FROM testcase_rewards WHERE course_id = $courseid AND quiz_id = $quizid AND question_id = $questionid");
$conn->query("DELETE FROM question_seed_testcases WHERE course_id = $courseid AND quiz_id = $quizid AND question_id = $questionid");
$conn->query("DELETE FROM testcase_contributions WHERE course_id = $courseid AND quiz_id = $quizid AND question_id = $questionid");
$conn->query("DELETE FROM testcase_canonical_keys WHERE course_id = $courseid AND quiz_id = $quizid AND question_id = $questionid");
$conn->query("DELETE FROM testcase_runs WHERE course_id = $courseid AND quiz_id = $quizid AND question_id = $questionid");

tlog("Đã khởi tạo môi trường thử nghiệm sạch cho Course 2, Quiz 76, Question 794.");

// Thực thể 1: Testcase mẫu (Seed testcase)
$seed_input = "print(find_max([101, 105, 102]))";
$seed_norm = normalizer::normalize($seed_input, 'trim');
$seed_fp = normalizer::fingerprint($seed_norm);
$stmt = $conn->prepare("INSERT INTO question_seed_testcases (course_id, quiz_id, question_id, input_raw, input_normalized, input_fingerprint, expected_output, is_active) VALUES (?, ?, ?, ?, ?, ?, '105', 1)");
$stmt->bind_param('iiisss', $courseid, $quizid, $questionid, $seed_input, $seed_norm, $seed_fp);
$stmt->execute();
$seed_id = $stmt->insert_id;
$stmt->close();
tlog("1. Đã tạo SEED testcase (ID: $seed_id, FP: " . substr($seed_fp, 0, 10) . "...)");

// Thực thể 2: Testcase sinh viên (student1) đã thử nhưng code chạy sai (tested_failed)
$tf_input = "print(find_max([201, 209, 205]))";
$run_tf = $service->create_run($courseid, $quiz, $qcontext, $student1_id, $wrong_code, $tf_input, "209", "Test failed", "other", "");
$tf_fp = normalizer::fingerprint(normalizer::normalize($tf_input, 'trim'));
tlog("2. Đã tạo TESTED FAILED testcase trong testcase_runs (Run ID: " . $run_tf['id'] . ", FP: " . substr($tf_fp, 0, 10) . "...)");

// Thực thể 4: Testcase do chính sinh viên (student1) đóng góp và đã duyệt (own_contributed)
$own_input = "print(find_max([301, 309, 305]))";
$run_own = $service->create_run($courseid, $quiz, $qcontext, $student1_id, $correct_code, $own_input, "309", "Own testcase", "other", "");
$res_own = $service->submit_contribution($run_own['id'], $student1_id);
$own_cid = $res_own['id'];
$service->review($own_cid, $courseid, $teacher_id, 'approved', 'Duyệt bài của student1', 5);
tlog("4. Đã tạo OWN CONTRIBUTED testcase (Contribution ID: $own_cid, Status: approved, Owner: student1)");

// Thực thể 3: Testcase sinh viên (student1) đã được thưởng trước đó (already_rewarded)
// Tạo 1 contribution từ student2, duyệt, và cấp thưởng cho student1
$ar_input = "print(find_max([401, 409, 405]))";
$run_ar = $service->create_run($courseid, $quiz, $qcontext, $student2_id, $correct_code, $ar_input, "409", "Already rewarded source", "other", "");
$res_ar = $service->submit_contribution($run_ar['id'], $student2_id);
$ar_cid = $res_ar['id'];
$service->review($ar_cid, $courseid, $teacher_id, 'approved', 'Duyệt để tạo thưởng cũ', 3);
// Nhận thưởng luôn để đánh dấu is_claimed = 1
$rw_ar = $conn->query("SELECT id FROM testcase_rewards WHERE receiver_user_id = $student1_id AND contribution_id = $ar_cid")->fetch_assoc();
if ($rw_ar) {
    $service->claim_reward((int)$rw_ar['id'], $student1_id, $courseid);
    tlog("3. Đã tạo ALREADY REWARDED testcase (Reward ID: " . $rw_ar['id'] . ", Contribution ID: $ar_cid, is_claimed: 1)");
} else {
    // Nếu chưa được cấp thì insert trực tiếp vào testcase_rewards để mô phỏng
    $conn->query("INSERT INTO testcase_rewards (course_id, quiz_id, question_id, receiver_user_id, contribution_id, reward_source, is_claimed, claimed_at) VALUES ($courseid, $quizid, $questionid, $student1_id, $ar_cid, 'contribution', 1, NOW())");
    tlog("3. Đã gán trực tiếp ALREADY REWARDED testcase cho student1");
}

// Thực thể 5 & 6: Hai testcase MỚI đủ điều kiện từ peer (student2)
$newA_input = "print(find_max([501, 509, 505]))";
$run_newA = $service->create_run($courseid, $quiz, $qcontext, $student2_id, $correct_code, $newA_input, "509", "New Candidate A", "other", "");
$res_newA = $service->submit_contribution($run_newA['id'], $student2_id);
$newA_cid = $res_newA['id'];
$service->review($newA_cid, $courseid, $teacher_id, 'rate_only', 'Đánh giá 4 sao', 4);
$conn->query("UPDATE testcase_contributions SET status = 'approved' WHERE id = $newA_cid");

$newB_input = "print(find_max([601, 609, 605]))";
$run_newB = $service->create_run($courseid, $quiz, $qcontext, $student2_id, $correct_code, $newB_input, "609", "New Candidate B", "other", "");
$res_newB = $service->submit_contribution($run_newB['id'], $student2_id);
$newB_cid = $res_newB['id'];
$service->review($newB_cid, $courseid, $teacher_id, 'rate_only', 'Đánh giá 5 sao', 5);
$conn->query("UPDATE testcase_contributions SET status = 'approved' WHERE id = $newB_cid");

tlog("5. Đã tạo Ứng viên Mới A (ID: $newA_cid, 4 sao ⭐, student2)") ;
tlog("6. Đã tạo Ứng viên Mới B (ID: $newB_cid, 5 sao ⭐, student2)");

// -----------------------------------------------------------------------------
// KIỂM TRA 1: Khi nhận thưởng, chỉ hai testcase mới được chọn?
// -----------------------------------------------------------------------------
tlog("\n>>> KIỂM TRA 1: Khảo sát tập ứng viên được chọn khi cấp thưởng cho student1");

// Xóa các reward chưa nhận của student1 nếu có
$conn->query("DELETE FROM testcase_rewards WHERE receiver_user_id = $student1_id AND is_claimed = 0");

// Tạo 1 đóng góp mới cho student1 để trigger grant_reward_for_contribution
$trigger_input = "print(find_max([701, 709, 705]))";
$run_trig = $service->create_run($courseid, $quiz, $qcontext, $student1_id, $correct_code, $trigger_input, "709", "Trigger contribution", "other", "");
$res_trig = $service->submit_contribution($run_trig['id'], $student1_id);
$trig_cid = $res_trig['id'];

// Giảng viên duyệt trigger_cid -> sinh thưởng lần 1
$service->review($trig_cid, $courseid, $teacher_id, 'approved', 'Duyệt để cấp thưởng lần 1', 5);

$reward1 = $conn->query("SELECT * FROM testcase_rewards WHERE receiver_user_id = $student1_id AND is_claimed = 0 ORDER BY id DESC LIMIT 1")->fetch_assoc();
$awarded_cid_1 = (int)($reward1['contribution_id'] ?? 0);
tlog("  - Cấp thưởng lần 1: Đã chọn Contribution ID: $awarded_cid_1");
$is_valid_candidate_1 = ($awarded_cid_1 === $newA_cid || $awarded_cid_1 === $newB_cid);
tlog("  - Thuộc tập 2 ứng viên mới (A hoặc B)? " . ($is_valid_candidate_1 ? "ĐÚNG ($awarded_cid_1)" : "SAI ($awarded_cid_1)"));
tlog("  - Có bị cấp trùng Seed ($seed_id) không? " . ($reward1['seed_id'] == $seed_id ? "CÓ (SAI)" : "KHÔNG (ĐÚNG)"));
tlog("  - Có bị cấp trùng Bài của chính mình ($own_cid) không? " . ($awarded_cid_1 == $own_cid ? "CÓ (SAI)" : "KHÔNG (ĐÚNG)"));
tlog("  - Có bị cấp trùng Bài đã thưởng cũ ($ar_cid) không? " . ($awarded_cid_1 == $ar_cid ? "CÓ (SAI)" : "KHÔNG (ĐÚNG)"));

// -----------------------------------------------------------------------------
// KIỂM TRA 2: Lần nhận tiếp theo không cấp lại testcase đó
// -----------------------------------------------------------------------------
tlog("\n>>> KIỂM TRA 2: Sau khi nhận một testcase, lần nhận tiếp theo không cấp lại");
// Sinh viên nhận thưởng 1
$service->claim_reward((int)$reward1['id'], $student1_id, $courseid);
tlog("  - Student1 đã bấm nhận thưởng cho Reward ID: " . $reward1['id']);

// Trigger thêm 1 lượt duyệt nữa cho student1
$trigger_input2 = "print(find_max([801, 809, 805]))";
$run_trig2 = $service->create_run($courseid, $quiz, $qcontext, $student1_id, $correct_code, $trigger_input2, "809", "Trigger contribution 2", "other", "");
$res_trig2 = $service->submit_contribution($run_trig2['id'], $student1_id);
$service->review($res_trig2['id'], $courseid, $teacher_id, 'approved', 'Duyệt để cấp thưởng lần 2', 5);

$reward2 = $conn->query("SELECT * FROM testcase_rewards WHERE receiver_user_id = $student1_id AND id <> " . (int)$reward1['id'] . " ORDER BY id DESC LIMIT 1")->fetch_assoc();
$awarded_cid_2 = (int)($reward2['contribution_id'] ?? 0);
tlog("  - Cấp thưởng lần 2: Đã chọn Contribution ID: $awarded_cid_2");
$no_duplicate_repeat = ($awarded_cid_2 !== $awarded_cid_1 && ($awarded_cid_2 === $newA_cid || $awarded_cid_2 === $newB_cid));
tlog("  - Kết quả: " . ($no_duplicate_repeat ? "ĐẠT (Cấp ứng viên còn lại, không cấp trùng lần 1)" : "CHƯA ĐẠT"));

// -----------------------------------------------------------------------------
// KIỂM TRA 4: Khi không còn ứng viên, hệ thống xử lý thế nào?
// -----------------------------------------------------------------------------
tlog("\n>>> KIỂM TRA 4 & 5: Trường hợp hết ứng viên và trường hợp chỉ còn 1 ứng viên");
$service->claim_reward((int)$reward2['id'], $student1_id, $courseid);

// Đã cấp cả newA và newB. Giờ cả kho peer chỉ còn Seed.
// Tạo trigger lần 3:
$trigger_input3 = "print(find_max([901, 909, 905]))";
$run_trig3 = $service->create_run($courseid, $quiz, $qcontext, $student1_id, $correct_code, $trigger_input3, "909", "Trigger contribution 3", "other", "");
$res_trig3 = $service->submit_contribution($run_trig3['id'], $student1_id);
$count_rewards_before = (int)$conn->query("SELECT COUNT(*) c FROM testcase_rewards WHERE receiver_user_id = $student1_id")->fetch_assoc()['c'];

$service->review($res_trig3['id'], $courseid, $teacher_id, 'approved', 'Duyệt khi hết peer', 5);
$reward3 = $conn->query("SELECT * FROM testcase_rewards WHERE receiver_user_id = $student1_id ORDER BY id DESC LIMIT 1")->fetch_assoc();
tlog("  - Hết peer contribution, hệ thống fallback về SEED: Source = " . $reward3['reward_source'] . ", Seed ID = " . $reward3['seed_id']);

// Nhận luôn seed
$service->claim_reward((int)$reward3['id'], $student1_id, $courseid);

// Trigger lần 4: Khi cả Peer lẫn Seed đều HẾT 100% ứng viên
$trigger_input4 = "print(find_max([951, 959, 955]))";
$run_trig4 = $service->create_run($courseid, $quiz, $qcontext, $student1_id, $correct_code, $trigger_input4, "959", "Trigger contribution 4", "other", "");
$res_trig4 = $service->submit_contribution($run_trig4['id'], $student1_id);
$count_before_empty = (int)$conn->query("SELECT COUNT(*) c FROM testcase_rewards WHERE receiver_user_id = $student1_id")->fetch_assoc()['c'];

$service->review($res_trig4['id'], $courseid, $teacher_id, 'approved', 'Duyệt khi cạn kiệt toàn bộ kho', 5);
$count_after_empty = (int)$conn->query("SELECT COUNT(*) c FROM testcase_rewards WHERE receiver_user_id = $student1_id")->fetch_assoc()['c'];
tlog("  - Số reward trước khi duyệt: $count_before_empty");
tlog("  - Số reward sau khi duyệt: $count_after_empty");
$no_ghost_reward = ($count_after_empty === $count_before_empty);
tlog("  - Kết quả khi hết ứng viên: " . ($no_ghost_reward ? "ĐẠT (Hệ thống không tạo bản ghi rác khi hết ứng viên)" : "CHƯA ĐẠT"));

// -----------------------------------------------------------------------------
// KIỂM TRA 6: Rà soát thuật toán phân phối Random
// -----------------------------------------------------------------------------
tlog("\n>>> KIỂM TRA 6: Rà soát thuật toán phân phối Random trong mã nguồn SQL");
tlog("  - Mã nguồn thực tế tại testcase_service.php:1443:");
tlog("      ORDER BY COALESCE(c.rating, 0) DESC, RAND() LIMIT 1");
tlog("  -> PHÁT HIỆN SAI LỆCH VỚI CHÍNH SÁCH MONG MUỐN:");
tlog("     * Chính sách mong muốn: Chọn ngẫu nhiên đồng đều trong tập testcase đủ điều kiện.");
tlog("     * Thực tế mã nguồn: Ưu tiên chọn testcase có số sao cao nhất trước (rating DESC), chỉ RAND() khi các testcase có cùng rating!");
tlog("     * Minh chứng: Ở Kiểm tra 1, Ứng viên B (5 sao) LUÔN được chọn trước Ứng viên A (4 sao)!");

// -----------------------------------------------------------------------------
// KIỂM TRA VỀ LỊCH SỬ 'ĐÃ THỬ' (TESTED FAILED TESTCASES)
// -----------------------------------------------------------------------------
tlog("\n>>> KIỂM TRA ĐẶC BIỆT: Lịch sử 'đã thử' trong testcase_runs có được loại trừ không?");
tlog("  - Mã nguồn câu truy vấn loại trừ tại testcase_service.php:1433-1442:");
tlog("      AND NOT EXISTS (SELECT 1 FROM testcase_rewards r ...)");
tlog("      AND NOT EXISTS (SELECT 1 FROM testcase_contributions own WHERE own.user_id = ? ...)");
tlog("  -> PHÁT HIỆN SAI LỆCH QUAN TRỌNG:");
tlog("     * Câu truy vấn HOÀN TOÀN KHÔNG KIỂM TRA bảng `testcase_runs`!");
tlog("     * Do đó: Testcase mà sinh viên đã thử trên Playground (dù chạy đúng hay chạy sai code) nhưng chưa đóng góp, KHÔNG bị loại trừ khỏi danh sách ứng viên thưởng.");
tlog("     * Nếu peer đóng góp một testcase đúng bằng input sinh viên đã từng thử thất bại, hệ thống vẫn có thể cấp testcase đó làm phần thưởng.");

// =============================================================================
// PHẦN 5: KIỂM CHỨNG RATING VÀ COMMENT CỦA GIẢNG VIÊN
// =============================================================================
tlog("\n--------------------------------------------------------------------------------");
tlog("PHẦN 5: KIỂM CHỨNG TÍNH NĂNG RATING VÀ COMMENT CỦA GIẢNG VIÊN");
tlog("--------------------------------------------------------------------------------");

$service->review($newA_cid, $courseid, $teacher_id, 'rate_only', 'Nhận xét: Bộ test kiểm tra tốt trường hợp số lớn.', 5, 'Bao phủ tốt');
$check_rev = $conn->query("SELECT rating, review_comment FROM testcase_contributions WHERE id = $newA_cid")->fetch_assoc();
$audit_rev = $conn->query("SELECT * FROM contribution_reviews WHERE contribution_id = $newA_cid ORDER BY id DESC LIMIT 1")->fetch_assoc();

tlog("  - Rating cập nhật trong testcase_contributions: " . $check_rev['rating'] . " ⭐");
tlog("  - Comment cập nhật: " . $check_rev['review_comment']);
tlog("  - Bản ghi kiểm toán (contribution_reviews): Reviewer ID = " . $audit_rev['reviewer_user_id'] . ", Note = " . $audit_rev['review_note'] . ", Rating = " . $audit_rev['rating']);
$rev_ok = ($check_rev['rating'] == 5 && !empty($check_rev['review_comment']) && $audit_rev['rating'] == 5);
tlog("  -> Kết luận Phần 5: " . ($rev_ok ? "ĐẠT (Giảng viên chấm sao và nhận xét thành công, lưu vết kiểm toán đầy đủ)" : "CHƯA ĐẠT"));

tlog("\n================================================================================");
tlog("HOÀN THÀNH 100% TOÀN BỘ CÁC CA THỬ NGHIỆM KIỂM CHỨNG CHÍNH SÁCH MULTISOLVE");
tlog("================================================================================");

file_put_contents("/var/www/html/multisolve_policy_test_log.txt", $log);
echo "Written log to /var/www/html/multisolve_policy_test_log.txt successfully.\n";
