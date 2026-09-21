<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_testcase_exchange', get_string('pluginname', 'local_testcase_exchange'));

    $settings->add(new admin_setting_configtext(
        'local_testcase_exchange/db_host',
        get_string('settings_db_host', 'local_testcase_exchange'),
        get_string('settings_db_host_desc', 'local_testcase_exchange'),
        'mariadb',
        PARAM_HOST
    ));

    $settings->add(new admin_setting_configtext(
        'local_testcase_exchange/db_user',
        get_string('settings_db_user', 'local_testcase_exchange'),
        '',
        'moodle_reader',
        PARAM_RAW
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_testcase_exchange/db_pass',
        get_string('settings_db_pass', 'local_testcase_exchange'),
        '',
        'ReaderSecret123!'
    ));

    $settings->add(new admin_setting_configtext(
        'local_testcase_exchange/db_name',
        get_string('settings_db_name', 'local_testcase_exchange'),
        '',
        'testcase_store',
        PARAM_ALPHANUMEXT
    ));

    $ADMIN->add('localplugins', $settings);
}
