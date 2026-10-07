<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

/**
 * Privacy provider for the external testcase repository.
 *
 * @package    local_testcase_exchange
 * @copyright  2026 Nguyen Quoc Thinh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_testcase_exchange\privacy;

use context;
use context_course;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_testcase_exchange\external_database;

/**
 * Describes, exports and removes personal data held in testcase_store.
 */
class provider implements
        \core_privacy\local\metadata\provider,
        \core_privacy\local\request\plugin\provider,
        \core_privacy\local\request\core_userlist_provider {

    /**
     * Describe personal data stored in the external database and sent to Jobe.
     *
     * @param collection $collection Metadata collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_external_location_link('testcase_store', [
            'user_id' => 'privacy:metadata:testcase_store:user_id',
            'input' => 'privacy:metadata:testcase_store:input',
            'source_hash' => 'privacy:metadata:testcase_store:source_hash',
            'outputs' => 'privacy:metadata:testcase_store:outputs',
            'reflection' => 'privacy:metadata:testcase_store:reflection',
        ], 'privacy:metadata:testcase_store');
        $collection->add_external_location_link('jobe', [
            'source' => 'privacy:metadata:jobe:source',
            'input' => 'privacy:metadata:jobe:input',
        ], 'privacy:metadata:jobe');
        return $collection;
    }

    /**
     * Find course contexts containing data for a user.
     *
     * @param int $userid User ID.
     * @return contextlist
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $courseids = self::course_ids_for_users([$userid]);
        if ($courseids) {
            global $DB;
            [$insql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'course');
            $params['contextlevel'] = CONTEXT_COURSE;
            $contextlist->add_from_sql(
                "SELECT id FROM {context} WHERE contextlevel = :contextlevel AND instanceid {$insql}",
                $params
            );
        }
        return $contextlist;
    }

    /**
     * Export a user's testcase data grouped by course.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        $userid = $contextlist->get_user()->id;
        $connection = self::connect();
        if (!$connection) {
            return;
        }
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_course) {
                continue;
            }
            $courseid = (int) $context->instanceid;
            $data = (object) [
                'private_runs' => self::select_rows(
                    $connection,
                    'SELECT * FROM testcase_runs WHERE course_id = ? AND user_id = ? ORDER BY id',
                    $courseid,
                    $userid
                ),
                'contributions' => self::select_rows(
                    $connection,
                    'SELECT * FROM testcase_contributions WHERE course_id = ? AND user_id = ? ORDER BY id',
                    $courseid,
                    $userid
                ),
                'received_rewards' => self::select_rows(
                    $connection,
                    'SELECT * FROM testcase_rewards WHERE course_id = ? AND receiver_user_id = ? ORDER BY id',
                    $courseid,
                    $userid
                ),
                'reviews' => self::select_rows(
                    $connection,
                    'SELECT r.* FROM contribution_reviews r
                       JOIN testcase_contributions c ON c.id = r.contribution_id
                      WHERE c.course_id = ? AND r.reviewer_user_id = ? ORDER BY r.id',
                    $courseid,
                    $userid
                ),
            ];
            writer::with_context($context)->export_data(
                [get_string('privacy:path', 'local_testcase_exchange')],
                $data
            );
        }
        $connection->close();
    }

    /**
     * Delete all plugin data in a course context.
     *
     * @param context $context Context to erase.
     */
    public static function delete_data_for_all_users_in_context(context $context): void {
        if (!$context instanceof context_course) {
            return;
        }
        self::delete_course_data((int) $context->instanceid);
    }

    /**
     * Delete one user's data in approved contexts.
     *
     * @param approved_contextlist $contextlist Approved contexts.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        $courseids = [];
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof context_course) {
                $courseids[] = (int) $context->instanceid;
            }
        }
        self::delete_user_data((int) $contextlist->get_user()->id, $courseids);
    }

    /**
     * Add users with stored data to a course user list.
     *
     * @param userlist $userlist User list.
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof context_course) {
            return;
        }
        $connection = self::connect();
        if (!$connection) {
            return;
        }
        $courseid = (int) $context->instanceid;
        $stmt = $connection->prepare(
            'SELECT user_id FROM testcase_runs WHERE course_id = ?
             UNION SELECT user_id FROM testcase_contributions WHERE course_id = ?
             UNION SELECT receiver_user_id FROM testcase_rewards WHERE course_id = ?'
        );
        $stmt->bind_param('iii', $courseid, $courseid, $courseid);
        $stmt->execute();
        $userids = [];
        foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
            if ((int) $row['user_id'] > 0) {
                $userids[] = (int) $row['user_id'];
            }
        }
        $stmt->close();
        $connection->close();
        $userlist->add_users($userids);
    }

    /**
     * Delete data for users approved in a course context.
     *
     * @param approved_userlist $userlist Approved users.
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof context_course) {
            return;
        }
        foreach ($userlist->get_userids() as $userid) {
            self::delete_user_data((int) $userid, [(int) $context->instanceid]);
        }
    }

    /** @return \mysqli|null External connection, or null when unavailable. */
    private static function connect(): ?\mysqli {
        try {
            return external_database::connect();
        } catch (\Throwable $exception) {
            debugging('Testcase privacy operation could not reach the external store.', DEBUG_DEVELOPER);
            return null;
        }
    }

    /** @return int[] Course IDs associated with the supplied users. */
    private static function course_ids_for_users(array $userids): array {
        $connection = self::connect();
        if (!$connection || empty($userids)) {
            return [];
        }
        $ids = array_map('intval', $userids);
        $in = implode(',', $ids);
        $result = $connection->query(
            "SELECT course_id FROM testcase_runs WHERE user_id IN ({$in})
             UNION SELECT course_id FROM testcase_contributions WHERE user_id IN ({$in})
             UNION SELECT course_id FROM testcase_rewards WHERE receiver_user_id IN ({$in})
             UNION SELECT c.course_id FROM contribution_reviews r
                    JOIN testcase_contributions c ON c.id = r.contribution_id
                   WHERE r.reviewer_user_id IN ({$in})"
        );
        $courseids = array_map('intval', array_column($result->fetch_all(MYSQLI_ASSOC), 'course_id'));
        $connection->close();
        return array_values(array_unique($courseids));
    }

    /** @return array Rows from a course/user scoped query. */
    private static function select_rows(\mysqli $connection, string $sql, int $courseid, int $userid): array {
        $stmt = $connection->prepare($sql);
        $stmt->bind_param('ii', $courseid, $userid);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return array_map(static function(array $row): array {
            foreach ($row as $key => $value) {
                if (str_ends_with($key, '_at') && $value !== null) {
                    $row[$key] = transform::datetime(strtotime($value));
                }
            }
            return $row;
        }, $rows);
    }

    /** Delete all external records belonging to a course. */
    private static function delete_course_data(int $courseid): void {
        $connection = self::connect();
        if (!$connection) {
            return;
        }
        $connection->begin_transaction();
        try {
            foreach ([
                'DELETE r FROM contribution_reviews r JOIN testcase_contributions c ON c.id = r.contribution_id WHERE c.course_id = ?',
                'DELETE FROM testcase_rewards WHERE course_id = ?',
                'DELETE FROM testcase_canonical_keys WHERE course_id = ?',
                'DELETE FROM testcase_contributions WHERE course_id = ?',
                'DELETE FROM testcase_runs WHERE course_id = ?',
            ] as $sql) {
                $stmt = $connection->prepare($sql);
                $stmt->bind_param('i', $courseid);
                $stmt->execute();
                $stmt->close();
            }
            self::delete_course_policies($connection, $courseid);
            $connection->commit();
        } catch (\Throwable $exception) {
            $connection->rollback();
            throw $exception;
        } finally {
            $connection->close();
        }
    }

    /** Delete or anonymise one user's data in selected courses. */
    private static function delete_user_data(int $userid, array $courseids): void {
        $connection = self::connect();
        if (!$connection) {
            return;
        }
        foreach ($courseids as $courseid) {
            $courseid = (int) $courseid;
            $connection->begin_transaction();
            try {
                $owned = [];
                $stmt = $connection->prepare(
                    'SELECT id FROM testcase_contributions WHERE course_id = ? AND user_id = ?'
                );
                $stmt->bind_param('ii', $courseid, $userid);
                $stmt->execute();
                $owned = array_map('intval', array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'id'));
                $stmt->close();
                if ($owned) {
                    $in = implode(',', $owned);
                    $connection->query("DELETE FROM contribution_reviews WHERE contribution_id IN ({$in})");
                    $connection->query("DELETE FROM testcase_rewards WHERE contribution_id IN ({$in})");
                    $connection->query("DELETE FROM testcase_canonical_keys WHERE canonical_contribution_id IN ({$in})");
                    $connection->query(
                        "UPDATE testcase_contributions SET duplicate_of = NULL, status = 'submitted'
                          WHERE duplicate_of IN ({$in})"
                    );
                    $connection->query("DELETE FROM testcase_contributions WHERE id IN ({$in})");
                }
                foreach ([
                    'DELETE FROM testcase_rewards WHERE course_id = ? AND receiver_user_id = ?',
                    'DELETE FROM testcase_runs WHERE course_id = ? AND user_id = ?',
                    'UPDATE contribution_reviews r JOIN testcase_contributions c ON c.id = r.contribution_id
                        SET r.reviewer_user_id = 0 WHERE c.course_id = ? AND r.reviewer_user_id = ?',
                ] as $sql) {
                    $stmt = $connection->prepare($sql);
                    $stmt->bind_param('ii', $courseid, $userid);
                    $stmt->execute();
                    $stmt->close();
                }
                self::anonymise_policy_editor($connection, $courseid, $userid);
                $connection->commit();
            } catch (\Throwable $exception) {
                $connection->rollback();
                throw $exception;
            }
        }
        $connection->close();
    }

    /** Delete Quiz and question policies associated with a course. */
    private static function delete_course_policies(\mysqli $connection, int $courseid): void {
        $quizids = self::quiz_ids_for_course($courseid);
        if (!$quizids) {
            return;
        }
        $in = implode(',', $quizids);
        $connection->query("DELETE FROM question_policies WHERE quiz_id IN ({$in})");
        $connection->query("DELETE FROM quiz_settings WHERE quiz_id IN ({$in})");
    }

    /** Remove a deleted user's identity from policy audit fields. */
    private static function anonymise_policy_editor(\mysqli $connection, int $courseid, int $userid): void {
        $quizids = self::quiz_ids_for_course($courseid);
        if (!$quizids) {
            return;
        }
        $in = implode(',', $quizids);
        $connection->query("UPDATE quiz_settings SET updated_by = NULL WHERE quiz_id IN ({$in}) AND updated_by = {$userid}");
        $connection->query(
            "UPDATE question_policies SET updated_by = NULL WHERE quiz_id IN ({$in}) AND updated_by = {$userid}"
        );
    }

    /** @return int[] Quiz IDs in a Moodle course. */
    private static function quiz_ids_for_course(int $courseid): array {
        global $DB;
        return array_map('intval', $DB->get_fieldset_select('quiz', 'id', 'course = :courseid', [
            'courseid' => $courseid,
        ]));
    }
}
