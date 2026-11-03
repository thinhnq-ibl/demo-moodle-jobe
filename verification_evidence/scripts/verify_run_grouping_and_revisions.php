<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Verification of Differential Test Run Grouping across Code Revisions:
 * 1. Preservation of complete run history and "Nạp code" buttons across all revisions.
 * 2. Duplicate testcase grouping by canonical scope (question_id:input_fingerprint).
 * 3. Contribution gate displaying button ONLY on latest eligible run; older eligible runs link to representative run.
 * 4. Fallback: If latest run fails (e.g. code bug), fallback to nearest eligible older run without losing contribution opportunity.
 * 5. Contributed state: Once contributed, all rows in the group display "Đã đóng góp #... (<status>)" replacing the button.
 * 6. Repository duplicates: Matches with seed bank display "Trùng testcase Đề thi"; matches with peer bank display "Đã có trong kho (#...)".
 * 7. Backend safety: Resubmitting from an older run or reload does not cause duplicate contribution records or inconsistent state.
 */
define('CLI_SCRIPT', true);
require_once('/var/www/html/config.php');
require_once('/var/www/html/local/testcase_exchange/classes/external_database.php');
require_once('/var/www/html/local/testcase_exchange/classes/schema_manager.php');
require_once('/var/www/html/local/testcase_exchange/classes/testcase_service.php');
require_once('/var/www/html/local/testcase_exchange/classes/context_service.php');
require_once('/var/www/html/local/testcase_exchange/classes/normalizer.php');

echo "================================================================================\n";
echo "BỘ KIỂM CHỨNG: XỬ LÝ NHÓM TESTCASE TRÙNG QUA NHIỀU PHIÊN BẢN CODE (RUN GROUPING)\n";
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
$student1 = $DB->get_record('user', ['username' => 'student1'], '*', MUST_EXIST);
$student2 = $DB->get_record('user', ['username' => 'student2'], '*', MUST_EXIST);

$created_run_ids = [];
$created_contrib_ids = [];
$created_canonical_keys = [];

// Helper to simulate view data preparation as implemented in index.php
function prepare_student_view_runs(\local_testcase_exchange\testcase_service $service, int $courseid, int $userid, bool $cancontribute = true): array {
    $runs = $service->runs_for_user($courseid, $userid);
    $rawcontributions = $service->contributions_for_user($courseid, $userid);
    $contributions = !empty($rawcontributions) ?
        \local_testcase_exchange\context_service::enrich_contributions($rawcontributions, $courseid) : [];
    
    $contributionbyrun = [];
    $user_contrib_by_fp = [];
    foreach ($contributions as $contribution) {
        $contributionbyrun[(int) $contribution['run_id']] = $contribution;
        $fp = (string) ($contribution['input_fingerprint'] ?? '');
        if ($fp !== '') {
            $key = (int) $contribution['question_id'] . ':' . $fp;
            if (!isset($user_contrib_by_fp[$key])) {
                $user_contrib_by_fp[$key] = $contribution;
            } else if (($user_contrib_by_fp[$key]['status'] ?? '') === 'duplicate' && ($contribution['status'] ?? '') !== 'duplicate') {
                $user_contrib_by_fp[$key] = $contribution;
            }
        }
    }
    $existingfingerprints = $service->existing_fingerprints_for_course($courseid);

    // Step 1: Pre-evaluate individual run metrics & identify newest eligible run per duplicate group
    $group_rep_run_id = [];
    foreach ($runs as &$run) {
        $run['show_oracle'] = false;
        $oracle_valid = ($run['oracle_outcome'] === 'success');
        $run['oracle_valid'] = $oracle_valid;
        $student_out = trim((string) ($run['student_run_output'] ?? ''));
        $predicted_out = trim((string) ($run['predicted_output'] ?? ''));
        $oracle_out = trim((string) ($run['oracle_output'] ?? ''));

        $run['has_student_output'] = ($student_out !== '');
        $run['student_matches_expected'] = ($student_out !== '' && $student_out === $predicted_out);
        $run['student_differs_expected'] = ($student_out !== '' && $student_out !== $predicted_out);

        $testcase_valid = ($oracle_valid && $predicted_out !== '' && $predicted_out === $oracle_out);
        $run['expected_matches_oracle'] = $testcase_valid;

        $student_passed_test = ($run['student_outcome'] === 'success' && $student_out !== '' && $student_out === $oracle_out);
        $run['student_passed_test'] = $student_passed_test;

        $is_eligible = ($cancontribute && $testcase_valid && $student_passed_test);
        $run['is_eligible'] = $is_eligible;

        $run_fp = (string) ($run['input_fingerprint'] ?? '');
        $fp_key = (int) $run['question_id'] . ':' . $run_fp;
        $run['group_key'] = $fp_key;

        if ($is_eligible && $run_fp !== '' && !isset($group_rep_run_id[$fp_key])) {
            $group_rep_run_id[$fp_key] = (int) $run['id'];
        }
    }
    unset($run);

    // Step 2: Presentation decoration adhering to duplicate grouping & contribution gates
    foreach ($runs as &$run) {
        $run_id = (int) $run['id'];
        $fp_key = $run['group_key'];
        $user_contrib = $user_contrib_by_fp[$fp_key] ?? null;
        $existing_canonical = ($run['input_fingerprint'] !== '' && isset($existingfingerprints[$fp_key])) ? $existingfingerprints[$fp_key] : null;
        $rep_id = $group_rep_run_id[$fp_key] ?? null;

        $run['can_contribute'] = false;
        $run['is_older_duplicate'] = false;
        $run['rep_id'] = null;
        $run['has_eligible_peer'] = false;
        $run['pending_code_fix'] = false;

        if ($user_contrib) {
            $status_lbl = $user_contrib['status_label'] ?? $user_contrib['status'];
            $run['display_status'] = 'Đã đóng góp #' . $user_contrib['id'] . ' (' . $status_lbl . ')';
            $run['display_status_class'] = 'badge badge-info p-1';
        } else if ($existing_canonical) {
            $label = ($existing_canonical['type'] === 'seed') ?
                'Trùng testcase Đề thi' :
                ('Đã có trong kho (#' . $existing_canonical['id'] . ')');
            $run['display_status'] = $label;
            $run['display_status_class'] = 'badge badge-secondary p-1';
        } else if ($rep_id !== null && $run_id === $rep_id) {
            $run['can_contribute'] = true;
            $run['display_status'] = 'Đủ điều kiện đóng góp!';
            $run['display_status_class'] = 'badge badge-success p-1';
        } else if ($run['is_eligible']) {
            $run['is_older_duplicate'] = true;
            $run['rep_id'] = $rep_id;
            $run['display_status'] = 'Trùng testcase ở lượt #' . $rep_id;
            $run['display_status_class'] = 'badge badge-secondary p-1';
        } else {
            if (!$run['oracle_valid']) {
                $run['display_status'] = 'Lỗi thực thi đề bài';
                $run['display_status_class'] = 'badge badge-danger p-1';
            } else if (!$run['expected_matches_oracle']) {
                $run['display_status'] = 'Kỳ vọng lệch Lời giải tham chiếu';
                $run['display_status_class'] = 'badge badge-warning text-dark p-1';
            } else {
                $run['display_status'] = 'Code chưa vượt qua testcase này';
                $run['display_status_class'] = 'badge badge-info p-1';
                $run['pending_code_fix'] = true;
                $run['pending_fix_message'] = 'Kết quả mong đợi đã được xác nhận. Chương trình hiện tại chưa vượt qua testcase này. Hãy sửa chương trình và chạy lại để đủ điều kiện đóng góp.';
            }

            if ($rep_id !== null && $rep_id !== $run_id) {
                $run['has_eligible_peer'] = true;
                $run['rep_id'] = $rep_id;
            }
        }

        $run['has_revision'] = !empty($run['revision_step']);
        $run['revision_badge'] = !empty($run['revision_step']) ? 'Code v' . $run['revision_step'] : null;
        $run['created_at_formatted'] = date('H:i:s d/m/Y', strtotime($run['created_at']));
        $run['has_code_snapshot'] = !empty($run['student_code_snapshot']);
    }
    unset($run);

    return $runs;
}

try {
    $entropy = time() % 10000;
    $test_input = "print(find_max([{$entropy}, " . ($entropy + 42) . ", " . ($entropy - 10) . "]))";
    $expected_output = (string) ($entropy + 42);
    $fp = \local_testcase_exchange\normalizer::fingerprint(\local_testcase_exchange\normalizer::normalize($test_input, 'trim'));
    $expected_group_key = "{$questionid}:{$fp}";

    echo "Dữ liệu kiểm thử nhóm testcase:\n";
    echo "  - Question ID: {$questionid}\n";
    echo "  - Testcase Input: {$test_input}\n";
    echo "  - Expected Output: {$expected_output}\n";
    echo "  - Fingerprint: {$fp}\n";
    echo "  - Group Key: {$expected_group_key}\n\n";

    $quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
    $qctx = \local_testcase_exchange\context_service::validate($courseid, $quizid, $questionid);

    // -----------------------------------------------------------------------------
    // GIAI ĐOẠN 1: TẠO 3 LƯỢT CHẠY CÙNG TESTCASE VỚI 3 PHIÊN BẢN CODE KHÁC NHAU
    // -----------------------------------------------------------------------------
    echo "--------------------------------------------------------------------------------\n";
    echo "GIAI ĐOẠN 1: TẠO 3 LƯỢT CHẠY CÙNG TESTCASE VỚI 3 PHIÊN BẢN CODE (CODE v1, v2, v3)\n";
    echo "--------------------------------------------------------------------------------\n";

    // Run 1: Code v1
    $code_v1 = "# Revision 1\ndef find_max(numbers):\n    return max(numbers)\n";
    $run1 = $service->create_run(
        $courseid,
        $quiz,
        $qctx,
        (int) $student1->id,
        $code_v1,
        $test_input,
        $expected_output,
        'Test case revision 1',
        'normal',
        'Reflection v1'
    );
    $created_run_ids[] = (int) $run1['id'];
    echo "  -> Đã tạo Run 1 (#{$run1['id']}) với Code v1\n";

    // Run 2: Code v2 (different code formatting/implementation)
    $code_v2 = "# Revision 2 (Loop implementation)\ndef find_max(numbers):\n    curr = numbers[0]\n    for n in numbers:\n        if n > curr:\n            curr = n\n    return curr\n";
    $run2 = $service->create_run(
        $courseid,
        $quiz,
        $qctx,
        (int) $student1->id,
        $code_v2,
        $test_input,
        $expected_output,
        'Test case revision 2',
        'normal',
        'Reflection v2'
    );
    $created_run_ids[] = (int) $run2['id'];
    echo "  -> Đã tạo Run 2 (#{$run2['id']}) với Code v2\n";

    // Run 3: Code v3 (optimized implementation)
    $code_v3 = "# Revision 3 (Optimized built-in)\ndef find_max(numbers):\n    # v3 built-in max with type check\n    return max(numbers)\n";
    $run3 = $service->create_run(
        $courseid,
        $quiz,
        $qctx,
        (int) $student1->id,
        $code_v3,
        $test_input,
        $expected_output,
        'Test case revision 3',
        'normal',
        'Reflection v3'
    );
    $created_run_ids[] = (int) $run3['id'];
    echo "  -> Đã tạo Run 3 (#{$run3['id']}) với Code v3\n";

    // Kiểm tra view sau 3 lượt chạy
    $viewruns = prepare_student_view_runs($service, $courseid, (int) $student1->id);
    $viewruns_by_id = [];
    foreach ($viewruns as $r) {
        $viewruns_by_id[(int) $r['id']] = $r;
    }

    assert_test(isset($viewruns_by_id[$run1['id']]) && isset($viewruns_by_id[$run2['id']]) && isset($viewruns_by_id[$run3['id']]),
        "Yêu cầu 1: Giữ nguyên lịch sử toàn bộ các lượt chạy trong bảng (Run #{$run1['id']}, #{$run2['id']}, #{$run3['id']} đều hiển thị)");

    assert_test(!empty($viewruns_by_id[$run1['id']]['has_code_snapshot']) &&
                !empty($viewruns_by_id[$run2['id']]['has_code_snapshot']) &&
                !empty($viewruns_by_id[$run3['id']]['has_code_snapshot']),
        "Yêu cầu 1: Nút 'Nạp code' và snapshot mã nguồn được bảo toàn đầy đủ trên tất cả các dòng");

    $r1 = $viewruns_by_id[$run1['id']];
    $r2 = $viewruns_by_id[$run2['id']];
    $r3 = $viewruns_by_id[$run3['id']];

    assert_test($r1['group_key'] === $expected_group_key && $r2['group_key'] === $expected_group_key && $r3['group_key'] === $expected_group_key,
        "Yêu cầu 2: Xác định nhóm testcase trùng theo đúng khóa và phạm vi lọc backend (khóa: {$expected_group_key})");

    assert_test($r3['can_contribute'] === true && $r3['display_status'] === 'Đủ điều kiện đóng góp!',
        "Yêu cầu 3: Lượt mới nhất đủ điều kiện (#{$run3['id']}) hiển thị nút 'Đề xuất đóng góp'");

    assert_test($r2['can_contribute'] === false && $r2['is_older_duplicate'] === true && $r2['rep_id'] === (int) $run3['id'] && $r2['display_status'] === "Trùng testcase ở lượt #{$run3['id']}",
        "Yêu cầu 3: Lượt cũ (#{$run2['id']}) ghi 'Trùng testcase ở lượt #{$run3['id']}' và dẫn tới lượt đại diện #{$run3['id']}");

    assert_test($r1['can_contribute'] === false && $r1['is_older_duplicate'] === true && $r1['rep_id'] === (int) $run3['id'] && $r1['display_status'] === "Trùng testcase ở lượt #{$run3['id']}",
        "Yêu cầu 3: Lượt cũ (#{$run1['id']}) ghi 'Trùng testcase ở lượt #{$run3['id']}' và dẫn tới lượt đại diện #{$run3['id']}");


    // -----------------------------------------------------------------------------
    // GIAI ĐOẠN 2: LƯỢT MỚI NHẤT BỊ LỖI (CODE v4) - FALLBACK VỀ LƯỢT ĐỦ ĐIỀU KIỆN GẦN NHẤT
    // -----------------------------------------------------------------------------
    echo "\n--------------------------------------------------------------------------------\n";
    echo "GIAI ĐOẠN 2: LƯỢT MỚI NHẤT BỊ LỖI (CODE v4) - FALLBACK VỀ LƯỢT ĐỦ ĐIỀU KIỆN GẦN NHẤT\n";
    echo "--------------------------------------------------------------------------------\n";

    // Run 4: Broken code that produces wrong output or fails
    $code_v4_broken = "# Revision 4 (Broken code)\ndef find_max(numbers):\n    return -999999\n";
    $run4 = $service->create_run(
        $courseid,
        $quiz,
        $qctx,
        (int) $student1->id,
        $code_v4_broken,
        $test_input,
        $expected_output,
        'Test case revision 4 broken',
        'normal',
        'Reflection v4'
    );
    $created_run_ids[] = (int) $run4['id'];
    echo "  -> Đã tạo Run 4 (#{$run4['id']}) với Code v4 bị lỗi\n";

    $viewruns = prepare_student_view_runs($service, $courseid, (int) $student1->id);
    $viewruns_by_id = [];
    foreach ($viewruns as $r) {
        $viewruns_by_id[(int) $r['id']] = $r;
    }

    $r4 = $viewruns_by_id[$run4['id']];
    $r3 = $viewruns_by_id[$run3['id']];
    $r2 = $viewruns_by_id[$run2['id']];
    $r1 = $viewruns_by_id[$run1['id']];

    assert_test($r4['can_contribute'] === false && $r4['display_status'] === 'Code chưa vượt qua testcase này',
        "Yêu cầu 4: Lượt mới nhất (#{$run4['id']}) không đủ điều kiện hiển thị thông báo lỗi 'Code chưa vượt qua testcase này'");

    assert_test($r4['has_eligible_peer'] === true && $r4['rep_id'] === (int) $run3['id'],
        "Yêu cầu 4: Lượt lỗi (#{$run4['id']}) nhận diện và liên kết tới lượt đạt gần nhất #{$run3['id']}");

    assert_test($r3['can_contribute'] === true && $r3['display_status'] === 'Đủ điều kiện đóng góp!',
        "Yêu cầu 4: Lượt đủ điều kiện gần nhất (#{$run3['id']}) ĐƯỢC GIỮ LẠI NÚT 'Đề xuất đóng góp', không làm mất quyền đóng góp");

    assert_test($r2['can_contribute'] === false && $r2['rep_id'] === (int) $run3['id'],
        "Yêu cầu 4: Lượt #{$run2['id']} tiếp tục dẫn tới lượt đại diện đủ điều kiện #{$run3['id']}");


    // -----------------------------------------------------------------------------
    // GIAI ĐOẠN 3: SINH VIÊN ĐÓNG GÓP TỪ LƯỢT ĐẠI DIỆN (#3) & TẢI LẠI TRANG
    // -----------------------------------------------------------------------------
    echo "\n--------------------------------------------------------------------------------\n";
    echo "GIAI ĐOẠN 3: SINH VIÊN ĐỀ XUẤT ĐÓNG GÓP TỪ LƯỢT #{$run3['id']} & TẢI LẠI TRANG\n";
    echo "--------------------------------------------------------------------------------\n";

    $contrib_res = $service->submit_contribution((int) $run3['id'], (int) $student1->id);
    $contrib_id = (int) $contrib_res['id'];
    $created_contrib_ids[] = $contrib_id;
    $created_canonical_keys[] = $expected_group_key;
    echo "  -> Đã tạo đóng góp #{$contrib_id} thành công với trạng thái: '{$contrib_res['status']}'\n";

    // Giả lập reload trang
    $viewruns = prepare_student_view_runs($service, $courseid, (int) $student1->id);
    $viewruns_by_id = [];
    foreach ($viewruns as $r) {
        $viewruns_by_id[(int) $r['id']] = $r;
    }

    $r4 = $viewruns_by_id[$run4['id']];
    $r3 = $viewruns_by_id[$run3['id']];
    $r2 = $viewruns_by_id[$run2['id']];
    $r1 = $viewruns_by_id[$run1['id']];

    $expected_contrib_text_pattern = "Đã đóng góp #{$contrib_id}";

    assert_test(strpos($r4['display_status'], $expected_contrib_text_pattern) !== false && $r4['can_contribute'] === false,
        "Yêu cầu 5: Lượt #{$run4['id']} hiển thị '{$r4['display_status']}' thay cho nút đề xuất");

    assert_test(strpos($r3['display_status'], $expected_contrib_text_pattern) !== false && $r3['can_contribute'] === false,
        "Yêu cầu 5: Lượt đại diện #{$run3['id']} chuyển thành '{$r3['display_status']}' thay cho nút đề xuất");

    assert_test(strpos($r2['display_status'], $expected_contrib_text_pattern) !== false && $r2['can_contribute'] === false,
        "Yêu cầu 5: Lượt cũ #{$run2['id']} chuyển thành '{$r2['display_status']}' thay cho liên kết trùng");

    assert_test(strpos($r1['display_status'], $expected_contrib_text_pattern) !== false && $r1['can_contribute'] === false,
        "Yêu cầu 5: Lượt cũ #{$run1['id']} chuyển thành '{$r1['display_status']}' thay cho liên kết trùng");

    assert_test(!empty($r1['has_code_snapshot']) && !empty($r2['has_code_snapshot']) && !empty($r3['has_code_snapshot']) && !empty($r4['has_code_snapshot']),
        "Yêu cầu 1: Nút 'Nạp code' và snapshot mã nguồn vẫn được bảo toàn nguyên vẹn sau khi đã đóng góp");


    // -----------------------------------------------------------------------------
    // GIAI ĐOẠN 4: THỬ GỬI LẠI ĐỀ XUẤT TỪ LƯỢT CŨ (#1 hoặc #2) VÀ TÁI SỬ DỤNG AN TOÀN
    // -----------------------------------------------------------------------------
    echo "\n--------------------------------------------------------------------------------\n";
    echo "GIAI ĐOẠN 4: GỬI LẠI ĐỀ XUẤT TỪ LƯỢT CŨ (#{$run1['id']}) VÀ BẢO ĐẢM TÍNH NHẤT QUÁN\n";
    echo "--------------------------------------------------------------------------------\n";

    // Đếm số lượng đóng góp hiện tại của sinh viên
    $stmt = $conn->prepare('SELECT COUNT(*) AS cnt FROM testcase_contributions WHERE user_id = ? AND question_id = ?');
    $stmt->bind_param('ii', $student1->id, $questionid);
    $stmt->execute();
    $cnt_before = (int) ($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
    $stmt->close();

    $re_submit_res = $service->submit_contribution((int) $run1['id'], (int) $student1->id);

    $stmt = $conn->prepare('SELECT COUNT(*) AS cnt FROM testcase_contributions WHERE user_id = ? AND question_id = ?');
    $stmt->bind_param('ii', $student1->id, $questionid);
    $stmt->execute();
    $cnt_after = (int) ($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
    $stmt->close();

    assert_test(!empty($re_submit_res['is_own_duplicate']) && (int) $re_submit_res['id'] === $contrib_id,
        "Yêu cầu 7: Backend an toàn: Gửi request từ lượt cũ (#{$run1['id']}) nhận diện chính xác đóng góp trước đó (#{$contrib_id})");

    assert_test($cnt_before === $cnt_after,
        "Yêu cầu 7: Không tạo bản ghi đóng góp trùng lặp trong cơ sở dữ liệu (Số lượng đóng góp không đổi: {$cnt_before} == {$cnt_after})");


    // -----------------------------------------------------------------------------
    // GIAI ĐOẠN 5: GIÁO VIÊN DUYỆT ĐÓNG GÓP & KIỂM TRA HIỂN THỊ TRẠNG THÁI MỚI TRÊN TOÀN NHÓM
    // -----------------------------------------------------------------------------
    echo "\n--------------------------------------------------------------------------------\n";
    echo "GIAI ĐOẠN 5: GIÁO VIÊN DUYỆT ĐÓNG GÓP & KIỂM TRA ĐỒNG BỘ TRẠNG THÁI 'Đã duyệt'\n";
    echo "--------------------------------------------------------------------------------\n";

    $service->review($contrib_id, $courseid, (int) $teacher->id, 'approved', 'Testcase xuat sac!', 5);
    echo "  -> Giáo viên teacher1 đã duyệt đóng góp #{$contrib_id} (rating: 5 sao)\n";

    $viewruns = prepare_student_view_runs($service, $courseid, (int) $student1->id);
    $viewruns_by_id = [];
    foreach ($viewruns as $r) {
        $viewruns_by_id[(int) $r['id']] = $r;
    }

    $r4 = $viewruns_by_id[$run4['id']];
    $r3 = $viewruns_by_id[$run3['id']];
    $r2 = $viewruns_by_id[$run2['id']];
    $r1 = $viewruns_by_id[$run1['id']];

    $check_approved = function($status) {
        return (strpos($status, 'Đã duyệt') !== false || strpos($status, 'Approved') !== false);
    };

    assert_test($check_approved($r4['display_status']),
        "Yêu cầu 5: Lượt #{$run4['id']} cập nhật hiển thị trạng thái duyệt ('{$r4['display_status']}')");

    assert_test($check_approved($r3['display_status']),
        "Yêu cầu 5: Lượt #{$run3['id']} cập nhật hiển thị trạng thái duyệt ('{$r3['display_status']}')");

    assert_test($check_approved($r2['display_status']),
        "Yêu cầu 5: Lượt #{$run2['id']} cập nhật hiển thị trạng thái duyệt ('{$r2['display_status']}')");

    assert_test($check_approved($r1['display_status']),
        "Yêu cầu 5: Lượt #{$run1['id']} cập nhật hiển thị trạng thái duyệt ('{$r1['display_status']}')");


    // -----------------------------------------------------------------------------
    // GIAI ĐOẠN 6: BẠN HỌC (STUDENT2) CHẠY TESTCASE NÀY -> HIỂN THỊ 'ĐÃ CÓ TRONG KHO (#...)'
    // -----------------------------------------------------------------------------
    echo "\n--------------------------------------------------------------------------------\n";
    echo "GIAI ĐOẠN 6: BẠN HỌC (STUDENT2) CHẠY CÙNG TESTCASE -> HIỂN THỊ 'ĐÃ CÓ TRONG KHO (#{$contrib_id})'\n";
    echo "--------------------------------------------------------------------------------\n";

    $run_student2 = $service->create_run(
        $courseid,
        $quiz,
        $qctx,
        (int) $student2->id,
        $code_v1,
        $test_input,
        $expected_output,
        'Student 2 testing same input',
        'normal',
        'Reflection student 2'
    );
    $created_run_ids[] = (int) $run_student2['id'];
    echo "  -> Đã tạo Run #{$run_student2['id']} cho student2 với cùng testcase\n";

    $viewruns_s2 = prepare_student_view_runs($service, $courseid, (int) $student2->id);
    $viewruns_s2_by_id = [];
    foreach ($viewruns_s2 as $r) {
        $viewruns_s2_by_id[(int) $r['id']] = $r;
    }
    $r_s2 = $viewruns_s2_by_id[$run_student2['id']];

    assert_test($r_s2['can_contribute'] === false && $r_s2['display_status'] === "Đã có trong kho (#{$contrib_id})",
        "Yêu cầu 6: Bạn học student2 chạy trùng testcase được hiển thị 'Đã có trong kho (#{$contrib_id})' và khóa nút đóng góp");


    // -----------------------------------------------------------------------------
    // GIAI ĐOẠN 7: TESTCASE TRÙNG KHO TESTCASE MẪU / SEED BANK CỦA GIÁO VIÊN
    // -----------------------------------------------------------------------------
    echo "\n--------------------------------------------------------------------------------\n";
    echo "GIAI ĐOẠN 7: TESTCASE TRÙNG KHO TESTCASE MẦM / SEED BANK CỦA GIÁO VIÊN\n";
    echo "--------------------------------------------------------------------------------\n";

    // Tìm một testcase mầm đang có
    $seeds = $service->list_seed_testcases($courseid, $quizid, $questionid);
    if (!empty($seeds)) {
        $seed = $seeds[0];
        $seed_input = !empty($seed['input_raw']) ? $seed['input_raw'] : (!empty($seed['stdin']) ? $seed['stdin'] : (string) $seed['testcode']);
        $seed_expected = $seed['expected_output'];
        echo "  - Đã chọn seed testcase #{$seed['id']} với input: {$seed_input}\n";

        $run_seed_test = $service->create_run(
            $courseid,
            $quiz,
            $qctx,
            (int) $student1->id,
            $code_v1,
            $seed_input,
            $seed_expected,
            'Testing against teacher seed',
            'seed',
            'Reflection seed test'
        );
        $created_run_ids[] = (int) $run_seed_test['id'];

        $viewruns_seed = prepare_student_view_runs($service, $courseid, (int) $student1->id);
        $viewruns_seed_by_id = [];
        foreach ($viewruns_seed as $r) {
            $viewruns_seed_by_id[(int) $r['id']] = $r;
        }
        $r_seed = $viewruns_seed_by_id[$run_seed_test['id']];

        assert_test($r_seed['can_contribute'] === false && $r_seed['display_status'] === 'Trùng testcase Đề thi',
            "Yêu cầu 6: Testcase trùng ngân hàng mầm đề thi hiển thị 'Trùng testcase Đề thi'");

        // Thử bypass UI gọi trực tiếp submit_contribution
        $seed_blocked = false;
        try {
            $service->submit_contribution((int) $run_seed_test['id'], (int) $student1->id);
        } catch (\Throwable $e) {
            $seed_blocked = true;
        }
        assert_test($seed_blocked,
            "Yêu cầu 6: Backend chủ động chặn không cho submit contribution testcase thuộc seed bank đề thi");
    } else {
        echo "  [SKIP] Khóa học không có seed testcase sẵn để kiểm tra giai đoạn 7.\n";
    }

} catch (\Throwable $e) {
    echo "\n[ERROR XẢY RA TRONG QUÁ TRÌNH KIỂM CHỨNG]: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    $failcount++;
} finally {
    echo "\n--------------------------------------------------------------------------------\n";
    echo "DỌN DẸP DỮ LIỆU KIỂM THỬ TẠM THỜI (FINALLY CLEANUP)\n";
    echo "--------------------------------------------------------------------------------\n";

    if (!empty($created_contrib_ids)) {
        $cids = implode(',', array_map('intval', $created_contrib_ids));
        $conn->query("DELETE FROM testcase_reviews WHERE contribution_id IN ({$cids})");
        $conn->query("DELETE FROM testcase_rewards WHERE contribution_id IN ({$cids})");
        $conn->query("DELETE FROM testcase_canonical_keys WHERE canonical_contribution_id IN ({$cids})");
        $conn->query("DELETE FROM testcase_contributions WHERE id IN ({$cids})");
        echo "  -> Đã dọn dẹp các bản ghi testcase_contributions: {$cids}\n";
    }

    if (!empty($created_run_ids)) {
        $rids = implode(',', array_map('intval', $created_run_ids));
        $conn->query("DELETE FROM testcase_runs WHERE id IN ({$rids})");
        echo "  -> Đã dọn dẹp các bản ghi testcase_runs: {$rids}\n";
    }
}

echo "\n================================================================================\n";
echo "TỔNG KẾT KIỂM CHỨNG: " . ($passcount) . " ĐẠT / " . ($passcount + $failcount) . " TIÊU CHÍ\n";
if ($failcount === 0 && $passcount > 0) {
    echo "KẾT LUẬN: TOÀN BỘ CÁC ĐIỀU KIỆN NHÓM TESTCASE VÀ BẢO TOÀN LƯỢT CHẠY ĐẠT 100%!\n";
} else {
    echo "CẢNH BÁO: CÓ {$failcount} TIÊU CHÍ CHƯA ĐẠT!\n";
}
echo "================================================================================\n";
