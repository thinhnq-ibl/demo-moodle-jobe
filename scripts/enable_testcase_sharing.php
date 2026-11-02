<?php
define('CLI_SCRIPT', true);
require_once('/var/www/html/config.php');
require_once('/var/www/html/course/lib.php');
require_once('/var/www/html/mod/quiz/lib.php');

global $DB, $CFG;

$now = time();
$quizzes = $DB->get_records('quiz');
echo "Enabling Testcase Exchange for all " . count($quizzes) . " quizzes...\n";

$enabled_count = 0;
foreach ($quizzes as $quiz) {
    $existing = $DB->get_record('local_tce_quiz_settings', ['quiz_id' => $quiz->id]);
    if ($existing) {
        $existing->is_enabled = 1;
        $existing->enabled = 1;
        $existing->timemodified = $now;
        $DB->update_record('local_tce_quiz_settings', $existing);
    } else {
        $record = new \stdClass();
        $record->quiz_id = (int)$quiz->id;
        $record->is_enabled = 1;
        $record->enabled = 1;
        $record->review_mode = 'teacher';
        $record->reward_policy = 'one_for_one';
        $record->show_oracle_output = 1;
        $record->leaderboard_enabled = 1;
        $record->max_runs_per_minute = 25;
        $record->max_input_bytes = 8192;
        $record->updated_by = 2; // admin
        $record->timecreated = $now;
        $record->timemodified = $now;
        $DB->insert_record('local_tce_quiz_settings', $record);
    }
    $enabled_count++;
}
echo "Enabled testcase exchange on {$enabled_count} quizzes in local_tce_quiz_settings.\n";

// Add Activity Link to Course 4 if not present
$course4 = $DB->get_record('course', ['id' => 4]);
$modurl = $DB->get_record('modules', ['name' => 'url'], '*', MUST_EXIST);
$link4 = $CFG->wwwroot . '/local/testcase_exchange/index.php?course=4';

$existing4 = $DB->get_record('url', ['course' => 4, 'externalurl' => $link4]);
if (!$existing4 && $course4) {
    $u = new \stdClass();
    $u->course = 4;
    $u->name = '🎁 Kho Testcase & Trao đổi Đóng góp (MultiSolve Hub)';
    $u->intro = '<p>Bấm vào đây để đóng góp testcase độc lập, mở khóa các ca kiểm thử mới từ ngân hàng đề và thi đua bảng xếp hạng cùng lớp!</p>';
    $u->introformat = FORMAT_HTML;
    $u->externalurl = $link4;
    $u->display = 0;
    $u->timemodified = $now;
    $uid = $DB->insert_record('url', $u);

    // Section 0 of Course 4 is section id 9
    $section0 = $DB->get_record('course_sections', ['course' => 4, 'section' => 0]);
    if ($section0) {
        $cm = new \stdClass();
        $cm->course = 4;
        $cm->module = $modurl->id;
        $cm->instance = $uid;
        $cm->section = $section0->id;
        $cm->added = $now;
        $cm->visible = 1;
        $cmid = add_course_module($cm);
        course_add_cm_to_section($course4, $cmid, 0);
        echo "Added URL activity module to Course 4 Section 0 (cmid: {$cmid}).\n";
    }
} else {
    echo "Activity link for Course 4 already exists or course not found.\n";
}

// Ensure Course 2 has testcase exchange link
$course2 = $DB->get_record('course', ['id' => 2]);
$link2 = $CFG->wwwroot . '/local/testcase_exchange/index.php?course=2';
$existing2 = $DB->get_record('url', ['course' => 2, 'externalurl' => $link2]);
if (!$existing2 && $course2) {
    $u = new \stdClass();
    $u->course = 2;
    $u->name = '🎁 Kho Testcase & Trao đổi Đóng góp (MultiSolve Hub)';
    $u->intro = '<p>Bấm vào đây để đóng góp và trao đổi testcase.</p>';
    $u->introformat = FORMAT_HTML;
    $u->externalurl = $link2;
    $u->display = 0;
    $u->timemodified = $now;
    $uid = $DB->insert_record('url', $u);

    $section0 = $DB->get_record('course_sections', ['course' => 2, 'section' => 0]);
    if ($section0) {
        $cm = new \stdClass();
        $cm->course = 2;
        $cm->module = $modurl->id;
        $cm->instance = $uid;
        $cm->section = $section0->id;
        $cm->added = $now;
        $cm->visible = 1;
        $cmid = add_course_module($cm);
        course_add_cm_to_section($course2, $cmid, 0);
        echo "Added URL activity module to Course 2 Section 0 (cmid: {$cmid}).\n";
    }
}

echo "Purging all Moodle caches...\n";
purge_all_caches();
echo "All done!\n";
