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
 * Testcase workflow service.
 *
 * @package    local_testcase_exchange
 * @copyright  2026 Nguyen Quoc Thinh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_testcase_exchange;

/**
 * Application service for private runs, contributions, review and rewards.
 */
final class testcase_service {
    /** @var \mysqli External repository connection. */
    private \mysqli $connection;

    /**
     * Create the workflow service.
     *
     * @param \mysqli $connection External repository connection.
     */
    public function __construct(\mysqli $connection) {
        $this->connection = $connection;
    }

    /**
     * Return settings for a Quiz.
     *
     * @param int $quizid Quiz ID.
     * @return array Quiz settings.
     */
    public function quiz_settings(int $quizid): array {
        $stmt = $this->connection->prepare('SELECT * FROM quiz_settings WHERE quiz_id = ?');
        $stmt->bind_param('i', $quizid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $row ?: [
            'quiz_id' => $quizid, 'enabled' => 0, 'review_mode' => 'teacher',
            'reward_policy' => 'one_for_one', 'show_oracle_output' => 1,
            'leaderboard_enabled' => 1, 'max_runs_per_minute' => 10, 'max_input_bytes' => 8192,
        ];
    }

    /**
     * Return execution policy for a question.
     *
     * @param int $quizid Quiz ID.
     * @param int $questionid Question ID.
     * @param string $coderunnertype CodeRunner question type.
     * @return array Question policy.
     */
    public function question_policy(int $quizid, int $questionid, string $coderunnertype = ''): array {
        $stmt = $this->connection->prepare('SELECT * FROM question_policies WHERE quiz_id = ? AND question_id = ?');
        $stmt->bind_param('ii', $quizid, $questionid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            return $row;
        }
        return [
            'quiz_id' => $quizid,
            'question_id' => $questionid,
            'input_mode' => str_contains($coderunnertype, 'program') ? 'stdin' : 'testcode',
            'testcode_template' => '',
            'normalization_mode' => 'trim',
            'categories' => 'normal,boundary,empty,invalid,large,branch,other',
        ];
    }

    /**
     * Execute and store a private testcase run.
     *
     * @param int $courseid Course ID.
     * @param \stdClass $quiz Quiz record.
     * @param \stdClass $questioncontext Validated question context.
     * @param int $userid User ID.
     * @param string $studentsource Student source.
     * @param string $input Raw input.
     * @param string $predicted Predicted output.
     * @param string $purpose Test purpose.
     * @param string $category Test category.
     * @param string $reflection Student reflection.
     * @return array Created run result.
     */
    public function create_run(
        int $courseid,
        \stdClass $quiz,
        \stdClass $questioncontext,
        int $userid,
        string $studentsource,
        string $input,
        string $predicted,
        string $purpose,
        string $category,
        string $reflection
    ): array {
        $settings = $this->quiz_settings((int) $quiz->id);
        if (empty($settings['enabled'])) {
            throw new \moodle_exception('featuredisabled', 'local_testcase_exchange');
        }
        if (strlen($input) > (int) $settings['max_input_bytes']) {
            throw new \moodle_exception('inputtoolarge', 'local_testcase_exchange');
        }
        $this->enforce_rate_limit($userid, (int) $quiz->id, (int) $settings['max_runs_per_minute']);
        $policy = $this->question_policy(
            (int) $quiz->id,
            (int) $questioncontext->questionid,
            (string) $questioncontext->coderunnertype
        );
        try {
            $normalized = normalizer::normalize($input, (string) $policy['normalization_mode']);
        } catch (\JsonException $e) {
            throw new \moodle_exception('invalidjsoninput', 'local_testcase_exchange');
        }
        $fingerprint = normalizer::fingerprint($normalized);
        $results = execution_service::run_pair(
            (int) $questioncontext->questionid,
            $studentsource,
            $input,
            $policy,
            $quiz
        );
        $server = $results['student']['server'] ?: $results['oracle']['server'];
        $sourcehash = hash('sha256', $studentsource);
        $studentsnapshot = $studentsource;
        $oraclesnapshot = (string) ($results['oracle_solution'] ?? '');
        $templatesnapshot = (string) ($results['template'] ?? '');
        $runtimecontext = json_encode([
            'coderunnertype' => (string) ($questioncontext->coderunnertype ?? ''),
            'input_mode' => (string) ($policy['input_mode'] ?? 'testcode'),
            'normalization_mode' => (string) ($policy['normalization_mode'] ?? 'trim'),
            'jobe_server' => $server,
            'timestamp' => date('c'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $qversion = (int) ($results['question_version'] ?? 1);
        $gradertype = (string) ($results['grader_type'] ?? 'EqualityGrader');
        $oracleversionhash = (string) ($results['oracle_version_hash'] ?? '');
        $testsuiteversionhash = (string) ($results['test_suite_version_hash'] ?? '');
        $executionlimits = (string) ($results['execution_limits'] ?? '');

        // Persist solution version immutably
        if ($oraclesnapshot !== '') {
            $this->ensure_solution_version(
                $courseid,
                (int) $quiz->id,
                (int) $questioncontext->questionid,
                $oraclesnapshot,
                $templatesnapshot,
                $qversion
            );
        }

        $stmt = $this->connection->prepare(
            'INSERT INTO testcase_runs
            (course_id, quiz_id, question_id, user_id, input_raw, input_normalized, input_fingerprint,
             predicted_output, student_run_output, oracle_output, student_outcome, oracle_outcome,
             purpose, category, reflection, jobe_server, student_source_hash,
             student_code_snapshot, oracle_solution_snapshot, template_snapshot, runtime_context,
             question_version, grader_type, oracle_version_hash, test_suite_version_hash, execution_limits)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $quizid = (int) $quiz->id;
        $questionid = (int) $questioncontext->questionid;
        $studentoutput = $results['student']['output'];
        $oracleoutput = $results['oracle']['output'];
        $studentoutcome = $results['student']['outcome'];
        $oracleoutcome = $results['oracle']['outcome'];
        $stmt->bind_param(
            'iiiisssssssssssssssssissss',
            $courseid,
            $quizid,
            $questionid,
            $userid,
            $input,
            $normalized,
            $fingerprint,
            $predicted,
            $studentoutput,
            $oracleoutput,
            $studentoutcome,
            $oracleoutcome,
            $purpose,
            $category,
            $reflection,
            $server,
            $sourcehash,
            $studentsnapshot,
            $oraclesnapshot,
            $templatesnapshot,
            $runtimecontext,
            $qversion,
            $gradertype,
            $oracleversionhash,
            $testsuiteversionhash,
            $executionlimits
        );
        $stmt->execute();
        $runid = (int) $stmt->insert_id;
        $stmt->close();
        return ['id' => $runid, 'student' => $results['student'], 'oracle' => $results['oracle'],
            'prediction_matches_oracle' => $oracleoutcome === 'success' &&
                trim($predicted) !== '' &&
                trim($predicted) === trim($oracleoutput)];
    }

    /**
     * Submit an owned private run as a contribution.
     *
     * @param int $runid Run ID.
     * @param int $userid Owner user ID.
     * @return array Contribution ID and status.
     */
    public function submit_contribution(int $runid, int $userid): array {
        $run = $this->owned_run($runid, $userid);
        $existingstmt = $this->connection->prepare(
            'SELECT id, status FROM testcase_contributions WHERE run_id = ?'
        );
        $existingstmt->bind_param('i', $runid);
        $existingstmt->execute();
        $existing = $existingstmt->get_result()->fetch_assoc();
        $existingstmt->close();
        if ($existing) {
            return ['id' => (int) $existing['id'], 'status' => $existing['status']];
        }
        if ($run['oracle_outcome'] !== 'success') {
            throw new \moodle_exception('oraclenotsuccessful', 'local_testcase_exchange');
        }
        $settings = $this->quiz_settings((int) $run['quiz_id']);
        $this->connection->begin_transaction();
        try {
            $status = 'submitted';
            $runpk = (int) $run['id'];
            $courseid = (int) $run['course_id'];
            $quizid = (int) $run['quiz_id'];
            $questionid = (int) $run['question_id'];
            $normalized = (string) $run['input_normalized'];
            $fingerprint = (string) $run['input_fingerprint'];
            $oracleoutput = (string) $run['oracle_output'];
            $purpose = (string) $run['purpose'];
            $category = (string) $run['category'];
            $stmt = $this->connection->prepare(
                'INSERT INTO testcase_contributions
                (run_id, course_id, quiz_id, question_id, user_id, input_normalized, input_fingerprint,
                 oracle_output, purpose, category, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param(
                'iiiiissssss',
                $runpk,
                $courseid,
                $quizid,
                $questionid,
                $userid,
                $normalized,
                $fingerprint,
                $oracleoutput,
                $purpose,
                $category,
                $status
            );
            $stmt->execute();
            $contributionid = (int) $stmt->insert_id;
            $stmt->close();

            $claim = $this->connection->prepare(
                'INSERT IGNORE INTO testcase_canonical_keys
                (course_id, quiz_id, question_id, input_fingerprint, canonical_contribution_id)
                VALUES (?, ?, ?, ?, ?)'
            );
            $claim->bind_param(
                'iiisi',
                $courseid,
                $quizid,
                $questionid,
                $fingerprint,
                $contributionid
            );
            $claim->execute();
            if ($claim->affected_rows === 0) {
                $canonical = $this->canonical_id($run);
                $status = 'duplicate';
                $update = $this->connection->prepare(
                    'UPDATE testcase_contributions SET status = ?, duplicate_of = ? WHERE id = ?'
                );
                $update->bind_param('sii', $status, $canonical, $contributionid);
                $update->execute();
                $update->close();
                $this->insert_review($contributionid, 0, 'submitted', 'duplicate', 'Exact fingerprint duplicate');
            } else if ($settings['review_mode'] === 'auto') {
                $status = 'approved';
                $update = $this->connection->prepare('UPDATE testcase_contributions SET status = ? WHERE id = ?');
                $update->bind_param('si', $status, $contributionid);
                $update->execute();
                $update->close();
                $this->insert_review($contributionid, 0, 'submitted', 'approved', 'Automatically approved by Quiz policy');
                $this->grant_reward_for_contribution($contributionid);
            }
            $claim->close();
            $this->connection->commit();
            return ['id' => $contributionid, 'status' => $status];
        } catch (\Throwable $e) {
            $this->connection->rollback();
            throw $e;
        }
    }

    /**
     * Apply a review transition and audit it.
     *
     * @param int $contributionid Contribution ID.
     * @param int $courseid Course ID.
     * @param int $reviewerid Reviewer user ID.
     * @param string $newstatus Requested status.
     * @param string $note Review note.
     */
    public function review(
        int $contributionid,
        int $courseid,
        int $reviewerid,
        string $newstatus,
        string $note = '',
        int $rating = 0,
        string $ratingreason = ''
    ): void {
        $allowed = ['approved', 'rejected', 'needs_explanation', 'archived', 'rate_only'];
        if (!in_array($newstatus, $allowed, true)) {
            throw new \moodle_exception('invalidstatus', 'local_testcase_exchange');
        }
        $stmt = $this->connection->prepare(
            'SELECT status, rating, review_comment FROM testcase_contributions WHERE id = ? AND course_id = ? FOR UPDATE'
        );
        $this->connection->begin_transaction();
        try {
            $stmt->bind_param('ii', $contributionid, $courseid);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$row) {
                throw new \moodle_exception('invalidcontribution', 'local_testcase_exchange');
            }
            $from = $row['status'];
            $targetstatus = ($newstatus === 'rate_only') ? $from : $newstatus;

            if ($targetstatus !== $from) {
                $transitions = [
                    'submitted' => ['approved', 'rejected', 'needs_explanation'],
                    'needs_explanation' => ['approved', 'rejected'],
                    'approved' => ['archived', 'rejected'],
                    'rejected' => ['approved'],
                ];
                if (!in_array($targetstatus, $transitions[$from] ?? [], true)) {
                    throw new \moodle_exception('invalidtransition', 'local_testcase_exchange');
                }
            }

            $finalrating = ($rating >= 1 && $rating <= 5) ? $rating : ($row['rating'] !== null ? (int) $row['rating'] : null);
            $finalcomment = (trim($note) !== '') ? trim($note) : $row['review_comment'];

            $update = $this->connection->prepare(
                'UPDATE testcase_contributions SET status = ?, rating = ?, review_comment = ? WHERE id = ?'
            );
            $update->bind_param('sisi', $targetstatus, $finalrating, $finalcomment, $contributionid);
            $update->execute();
            $update->close();

            $this->insert_review($contributionid, $reviewerid, $from, $targetstatus, $note, $finalrating, $ratingreason);
            if ($targetstatus === 'approved' && $from !== 'approved') {
                $this->grant_reward_for_contribution($contributionid);
            }
            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollback();
            throw $e;
        }
    }

    /**
     * Add an explanation and resubmit a contribution.
     *
     * @param int $contributionid Contribution ID.
     * @param int $userid Owner user ID.
     * @param string $explanation Explanation text.
     */
    public function resubmit_explanation(int $contributionid, int $userid, string $explanation): void {
        if (trim($explanation) === '') {
            throw new \moodle_exception('explanationrequired', 'local_testcase_exchange');
        }
        $this->connection->begin_transaction();
        try {
            $stmt = $this->connection->prepare(
                "SELECT status FROM testcase_contributions
                  WHERE id = ? AND user_id = ? FOR UPDATE"
            );
            $stmt->bind_param('ii', $contributionid, $userid);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if (!$row || $row['status'] !== 'needs_explanation') {
                throw new \moodle_exception('invalidtransition', 'local_testcase_exchange');
            }
            $status = 'submitted';
            $update = $this->connection->prepare(
                "UPDATE testcase_contributions
                    SET status = ?, purpose = CONCAT(COALESCE(purpose, ''), '\nExplanation: ', ?)
                  WHERE id = ?"
            );
            $update->bind_param('ssi', $status, $explanation, $contributionid);
            $update->execute();
            $update->close();
            $this->insert_review(
                $contributionid,
                $userid,
                'needs_explanation',
                'submitted',
                $explanation
            );
            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollback();
            throw $e;
        }
    }

    /**
     * Save Quiz-level policy settings.
     *
     * @param int $quizid Quiz ID.
     * @param int $userid Editor user ID.
     * @param array $values Submitted values.
     */
    public function save_quiz_settings(int $quizid, int $userid, array $values): void {
        $stmt = $this->connection->prepare(
            'INSERT INTO quiz_settings
            (quiz_id, is_enabled, enabled, review_mode, reward_policy, show_oracle_output,
             leaderboard_enabled, max_runs_per_minute, max_input_bytes, updated_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE is_enabled=VALUES(is_enabled), enabled=VALUES(enabled),
             review_mode=VALUES(review_mode), reward_policy=VALUES(reward_policy),
             show_oracle_output=VALUES(show_oracle_output), leaderboard_enabled=VALUES(leaderboard_enabled),
             max_runs_per_minute=VALUES(max_runs_per_minute), max_input_bytes=VALUES(max_input_bytes),
             updated_by=VALUES(updated_by)'
        );
        $enabled = !empty($values['enabled']) ? 1 : 0;
        $showoracle = !empty($values['show_oracle_output']) ? 1 : 0;
        $leaderboard = !empty($values['leaderboard_enabled']) ? 1 : 0;
        $reviewmode = in_array($values['review_mode'], ['auto', 'teacher'], true) ? $values['review_mode'] : 'teacher';
        $rewardpolicy = in_array($values['reward_policy'], ['disabled', 'one_for_one'], true) ?
            $values['reward_policy'] : 'one_for_one';
        $maxruns = max(1, min(120, (int) $values['max_runs_per_minute']));
        $maxbytes = max(64, min(1048576, (int) $values['max_input_bytes']));
        $stmt->bind_param(
            'iiissiiiii',
            $quizid,
            $enabled,
            $enabled,
            $reviewmode,
            $rewardpolicy,
            $showoracle,
            $leaderboard,
            $maxruns,
            $maxbytes,
            $userid
        );
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Save question-level execution policy.
     *
     * @param int $quizid Quiz ID.
     * @param int $questionid Question ID.
     * @param int $userid Editor user ID.
     * @param array $values Submitted values.
     */
    public function save_question_policy(int $quizid, int $questionid, int $userid, array $values): void {
        $mode = in_array($values['input_mode'], ['stdin', 'testcode', 'template'], true) ?
            $values['input_mode'] : 'testcode';
        $normalization = in_array($values['normalization_mode'], ['raw', 'trim', 'json'], true) ?
            $values['normalization_mode'] : 'trim';
        $template = (string) $values['testcode_template'];
        $categories = (string) $values['categories'];
        $stmt = $this->connection->prepare(
            'INSERT INTO question_policies
            (question_id, quiz_id, input_mode, testcode_template, normalization_mode, categories, updated_by)
            VALUES (?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE quiz_id=VALUES(quiz_id), input_mode=VALUES(input_mode),
            testcode_template=VALUES(testcode_template), normalization_mode=VALUES(normalization_mode),
            categories=VALUES(categories), updated_by=VALUES(updated_by)'
        );
        $stmt->bind_param('iissssi', $questionid, $quizid, $mode, $template, $normalization, $categories, $userid);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * Return private runs for a user.
     *
     * @param int $courseid Course ID.
     * @param int $userid User ID.
     * @return array Private runs.
     */
    public function runs_for_user(int $courseid, int $userid): array {
        $stmt = $this->connection->prepare(
            'SELECT * FROM testcase_runs WHERE course_id = ? AND user_id = ? ORDER BY id DESC LIMIT 100'
        );
        $stmt->bind_param('ii', $courseid, $userid);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    /**
     * Return contributions for a user.
     *
     * @param int $courseid Course ID.
     * @param int $userid User ID.
     * @return array Contributions.
     */
    public function contributions_for_user(int $courseid, int $userid): array {
        $stmt = $this->connection->prepare(
            'SELECT c.*,
                    (SELECT cr.review_note FROM contribution_reviews cr
                      WHERE cr.contribution_id = c.id AND cr.review_note IS NOT NULL AND cr.review_note <> \'\'
                      ORDER BY cr.id DESC LIMIT 1) AS latest_review_note,
                    (SELECT cr.rating FROM contribution_reviews cr
                      WHERE cr.contribution_id = c.id AND cr.rating IS NOT NULL AND cr.rating > 0
                      ORDER BY cr.id DESC LIMIT 1) AS latest_rating
               FROM testcase_contributions c
              WHERE c.course_id = ? AND c.user_id = ? ORDER BY c.id DESC LIMIT 100'
        );
        $stmt->bind_param('ii', $courseid, $userid);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }


    /**
     * Return contributions awaiting review.
     *
     * @param int $courseid Course ID.
     * @return array Pending contributions.
     */
    public function pending_contributions(int $courseid): array {
        return $this->course_contributions($courseid, 'pending');
    }

    /**
     * Return contributions for a course with optional status, quiz, and question filters.
     *
     * @param int $courseid Course ID.
     * @param string $status Filter status ('all', 'pending', 'submitted', 'approved', 'rejected', 'duplicate', 'needs_explanation').
     * @param int $quizid Optional Quiz ID filter.
     * @param int $questionid Optional Question ID filter.
     * @return array Contributions.
     */
    public function course_contributions(int $courseid, string $status = 'all', int $quizid = 0, int $questionid = 0): array {
        $conditions = ['c.course_id = ?'];
        $params = [$courseid];
        $types = 'i';

        if ($status === 'pending') {
            $conditions[] = "c.status IN ('submitted', 'needs_explanation')";
        } else if ($status !== 'all' && in_array($status, ['submitted', 'approved', 'rejected', 'duplicate', 'needs_explanation', 'archived'], true)) {
            $conditions[] = 'c.status = ?';
            $params[] = $status;
            $types .= 's';
        }

        if ($quizid > 0) {
            $conditions[] = 'c.quiz_id = ?';
            $params[] = $quizid;
            $types .= 'i';
        }

        if ($questionid > 0) {
            $conditions[] = 'c.question_id = ?';
            $params[] = $questionid;
            $types .= 'i';
        }

        $where = implode(' AND ', $conditions);
        $sql = "SELECT c.*,
                       (SELECT cr.review_note FROM contribution_reviews cr
                         WHERE cr.contribution_id = c.id AND cr.review_note IS NOT NULL AND cr.review_note <> ''
                         ORDER BY cr.id DESC LIMIT 1) AS latest_review_note,
                       (SELECT cr.rating FROM contribution_reviews cr
                         WHERE cr.contribution_id = c.id AND cr.rating IS NOT NULL AND cr.rating > 0
                         ORDER BY cr.id DESC LIMIT 1) AS latest_rating
                  FROM testcase_contributions c
                 WHERE {$where}
              ORDER BY c.id DESC LIMIT 500";

        $stmt = $this->connection->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $rows;
    }

    /**
     * Return contribution counts by status for a course with optional quiz/question filters.
     *
     * @param int $courseid Course ID.
     * @param int $quizid Optional Quiz ID.
     * @param int $questionid Optional Question ID.
     * @return array<string, int>
     */
    public function contribution_counts(int $courseid, int $quizid = 0, int $questionid = 0): array {
        $conditions = ['course_id = ?'];
        $params = [$courseid];
        $types = 'i';

        if ($quizid > 0) {
            $conditions[] = 'quiz_id = ?';
            $params[] = $quizid;
            $types .= 'i';
        }

        if ($questionid > 0) {
            $conditions[] = 'question_id = ?';
            $params[] = $questionid;
            $types .= 'i';
        }

        $where = implode(' AND ', $conditions);
        $sql = "SELECT status, COUNT(*) AS cnt FROM testcase_contributions WHERE {$where} GROUP BY status";

        $stmt = $this->connection->prepare($sql);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $result = $stmt->get_result();

        $counts = [
            'all' => 0,
            'pending' => 0,
            'approved' => 0,
            'duplicate' => 0,
            'rejected' => 0,
            'needs_explanation' => 0,
            'submitted' => 0,
        ];

        while ($row = $result->fetch_assoc()) {
            $st = (string) $row['status'];
            $cnt = (int) $row['cnt'];
            $counts[$st] = $cnt;
            $counts['all'] += $cnt;
            if ($st === 'submitted' || $st === 'needs_explanation') {
                $counts['pending'] += $cnt;
            }
        }
        $stmt->close();
        return $counts;
    }

    /**
     * Load a private run owned by a user.
     *
     * @param int $runid Run ID.
     * @param int $userid Owner user ID.
     * @return array Owned run.
     */
    private function owned_run(int $runid, int $userid): array {
        $stmt = $this->connection->prepare('SELECT * FROM testcase_runs WHERE id = ? AND user_id = ?');
        $stmt->bind_param('ii', $runid, $userid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$row) {
            throw new \moodle_exception('invalidrun', 'local_testcase_exchange');
        }
        return $row;
    }

    /**
     * Enforce the per-user Quiz run limit.
     *
     * @param int $userid User ID.
     * @param int $quizid Quiz ID.
     * @param int $limit Maximum runs per minute.
     */
    private function enforce_rate_limit(int $userid, int $quizid, int $limit): void {
        $stmt = $this->connection->prepare(
            'SELECT COUNT(*) AS total FROM testcase_runs
              WHERE user_id = ? AND quiz_id = ? AND created_at >= (NOW() - INTERVAL 1 MINUTE)'
        );
        $stmt->bind_param('ii', $userid, $quizid);
        $stmt->execute();
        $count = (int) $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();
        if ($count >= $limit) {
            throw new \moodle_exception('ratelimited', 'local_testcase_exchange');
        }
    }

    /**
     * Find the canonical contribution ID for a run.
     *
     * @param array $run Private run.
     * @return int Canonical contribution ID.
     */
    private function canonical_id(array $run): int {
        $stmt = $this->connection->prepare(
            'SELECT canonical_contribution_id FROM testcase_canonical_keys
              WHERE course_id = ? AND quiz_id = ? AND question_id = ? AND input_fingerprint = ?'
        );
        $courseid = (int) $run['course_id'];
        $quizid = (int) $run['quiz_id'];
        $questionid = (int) $run['question_id'];
        $fingerprint = (string) $run['input_fingerprint'];
        $stmt->bind_param('iiis', $courseid, $quizid, $questionid, $fingerprint);
        $stmt->execute();
        $id = (int) $stmt->get_result()->fetch_assoc()['canonical_contribution_id'];
        $stmt->close();
        return $id;
    }

    /**
     * Ensure a solution version snapshot exists in question_solution_versions.
     *
     * @param int $courseid Course ID.
     * @param int $quizid Quiz ID.
     * @param int $questionid Question ID.
     * @param string $solutioncode Teacher answer code.
     * @param string $templatecode Question template code.
     * @param int $questionversion Version number.
     * @param int $authorid Teacher user ID.
     * @return array{id:int, solution_hash:string}
     */
    public function ensure_solution_version(
        int $courseid,
        int $quizid,
        int $questionid,
        string $solutioncode,
        string $templatecode = '',
        int $questionversion = 1,
        int $authorid = 0
    ): array {
        $hash = hash('sha256', trim($solutioncode));
        $stmt = $this->connection->prepare(
            'SELECT id, solution_hash FROM question_solution_versions
              WHERE course_id = ? AND quiz_id = ? AND question_id = ? AND solution_hash = ?'
        );
        $stmt->bind_param('iiis', $courseid, $quizid, $questionid, $hash);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            return ['id' => (int) $row['id'], 'solution_hash' => (string) $row['solution_hash']];
        }

        $ins = $this->connection->prepare(
            'INSERT IGNORE INTO question_solution_versions
            (course_id, quiz_id, question_id, question_version, solution_code, solution_hash, template_code, author_user_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $ins->bind_param(
            'iiiisssi',
            $courseid,
            $quizid,
            $questionid,
            $questionversion,
            $solutioncode,
            $hash,
            $templatecode,
            $authorid
        );
        $ins->execute();
        $id = $ins->insert_id ?: 0;
        $ins->close();

        return ['id' => (int) $id, 'solution_hash' => $hash];
    }

    /**
     * Synchronize Suite 2 (Reward Bank / Seeds) from Moodle Question Bank for a specific question.
     *
     * @param int $courseid Course ID.
     * @param int $quizid Quiz ID.
     * @param int $questionid Question ID.
     * @return int Number of seed testcases synchronized.
     */
    public function sync_question_testsuites(int $courseid, int $quizid, int $questionid): int {
        global $DB;

        $tests = $DB->get_records('question_coderunner_tests', ['questionid' => $questionid]);
        if (!$tests) {
            return 0;
        }

        $synced = 0;
        foreach ($tests as $t) {
            $extra = strtolower(trim((string) ($t->extra ?? '')));
            $is_reward_seed = in_array($extra, ['reward_bank', 'seed', 'suite2'], true) ||
                              (strpos($extra, 'reward') !== false && (int) $t->useasexample === 0);

            if (!$is_reward_seed) {
                continue;
            }

            $rawinput = trim((string) ($t->stdin ?? ''));
            if ($rawinput === '') {
                $rawinput = trim((string) ($t->testcode ?? ''));
            }
            if ($rawinput === '') {
                continue;
            }

            $normalized = normalizer::normalize($rawinput, 'trim');
            $fingerprint = normalizer::fingerprint($normalized);
            $testcode = (string) ($t->testcode ?? '');
            $stdin = (string) ($t->stdin ?? '');
            $expected = (string) ($t->expected ?? '');

            $stmt = $this->connection->prepare(
                'INSERT INTO question_seed_testcases
                (course_id, quiz_id, question_id, moodle_test_id, testcode, stdin, input_raw, input_normalized, input_fingerprint, expected_output, category, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "seed", 1)
                ON DUPLICATE KEY UPDATE
                    testcode = VALUES(testcode),
                    stdin = VALUES(stdin),
                    input_raw = VALUES(input_raw),
                    input_normalized = VALUES(input_normalized),
                    expected_output = VALUES(expected_output),
                    is_active = 1'
            );
            $mid = (int) $t->id;
            $stmt->bind_param(
                'iiiissssss',
                $courseid,
                $quizid,
                $questionid,
                $mid,
                $testcode,
                $stdin,
                $rawinput,
                $normalized,
                $fingerprint,
                $expected
            );
            $stmt->execute();
            if ($stmt->affected_rows > 0) {
                $synced++;
            }
            $stmt->close();
        }

        return $synced;
    }

    /**
     * Return rewards for a user (unclaimed and claimed).
     *
     * @param int $courseid Course ID.
     * @param int $userid User ID.
     * @return array Rewards with masked input/output if unclaimed.
     */
    public function rewards_for_user(int $courseid, int $userid): array {
        $stmt = $this->connection->prepare(
            'SELECT r.id, r.course_id, r.quiz_id, r.question_id, r.receiver_user_id, r.contribution_id,
                    r.reward_source, r.seed_id, r.is_claimed, r.claimed_at, r.created_at,
                    COALESCE(c.input_normalized, s.input_normalized, "") AS input_normalized,
                    COALESCE(c.oracle_output, s.expected_output, "") AS oracle_output,
                    COALESCE(c.category, s.category, "seed") AS category
               FROM testcase_rewards r
          LEFT JOIN testcase_contributions c ON (r.reward_source = "contribution" AND c.id = r.contribution_id)
          LEFT JOIN question_seed_testcases s ON (r.reward_source = "seed" AND s.id = r.seed_id)
              WHERE r.course_id = ? AND r.receiver_user_id = ?
           ORDER BY r.id DESC LIMIT 100'
        );
        $stmt->bind_param('ii', $courseid, $userid);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Process claim state for display
        foreach ($rows as &$row) {
            $row['is_claimed_bool'] = ((int) $row['is_claimed'] === 1);
            if (!$row['is_claimed_bool']) {
                $row['display_input'] = '🔒 [Nhấn "Nhận thưởng" để xem nội dung kiểm thử]';
                $row['display_oracle'] = '🔒 [Ẩn cho đến khi bạn bấm nhận]';
            } else {
                $row['display_input'] = $row['input_normalized'];
                $row['display_oracle'] = $row['oracle_output'];
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * Claim a pending reward to reveal its testcase and record precise viewed_at timestamp.
     *
     * @param int $rewardid Reward ID.
     * @param int $userid User ID.
     * @param int $courseid Course ID.
     * @return bool True if claimed, false if invalid.
     */
    public function claim_reward(int $rewardid, int $userid, int $courseid): bool {
        $stmt = $this->connection->prepare(
            'SELECT * FROM testcase_rewards WHERE id = ? AND receiver_user_id = ? AND course_id = ?'
        );
        $stmt->bind_param('iii', $rewardid, $userid, $courseid);
        $stmt->execute();
        $reward = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$reward) {
            return false;
        }

        if ((int) $reward['is_claimed'] === 1) {
            return true;
        }

        $now = date('Y-m-d H:i:s');
        $upd = $this->connection->prepare(
            'UPDATE testcase_rewards SET is_claimed = 1, claimed_at = ? WHERE id = ?'
        );
        $upd->bind_param('si', $now, $rewardid);
        $upd->execute();
        $upd->close();

        // Log view event
        $log = $this->connection->prepare(
            'INSERT INTO testcase_view_logs
            (course_id, quiz_id, question_id, user_id, reward_id, contribution_id, action, created_at)
            VALUES (?, ?, ?, ?, ?, ?, "claim_reward", ?)'
        );
        $cid = (int) $reward['course_id'];
        $qid = (int) $reward['quiz_id'];
        $quid = (int) $reward['question_id'];
        $contribid = (int) ($reward['contribution_id'] ?? 0);
        $log->bind_param('iiiiiss', $cid, $qid, $quid, $userid, $rewardid, $contribid, $now);
        $log->execute();
        $log->close();

        return true;
    }

    /**
     * Return course leaderboard sorted by: total_stars DESC, approved_count DESC, earliest_submitted_at ASC.
     *
     * @param int $courseid Course ID.
     * @param int $currentuserid Current user ID.
     * @return array{leaderboard:array, my_rank:int, my_stars:int, my_count:int, top_user_name:string, top_stars:int, top_count:int}
     */
    public function course_leaderboard(int $courseid, int $currentuserid): array {
        global $DB;

        $sql = "
            SELECT user_id,
                   COALESCE(SUM(COALESCE(rating, 0)), 0) AS total_stars,
                   COUNT(id) AS approved_count,
                   MIN(submitted_at) AS first_submitted_at
              FROM testcase_contributions
             WHERE course_id = ? AND status = 'approved'
             GROUP BY user_id
             ORDER BY total_stars DESC, approved_count DESC, first_submitted_at ASC, user_id ASC
        ";
        $stmt = $this->connection->prepare($sql);
        $stmt->bind_param('i', $courseid);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        $leaderboard = [];
        $rank = 1;
        $myrank = 0;
        $mystars = 0;
        $mycount = 0;

        foreach ($res as $row) {
            $uid = (int) $row['user_id'];
            $stars = (int) $row['total_stars'];
            $count = (int) $row['approved_count'];

            $userobj = $DB->get_record('user', ['id' => $uid], 'id, firstname, lastname, username');
            $fullname = $userobj ? fullname($userobj) : ('Sinh viên #' . $uid);

            $iscurrent = ($uid === $currentuserid);
            if ($iscurrent) {
                $myrank = $rank;
                $mystars = $stars;
                $mycount = $count;
            }

            $starsdisplay = '';
            for ($s = 0; $s < min($stars, 20); $s++) {
                $starsdisplay .= '⭐';
            }

            $leaderboard[] = [
                'rank' => $rank,
                'user_id' => $uid,
                'fullname' => $fullname,
                'username' => $userobj ? $userobj->username : '',
                'total_stars' => $stars,
                'stars_display' => $starsdisplay,
                'approved_count' => $count,
                'first_submitted_at' => $row['first_submitted_at'],
                'is_current_user' => $iscurrent,
                'badge_class' => ($rank === 1 ? 'badge-warning text-dark font-weight-bold' :
                                 ($rank === 2 ? 'badge-secondary' :
                                 ($rank === 3 ? 'badge-info' : 'badge-light text-muted'))),
                'medal' => ($rank === 1 ? '🥇' : ($rank === 2 ? '🥈' : ($rank === 3 ? '🥉' : ('#' . $rank)))),
            ];
            $rank++;
        }

        $top = $leaderboard[0] ?? null;

        return [
            'leaderboard' => $leaderboard,
            'my_rank' => $myrank,
            'my_stars' => $mystars,
            'my_count' => $mycount,
            'top_user_name' => $top ? $top['fullname'] : '',
            'top_stars' => $top ? $top['total_stars'] : 0,
            'top_count' => $top ? $top['approved_count'] : 0,
            'has_leaderboard' => !empty($leaderboard),
        ];
    }

    /**
     * Grant an idempotent reward after approval, avoiding duplicates across contributions and seeds.
     *
     * @param int $contributionid Approved contribution ID.
     */
    private function grant_reward_for_contribution(int $contributionid): void {
        $contribution = $this->connection->query(
            'SELECT * FROM testcase_contributions WHERE id = ' . (int) $contributionid
        )->fetch_assoc();
        if (!$contribution) {
            return;
        }
        $settings = $this->quiz_settings((int) $contribution['quiz_id']);
        if ($settings['reward_policy'] !== 'one_for_one') {
            return;
        }
        $courseid = (int) $contribution['course_id'];
        $quizid = (int) $contribution['quiz_id'];
        $questionid = (int) $contribution['question_id'];
        $ownerid = (int) $contribution['user_id'];

        // Find candidate from approved peer contributions (prioritize higher star rating)
        $stmt = $this->connection->prepare(
            "SELECT c.id, c.input_fingerprint, 'contribution' AS source
               FROM testcase_contributions c
              WHERE c.course_id = ? AND c.quiz_id = ? AND c.question_id = ?
                AND c.status = 'approved' AND c.user_id <> ?
                AND NOT EXISTS (
                    SELECT 1 FROM testcase_rewards r
                     WHERE r.receiver_user_id = ? AND r.course_id = ? AND r.question_id = ?
                       AND r.contribution_id = c.id
                )
                AND NOT EXISTS (
                    SELECT 1 FROM testcase_contributions own
                     WHERE own.user_id = ? AND own.course_id = ? AND own.question_id = ?
                       AND own.input_fingerprint = c.input_fingerprint
                )
              ORDER BY COALESCE(c.rating, 0) DESC, RAND() LIMIT 1"
        );
        $stmt->bind_param(
            'iiiiiiiiii',
            $courseid, $quizid, $questionid, $ownerid,
            $ownerid, $courseid, $questionid,
            $ownerid, $courseid, $questionid
        );
        $stmt->execute();
        $candidate = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // If no peer contribution available, fallback to seed testcase bank (Cold-start guardrail)
        if (!$candidate) {
            $stmt = $this->connection->prepare(
                "SELECT s.id, s.input_fingerprint, 'seed' AS source
                   FROM question_seed_testcases s
                  WHERE s.course_id = ? AND s.quiz_id = ? AND s.question_id = ? AND s.is_active = 1
                    AND NOT EXISTS (
                        SELECT 1 FROM testcase_rewards r
                         WHERE r.receiver_user_id = ? AND r.course_id = ? AND r.question_id = ?
                           AND r.reward_source = 'seed' AND r.seed_id = s.id
                    )
                    AND NOT EXISTS (
                        SELECT 1 FROM testcase_contributions own
                         WHERE own.user_id = ? AND own.course_id = ? AND own.question_id = ?
                           AND own.input_fingerprint = s.input_fingerprint
                    )
                  ORDER BY RAND() LIMIT 1"
            );
            $stmt->bind_param(
                'iiiiiiiiii',
                $courseid, $quizid, $questionid,
                $ownerid, $courseid, $questionid,
                $ownerid, $courseid, $questionid
            );
            $stmt->execute();
            $candidate = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }

        if (!$candidate) {
            return;
        }

        $source = (string) $candidate['source'];
        $targetid = (int) $candidate['id'];
        $contribid = ($source === 'contribution') ? $targetid : 0;
        $seedid = ($source === 'seed') ? $targetid : null;

        $insert = $this->connection->prepare(
            'INSERT IGNORE INTO testcase_rewards
            (course_id, quiz_id, question_id, receiver_user_id, contribution_id, reward_source, seed_id, is_claimed, claimed_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, 0, NULL)'
        );
        $insert->bind_param(
            'iiiiisi',
            $courseid,
            $quizid,
            $questionid,
            $ownerid,
            $contribid,
            $source,
            $seedid
        );
        $insert->execute();
        $insert->close();
    }

    /**
     * Insert an immutable review audit row.
     *
     * @param int $contributionid Contribution ID.
     * @param int $reviewerid Reviewer user ID.
     * @param string $from Previous status.
     * @param string $to New status.
     * @param string $note Review note.
     * @param ?int $rating Rating 1-5.
     * @param string $ratingreason Rating reason.
     */
    private function insert_review(
        int $contributionid,
        int $reviewerid,
        string $from,
        string $to,
        string $note,
        ?int $rating = null,
        string $ratingreason = ''
    ): void {
        $audit = $this->connection->prepare(
            'INSERT INTO contribution_reviews
            (contribution_id, reviewer_user_id, from_status, to_status, review_note, rating, rating_reason)
            VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $audit->bind_param('iisssis', $contributionid, $reviewerid, $from, $to, $note, $rating, $ratingreason);
        $audit->execute();
        $audit->close();
    }

    /**
     * Return summary statistics for all courses in testcase_contributions.
     *
     * @return array<int, array{course_id:int, total_count:int, pending_count:int, approved_count:int, rejected_count:int, duplicate_count:int, student_count:int}>
     */
    public function courses_summary(): array {
        $result = $this->connection->query(
            "SELECT course_id,
                    COUNT(*) AS total_count,
                    SUM(CASE WHEN status IN ('submitted', 'needs_explanation') THEN 1 ELSE 0 END) AS pending_count,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved_count,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected_count,
                    SUM(CASE WHEN status = 'duplicate' THEN 1 ELSE 0 END) AS duplicate_count,
                    COUNT(DISTINCT user_id) AS student_count
               FROM testcase_contributions
              GROUP BY course_id"
        );
        if (!$result) {
            return [];
        }
        $summary = [];
        while ($row = $result->fetch_assoc()) {
            $summary[(int) $row['course_id']] = [
                'course_id' => (int) $row['course_id'],
                'total_count' => (int) $row['total_count'],
                'pending_count' => (int) $row['pending_count'],
                'approved_count' => (int) $row['approved_count'],
                'rejected_count' => (int) $row['rejected_count'],
                'duplicate_count' => (int) $row['duplicate_count'],
                'student_count' => (int) $row['student_count'],
            ];
        }
        return $summary;
    }
}

