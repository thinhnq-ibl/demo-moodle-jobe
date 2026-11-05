with open('local_testcase_exchange/classes/testcase_service.php', 'r', encoding='utf-8') as f:
    code = f.read()

# 1. Update submit_contribution: Idempotency check and insert expected_output
target_submit = """            $contributionid = $this->db->insert('local_tce_contributions', [
                'run_id' => $runpk,
                'course_id' => $courseid,
                'quiz_id' => $quizid,
                'question_id' => $questionid,
                'user_id' => $userid,
                'input_normalized' => (string) $run['input_normalized'],
                'input_fingerprint' => $fingerprint,
                'oracle_output' => (string) $run['oracle_output'],
                'purpose' => (string) $run['purpose'],
                'category' => (string) $run['category'],
                'status' => $status,
                'timecreated' => $timenow,
                'timemodified' => $timenow,
            ]);"""

replacement_submit = """            // Idempotency check: if student already submitted this testcase, return existing contribution directly
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
            ]);"""

if target_submit in code:
    code = code.replace(target_submit, replacement_submit, 1)
    print("Patched submit_contribution")
else:
    print("ERROR: target_submit not found")

# 2. Update user_contributions: JOIN runs to get predicted_output / expected_output
target_user_contrib = """            'SELECT c.*,
                    (SELECT cr.review_note FROM {local_tce_reviews} cr
                      WHERE cr.id = (
                          SELECT MAX(cr2.id) FROM {local_tce_reviews} cr2
                           WHERE cr2.contribution_id = c.id AND cr2.review_note IS NOT NULL AND cr2.review_note <> \\'\\'
                      )) AS latest_review_note,
                    (SELECT cr.rating FROM {local_tce_reviews} cr
                      WHERE cr.id = (
                          SELECT MAX(cr3.id) FROM {local_tce_reviews} cr3
                           WHERE cr3.contribution_id = c.id AND cr3.rating IS NOT NULL AND cr3.rating > 0
                      )) AS latest_rating
               FROM {local_tce_contributions} c
              WHERE c.course_id = ? AND c.user_id = ? ORDER BY c.id DESC',"""

replacement_user_contrib = """            'SELECT c.*,
                    COALESCE(c.expected_output, r.predicted_output) AS expected_output,
                    r.predicted_output AS run_predicted_output,
                    r.student_run_output,
                    r.oracle_output AS run_oracle_output,
                    r.oracle_outcome,
                    (SELECT cr.review_note FROM {local_tce_reviews} cr
                      WHERE cr.id = (
                          SELECT MAX(cr2.id) FROM {local_tce_reviews} cr2
                           WHERE cr2.contribution_id = c.id AND cr2.review_note IS NOT NULL AND cr2.review_note <> \\'\\'
                      )) AS latest_review_note,
                    (SELECT cr.rating FROM {local_tce_reviews} cr
                      WHERE cr.id = (
                          SELECT MAX(cr3.id) FROM {local_tce_reviews} cr3
                           WHERE cr3.contribution_id = c.id AND cr3.rating IS NOT NULL AND cr3.rating > 0
                      )) AS latest_rating
               FROM {local_tce_contributions} c
          LEFT JOIN {local_tce_runs} r ON r.id = c.run_id
              WHERE c.course_id = ? AND c.user_id = ? ORDER BY c.id DESC',"""

if target_user_contrib in code:
    code = code.replace(target_user_contrib, replacement_user_contrib, 1)
    print("Patched user_contributions")
else:
    print("ERROR: target_user_contrib not found")

# 3. Update course_contributions: JOIN runs to get predicted_output / expected_output
target_course_contrib = """                $sql = "SELECT c.*,
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
                 WHERE {$where}
              ORDER BY c.id DESC";"""

replacement_course_contrib = """                $sql = "SELECT c.*,
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
              ORDER BY c.id DESC";"""

if target_course_contrib in code:
    code = code.replace(target_course_contrib, replacement_course_contrib, 1)
    print("Patched course_contributions")
else:
    print("ERROR: target_course_contrib not found")

with open('local_testcase_exchange/classes/testcase_service.php', 'w', encoding='utf-8') as f:
    f.write(code)
print("Saved testcase_service.php")
