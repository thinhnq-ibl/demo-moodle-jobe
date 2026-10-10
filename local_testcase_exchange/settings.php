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
 * Administrative settings for the testcase exchange.
 *
 * @package    local_testcase_exchange
 * @copyright  2026 Nguyen Quoc Thinh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_testcase_exchange', get_string('pluginname', 'local_testcase_exchange'));

    $settings->add(new admin_setting_heading(
        'local_testcase_exchange/dbsettings',
        get_string('settings_db_heading', 'local_testcase_exchange'),
        get_string('settings_db_heading_desc', 'local_testcase_exchange')
    ));

    $settings->add(new admin_setting_configtext(
        'local_testcase_exchange/db_host',
        get_string('settings_db_host', 'local_testcase_exchange'),
        get_string('settings_db_host_desc', 'local_testcase_exchange'),
        'mariadb',
        PARAM_HOST
    ));

    $settings->add(new admin_setting_configtext(
        'local_testcase_exchange/db_port',
        get_string('settings_db_port', 'local_testcase_exchange'),
        get_string('settings_db_port_desc', 'local_testcase_exchange'),
        3306,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_testcase_exchange/db_user',
        get_string('settings_db_user', 'local_testcase_exchange'),
        get_string('settings_db_user_desc', 'local_testcase_exchange'),
        'moodle_app_writer',
        PARAM_RAW_TRIMMED
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_testcase_exchange/db_pass',
        get_string('settings_db_pass', 'local_testcase_exchange'),
        get_string('settings_db_pass_desc', 'local_testcase_exchange'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_testcase_exchange/db_name',
        get_string('settings_db_name', 'local_testcase_exchange'),
        get_string('settings_db_name_desc', 'local_testcase_exchange'),
        'testcase_store',
        PARAM_ALPHANUMEXT
    ));

    $settings->add(new admin_setting_heading(
        'local_testcase_exchange/jobesettings',
        get_string('settings_jobe_heading', 'local_testcase_exchange'),
        get_string('settings_jobe_heading_desc', 'local_testcase_exchange')
    ));

    $settings->add(new admin_setting_configtextarea(
        'local_testcase_exchange/jobe_servers',
        get_string('settings_jobe_servers', 'local_testcase_exchange'),
        get_string('settings_jobe_servers_desc', 'local_testcase_exchange'),
        '',
        PARAM_RAW_TRIMMED
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_testcase_exchange/jobe_api_key',
        get_string('settings_jobe_api_key', 'local_testcase_exchange'),
        get_string('settings_jobe_api_key_desc', 'local_testcase_exchange'),
        ''
    ));

    $ADMIN->add('localplugins', $settings);
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_testcase_exchange_dashboard',
        get_string('nav_testcase_bank', 'local_testcase_exchange'),
        new moodle_url('/local/testcase_exchange/index.php')
    ));
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_testcase_exchange_review',
        get_string('reviewcontributions', 'local_testcase_exchange'),
        new moodle_url('/local/testcase_exchange/review.php')
    ));
    $ADMIN->add('localplugins', new admin_externalpage(
        'local_testcase_exchange_health',
        get_string('healthcheck', 'local_testcase_exchange'),
        new moodle_url('/local/testcase_exchange/health.php')
    ));
}
