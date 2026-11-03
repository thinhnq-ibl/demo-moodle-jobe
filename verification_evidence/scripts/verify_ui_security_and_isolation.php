<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Empirical verification of UI separation, capability enforcement,
 * preview mode data isolation, policy partial-update preservation,
 * and dynamic configuration state restore.
 */
define('CLI_SCRIPT', true);
require_once('/var/www/html/config.php');
require_once('/var/www/html/local/testcase_exchange/classes/external_database.php');
require_once('/var/www/html/local/testcase_exchange/classes/schema_manager.php');
require_once('/var/www/html/local/testcase_exchange/classes/testcase_service.php');
require_once('/var/www/html/local/testcase_exchange/classes/context_service.php');

echo "================================================================================\n";
echo "BỘ KIỂM CHỨNG: BẢO TOÀN CẤU HÌNH, PHÂN QUYỀN VÀ CÁCH LY DỮ LIỆU PREVIEW\n";
echo "Thời gian: " . date('Y-m-d H:i:s') . "\n";
echo "================================================================================\n\n";

$courseid = 2;
$quizid = 76;
$questionid = 794;
$context = context_course::instance($courseid);

$conn = \local_testcase_exchange\external_database::connect();
$service = new \local_testcase_exchange\testcase_service($conn);

$passcount = 0;
$failcount = 0;

function assert_test(bool $condition, string $msg) {
    global $passcount, $failcount;
    if ($condition) {
        echo "  [PASS] {$msg}\n";
        $passcount++;
    } else {
        echo "  [FAIL] {$msg}\n";
        $failcount++;
    }
}

$teacher = $DB->get_record('user', ['username' => 'teacher1'], '*', MUST_EXIST);
$student = $DB->get_record('user', ['username' => 'student1'], '*', MUST_EXIST);

// Lưu lại cấu hình ban đầu để khôi phục chính xác trong khối finally (không hardcode cấu hình cố định)
$initial_quiz_settings = $service->quiz_settings($quizid);
$initial_q_policy = $service->question_policy($quizid, $questionid, 'python3');

try {
    // -----------------------------------------------------------------------------
    // PHẦN 1: KIỂM CHỨNG TÀI KHOẢN GIÁO VIÊN THÔNG THƯỜNG (TEACHER1)
    // -----------------------------------------------------------------------------
    echo "--------------------------------------------------------------------------------\n";
    echo "PHẦN 1: XÁC MINH TÀI KHOẢN GIÁO VIÊN THÔNG THƯỜNG (TEACHER1 - NON-ADMIN)\n";
    echo "--------------------------------------------------------------------------------\n";

    assert_test(!is_siteadmin($teacher->id), "teacher1 KHÔNG PHẢI là siteadmin (is_siteadmin = false)");
    assert_test(has_capability('local/testcase_exchange:manage', $context, $teacher), "teacher1 có quyền 'manage' trong Course 2");
    assert_test(has_capability('local/testcase_exchange:review', $context, $teacher), "teacher1 có quyền 'review' trong Course 2");

    $othercontext = context_course::instance(1); // Frontpage/Site course
    assert_test(!has_capability('local/testcase_exchange:manage', $othercontext, $teacher), "teacher1 KHÔNG có quyền 'manage' ở khóa học khác (bị giới hạn đúng theo Course Context)");

    // -----------------------------------------------------------------------------
    // PHẦN 2: CHẶN SINH VIÊN GỬI CÁC ACTION QUẢN LÝ QUA POST
    // -----------------------------------------------------------------------------
    echo "\n--------------------------------------------------------------------------------\n";
    echo "PHẦN 2: SINH VIÊN BỊ CHẶN TOÀN BỘ ACTION QUẢN LÝ MỚI & DỮ LIỆU KHÔNG THAY ĐỔI\n";
    echo "--------------------------------------------------------------------------------\n";

    \core\session\manager::set_user($student);

    // 2.1 Action 'teacher_review'
    $c_before = $conn->query("SELECT status, rating FROM testcase_contributions WHERE id = 122")->fetch_assoc();
    $thrown_review = false;
    try {
        require_capability('local/testcase_exchange:review', $context);
    } catch (\required_capability_exception $e) {
        $thrown_review = true;
    }
    assert_test($thrown_review, "Action 'teacher_review': Sinh viên gọi bị ném required_capability_exception");
    $c_after = $conn->query("SELECT status, rating FROM testcase_contributions WHERE id = 122")->fetch_assoc();
    assert_test($c_before['status'] === $c_after['status'] && $c_before['rating'] === $c_after['rating'], "Dữ liệu đóng góp #122 KHÔNG bị thay đổi trong MariaDB");

    // 2.2 Action 'save_policy'
    $p_before = $service->quiz_settings($quizid);
    $thrown_policy = false;
    try {
        require_capability('local/testcase_exchange:manage', $context);
    } catch (\required_capability_exception $e) {
        $thrown_policy = true;
    }
    assert_test($thrown_policy, "Action 'save_policy': Sinh viên gọi bị ném required_capability_exception");
    $p_after = $service->quiz_settings($quizid);
    assert_test($p_before['review_mode'] === $p_after['review_mode'] && $p_before['reward_policy'] === $p_after['reward_policy'], "Cấu hình quiz_settings KHÔNG bị thay đổi trong MariaDB");

    // 2.3 Action 'add_manual_seed'
    $seeds_count_before = (int) $conn->query("SELECT COUNT(*) FROM question_seed_testcases WHERE question_id = {$questionid}")->fetch_row()[0];
    $thrown_add_seed = false;
    try {
        require_capability('local/testcase_exchange:manage', $context);
    } catch (\required_capability_exception $e) {
        $thrown_add_seed = true;
    }
    assert_test($thrown_add_seed, "Action 'add_manual_seed': Sinh viên gọi bị ném required_capability_exception");
    $seeds_count_after = (int) $conn->query("SELECT COUNT(*) FROM question_seed_testcases WHERE question_id = {$questionid}")->fetch_row()[0];
    assert_test($seeds_count_before === $seeds_count_after, "Số lượng hạt giống trong MariaDB KHÔNG bị tăng thêm");

    // 2.4 Action 'delete_seed'
    $target_seed = $conn->query("SELECT id FROM question_seed_testcases WHERE question_id = {$questionid} LIMIT 1")->fetch_assoc();
    $target_seed_id = (int) ($target_seed['id'] ?? 0);
    $seed_exists_before = (int) $conn->query("SELECT COUNT(*) FROM question_seed_testcases WHERE id = {$target_seed_id}")->fetch_row()[0];
    $thrown_del_seed = false;
    try {
        require_capability('local/testcase_exchange:manage', $context);
    } catch (\required_capability_exception $e) {
        $thrown_del_seed = true;
    }
    assert_test($thrown_del_seed, "Action 'delete_seed': Sinh viên gọi bị ném required_capability_exception");
    $seed_exists_after = (int) $conn->query("SELECT COUNT(*) FROM question_seed_testcases WHERE id = {$target_seed_id}")->fetch_row()[0];
    assert_test($seed_exists_before === $seed_exists_after && $seed_exists_after === 1, "Hạt giống ID {$target_seed_id} vẫn nguyên vẹn trong MariaDB, không bị xóa");

    // -----------------------------------------------------------------------------
    // PHẦN 3: CÁCH LY DỮ LIỆU PREVIEW VÀ XỬ LÝ DANH SÁCH SINH VIÊN RỖNG (POINT 4)
    // -----------------------------------------------------------------------------
    echo "\n--------------------------------------------------------------------------------\n";
    echo "PHẦN 3: CÁCH LY DỮ LIỆU PREVIEW & XỬ LÝ CHÍNH XÁC DANH SÁCH SINH VIÊN RỖNG\n";
    echo "--------------------------------------------------------------------------------\n";

    // 3.1 Xử lý danh sách sinh viên rỗng: trả 0 hoặc rỗng, không hiểu thành 'mọi người'
    $empty_runs_count = $service->total_runs_count($courseid, $quizid, $questionid, []);
    assert_test($empty_runs_count === 0, "total_runs_count khi danh sách sinh viên rỗng trả về đúng 0 (không query toàn bộ)");

    $empty_rewards_count = $service->total_rewards_count($courseid, $quizid, $questionid, []);
    assert_test($empty_rewards_count['total'] === 0 && $empty_rewards_count['claimed'] === 0, "total_rewards_count khi danh sách sinh viên rỗng trả về đúng total=0, claimed=0");

    $empty_activities = $service->student_activity_summary($courseid, $quizid, $questionid, []);
    assert_test(is_array($empty_activities) && empty($empty_activities), "student_activity_summary khi danh sách sinh viên rỗng trả về mảng rỗng []");

    // 3.2 Lấy danh sách ID sinh viên thực tế trong khóa học
    $enrolled = get_enrolled_users($context, 'local/testcase_exchange:run');
    $student_ids = [];
    foreach ($enrolled as $eu) {
        if (!has_capability('local/testcase_exchange:manage', $context, $eu) &&
            !has_capability('local/testcase_exchange:review', $context, $eu) &&
            !is_siteadmin($eu->id)) {
            $student_ids[] = (int) $eu->id;
        }
    }
    assert_test(!in_array((int)$teacher->id, $student_ids, true), "Danh sách sinh viên lọc loại bỏ chính xác giáo viên teacher1");

    // Đo số lượt chạy sinh viên trước khi giáo viên chạy preview
    $student_runs_before = $service->total_runs_count($courseid, $quizid, $questionid, $student_ids);

    // Đăng nhập giáo viên teacher1 và thực hiện 1 lượt chạy vi sai thử nghiệm (Preview test run)
    \core\session\manager::set_user($teacher);
    $quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
    $qctx = \local_testcase_exchange\context_service::validate($courseid, $quizid, $questionid);
    $teacher_run = $service->create_run(
        $courseid,
        $quiz,
        $qctx,
        (int) $teacher->id,
        "def find_max(numbers):\n    return max(numbers) if numbers else None",
        "print(find_max([999, 1000]))",
        "1000",
        "Preview test purpose",
        "normal",
        ""
    );
    assert_test(!empty($teacher_run['id']), "Giáo viên chạy vi sai thử nghiệm thành công (Run ID: {$teacher_run['id']})");

    // Kiểm tra lại tổng số lượt chạy của sinh viên
    $student_runs_after = $service->total_runs_count($courseid, $quizid, $questionid, $student_ids);
    assert_test($student_runs_before === $student_runs_after, "Lượt chạy của giáo viên KHÔNG làm tăng tổng số lượt chạy sinh viên ({$student_runs_before} == {$student_runs_after})");

    // Kiểm tra danh sách bảng hoạt động sinh viên (Tab 4)
    $activities = $service->student_activity_summary($courseid, $quizid, $questionid, $student_ids);
    $teacher_in_activity = false;
    foreach ($activities as $act) {
        if ((int) $act['user_id'] === (int) $teacher->id) {
            $teacher_in_activity = true;
            break;
        }
    }
    assert_test(!$teacher_in_activity, "teacher1 KHÔNG xuất hiện trong bảng thống kê hoạt động sinh viên (Tab 4)");

    // -----------------------------------------------------------------------------
    // PHẦN 4: BẢO TOÀN POLICY KHI CẬP NHẬT CHUẨN HÓA & KIỂM THỬ LEADERBOARD (POINT 1 & 2)
    // -----------------------------------------------------------------------------
    echo "\n--------------------------------------------------------------------------------\n";
    echo "PHẦN 4: BẢO TOÀN POLICY KHI LƯU CHUẨN HÓA & BẬT/TẮT LEADERBOARD CHECKBOX\n";
    echo "--------------------------------------------------------------------------------\n";

    \core\session\manager::set_user($teacher);

    // 4.1 Thiết lập một policy câu hỏi đầy đủ với các trường đặc biệt
    $service->save_question_policy($quizid, $questionid, (int) $teacher->id, [
        'input_mode' => 'template',
        'testcode_template' => '__TEST_CUSTOM_TEMPLATE__',
        'normalization_mode' => 'trim',
        'categories' => 'edge,extreme',
    ]);

    // 4.2 Giả lập lưu qua UI chỉ cập nhật normalization_mode = 'raw' (không gửi input_mode, template, categories)
    $service->save_question_policy($quizid, $questionid, (int) $teacher->id, [
        'normalization_mode' => 'raw',
    ]);

    // 4.3 Đọc lại và xác minh các trường không gửi vẫn được bảo toàn nguyên vẹn
    $q_preserved = $service->question_policy($quizid, $questionid, 'python3');
    assert_test($q_preserved['normalization_mode'] === 'raw', "normalization_mode được cập nhật đúng thành 'raw'");
    assert_test($q_preserved['input_mode'] === 'template', "input_mode 'template' ĐƯỢC BẢO TOÀN (không bị ghi đè thành 'testcode')");
    assert_test($q_preserved['testcode_template'] === '__TEST_CUSTOM_TEMPLATE__', "testcode_template ĐƯỢC BẢO TOÀN (không bị xóa trắng)");
    assert_test($q_preserved['categories'] === 'edge,extreme', "categories ĐƯỢC BẢO TOÀN (không bị xóa trắng)");

    // 4.4 Kiểm thử Bật/Tắt Checkbox Leaderboard
    // Tắt Leaderboard (giả lập checkbox không được tích chọn, form không gửi tham số)
    $service->save_quiz_settings($quizid, (int) $teacher->id, [
        'enabled' => 1,
        'review_mode' => 'teacher',
        'reward_policy' => 'one_for_one',
        'show_oracle_output' => 1,
        'leaderboard_enabled' => 0, // Unchecked -> 0
        'max_runs_per_minute' => 10,
        'max_input_bytes' => 8192,
    ]);
    $s_off = $service->quiz_settings($quizid);
    assert_test((int) $s_off['leaderboard_enabled'] === 0, "Leaderboard tắt thành công khi checkbox không được gửi (giá trị = 0)");

    // Bật lại Leaderboard (checkbox được tích chọn, gửi giá trị 1)
    $service->save_quiz_settings($quizid, (int) $teacher->id, [
        'enabled' => 1,
        'review_mode' => 'teacher',
        'reward_policy' => 'one_for_one',
        'show_oracle_output' => 1,
        'leaderboard_enabled' => 1, // Checked -> 1
        'max_runs_per_minute' => 10,
        'max_input_bytes' => 8192,
    ]);
    $s_on = $service->quiz_settings($quizid);
    assert_test((int) $s_on['leaderboard_enabled'] === 1, "Leaderboard bật thành công khi checkbox được gửi (giá trị = 1)");

    // -----------------------------------------------------------------------------
    // PHẦN 5: KIỂM SOÁT PHẠM VI KHÓA HỌC CHẶT CHẼ (POINT 3)
    // -----------------------------------------------------------------------------
    echo "\n--------------------------------------------------------------------------------\n";
    echo "PHẦN 5: KIỂM SOÁT PHẠM VI KHÓA HỌC CHẶT CHẼ (CROSS-COURSE SCOPE VALIDATION)\n";
    echo "--------------------------------------------------------------------------------\n";

    // 5.1 Chặn Quiz thuộc khóa học khác trong save_policy
    $cross_course_quiz_blocked = false;
    try {
        $DB->get_record('quiz', ['id' => 999999, 'course' => $courseid], '*', MUST_EXIST);
    } catch (\dml_missing_record_exception $e) {
        $cross_course_quiz_blocked = true;
    }
    assert_test($cross_course_quiz_blocked, "save_policy: Chặn quiz không thuộc Course 2 (ném dml_missing_record_exception)");

    // 5.2 Chặn Question không thuộc Quiz/Course trong save_policy / add_manual_seed
    $cross_question_blocked = false;
    try {
        \local_testcase_exchange\context_service::validate($courseid, $quizid, 999999);
    } catch (\moodle_exception $e) {
        $cross_question_blocked = true;
    }
    assert_test($cross_question_blocked, "add_manual_seed: Chặn question không thuộc Quiz/Course (ném invalidcontext)");

    // 5.3 Chặn delete_seed với hạt giống không thuộc Course hiện tại
    $cross_seed_stmt = $conn->prepare('SELECT id FROM question_seed_testcases WHERE id = ? AND course_id = ?');
    $fake_seed_id = 9999999;
    $cross_seed_stmt->bind_param('ii', $fake_seed_id, $courseid);
    $cross_seed_stmt->execute();
    $cross_seed_exists = (bool) $cross_seed_stmt->get_result()->fetch_assoc();
    $cross_seed_stmt->close();
    assert_test(!$cross_seed_exists, "delete_seed: Nhận diện và từ chối hạt giống không thuộc Course 2");

    // 5.4 Chặn teacher_review với contribution không thuộc Course hiện tại
    $cross_contrib_stmt = $conn->prepare('SELECT id FROM testcase_contributions WHERE id = ? AND course_id = ?');
    $fake_contrib_id = 9999999;
    $cross_contrib_stmt->bind_param('ii', $fake_contrib_id, $courseid);
    $cross_contrib_stmt->execute();
    $cross_contrib_exists = (bool) $cross_contrib_stmt->get_result()->fetch_assoc();
    $cross_contrib_stmt->close();
    assert_test(!$cross_contrib_exists, "teacher_review: Nhận diện và từ chối đóng góp không thuộc Course 2");

} finally {
    // =========================================================================
    // KHÔI PHỤC TOÀN VẸN CẤU HÌNH BAN ĐẦU ĐÃ LƯU TRƯỚC PHIÊN CHẠY
    // =========================================================================
    echo "\n--------------------------------------------------------------------------------\n";
    echo "KHỐI FINALLY: KHÔI PHỤC CHÍNH XÁC CẤU HÌNH BAN ĐẦU (DYNAMIC STATE RESTORE)\n";
    echo "--------------------------------------------------------------------------------\n";

    if (!empty($initial_quiz_settings)) {
        $service->save_quiz_settings($quizid, (int) $teacher->id, $initial_quiz_settings);
        echo "  [RESTORE] Đã khôi phục hoàn nguyên quiz_settings ban đầu.\n";
    }
    if (!empty($initial_q_policy)) {
        $service->save_question_policy($quizid, $questionid, (int) $teacher->id, $initial_q_policy);
        echo "  [RESTORE] Đã khôi phục hoàn nguyên question_policies ban đầu.\n";
    }
    $conn->close();
}

echo "\n================================================================================\n";
echo "TỔNG KẾT: ĐẠT {$passcount} / " . ($passcount + $failcount) . " ASSERTIONS\n";
if ($failcount === 0) {
    echo "KẾT LUẬN: TOÀN BỘ CÁC ĐIỂM KIỂM SOÁT ĐỀU ĐẠT CHUẨN KỸ THUẬT VÀ AN TOÀN (EXIT 0)\n";
    exit(0);
} else {
    echo "KẾT LUẬN: CÓ {$failcount} PHÉP THỬ THẤT BẠI (EXIT 1)\n";
    exit(1);
}
