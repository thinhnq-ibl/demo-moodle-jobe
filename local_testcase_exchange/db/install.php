<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify it under the terms of the GNU GPL v3 or later.
// Moodle is distributed without any warranty. See <http://www.gnu.org/licenses/>.

/**
 * Plugin installation hook.
 *
 * @package local_testcase_exchange
 * @copyright 2026 Nguyen Quoc Thinh
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

/** Initialise the external schema without creating a DB-writing CodeRunner prototype. */
function xmldb_local_testcase_exchange_install() {
    try {
        $connection = \local_testcase_exchange\external_database::connect();
        \local_testcase_exchange\schema_manager::migrate($connection);
        $connection->close();
    } catch (Throwable $e) {
        mtrace('External testcase_store initialisation deferred; configure the plugin and use its health check.');
        debugging($e->getMessage(), DEBUG_DEVELOPER);
    }
    return true;
}
