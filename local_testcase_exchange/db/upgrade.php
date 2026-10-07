<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify it under the terms of the GNU GPL v3 or later.
// Moodle is distributed without any warranty. See <http://www.gnu.org/licenses/>.

/**
 * Plugin upgrade steps.
 *
 * @package local_testcase_exchange
 * @copyright 2026 Nguyen Quoc Thinh
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/** Upgrade hook for external testcase_store migrations. */
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
