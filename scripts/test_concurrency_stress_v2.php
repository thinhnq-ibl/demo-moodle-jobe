<?php
/**
 * Comprehensive Concurrency, Idempotency & Rollback Stress Test (V2).
 *
 * Verifies:
 * 1. Two concurrent processes reviewing the SAME contribution with PEER candidate -> ASSERT EXACTLY 1 REWARD (source=contribution).
 * 2. Two concurrent processes reviewing the SAME contribution with SEED candidate -> ASSERT EXACTLY 1 REWARD (source=seed).
 * 3. Two concurrent processes reviewing TWO DIFFERENT contributions of the SAME student -> ASSERT EXACTLY 2 DISTINCT REWARDS.
 * 4. Claim reward failure during view event insertion triggers CLEAN TRANSACTION ROLLBACK (is_claimed remains 0).
 */

define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/clilib.php');

global $DB;

echo "=== STARTING COMPREHENSIVE CONCURRENCY & REWARD STRESS TESTS (V2) ===\n";

$courseid = 2;
$teacher = $DB->get_record('user', ['username' => 'admin']);
$teacherid = $teacher ? (int)$teacher->id : 2;

$student = $DB->get_record_select('user', "deleted = 0 AND id NOT IN (1, :teacherid)", ['teacherid' => $teacherid], '*', IGNORE_MULTIPLE);
$studentid = $student ? (int)$student->id : 3;

// Find peer student (different from studentid)
$peer = $DB->get_record_select('user', "deleted = 0 AND id NOT IN (1, :teacherid, :studentid)", ['teacherid' => $teacherid, 'studentid' => $studentid], '*', IGNORE_MULTIPLE);
if (!$peer) {
    // Create temporary peer user if not existing
    $peerid = $DB->insert_record('user', (object)[
        'username' => 'peer_student_' . time(),
        'email' => 'peer_' . time() . '@example.com',
        'firstname' => 'Peer',
        'lastname' => 'Student',
        'confirmed' => 1,
        'mnethostid' => 1,
    ]);
} else {
    $peerid = (int)$peer->id;
}

$gateway = new \local_testcase_exchange\db_gateway();
$service = new \local_testcase_exchange\testcase_service($gateway);

// Ensure quiz settings has reward_policy = one_for_one
$service->save_quiz_settings(1, $teacherid, [
    'enabled' => 1,
    'reward_policy' => 'one_for_one',
    'max_runs_per_minute' => 100,
    'max_input_bytes' => 8192,
]);

// =========================================================================
// TEST 1: Concurrent Approval of SAME Contribution with PEER candidate
// =========================================================================
echo "\n--- TEST 1: Concurrent Approval with Valid PEER Candidate ---\n";
$q_test1 = mt_rand(50000, 59999);

// Create an approved peer contribution from $peerid
$peer_run = $gateway->insert('local_tce_runs', [
    'course_id' => $courseid,
    'quiz_id' => 1,
    'question_id' => $q_test1,
    'user_id' => $peerid,
    'input_raw' => 'peer_input_t1',
    'input_raw_hash' => hash('sha256', 'peer_input_t1'),
    'input_normalized' => 'peer_input_t1',
    'input_fingerprint' => hash('sha256', 'peer_fp_t1_' . time()),
    'student_source_hash' => hash('sha256', 'code'),
    'predicted_output' => 'out',
    'student_run_output' => 'out',
    'oracle_output' => 'out',
    'student_outcome' => 'success',
    'oracle_outcome' => 'success',
    'timecreated' => time(),
]);
$peer_contrib_id = $gateway->insert('local_tce_contributions', [
    'run_id' => $peer_run,
    'course_id' => $courseid,
    'quiz_id' => 1,
    'question_id' => $q_test1,
    'user_id' => $peerid,
    'input_normalized' => 'peer_input_t1',
    'input_fingerprint' => hash('sha256', 'peer_fp_t1_' . time()),
    'oracle_output' => 'out',
    'purpose' => 'Peer candidate for Test 1',
    'status' => 'approved',
    'rating' => 5,
    'timecreated' => time(),
    'timemodified' => time(),
]);
echo "Prepared approved peer candidate ID={$peer_contrib_id} from User {$peerid}.\n";

// Create student contribution to be reviewed
$student_run1 = $gateway->insert('local_tce_runs', [
    'course_id' => $courseid,
    'quiz_id' => 1,
    'question_id' => $q_test1,
    'user_id' => $studentid,
    'input_raw' => 'student_input_t1',
    'input_raw_hash' => hash('sha256', 'student_input_t1'),
    'input_normalized' => 'student_input_t1',
    'input_fingerprint' => hash('sha256', 'student_fp_t1_' . time()),
    'student_source_hash' => hash('sha256', 'code'),
    'predicted_output' => 'out',
    'student_run_output' => 'out',
    'oracle_output' => 'out',
    'student_outcome' => 'success',
    'oracle_outcome' => 'success',
    'timecreated' => time(),
]);
$student_contrib1 = $gateway->insert('local_tce_contributions', [
    'run_id' => $student_run1,
    'course_id' => $courseid,
    'quiz_id' => 1,
    'question_id' => $q_test1,
    'user_id' => $studentid,
    'input_normalized' => 'student_input_t1',
    'input_fingerprint' => hash('sha256', 'student_fp_t1_' . time()),
    'oracle_output' => 'out',
    'purpose' => 'Student contribution for Test 1',
    'status' => 'submitted',
    'timecreated' => time(),
    'timemodified' => time(),
]);

$barrier1 = '/tmp/barrier_p1_' . uniqid() . '.lock';
$log1_a = '/tmp/worker_p1_a.json';
$log1_b = '/tmp/worker_p1_b.json';
@unlink($barrier1); @unlink($log1_a); @unlink($log1_b);

exec("php /tmp/concurrency_worker_review.php {$student_contrib1} {$teacherid} {$courseid} {$barrier1} {$log1_a} > /dev/null 2>&1 &");
exec("php /tmp/concurrency_worker_review.php {$student_contrib1} {$teacherid} {$courseid} {$barrier1} {$log1_b} > /dev/null 2>&1 &");
usleep(50000);
touch($barrier1);

$deadline = microtime(true) + 5.0;
while ((!file_exists($log1_a) || !file_exists($log1_b)) && microtime(true) < $deadline) {
    usleep(10000);
}

$rewards_t1 = $gateway->fetch_all(
    'SELECT * FROM {local_tce_rewards} WHERE receiver_user_id = ? AND question_id = ?',
    [$studentid, $q_test1]
);

echo "Worker A: " . @file_get_contents($log1_a) . "\n";
echo "Worker B: " . @file_get_contents($log1_b) . "\n";
echo "Rewards allocated for student in Question {$q_test1}: " . count($rewards_t1) . "\n";
if (count($rewards_t1) !== 1) {
    throw new Exception("TEST 1 FAILED: Expected EXACTLY 1 reward, but got " . count($rewards_t1));
}
if ($rewards_t1[0]['reward_source'] !== 'contribution' || (int)$rewards_t1[0]['contribution_id'] !== $peer_contrib_id) {
    throw new Exception("TEST 1 FAILED: Reward source/candidate mismatch: " . json_encode($rewards_t1[0]));
}
echo "✅ TEST 1 PASSED: Exactly 1 Peer reward granted without duplication.\n";

// =========================================================================
// TEST 2: Concurrent Approval of SAME Contribution with SEED candidate
// =========================================================================
echo "\n--- TEST 2: Concurrent Approval with Valid SEED Candidate ---\n";
$q_test2 = mt_rand(60000, 69999);

// Create an active seed testcase
$seed_id = $gateway->insert('local_tce_seeds', [
    'course_id' => $courseid,
    'quiz_id' => 1,
    'question_id' => $q_test2,
    'input_raw' => 'seed_input_t2',
    'input_normalized' => 'seed_input_t2',
    'input_fingerprint' => hash('sha256', 'seed_fp_t2_' . time()),
    'expected_output' => 'seed_out',
    'is_active' => 1,
    'timecreated' => time(),
]);
echo "Prepared active seed candidate ID={$seed_id}.\n";

// Create student contribution to be reviewed
$student_run2 = $gateway->insert('local_tce_runs', [
    'course_id' => $courseid,
    'quiz_id' => 1,
    'question_id' => $q_test2,
    'user_id' => $studentid,
    'input_raw' => 'student_input_t2',
    'input_raw_hash' => hash('sha256', 'student_input_t2'),
    'input_normalized' => 'student_input_t2',
    'input_fingerprint' => hash('sha256', 'student_fp_t2_' . time()),
    'student_source_hash' => hash('sha256', 'code'),
    'predicted_output' => 'out',
    'student_run_output' => 'out',
    'oracle_output' => 'out',
    'student_outcome' => 'success',
    'oracle_outcome' => 'success',
    'timecreated' => time(),
]);
$student_contrib2 = $gateway->insert('local_tce_contributions', [
    'run_id' => $student_run2,
    'course_id' => $courseid,
    'quiz_id' => 1,
    'question_id' => $q_test2,
    'user_id' => $studentid,
    'input_normalized' => 'student_input_t2',
    'input_fingerprint' => hash('sha256', 'student_fp_t2_' . time()),
    'oracle_output' => 'out',
    'purpose' => 'Student contribution for Test 2',
    'status' => 'submitted',
    'timecreated' => time(),
    'timemodified' => time(),
]);

$barrier2 = '/tmp/barrier_s2_' . uniqid() . '.lock';
$log2_a = '/tmp/worker_s2_a.json';
$log2_b = '/tmp/worker_s2_b.json';
@unlink($barrier2); @unlink($log2_a); @unlink($log2_b);

exec("php /tmp/concurrency_worker_review.php {$student_contrib2} {$teacherid} {$courseid} {$barrier2} {$log2_a} > /dev/null 2>&1 &");
exec("php /tmp/concurrency_worker_review.php {$student_contrib2} {$teacherid} {$courseid} {$barrier2} {$log2_b} > /dev/null 2>&1 &");
usleep(50000);
touch($barrier2);

$deadline = microtime(true) + 5.0;
while ((!file_exists($log2_a) || !file_exists($log2_b)) && microtime(true) < $deadline) {
    usleep(10000);
}

$rewards_t2 = $gateway->fetch_all(
    'SELECT * FROM {local_tce_rewards} WHERE receiver_user_id = ? AND question_id = ?',
    [$studentid, $q_test2]
);

echo "Worker A: " . @file_get_contents($log2_a) . "\n";
echo "Worker B: " . @file_get_contents($log2_b) . "\n";
echo "Rewards allocated for student in Question {$q_test2}: " . count($rewards_t2) . "\n";
if (count($rewards_t2) !== 1) {
    throw new Exception("TEST 2 FAILED: Expected EXACTLY 1 reward, but got " . count($rewards_t2));
}
if ($rewards_t2[0]['reward_source'] !== 'seed' || (int)$rewards_t2[0]['seed_id'] !== $seed_id) {
    throw new Exception("TEST 2 FAILED: Reward source/candidate mismatch: " . json_encode($rewards_t2[0]));
}
echo "✅ TEST 2 PASSED: Exactly 1 Seed reward granted without duplication.\n";

// =========================================================================
// TEST 3: Concurrent Approval of TWO DIFFERENT Contributions of the SAME user
// =========================================================================
echo "\n--- TEST 3: Two Different Contributions of the Same Student Reviewed Concurrently ---\n";
$q_test3 = mt_rand(70000, 79999);

// Create 2 distinct seeds available for allocation
$seed3_a = $gateway->insert('local_tce_seeds', [
    'course_id' => $courseid,
    'quiz_id' => 1,
    'question_id' => $q_test3,
    'input_raw' => 'seed_3a',
    'input_normalized' => 'seed_3a',
    'input_fingerprint' => hash('sha256', 'seed_3a_' . time()),
    'expected_output' => 'out_3a',
    'is_active' => 1,
    'timecreated' => time(),
]);
$seed3_b = $gateway->insert('local_tce_seeds', [
    'course_id' => $courseid,
    'quiz_id' => 1,
    'question_id' => $q_test3,
    'input_raw' => 'seed_3b',
    'input_normalized' => 'seed_3b',
    'input_fingerprint' => hash('sha256', 'seed_3b_' . time()),
    'expected_output' => 'out_3b',
    'is_active' => 1,
    'timecreated' => time(),
]);
echo "Prepared 2 distinct seeds (ID={$seed3_a}, ID={$seed3_b}) for Question {$q_test3}.\n";

// Student submits Contribution 1
$run3_1 = $gateway->insert('local_tce_runs', [
    'course_id' => $courseid, 'quiz_id' => 1, 'question_id' => $q_test3, 'user_id' => $studentid,
    'input_raw' => 's3_in_1', 'input_raw_hash' => hash('sha256', 's3_in_1'),
    'input_normalized' => 's3_in_1', 'input_fingerprint' => hash('sha256', 's3_fp_1_' . time()),
    'student_source_hash' => hash('sha256', 'c1'), 'predicted_output' => 'out', 'student_run_output' => 'out',
    'oracle_output' => 'out', 'student_outcome' => 'success', 'oracle_outcome' => 'success', 'timecreated' => time(),
]);
$contrib3_1 = $gateway->insert('local_tce_contributions', [
    'run_id' => $run3_1, 'course_id' => $courseid, 'quiz_id' => 1, 'question_id' => $q_test3,
    'user_id' => $studentid, 'input_normalized' => 's3_in_1',
    'input_fingerprint' => hash('sha256', 's3_fp_1_' . time()),
    'oracle_output' => 'out', 'purpose' => 'Contrib 1', 'status' => 'submitted',
    'timecreated' => time(), 'timemodified' => time(),
]);

// Student submits Contribution 2
$run3_2 = $gateway->insert('local_tce_runs', [
    'course_id' => $courseid, 'quiz_id' => 1, 'question_id' => $q_test3, 'user_id' => $studentid,
    'input_raw' => 's3_in_2', 'input_raw_hash' => hash('sha256', 's3_in_2'),
    'input_normalized' => 's3_in_2', 'input_fingerprint' => hash('sha256', 's3_fp_2_' . time()),
    'student_source_hash' => hash('sha256', 'c2'), 'predicted_output' => 'out', 'student_run_output' => 'out',
    'oracle_output' => 'out', 'student_outcome' => 'success', 'oracle_outcome' => 'success', 'timecreated' => time(),
]);
$contrib3_2 = $gateway->insert('local_tce_contributions', [
    'run_id' => $run3_2, 'course_id' => $courseid, 'quiz_id' => 1, 'question_id' => $q_test3,
    'user_id' => $studentid, 'input_normalized' => 's3_in_2',
    'input_fingerprint' => hash('sha256', 's3_fp_2_' . time()),
    'oracle_output' => 'out', 'purpose' => 'Contrib 2', 'status' => 'submitted',
    'timecreated' => time(), 'timemodified' => time(),
]);

echo "Created 2 distinct submitted contributions (ID={$contrib3_1}, ID={$contrib3_2}) for the same student.\n";

$barrier3 = '/tmp/barrier_diff_' . uniqid() . '.lock';
$log3_a = '/tmp/worker_diff_a.json';
$log3_b = '/tmp/worker_diff_b.json';
@unlink($barrier3); @unlink($log3_a); @unlink($log3_b);

// Process A reviews Contribution 1; Process B reviews Contribution 2 simultaneously
exec("php /tmp/concurrency_worker_review.php {$contrib3_1} {$teacherid} {$courseid} {$barrier3} {$log3_a} > /dev/null 2>&1 &");
exec("php /tmp/concurrency_worker_review.php {$contrib3_2} {$teacherid} {$courseid} {$barrier3} {$log3_b} > /dev/null 2>&1 &");
usleep(50000);
touch($barrier3);

$deadline = microtime(true) + 5.0;
while ((!file_exists($log3_a) || !file_exists($log3_b)) && microtime(true) < $deadline) {
    usleep(10000);
}

$rewards_t3 = $gateway->fetch_all(
    'SELECT * FROM {local_tce_rewards} WHERE receiver_user_id = ? AND question_id = ?',
    [$studentid, $q_test3]
);

echo "Worker A (Contrib 1): " . @file_get_contents($log3_a) . "\n";
echo "Worker B (Contrib 2): " . @file_get_contents($log3_b) . "\n";
echo "Total rewards allocated for student in Question {$q_test3}: " . count($rewards_t3) . "\n";
foreach ($rewards_t3 as $r) {
    echo "  - Reward ID={$r['id']}, source={$r['reward_source']}, seed_id={$r['seed_id']}, contrib_id={$r['contribution_id']}\n";
}

if (count($rewards_t3) !== 2) {
    throw new Exception("TEST 3 FAILED: Expected EXACTLY 2 rewards for 2 approved contributions, but got " . count($rewards_t3));
}
// Assert that the 2 rewards are DISTINCT (not the same seed granted twice)
$seed_ids = array_map(function($r) { return $r['seed_id']; }, $rewards_t3);
if ($seed_ids[0] === $seed_ids[1]) {
    throw new Exception("TEST 3 FAILED: Race condition! Both contributions received the EXACT SAME seed reward ID={$seed_ids[0]}!");
}
echo "✅ TEST 3 PASSED: Both distinct contributions received 2 DISTINCT testcase rewards without race conflict.\n";

// =========================================================================
// TEST 4: Transaction Rollback on Claim View Failure
// =========================================================================
echo "\n--- TEST 4: Rollback Integrity during claim_reward() Failure ---\n";
$q_test4 = mt_rand(80000, 89999);
$fail_reward_id = $gateway->insert('local_tce_rewards', [
    'course_id' => $courseid, 'quiz_id' => 1, 'question_id' => $q_test4,
    'receiver_user_id' => $studentid, 'contribution_id' => 99999, 'reward_source' => 'contribution',
    'is_claimed' => 0, 'timecreated' => time(),
]);

echo "Created test reward ID={$fail_reward_id} (is_claimed=0).\n";

// Test atomic rollback: Subclass db_gateway or inject a failure during view insert
class fault_injecting_gateway extends \local_testcase_exchange\db_gateway {
    public function insert(string $table, array $data): int {
        if ($table === 'local_tce_views') {
            throw new \dml_write_exception('Simulated catastrophic database failure during view event audit insertion');
        }
        return parent::insert($table, $data);
    }
}

$faulty_gateway = new fault_injecting_gateway();
$faulty_service = new \local_testcase_exchange\testcase_service($faulty_gateway);

$caught_exception = null;
try {
    $claim_result = $faulty_service->claim_reward($fail_reward_id, $studentid, $courseid);
    echo "Claim result with fault injection: " . ($claim_result ? "TRUE" : "FALSE") . "\n";
} catch (\Throwable $e) {
    $caught_exception = $e->getMessage();
    echo "Expected failure caught and rolled back: {$caught_exception}\n";
}

// Check DB state: was the UPDATE rolled back?
$reward_after_fail = $gateway->fetch_row('SELECT * FROM {local_tce_rewards} WHERE id = ?', [$fail_reward_id]);
echo "Reward state after failed claim: is_claimed={$reward_after_fail['is_claimed']}, claimed_at=" . var_export($reward_after_fail['claimed_at'], true) . "\n";

if ((int)$reward_after_fail['is_claimed'] !== 0) {
    throw new Exception("TEST 4 FAILED: Transaction rollback did NOT revert is_claimed back to 0!");
}
if (!$caught_exception) {
    throw new Exception("TEST 4 FAILED: Catastrophic fault was swallowed without rethrow!");
}
echo "✅ TEST 4 PASSED: Catastrophic audit failure cleanly rolled back the UPDATE. is_claimed remained 0.\n";

echo "\n=== ALL COMPREHENSIVE CONCURRENCY & TRANSACTION STRESS TESTS PASSED (100%) ===\n";
