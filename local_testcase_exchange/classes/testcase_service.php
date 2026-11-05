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

defined('MOODLE_INTERNAL') || die();

/**
 * Application service for private runs, contributions, review and rewards.
 */
final class testcase_service {
    /** @var db_gateway Database access gateway. */
    private db_gateway $db;

    /**
     * Create the workflow service.
     *
     * @param mixed $db Optional db_gateway, moodle_database or null.
     */
    public function __construct($db = null) {
        if ($db instanceof db_gateway) {
            $this->db = $db;
        } else if ($db instanceof \moodle_database) {
            $this->db = new db_gateway($db);
        } else {
            $this->db = new db_gateway();
        }
    }

    /**
     * Unescape literal \n or \r\n characters if a string was flattened without real newlines.
     *
     * @param string $text Source text.
     * @return string Sanitized text with real newlines.
     */
    public static function sanitize_multiline_text(string $text): string {
        if (strpos($text, "\n") === false && strpos($text, "\\n") !== false) {
            return str_replace(["\\r\\n", "\\n", "\\r"], "\n", $text);
        }
        return $text;
    }

    /**
     * Return settings for a Quiz.
     *
     * @param int $quizid Quiz ID.
     * @return array Quiz settings.
     */
    public function quiz_settings(int $quizid): array {
        $row = $this->db->fetch_row('SELECT * FROM {local_tce_quiz_settings} WHERE quiz_id = ?', [$quizid]);
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
        $row = $this->db->fetch_row(
            'SELECT * FROM {local_tce_policies} WHERE quiz_id = ? AND question_id = ?',
            [$quizid, $questionid]
        );
        if ($row) {
            return $row;
        }

        // Auto-detect default input_mode based on whether question testcases utilize stdin.
        $detectedmode = 'testcode';
        try {
            global $DB;
            $hasstdin = $DB->record_exists_select(
                'question_coderunner_tests',
                'questionid = ? AND stdin IS NOT NULL AND stdin <> \'\'',
                [$questionid]
            );
            if ($hasstdin) {
                $detectedmode = 'stdin';
            }
        } catch (\Throwable $e) {
            // Fallback to testcode.
        }

        return [
            'question_id' => $questionid,
            'quiz_id' => $quizid,
            'input_mode' => $detectedmode,
            'testcode_template' => '',
            'normalization_mode' => 'trim',
            'categories' => "edge_case\nrandom\nperformance\nsyntax",
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
        $studentsource = self::sanitize_multiline_text($studentsource);
        $input = self::sanitize_multiline_text($input);
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
        $sourcehash = hash('sha256', $studentsource);
        $rawinputhash = hash('sha256', $input);
        $qid = (int) $questioncontext->questionid;

        // Tải context để lấy oracle_version_hash và sample testcases
        $execcontext = execution_service::resolve_context($qid, $policy);
        $oracleversionhash = (string) $execcontext['oracle_version_hash'];

        // Kiểm tra chống spam & giảm tải: Khóa tra cứu bao quát input thô, mã sinh viên và phiên bản oracle
        $existingrun = $this->db->fetch_row(
            'SELECT id, student_run_output, oracle_output, student_outcome, oracle_outcome,
                    predicted_output, jobe_server, timecreated, code_revision_id
               FROM {local_tce_runs}
              WHERE user_id = ?
                AND question_id = ?
                AND input_raw_hash = ?
                AND student_source_hash = ?
                AND oracle_version_hash = ?
              ORDER BY id DESC',
            [$userid, $qid, $rawinputhash, $sourcehash, $oracleversionhash]
        );

        if ($existingrun) {
            $issameprediction = (trim((string) $existingrun['predicted_output']) === trim((string) $predicted));
            if ($issameprediction) {
                // Toàn bộ code, input và expected đều giống hệt -> Chặn hoàn toàn và trả lại lượt chạy cũ
                return [
                    'id' => (int) $existingrun['id'],
                    'is_duplicate_run' => true,
                    'student' => [
                        'outcome' => $existingrun['student_outcome'],
                        'output' => $existingrun['student_run_output'],
                        'server' => $existingrun['jobe_server'],
                    ],
                    'oracle' => [
                        'outcome' => $existingrun['oracle_outcome'],
                        'output' => $existingrun['oracle_output'],
                        'server' => $existingrun['jobe_server'],
                    ],
                    'input' => $input,
                    'normalized' => $normalized,
                    'fingerprint' => $fingerprint,
                    'code_revision_id' => $existingrun['code_revision_id'],
                ];
            }

            // Sinh viên điều chỉnh Expected Output: Tái sử dụng kết quả Jobe cũ nhưng ghi nhận dự đoán mới
            $cachedresults = [
                'student' => [
                    'outcome' => $existingrun['student_outcome'],
                    'output' => $existingrun['student_run_output'],
                    'server' => 'cached',
                ],
                'oracle' => [
                    'outcome' => $existingrun['oracle_outcome'],
                    'output' => $existingrun['oracle_output'],
                    'server' => 'cached',
                ],
                'oracle_solution' => (string) ($execcontext['oracle_solution'] ?? ''),
                'template' => (string) ($execcontext['template'] ?? ''),
                'question_version' => (int) ($execcontext['question_version'] ?? 1),
                'grader_type' => (string) ($execcontext['grader_type'] ?? 'EqualityGrader'),
                'oracle_version_hash' => $oracleversionhash,
                'test_suite_version_hash' => (string) ($execcontext['test_suite_version_hash'] ?? ''),
                'execution_limits' => (string) ($execcontext['execution_limits'] ?? ''),
            ];
            $server = 'cached';
            $runtimecontext = json_encode([
                'coderunnertype' => (string) ($questioncontext->coderunnertype ?? ''),
                'input_mode' => (string) ($policy['input_mode'] ?? 'testcode'),
                'normalization_mode' => (string) ($policy['normalization_mode'] ?? 'trim'),
                'jobe_server' => 'cached',
                'prediction_updated' => true,
                'timestamp' => date('c'),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return $this->persist_run_record(
                $courseid, $quiz, $questioncontext, $userid,
                $studentsource, $input, $normalized, $fingerprint, $rawinputhash, $sourcehash,
                $predicted, $purpose, $category, $reflection,
                $cachedresults, $server, $runtimecontext
            );
        }

        // Tra cứu bộ nhớ đệm kết quả Solution: Khóa cache phải khớp input thô và phiên bản Lời giải mẫu
        $cachedoracle = null;
        $oraclerow = $this->db->fetch_row(
            'SELECT oracle_output, oracle_outcome, jobe_server 
               FROM {local_tce_runs} 
              WHERE question_id = ? AND input_raw_hash = ? AND oracle_version_hash = ? AND oracle_outcome = \'success\' 
              ORDER BY id DESC',
            [$qid, $rawinputhash, $oracleversionhash]
        );
        if ($oraclerow && trim((string)$oraclerow['oracle_output']) !== '') {
            $cachedoracle = [
                'outcome' => $oraclerow['oracle_outcome'],
                'output' => $oraclerow['oracle_output'],
                'server' => (string) ($oraclerow['jobe_server'] ?? 'cached_oracle'),
                'error' => '',
            ];
        }

        $results = execution_service::run_pair($qid, $studentsource, $input, $policy, $quiz, $cachedoracle);
        $server = $results['student']['server'] ?: ($results['oracle']['server'] ?: 'jobe1');
        $runtimecontext = json_encode([
            'coderunnertype' => (string) ($questioncontext->coderunnertype ?? ''),
            'input_mode' => (string) ($policy['input_mode'] ?? 'testcode'),
            'normalization_mode' => (string) ($policy['normalization_mode'] ?? 'trim'),
            'jobe_server' => $server,
            'timestamp' => date('c'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $this->persist_run_record(
            $courseid, $quiz, $questioncontext, $userid,
            $studentsource, $input, $normalized, $fingerprint, $rawinputhash, $sourcehash,
            $predicted, $purpose, $category, $reflection,
            $results, $server, $runtimecontext
        );
    }

    /**
     * Persist run execution record to the local_tce_runs table.
     */
    private function persist_run_record(
        int $courseid,
        \stdClass $quiz,
        \stdClass $questioncontext,
        int $userid,
        string $studentsource,
        string $input,
        string $normalized,
        string $fingerprint,
        string $rawinputhash,
        string $sourcehash,
        string $predicted,
        string $purpose,
        string $category,
        string $reflection,
        array $results,
        string $server,
        string $runtimecontext
    ): array {
        $quizid = (int) $quiz->id;
        $qid = (int) $questioncontext->questionid;

        // Auto-track or link to student's code revision
        $revinfo = $this->record_or_get_code_revision($courseid, $quizid, $qid, $userid, $studentsource);
        $coderevid = $revinfo['id'];

        $runid = $this->db->insert('local_tce_runs', [
            'course_id' => $courseid,
            'quiz_id' => $quizid,
            'question_id' => $qid,
            'user_id' => $userid,
            'code_revision_id' => $coderevid,
            'quiz_attempt_id' => null,
            'question_attempt_id' => null,
            'input_raw' => $input,
            'input_normalized' => $normalized,
            'input_fingerprint' => $fingerprint,
            'input_raw_hash' => $rawinputhash,
            'predicted_output' => $predicted,
            'student_run_output' => $results['student']['output'],
            'oracle_output' => $results['oracle']['output'],
            'student_outcome' => $results['student']['outcome'],
            'oracle_outcome' => $results['oracle']['outcome'],
            'purpose' => $purpose,
            'category' => $category,
            'reflection' => $reflection,
            'jobe_server' => $server,
            'student_source_hash' => $sourcehash,
            'student_code_snapshot' => $studentsource,
            'oracle_solution_snapshot' => (string) ($results['oracle_solution'] ?? ''),
            'template_snapshot' => (string) ($results['template'] ?? ''),
            'runtime_context' => $runtimecontext,
            'question_version' => (int) ($results['question_version'] ?? 1),
            'grader_type' => (string) ($results['grader_type'] ?? 'EqualityGrader'),
            'oracle_version_hash' => (string) ($results['oracle_version_hash'] ?? ''),
            'test_suite_version_hash' => (string) ($results['test_suite_version_hash'] ?? ''),
            'execution_limits' => (string) ($results['execution_limits'] ?? ''),
            'timecreated' => time(),
        ]);

        return [
            'id' => $runid,
            'is_duplicate_run' => false,
            'student' => $results['student'],
            'oracle' => $results['oracle'],
            'input' => $input,
            'normalized' => $normalized,
            'fingerprint' => $fingerprint,
            'code_revision_id' => $coderevid,
        ];
    }

    /**
     * Submit a private run as a public contribution candidate.
     */
    public function submit_contribution(int $runid, int $userid): array {
        $run = $this->owned_run($runid, $userid);
        $existing = $this->db->fetch_row(
            'SELECT id, status FROM {local_tce_contributions} WHERE run_id = ?',
            [$runid]
        );
        if ($existing) {
            return ['id' => (int) $existing['id'], 'status' => $existing['status'], 'is_own_duplicate' => false];
        }

        // Check if user already contributed this exact testcase fingerprint for this question from another run
        $existingfp = $this->db->fetch_row(
            'SELECT id, status FROM {local_tce_contributions} 
              WHERE user_id = ? AND question_id = ? AND input_fingerprint = ? 
           ORDER BY id ASC',
            [$userid, $run['question_id'], $run['input_fingerprint']]
        );
        if ($existingfp) {
            return [
                'id' => (int) $existingfp['id'],
                'status' => $existingfp['status'],
                'is_own_duplicate' => true,
                'canonical_id' => (int) $existingfp['id'],
            ];
        }

        // Check if testcase is already in the teacher seed bank
        $seedexists = $this->db->fetch_row(
            'SELECT id FROM {local_tce_seeds} 
              WHERE course_id = ? AND question_id = ? AND input_fingerprint = ? AND is_active = 1',
            [$run['course_id'], $run['question_id'], $run['input_fingerprint']]
        );
        if ($seedexists) {
            throw new \moodle_exception('cannotcontributeseedtestcase', 'local_testcase_exchange', '',
                'Testcase này đã thuộc bộ đề thi hoặc ngân hàng mẫu của giáo viên, không thể đề xuất đóng góp!');
        }

        if ($run['oracle_outcome'] !== 'success') {
            throw new \moodle_exception('oraclenotsuccessful', 'local_testcase_exchange');
        }
        if (trim((string) $run['predicted_output']) === '' ||
            trim((string) $run['predicted_output']) !== trim((string) $run['oracle_output'])) {
            throw new \moodle_exception('predictionmismatchoracle', 'local_testcase_exchange', '',
                'Kết quả kỳ vọng (Expected Output) không khớp với Solution của Thầy/Cô nên không thể đề xuất đóng góp!');
        }
        if ($run['student_outcome'] !== 'success' ||
            trim((string) $run['student_run_output']) !== trim((string) $run['oracle_output'])) {
            throw new \moodle_exception('studentcodenotpassing', 'local_testcase_exchange', '',
                'Kết quả mong đợi của bạn đã được xác nhận. Chương trình hiện tại chưa vượt qua testcase này. Hãy sửa chương trình và chạy lại để đủ điều kiện đóng góp.');
        }

        $settings = $this->quiz_settings((int) $run['quiz_id']);
        $tx = $this->db->start_transaction();
        try {
            $status = 'submitted';
            $runpk = (int) $run['id'];
            $courseid = (int) $run['course_id'];
            $quizid = (int) $run['quiz_id'];
            $questionid = (int) $run['question_id'];
            $fingerprint = (string) $run['input_fingerprint'];
            $timenow = time();

            // Idempotency check: if student already submitted this testcase, return existing contribution directly
            $existingcontrib = $this->db->fetch_row(
                'SELECT id, status, duplicate_of FROM {local_tce_contributions}
                  WHERE quiz_id = ? AND question_id = ? AND user_id = ? AND input_fingerprint = ?
               ORDER BY id ASC',
                [$quizid, $questionid, $userid, $fingerprint]
            );
            if ($existingcontrib) {
                $canonical_id = !empty($existingcontrib['duplicate_of']) ? (int) $existingcontrib['duplicate_of'] : (int) $existingcontrib['id'];
                $tx->allow_commit();
                return [
                    'id' => (int) $existingcontrib['id'],
                    'status' => (string) $existingcontrib['status'],
                    'is_own_duplicate' => true,
                    'canonical_id' => $canonical_id,
                ];
            }

            $predicted_val = isset($run['predicted_output']) ? (string) $run['predicted_output'] : '';
            $contributionid = $this->db->insert('local_tce_contributions', [
                'run_id' => $runpk,
                'course_id' => $courseid,
                'quiz_id' => $quizid,
                'question_id' => $questionid,
                'user_id' => $userid,
                'input_normalized' => (string) $run['input_normalized'],
                'input_fingerprint' => $fingerprint,
                'expected_output' => $predicted_val,
                'oracle_output' => (string) $run['oracle_output'],
                'purpose' => (string) $run['purpose'],
                'category' => (string) $run['category'],
                'status' => $status,
                'timecreated' => $timenow,
                'timemodified' => $timenow,
            ]);

            // Attempt to claim canonical key
            $isduplicate = false;
            $canonicalrec = $this->db->fetch_row(
                'SELECT canonical_contribution_id FROM {local_tce_canonical}
                  WHERE course_id = ? AND quiz_id = ? AND question_id = ? AND input_fingerprint = ?',
                [$courseid, $quizid, $questionid, $fingerprint]
            );

            if ($canonicalrec) {
                $isduplicate = true;
                $canonical = (int) $canonicalrec['canonical_contribution_id'];
            } else {
                try {
                    $this->db->insert('local_tce_canonical', [
                        'course_id' => $courseid,
                        'quiz_id' => $quizid,
                        'question_id' => $questionid,
                        'input_fingerprint' => $fingerprint,
                        'canonical_contribution_id' => $contributionid,
                        'timecreated' => $timenow,
                    ]);
                    $canonical = $contributionid;
                } catch (\Throwable $e) {
                    $isduplicate = true;
                    $canonical = $this->canonical_id($run);
                }
            }

            $isownduplicate = false;
            if ($isduplicate) {
                $status = 'duplicate';
                $ownerrow = $this->db->fetch_row(
                    'SELECT user_id FROM {local_tce_contributions} WHERE id = ?',
                    [$canonical]
                );
                $canonicaluser = (int) ($ownerrow['user_id'] ?? 0);
                $isownduplicate = ($canonicaluser === $userid);

                $this->db->execute(
                    'UPDATE {local_tce_contributions} SET status = ?, duplicate_of = ?, timemodified = ? WHERE id = ?',
                    [$status, $canonical, $timenow, $contributionid]
                );

                $note = $isownduplicate ? 'Duplicate of own contribution #' . $canonical : 'Exact fingerprint duplicate';
                $this->insert_review($contributionid, 0, 'submitted', 'duplicate', $note);
            } else if ($settings['review_mode'] === 'auto') {
                $status = 'approved';
                $this->db->execute(
                    'UPDATE {local_tce_contributions} SET status = ?, timemodified = ? WHERE id = ?',
                    [$status, $timenow, $contributionid]
                );
                $this->insert_review($contributionid, 0, 'submitted', 'approved', 'Automatically approved by Quiz policy');
                $this->grant_reward_for_contribution($contributionid);
            }

            $tx->allow_commit();
            return [
                'id' => $contributionid,
                'status' => $status,
                'is_own_duplicate' => $isownduplicate,
                'canonical_id' => $canonical,
            ];
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    /**
     * Apply a review transition and audit it.
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

        $tx = $this->db->start_transaction();
        try {
            $row = $this->db->fetch_row(
                'SELECT status, rating, review_comment FROM {local_tce_contributions} WHERE id = ? AND course_id = ? FOR UPDATE',
                [$contributionid, $courseid]
            );
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
            $timenow = time();

            $this->db->execute(
                'UPDATE {local_tce_contributions} SET status = ?, rating = ?, review_comment = ?, timemodified = ? WHERE id = ?',
                [$targetstatus, $finalrating, $finalcomment, $timenow, $contributionid]
            );

            $this->insert_review($contributionid, $reviewerid, $from, $targetstatus, $note, $finalrating, $ratingreason);
            if ($targetstatus === 'approved' && $from !== 'approved') {
                $this->grant_reward_for_contribution($contributionid);
            }
            $tx->allow_commit();
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    /**
     * Add an explanation and resubmit a contribution.
     */
    public function resubmit_explanation(int $contributionid, int $userid, string $explanation): void {
        if (trim($explanation) === '') {
            throw new \moodle_exception('explanationrequired', 'local_testcase_exchange');
        }
        $tx = $this->db->start_transaction();
        try {
            $row = $this->db->fetch_row(
                "SELECT status, purpose FROM {local_tce_contributions} WHERE id = ? AND user_id = ?",
                [$contributionid, $userid]
            );
            if (!$row || $row['status'] !== 'needs_explanation') {
                throw new \moodle_exception('invalidtransition', 'local_testcase_exchange');
            }
            $status = 'submitted';
            $newpurpose = ($row['purpose'] ?? '') . "\nExplanation: " . $explanation;
            $timenow = time();

            $this->db->execute(
                "UPDATE {local_tce_contributions} SET status = ?, purpose = ?, timemodified = ? WHERE id = ?",
                [$status, $newpurpose, $timenow, $contributionid]
            );
            $this->insert_review(
                $contributionid,
                $userid,
                'needs_explanation',
                'submitted',
                $explanation
            );
            $tx->allow_commit();
        } catch (\Throwable $e) {
            throw $e;
        }
    }

    /**
     * Save Quiz-level policy settings.
     */
    public function save_quiz_settings(int $quizid, int $userid, array $values): void {
        $enabled = !empty($values['enabled']) ? 1 : 0;
        $showoracle = !empty($values['show_oracle_output']) ? 1 : 0;
        $leaderboard = !empty($values['leaderboard_enabled']) ? 1 : 0;
        $reviewmode = in_array($values['review_mode'], ['auto', 'teacher'], true) ? $values['review_mode'] : 'teacher';
        $rewardpolicy = in_array($values['reward_policy'], ['disabled', 'one_for_one'], true) ?
            $values['reward_policy'] : 'one_for_one';
        $maxruns = max(1, min(120, (int) ($values['max_runs_per_minute'] ?? 10)));
        $maxbytes = max(64, min(1048576, (int) ($values['max_input_bytes'] ?? 8192)));
        $timenow = time();

        $existing = $this->db->fetch_row('SELECT id FROM {local_tce_quiz_settings} WHERE quiz_id = ?', [$quizid]);
        if ($existing) {
            $this->db->update('local_tce_quiz_settings', [
                'id' => (int) $existing['id'],
                'quiz_id' => $quizid,
                'is_enabled' => $enabled,
                'enabled' => $enabled,
                'review_mode' => $reviewmode,
                'reward_policy' => $rewardpolicy,
                'show_oracle_output' => $showoracle,
                'leaderboard_enabled' => $leaderboard,
                'max_runs_per_minute' => $maxruns,
                'max_input_bytes' => $maxbytes,
                'updated_by' => $userid,
                'timemodified' => $timenow,
            ]);
        } else {
            $this->db->insert('local_tce_quiz_settings', [
                'quiz_id' => $quizid,
                'is_enabled' => $enabled,
                'enabled' => $enabled,
                'review_mode' => $reviewmode,
                'reward_policy' => $rewardpolicy,
                'show_oracle_output' => $showoracle,
                'leaderboard_enabled' => $leaderboard,
                'max_runs_per_minute' => $maxruns,
                'max_input_bytes' => $maxbytes,
                'updated_by' => $userid,
                'timecreated' => $timenow,
                'timemodified' => $timenow,
            ]);
        }
    }

    /**
     * Save question-level execution policy.
     */
    public function save_question_policy(int $quizid, int $questionid, int $userid, array $values): void {
        $curr = $this->db->fetch_row(
            'SELECT id, input_mode, testcode_template, normalization_mode, categories FROM {local_tce_policies} WHERE quiz_id = ? AND question_id = ?',
            [$quizid, $questionid]
        );

        $mode_val = array_key_exists('input_mode', $values) ? $values['input_mode'] : ($curr['input_mode'] ?? 'testcode');
        $mode = in_array($mode_val, ['stdin', 'testcode', 'template'], true) ? $mode_val : 'testcode';

        $norm_val = array_key_exists('normalization_mode', $values) ? $values['normalization_mode'] : ($curr['normalization_mode'] ?? 'trim');
        $normalization = in_array($norm_val, ['raw', 'trim', 'json'], true) ? $norm_val : 'trim';

        $template = array_key_exists('testcode_template', $values) ? (string) $values['testcode_template'] : (string) ($curr['testcode_template'] ?? '');
        $categories = array_key_exists('categories', $values) ? (string) $values['categories'] : (string) ($curr['categories'] ?? '');
        $timenow = time();

        if ($curr) {
            $this->db->update('local_tce_policies', [
                'id' => (int) $curr['id'],
                'quiz_id' => $quizid,
                'question_id' => $questionid,
                'input_mode' => $mode,
                'testcode_template' => $template,
                'normalization_mode' => $normalization,
                'categories' => $categories,
                'updated_by' => $userid,
                'timemodified' => $timenow,
            ]);
        } else {
            $this->db->insert('local_tce_policies', [
                'quiz_id' => $quizid,
                'question_id' => $questionid,
                'input_mode' => $mode,
                'testcode_template' => $template,
                'normalization_mode' => $normalization,
                'categories' => $categories,
                'updated_by' => $userid,
                'timecreated' => $timenow,
                'timemodified' => $timenow,
            ]);
        }
    }

    /**
     * Return private runs for a user, enriched with code revision step numbers.
     */
    public function runs_for_user(int $courseid, int $userid): array {
        return $this->db->fetch_all(
            'SELECT r.*,
                    rev.step_number AS revision_step,
                    rev.timecreated AS revision_created_at
               FROM {local_tce_runs} r
          LEFT JOIN {local_tce_revisions} rev ON rev.id = r.code_revision_id
              WHERE r.course_id = ? AND r.user_id = ?
           ORDER BY r.id DESC',
            [$courseid, $userid]
        );
    }

    /**
     * Record a new code revision if submitted code differs from preceding revision.
     */
    public function record_or_get_code_revision(
        int $courseid,
        int $quizid,
        int $questionid,
        int $userid,
        string $codecontent
    ): array {
        $trimmed = trim(self::sanitize_multiline_text($codecontent));
        if ($trimmed === '') {
            return ['id' => null, 'step_number' => 0, 'is_new' => false];
        }

        $codehash = hash('sha256', $trimmed);

        $last = $this->db->fetch_row(
            'SELECT id, code_hash, code_content, step_number FROM {local_tce_revisions}
              WHERE course_id = ? AND quiz_id = ? AND question_id = ? AND user_id = ?
              ORDER BY step_number DESC, id DESC',
            [$courseid, $quizid, $questionid, $userid]
        );

        if ($last) {
            $lasthash = (string) ($last['code_hash'] ?? '');
            $lastcontent = trim(self::sanitize_multiline_text($last['code_content'] ?? ''));
            if ($lasthash === $codehash || $lastcontent === $trimmed) {
                return [
                    'id' => (int) $last['id'],
                    'step_number' => (int) $last['step_number'],
                    'is_new' => false,
                ];
            }
        }

        $newstep = $last ? ((int) $last['step_number'] + 1) : 1;
        $mode = 'playground';
        $timenow = time();

        $newid = $this->db->insert('local_tce_revisions', [
            'course_id' => $courseid,
            'quiz_id' => $quizid,
            'question_id' => $questionid,
            'user_id' => $userid,
            'step_number' => $newstep,
            'code_content' => $trimmed,
            'code_hash' => $codehash,
            'execution_mode' => $mode,
            'timecreated' => $timenow,
        ]);

        return [
            'id' => $newid,
            'step_number' => $newstep,
            'is_new' => true,
        ];
    }

    /**
     * Get the latest run context for prefilling the playground form.
     */
    public function get_latest_student_run_context(int $courseid, int $quizid, int $questionid, int $userid): ?array {
        $row = $this->db->fetch_row(
            'SELECT r.student_code_snapshot, r.input_raw, r.predicted_output, r.purpose, r.category,
                    rev.step_number AS revision_step, rev.code_content AS revision_code
               FROM {local_tce_runs} r
          LEFT JOIN {local_tce_revisions} rev ON rev.id = r.code_revision_id
              WHERE r.course_id = ? AND r.quiz_id = ? AND r.question_id = ? AND r.user_id = ?
              ORDER BY r.id DESC',
            [$courseid, $quizid, $questionid, $userid]
        );
        if ($row) {
            return $row;
        }

        return $this->db->fetch_row(
            'SELECT code_content AS revision_code, step_number AS revision_step
               FROM {local_tce_revisions}
              WHERE course_id = ? AND quiz_id = ? AND question_id = ? AND user_id = ?
              ORDER BY id DESC',
            [$courseid, $quizid, $questionid, $userid]
        );
    }

    /**
     * Get summary of code revisions and associated testcases for a user and question.
     *
     * @param int $courseid Course ID
     * @param int $quizid Quiz ID
     * @param int $questionid Question ID
     * @param int $userid User ID
     * @param int $limit Maximum number of latest revisions to return (default 3)
     * @return array
     */
    public function code_revisions_summary(int $courseid, int $quizid, int $questionid, int $userid, int $limit = 3): array {
        $rawrevisions = $this->db->fetch_all(
            'SELECT id, step_number, code_content, code_hash, timecreated
               FROM {local_tce_revisions}
              WHERE course_id = ? AND quiz_id = ? AND question_id = ? AND user_id = ?
              ORDER BY step_number DESC, id DESC',
            [$courseid, $quizid, $questionid, $userid]
        );

        if (empty($rawrevisions)) {
            return [];
        }

        // Deduplicate adjacent revisions that have identical code content/hash.
        $revisions = [];
        $prevCode = null;
        $prevHash = null;
        foreach ($rawrevisions as $rev) {
            $curCode = trim(self::sanitize_multiline_text($rev['code_content'] ?? ''));
            $curHash = (string) ($rev['code_hash'] ?? '');
            if ($curHash === '' && $curCode !== '') {
                $curHash = hash('sha256', $curCode);
            }
            if ($prevCode !== null && ($curCode === $prevCode || ($curHash !== '' && $curHash === $prevHash))) {
                continue; // Skip duplicate version that matches the preceding version.
            }
            $prevCode = $curCode;
            $prevHash = $curHash;
            $revisions[] = $rev;
        }

        if ($limit > 0 && count($revisions) > $limit) {
            $revisions = array_slice($revisions, 0, $limit);
        }

        $allruns = $this->db->fetch_all(
            'SELECT id, code_revision_id, input_raw, student_run_output, oracle_outcome,
                    student_outcome, timecreated
               FROM {local_tce_runs}
              WHERE course_id = ? AND quiz_id = ? AND question_id = ? AND user_id = ?
              ORDER BY id ASC',
            [$courseid, $quizid, $questionid, $userid]
        );

        $runsbyrevision = [];
        foreach ($allruns as $r) {
            $revid = (int) ($r['code_revision_id'] ?? 0);
            if ($revid > 0) {
                $runsbyrevision[$revid][] = $r;
            }
        }

        $result = [];
        foreach ($revisions as $idx => $rev) {
            $revid = (int) $rev['id'];
            $linkedruns = $runsbyrevision[$revid] ?? [];
            $runids = array_column($linkedruns, 'id');
            $testcaselabels = array_map(function($id) {
                return '#' . $id;
            }, $runids);

            $snippetlines = explode("\n", trim($rev['code_content']));
            $snippet = implode("\n", array_slice($snippetlines, 0, 3));
            if (count($snippetlines) > 3) {
                $snippet .= "\n...";
            }

            $createdtime = (int) $rev['timecreated'];
            $result[] = [
                'id' => $revid,
                'step_number' => (int) $rev['step_number'],
                'created_at' => $createdtime ? date('Y-m-d H:i:s', $createdtime) : '',
                'created_at_formatted' => $createdtime ? date('H:i:s - d/m/Y', $createdtime) : '',
                'code_content' => $rev['code_content'],
                'code_content_json' => json_encode($rev['code_content']),
                'code_snippet' => $snippet,
                'testcase_count' => count($linkedruns),
                'testcase_ids_string' => !empty($testcaselabels) ? implode(', ', $testcaselabels) : 'Chưa chạy testcase nào',
                'has_testcases' => !empty($linkedruns),
                'is_latest' => ($idx === 0),
            ];
        }

        return $result;
    }

    /**
     * Return contributions for a user.
     */
    public function contributions_for_user(int $courseid, int $userid): array {
        return $this->db->fetch_all(
            'SELECT c.*,
                    COALESCE(c.expected_output, r.predicted_output) AS expected_output,
                    r.predicted_output AS run_predicted_output,
                    r.student_run_output,
                    r.oracle_output AS run_oracle_output,
                    r.oracle_outcome,
                    (SELECT cr.review_note FROM {local_tce_reviews} cr
                      WHERE cr.id = (
                          SELECT MAX(cr2.id) FROM {local_tce_reviews} cr2
                           WHERE cr2.contribution_id = c.id AND cr2.review_note IS NOT NULL AND cr2.review_note <> \'\'
                      )) AS latest_review_note,
                    (SELECT cr.rating FROM {local_tce_reviews} cr
                      WHERE cr.id = (
                          SELECT MAX(cr3.id) FROM {local_tce_reviews} cr3
                           WHERE cr3.contribution_id = c.id AND cr3.rating IS NOT NULL AND cr3.rating > 0
                      )) AS latest_rating
               FROM {local_tce_contributions} c
          LEFT JOIN {local_tce_runs} r ON r.id = c.run_id
              WHERE c.course_id = ? AND c.user_id = ? ORDER BY c.id DESC',
            [$courseid, $userid]
        );
    }

    /**
     * Return contributions awaiting review.
     */
    public function pending_contributions(int $courseid): array {
        return $this->course_contributions($courseid, 'pending');
    }

    /**
     * Return contributions for a course with optional status, quiz, and question filters.
     */
    public function course_contributions(int $courseid, string $status = 'all', int $quizid = 0, int $questionid = 0): array {
        $conditions = ['c.course_id = ?'];
        $params = [$courseid];

        if ($status === 'pending') {
            $conditions[] = "c.status IN ('submitted', 'needs_explanation')";
        } else if ($status !== 'all' && in_array($status, ['submitted', 'approved', 'rejected', 'duplicate', 'needs_explanation', 'archived'], true)) {
            $conditions[] = 'c.status = ?';
            $params[] = $status;
        }

        if ($quizid > 0) {
            $conditions[] = 'c.quiz_id = ?';
            $params[] = $quizid;
        }

        if ($questionid > 0) {
            $conditions[] = 'c.question_id = ?';
            $params[] = $questionid;
        }

        $where = implode(' AND ', $conditions);
                $sql = "SELECT c.*,
                       COALESCE(c.expected_output, r.predicted_output) AS expected_output,
                       r.predicted_output AS run_predicted_output,
                       r.student_run_output,
                       r.oracle_output AS run_oracle_output,
                       r.oracle_outcome,
                       (SELECT cr.review_note FROM {local_tce_reviews} cr
                         WHERE cr.id = (
                             SELECT MAX(cr2.id) FROM {local_tce_reviews} cr2
                              WHERE cr2.contribution_id = c.id AND cr2.review_note IS NOT NULL AND cr2.review_note <> ''
                         )) AS latest_review_note,
                       (SELECT cr.rating FROM {local_tce_reviews} cr
                         WHERE cr.id = (
                             SELECT MAX(cr3.id) FROM {local_tce_reviews} cr3
                              WHERE cr3.contribution_id = c.id AND cr3.rating IS NOT NULL AND cr3.rating > 0
                         )) AS latest_rating
                  FROM {local_tce_contributions} c
             LEFT JOIN {local_tce_runs} r ON r.id = c.run_id
                 WHERE {$where}
              ORDER BY c.id DESC";
        return $this->db->fetch_all($sql, $params);
    }

    /**
     * Return contribution counts by status for a course with optional quiz/question filters.
     */
    public function contribution_counts(int $courseid, int $quizid = 0, int $questionid = 0): array {
        $conditions = ['course_id = ?'];
        $params = [$courseid];

        if ($quizid > 0) {
            $conditions[] = 'quiz_id = ?';
            $params[] = $quizid;
        }

        if ($questionid > 0) {
            $conditions[] = 'question_id = ?';
            $params[] = $questionid;
        }

        $where = implode(' AND ', $conditions);
        $sql = "SELECT status, COUNT(*) AS cnt FROM {local_tce_contributions} WHERE {$where} GROUP BY status";
        $rows = $this->db->fetch_all($sql, $params);

        $counts = [
            'all' => 0,
            'pending' => 0,
            'approved' => 0,
            'duplicate' => 0,
            'rejected' => 0,
            'needs_explanation' => 0,
            'submitted' => 0,
        ];

        foreach ($rows as $row) {
            $st = (string) $row['status'];
            $cnt = (int) $row['cnt'];
            $counts[$st] = $cnt;
            $counts['all'] += $cnt;
            if ($st === 'submitted' || $st === 'needs_explanation') {
                $counts['pending'] += $cnt;
            }
        }
        return $counts;
    }

    /**
     * Load a private run owned by a user.
     */
    private function owned_run(int $runid, int $userid): array {
        $row = $this->db->fetch_row(
            'SELECT * FROM {local_tce_runs} WHERE id = ? AND user_id = ?',
            [$runid, $userid]
        );
        if (!$row) {
            throw new \moodle_exception('invalidrun', 'local_testcase_exchange');
        }
        return $row;
    }

    /**
     * Enforce per-user Quiz run rate limit.
     */
    private function enforce_rate_limit(int $userid, int $quizid, int $limit): void {
        $oneminuteago = time() - 60;
        $row = $this->db->fetch_row(
            'SELECT COUNT(*) AS total FROM {local_tce_runs}
              WHERE user_id = ? AND quiz_id = ? AND timecreated >= ?',
            [$userid, $quizid, $oneminuteago]
        );
        $count = (int) ($row['total'] ?? 0);
        if ($count >= $limit) {
            throw new \moodle_exception('ratelimited', 'local_testcase_exchange');
        }
    }

    /**
     * Get existing canonical testcases and seeds for a course.
     */
    public function existing_fingerprints_for_course(int $courseid): array {
        $map = [];
        $rows1 = $this->db->fetch_all(
            'SELECT id, question_id, input_fingerprint, canonical_contribution_id 
               FROM {local_tce_canonical} 
              WHERE course_id = ?',
            [$courseid]
        );
        foreach ($rows1 as $row) {
            $key = (int) $row['question_id'] . ':' . (string) $row['input_fingerprint'];
            $map[$key] = [
                'type' => 'contribution',
                'id' => (int) $row['canonical_contribution_id'],
            ];
        }

        $rows2 = $this->db->fetch_all(
            'SELECT id, question_id, input_fingerprint 
               FROM {local_tce_seeds} 
              WHERE course_id = ? AND is_active = 1',
            [$courseid]
        );
        foreach ($rows2 as $row2) {
            $key = (int) $row2['question_id'] . ':' . (string) $row2['input_fingerprint'];
            if (!isset($map[$key])) {
                $map[$key] = [
                    'type' => 'seed',
                    'id' => (int) $row2['id'],
                ];
            }
        }

        return $map;
    }

    /**
     * Find the canonical contribution ID for a run.
     */
    private function canonical_id(array $run): int {
        $row = $this->db->fetch_row(
            'SELECT canonical_contribution_id FROM {local_tce_canonical}
              WHERE course_id = ? AND quiz_id = ? AND question_id = ? AND input_fingerprint = ?',
            [(int) $run['course_id'], (int) $run['quiz_id'], (int) $run['question_id'], (string) $run['input_fingerprint']]
        );
        return (int) ($row['canonical_contribution_id'] ?? 0);
    }

    /**
     * Ensure a solution version snapshot exists.
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
        $row = $this->db->fetch_row(
            'SELECT id, solution_hash FROM {local_tce_solutions}
              WHERE course_id = ? AND quiz_id = ? AND question_id = ? AND solution_hash = ?',
            [$courseid, $quizid, $questionid, $hash]
        );
        if ($row) {
            return ['id' => (int) $row['id'], 'solution_hash' => (string) $row['solution_hash']];
        }

        $timenow = time();
        try {
            $id = $this->db->insert('local_tce_solutions', [
                'course_id' => $courseid,
                'quiz_id' => $quizid,
                'question_id' => $questionid,
                'question_version' => $questionversion,
                'solution_code' => $solutioncode,
                'solution_hash' => $hash,
                'template_code' => $templatecode,
                'author_user_id' => $authorid,
                'timecreated' => $timenow,
            ]);
        } catch (\Throwable $e) {
            $existing = $this->db->fetch_row(
                'SELECT id FROM {local_tce_solutions} WHERE course_id = ? AND quiz_id = ? AND question_id = ? AND solution_hash = ?',
                [$courseid, $quizid, $questionid, $hash]
            );
            $id = $existing ? (int) $existing['id'] : 0;
        }

        return ['id' => (int) $id, 'solution_hash' => $hash];
    }

    /**
     * Synchronize Suite 2 (Reward Bank / Seeds) from Moodle Question Bank for a specific question.
     */
    public function sync_question_testsuites(int $courseid, int $quizid, int $questionid): int {
        global $DB;

        $tests = $DB->get_records('question_coderunner_tests', ['questionid' => $questionid]);
        if (!$tests) {
            return 0;
        }

        $synced = 0;
        $timenow = time();
        foreach ($tests as $t) {
            $extra = strtolower(trim((string) ($t->extra ?? '')));
            $is_reward_seed = in_array($extra, ['s', 'seed', 'reward_bank', 'suite2'], true) ||
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
            $mid = (int) $t->id;

            $existing = $this->db->fetch_row(
                'SELECT id FROM {local_tce_seeds} WHERE course_id = ? AND quiz_id = ? AND question_id = ? AND input_fingerprint = ?',
                [$courseid, $quizid, $questionid, $fingerprint]
            );

            if ($existing) {
                $this->db->update('local_tce_seeds', [
                    'id' => (int) $existing['id'],
                    'moodle_test_id' => $mid,
                    'testcode' => $testcode,
                    'stdin' => $stdin,
                    'input_raw' => $rawinput,
                    'input_normalized' => $normalized,
                    'expected_output' => $expected,
                    'is_active' => 1,
                ]);
            } else {
                $this->db->insert('local_tce_seeds', [
                    'course_id' => $courseid,
                    'quiz_id' => $quizid,
                    'question_id' => $questionid,
                    'moodle_test_id' => $mid,
                    'testcode' => $testcode,
                    'stdin' => $stdin,
                    'input_raw' => $rawinput,
                    'input_normalized' => $normalized,
                    'input_fingerprint' => $fingerprint,
                    'expected_output' => $expected,
                    'category' => 'seed',
                    'is_active' => 1,
                    'timecreated' => $timenow,
                ]);
            }
            $synced++;
        }

        return $synced;
    }

    /**
     * Return rewards for a user (unclaimed and claimed).
     */
    public function rewards_for_user(int $courseid, int $userid): array {
        $rows = $this->db->fetch_all(
            'SELECT r.id, r.course_id, r.quiz_id, r.question_id, r.receiver_user_id, r.contribution_id,
                    r.reward_source, r.seed_id, r.is_claimed, r.claimed_at, r.timecreated,
                    COALESCE(c.input_normalized, s.input_normalized, \'\') AS input_normalized,
                    COALESCE(c.oracle_output, s.expected_output, \'\') AS oracle_output,
                    COALESCE(c.category, s.category, \'seed\') AS category
               FROM {local_tce_rewards} r
          LEFT JOIN {local_tce_contributions} c ON (r.reward_source = \'contribution\' AND c.id = r.contribution_id)
          LEFT JOIN {local_tce_seeds} s ON (r.reward_source = \'seed\' AND s.id = r.seed_id)
              WHERE r.course_id = ? AND r.receiver_user_id = ?
           ORDER BY r.id DESC',
            [$courseid, $userid]
        );

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
     * Claim a pending reward to reveal its testcase and record view event.
     */
    public function claim_reward(int $rewardid, int $userid, int $courseid): bool {
        $tx = $this->db->start_transaction();
        try {
            $reward = $this->db->fetch_row(
                'SELECT * FROM {local_tce_rewards} WHERE id = ? AND receiver_user_id = ? AND course_id = ? FOR UPDATE',
                [$rewardid, $userid, $courseid]
            );
            if (!$reward) {
                $tx->rollback(new \moodle_exception('invalidreward', 'local_testcase_exchange'));
                return false;
            }

            if ((int) $reward['is_claimed'] === 1) {
                $tx->allow_commit();
                return true;
            }

            $timenow = time();
            $this->db->execute(
                'UPDATE {local_tce_rewards} SET is_claimed = 1, claimed_at = ? WHERE id = ? AND is_claimed = 0',
                [$timenow, $rewardid]
            );

            $this->db->insert('local_tce_views', [
                'course_id' => (int) $reward['course_id'],
                'quiz_id' => (int) $reward['quiz_id'],
                'question_id' => (int) $reward['question_id'],
                'user_id' => $userid,
                'reward_id' => $rewardid,
                'contribution_id' => (int) ($reward['contribution_id'] ?? 0),
                'action' => 'claim_reward',
                'timecreated' => $timenow,
            ]);

            $tx->allow_commit();
            return true;
        } catch (\Throwable $e) {
            $tx->rollback($e);
        }
    }

    /**
     * Return course leaderboard sorted by: total_stars DESC, approved_count DESC, earliest_submitted_at ASC.
     */
    public function course_leaderboard(int $courseid, int $currentuserid): array {
        global $DB;

        $sql = "
            SELECT user_id,
                   COALESCE(SUM(COALESCE(rating, 0)), 0) AS total_stars,
                   COUNT(id) AS approved_count,
                   MIN(timecreated) AS first_submitted_at
              FROM {local_tce_contributions}
             WHERE course_id = ? AND status = 'approved'
             GROUP BY user_id
             ORDER BY total_stars DESC, approved_count DESC, first_submitted_at ASC, user_id ASC
        ";
        $res = $this->db->fetch_all($sql, [$courseid]);

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

            $firstsub = (int) ($row['first_submitted_at'] ?? 0);
            $leaderboard[] = [
                'rank' => $rank,
                'user_id' => $uid,
                'fullname' => $fullname,
                'username' => $userobj ? $userobj->username : '',
                'total_stars' => $stars,
                'stars_display' => $stars . ' ⭐',
                'approved_count' => $count,
                'first_submitted_at' => $firstsub ? date('Y-m-d H:i:s', $firstsub) : '',
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
     * Grant an idempotent reward after approval.
     */
    private function grant_reward_for_contribution(int $contributionid): void {
        $contribution = $this->db->fetch_row(
            'SELECT * FROM {local_tce_contributions} WHERE id = ?',
            [$contributionid]
        );
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

        // Sample public testcases exclusion
        $policy = $this->question_policy($quizid, $questionid);
        $samplefingerprints = [];
        try {
            $execcontext = execution_service::resolve_context($questionid, $policy);
            $samplefingerprints = $execcontext['sample_fingerprints'] ?? [];
        } catch (\Throwable $e) {
            $samplefingerprints = [];
        }

        // Lock recipient row to serialize concurrent reward allocations for the same user,
        // preventing race conditions where multiple contributions of the same user compete for candidates.
        $this->db->fetch_row(
            'SELECT id FROM {user} WHERE id = ? FOR UPDATE',
            [$ownerid]
        );

        // 1. Peer Pool candidates
        $peerrows = $this->db->fetch_all(
            "SELECT c.id, c.input_fingerprint, 'contribution' AS source
               FROM {local_tce_contributions} c
              WHERE c.course_id = ? AND c.quiz_id = ? AND c.question_id = ?
                AND c.status = 'approved' AND c.user_id <> ?
                AND NOT EXISTS (
                    SELECT 1 FROM {local_tce_rewards} r
                     WHERE r.receiver_user_id = ? AND r.course_id = ? AND r.question_id = ?
                       AND r.contribution_id = c.id
                )
                AND NOT EXISTS (
                    SELECT 1 FROM {local_tce_contributions} own
                     WHERE own.user_id = ? AND own.course_id = ? AND own.question_id = ?
                       AND own.input_fingerprint = c.input_fingerprint
                )
                AND NOT EXISTS (
                    SELECT 1 FROM {local_tce_runs} tr
                     WHERE tr.user_id = ? AND tr.question_id = ?
                       AND tr.input_fingerprint = c.input_fingerprint
                )",
            [
                $courseid, $quizid, $questionid, $ownerid,
                $ownerid, $courseid, $questionid,
                $ownerid, $courseid, $questionid,
                $ownerid, $questionid
            ]
        );

        if (!empty($samplefingerprints)) {
            $samplelookup = array_flip($samplefingerprints);
            $peerrows = array_filter($peerrows, function($row) use ($samplelookup) {
                return !isset($samplelookup[$row['input_fingerprint']]);
            });
        }

        $candidate = null;
        if (!empty($peerrows)) {
            $peerrows = array_values($peerrows);
            $candidate = $peerrows[array_rand($peerrows)];
        }

        // 2. Fallback to Seed Bank
        if (!$candidate) {
            $seedrows = $this->db->fetch_all(
                "SELECT s.id, s.input_fingerprint, 'seed' AS source
                   FROM {local_tce_seeds} s
                  WHERE s.course_id = ? AND s.quiz_id = ? AND s.question_id = ? AND s.is_active = 1
                    AND NOT EXISTS (
                        SELECT 1 FROM {local_tce_rewards} r
                         WHERE r.receiver_user_id = ? AND r.course_id = ? AND r.question_id = ?
                           AND r.reward_source = 'seed' AND r.seed_id = s.id
                    )
                    AND NOT EXISTS (
                        SELECT 1 FROM {local_tce_contributions} own
                         WHERE own.user_id = ? AND own.course_id = ? AND own.question_id = ?
                           AND own.input_fingerprint = s.input_fingerprint
                    )
                    AND NOT EXISTS (
                        SELECT 1 FROM {local_tce_runs} tr
                         WHERE tr.user_id = ? AND tr.question_id = ?
                           AND tr.input_fingerprint = s.input_fingerprint
                    )",
                [
                    $courseid, $quizid, $questionid,
                    $ownerid, $courseid, $questionid,
                    $ownerid, $courseid, $questionid,
                    $ownerid, $questionid
                ]
            );

            if (!empty($samplefingerprints)) {
                $samplelookup = array_flip($samplefingerprints);
                $seedrows = array_filter($seedrows, function($row) use ($samplelookup) {
                    return !isset($samplelookup[$row['input_fingerprint']]);
                });
            }

            if (!empty($seedrows)) {
                $seedrows = array_values($seedrows);
                $candidate = $seedrows[array_rand($seedrows)];
            }
        }

        if (!$candidate) {
            return;
        }

        $source = (string) $candidate['source'];
        $targetid = (int) $candidate['id'];
        $contribid = ($source === 'contribution') ? $targetid : null;
        $seedid = ($source === 'seed') ? $targetid : null;
        $timenow = time();

        // Concurrency defense: Check if candidate reward was already granted in concurrent transaction
        $existing = $this->db->fetch_row(
            'SELECT id FROM {local_tce_rewards}
              WHERE receiver_user_id = ? AND question_id = ?
                AND (
                    (reward_source = \'contribution\' AND contribution_id = ?)
                    OR (reward_source = \'seed\' AND seed_id = ?)
                )
              FOR UPDATE',
            [$ownerid, $questionid, $contribid, $seedid]
        );
        if ($existing) {
            return;
        }

        try {
            $this->db->insert('local_tce_rewards', [
                'course_id' => $courseid,
                'quiz_id' => $quizid,
                'question_id' => $questionid,
                'receiver_user_id' => $ownerid,
                'contribution_id' => $contribid,
                'reward_source' => $source,
                'seed_id' => $seedid,
                'is_claimed' => 0,
                'claimed_at' => null,
                'timecreated' => $timenow,
            ]);
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            $isdup = (stripos($msg, 'Duplicate') !== false ||
                      stripos($msg, 'unique') !== false ||
                      stripos($msg, '1062') !== false ||
                      stripos($msg, '23505') !== false);
            if (!$isdup && $e instanceof \dml_write_exception && isset($e->error)) {
                $isdup = (stripos($e->error, 'Duplicate') !== false || stripos($e->error, 'unique') !== false);
            }
            if ($isdup) {
                // Benign duplicate reward race condition suppressed idempotently.
                debugging('Suppressed duplicate reward race condition: ' . $msg, DEBUG_DEVELOPER);
            } else {
                // Unexpected DB failure: rethrow so parent review transaction cleanly rolls back.
                throw $e;
            }
        }
    }

    /**
     * Insert an immutable review audit row.
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
        $this->db->insert('local_tce_reviews', [
            'contribution_id' => $contributionid,
            'reviewer_user_id' => $reviewerid,
            'from_status' => $from,
            'to_status' => $to,
            'review_note' => $note,
            'rating' => $rating,
            'rating_reason' => $ratingreason,
            'timecreated' => time(),
        ]);
    }

    /**
     * Return summary statistics for all courses in local_tce_contributions.
     */
    public function courses_summary(): array {
        $rows = $this->db->fetch_all(
            "SELECT course_id,
                    COUNT(*) AS total_count,
                    SUM(CASE WHEN status IN ('submitted', 'needs_explanation') THEN 1 ELSE 0 END) AS pending_count,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved_count,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS rejected_count,
                    SUM(CASE WHEN status = 'duplicate' THEN 1 ELSE 0 END) AS duplicate_count,
                    COUNT(DISTINCT user_id) AS student_count
               FROM {local_tce_contributions}
              GROUP BY course_id"
        );

        $summary = [];
        foreach ($rows as $row) {
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

    /**
     * List all seed testcases for a specific question.
     */
    public function list_seed_testcases(int $courseid, int $quizid, int $questionid): array {
        return $this->db->fetch_all(
            'SELECT id, testcode, stdin, input_raw, expected_output, category, timecreated
               FROM {local_tce_seeds}
              WHERE course_id = ? AND quiz_id = ? AND question_id = ? AND is_active = 1
              ORDER BY id ASC',
            [$courseid, $quizid, $questionid]
        );
    }

    /**
     * Add a manual seed testcase for a question.
     */
    public function add_manual_seed(
        int $courseid,
        int $quizid,
        int $questionid,
        string $testcode,
        string $stdin,
        string $expected,
        string $purpose = '',
        string $sourcetype = 'manual'
    ): array {
        $rawinput = trim($stdin);
        if ($rawinput === '') {
            $rawinput = trim($testcode);
        }
        $normalized = normalizer::normalize($rawinput, 'trim');
        $fingerprint = normalizer::fingerprint($normalized);
        $timenow = time();

        $existing = $this->db->fetch_row(
            'SELECT id FROM {local_tce_seeds} WHERE course_id = ? AND quiz_id = ? AND question_id = ? AND input_fingerprint = ?',
            [$courseid, $quizid, $questionid, $fingerprint]
        );

        if ($existing) {
            $this->db->update('local_tce_seeds', [
                'id' => (int) $existing['id'],
                'testcode' => $testcode,
                'stdin' => $stdin,
                'input_raw' => $rawinput,
                'input_normalized' => $normalized,
                'expected_output' => $expected,
                'is_active' => 1,
            ]);
            $insertid = (int) $existing['id'];
        } else {
            $insertid = $this->db->insert('local_tce_seeds', [
                'course_id' => $courseid,
                'quiz_id' => $quizid,
                'question_id' => $questionid,
                'testcode' => $testcode,
                'stdin' => $stdin,
                'input_raw' => $rawinput,
                'input_normalized' => $normalized,
                'input_fingerprint' => $fingerprint,
                'expected_output' => $expected,
                'category' => 'seed',
                'is_active' => 1,
                'timecreated' => $timenow,
            ]);
        }

        return [
            'id' => $insertid,
            'testcode' => $testcode,
            'expected_output' => $expected,
            'fingerprint' => $fingerprint,
        ];
    }

    /**
     * Delete (deactivate) a seed testcase.
     */
    public function delete_seed_testcase(int $seedid, int $courseid): bool {
        return $this->db->execute(
            'DELETE FROM {local_tce_seeds} WHERE id = ? AND course_id = ?',
            [$seedid, $courseid]
        );
    }

    /**
     * Import seed testcases from uploaded Moodle XML file content.
     */
    public function import_seeds_from_xml_content(
        int $courseid,
        int $quizid,
        int $questionid,
        string $xmlcontent
    ): int {
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xmlcontent);
        if (!$xml) {
            libxml_clear_errors();
            throw new \moodle_exception('invalidxml', 'local_testcase_exchange', '', 'Invalid Moodle XML format');
        }

        $imported = 0;
        $testcase_elements = $xml->xpath('//testcase');
        if (empty($testcase_elements)) {
            $testcase_elements = $xml->xpath('//testcases/testcase');
        }

        foreach ($testcase_elements as $tc) {
            $testcode = '';
            if (isset($tc->testcode->text)) {
                $testcode = (string) $tc->testcode->text;
            } else if (isset($tc->testcode)) {
                $testcode = (string) $tc->testcode;
            }

            $stdin = '';
            if (isset($tc->stdin->text)) {
                $stdin = (string) $tc->stdin->text;
            } else if (isset($tc->stdin)) {
                $stdin = (string) $tc->stdin;
            }

            $expected = '';
            if (isset($tc->expected->text)) {
                $expected = (string) $tc->expected->text;
            } else if (isset($tc->expected)) {
                $expected = (string) $tc->expected;
            }

            $purpose = 'Imported from Moodle XML';
            if (isset($tc->extra->text) && trim((string) $tc->extra->text) !== '') {
                $purpose .= ' (' . trim((string) $tc->extra->text) . ')';
            }

            if (trim($testcode) === '' && trim($stdin) === '') {
                continue;
            }

            $this->add_manual_seed(
                $courseid,
                $quizid,
                $questionid,
                trim($testcode),
                trim($stdin),
                trim($expected),
                $purpose,
                'xml_import'
            );
            $imported++;
        }

        return $imported;
    }

    /**
     * Get total runs count for course, optional quiz and question, isolated to enrolled students.
     */
    public function total_runs_count(int $courseid, int $quizid = 0, int $questionid = 0, ?array $studentuserids = null): int {
        if ($studentuserids !== null && empty($studentuserids)) {
            return 0;
        }
        $conditions = ['course_id = ?'];
        $params = [$courseid];

        if ($quizid > 0) {
            $conditions[] = 'quiz_id = ?';
            $params[] = $quizid;
        }
        if ($questionid > 0) {
            $conditions[] = 'question_id = ?';
            $params[] = $questionid;
        }
        if ($studentuserids !== null && !empty($studentuserids)) {
            $safeids = array_map('intval', $studentuserids);
            $conditions[] = 'user_id IN (' . implode(',', $safeids) . ')';
        }
        $where = implode(' AND ', $conditions);
        $row = $this->db->fetch_row("SELECT COUNT(*) AS total FROM {local_tce_runs} WHERE {$where}", $params);
        return (int) ($row['total'] ?? 0);
    }

    /**
     * Get rewards statistics for course, optional quiz and question.
     */
    public function total_rewards_count(int $courseid, int $quizid = 0, int $questionid = 0, ?array $studentuserids = null): array {
        if ($studentuserids !== null && empty($studentuserids)) {
            return ['total' => 0, 'claimed' => 0, 'unclaimed' => 0];
        }
        $conditions = ['course_id = ?'];
        $params = [$courseid];

        if ($quizid > 0) {
            $conditions[] = 'quiz_id = ?';
            $params[] = $quizid;
        }
        if ($questionid > 0) {
            $conditions[] = 'question_id = ?';
            $params[] = $questionid;
        }
        if ($studentuserids !== null && !empty($studentuserids)) {
            $safeids = array_map('intval', $studentuserids);
            $conditions[] = 'receiver_user_id IN (' . implode(',', $safeids) . ')';
        }
        $where = implode(' AND ', $conditions);
        $row = $this->db->fetch_row(
            "SELECT COUNT(*) AS total, SUM(CASE WHEN is_claimed = 1 THEN 1 ELSE 0 END) AS claimed FROM {local_tce_rewards} WHERE {$where}",
            $params
        );
        $total = (int) ($row['total'] ?? 0);
        $claimed = (int) ($row['claimed'] ?? 0);
        return [
            'total' => $total,
            'claimed' => $claimed,
            'unclaimed' => max(0, $total - $claimed),
        ];
    }

    /**
     * Aggregated student activity for a course, quiz and question, isolated to enrolled students.
     */
    public function student_activity_summary(int $courseid, int $quizid = 0, int $questionid = 0, ?array $studentuserids = null): array {
        if ($studentuserids !== null && empty($studentuserids)) {
            return [];
        }
        $studentfilterclause = '';
        $safeids = [];
        if ($studentuserids !== null && !empty($studentuserids)) {
            $safeids = array_map('intval', $studentuserids);
            $studentfilterclause = ' AND user_id IN (' . implode(',', $safeids) . ')';
        }

        // 1. Runs per user
        $runconds = ['course_id = ?'];
        $runparams = [$courseid];
        if ($quizid > 0) {
            $runconds[] = 'quiz_id = ?';
            $runparams[] = $quizid;
        }
        if ($questionid > 0) {
            $runconds[] = 'question_id = ?';
            $runparams[] = $questionid;
        }
        $runwhere = implode(' AND ', $runconds) . $studentfilterclause;
        $runsql = "SELECT user_id, COUNT(*) AS total_runs, MAX(timecreated) AS last_run_at FROM {local_tce_runs} WHERE {$runwhere} GROUP BY user_id";
        $runrows = $this->db->fetch_all($runsql, $runparams);

        $users = [];
        foreach ($runrows as $row) {
            $uid = (int) $row['user_id'];
            $lastrun = (int) ($row['last_run_at'] ?? 0);
            $users[$uid] = [
                'user_id' => $uid,
                'total_runs' => (int) $row['total_runs'],
                'last_run_at' => $lastrun ? date('Y-m-d H:i:s', $lastrun) : null,
                'total_contributions' => 0,
                'approved_contributions' => 0,
                'pending_contributions' => 0,
                'total_stars' => 0,
                'claimed_rewards' => 0,
                'total_rewards' => 0,
            ];
        }

        // 2. Contributions per user
        $cconds = ['course_id = ?'];
        $cparams = [$courseid];
        if ($quizid > 0) {
            $cconds[] = 'quiz_id = ?';
            $cparams[] = $quizid;
        }
        if ($questionid > 0) {
            $cconds[] = 'question_id = ?';
            $cparams[] = $questionid;
        }
        $cwhere = implode(' AND ', $cconds) . $studentfilterclause;
        $csql = "SELECT user_id,
                        COUNT(*) AS total_c,
                        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved_c,
                        SUM(CASE WHEN status IN ('submitted', 'needs_explanation') THEN 1 ELSE 0 END) AS pending_c,
                        SUM(CASE WHEN status = 'approved' THEN COALESCE(rating, 0) ELSE 0 END) AS total_s
                   FROM {local_tce_contributions}
                  WHERE {$cwhere}
                  GROUP BY user_id";
        $crows = $this->db->fetch_all($csql, $cparams);
        foreach ($crows as $row) {
            $uid = (int) $row['user_id'];
            if (!isset($users[$uid])) {
                $users[$uid] = [
                    'user_id' => $uid,
                    'total_runs' => 0,
                    'last_run_at' => null,
                    'total_contributions' => 0,
                    'approved_contributions' => 0,
                    'pending_contributions' => 0,
                    'total_stars' => 0,
                    'claimed_rewards' => 0,
                    'total_rewards' => 0,
                ];
            }
            $users[$uid]['total_contributions'] = (int) $row['total_c'];
            $users[$uid]['approved_contributions'] = (int) $row['approved_c'];
            $users[$uid]['pending_contributions'] = (int) $row['pending_c'];
            $users[$uid]['total_stars'] = (int) $row['total_s'];
        }

        // 3. Rewards per user
        $rconds = ['course_id = ?'];
        $rparams = [$courseid];
        if ($quizid > 0) {
            $rconds[] = 'quiz_id = ?';
            $rparams[] = $quizid;
        }
        if ($questionid > 0) {
            $rconds[] = 'question_id = ?';
            $rparams[] = $questionid;
        }
        $rstudentfilterclause = '';
        if (!empty($studentuserids)) {
            $safeids = array_map('intval', $studentuserids);
            $rstudentfilterclause = ' AND receiver_user_id IN (' . implode(',', $safeids) . ')';
        }
        $rwhere = implode(' AND ', $rconds) . $rstudentfilterclause;
        $rsql = "SELECT receiver_user_id AS user_id,
                        COUNT(*) AS total_r,
                        SUM(CASE WHEN is_claimed = 1 THEN 1 ELSE 0 END) AS claimed_r
                   FROM {local_tce_rewards}
                  WHERE {$rwhere}
                  GROUP BY receiver_user_id";
        $rrows = $this->db->fetch_all($rsql, $rparams);
        foreach ($rrows as $row) {
            $uid = (int) $row['user_id'];
            if (!isset($users[$uid])) {
                $users[$uid] = [
                    'user_id' => $uid,
                    'total_runs' => 0,
                    'last_run_at' => null,
                    'total_contributions' => 0,
                    'approved_contributions' => 0,
                    'pending_contributions' => 0,
                    'total_stars' => 0,
                    'claimed_rewards' => 0,
                    'total_rewards' => 0,
                ];
            }
            $users[$uid]['total_rewards'] = (int) $row['total_r'];
            $users[$uid]['claimed_rewards'] = (int) $row['claimed_r'];
        }

        if (!empty($studentuserids)) {
            $safeidlookup = array_flip($safeids);
            $users = array_filter($users, function($u) use ($safeidlookup) {
                return isset($safeidlookup[$u['user_id']]);
            });
        }

        usort($users, function($a, $b) {
            if ($b['total_contributions'] !== $a['total_contributions']) {
                return $b['total_contributions'] - $a['total_contributions'];
            }
            return $b['total_runs'] - $a['total_runs'];
        });

        return array_values($users);
    }
}
