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
 * Plugin upgrade steps.
 *
 * @package local_testcase_exchange
 * @copyright 2026 Nguyen Quoc Thinh
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
/**
 * Upgrade hook for external testcase repository migrations.
 *
 * @param int $oldversion Previously installed plugin version.
 * @return bool
 */
function xmldb_local_testcase_exchange_upgrade($oldversion) {
    if ($oldversion < 2026100702) {
        try {
            $connection = \local_testcase_exchange\external_database::connect();
            \local_testcase_exchange\schema_manager::migrate($connection);
            $connection->close();
        } catch (Throwable $e) {
            mtrace('External testcase_store migration deferred; use the plugin health check after upgrade.');
            debugging($e->getMessage(), DEBUG_DEVELOPER);
        }
        upgrade_plugin_savepoint(true, 2026100702, 'local', 'testcase_exchange');
    }
    if ($oldversion < 2026100704) {
        upgrade_plugin_savepoint(true, 2026100704, 'local', 'testcase_exchange');
    }
    return true;
}
