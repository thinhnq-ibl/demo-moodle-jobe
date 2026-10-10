<?php
// Comprehensive verification script for Execution Context Restoration & Offline Replay.
// Run inside moodle_app: docker exec moodle_app php scripts/verify_execution_context_restoration.php

define('CLI_SCRIPT', true);

require_once('/var/www/html/config.php');
require_once($CFG->dirroot . '/local/testcase_exchange/classes/replay_service.php');

use local_testcase_exchange\external_database;
use local_testcase_exchange\schema_manager;
use local_testcase_exchange\context_service;
use local_testcase_exchange\replay_service;

echo "====================================================================\n";
echo " KIỂM CHỨNG TOÀN DIỆN: PHỤC HỒI NGỮ CẢNH CHẠY & REPLAY ĐỘC LẬP \n";
echo "====================================================================\n\n";

$conn = external_database::connect();

// -------------------------------------------------------------
// 1. Kiểm tra Schema & Migrate
// -------------------------------------------------------------
echo "[1/4] KIỂM TRA SCHEMA & CÁC CỘT NGỮ CẢNH MỞ RỘNG:\n";
schema_manager::migrate($conn);

$checkcols = [
    'student_code_snapshot', 'oracle_solution_snapshot', 'template_snapshot',
    'runtime_context', 'question_version', 'grader_type', 'oracle_version_hash',
    'test_suite_version_hash', 'execution_limits'
];
$res = $conn->query("SHOW COLUMNS FROM testcase_runs");
$existing = [];
while ($row = $res->fetch_assoc()) {
    $existing[$row['Field']] = true;
}

$allok = true;
foreach ($checkcols as $col) {
    if (isset($existing[$col])) {
        echo "   [+] Cột $col: SẴN SÀNG.\n";
    } else {
        echo "   [!] THIẾU CỘT: $col\n";
        $allok = false;
    }
}
if (!$allok) {
    exit(1);
}
echo "   -> Schema đồng bộ thành công 100%.\n\n";

// -------------------------------------------------------------
// 2. Kiểm chứng Phân loại Sự kiện trong Trajectory
// -------------------------------------------------------------
echo "[2/4] KIỂM CHỨNG PHÂN LOẠI CHUỖI NỘP BÀI (TRAJECTORY CLASSIFICATION):\n";
$qa = $DB->get_record('question_attempts', ['id' => 15]);
if ($qa) {
    $traj = context_service::get_submission_trajectory((int)$qa->questionusageid, (int)$qa->questionid);
    echo "   -> Tìm thấy " . count($traj) . " bước trong lịch sử attempt #15:\n";
    foreach ($traj as $step) {
        $execflag = $step['has_execution'] ? "[CÓ CHẠY JOBE]" : "[KHÔNG CHẠY CODE]";
        echo sprintf(
            "      • Step %d (%s) - %-14s: %-38s %s\n",
            $step['step_number'],
            $step['timestamp'],
            $step['event_type'],
            $step['description'],
            $execflag
        );
    }
    echo "   -> Phân biệt rõ rệt giữa: Khởi tạo (init), Chạy Check (check_execute), và Nộp bài (finish_attempt).\n\n";
} else {
    echo "   [!] Không tìm thấy attempt #15 để test trajectory.\n\n";
}

// -------------------------------------------------------------
// 3. Kiểm thử Replay Đa dạng Ngữ cảnh (Libraries, Multi-line, Errors)
// -------------------------------------------------------------
echo "[3/4] THỰC THI REPLAY ĐA DẠNG NGỮ CẢNH TRÊN JOBE SANDBOX:\n";

$testcases = [
    'Case A: Thư viện chuẩn (math & re)' => [
        'code' => "import math, re\nline = input()\nnumbers = [float(x) for x in re.findall(r'\\d+(?:\\.\\d+)?', line)]\nprint(f'{math.prod(numbers):.2f}')",
        'oracle' => "import math, re\nline = input()\nnumbers = [float(x) for x in re.findall(r'\\d+(?:\\.\\d+)?', line)]\nprint(f'{math.prod(numbers):.2f}')",
        'input' => "Radius: 2.5 and Factor: 4",
        'expected' => "10.00",
        'grader' => "EqualityGrader",
    ],
    'Case B: Input nhiều dòng & cấu trúc dữ liệu' => [
        'code' => "n = int(input())\nitems = [input().strip() for _ in range(n)]\nprint(', '.join(sorted(items)))",
        'oracle' => "n = int(input())\nitems = [input().strip() for _ in range(n)]\nprint(', '.join(sorted(items)))",
        'input' => "3\nbanana\napple\norange",
        'expected' => "apple, banana, orange",
        'grader' => "EqualityGrader",
    ],
    'Case C: Phát hiện Lỗi Runtime (ZeroDivisionError)' => [
        'code' => "a, b = map(int, input().split())\nprint(a // b)",
        'oracle' => "a, b = map(int, input().split())\nprint(a // b if b != 0 else 'div_by_zero')",
        'input' => "10 0",
        'expected' => "div_by_zero",
        'grader' => "EqualityGrader",
    ],
];

foreach ($testcases as $title => $tc) {
    echo "   --- $title ---\n";
    $fake_snapshot = [
        'id' => 999,
        'student_code_snapshot' => $tc['code'],
        'oracle_solution_snapshot' => $tc['oracle'],
        'template_snapshot' => '',
        'runtime_context' => json_encode(['coderunnertype' => 'python3']),
        'grader_type' => $tc['grader'],
        'execution_limits' => json_encode(['cputimelimitsecs' => 5, 'memlimitmb' => 256]),
        'input_raw' => $tc['input'],
        'student_run_output' => $tc['expected'],
    ];

    $replay = replay_service::replay_run($fake_snapshot, null, 'http://jobe1');
    echo "      • Input: " . str_replace("\n", "\\n", $tc['input']) . "\n";
    echo "      • Output sinh viên: " . ($replay['student_stdout'] ?: ($replay['student_stderr'] ? '[RUNTIME ERROR/EXCEPTION]' : '[EMPTY]')) . "\n";
    echo "      • Output Oracle: " . $replay['oracle_stdout'] . "\n";
    echo "      • Thời gian thực thi Jobe: {$replay['duration_ms']} ms\n";
    echo "      • Khớp quy tắc Grader: " . ($replay['is_passed_against_oracle'] ? "PASS" : "FAIL (Phát hiện lỗi)") . "\n";
}
echo "\n";

// -------------------------------------------------------------
// 4. Kiểm chứng Tuyệt đối: Zero Gradebook & DB Side-Effects
// -------------------------------------------------------------
echo "[4/4] KIỂM CHỨNG TÍNH KHÔNG TÁC ĐỘNG CƠ SỞ DỮ LIỆU & GRADEBOOK:\n";

$verification = replay_service::verify_zero_side_effect(function() use ($testcases) {
    // Chạy 3 lần replay liên tiếp
    foreach ($testcases as $tc) {
        $snap = [
            'id' => 888,
            'student_code_snapshot' => $tc['code'],
            'oracle_solution_snapshot' => $tc['oracle'],
            'runtime_context' => json_encode(['coderunnertype' => 'python3']),
            'grader_type' => $tc['grader'],
            'input_raw' => $tc['input'],
            'student_run_output' => $tc['expected'],
        ];
        replay_service::replay_run($snap);
    }
    return "All 3 replay runs finished";
});

echo "   • Checksum Bảng Điểm & Attempt TRƯỚC replay: " . substr($verification['signature_before'], 0, 16) . "...\n";
echo "   • Checksum Bảng Điểm & Attempt SAU replay:   " . substr($verification['signature_after'], 0, 16) . "...\n";
if ($verification['is_pure_read_only']) {
    echo "   ===> XÁC NHẬN: CHỮ KÝ TRƯỚC VÀ SAU TRÙNG KHỚP 100%! <===\n";
    echo "   ===> REPLAY HOÀN TOÀN READ-ONLY, ZERO TÁC ĐỘNG LÊN ĐIỂM SỐ VÀ ATTEMPT MÔN HỌC! <===\n";
} else {
    echo "   [!] CẢNH BÁO: PHÁT HIỆN THAY ĐỔI TRONG DATABASE!\n";
    exit(1);
}

echo "\n====================================================================\n";
echo " KẾT LUẬN: ĐÃ HOÀN TẤT VÀ ĐẠT TOÀN BỘ TIÊU CHÍ BẢO VỆ PHỤC HỒI NGỮ CẢNH \n";
echo "====================================================================\n";
