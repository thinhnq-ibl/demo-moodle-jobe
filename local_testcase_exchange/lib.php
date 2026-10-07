<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Return the external testcase database configuration.
 *
 * @return array{host:string, port:int, user:string, pass:string, name:string}
 */
function local_testcase_exchange_get_db_config(): array {
    $port = (int) get_config('local_testcase_exchange', 'db_port');
    $password = get_config('local_testcase_exchange', 'db_pass');

    return [
        'host' => get_config('local_testcase_exchange', 'db_host') ?: 'mariadb',
        'port' => $port > 0 && $port <= 65535 ? $port : 3306,
        'user' => get_config('local_testcase_exchange', 'db_user') ?: 'moodle_app_writer',
        'pass' => $password === false ? '' : (string) $password,
        'name' => get_config('local_testcase_exchange', 'db_name') ?: 'testcase_store',
    ];
}

/**
 * Return configured Jobe servers with a ready-to-use runs endpoint.
 *
 * The setting accepts the same semicolon-separated host format as CodeRunner,
 * as well as one server per line. A full REST runs URL is also accepted.
 *
 * @return array<int, array{label:string, runsurl:string}>
 */
function local_testcase_exchange_get_jobe_servers(): array {
    $configured = trim((string) get_config('local_testcase_exchange', 'jobe_servers'));
    if ($configured === '') {
        $configured = trim((string) get_config('qtype_coderunner', 'jobe_host'));
    }
    if ($configured === '') {
        $configured = 'jobe1;jobe2';
    }

    $servers = [];
    foreach (preg_split('/[;\r\n]+/', $configured) as $server) {
        $server = trim($server);
        if ($server === '') {
            continue;
        }

        if (!preg_match('~^https?://~i', $server)) {
            $server = 'http://' . $server;
        }

        $baseurl = rtrim($server, '/');
        if (preg_match('~/jobe/index\.php/restapi/runs$~i', $baseurl)) {
            $runsurl = $baseurl;
        } elseif (preg_match('~/jobe/index\.php/restapi$~i', $baseurl)) {
            $runsurl = $baseurl . '/runs';
        } else {
            $runsurl = $baseurl . '/jobe/index.php/restapi/runs';
        }

        $servers[] = [
            'label' => parse_url($baseurl, PHP_URL_HOST) ?: $baseurl,
            'runsurl' => $runsurl,
        ];
    }

    return $servers;
}

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
