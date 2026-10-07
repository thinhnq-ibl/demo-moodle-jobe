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
 * Plugin callbacks and configuration helpers.
 *
 * @package local_testcase_exchange
 * @copyright 2026 Nguyen Quoc Thinh
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
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
        } else if (preg_match('~/jobe/index\.php/restapi$~i', $baseurl)) {
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
 * Add the testcase exchange link to course navigation.
 *
 * @param navigation_node $parentnode Course navigation node.
 * @param stdClass $course Current course.
 * @param context_course $context Course context.
 */
function local_testcase_exchange_extend_navigation_course(navigation_node $parentnode, stdClass $course, context_course $context) {
    // Hide the link from guests and logged-out users.
    if (!isloggedin() || isguestuser()) {
        return;
    }

    // Respect the course-level view capability.
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
