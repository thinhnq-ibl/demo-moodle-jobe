<?php
// End-to-end fixture for the local Docker Moodle site. Not distributed with the plugin.

define('CLI_SCRIPT', true);

require_once('/var/www/html/config.php');
require_once($CFG->dirroot . '/lib/questionlib.php');

$course = $DB->get_record('course', ['shortname' => 'AI-PY-PILOT'], '*', MUST_EXIST);
$context = context_course::instance($course->id);
$questioncontexts = \local_testcase_exchange\context_service::questions_for_course($course->id);
$byquestion = [];
foreach ($questioncontexts as $questioncontext) {
    $byquestion[(int) $questioncontext->questionid] = $questioncontext;
}

$scenarios = [
    'student1' => [
        121 => [
            'source' => "score = float(input())\nscore = max(0.0, min(1.0, score))\nprint(f'{score:.2f}')",
            'input' => '0.999',
            'predicted' => '1.00',
            'purpose' => 'Kiểm tra làm tròn gần biên trên.',
            'category' => 'boundary',
        ],
        122 => [
            'source' => "words = input().lower().split()\nprint(sum(1 for word in words if word == 'ai'))",
            'input' => '  AI   ai  ',
            'predicted' => '2',
            'purpose' => 'Kiểm tra chữ hoa và nhiều khoảng trắng.',
            'category' => 'branch',
        ],
        123 => [
            'source' => "correct, total = map(int, input().split())\naccuracy = 0.0 if total == 0 else correct * 100.0 / total\nprint(f'{accuracy:.2f}')",
            'input' => '2 7',
            'predicted' => '28.57',
            'purpose' => 'Kiểm tra phép chia tạo số thập phân tuần hoàn.',
            'category' => 'normal',
        ],
    ],
    'student2' => [
        121 => [
            'source' => "score = float(input())\nscore = max(0.0, min(1.0, score))\nprint(f'{score:.2f}')",
            'input' => '-10',
            'predicted' => '0.00',
            'purpose' => 'Kiểm tra giá trị rất nhỏ ngoài miền.',
            'category' => 'boundary',
        ],
        122 => [
            'source' => "words = input().lower().split()\nprint(sum(1 for word in words if word == 'ai'))",
            'input' => 'AI-powered AI',
            'predicted' => '1',
            'purpose' => 'Phân biệt từ AI với từ ghép có dấu gạch nối.',
            'category' => 'branch',
        ],
        123 => [
            'source' => "correct, total = map(int, input().split())\naccuracy = 0.0 if total == 0 else correct * 100.0 / total\nprint(f'{accuracy:.2f}')",
            'input' => '999 1000',
            'predicted' => '99.90',
            'purpose' => 'Kiểm tra tập dữ liệu lớn gần accuracy tuyệt đối.',
            'category' => 'large',
        ],
    ],
];

$connection = \local_testcase_exchange\external_database::connect();
$service = new \local_testcase_exchange\testcase_service($connection);
$results = [];
foreach ($scenarios as $username => $questions) {
    $user = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);
    \core\session\manager::set_user($user);
    foreach ($questions as $questionid => $scenario) {
        if (!isset($byquestion[$questionid])) {
            throw new coding_exception("Question {$questionid} is not in the pilot course.");
        }
        $questioncontext = $byquestion[$questionid];
        $quiz = $DB->get_record('quiz', ['id' => $questioncontext->quizid], '*', MUST_EXIST);
        $fingerprint = \local_testcase_exchange\normalizer::fingerprint(trim($scenario['input']));
        $stmt = $connection->prepare(
            'SELECT id FROM testcase_runs
              WHERE course_id = ? AND quiz_id = ? AND question_id = ? AND user_id = ? AND input_fingerprint = ?'
        );
        $courseid = (int) $course->id;
        $quizid = (int) $quiz->id;
        $userid = (int) $user->id;
        $stmt->bind_param('iiiis', $courseid, $quizid, $questionid, $userid, $fingerprint);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($existing) {
            $runid = (int) $existing['id'];
            $run = $connection->query('SELECT * FROM testcase_runs WHERE id = ' . $runid)->fetch_assoc();
        } else {
            $runresult = $service->create_run(
                $courseid,
                $quiz,
                $questioncontext,
                $userid,
                $scenario['source'],
                $scenario['input'],
                $scenario['predicted'],
                $scenario['purpose'],
                $scenario['category'],
                'Testcase được chọn để tìm lỗi ở miền ít được kiểm tra.'
            );
            $runid = (int) $runresult['id'];
            $run = $connection->query('SELECT * FROM testcase_runs WHERE id = ' . $runid)->fetch_assoc();
        }
        $contribution = $service->submit_contribution($runid, $userid);
        $results[] = [
            'user' => $username,
            'quizid' => $quizid,
            'questionid' => $questionid,
            'runid' => $runid,
            'student_outcome' => $run['student_outcome'],
            'oracle_outcome' => $run['oracle_outcome'],
            'contribution' => $contribution,
        ];
    }
}

$admin = $DB->get_record('user', ['id' => 2], '*', MUST_EXIST);
\core\session\manager::set_user($admin);
foreach ($results as &$result) {
    if ($result['contribution']['status'] === 'submitted') {
        $service->review(
            (int) $result['contribution']['id'],
            (int) $course->id,
            (int) $admin->id,
            'approved',
            'Pilot review: input có mục đích rõ và oracle chạy thành công.'
        );
        $result['contribution']['status'] = 'approved';
    }
}
unset($result);

$privacy = [];
foreach (['student1', 'student2'] as $username) {
    $user = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);
    \core\session\manager::set_user($user);
    $ownruns = $service->runs_for_user((int) $course->id, (int) $user->id);
    $owncontributions = $service->contributions_for_user((int) $course->id, (int) $user->id);
    $privacy[$username] = [
        'can_view' => has_capability('local/testcase_exchange:view', $context),
        'can_run' => has_capability('local/testcase_exchange:run', $context),
        'can_contribute' => has_capability('local/testcase_exchange:contribute', $context),
        'can_review' => has_capability('local/testcase_exchange:review', $context),
        'can_manage' => has_capability('local/testcase_exchange:manage', $context),
        'visible_run_user_ids' => array_values(array_unique(array_map(
            static fn(array $row): int => (int) $row['user_id'],
            $ownruns
        ))),
        'visible_contribution_user_ids' => array_values(array_unique(array_map(
            static fn(array $row): int => (int) $row['user_id'],
            $owncontributions
        ))),
        'reward_count' => count($service->rewards_for_user((int) $course->id, (int) $user->id)),
    ];
}

$forum = $DB->get_record('forum', ['course' => $course->id, 'type' => 'qanda'], '*', MUST_EXIST);
$discussion = $DB->get_record('forum_discussions', ['forum' => $forum->id], '*', MUST_EXIST);
$rootpost = $DB->get_record('forum_posts', ['id' => $discussion->firstpost], '*', MUST_EXIST);
$studentquestions = [
    'student1' => 'Turing Test liên hệ thế nào với mô hình máy Turing, và vì sao hai ý tưởng này vẫn chưa đủ để kết luận máy thực sự suy nghĩ?',
    'student2' => 'Từ công việc giải mã và cách xã hội đối xử với Alan Turing, người phát triển AI hôm nay cần cân bằng hiệu quả kỹ thuật với trách nhiệm xã hội ra sao?',
];
foreach ($studentquestions as $username => $question) {
    $user = $DB->get_record('user', ['username' => $username], '*', MUST_EXIST);
    if (!$DB->record_exists('forum_posts', [
        'discussion' => $discussion->id,
        'parent' => $rootpost->id,
        'userid' => $user->id,
    ])) {
        $DB->insert_record('forum_posts', (object) [
            'discussion' => $discussion->id,
            'parent' => $rootpost->id,
            'userid' => $user->id,
            'created' => time(),
            'modified' => time(),
            'mailed' => 0,
            'subject' => 'Câu hỏi của ' . fullname($user),
            'message' => '<p>' . s($question) . '</p>',
            'messageformat' => FORMAT_HTML,
            'messagetrust' => 0,
            'attachment' => 0,
            'totalscore' => 0,
            'mailnow' => 0,
            'deleted' => 0,
            'privatereplyto' => 0,
            'wordcount' => count_words($question),
            'charcount' => core_text::strlen($question),
        ]);
    }
}

$connection->close();
echo json_encode([
    'courseid' => (int) $course->id,
    'runs_and_contributions' => $results,
    'student_visibility' => $privacy,
    'qanda_forum' => [
        'forumid' => (int) $forum->id,
        'discussionid' => (int) $discussion->id,
        'student_question_count' => count($studentquestions),
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
