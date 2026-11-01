<?php
/**
 * Test script to reproduce subquery returns more than 1 row in contributions_for_user().
 */

define('CLI_SCRIPT', true);
require('/var/www/html/config.php');
require_once($CFG->libdir . '/clilib.php');

global $DB;

echo "=== TESTING CONTRIBUTIONS_FOR_USER WITH MULTIPLE REVIEWS ===\n";

$course = $DB->get_record('course', ['id' => 2]);
$courseid = $course ? (int)$course->id : 1;

$teacher = $DB->get_record('user', ['username' => 'admin']);
$teacherid = $teacher ? (int)$teacher->id : 2;

$student = $DB->get_record_select('user', "deleted = 0 AND id NOT IN (1, :teacherid)", ['teacherid' => $teacherid], '*', IGNORE_MULTIPLE);
$studentid = $student ? (int)$student->id : 3;

$gateway = new \local_testcase_exchange\db_gateway();
$service = new \local_testcase_exchange\testcase_service($gateway);

// 1. Insert a test run
$runid = $gateway->insert('local_tce_runs', [
    'course_id' => $courseid,
    'quiz_id' => 1,
    'question_id' => 10001,
    'user_id' => $studentid,
    'input_raw' => 'multi_review_test',
    'input_raw_hash' => hash('sha256', 'multi_review_test'),
    'input_normalized' => 'multi_review_test',
    'input_fingerprint' => hash('sha256', 'multi_review_test_' . time()),
    'student_source_hash' => hash('sha256', 'code'),
    'predicted_output' => 'out',
    'student_run_output' => 'out',
    'oracle_output' => 'out',
    'student_outcome' => 'success',
    'oracle_outcome' => 'success',
    'timecreated' => time(),
]);

// 2. Insert contribution
$contribid = $gateway->insert('local_tce_contributions', [
    'run_id' => $runid,
    'course_id' => $courseid,
    'quiz_id' => 1,
    'question_id' => 10001,
    'user_id' => $studentid,
    'input_normalized' => 'multi_review_test',
    'input_fingerprint' => hash('sha256', 'multi_review_test_' . time()),
    'oracle_output' => 'out',
    'purpose' => 'Multi review test',
    'status' => 'submitted',
    'timecreated' => time(),
    'timemodified' => time(),
]);

echo "Created test contribution ID={$contribid}\n";

// 3. Add 3 distinct reviews for this contribution
$gateway->insert('local_tce_reviews', [
    'contribution_id' => $contribid,
    'reviewer_user_id' => $teacherid,
    'from_status' => 'submitted',
    'to_status' => 'needs_explanation',
    'review_note' => 'First review: please elaborate purpose.',
    'rating' => 2,
    'timecreated' => time() - 200,
]);

$gateway->insert('local_tce_reviews', [
    'contribution_id' => $contribid,
    'reviewer_user_id' => $teacherid,
    'from_status' => 'needs_explanation',
    'to_status' => 'submitted',
    'review_note' => 'Second review: looks better.',
    'rating' => 4,
    'timecreated' => time() - 100,
]);

$gateway->insert('local_tce_reviews', [
    'contribution_id' => $contribid,
    'reviewer_user_id' => $teacherid,
    'from_status' => 'submitted',
    'to_status' => 'approved',
    'review_note' => 'Third review: fully approved!',
    'rating' => 5,
    'timecreated' => time(),
]);

echo "Inserted 3 review audit records for contribution ID={$contribid}.\n";

// 4. Call contributions_for_user()
try {
    $rows = $service->contributions_for_user($courseid, $studentid);
    echo "Retrieved " . count($rows) . " contributions.\n";
    $target = null;
    foreach ($rows as $r) {
        if ((int)$r['id'] === $contribid) {
            $target = $r;
            break;
        }
    }
    if ($target) {
        echo "✅ Success without SQL fatal error!\n";
        echo "  latest_review_note: '{$target['latest_review_note']}'\n";
        echo "  latest_rating: '{$target['latest_rating']}'\n";
    }
} catch (\Throwable $e) {
    echo "❌ FATAL SQL ERROR REPRODUCED: " . $e->getMessage() . "\n";
}
