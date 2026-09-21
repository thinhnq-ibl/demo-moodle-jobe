<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Hook tự động chèn mục điều hướng vào thanh menu khóa học.
 * Chạy tự động trong toàn bộ hệ thống khi plugin được cài đặt.
 *
 * @param navigation_node $parentnode Node cha (Course navigation).
 * @param stdClass $course Đối tượng khóa học hiện tại.
 * @param context_course $context Ngữ cảnh khóa học.
 */
function local_testcase_exchange_extend_navigation_course(navigation_node $parentnode, stdClass $course, context_course $context) {
    global $USER;

    // Chỉ hiển thị cho người dùng đã đăng nhập hợp lệ (bỏ qua tài khoản Guest)
    if (!isloggedin() || isguestuser()) {
        return;
    }

    // Kiểm tra quyền xem nội dung plugin
    if (!has_capability('local/testcase_exchange:view', $context)) {
        return;
    }

    $url = new moodle_url('/local/testcase_exchange/index.php', ['course' => $course->id]);
    $node = navigation_node::create(
        get_string('nav_testcase_bank', 'local_testcase_exchange'),
        $url,
        navigation_node::TYPE_CUSTOM,
        null,
        'testcase_exchange_node',
        new pix_icon('i/report', '')
    );

    $node->showinflatnavigation = true;
    $parentnode->add_node($node);
}
