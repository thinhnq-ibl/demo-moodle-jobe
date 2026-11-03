<?php
/**
 * Test script to reproduce fingerprint loss due to non-unique first column in get_records_sql().
 */

define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/clilib.php');

global $DB;

echo "=== TESTING EXISTING_FINGERPRINTS_FOR_COURSE DEDUPLICATION LOSS ===\n";

$courseid = 2;
$gateway = new \local_testcase_exchange\db_gateway();
$service = new \local_testcase_exchange\testcase_service($gateway);

$test_qid = 99991;

// Clean up
$DB->delete_records('local_tce_canonical', ['course_id' => $courseid, 'question_id' => $test_qid]);

// Insert 3 distinct canonical testcases for the SAME question_id
$fp1 = 'fp_alpha_' . bin2hex(random_bytes(8));
$fp2 = 'fp_beta_' . bin2hex(random_bytes(8));
$fp3 = 'fp_gamma_' . bin2hex(random_bytes(8));

$gateway->insert('local_tce_canonical', [
    'course_id' => $courseid,
    'quiz_id' => 1,
    'question_id' => $test_qid,
    'canonical_contribution_id' => 101,
    'input_fingerprint' => $fp1,
    'timecreated' => time(),
]);

$gateway->insert('local_tce_canonical', [
    'course_id' => $courseid,
    'quiz_id' => 1,
    'question_id' => $test_qid,
    'canonical_contribution_id' => 102,
    'input_fingerprint' => $fp2,
    'timecreated' => time(),
]);

$gateway->insert('local_tce_canonical', [
    'course_id' => $courseid,
    'quiz_id' => 1,
    'question_id' => $test_qid,
    'canonical_contribution_id' => 103,
    'input_fingerprint' => $fp3,
    'timecreated' => time(),
]);

echo "Inserted 3 canonical testcases for question_id={$test_qid}.\n";

// Call existing_fingerprints_for_course()
$map = $service->existing_fingerprints_for_course($courseid);

$count_for_q = 0;
foreach ($map as $key => $info) {
    if (strpos($key, "{$test_qid}:") === 0) {
        $count_for_q++;
    }
}

echo "Found {$count_for_q} canonical fingerprints for question_id={$test_qid} in map.\n";
if ($count_for_q < 3) {
    echo "❌ BUG CONFIRMED: Expected 3 fingerprints, but only got {$count_for_q} because get_records_sql indexed by first column 'question_id' and overwrote records!\n";
} else {
    echo "✅ PASS: All 3 fingerprints retrieved successfully.\n";
}

// Clean up
$DB->delete_records('local_tce_canonical', ['course_id' => $courseid, 'question_id' => $test_qid]);
