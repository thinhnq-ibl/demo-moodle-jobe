<?php
define('CLI_SCRIPT', true);

require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->dirroot . '/local/testcase_exchange/classes/testcase_service.php');
require_once($CFG->dirroot . '/local/testcase_exchange/classes/context_service.php');

use local_testcase_exchange\testcase_service;
use local_testcase_exchange\context_service;

$courseid = 3;
$quizid = 2;
$questionid = 119;
$student = $DB->get_record('user', ['username' => 'student1'], '*', MUST_EXIST);
$quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
$qctx = context_service::validate($courseid, $quizid, $questionid);

$service = new testcase_service();

// Student source code for is_prime
$student_code = <<<'PY'
def is_prime(n):
    if not isinstance(n, int):
        return False
    if n < 2:
        return False
    for i in range(2, int(n**0.5) + 1):
        if n % i == 0:
            return False
    return True
PY;

$testcases = [
    ['input' => 'print(is_prime(2))', 'expected' => 'True', 'desc' => 'Smallest prime (2)'],
    ['input' => 'print(is_prime(3))', 'expected' => 'True', 'desc' => 'Odd prime (3)'],
    ['input' => 'print(is_prime(4))', 'expected' => 'False', 'desc' => 'Smallest composite (4)'],
    ['input' => 'print(is_prime(0))', 'expected' => 'False', 'desc' => 'Boundary value 0'],
    ['input' => 'print(is_prime(1))', 'expected' => 'False', 'desc' => 'Boundary value 1'],
    ['input' => 'print(is_prime(-5))', 'expected' => 'False', 'desc' => 'Negative number (-5)'],
    ['input' => 'print(is_prime(7919))', 'expected' => 'True', 'desc' => 'Large 4-digit prime (7919)'],
    ['input' => 'print(is_prime(8000))', 'expected' => 'False', 'desc' => 'Large composite (8000)'],
    ['input' => 'print(is_prime(2.5))', 'expected' => 'False', 'desc' => 'Float type input (2.5)'],
    ['input' => 'print(is_prime("abc"))', 'expected' => 'False', 'desc' => 'String type input ("abc")'],
    ['input' => 'print(is_prime(7))', 'expected' => 'False', 'desc' => 'Intentional mismatch test (7 is prime, expected False)']
];

echo "=================================================================\n";
echo "VERIFYING 11 EDGE CASES ON QUESTION {$questionid} (is_prime)\n";
echo "=================================================================\n";

$pass_count = 0;
foreach ($testcases as $i => $tc) {
    $num = $i + 1;
    $run = $service->create_run(
        $courseid,
        $quiz,
        $qctx,
        (int)$student->id,
        $student_code,
        $tc['input'],
        $tc['expected'],
        $tc['desc'],
        'boundary',
        'Testing edge case'
    );

    $student_out = trim((string)($run['student']['output'] ?? ''));
    $expected_out = trim((string)$tc['expected']);
    $match = ($student_out === $expected_out);
    
    // For test 11 (intentional mismatch), we verify the system correctly identifies mismatch!
    if ($num === 11) {
        $verified = (!$match && $student_out === 'True');
    } else {
        $verified = $match;
    }

    if ($verified) {
        $pass_count++;
    }

    $icon = $verified ? "✅ PASS" : "❌ FAIL";
    echo sprintf("[%2d/11] %-35s | Input: %-25s | Out: %-6s | Exp: %-6s | %s\n",
        $num, $tc['desc'], $tc['input'], $student_out, $expected_out, $icon);
}

echo "=================================================================\n";
echo "RESULT: {$pass_count}/11 edge cases verified correctly.\n";
echo "=================================================================\n";
