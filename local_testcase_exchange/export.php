<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Web export endpoint for student learning and testcase timeline.
 *
 * @package    local_testcase_exchange
 * @copyright  2026 Nguyen Quoc Thinh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/external_database.php');

$courseid = required_param('course', PARAM_INT);
$quizid = optional_param('quiz', 0, PARAM_INT);
$format = optional_param('format', 'csv', PARAM_ALPHA);
$targetuserid = optional_param('userid', 0, PARAM_INT);
$includecode = optional_param('include_code', 0, PARAM_BOOL);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);

$context = context_course::instance($course->id);
require_capability('local/testcase_exchange:view', $context);

$isteacher = has_capability('local/testcase_exchange:review', $context) || 
             has_capability('moodle/course:manageactivities', $context);

// Non-teachers can only export their own timeline.
if (!$isteacher) {
    $targetuserid = (int)$USER->id;
}

try {
    $conn = \local_testcase_exchange\external_database::connect();
} catch (\Throwable $e) {
    print_error('dberror', 'local_testcase_exchange', '', $e->getMessage());
}

$whereclauses = ['tl.course_id = ?'];
$params = [(int)$courseid];
$types = 'i';

if ($quizid > 0) {
    $whereclauses[] = 'tl.quiz_id = ?';
    $params[] = (int)$quizid;
    $types .= 'i';
}
if ($targetuserid > 0) {
    $whereclauses[] = 'tl.user_id = ?';
    $params[] = (int)$targetuserid;
    $types .= 'i';
}

$wheresql = 'WHERE ' . implode(' AND ', $whereclauses);

$sql = "
SELECT 
    tl.event_time,
    tl.user_id,
    u.username,
    CONCAT(u.firstname, ' ', u.lastname) AS fullname,
    tl.course_id,
    tl.quiz_id,
    tl.question_id,
    tl.event_type,
    tl.event_name,
    tl.code_version,
    tl.status,
    tl.rating,
    tl.comment,
    tl.details,
    tl.code_snapshot,
    tl.test_input,
    tl.test_output
FROM (
    -- 1. Playground test runs
    SELECT 
        tr.created_at AS event_time,
        tr.user_id,
        tr.course_id,
        tr.quiz_id,
        tr.question_id,
        'PLAYGROUND_TEST' AS event_type,
        'Chạy thử nghiệm (Contribute Zone)' AS event_name,
        COALESCE(scr.step_number, 1) AS code_version,
        tr.student_outcome AS status,
        NULL AS rating,
        NULL AS comment,
        CONCAT('Mục đích: ', COALESCE(tr.purpose, 'N/A'), ' | Phân loại: ', COALESCE(tr.category, 'N/A'), 
               ' | Kết quả SV: ', tr.student_outcome, ' | Kết quả Oracle: ', tr.oracle_outcome) AS details,
        tr.student_code_snapshot AS code_snapshot,
        tr.input_raw AS test_input,
        tr.student_run_output AS test_output
    FROM testcase_store.testcase_runs tr
    LEFT JOIN testcase_store.student_code_revisions scr ON tr.code_revision_id = scr.id

    UNION ALL

    -- 2. Testcase contributions
    SELECT 
        tc.submitted_at AS event_time,
        tc.user_id,
        tc.course_id,
        tc.quiz_id,
        tc.question_id,
        'TESTCASE_CONTRIBUTE' AS event_type,
        'Đề xuất đóng góp Testcase' AS event_name,
        NULL AS code_version,
        tc.status AS status,
        NULL AS rating,
        NULL AS comment,
        CONCAT('Testcase #', tc.id, ' | Mục đích: ', COALESCE(tc.purpose, 'N/A'), ' | Phân loại: ', COALESCE(tc.category, 'N/A')) AS details,
        NULL AS code_snapshot,
        tc.input_normalized AS test_input,
        tc.oracle_output AS test_output
    FROM testcase_store.testcase_contributions tc

    UNION ALL

    -- 3. Testcase reviews, accept/reject, rating & teacher comment
    SELECT 
        cr.created_at AS event_time,
        tc.user_id,
        tc.course_id,
        tc.quiz_id,
        tc.question_id,
        'TESTCASE_REVIEW' AS event_type,
        CASE 
            WHEN cr.to_status = 'approved' AND cr.rating IS NOT NULL THEN 'Testcase được Duyệt & Chấm điểm'
            WHEN cr.to_status = 'approved' THEN 'Testcase được Duyệt (Accepted)'
            WHEN cr.to_status = 'rejected' THEN 'Testcase bị Từ chối (Rejected)'
            ELSE 'Thẩm định & Đánh giá Testcase'
        END AS event_name,
        NULL AS code_version,
        cr.to_status AS status,
        cr.rating AS rating,
        cr.review_note AS comment,
        CONCAT('Testcase #', tc.id, ' [', cr.from_status, ' -> ', cr.to_status, ']', 
               IF(cr.rating IS NOT NULL, CONCAT(' | Đánh giá: ', cr.rating, ' sao (', COALESCE(cr.rating_reason, ''), ')'), ''),
               IF(cr.review_note IS NOT NULL AND cr.review_note != '', CONCAT(' | Nhận xét GV: ', cr.review_note), '')) AS details,
        NULL AS code_snapshot,
        tc.input_normalized AS test_input,
        tc.oracle_output AS test_output
    FROM testcase_store.contribution_reviews cr
    JOIN testcase_store.testcase_contributions tc ON cr.contribution_id = tc.id

    UNION ALL

    -- 4. Testcase rewards
    SELECT 
        COALESCE(trw.claimed_at, trw.created_at) AS event_time,
        trw.receiver_user_id AS user_id,
        trw.course_id,
        trw.quiz_id,
        trw.question_id,
        'TESTCASE_REWARD' AS event_type,
        'Nhận testcase thưởng (Kho chung)' AS event_name,
        NULL AS code_version,
        IF(trw.is_claimed = 1, 'claimed', 'unclaimed') AS status,
        NULL AS rating,
        NULL AS comment,
        CONCAT('Nhận Testcase #', trw.contribution_id, ' từ nguồn: ', trw.reward_source, 
               IF(trw.is_claimed = 1, ' (Đã mở khóa)', ' (Chưa mở khóa)')) AS details,
        NULL AS code_snapshot,
        NULL AS test_input,
        NULL AS test_output
    FROM testcase_store.testcase_rewards trw

    UNION ALL

    -- 5. Quiz submissions
    SELECT 
        FROM_UNIXTIME(qa.timefinish) AS event_time,
        qa.userid AS user_id,
        q.course AS course_id,
        qa.quiz AS quiz_id,
        0 AS question_id,
        'QUIZ_SUBMIT' AS event_type,
        'Nộp bài thi Quiz lấy điểm' AS event_name,
        NULL AS code_version,
        qa.state AS status,
        NULL AS rating,
        NULL AS comment,
        CONCAT('Lần nộp thi #', qa.attempt, ' | Điểm đạt: ', COALESCE(qa.sumgrades, 0), ' / ', q.sumgrades) AS details,
        NULL AS code_snapshot,
        NULL AS test_input,
        NULL AS test_output
    FROM moodle.mdl_quiz_attempts qa
    JOIN moodle.mdl_quiz q ON qa.quiz = q.id
    WHERE qa.timefinish > 0
) AS tl
JOIN moodle.mdl_user u ON tl.user_id = u.id
{$wheresql}
ORDER BY tl.event_time ASC;
";

$stmt = $conn->prepare($sql);
if (!$stmt) {
    print_error('dberror', 'local_testcase_exchange', '', $conn->error);
}

$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}
$stmt->close();
$conn->close();

$timestamp = date('Ymd_His');
$filename = "student_timeline_c{$courseid}" . ($quizid ? "_q{$quizid}" : "") . "_{$timestamp}";

if ($format === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"{$filename}.json\"");
    echo json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
}

// Default CSV export
header('Content-Type: text/csv; charset=utf-8');
header("Content-Disposition: attachment; filename=\"{$filename}.csv\"");

// Add UTF-8 BOM so Excel opens Vietnamese characters correctly without encoding issues
echo "\xEF\xBB\xBF";

$output = fopen('php://output', 'w');
$headers = [
    'event_time', 'username', 'fullname', 'course_id', 'quiz_id', 'question_id',
    'event_type', 'event_name', 'code_version', 'status', 'rating', 'comment', 'details'
];
if ($includecode) {
    $headers[] = 'code_snapshot';
    $headers[] = 'test_input';
    $headers[] = 'test_output';
}

fputcsv($output, $headers);
foreach ($rows as $r) {
    $rowdata = [];
    foreach ($headers as $h) {
        $rowdata[] = $r[$h] ?? '';
    }
    fputcsv($output, $rowdata);
}
fclose($output);
exit;
