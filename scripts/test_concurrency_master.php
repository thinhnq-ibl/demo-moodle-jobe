<?php
/**
 * Master Concurrency Verification Runner.
 *
 * Spawns multiple independent OS processes with separate database connections
 * synchronized via a high-precision barrier file.
 */

define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/clilib.php');

global $DB;

echo "=== STARTING MULTI-PROCESS CONCURRENCY STRESS TESTS ===\n";

$courseid = 2;
$teacher = $DB->get_record('user', ['username' => 'admin']);
$teacherid = $teacher ? (int)$teacher->id : 2;

$student = $DB->get_record_select('user', "deleted = 0 AND id NOT IN (1, :teacherid)", ['teacherid' => $teacherid], '*', IGNORE_MULTIPLE);
$studentid = $student ? (int)$student->id : 3;

$gateway = new \local_testcase_exchange\db_gateway();
$service = new \local_testcase_exchange\testcase_service($gateway);

// =========================================================================
// TEST 1: Concurrent Reward Claiming (2 independent processes)
// =========================================================================
echo "\n--- TEST 1: Two Independent Processes Calling claim_reward() Concurrently ---\n";

// Create a pending unclaimed reward
$test_contrib_id = mt_rand(10000, 999999);
$test_qid = mt_rand(30000, 39999);
$reward_id = $gateway->insert('local_tce_rewards', [
    'course_id' => $courseid,
    'quiz_id' => 1,
    'question_id' => $test_qid,
    'receiver_user_id' => $studentid,
    'contribution_id' => $test_contrib_id,
    'reward_source' => 'contribution',
    'is_claimed' => 0,
    'timecreated' => time(),
]);

echo "Created test reward record ID={$reward_id} (is_claimed=0).\n";

$barrier1 = '/tmp/barrier_claim_' . uniqid() . '.lock';
$log1_a = '/tmp/worker_claim_a.json';
$log1_b = '/tmp/worker_claim_b.json';
@unlink($barrier1);
@unlink($log1_a);
@unlink($log1_b);

// Launch Process A
$cmd_a = "php /tmp/concurrency_worker_claim.php {$reward_id} {$studentid} {$courseid} {$barrier1} {$log1_a} > /dev/null 2>&1 &";
// Launch Process B
$cmd_b = "php /tmp/concurrency_worker_claim.php {$reward_id} {$studentid} {$courseid} {$barrier1} {$log1_b} > /dev/null 2>&1 &";

exec($cmd_a);
exec($cmd_b);
usleep(50000); // 50ms to allow processes to boot and reach barrier spin loop

// Release barrier: Trigger simultaneous execution
touch($barrier1);
echo "Barrier released: both worker processes started claim_reward() concurrently.\n";

// Wait for both workers to complete (max 5s)
$deadline = microtime(true) + 5.0;
while ((!file_exists($log1_a) || !file_exists($log1_b)) && microtime(true) < $deadline) {
    usleep(10000);
}

$raw_a = @file_get_contents($log1_a);
$raw_b = @file_get_contents($log1_b);
echo "Worker A Raw Output: {$raw_a}\n";
echo "Worker B Raw Output: {$raw_b}\n";

// Verify Database State
$reward_after = $gateway->fetch_row('SELECT * FROM {local_tce_rewards} WHERE id = ?', [$reward_id]);
$view_events = $gateway->fetch_all(
    'SELECT * FROM {local_tce_views} WHERE reward_id = ? AND action = ?',
    [$reward_id, 'claim_reward']
);

$view_count = count($view_events);
echo "Database Inspection:\n";
echo "  - Reward ID={$reward_id}: is_claimed={$reward_after['is_claimed']}, claimed_at={$reward_after['claimed_at']}\n";
echo "  - Total 'claim_reward' view events recorded: {$view_count}\n";

if ((int)$reward_after['is_claimed'] !== 1) {
    throw new Exception("TEST 1 FAILED: Reward was not claimed!");
}

if ($view_count !== 1) {
    throw new Exception("TEST 1 FAILED: Expected exactly 1 view event, but found {$view_count} (RACE CONDITION DETECTED!)");
}
echo "✅ TEST 1 PASSED: Exactly 1 view event created. Idempotency & Concurrency lock verified.\n";

// =========================================================================
// TEST 2: Concurrent Review & Reward Granting (2 independent processes)
// =========================================================================
echo "\n--- TEST 2: Two Independent Processes Calling review() Concurrently ---\n";

// Create a pending contribution
$test_qid2 = mt_rand(40000, 49999);
$run_id2 = $gateway->insert('local_tce_runs', [
    'course_id' => $courseid,
    'quiz_id' => 1,
    'question_id' => $test_qid2,
    'user_id' => $studentid,
    'input_raw' => 'concur_grant_test_' . microtime(true),
    'input_raw_hash' => hash('sha256', 'concur_grant_test_' . microtime(true)),
    'input_normalized' => 'concur_grant_test',
    'input_fingerprint' => hash('sha256', 'concur_grant_test_' . microtime(true)),
    'student_source_hash' => hash('sha256', 'code'),
    'predicted_output' => 'out',
    'student_run_output' => 'out',
    'oracle_output' => 'out',
    'student_outcome' => 'success',
    'oracle_outcome' => 'success',
    'timecreated' => time(),
]);

$contrib_id2 = $gateway->insert('local_tce_contributions', [
    'run_id' => $run_id2,
    'course_id' => $courseid,
    'quiz_id' => 1,
    'question_id' => $test_qid2,
    'user_id' => $studentid,
    'input_normalized' => 'concur_grant_test',
    'input_fingerprint' => hash('sha256', 'concur_grant_test_' . microtime(true)),
    'oracle_output' => 'out',
    'purpose' => 'Concurrent grant test',
    'status' => 'submitted',
    'timecreated' => time(),
    'timemodified' => time(),
]);

echo "Created test contribution ID={$contrib_id2} (status=submitted).\n";

$barrier2 = '/tmp/barrier_rev_' . uniqid() . '.lock';
$log2_a = '/tmp/worker_rev_a.json';
$log2_b = '/tmp/worker_rev_b.json';
@unlink($barrier2);
@unlink($log2_a);
@unlink($log2_b);

// Launch Process A & B
$cmd_rev_a = "php /tmp/concurrency_worker_review.php {$contrib_id2} {$teacherid} {$courseid} {$barrier2} {$log2_a} > /dev/null 2>&1 &";
$cmd_rev_b = "php /tmp/concurrency_worker_review.php {$contrib_id2} {$teacherid} {$courseid} {$barrier2} {$log2_b} > /dev/null 2>&1 &";

exec($cmd_rev_a);
exec($cmd_rev_b);
usleep(50000);

// Release barrier
touch($barrier2);
echo "Barrier released: both worker processes started review() concurrently.\n";

$deadline2 = microtime(true) + 5.0;
while ((!file_exists($log2_a) || !file_exists($log2_b)) && microtime(true) < $deadline2) {
    usleep(10000);
}

$raw_rev_a = @file_get_contents($log2_a);
$raw_rev_b = @file_get_contents($log2_b);
echo "Review Worker A Raw Output: {$raw_rev_a}\n";
echo "Review Worker B Raw Output: {$raw_rev_b}\n";

// Verify Contribution & Rewards
$contrib2_after = $gateway->fetch_row('SELECT * FROM {local_tce_contributions} WHERE id = ?', [$contrib_id2]);
$rewards_for_q = $gateway->fetch_all(
    'SELECT * FROM {local_tce_rewards} WHERE receiver_user_id = ? AND question_id = ?',
    [$studentid, $test_qid2]
);

echo "Database Inspection:\n";
echo "  - Contribution ID={$contrib_id2}: status={$contrib2_after['status']}, rating={$contrib2_after['rating']}\n";
echo "  - Rewards allocated for student in question {$test_qid2}: " . count($rewards_for_q) . "\n";

// Count review audit logs
$reviews = $gateway->fetch_all('SELECT * FROM {local_tce_reviews} WHERE contribution_id = ?', [$contrib_id2]);
echo "  - Total review audit records created: " . count($reviews) . "\n";

if ($contrib2_after['status'] !== 'approved') {
    throw new Exception("TEST 2 FAILED: Contribution status was not approved!");
}
if (count($rewards_for_q) > 1) {
    throw new Exception("TEST 2 FAILED: Multiple duplicate rewards granted (" . count($rewards_for_q) . ")!");
}
echo "✅ TEST 2 PASSED: Exactly <= 1 reward granted without duplication under concurrent execution.\n";

echo "\n=== ALL CONCURRENCY & MULTI-PROCESS TESTS PASSED ===\n";
