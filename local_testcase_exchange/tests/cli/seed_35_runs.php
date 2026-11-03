<?php
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->dirroot . '/local/testcase_exchange/classes/testcase_service.php');

global $DB, $CFG;

echo "=== Seeding Runs, Timezones and Rewards for TCE ===\n";

// 1. Timezone Alignment
$DB->set_field('user', 'timezone', 'Asia/Ho_Chi_Minh', ['username' => 'student1']);
$DB->set_field('user', 'timezone', 'Asia/Ho_Chi_Minh', ['username' => 'admin']);
set_config('timezone', 'Asia/Ho_Chi_Minh');
echo "Set timezone to Asia/Ho_Chi_Minh for student1, admin and site default.\n";

$student = $DB->get_record('user', ['username' => 'student1'], '*', MUST_EXIST);
$admin = $DB->get_record('user', ['username' => 'admin'], '*', MUST_EXIST);

$courseid = 3;
$quizid = 2;
$questionid = 119;

$student_code = "def is_prime(n):\n    if n <= 1:\n        return False\n    for i in range(2, int(n**0.5) + 1):\n        if n % i == 0:\n            return False\n    return True\n";
$code_hash = hash('sha256', $student_code);

// 2. Ensure 35 diverse runs exist for Question 119
$existing_runs = $DB->count_records('local_tce_runs', ['question_id' => $questionid, 'user_id' => $student->id]);
echo "Current runs count for Question 119: {$existing_runs}\n";

$test_inputs = [
    ['in' => 'print(is_prime(2))', 'exp' => 'True', 'cat' => 'normal', 'pur' => 'Kiểm thử số nguyên tố chẵn duy nhất (2)'],
    ['in' => 'print(is_prime(3))', 'exp' => 'True', 'cat' => 'normal', 'pur' => 'Kiểm thử số nguyên tố nhỏ (3)'],
    ['in' => 'print(is_prime(4))', 'exp' => 'False', 'cat' => 'normal', 'pur' => 'Kiểm thử hợp số nhỏ nhất (4)'],
    ['in' => 'print(is_prime(5))', 'exp' => 'True', 'cat' => 'normal', 'pur' => 'Kiểm thử số nguyên tố 5'],
    ['in' => 'print(is_prime(6))', 'exp' => 'False', 'cat' => 'normal', 'pur' => 'Kiểm thử hợp số chẵn 6'],
    ['in' => 'print(is_prime(7))', 'exp' => 'True', 'cat' => 'normal', 'pur' => 'Kiểm thử số nguyên tố lẻ 7'],
    ['in' => 'print(is_prime(8))', 'exp' => 'False', 'cat' => 'normal', 'pur' => 'Kiểm thử lũy thừa của 2 (8)'],
    ['in' => 'print(is_prime(9))', 'exp' => 'False', 'cat' => 'normal', 'pur' => 'Kiểm thử hợp số lẻ (9)'],
    ['in' => 'print(is_prime(10))', 'exp' => 'False', 'cat' => 'normal', 'pur' => 'Kiểm thử số tròn chục 10'],
    ['in' => 'print(is_prime(11))', 'exp' => 'True', 'cat' => 'normal', 'pur' => 'Kiểm thử số nguyên tố hai chữ số (11)'],
    ['in' => 'print(is_prime(12))', 'exp' => 'False', 'cat' => 'normal', 'pur' => 'Kiểm thử hợp số nhiều ước (12)'],
    ['in' => 'print(is_prime(13))', 'exp' => 'True', 'cat' => 'normal', 'pur' => 'Kiểm thử số nguyên tố 13'],
    ['in' => 'print(is_prime(17))', 'exp' => 'True', 'cat' => 'normal', 'pur' => 'Kiểm thử số nguyên tố 17'],
    ['in' => 'print(is_prime(19))', 'exp' => 'True', 'cat' => 'normal', 'pur' => 'Kiểm thử số nguyên tố 19'],
    ['in' => 'print(is_prime(20))', 'exp' => 'False', 'cat' => 'normal', 'pur' => 'Kiểm thử hợp số 20'],
    ['in' => 'print(is_prime(23))', 'exp' => 'True', 'cat' => 'normal', 'pur' => 'Kiểm thử số nguyên tố 23'],
    ['in' => 'print(is_prime(29))', 'exp' => 'True', 'cat' => 'normal', 'pur' => 'Kiểm thử số nguyên tố 29'],
    ['in' => 'print(is_prime(31))', 'exp' => 'True', 'cat' => 'normal', 'pur' => 'Kiểm thử số nguyên tố 31'],
    ['in' => 'print(is_prime(37))', 'exp' => 'True', 'cat' => 'normal', 'pur' => 'Kiểm thử số nguyên tố 37'],
    ['in' => 'print(is_prime(41))', 'exp' => 'True', 'cat' => 'normal', 'pur' => 'Kiểm thử số nguyên tố 41'],
    ['in' => 'print(is_prime(43))', 'exp' => 'True', 'cat' => 'normal', 'pur' => 'Kiểm thử số nguyên tố 43'],
    ['in' => 'print(is_prime(47))', 'exp' => 'True', 'cat' => 'normal', 'pur' => 'Kiểm thử số nguyên tố 47'],
    ['in' => 'print(is_prime(1))', 'exp' => 'False', 'cat' => 'boundary', 'pur' => 'Kiểm thử giá trị biên n = 1 (không phải SNT)'],
    ['in' => 'print(is_prime(0))', 'exp' => 'False', 'cat' => 'boundary', 'pur' => 'Kiểm thử giá trị biên n = 0'],
    ['in' => 'print(is_prime(-5))', 'exp' => 'False', 'cat' => 'invalid', 'pur' => 'Kiểm thử số âm (-5)'],
    ['in' => 'print(is_prime(97))', 'exp' => 'True', 'cat' => 'normal', 'pur' => 'Kiểm thử số nguyên tố lớn nhất dưới 100 (97)'],
    ['in' => 'print(is_prime(100))', 'exp' => 'False', 'cat' => 'normal', 'pur' => 'Kiểm thử số chính phương tròn 100'],
    ['in' => 'print(is_prime(101))', 'exp' => 'True', 'cat' => 'large', 'pur' => 'Kiểm thử số nguyên tố ba chữ số đầu tiên (101)'],
    ['in' => 'print(is_prime(103))', 'exp' => 'True', 'cat' => 'large', 'pur' => 'Kiểm thử số nguyên tố 103'],
    ['in' => 'print(is_prime(107))', 'exp' => 'True', 'cat' => 'large', 'pur' => 'Kiểm thử số nguyên tố 107'],
    ['in' => 'print(is_prime(109))', 'exp' => 'True', 'cat' => 'large', 'pur' => 'Kiểm thử số nguyên tố 109'],
    ['in' => 'print(is_prime(113))', 'exp' => 'True', 'cat' => 'large', 'pur' => 'Kiểm thử số nguyên tố 113'],
    ['in' => 'print(is_prime(127))', 'exp' => 'True', 'cat' => 'large', 'pur' => 'Kiểm thử số nguyên tố Mersenne (127)'],
    ['in' => 'print(is_prime(131))', 'exp' => 'True', 'cat' => 'large', 'pur' => 'Kiểm thử số nguyên tố 131'],
    ['in' => 'print(is_prime(137))', 'exp' => 'True', 'cat' => 'large', 'pur' => 'Kiểm thử số nguyên tố 137'],
];

$now = time();
$i = 0;
foreach ($test_inputs as $tc) {
    $i++;
    $raw = $tc['in'];
    $norm = trim($raw);
    $fp = hash('sha256', $norm);
    $time = $now - (count($test_inputs) - $i) * 120; // spaced 2 mins apart

    // Check if run already exists
    $existing = $DB->get_record('local_tce_runs', [
        'question_id' => $questionid,
        'user_id' => $student->id,
        'input_raw' => $raw
    ]);
    if (!$existing) {
        $runrecord = (object)[
            'course_id' => $courseid,
            'quiz_id' => $quizid,
            'question_id' => $questionid,
            'user_id' => $student->id,
            'input_raw' => $raw,
            'input_normalized' => $norm,
            'input_fingerprint' => $fp,
            'input_raw_hash' => hash('sha256', $raw),
            'predicted_output' => $tc['exp'],
            'student_run_output' => $tc['exp'],
            'oracle_output' => $tc['exp'],
            'student_outcome' => 'success',
            'oracle_outcome' => 'success',
            'purpose' => $tc['pur'],
            'category' => $tc['cat'],
            'reflection' => '',
            'jobe_server' => 'jobe1:4001',
            'student_source_hash' => $code_hash,
            'student_code_snapshot' => $student_code,
            'oracle_solution_snapshot' => $student_code,
            'template_snapshot' => '',
            'runtime_context' => '{}',
            'question_version' => 1,
            'grader_type' => 'python3',
            'timecreated' => $time,
        ];
        $DB->insert_record('local_tce_runs', $runrecord);
    }
}

$total_now = $DB->count_records('local_tce_runs', ['question_id' => $questionid, 'user_id' => $student->id]);
echo "Updated runs count for Question 119: {$total_now}\n";

// 3. Ensure meaningful Reward Records exist in local_tce_rewards for Course 3 / Quiz 2
$rewards_count = $DB->count_records('local_tce_rewards', ['course_id' => $courseid]);
if ($rewards_count === 0) {
    // Insert 2 sample granted rewards
    $DB->insert_record('local_tce_rewards', (object)[
        'course_id' => $courseid,
        'quiz_id' => $quizid,
        'question_id' => $questionid,
        'giver_user_id' => 0, // system/peer
        'receiver_user_id' => $student->id,
        'testcase_id' => 1,
        'is_claimed' => 1,
        'timecreated' => $now - 3600,
        'timeclaimed' => $now - 1800
    ]);
    $DB->insert_record('local_tce_rewards', (object)[
        'course_id' => $courseid,
        'quiz_id' => $quizid,
        'question_id' => $questionid,
        'giver_user_id' => 0,
        'receiver_user_id' => $student->id,
        'testcase_id' => 2,
        'is_claimed' => 0,
        'timecreated' => $now - 1200,
        'timeclaimed' => 0
    ]);
    echo "Inserted 2 sample rewards (1 claimed, 1 unclaimed).\n";
} else {
    echo "Rewards already exist: {$rewards_count}.\n";
}

echo "=== Seeding Completed Successfully ===\n";
