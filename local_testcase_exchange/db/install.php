<?php
defined('MOODLE_INTERNAL') || die();

/** Initialise the external schema without creating a DB-writing CodeRunner prototype. */
function xmldb_local_testcase_exchange_install() {
    try {
        $connection = \local_testcase_exchange\external_database::connect();
        \local_testcase_exchange\schema_manager::migrate($connection);
        $connection->close();
    } catch (Throwable $e) {
        mtrace('External testcase_store initialisation deferred: ' . $e->getMessage());
    }
    return true;
}
