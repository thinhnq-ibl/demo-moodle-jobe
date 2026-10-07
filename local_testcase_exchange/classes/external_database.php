<?php
namespace local_testcase_exchange;

defined('MOODLE_INTERNAL') || die();

/** External testcase_store connection factory. */
final class external_database {
    /** @return \mysqli */
    public static function connect(): \mysqli {
        $port = (int) get_config('local_testcase_exchange', 'db_port');
        $password = get_config('local_testcase_exchange', 'db_pass');
        $config = [
            'host' => get_config('local_testcase_exchange', 'db_host') ?: 'mariadb',
            'port' => $port > 0 && $port <= 65535 ? $port : 3306,
            'user' => get_config('local_testcase_exchange', 'db_user') ?: 'moodle_app_writer',
            'pass' => $password === false ? '' : (string) $password,
            'name' => get_config('local_testcase_exchange', 'db_name') ?: 'testcase_store',
        ];

        $connection = new \mysqli(
            $config['host'],
            $config['user'],
            $config['pass'],
            $config['name'],
            $config['port']
        );
        $connection->set_charset('utf8mb4');
        return $connection;
    }
}
