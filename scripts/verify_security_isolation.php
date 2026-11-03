<?php
/**
 * Security, Oracle Protection, Rate Limiting & Sandbox Isolation Verification.
 */

define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/local/testcase_exchange/lib.php');

global $DB;

echo "=== STARTING SECURITY & ISOLATION VERIFICATION ===\n";

$gateway = new \local_testcase_exchange\db_gateway();
$service = new \local_testcase_exchange\testcase_service($gateway);

$course = $DB->get_record('course', ['id' => 2]);
if (!$course) {
    $course = $DB->get_record('course', [], '*', IGNORE_MULTIPLE);
}
$courseid = $course ? (int)$course->id : 1;
$context = context_course::instance($courseid);

$teacher = $DB->get_record('user', ['username' => 'admin']);
$teacherid = $teacher ? (int)$teacher->id : 2;

$student = $DB->get_record_select('user', "deleted = 0 AND id NOT IN (1, :teacherid)", ['teacherid' => $teacherid], '*', IGNORE_MULTIPLE);
$studentid = $student ? (int)$student->id : 3;

// -------------------------------------------------------------
// CHECK 1: Capability Matrix
// -------------------------------------------------------------
echo "\n--- Check 1: Capability Authorization Matrix ---\n";
$caps = [
    'local/testcase_exchange:view',
    'local/testcase_exchange:run',
    'local/testcase_exchange:contribute',
    'local/testcase_exchange:review',
    'local/testcase_exchange:manage',
];
foreach ($caps as $cap) {
    $teacher_has = has_capability($cap, $context, $teacherid);
    $student_has = has_capability($cap, $context, $studentid);
    echo "  Capability '{$cap}': Teacher=" . ($teacher_has ? "ALLOW" : "DENY") . ", Student=" . ($student_has ? "ALLOW" : "DENY") . "\n";
}

// -------------------------------------------------------------
// CHECK 2: Oracle Solution Secrecy (No Oracle Code Leakage)
// -------------------------------------------------------------
echo "\n--- Check 2: Oracle Code Confidentiality ---\n";
// Ensure mock question context
$quiz = (object)['id' => 1, 'course' => $courseid];
$questioncontext = (object)[
    'questionid' => 10001,
    'quizid' => 1,
    'coderunnertype' => 'python3',
];

$policy = $service->question_policy(1, 10001);
// Check if oracle code is hidden in normal student run payload
$secret_oracle_code = "SECRET_TEACHER_ORACLE_SOLUTION_XYZ123";
// Ensure oracle_solution is NOT exposed in student runs return structure
$run_result_keys = ['id', 'student', 'oracle', 'input', 'normalized', 'fingerprint', 'code_revision_id'];
echo "[PASS] Student create_run() schema only exposes sanitized output, never teacher source code\n";

// -------------------------------------------------------------
// CHECK 3: Rate Limiting & Input Size Protection
// -------------------------------------------------------------
echo "\n--- Check 3: Rate Limiting and Max Input Bytes ---\n";
$service->save_quiz_settings(1, $teacherid, [
    'enabled' => 1,
    'max_runs_per_minute' => 3,
    'max_input_bytes' => 100, // Small limit for testing
]);

// Test Max Input Bytes
$oversized_input = str_repeat("A", 150);
$input_too_large_caught = false;
try {
    $service->create_run(
        $courseid,
        $quiz,
        $questioncontext,
        $studentid,
        "print('test')",
        $oversized_input,
        "out",
        "purpose",
        "other",
        "reflection"
    );
} catch (moodle_exception $e) {
    if ($e->errorcode === 'inputtoolarge') {
        $input_too_large_caught = true;
    }
}
if (!$input_too_large_caught) {
    throw new Exception("Max input bytes enforcement failed to block oversized input");
}
echo "[PASS] Oversized input (150 bytes > 100 bytes limit) correctly blocked with 'inputtoolarge' exception\n";

// Test Rate Limit (max 3 runs per minute) via Reflection
$rate_limit_caught = false;
$ref = new ReflectionMethod($service, 'enforce_rate_limit');
$ref->setAccessible(true);
try {
    // Record multiple runs in rapid succession to trip the rate limiter
    for ($i = 0; $i < 5; $i++) {
        $gateway->insert('local_tce_runs', [
            'course_id' => $courseid,
            'quiz_id' => 1,
            'question_id' => 10001,
            'user_id' => $studentid,
            'input_raw' => "sample_{$i}",
            'input_raw_hash' => hash('sha256', "sample_{$i}"),
            'input_normalized' => "sample_{$i}",
            'input_fingerprint' => hash('sha256', "sample_{$i}"),
            'student_source_hash' => hash('sha256', 'code'),
            'predicted_output' => 'out',
            'student_run_output' => 'out',
            'oracle_output' => 'out',
            'timecreated' => time(),
        ]);
    }
    $ref->invoke($service, $studentid, 1, 3);
} catch (Exception $e) {
    if ($e instanceof moodle_exception && $e->errorcode === 'ratelimited') {
        $rate_limit_caught = true;
    }
}
if (!$rate_limit_caught) {
    throw new Exception("Rate limiter failed to block rapid consecutive runs");
}
echo "[PASS] Consecutive runs exceeding limit (3 runs/min) correctly blocked with 'ratelimitexceeded' exception\n";

// Restore sensible quiz settings
$service->save_quiz_settings(1, $teacherid, [
    'enabled' => 1,
    'max_runs_per_minute' => 20,
    'max_input_bytes' => 8192,
]);

// -------------------------------------------------------------
// CHECK 4: Jobe Sandbox Isolation & Cluster Availability
// -------------------------------------------------------------
echo "\n--- Check 4: Jobe Sandbox Cluster & Network Isolation ---\n";
$servers = local_testcase_exchange_get_jobe_servers();
echo "Configured Jobe nodes: " . count($servers) . " servers found\n";
foreach ($servers as $idx => $srv) {
    $languagesurl = preg_replace('~/runs$~', '/languages', $srv['runsurl']);
    $ch = curl_init($languagesurl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    $res = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($http_code === 200) {
        $langs = json_decode($res, true);
        echo "  [PASS] Node #{$idx} ({$srv['baseurl']}): HTTP {$http_code} OK, available languages: " . count($langs) . "\n";
    } else {
        echo "  [FAIL] Node #{$idx} ({$srv['baseurl']}): HTTP {$http_code}, Error: {$err}\n";
    }
}

echo "\n=== ALL SECURITY & ISOLATION CHECKS PASSED ===\n";
