<?php
define("CLI_SCRIPT", true);
require "/var/www/html/config.php";
require_once($CFG->dirroot . "/mod/quiz/locallib.php");

global $DB;
$student = $DB->get_record("user", ["username" => "student1"]);
\core\session\manager::set_user($student);

$quiz = $DB->get_record("quiz", ["id" => 76]);
$quizobj = \mod_quiz\quiz_settings::create($quiz->id, $student->id);

echo "=== KIEM CHUNG TIEN TRINH BIEN DONG DIEM SO VA PENALTY TRUOC - SAU RUN VA CHECK ===\n\n";

// Khoi tao attempt moi de theo doi tu dau
$attempt = quiz_prepare_and_start_new_attempt($quizobj, 3, null, false, [], [], $student->id);
$attemptid = $attempt->id;
$attemptobj = \mod_quiz\quiz_attempt::create($attemptid);
$usageid = $attemptobj->get_uniqueid();

function print_state($label, $attemptid, $usageid, $quizid, $userid) {
    global $DB;
    $qa = $DB->get_record("question_attempts", ["questionusageid" => $usageid, "slot" => 1]);
    $last_step = $DB->get_records_sql("SELECT * FROM {question_attempt_steps} WHERE questionattemptid = ? ORDER BY sequencenumber DESC LIMIT 1", [$qa->id]);
    $last_step = reset($last_step);
    
    $attempt_rec = $DB->get_record("quiz_attempts", ["id" => $attemptid]);
    $grades = $DB->get_record("quiz_grades", ["quiz" => $quizid, "userid" => $userid]);
    
    echo "[$label]\n";
    echo "  - Question State: " . ($last_step ? $last_step->state : "N/A") . "\n";
    echo "  - Question Fraction (Raw Mark): " . ($last_step ? var_export($last_step->fraction, true) : "N/A") . "\n";
    echo "  - QA Max Mark: " . $qa->maxmark . "\n";
    echo "  - Step Sequence: " . ($last_step ? $last_step->sequencenumber : 0) . "\n";
    echo "  - Quiz Attempt Sumgrades: " . var_export($attempt_rec->sumgrades, true) . "\n";
    echo "  - Quiz Gradebook Grade: " . ($grades ? $grades->grade : "Chua ghi (InProgress)") . "\n";
    echo "--------------------------------------------------------------------------------\n";
}

// 1. TRANG THAI BAN DAU
print_state("1. KHOI TAO ATTEMPT (TRUOC MOI THAO TAC)", $attemptid, $usageid, $quiz->id, $student->id);

// 2. THAO TAC RUN TREN SCRATCHPAD (5 LAN LIEN TIEP)
set_config("wsenabled", 1, "qtype_coderunner");
$context = context_course::instance(2);
for ($i = 1; $i <= 5; $i++) {
    $code = "def find_max(lst):\n    return max(lst)\nprint(find_max([3, 8, 5]))";
    $run_res = \qtype_coderunner\external\run_in_sandbox::execute($context->id, $code, "python3");
}
print_state("2. SAU KHI BAM RUN 5 LAN TREN SCRATCHPAD", $attemptid, $usageid, $quiz->id, $student->id);

// 3. THAO TAC CHECK LAN 1: NOP CODE SAI
$timenow = time();
$serialized_wrong = json_encode([
    "answer_code" => ["def find_max(lst):\n    return lst[-1]"], // Sai: tra ve phan tu cuoi
    "test_code" => ["print(find_max([3, 8, 5]))"],
    "show_hide" => ["1"],
    "prefix_ans" => ["1"]
]);
$attemptobj->process_submitted_actions($timenow, false, [1 => ["answer" => $serialized_wrong, "-submit" => 1]]);
print_state("3. SAU KHI BAM CHECK LAN 1 (CODE SAI, Q794 - allornothing=1, penalty=0.1)", $attemptid, $usageid, $quiz->id, $student->id);

// 4. THAO TAC CHECK LAN 2: SUA LAI DUNG
$timenow += 10;
$serialized_right = json_encode([
    "answer_code" => ["def find_max(lst):\n    return max(lst)"], // Sua lai dung
    "test_code" => ["print(find_max([3, 8, 5]))"],
    "show_hide" => ["1"],
    "prefix_ans" => ["1"]
]);
$attemptobj->process_submitted_actions($timenow, false, [1 => ["answer" => $serialized_right, "-submit" => 1]]);
print_state("4. SAU KHI BAM CHECK LAN 2 (SUA CODE DUNG)", $attemptid, $usageid, $quiz->id, $student->id);

// 5. NOP TOAN BO QUIZ VA KET THUC
$attemptobj = \mod_quiz\quiz_attempt::create($attemptid);
$attemptobj->process_finish($timenow + 20, false);
print_state("5. SAU KHI FINISH ATTEMPT & LUU GRADEBOOK", $attemptid, $usageid, $quiz->id, $student->id);
