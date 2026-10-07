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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * External database connection factory.
 *
 * @package    local_testcase_exchange
 * @copyright  2026 Nguyen Quoc Thinh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
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
