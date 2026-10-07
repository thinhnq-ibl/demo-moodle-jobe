<?php
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
        "jobe1\njobe2",
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
        'local_testcase_exchange_health',
        get_string('healthcheck', 'local_testcase_exchange'),
        new moodle_url('/local/testcase_exchange/health.php')
    ));
}
