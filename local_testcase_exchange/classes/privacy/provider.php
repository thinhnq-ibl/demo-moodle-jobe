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

namespace local_testcase_exchange\privacy;

defined('MOODLE_INTERNAL') || die();

use context;
use context_course;
use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\transform;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for local_testcase_exchange implementing GDPR compliance.
 *
 * @package    local_testcase_exchange
 * @copyright  2026 Nguyen Quoc Thinh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {

    /**
     * Describe personal data stored by the plugin.
     *
     * @param collection $collection Metadata collection.
     * @return collection
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table('local_tce_runs', [
            'user_id' => 'privacy:metadata:runs:user_id',
            'input_raw' => 'privacy:metadata:runs:input_raw',
            'predicted_output' => 'privacy:metadata:runs:predicted_output',
            'student_run_output' => 'privacy:metadata:runs:student_run_output',
            'oracle_output' => 'privacy:metadata:runs:oracle_output',
            'student_code_snapshot' => 'privacy:metadata:runs:student_code_snapshot',
            'reflection' => 'privacy:metadata:runs:reflection',
            'timecreated' => 'privacy:metadata:runs:timecreated',
        ], 'privacy:metadata:runs');

        $collection->add_database_table('local_tce_contributions', [
            'user_id' => 'privacy:metadata:contributions:user_id',
            'input_normalized' => 'privacy:metadata:contributions:input_normalized',
            'oracle_output' => 'privacy:metadata:contributions:oracle_output',
            'purpose' => 'privacy:metadata:contributions:purpose',
            'status' => 'privacy:metadata:contributions:status',
            'rating' => 'privacy:metadata:contributions:rating',
            'review_comment' => 'privacy:metadata:contributions:review_comment',
            'timecreated' => 'privacy:metadata:contributions:timecreated',
        ], 'privacy:metadata:contributions');

        $collection->add_database_table('local_tce_rewards', [
            'receiver_user_id' => 'privacy:metadata:rewards:receiver_user_id',
            'is_claimed' => 'privacy:metadata:rewards:is_claimed',
            'claimed_at' => 'privacy:metadata:rewards:claimed_at',
            'timecreated' => 'privacy:metadata:rewards:timecreated',
        ], 'privacy:metadata:rewards');

        $collection->add_database_table('local_tce_reviews', [
            'reviewer_user_id' => 'privacy:metadata:reviews:reviewer_user_id',
            'review_note' => 'privacy:metadata:reviews:review_note',
            'rating' => 'privacy:metadata:reviews:rating',
            'timecreated' => 'privacy:metadata:reviews:timecreated',
        ], 'privacy:metadata:reviews');

        $collection->add_database_table('local_tce_revisions', [
            'user_id' => 'privacy:metadata:revisions:user_id',
            'code_content' => 'privacy:metadata:revisions:code_content',
            'step_number' => 'privacy:metadata:revisions:step_number',
            'timecreated' => 'privacy:metadata:revisions:timecreated',
        ], 'privacy:metadata:revisions');

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
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof context_course) {
                continue;
            }
            $courseid = (int) $context->instanceid;
            $data = (object) [
                'private_runs' => array_values($DB->get_records('local_tce_runs', ['course_id' => $courseid, 'user_id' => $userid], 'id ASC')),
                'contributions' => array_values($DB->get_records('local_tce_contributions', ['course_id' => $courseid, 'user_id' => $userid], 'id ASC')),
                'received_rewards' => array_values($DB->get_records('local_tce_rewards', ['course_id' => $courseid, 'receiver_user_id' => $userid], 'id ASC')),
                'code_revisions' => array_values($DB->get_records('local_tce_revisions', ['course_id' => $courseid, 'user_id' => $userid], 'id ASC')),
                'reviews_conducted' => array_values($DB->get_records_sql(
                    'SELECT r.* FROM {local_tce_reviews} r
                       JOIN {local_tce_contributions} c ON c.id = r.contribution_id
                      WHERE c.course_id = ? AND r.reviewer_user_id = ? ORDER BY r.id ASC',
                    [$courseid, $userid]
                )),
            ];
            writer::with_context($context)->export_data(
                [get_string('privacy:path', 'local_testcase_exchange')],
                $data
            );
        }
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
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof context_course) {
            return;
        }
        $courseid = (int) $context->instanceid;

        $sql = "SELECT user_id FROM {local_tce_runs} WHERE course_id = :c1
                UNION
                SELECT user_id FROM {local_tce_contributions} WHERE course_id = :c2
                UNION
                SELECT receiver_user_id AS user_id FROM {local_tce_rewards} WHERE course_id = :c3
                UNION
                SELECT user_id FROM {local_tce_revisions} WHERE course_id = :c4";
        $records = $DB->get_records_sql($sql, ['c1' => $courseid, 'c2' => $courseid, 'c3' => $courseid, 'c4' => $courseid]);
        $userids = [];
        foreach ($records as $r) {
            if (!empty($r->user_id)) {
                $userids[] = (int) $r->user_id;
            }
        }
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

    /**
     * Find course IDs associated with supplied users.
     *
     * @param int[] $userids User IDs.
     * @return int[] Course IDs.
     */
    private static function course_ids_for_users(array $userids): array {
        global $DB;

        if (empty($userids)) {
            return [];
        }
        [$in1, $p1] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'u1');
        [$in2, $p2] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'u2');
        [$in3, $p3] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'u3');
        [$in4, $p4] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'u4');
        $params = array_merge($p1, $p2, $p3, $p4);

        $sql = "SELECT course_id FROM {local_tce_runs} WHERE user_id {$in1}
                UNION
                SELECT course_id FROM {local_tce_contributions} WHERE user_id {$in2}
                UNION
                SELECT course_id FROM {local_tce_rewards} WHERE receiver_user_id {$in3}
                UNION
                SELECT course_id FROM {local_tce_revisions} WHERE user_id {$in4}";
        $records = $DB->get_records_sql($sql, $params);
        $courseids = [];
        foreach ($records as $r) {
            if (!empty($r->course_id)) {
                $courseids[] = (int) $r->course_id;
            }
        }
        return array_values(array_unique($courseids));
    }

    /**
     * Delete all plugin records belonging to a course.
     *
     * @param int $courseid Course ID.
     */
    private static function delete_course_data(int $courseid): void {
        global $DB;

        $tx = $DB->start_delegated_transaction();
        try {
            // Delete reviews for contributions in this course
            $DB->execute(
                'DELETE FROM {local_tce_reviews} WHERE contribution_id IN (
                    SELECT id FROM {local_tce_contributions} WHERE course_id = ?
                )',
                [$courseid]
            );
            $DB->delete_records('local_tce_rewards', ['course_id' => $courseid]);
            $DB->delete_records('local_tce_canonical', ['course_id' => $courseid]);
            $DB->delete_records('local_tce_contributions', ['course_id' => $courseid]);
            $DB->delete_records('local_tce_runs', ['course_id' => $courseid]);
            $DB->delete_records('local_tce_revisions', ['course_id' => $courseid]);
            $DB->delete_records('local_tce_seeds', ['course_id' => $courseid]);
            $DB->delete_records('local_tce_solutions', ['course_id' => $courseid]);
            $DB->delete_records('local_tce_views', ['course_id' => $courseid]);

            self::delete_course_policies($courseid);
            $tx->allow_commit();
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    /**
     * Delete or anonymise one user's data in selected courses.
     *
     * @param int $userid User ID.
     * @param int[] $courseids Course IDs.
     */
    private static function delete_user_data(int $userid, array $courseids): void {
        global $DB;

        foreach ($courseids as $courseid) {
            $courseid = (int) $courseid;
            $tx = $DB->start_delegated_transaction();
            try {
                $owned = $DB->get_fieldset_select(
                    'local_tce_contributions',
                    'id',
                    'course_id = :cid AND user_id = :uid',
                    ['cid' => $courseid, 'uid' => $userid]
                );

                if (!empty($owned)) {
                    [$insql, $inparams] = $DB->get_in_or_equal($owned, SQL_PARAMS_NAMED, 'contrib');
                    $DB->delete_records_select('local_tce_reviews', "contribution_id {$insql}", $inparams);
                    $DB->delete_records_select('local_tce_rewards', "contribution_id {$insql}", $inparams);
                    $DB->delete_records_select('local_tce_canonical', "canonical_contribution_id {$insql}", $inparams);
                    $DB->execute(
                        "UPDATE {local_tce_contributions} SET duplicate_of = NULL, status = 'submitted' WHERE duplicate_of {$insql}",
                        $inparams
                    );
                    $DB->delete_records_select('local_tce_contributions', "id {$insql}", $inparams);
                }

                $DB->delete_records('local_tce_rewards', ['course_id' => $courseid, 'receiver_user_id' => $userid]);
                $DB->delete_records('local_tce_runs', ['course_id' => $courseid, 'user_id' => $userid]);
                $DB->delete_records('local_tce_revisions', ['course_id' => $courseid, 'user_id' => $userid]);
                $DB->delete_records('local_tce_views', ['course_id' => $courseid, 'user_id' => $userid]);

                // Anonymise review notes given by this user
                $DB->execute(
                    'UPDATE {local_tce_reviews} SET reviewer_user_id = 0
                      WHERE contribution_id IN (
                          SELECT id FROM {local_tce_contributions} WHERE course_id = :cid
                      ) AND reviewer_user_id = :uid',
                    ['cid' => $courseid, 'uid' => $userid]
                );

                self::anonymise_policy_editor($courseid, $userid);
                $tx->allow_commit();
            } catch (\Throwable $e) {
                throw $e;
            }
        }
    }

    /**
     * Delete Quiz and question policies associated with a course.
     */
    private static function delete_course_policies(int $courseid): void {
        global $DB;

        $quizids = self::quiz_ids_for_course($courseid);
        if (!$quizids) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($quizids, SQL_PARAMS_NAMED, 'quiz');
        $DB->delete_records_select('local_tce_policies', "quiz_id {$insql}", $params);
        $DB->delete_records_select('local_tce_quiz_settings', "quiz_id {$insql}", $params);
    }

    /**
     * Remove a deleted user's identity from policy audit fields.
     */
    private static function anonymise_policy_editor(int $courseid, int $userid): void {
        global $DB;

        $quizids = self::quiz_ids_for_course($courseid);
        if (!$quizids) {
            return;
        }
        [$insql, $params] = $DB->get_in_or_equal($quizids, SQL_PARAMS_NAMED, 'quiz');
        $params['uid'] = $userid;
        $DB->execute("UPDATE {local_tce_quiz_settings} SET updated_by = NULL WHERE quiz_id {$insql} AND updated_by = :uid", $params);
        $DB->execute("UPDATE {local_tce_policies} SET updated_by = NULL WHERE quiz_id {$insql} AND updated_by = :uid", $params);
    }

    /**
     * Find Quiz IDs in a Moodle course.
     */
    private static function quiz_ids_for_course(int $courseid): array {
        global $DB;
        return array_map('intval', $DB->get_fieldset_select('quiz', 'id', 'course = :courseid', [
            'courseid' => $courseid,
        ]));
    }
}
