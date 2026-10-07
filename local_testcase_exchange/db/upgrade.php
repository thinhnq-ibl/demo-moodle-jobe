<?php
defined('MOODLE_INTERNAL') || die();

/** Upgrade hook for external testcase_store migrations. */
function xmldb_local_testcase_exchange_upgrade($oldversion) {
    if ($oldversion < 2026100702) {
        try {
            $connection = \local_testcase_exchange\external_database::connect();
            \local_testcase_exchange\schema_manager::migrate($connection);
            $connection->close();
        } catch (Throwable $e) {
            mtrace('External testcase_store migration deferred: ' . $e->getMessage());
        }
        upgrade_plugin_savepoint(true, 2026100702, 'local', 'testcase_exchange');
    }
    return true;
}
