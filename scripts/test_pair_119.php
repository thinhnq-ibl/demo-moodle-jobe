<?php
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/config.php');
require_once($CFG->dirroot . '/local/testcase_exchange/classes/testcase_service.php');
require_once($CFG->dirroot . '/local/testcase_exchange/classes/execution_service.php');

$quiz = $DB->get_record('quiz', ['id' => 2], '*', MUST_EXIST);
$service = new \local_testcase_exchange\testcase_service();
$pol = $service->question_policy(2, 119, 'python3');
print_r($pol);

$run = \local_testcase_exchange\execution_service::run_pair(
    119,
    "def is_prime(n):\n    return n > 1\n",
    "print(is_prime(5))",
    $pol,
    $quiz
);
print_r($run);
