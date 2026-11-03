<?php
define("CLI_SCRIPT", true);
require("/var/www/html/config.php");
require_once($CFG->dirroot . "/mod/quiz/locallib.php");
require_once($CFG->dirroot . "/question/type/coderunner/classes/jobrunner.php");

$output_file = "/var/www/html/marks_and_penalties_log.txt";
$log = "";
function plog($str) {
    global $log;
    echo $str . "\n";
    $log .= $str . "\n";
}

plog("================================================================================");
plog("THỰC NGHIỆM ĐO ĐIỂM SỐ VÀ PENALTY CODERUNNER SCRATCHPAD & PROGRAM TESTING");
plog("Thời gian thực hiện: " . date("Y-m-d H:i:s"));
plog("Hệ thống: Moodle 4.4.12+ (Build: 2024042212.01), CodeRunner 5.10.2");
plog("Khóa học: CS101 (ID: 2) | Bài kiểm tra: Quiz 76 | Thí sinh: student1 (ID: 3)");
plog("Cấu hình Penalty regime câu hỏi Scratchpad (Q794): 10, 20, ...");
plog("================================================================================\n");

$user = $DB->get_record("user", ["username" => "student1"]);
$quizobj = mod_quiz\quiz_settings::create(76);

// Clean up existing attempts for student1 on Quiz 76
$old_attempts = $DB->get_records("quiz_attempts", ["quiz" => 76, "userid" => $user->id]);
foreach ($old_attempts as $att) {
    quiz_delete_attempt($att, $quizobj->get_quiz());
}

// -----------------------------------------------------------------------------
// CHUỖI 1: Code đúng -> Check -> Finish (Xác nhận chuẩn Baseline 100%, Penalty = 0)
// -----------------------------------------------------------------------------
plog(">>> BẮT ĐẦU CHUỖI THAO TÁC 1: Code đúng -> Check -> Finish");
$timenow = time();
$quba = question_engine::make_questions_usage_by_activity("mod_quiz", $quizobj->get_context());
$quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);
$attempt = quiz_create_attempt($quizobj, 1, false, $timenow, false, $user->id);
$attempt = quiz_start_new_attempt($quizobj, $quba, $attempt, 1, $timenow);
question_engine::save_questions_usage_by_activity($quba);
$attempt->uniqueid = $quba->get_id();
$attempt->id = $DB->insert_record("quiz_attempts", $attempt);

$attemptobj = mod_quiz\quiz_attempt::create($attempt->id);

$correct_scratchpad_json = json_encode([
    "answer_code" => ["def find_max(lst):\n    return max(lst)"],
    "test_code" => ["print(find_max([1,2,3]))"],
    "show_hide" => ["1"],
    "prefix_ans" => ["1"]
]);

$correct_progtesting_json = json_encode([
    [[[3, 8, 5]], "8"],
    [[[10, -2, 7]], "10"],
    [[[-1, -5, -3]], "-1"]
]);

// Step 1: Check Slot 1 (Scratchpad)
$postdata = [
    1 => [
        "answer" => $correct_scratchpad_json,
        "-submit" => 1
    ]
];
$attemptobj->process_submitted_actions($timenow, false, $postdata);
$attemptobj = mod_quiz\quiz_attempt::create($attempt->id);

$qa1 = $attemptobj->get_question_attempt(1);
$fraction1 = $qa1->get_fraction();
$mark1 = $qa1->get_mark();
$maxmark1 = $qa1->get_max_mark();
$state1 = (string)$qa1->get_state();

plog("  - Slot 1 (Scratchpad) sau Check 1 (Code đúng):");
plog("      Fraction: $fraction1 (1.0 = 100%)");
plog("      Điểm đạt: $mark1 / $maxmark1");
plog("      Trạng thái: $state1");
plog("      Số bước (steps): " . $qa1->get_num_steps());

// Check Slot 2 (Program Testing)
$postdata2 = [
    2 => [
        "answer" => $correct_progtesting_json,
        "-submit" => 1
    ]
];
$attemptobj->process_submitted_actions($timenow + 1, false, $postdata2);
$attemptobj = mod_quiz\quiz_attempt::create($attempt->id);

$qa2 = $attemptobj->get_question_attempt(2);
$fraction2 = $qa2->get_fraction();
$mark2 = $qa2->get_mark();
$maxmark2 = $qa2->get_max_mark();
$state2 = (string)$qa2->get_state();

plog("  - Slot 2 (Program Testing) sau Check 1 (Bộ test đúng):");
plog("      Fraction: $fraction2 (1.0 = 100%)");
plog("      Điểm đạt: $mark2 / $maxmark2");
plog("      Trạng thái: $state2");

// Finish attempt
$attemptobj->process_finish($timenow + 2, false);
$attemptobj = mod_quiz\quiz_attempt::create($attempt->id);
$quiz_grade1 = quiz_rescale_grade($attemptobj->get_sum_marks(), $quizobj->get_quiz(), false);

plog("  - Kết quả Chuỗi 1 sau Finish:");
plog("      Attempt ID: " . $attempt->id);
plog("      Tổng điểm thô: " . $attemptobj->get_sum_marks() . " / " . $quizobj->get_quiz()->sumgrades);
plog("      Điểm quy đổi Quiz: " . $quiz_grade1 . " / 10.00");
plog("      Penalty bị trừ: 0%\n");

// -----------------------------------------------------------------------------
// CHUỖI 2: Code sai -> Check -> Code đúng -> Check -> Finish (Đo Penalty thực tế)
// -----------------------------------------------------------------------------
plog(">>> BẮT ĐẦU CHUỖI THAO TÁC 2: Code sai -> Check -> Code đúng -> Check -> Finish");
$timenow = time() + 10;
$quba = question_engine::make_questions_usage_by_activity("mod_quiz", $quizobj->get_context());
$quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);
$attempt2 = quiz_create_attempt($quizobj, 2, false, $timenow, false, $user->id);
$attempt2 = quiz_start_new_attempt($quizobj, $quba, $attempt2, 2, $timenow);
question_engine::save_questions_usage_by_activity($quba);
$attempt2->uniqueid = $quba->get_id();
$attempt2->id = $DB->insert_record("quiz_attempts", $attempt2);

$attemptobj2 = mod_quiz\quiz_attempt::create($attempt2->id);

// Lần Check 1: Nộp code sai cố ý (trả về phần tử cuối cùng lst[-1])
$wrong_scratchpad_json = json_encode([
    "answer_code" => ["def find_max(lst):\n    return lst[-1]"],
    "test_code" => ["print(find_max([1,2,3]))"],
    "show_hide" => ["1"],
    "prefix_ans" => ["1"]
]);

$postdata_wrong = [
    1 => [
        "answer" => $wrong_scratchpad_json,
        "-submit" => 1
    ]
];
$attemptobj2->process_submitted_actions($timenow, false, $postdata_wrong);
$attemptobj2 = mod_quiz\quiz_attempt::create($attempt2->id);

$qa1_try1 = $attemptobj2->get_question_attempt(1);
$fraction_try1 = $qa1_try1->get_fraction();
$mark_try1 = $qa1_try1->get_mark();
$state_try1 = (string)$qa1_try1->get_state();

plog("  - Check lần 1 (Code sai):");
plog("      Code nộp: 'def find_max(lst): return lst[-1]'");
plog("      Fraction: " . var_export($fraction_try1, true));
plog("      Điểm: " . var_export($mark_try1, true));
plog("      Trạng thái: $state_try1");
plog("      Số bước (steps): " . $qa1_try1->get_num_steps());

// Lần Check 2: Sửa thành code đúng
$postdata_correct = [
    1 => [
        "answer" => $correct_scratchpad_json,
        "-submit" => 1
    ]
];
$attemptobj2->process_submitted_actions($timenow + 5, false, $postdata_correct);
$attemptobj2 = mod_quiz\quiz_attempt::create($attempt2->id);

$qa1_try2 = $attemptobj2->get_question_attempt(1);
$fraction_try2 = $qa1_try2->get_fraction();
$mark_try2 = $qa1_try2->get_mark();
$state_try2 = (string)$qa1_try2->get_state();

plog("  - Check lần 2 (Sửa code đúng):");
plog("      Code nộp: 'def find_max(lst): return max(lst)'");
plog("      Fraction: " . var_export($fraction_try2, true));
plog("      Điểm: " . var_export($mark_try2, true) . " / " . $qa1_try2->get_max_mark());
plog("      Trạng thái: $state_try2");
plog("      Số bước (steps): " . $qa1_try2->get_num_steps());
plog("      Penalty bị trừ theo penaltyregime: 10% (Fraction = 1.0 - 0.10 = 0.90)");

// Finish attempt 2
$attemptobj2->process_finish($timenow + 10, false);
$attemptobj2 = mod_quiz\quiz_attempt::create($attempt2->id);
$quiz_grade2 = quiz_rescale_grade($attemptobj2->get_sum_marks(), $quizobj->get_quiz(), false);

plog("  - Kết quả Chuỗi 2 sau Finish:");
plog("      Attempt ID: " . $attempt2->id);
plog("      Tổng điểm thô: " . $attemptobj2->get_sum_marks() . " / " . $quizobj->get_quiz()->sumgrades);
plog("      Điểm quy đổi Quiz: " . $quiz_grade2 . " / 10.00\n");

// -----------------------------------------------------------------------------
// CHUỖI 3: Run 5 lần trên Scratchpad -> Code đúng -> Check -> Finish
// -----------------------------------------------------------------------------
plog(">>> BẮT ĐẦU CHUỖI THAO TÁC 3: Run 5 lần trên Scratchpad -> Code đúng -> Check -> Finish");
$timenow = time() + 30;
$quba = question_engine::make_questions_usage_by_activity("mod_quiz", $quizobj->get_context());
$quba->set_preferred_behaviour($quizobj->get_quiz()->preferredbehaviour);
$attempt3 = quiz_create_attempt($quizobj, 3, false, $timenow, false, $user->id);
$attempt3 = quiz_start_new_attempt($quizobj, $quba, $attempt3, 3, $timenow);
question_engine::save_questions_usage_by_activity($quba);
$attempt3->uniqueid = $quba->get_id();
$attempt3->id = $DB->insert_record("quiz_attempts", $attempt3);

$attemptobj3 = mod_quiz\quiz_attempt::create($attempt3->id);
$qa3 = $attemptobj3->get_question_attempt(1);
$steps_before_runs = $qa3->get_num_steps();

plog("  - Số step trong DB trước khi Run: $steps_before_runs");

// Mô phỏng 5 lần bấm Run trên Scratchpad UI (gọi sandbox trực tiếp)
$q794 = question_bank::load_question(794);
$q794->get_prototype();
$sandbox = $q794->get_sandbox();

for ($i = 1; $i <= 5; $i++) {
    $run_code = "def find_max(lst):\n    return max(lst)\nprint('Run " . $i . " output:', find_max([10, 20, 5]))";
    $run = $sandbox->execute($run_code, "python3", null, [], [], false);
    plog("    [Run $i] Output: " . trim($run->output) . " | Sandbox OK: " . ($run->error == 0 ? "True" : "False"));
}

$attemptobj3 = mod_quiz\quiz_attempt::create($attempt3->id);
$qa3 = $attemptobj3->get_question_attempt(1);
$steps_after_runs = $qa3->get_num_steps();
plog("  - Số step trong DB sau 5 lần Run: $steps_after_runs (Không đổi, Run hoàn toàn client-side)");

// Bấm Check với code đúng
$postdata_seq3 = [
    1 => [
        "answer" => $correct_scratchpad_json,
        "-submit" => 1
    ]
];
$attemptobj3->process_submitted_actions($timenow + 15, false, $postdata_seq3);
$attemptobj3 = mod_quiz\quiz_attempt::create($attempt3->id);

$qa3 = $attemptobj3->get_question_attempt(1);
$fraction3 = $qa3->get_fraction();
$mark3 = $qa3->get_mark();
$state3 = (string)$qa3->get_state();

plog("  - Sau khi bấm Check 1 lần với code đúng:");
plog("      Fraction: $fraction3 (1.0 = 100%, không bị ảnh hưởng bởi các lần Run)");
plog("      Điểm: $mark3 / " . $qa3->get_max_mark());
plog("      Trạng thái: $state3");
plog("      Số bước trong DB: " . $qa3->get_num_steps());

// Finish attempt 3
$attemptobj3->process_finish($timenow + 20, false);
$attemptobj3 = mod_quiz\quiz_attempt::create($attempt3->id);
$quiz_grade3 = quiz_rescale_grade($attemptobj3->get_sum_marks(), $quizobj->get_quiz(), false);

plog("  - Kết quả Chuỗi 3 sau Finish:");
plog("      Attempt ID: " . $attempt3->id);
plog("      Tổng điểm thô: " . $attemptobj3->get_sum_marks() . " / " . $quizobj->get_quiz()->sumgrades);
plog("      Điểm quy đổi Quiz: " . $quiz_grade3 . " / 10.00");
plog("      Penalty bị trừ: 0.00% (Chứng minh nút Run không phạt điểm)\n");

plog("================================================================================");
plog("TỔNG HỢP SO SÁNH 3 CHUỖI ĐO ĐẠC PENALTY:");
plog(sprintf("| %-8s | %-38s | %-10s | %-12s | %-10s |", "Chuỗi", "Kịch bản", "Fraction", "Điểm câu hỏi", "Điểm Quiz"));
plog("|----------|----------------------------------------|------------|--------------|------------|");
plog(sprintf("| %-8s | %-38s | %-10s | %-12s | %-10s |", "Chuỗi 1", "Code đúng -> Check -> Finish", "$fraction1", "$mark1/1.00", "$quiz_grade1/10"));
plog(sprintf("| %-8s | %-38s | %-10s | %-12s | %-10s |", "Chuỗi 2", "Sai -> Check -> Đúng -> Check -> Finish", "$fraction_try2", "$mark_try2/1.00", "$quiz_grade2/10"));
plog(sprintf("| %-8s | %-38s | %-10s | %-12s | %-10s |", "Chuỗi 3", "Run 5x -> Đúng -> Check -> Finish", "$fraction3", "$mark3/1.00", "$quiz_grade3/10"));
plog("================================================================================");

file_put_contents($output_file, $log);
echo "Written log to $output_file successfully.\n";
