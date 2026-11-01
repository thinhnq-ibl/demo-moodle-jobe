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

namespace local_testcase_exchange;

defined('MOODLE_INTERNAL') || die();

/**
 * Moodle database access gateway for local_testcase_exchange.
 *
 * Provides typed, parameterized access to internal {local_tce_*} tables
 * using Moodle Core DML API ($DB).
 *
 * @package    local_testcase_exchange
 * @copyright  2026 Nguyen Quoc Thinh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class db_gateway {

    /** @var \moodle_database Core database instance. */
    private \moodle_database $db;

    /**
     * Constructor.
     *
     * @param ?\moodle_database $db Optional database instance.
     */
    public function __construct(?\moodle_database $db = null) {
        global $DB;
        $this->db = $db ?: $DB;
    }

    /**
     * Get underlying Moodle database instance.
     *
     * @return \moodle_database
     */
    public function get_db(): \moodle_database {
        return $this->db;
    }

    /**
     * Fetch all matching rows as an array of associative arrays.
     *
     * @param string $sql SQL query with {table} tokens.
     * @param array $params Query parameters.
     * @return array<array<string, mixed>>
     */
    public function fetch_all(string $sql, array $params = []): array {
        $records = $this->db->get_records_sql($sql, $params);
        $result = [];
        foreach ($records as $rec) {
            $result[] = (array) $rec;
        }
        return $result;
    }

    /**
     * Fetch a single row as an associative array.
     *
     * @param string $sql SQL query with {table} tokens.
     * @param array $params Query parameters.
     * @return ?array<string, mixed>
     */
    public function fetch_row(string $sql, array $params = []): ?array {
        // Moodle get_record_sql() automatically appends 'LIMIT 0, 1' at the very end of the string,
        // which creates invalid SQL syntax if 'FOR UPDATE' is at the end.
        // For locking queries, use get_records_sql() and take the first record.
        if (stripos($sql, 'FOR UPDATE') !== false) {
            $records = $this->db->get_records_sql($sql, $params);
            if (empty($records)) {
                return null;
            }
            $first = reset($records);
            return (array) $first;
        }

        $rec = $this->db->get_record_sql($sql, $params, IGNORE_MULTIPLE);
        return $rec ? (array) $rec : null;
    }

    /**
     * Execute an arbitrary SQL statement with parameters.
     *
     * @param string $sql SQL query.
     * @param array $params Query parameters.
     * @return bool
     */
    public function execute(string $sql, array $params = []): bool {
        return $this->db->execute($sql, $params);
    }

    /**
     * Insert a record into a table and return the new ID.
     *
     * @param string $table Table name without prefix (e.g. 'local_tce_runs').
     * @param array $data Column-value map.
     * @return int
     */
    public function insert(string $table, array $data): int {
        return (int) $this->db->insert_record($table, (object) $data);
    }

    /**
     * Update an existing record in a table.
     *
     * @param string $table Table name without prefix.
     * @param array $data Column-value map containing 'id'.
     * @return bool
     */
    public function update(string $table, array $data): bool {
        return $this->db->update_record($table, (object) $data);
    }

    /**
     * Delete records matching conditions.
     *
     * @param string $table Table name without prefix.
     * @param array $conditions Column-value matching conditions.
     * @return bool
     */
    public function delete(string $table, array $conditions): bool {
        return $this->db->delete_records($table, $conditions);
    }

    /**
     * Start a Moodle delegated transaction.
     *
     * @return \moodle_transaction
     */
    public function start_transaction(): \moodle_transaction {
        return $this->db->start_delegated_transaction();
    }
}
