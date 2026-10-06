<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'CodeRunner Testcase Exchange';
$string['testcase_exchange:view'] = 'View testcase exchange and leaderboard';
$string['testcase_exchange:manage'] = 'Manage the testcase repository';
$string['heading_dashboard'] = 'Testcase Contribution Dashboard and Leaderboard';
$string['settings_db_heading'] = 'Testcase database';
$string['settings_db_heading_desc'] = 'Connection used by the plugin to read and write testcase data.';
$string['settings_db_host'] = 'MariaDB Host';
$string['settings_db_host_desc'] = 'Database hostname, without a protocol or port.';
$string['settings_db_port'] = 'Database port';
$string['settings_db_port_desc'] = 'MariaDB/MySQL TCP port.';
$string['settings_db_user'] = 'DB User';
$string['settings_db_user_desc'] = 'This account requires SELECT and INSERT permissions on the testcase tables.';
$string['settings_db_pass'] = 'DB Password';
$string['settings_db_pass_desc'] = 'Password for the testcase database account.';
$string['settings_db_name'] = 'DB Name';
$string['settings_db_name_desc'] = 'Name of the separate testcase database.';
$string['settings_jobe_heading'] = 'Jobe runner';
$string['settings_jobe_heading_desc'] = 'Jobe servers used to validate submitted testcases against the teacher solution.';
$string['settings_jobe_servers'] = 'Jobe server URLs';
$string['settings_jobe_servers_desc'] = 'Enter one server per line, or separate servers with semicolons. Examples: jobe1, jobe2:80, or https://jobe.example.edu. The REST path is added automatically.';
$string['settings_jobe_api_key'] = 'Jobe API key';
$string['settings_jobe_api_key_desc'] = 'Optional API key sent in the X-API-KEY header. Leave blank when Jobe does not require one.';
$string['nav_testcase_bank'] = 'Testcase Bank & Exchange';
$string['my_testcases'] = 'My Testcases';
$string['received_testcases'] = 'Received Testcases';
