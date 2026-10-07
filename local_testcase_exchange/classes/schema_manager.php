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
 * External database schema manager.
 *
 * @package    local_testcase_exchange
 * @copyright  2026 Nguyen Quoc Thinh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_testcase_exchange;

/**
 * Creates and migrates the external testcase repository schema.
 */
final class schema_manager {
    /** Current external schema version. */
    private const VERSION = '2026100701';

    /**
     * Apply idempotent external schema and legacy-data migrations.
     *
     * @param \mysqli $connection External connection.
     */
    public static function migrate(\mysqli $connection): void {
        global $DB;

        $statements = [
            "CREATE TABLE IF NOT EXISTS schema_migrations (
                version VARCHAR(32) NOT NULL PRIMARY KEY,
                applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                checksum VARCHAR(64) NOT NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS quiz_settings (
                quiz_id BIGINT NOT NULL PRIMARY KEY,
                is_enabled TINYINT(1) NOT NULL DEFAULT 0,
                updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "ALTER TABLE quiz_settings ADD COLUMN IF NOT EXISTS enabled TINYINT(1) NOT NULL DEFAULT 0",
            "ALTER TABLE quiz_settings ADD COLUMN IF NOT EXISTS review_mode VARCHAR(16) NOT NULL DEFAULT 'teacher'",
            "ALTER TABLE quiz_settings ADD COLUMN IF NOT EXISTS reward_policy VARCHAR(24) NOT NULL DEFAULT 'one_for_one'",
            "ALTER TABLE quiz_settings ADD COLUMN IF NOT EXISTS show_oracle_output TINYINT(1) NOT NULL DEFAULT 1",
            "ALTER TABLE quiz_settings ADD COLUMN IF NOT EXISTS leaderboard_enabled TINYINT(1) NOT NULL DEFAULT 1",
            "ALTER TABLE quiz_settings ADD COLUMN IF NOT EXISTS max_runs_per_minute INT NOT NULL DEFAULT 10",
            "ALTER TABLE quiz_settings ADD COLUMN IF NOT EXISTS max_input_bytes INT NOT NULL DEFAULT 8192",
            "ALTER TABLE quiz_settings ADD COLUMN IF NOT EXISTS updated_by BIGINT NULL",
            "CREATE TABLE IF NOT EXISTS question_policies (
                question_id BIGINT NOT NULL PRIMARY KEY,
                quiz_id BIGINT NOT NULL,
                input_mode VARCHAR(16) NOT NULL DEFAULT 'testcode',
                testcode_template LONGTEXT NULL,
                normalization_mode VARCHAR(16) NOT NULL DEFAULT 'trim',
                categories TEXT NULL,
                updated_by BIGINT NULL,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_policy_quiz (quiz_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS testcase_runs (
                id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                legacy_source_id BIGINT NULL,
                course_id BIGINT NOT NULL,
                quiz_id BIGINT NOT NULL,
                question_id BIGINT NOT NULL,
                user_id BIGINT NOT NULL,
                quiz_attempt_id BIGINT NULL,
                question_attempt_id BIGINT NULL,
                input_raw LONGTEXT NOT NULL,
                input_normalized LONGTEXT NOT NULL,
                input_fingerprint CHAR(64) NOT NULL,
                predicted_output LONGTEXT NULL,
                student_run_output LONGTEXT NULL,
                oracle_output LONGTEXT NULL,
                student_outcome VARCHAR(32) NOT NULL,
                oracle_outcome VARCHAR(32) NOT NULL,
                purpose TEXT NULL,
                category VARCHAR(64) NULL,
                reflection TEXT NULL,
                jobe_server VARCHAR(255) NULL,
                student_source_hash CHAR(64) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_run_legacy (legacy_source_id),
                KEY idx_runs_owner (course_id, quiz_id, question_id, user_id, created_at),
                KEY idx_runs_fingerprint (course_id, quiz_id, question_id, input_fingerprint)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS testcase_contributions (
                id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                legacy_source_id BIGINT NULL,
                run_id BIGINT NOT NULL,
                course_id BIGINT NOT NULL,
                quiz_id BIGINT NOT NULL,
                question_id BIGINT NOT NULL,
                user_id BIGINT NOT NULL,
                input_normalized LONGTEXT NOT NULL,
                input_fingerprint CHAR(64) NOT NULL,
                oracle_output LONGTEXT NULL,
                purpose TEXT NULL,
                category VARCHAR(64) NULL,
                status VARCHAR(32) NOT NULL DEFAULT 'submitted',
                duplicate_of BIGINT NULL,
                submitted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_contribution_legacy (legacy_source_id),
                UNIQUE KEY uq_contribution_run (run_id),
                KEY idx_contribution_scope (course_id, quiz_id, question_id, status),
                KEY idx_contribution_owner (user_id, submitted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS testcase_canonical_keys (
                id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                course_id BIGINT NOT NULL,
                quiz_id BIGINT NOT NULL,
                question_id BIGINT NOT NULL,
                input_fingerprint CHAR(64) NOT NULL,
                canonical_contribution_id BIGINT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_canonical (course_id, quiz_id, question_id, input_fingerprint)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "ALTER TABLE testcase_contributions ADD UNIQUE INDEX IF NOT EXISTS uq_contribution_run (run_id)",
            "CREATE TABLE IF NOT EXISTS contribution_reviews (
                id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                contribution_id BIGINT NOT NULL,
                reviewer_user_id BIGINT NOT NULL,
                from_status VARCHAR(32) NOT NULL,
                to_status VARCHAR(32) NOT NULL,
                review_note TEXT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_review_contribution (contribution_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
            "CREATE TABLE IF NOT EXISTS testcase_rewards (
                id BIGINT NOT NULL AUTO_INCREMENT PRIMARY KEY,
                legacy_source_id BIGINT NULL,
                course_id BIGINT NOT NULL,
                quiz_id BIGINT NOT NULL,
                question_id BIGINT NOT NULL,
                receiver_user_id BIGINT NOT NULL,
                contribution_id BIGINT NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_reward (receiver_user_id, contribution_id),
                UNIQUE KEY uq_reward_legacy (legacy_source_id),
                KEY idx_reward_scope (course_id, quiz_id, question_id, receiver_user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        ];

        foreach ($statements as $sql) {
            $connection->query($sql);
        }
        $connection->query("UPDATE quiz_settings SET enabled = is_enabled WHERE enabled = 0 AND is_enabled = 1");

        $version = $connection->real_escape_string(self::VERSION);
        $exists = $connection->query("SELECT version FROM schema_migrations WHERE version = '{$version}'");
        if ($exists->num_rows === 0) {
            self::migrate_legacy_data($connection, $DB);
            $checksum = hash('sha256', implode("\n", $statements));
            $stmt = $connection->prepare('INSERT INTO schema_migrations (version, checksum) VALUES (?, ?)');
            $stmt->bind_param('ss', $version, $checksum);
            $stmt->execute();
            $stmt->close();
        }
    }

    /**
     * Migrate legacy testcase rows.
     *
     * @param \mysqli $connection External connection.
     * @param \moodle_database $moodledb Moodle database.
     */
    private static function migrate_legacy_data(\mysqli $connection, $moodledb): void {
        if (!self::table_exists($connection, 'student_testcases')) {
            return;
        }
        $columns = self::columns($connection, 'student_testcases');
        $outputcolumn = isset($columns['test_output']) ? 'test_output' :
            (isset($columns['expected_output']) ? 'expected_output' : 'NULL');
        $hasquiz = isset($columns['quiz_id']);
        $sql = 'SELECT id, course_id, ' . ($hasquiz ? 'quiz_id' : '1 AS quiz_id') .
            ", question_id, student_id, test_input, {$outputcolumn} AS legacy_output, jobe_server, created_at " .
            'FROM student_testcases ORDER BY id';
        $result = $connection->query($sql);
        while ($row = $result->fetch_assoc()) {
            $course = $moodledb->get_record('course', ['shortname' => $row['course_id']]);
            $user = $moodledb->get_record('user', ['username' => $row['student_id']]);
            if (!$course) {
                continue;
            }
            $userid = $user ? (int) $user->id : 0;
            $normalized = normalizer::normalize((string) $row['test_input'], 'trim');
            $fingerprint = normalizer::fingerprint($normalized);
            $connection->begin_transaction();
            try {
                $stmt = $connection->prepare(
                    'INSERT IGNORE INTO testcase_runs
                    (legacy_source_id, course_id, quiz_id, question_id, user_id, input_raw, input_normalized,
                     input_fingerprint, predicted_output, oracle_output, student_outcome, oracle_outcome,
                     jobe_server, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $outcome = 'legacy';
                $legacyid = (int) $row['id'];
                $courseid = (int) $course->id;
                $quizid = (int) $row['quiz_id'];
                $questionid = (int) $row['question_id'];
                $input = (string) $row['test_input'];
                $output = (string) ($row['legacy_output'] ?? '');
                $server = (string) ($row['jobe_server'] ?? 'legacy');
                $created = (string) $row['created_at'];
                $stmt->bind_param(
                    'iiiiisssssssss',
                    $legacyid,
                    $courseid,
                    $quizid,
                    $questionid,
                    $userid,
                    $input,
                    $normalized,
                    $fingerprint,
                    $output,
                    $output,
                    $outcome,
                    $outcome,
                    $server,
                    $created
                );
                $stmt->execute();
                $stmt->close();

                $runstmt = $connection->prepare('SELECT id FROM testcase_runs WHERE legacy_source_id = ?');
                $runstmt->bind_param('i', $legacyid);
                $runstmt->execute();
                $runid = (int) $runstmt->get_result()->fetch_assoc()['id'];
                $runstmt->close();

                $status = 'approved';
                $stmt = $connection->prepare(
                    'INSERT IGNORE INTO testcase_contributions
                    (legacy_source_id, run_id, course_id, quiz_id, question_id, user_id, input_normalized,
                     input_fingerprint, oracle_output, status, submitted_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->bind_param(
                    'iiiiiisssss',
                    $legacyid,
                    $runid,
                    $courseid,
                    $quizid,
                    $questionid,
                    $userid,
                    $normalized,
                    $fingerprint,
                    $output,
                    $status,
                    $created
                );
                $stmt->execute();
                $stmt->close();

                $contribstmt = $connection->prepare(
                    'SELECT id FROM testcase_contributions WHERE legacy_source_id = ?'
                );
                $contribstmt->bind_param('i', $legacyid);
                $contribstmt->execute();
                $contributionid = (int) $contribstmt->get_result()->fetch_assoc()['id'];
                $contribstmt->close();

                $claim = $connection->prepare(
                    'INSERT IGNORE INTO testcase_canonical_keys
                    (course_id, quiz_id, question_id, input_fingerprint, canonical_contribution_id)
                    VALUES (?, ?, ?, ?, ?)'
                );
                $claim->bind_param('iiisi', $courseid, $quizid, $questionid, $fingerprint, $contributionid);
                $claim->execute();
                if ($claim->affected_rows === 0) {
                    $canonical = self::canonical_id($connection, $courseid, $quizid, $questionid, $fingerprint);
                    $duplicate = 'duplicate';
                    $update = $connection->prepare(
                        'UPDATE testcase_contributions SET status = ?, duplicate_of = ? WHERE id = ?'
                    );
                    $update->bind_param('sii', $duplicate, $canonical, $contributionid);
                    $update->execute();
                    $update->close();
                }
                $claim->close();
                $connection->commit();
            } catch (\Throwable $e) {
                $connection->rollback();
                throw $e;
            }
        }
        self::migrate_legacy_rewards($connection, $moodledb);
    }

    /**
     * Migrate legacy reward rows.
     *
     * @param \mysqli $connection External connection.
     * @param \moodle_database $moodledb Moodle database.
     */
    private static function migrate_legacy_rewards(\mysqli $connection, $moodledb): void {
        if (!self::table_exists($connection, 'testcase_exchanges')) {
            return;
        }
        $result = $connection->query('SELECT * FROM testcase_exchanges ORDER BY id');
        while ($row = $result->fetch_assoc()) {
            $user = $moodledb->get_record('user', ['username' => $row['receiver_student']]);
            $contribution = $connection->query(
                'SELECT id, course_id, quiz_id, question_id FROM testcase_contributions WHERE legacy_source_id = ' .
                (int) $row['testcase_id']
            )->fetch_assoc();
            if (!$user || !$contribution) {
                continue;
            }
            $stmt = $connection->prepare(
                'INSERT IGNORE INTO testcase_rewards
                (legacy_source_id, course_id, quiz_id, question_id, receiver_user_id, contribution_id, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $legacyid = (int) $row['id'];
            $courseid = (int) $contribution['course_id'];
            $quizid = (int) $contribution['quiz_id'];
            $questionid = (int) $contribution['question_id'];
            $userid = (int) $user->id;
            $contributionid = (int) $contribution['id'];
            $created = (string) $row['received_at'];
            $stmt->bind_param('iiiiiis', $legacyid, $courseid, $quizid, $questionid, $userid, $contributionid, $created);
            $stmt->execute();
            $stmt->close();
        }
    }

    /**
     * Find the canonical contribution for a fingerprint.
     *
     * @param \mysqli $connection External connection.
     * @param int $courseid Course ID.
     * @param int $quizid Quiz ID.
     * @param int $questionid Question ID.
     * @param string $fingerprint Input fingerprint.
     * @return int Contribution ID.
     */
    private static function canonical_id(
        \mysqli $connection,
        int $courseid,
        int $quizid,
        int $questionid,
        string $fingerprint
    ): int {
        $stmt = $connection->prepare(
            'SELECT canonical_contribution_id FROM testcase_canonical_keys
             WHERE course_id = ? AND quiz_id = ? AND question_id = ? AND input_fingerprint = ?'
        );
        $stmt->bind_param('iiis', $courseid, $quizid, $questionid, $fingerprint);
        $stmt->execute();
        $id = (int) $stmt->get_result()->fetch_assoc()['canonical_contribution_id'];
        $stmt->close();
        return $id;
    }

    /**
     * Check whether a legacy table exists.
     *
     * @param \mysqli $connection External connection.
     * @param string $table Table name controlled by this class.
     * @return bool
     */
    private static function table_exists(\mysqli $connection, string $table): bool {
        $escaped = $connection->real_escape_string($table);
        return $connection->query("SHOW TABLES LIKE '{$escaped}'")->num_rows > 0;
    }

    /**
     * Return metadata for columns in a controlled legacy table.
     *
     * @param \mysqli $connection External connection.
     * @param string $table Table name controlled by this class.
     * @return array Column metadata keyed by name.
     */
    private static function columns(\mysqli $connection, string $table): array {
        $columns = [];
        $result = $connection->query("SHOW COLUMNS FROM `{$table}`");
        while ($row = $result->fetch_assoc()) {
            $columns[$row['Field']] = $row;
        }
        return $columns;
    }
}
