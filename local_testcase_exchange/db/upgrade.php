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
 * @package    local_testcase_exchange
 * @copyright  2026 Nguyen Quoc Thinh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade hook for local_testcase_exchange migrations.
 *
 * @param int $oldversion Previously installed plugin version.
 * @return bool
 */
function xmldb_local_testcase_exchange_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026110101) {
        $installxml = __DIR__ . '/install.xml';
        if (file_exists($installxml)) {
            $dbman->install_from_xmldb_file($installxml);
        }
        upgrade_plugin_savepoint(true, 2026110101, 'local', 'testcase_exchange');
    }

    if ($oldversion < 2026110102) {
        $table = new xmldb_table('local_tce_rewards');
        $index = new xmldb_index('idx_reward_unique_peer', XMLDB_INDEX_UNIQUE, ['receiver_user_id', 'question_id', 'contribution_id']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        upgrade_plugin_savepoint(true, 2026110102, 'local', 'testcase_exchange');
    }

    if ($oldversion < 2026110103) {
        $table = new xmldb_table('local_tce_rewards');
        $indexseed = new xmldb_index('idx_reward_unique_seed', XMLDB_INDEX_UNIQUE, ['receiver_user_id', 'question_id', 'seed_id']);
        if (!$dbman->index_exists($table, $indexseed)) {
            $dbman->add_index($table, $indexseed);
        }
        upgrade_plugin_savepoint(true, 2026110103, 'local', 'testcase_exchange');
    }

    if ($oldversion < 2026110201) {
        $table = new xmldb_table('local_tce_policies');
        $oldkey = new xmldb_key('uq_question', XMLDB_KEY_UNIQUE, ['question_id']);
        if ($dbman->find_key_name($table, $oldkey)) {
            $dbman->drop_key($table, $oldkey);
        }
        $newkey = new xmldb_key('uq_quiz_question', XMLDB_KEY_UNIQUE, ['quiz_id', 'question_id']);
        if (!$dbman->find_key_name($table, $newkey)) {
            $dbman->add_key($table, $newkey);
        }
        upgrade_plugin_savepoint(true, 2026110201, 'local', 'testcase_exchange');
    }

    return true;
}
