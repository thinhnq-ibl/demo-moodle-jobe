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
 * Moodle course and question context service.
 *
 * @package    local_testcase_exchange
 * @copyright  2026 Nguyen Quoc Thinh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_testcase_exchange;

/**
 * Resolves and validates Moodle course, Quiz and CodeRunner question context.
 */
final class context_service {
    /**
     * Return current CodeRunner question versions used by Quizzes in a course.
     *
     * @param int $courseid Course ID.
     * @return array Question context records.
     */
    public static function questions_for_course(int $courseid): array {
        global $DB;
        $sql = "
            SELECT CONCAT(qz.id, '-', q.id) AS rowid,
                   qz.id AS quizid, qz.name AS quizname, q.id AS questionid,
                   q.name AS questionname, qo.coderunnertype
              FROM {course_modules} cm
              JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
              JOIN {quiz} qz ON qz.id = cm.instance
              JOIN {quiz_slots} qs ON qs.quizid = qz.id
              JOIN {question_references} qr ON qr.itemid = qs.id AND qr.component = 'mod_quiz'
              JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
              JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
               AND qv.version = CASE WHEN qr.version IS NULL THEN (
                   SELECT MAX(qv2.version)
                     FROM {question_versions} qv2
                    WHERE qv2.questionbankentryid = qbe.id AND qv2.status = 'ready'
               ) ELSE qr.version END
              JOIN {question} q ON q.id = qv.questionid AND q.qtype = 'coderunner'
              JOIN {question_coderunner_options} qo ON qo.questionid = q.id
             WHERE cm.course = ?
             ORDER BY qz.id, qs.slot";
        return array_values($DB->get_records_sql($sql, [$courseid]));
    }

    /**
     * Validate that a question belongs to a Quiz in a course.
     *
     * @param int $courseid Course ID.
     * @param int $quizid Quiz ID.
     * @param int $questionid Question ID.
     * @return \stdClass Validated question context.
     */
    public static function validate(int $courseid, int $quizid, int $questionid): \stdClass {
        foreach (self::questions_for_course($courseid) as $record) {
            if ((int) $record->quizid === $quizid && (int) $record->questionid === $questionid) {
                return $record;
            }
        }
        throw new \moodle_exception('invalidcontext', 'local_testcase_exchange');
    }

    /**
     * Enrich a list of contributions with Moodle user fullnames, usernames, and question names.
     *
     * @param array $contributions Raw contribution rows.
     * @param int $courseid Course ID.
     * @return array Enriched contributions.
     */
    public static function enrich_contributions(array $contributions, int $courseid): array {
        global $DB;

        if (empty($contributions)) {
            return [];
        }

        $userids = array_unique(array_column($contributions, 'user_id'));
        $users = [];
        if (!empty($userids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($userids);
            $userrecords = $DB->get_records_select('user', "id $insql", $inparams, '', 'id, username, firstname, lastname, email');
            foreach ($userrecords as $u) {
                $users[(int) $u->id] = $u;
            }
        }

        $questions = self::questions_for_course($courseid);
        $questionmap = [];
        foreach ($questions as $q) {
            $questionmap[$q->quizid . ':' . $q->questionid] = $q;
        }

        $statuslabels = [
            'submitted' => get_string('pending', 'local_testcase_exchange'),
            'approved' => get_string('status_approved', 'local_testcase_exchange'),
            'rejected' => get_string('status_rejected', 'local_testcase_exchange'),
            'duplicate' => get_string('status_duplicate', 'local_testcase_exchange'),
            'needs_explanation' => get_string('status_needs_explanation', 'local_testcase_exchange'),
            'archived' => get_string('status_archived', 'local_testcase_exchange'),
        ];

        $badgemap = [
            'submitted' => 'badge badge-warning text-dark',
            'approved' => 'badge badge-success',
            'rejected' => 'badge badge-danger',
            'duplicate' => 'badge badge-secondary',
            'needs_explanation' => 'badge badge-info',
            'archived' => 'badge badge-light',
        ];

        foreach ($contributions as &$c) {
            $uid = (int) $c['user_id'];
            if (isset($users[$uid])) {
                $u = $users[$uid];
                $c['student_name'] = fullname($u);
                $c['student_username'] = $u->username;
                $c['student_email'] = $u->email;
                $c['student_display'] = fullname($u) . ' (' . $u->username . ')';
            } else {
                $c['student_name'] = 'User #' . $uid;
                $c['student_username'] = '#' . $uid;
                $c['student_email'] = '';
                $c['student_display'] = 'User #' . $uid;
            }

            $qk = $c['quiz_id'] . ':' . $c['question_id'];
            if (isset($questionmap[$qk])) {
                $q = $questionmap[$qk];
                $c['quiz_name'] = $q->quizname;
                $c['question_name'] = $q->questionname;
                $c['quiz_question_label'] = $q->quizname . ' — ' . $q->questionname;
            } else {
                $c['quiz_name'] = 'Quiz #' . $c['quiz_id'];
                $c['question_name'] = 'Question #' . $c['question_id'];
                $c['quiz_question_label'] = 'Quiz #' . $c['quiz_id'] . ' / #' . $c['question_id'];
            }

            $st = (string) $c['status'];
            $c['status_label'] = $statuslabels[$st] ?? $st;
            $c['status_badge_class'] = $badgemap[$st] ?? 'badge badge-light';
            $c['is_pending'] = ($st === 'submitted' || $st === 'needs_explanation');
            $c['is_approved'] = ($st === 'approved');
            $c['is_rejected'] = ($st === 'rejected');
            $c['is_duplicate'] = ($st === 'duplicate');

            // Star rating & teacher comment.
            $rating = !empty($c['rating']) ? (int) $c['rating'] : (!empty($c['latest_rating']) ? (int) $c['latest_rating'] : 0);
            if ($rating >= 1 && $rating <= 5) {
                $c['has_rating'] = true;
                $c['rating_val'] = $rating;
                $c['stars_display'] = str_repeat('★', $rating) . str_repeat('☆', 5 - $rating);
                $c['stars_emoji'] = str_repeat('⭐', $rating);
                $c['is_rating_1'] = ($rating === 1);
                $c['is_rating_2'] = ($rating === 2);
                $c['is_rating_3'] = ($rating === 3);
                $c['is_rating_4'] = ($rating === 4);
                $c['is_rating_5'] = ($rating === 5);
            } else {
                $c['has_rating'] = false;
                $c['rating_val'] = 0;
                $c['stars_display'] = '';
                $c['stars_emoji'] = '';
                $c['is_rating_1'] = false;
                $c['is_rating_2'] = false;
                $c['is_rating_3'] = false;
                $c['is_rating_4'] = false;
                $c['is_rating_5'] = false;
            }

            $comment = !empty($c['review_comment']) ? trim($c['review_comment']) : (!empty($c['latest_review_note']) ? trim($c['latest_review_note']) : '');
            $c['teacher_comment'] = $comment;
            $c['has_teacher_comment'] = ($comment !== '');
        }
        unset($c);

        return $contributions;
    }

    /**
     * Extract the latest valid code submission for a question in a quiz attempt.
     * Prioritises steps that represent actual graded executions over draft/autosave steps.
     *
     * @param int $usageid Question usage ID (attempt->uniqueid).
     * @param int $questionid Question ID.
     * @return string The submitted student source code, or empty string.
     */
    public static function get_latest_valid_submission_code(int $usageid, int $questionid): string {
        global $DB;

        // 1. First priority: look for a step that had an actual graded execution.
        $sqlgraded = "
            SELECT qasd.value
              FROM {question_attempts} qa
              JOIN {question_attempt_steps} qas ON qas.questionattemptid = qa.id
              JOIN {question_attempt_step_data} qasd ON qasd.attemptstepid = qas.id
             WHERE qa.questionusageid = :usageid
               AND qa.questionid = :questionid
               AND qasd.name = 'answer'
               AND qasd.value <> ''
               AND (
                   qas.state IN ('gradedpartial', 'gradedright', 'gradedwrong', 'complete')
                   OR EXISTS (
                       SELECT 1 FROM {question_attempt_step_data} qasdx
                        WHERE qasdx.attemptstepid = qas.id
                          AND (qasdx.name = '-_testoutcome' OR qasdx.name = '-submit')
                   )
               )
          ORDER BY qas.sequencenumber DESC";

        $code = (string) $DB->get_field_sql($sqlgraded, [
            'usageid' => $usageid,
            'questionid' => $questionid,
        ], IGNORE_MULTIPLE);

        if (trim($code) !== '') {
            return $code;
        }

        // 2. Fallback: latest non-empty answer step if no graded step exists yet.
        $sqlfallback = "
            SELECT qasd.value
              FROM {question_attempts} qa
              JOIN {question_attempt_steps} qas ON qas.questionattemptid = qa.id
              JOIN {question_attempt_step_data} qasd ON qasd.attemptstepid = qas.id
             WHERE qa.questionusageid = :usageid
               AND qa.questionid = :questionid
               AND qasd.name = 'answer'
               AND qasd.value <> ''
          ORDER BY qas.sequencenumber DESC";

        return (string) $DB->get_field_sql($sqlfallback, [
            'usageid' => $usageid,
            'questionid' => $questionid,
        ], IGNORE_MULTIPLE);
    }

    /**
     * Check if a question in an attempt has a valid code answer.
     *
     * @param int $usageid Question usage ID.
     * @param int $questionid Question ID.
     * @return bool
     */
    public static function has_valid_submission(int $usageid, int $questionid): bool {
        return self::get_latest_valid_submission_code($usageid, $questionid) !== '';
    }

    /**
     * Reconstruct and classify the student submission trajectory across time.
     * Differentiates between:
     * - 'init': question attempt initialization
     * - 'edit_draft': code edited / autosaved without check execution
     * - 'check_execute': student clicked 'Check' and executed code on Jobe
     * - 'finish_attempt': attempt submitted / finalized
     *
     * @param int $usageid Question usage ID.
     * @param int $questionid Question ID.
     * @return array List of categorized trajectory steps in chronological order.
     */
    public static function get_submission_trajectory(int $usageid, int $questionid): array {
        global $DB;

        $sql = "
            SELECT qas.id AS step_id,
                   qas.sequencenumber,
                   qas.state,
                   qas.fraction,
                   qas.timecreated,
                   qasd.name,
                   qasd.value
              FROM {question_attempts} qa
              JOIN {question_attempt_steps} qas ON qas.questionattemptid = qa.id
              LEFT JOIN {question_attempt_step_data} qasd ON qasd.attemptstepid = qas.id
             WHERE qa.questionusageid = :usageid
               AND qa.questionid = :questionid
          ORDER BY qas.sequencenumber ASC, qasd.id ASC";

        $records = $DB->get_records_sql($sql, [
            'usageid' => $usageid,
            'questionid' => $questionid,
        ]);

        $steps = [];
        foreach ($records as $r) {
            $seq = (int) $r->sequencenumber;
            if (!isset($steps[$seq])) {
                $steps[$seq] = [
                    'step_number' => $seq,
                    'state' => (string) $r->state,
                    'fraction' => $r->fraction !== null ? (float) $r->fraction : null,
                    'timecreated' => (int) $r->timecreated,
                    'timestamp_iso' => date('c', (int) $r->timecreated),
                    'timestamp_human' => date('Y-m-d H:i:s', (int) $r->timecreated),
                    'data' => [],
                ];
            }
            if ($r->name !== null) {
                $steps[$seq]['data'][$r->name] = $r->value;
            }
        }

        $trajectory = [];
        $lastcode = '';
        foreach ($steps as $seq => $step) {
            $data = $step['data'];
            $hasanswer = isset($data['answer']) && trim($data['answer']) !== '';
            $code = $hasanswer ? $data['answer'] : $lastcode;
            if ($hasanswer) {
                $lastcode = $code;
            }

            $hascheck = isset($data['-submit']) || isset($data['_testoutcome']) ||
                in_array($step['state'], ['gradedpartial', 'gradedright', 'gradedwrong'], true);
            $hasfinish = isset($data['-finish']) || $step['state'] === 'complete';

            if ($seq === 0) {
                $type = 'init';
                $desc = 'Khởi tạo câu hỏi trong đề thi';
            } else if ($hasfinish) {
                $type = 'finish_attempt';
                $desc = 'Nộp bài thi & chốt kết quả câu hỏi';
            } else if ($hascheck) {
                $type = 'check_execute';
                $desc = 'Bấm Check và nhận kết quả thực thi từ Jobe';
            } else if ($hasanswer) {
                $type = 'edit_draft';
                $desc = 'Chỉnh sửa mã nguồn / Lưu nháp';
            } else {
                $type = 'interaction';
                $desc = 'Tương tác giao diện câu hỏi';
            }

            $trajectory[] = [
                'step_number' => $seq,
                'event_type' => $type,
                'description' => $desc,
                'state' => $step['state'],
                'fraction' => $step['fraction'],
                'has_execution' => $hascheck,
                'code' => $code,
                'code_length' => strlen($code),
                'timestamp' => $step['timestamp_human'],
                'timestamp_iso' => $step['timestamp_iso'],
            ];
        }

        return $trajectory;
    }

    /**
     * Return all courses with CodeRunner questions.
     *
     * @return array<int, \stdClass> List of course summaries.
     */
    public static function courses_with_coderunner(): array {
        global $DB;
        $sql = "
            SELECT c.id AS courseid, c.fullname, c.shortname,
                   COUNT(DISTINCT qz.id) AS quiz_count,
                   COUNT(DISTINCT q.id) AS question_count
              FROM {course} c
              JOIN {course_modules} cm ON cm.course = c.id
              JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
              JOIN {quiz} qz ON qz.id = cm.instance
              JOIN {quiz_slots} qs ON qs.quizid = qz.id
              JOIN {question_references} qr ON qr.itemid = qs.id AND qr.component = 'mod_quiz'
              JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
              JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
               AND qv.version = CASE WHEN qr.version IS NULL THEN (
                   SELECT MAX(qv2.version)
                     FROM {question_versions} qv2
                    WHERE qv2.questionbankentryid = qbe.id AND qv2.status = 'ready'
               ) ELSE qr.version END
              JOIN {question} q ON q.id = qv.questionid AND q.qtype = 'coderunner'
             WHERE c.id > 1
             GROUP BY c.id, c.fullname, c.shortname
             ORDER BY c.id ASC";
        return array_values($DB->get_records_sql($sql));
    }
}

