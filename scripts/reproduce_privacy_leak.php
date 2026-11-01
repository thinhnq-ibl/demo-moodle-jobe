<?php
/**
 * Test script to reproduce Privacy Export leaking teacher oracle code.
 */

define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/local/testcase_exchange/classes/privacy/provider.php');

global $DB;

echo "=== TESTING PRIVACY EXPORT FOR ORACLE CODE LEAKAGE ===\n";

$course = $DB->get_record('course', ['id' => 2]);
$courseid = $course ? (int)$course->id : 1;

$teacher = $DB->get_record('user', ['username' => 'admin']);
$teacherid = $teacher ? (int)$teacher->id : 2;

$student = $DB->get_record_select('user', "deleted = 0 AND id NOT IN (1, :teacherid)", ['teacherid' => $teacherid], '*', IGNORE_MULTIPLE);
$studentid = $student ? (int)$student->id : 3;

$secret_oracle_code = "SECRET_TEACHER_SOLUTION_DEF_SOLVE_XYZ_987654321";

// Insert a test run with confidential teacher code snapshot
$runid = $DB->insert_record('local_tce_runs', (object)[
    'course_id' => $courseid,
    'quiz_id' => 1,
    'question_id' => 10001,
    'user_id' => $studentid,
    'input_raw' => 'secret_test_input',
    'input_raw_hash' => hash('sha256', 'secret_test_input'),
    'input_normalized' => 'secret_test_input',
    'input_fingerprint' => hash('sha256', 'secret_test_input'),
    'student_source_hash' => hash('sha256', 'student_code'),
    'student_code_snapshot' => 'def solve(): return 42',
    'predicted_output' => '42',
    'student_run_output' => '42',
    'oracle_output' => '42',
    'oracle_solution_snapshot' => $secret_oracle_code,
    'template_snapshot' => 'SECRET_TEMPLATE_CODE',
    'runtime_context' => '{"internal_env":"secret"}',
    'student_outcome' => 'success',
    'oracle_outcome' => 'success',
    'timecreated' => time(),
]);

echo "Inserted test run ID={$runid} with confidential oracle code.\n";

// Execute privacy export
$context = context_course::instance($courseid);
$user = \core_user::get_user($studentid);
$approved_contextlist = new \core_privacy\tests\request\approved_contextlist(
    $user,
    'local_testcase_exchange',
    [$context->id]
);

\local_testcase_exchange\privacy\provider::export_user_data($approved_contextlist);

$writer = \core_privacy\local\request\writer::with_context($context);
$exported_data = $writer->get_data([get_string('privacy:path', 'local_testcase_exchange')]);

echo "Inspecting exported data...\n";
$json = json_encode($exported_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

if (strpos($json, $secret_oracle_code) !== false) {
    echo "❌ VULNERABILITY CONFIRMED: Secret oracle code found in exported privacy data!\n";
    echo "Found leaked snippet:\n";
    foreach ($exported_data->private_runs as $r) {
        if (isset($r->oracle_solution_snapshot) && $r->oracle_solution_snapshot === $secret_oracle_code) {
            echo "  Leaked in private_runs record ID={$r->id}: oracle_solution_snapshot = '{$r->oracle_solution_snapshot}'\n";
        }
    }
} else {
    echo "✅ CLEAN: Secret oracle code is NOT in exported privacy data.\n";
}

// Clean up
$DB->delete_records('local_tce_runs', ['id' => $runid]);
