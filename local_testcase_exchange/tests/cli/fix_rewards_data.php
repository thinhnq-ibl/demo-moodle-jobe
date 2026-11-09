<?php
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->dirroot . '/local/testcase_exchange/classes/testcase_service.php');

global $DB;

echo "=== Fixing Seed and Reward Data for Course 3 / Quiz 2 / Question 119 ===\n";

$courseid = 3;
$quizid = 2;
$questionid = 119;
$student = $DB->get_record('user', ['username' => 'student1'], '*', MUST_EXIST);
$student2 = $DB->get_record('user', ['username' => 'student2']);

if (!$student2) {
    // Fallback: get any other student or admin
    $student2 = $DB->get_record('user', ['username' => 'admin'], '*', MUST_EXIST);
}

$now = time();

// 1. Seed 1: Teacher Seed Bank (Prime 13)
$seed1_code = 'System.out.println(isPrime(13));';
$seed1_fp = hash('sha256', trim($seed1_code));
$seed1 = $DB->get_record('local_tce_seeds', ['question_id' => $questionid, 'input_fingerprint' => $seed1_fp]);

if (!$seed1) {
    $seed1_id = $DB->insert_record('local_tce_seeds', (object)[
        'course_id' => $courseid,
        'quiz_id' => $quizid,
        'question_id' => $questionid,
        'testcode' => $seed1_code,
        'stdin' => '',
        'input_raw' => $seed1_code,
        'input_normalized' => $seed1_code,
        'input_fingerprint' => $seed1_fp,
        'expected_output' => "true\n",
        'category' => 'normal',
        'is_active' => 1,
        'timecreated' => $now - 7200,
    ]);
    echo "Created Seed 1 (id: {$seed1_id})\n";
} else {
    $seed1_id = $seed1->id;
    $DB->update_record('local_tce_seeds', (object)[
        'id' => $seed1_id,
        'testcode' => $seed1_code,
        'input_raw' => $seed1_code,
        'input_normalized' => $seed1_code,
        'expected_output' => "true\n",
        'category' => 'normal',
    ]);
    echo "Updated Seed 1 (id: {$seed1_id})\n";
}

// 2. Seed 2: Teacher Seed Bank (Composite 100)
$seed2_code = 'System.out.println(isPrime(100));';
$seed2_fp = hash('sha256', trim($seed2_code));
$seed2 = $DB->get_record('local_tce_seeds', ['question_id' => $questionid, 'input_fingerprint' => $seed2_fp]);

if (!$seed2) {
    $seed2_id = $DB->insert_record('local_tce_seeds', (object)[
        'course_id' => $courseid,
        'quiz_id' => $quizid,
        'question_id' => $questionid,
        'testcode' => $seed2_code,
        'stdin' => '',
        'input_raw' => $seed2_code,
        'input_normalized' => $seed2_code,
        'input_fingerprint' => $seed2_fp,
        'expected_output' => "false\n",
        'category' => 'boundary',
        'is_active' => 1,
        'timecreated' => $now - 7200,
    ]);
    echo "Created Seed 2 (id: {$seed2_id})\n";
} else {
    $seed2_id = $seed2->id;
    $DB->update_record('local_tce_seeds', (object)[
        'id' => $seed2_id,
        'testcode' => $seed2_code,
        'input_raw' => $seed2_code,
        'input_normalized' => $seed2_code,
        'expected_output' => "false\n",
        'category' => 'boundary',
    ]);
    echo "Updated Seed 2 (id: {$seed2_id})\n";
}

// 3. Peer Contribution from Student2 (Prime 97)
$peer_code = 'System.out.println(isPrime(97));';
$peer_fp = hash('sha256', trim($peer_code));
$peer_run = $DB->get_record('local_tce_runs', ['question_id' => $questionid, 'input_fingerprint' => $peer_fp, 'user_id' => $student2->id]);

if (!$peer_run) {
    $peer_run_id = $DB->insert_record('local_tce_runs', (object)[
        'course_id' => $courseid,
        'quiz_id' => $quizid,
        'question_id' => $questionid,
        'user_id' => $student2->id,
        'input_raw' => $peer_code,
        'input_normalized' => $peer_code,
        'input_fingerprint' => $peer_fp,
        'expected_output' => "true\n",
        'purpose' => 'Kiểm thử số nguyên tố lớn 2 chữ số (97)',
        'category' => 'normal',
        'code_raw' => "public static boolean isPrime(int n) {\n    if (n <= 1) return false;\n    for (int i = 2; i * i <= n; i++) {\n        if (n % i == 0) return false;\n    }\n    return true;\n}",
        'code_hash' => hash('sha256', 'student2_code'),
        'oracle_output' => "true\n",
        'oracle_status' => 'success',
        'is_reproducible' => 1,
        'status' => 'success',
        'timecreated' => $now - 5000,
    ]);
    echo "Created Peer Run (id: {$peer_run_id})\n";
} else {
    $peer_run_id = $peer_run->id;
}

$peer_contrib = $DB->get_record('local_tce_contributions', ['run_id' => $peer_run_id]);
if (!$peer_contrib) {
    $peer_contrib_id = $DB->insert_record('local_tce_contributions', (object)[
        'run_id' => $peer_run_id,
        'course_id' => $courseid,
        'quiz_id' => $quizid,
        'question_id' => $questionid,
        'user_id' => $student2->id,
        'input_normalized' => $peer_code,
        'input_fingerprint' => $peer_fp,
        'expected_output' => "true\n",
        'oracle_output' => "true\n",
        'purpose' => 'Kiểm thử số nguyên tố lớn 2 chữ số (97)',
        'category' => 'normal',
        'status' => 'approved',
        'timecreated' => $now - 4500,
        'timemodified' => $now - 4000,
    ]);
    echo "Created Peer Contribution (id: {$peer_contrib_id})\n";
} else {
    $peer_contrib_id = $peer_contrib->id;
    $DB->update_record('local_tce_contributions', (object)[
        'id' => $peer_contrib_id,
        'status' => 'approved',
        'input_normalized' => $peer_code,
        'expected_output' => "true\n",
        'oracle_output' => "true\n",
        'category' => 'normal',
    ]);
    echo "Updated Peer Contribution (id: {$peer_contrib_id})\n";
}

// 4. Update or Insert Reward records for student1
// Update reward #52 -> Teacher Seed 1
if ($DB->record_exists('local_tce_rewards', ['id' => 52])) {
    $DB->update_record('local_tce_rewards', (object)[
        'id' => 52,
        'course_id' => $courseid,
        'quiz_id' => $quizid,
        'question_id' => $questionid,
        'receiver_user_id' => $student->id,
        'reward_source' => 'seed',
        'seed_id' => $seed1_id,
        'contribution_id' => null,
        'is_claimed' => 1,
        'claimed_at' => $now - 3600,
        'timecreated' => $now - 7200,
    ]);
    echo "Updated Reward #52 -> Seed 1 (Prime 13)\n";
}

// Update reward #53 -> Peer Contribution
if ($DB->record_exists('local_tce_rewards', ['id' => 53])) {
    $DB->update_record('local_tce_rewards', (object)[
        'id' => 53,
        'course_id' => $courseid,
        'quiz_id' => $quizid,
        'question_id' => $questionid,
        'receiver_user_id' => $student->id,
        'reward_source' => 'contribution',
        'contribution_id' => $peer_contrib_id,
        'seed_id' => null,
        'is_claimed' => 1,
        'claimed_at' => $now - 1200,
        'timecreated' => $now - 4000,
    ]);
    echo "Updated Reward #53 -> Peer Contribution (Prime 97)\n";
}

echo "=== All Seed and Reward data fixed successfully! ===\n";
