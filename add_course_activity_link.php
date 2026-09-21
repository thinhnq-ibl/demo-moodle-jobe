<?php
define('CLI_SCRIPT', true);
require_once('/var/www/html/config.php');
require_once('/var/www/html/course/lib.php');

global $DB, $CFG;

$course = $DB->get_record('course', ['id' => 3], '*', MUST_EXIST);
$mod = $DB->get_record('modules', ['name' => 'url'], '*', MUST_EXIST);

$link_url = $CFG->wwwroot . '/local/testcase_exchange/index.php?course=' . $course->id;

// Kiểm tra xem đã có link URL module này chưa
$existing_url = $DB->get_record('url', ['course' => $course->id, 'externalurl' => $link_url]);
if (!$existing_url) {
    $u = new stdClass();
    $u->course = $course->id;
    $u->name = '🎁 Kho Testcase & Đổi thưởng (Bảng xếp hạng đóng góp)';
    $u->intro = '<p>Bấm vào đây để đóng góp testcase độc lạ, mở khóa các ca kiểm thử mới từ ngân hàng và thi đua cùng các bạn trong lớp!</p>';
    $u->introformat = FORMAT_HTML;
    $u->externalurl = $link_url;
    $u->display = 0; // Tự động mở
    $u->timemodified = time();
    $uid = $DB->insert_record('url', $u);

    // Gắn vào Section 1 (ngay trên bài Quiz)
    $cm = new stdClass();
    $cm->course = $course->id;
    $cm->module = $mod->id;
    $cm->instance = $uid;
    $cm->section = 7; // Section 1 của Course 3
    $cm->added = time();
    $cm->visible = 1;
    $cmid = add_course_module($cm);
    course_add_cm_to_section($course, $cmid, 1);
    
    echo "Đã gắn thành công Activity link Kho Testcase vào Section 1 của khóa học CS102!\n";
} else {
    echo "Activity link đã tồn tại!\n";
}
