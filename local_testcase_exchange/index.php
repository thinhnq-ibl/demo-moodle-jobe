<?php
require_once(__DIR__ . '/../../config.php');
require_login();

global $USER, $DB, $PAGE, $OUTPUT;

$courseid = optional_param('course', 3, PARAM_INT);
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);

require_capability('local/testcase_exchange:view', $context);

$PAGE->set_url(new moodle_url('/local/testcase_exchange/index.php', ['course' => $courseid]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('nav_testcase_bank', 'local_testcase_exchange'));
$PAGE->set_heading($course->fullname . ' - ' . get_string('nav_testcase_bank', 'local_testcase_exchange'));

$current_student = $USER->username;

// Cấu hình CSDL testcase_store
$db_host = get_config('local_testcase_exchange', 'db_host') ?: 'mariadb';
$db_user = get_config('local_testcase_exchange', 'db_user') ?: 'moodle_reader';
$db_pass = get_config('local_testcase_exchange', 'db_pass') ?: 'ReaderSecret123!';
$db_name = get_config('local_testcase_exchange', 'db_name') ?: 'testcase_store';

$conn = @new mysqli($db_host, $db_user, $db_pass, $db_name, 3306);
$error_db = null;
if ($conn->connect_error) {
    $error_db = "Không thể kết nối CSDL testcase_store: " . $conn->connect_error;
}

$alert_msg = null;
$alert_type = null;

$questions = [];
$sql_questions = "
    SELECT DISTINCT q.id, q.name 
    FROM {course_modules} cm 
    JOIN {quiz} qz ON qz.id = cm.instance 
    JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz' 
    JOIN {quiz_slots} qs ON qs.quizid = qz.id 
    JOIN {question_references} qr ON qr.itemid = qs.id AND qr.component = 'mod_quiz' 
    JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid 
    JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id 
    JOIN {question} q ON q.id = qv.questionid 
    WHERE cm.course = ?
    ORDER BY q.id ASC
";
$records = $DB->get_records_sql($sql_questions, [$course->id]);
foreach ($records as $r) {
    $questions[$r->id] = $r->name;
}

/**
 * Thẩm định bằng cách chạy trực tiếp qua code giải chuẩn trong question_solutions
 */
function evaluate_against_teacher_solution($conn, $question_id, $test_input) {
    $stmt = $conn->prepare("SELECT func_name, solution_code FROM question_solutions WHERE question_id = ?");
    $stmt->bind_param('i', $question_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return null;
    }

    $func_name = $row['func_name'];
    $solution_code = $row['solution_code'];

    // Gọi python qua lệnh nội bộ
    $py_script = $solution_code . "\n\n" .
                 "inp = '''" . addslashes($test_input) . "'''.strip()\n" .
                 "if inp.lstrip('-').isdigit():\n" .
                 "    val = int(inp)\n" .
                 "else:\n" .
                 "    val = inp\n" .
                 "res = " . $func_name . "(val)\n" .
                 "print('True' if res else 'False')\n";

    $descriptors = [
        0 => ["pipe", "r"],
        1 => ["pipe", "w"],
        2 => ["pipe", "w"]
    ];
    $process = proc_open('python3', $descriptors, $pipes);
    if (is_resource($process)) {
        fwrite($pipes[0], $py_script);
        fclose($pipes[0]);
        $output = trim(stream_get_contents($pipes[1]));
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        return $output;
    }
    return null;
}

// XỬ LÝ NỘP TESTCASE ĐẦY ĐỦ (INPUT + EXPECTED OUTPUT ĐỐI CHỨNG VỚI CODE ANSWER CỦA THẦY CÔ)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_testcase' && confirm_sesskey()) {
    $selected_qid = required_param('question_id', PARAM_INT);
    $submitted_input = trim(required_param('test_input', PARAM_RAW));
    $submitted_expected = trim(required_param('expected_output', PARAM_RAW)); // "True" hoặc "False"

    if (empty($submitted_input) || empty($submitted_expected)) {
        $alert_msg = "❌ Vui lòng nhập đầy đủ cả Dữ liệu đầu vào (Input) và Kết quả mong đợi (Expected Output)!";
        $alert_type = "danger";
    } else {
        $valid = true;
        $actual_teacher_output = evaluate_against_teacher_solution($conn, $selected_qid, $submitted_input);

        if ($actual_teacher_output === null) {
            $alert_msg = "⚠️ Không tìm thấy lời giải mẫu (Answer Solution) của thầy cô cho bài tập này!";
            $alert_type = "warning";
            $valid = false;
        } else {
            // ĐỐI CHIẾU KẾT QUẢ MONG ĐỢI VỚI OUTPUT CHẠY RA TỪ CODE CỦA THẦY CÔ
            if (strcasecmp($submitted_expected, $actual_teacher_output) !== 0) {
                $alert_msg = "❌ <b>TESTCASE KHÔNG KHỚP VỚI LỜI GIẢI CỦA THẦY CÔ!</b><br>" .
                             "Khi chạy Input <code>" . htmlspecialchars($submitted_input) . "</code> qua đoạn code mẫu của thầy cô (<code>question.answer</code>):<br>" .
                             "👉 Kết quả thực tế chạy ra là: <b><code style='color: #d63384; font-size: 1.15em;'>{$actual_teacher_output}</code></b>.<br>" .
                             "👉 Nhưng Expected Output bạn đưa ra là: <code>{$submitted_expected}</code>.<br>" .
                             "💡 <i>Chỉ khi Expected Output của testcase trùng khớp với kết quả từ code của thầy cô thì testcase mới được xem là Pass và lưu vào kho!</i>";
                $alert_type = "danger";
                $valid = false;
            }
        }

        // 3. NẾU TESTCASE KHỚP HOÀN TOÀN -> KIỂM TRA CHỐNG TRÙNG VÀ GHI NHẬN
        if ($valid && !$error_db) {
            $cname = $course->shortname;

            // A. Đã từng nộp chưa?
            $st1 = $conn->prepare("SELECT id FROM student_testcases WHERE course_id=? AND question_id=? AND student_id=? AND test_input=?");
            $st1->bind_param('siss', $cname, $selected_qid, $current_student, $submitted_input);
            $st1->execute();
            if ($st1->get_result()->fetch_assoc()) {
                $alert_msg = "⚠️ Bạn đã từng đóng góp ca kiểm thử <code>" . htmlspecialchars($submitted_input) . "</code> này rồi!";
                $alert_type = "warning";
            } else {
                // B. Đã từng được ngân hàng tặng chưa?
                $st2 = $conn->prepare("SELECT id FROM student_received_testcases WHERE course_id=? AND question_id=? AND student_id=? AND test_input=?");
                $st2->bind_param('siss', $cname, $selected_qid, $current_student, $submitted_input);
                $st2->execute();
                if ($st2->get_result()->fetch_assoc()) {
                    $alert_msg = "❌ <b>Không được tính là ca mới!</b> Ca kiểm thử <code>" . htmlspecialchars($submitted_input) . "</code> là testcase bạn đã được hệ thống thưởng trước đó.";
                    $alert_type = "danger";
                } else {
                    // C. Có trùng hạt giống không?
                    $st3 = $conn->prepare("SELECT id FROM question_seed_testcases WHERE course_id=? AND question_id=? AND test_input=?");
                    $st3->bind_param('sis', $cname, $selected_qid, $submitted_input);
                    $st3->execute();
                    if ($st3->get_result()->fetch_assoc()) {
                        $alert_msg = "❌ <b>Trùng testcase có sẵn!</b> Ca kiểm thử <code>" . htmlspecialchars($submitted_input) . "</code> đã có sẵn trong ngân hàng gốc của hệ thống.";
                        $alert_type = "warning";
                    } else {
                        // D. Hợp lệ! Ghi nhận đầy đủ (Input + Expected Output chuẩn từ code thầy cô)
                        $in_stmt = $conn->prepare("INSERT INTO student_testcases (course_id, question_id, student_id, test_input, expected_output, jobe_server) VALUES (?, ?, ?, ?, ?, 'web_dashboard')");
                        $in_stmt->bind_param('sisss', $cname, $selected_qid, $current_student, $submitted_input, $actual_teacher_output);
                        $in_stmt->execute();

                        // E. Cấp phát phần thưởng mới
                        $cur_owned = [];
                        $o_res = $conn->query("
                            SELECT test_input FROM student_testcases WHERE course_id='{$conn->real_escape_string($cname)}' AND question_id={$selected_qid} AND student_id='{$conn->real_escape_string($current_student)}'
                            UNION
                            SELECT test_input FROM student_received_testcases WHERE course_id='{$conn->real_escape_string($cname)}' AND question_id={$selected_qid} AND student_id='{$conn->real_escape_string($current_student)}'
                        ");
                        while ($row = $o_res->fetch_assoc()) {
                            $cur_owned[trim($row['test_input'])] = true;
                        }

                        $candidates = [];
                        $avail_res = $conn->query("
                            SELECT test_input, expected_output FROM question_seed_testcases WHERE course_id='{$conn->real_escape_string($cname)}' AND question_id={$selected_qid}
                            UNION
                            SELECT test_input, expected_output FROM student_testcases WHERE course_id='{$conn->real_escape_string($cname)}' AND question_id={$selected_qid} AND student_id!='{$conn->real_escape_string($current_student)}'
                        ");
                        while ($row = $avail_res->fetch_assoc()) {
                            $ti = trim($row['test_input']);
                            if (!isset($cur_owned[$ti])) {
                                $candidates[] = ['input' => $ti, 'expected' => $row['expected_output'] ?: 'N/A'];
                            }
                        }

                        if (!empty($candidates)) {
                            $reward_obj = $candidates[array_rand($candidates)];
                            $rew_input = $reward_obj['input'];
                            $rew_exp = $reward_obj['expected'];

                            $rew_stmt = $conn->prepare("INSERT INTO student_received_testcases (course_id, question_id, student_id, test_input, expected_output) VALUES (?, ?, ?, ?, ?)");
                            $rew_stmt->bind_param('sisss', $cname, $selected_qid, $current_student, $rew_input, $rew_exp);
                            $rew_stmt->execute();

                            $alert_msg = "🎉 <b>ĐÓNG GÓP THÀNH CÔNG!</b> Testcase <code>[Input = " . htmlspecialchars($submitted_input) . " ➔ Expected: {$actual_teacher_output}]</code> đã chạy khớp hoàn toàn với lời giải mẫu của thầy cô!<br><hr>" .
                                         "🎁 <b>PHẦN THƯỞNG MỚI MỞ KHÓA TỪ NGÂN HÀNG:</b><br>" .
                                         "👉 <b>Input:</b> <code style='font-size: 1.15em; font-weight: bold; color: #198754;'>" . htmlspecialchars($rew_input) . "</code><br>" .
                                         "👉 <b>Expected Output:</b> <code style='font-size: 1.15em; font-weight: bold; color: #0d6efd;'>{$rew_exp}</code><br>" .
                                         "<small class='text-muted'>Ca kiểm thử này đã được lưu vào túi đồ của bạn. Hãy dùng nó để thử nghiệm code của mình!</small>";
                            $alert_type = "success";
                        } else {
                            $alert_msg = "🎉 <b>ĐÓNG GÓP THÀNH CÔNG!</b> Bạn đã có FULL toàn bộ kho testcase của bài tập này!";
                            $alert_type = "success";
                        }
                    }
                }
            }
        }
    }
}

// TẢI DỮ LIỆU HIỂN THỊ
$my_submitted = [];
$my_received = [];
$total_tests = 0;
$total_students = 0;
$leaderboard = [];

if (!$error_db) {
    $cname = $course->shortname;
    $stmt1 = $conn->prepare("SELECT question_id, test_input, expected_output, created_at FROM student_testcases WHERE student_id = ? AND course_id = ? ORDER BY id DESC");
    $stmt1->bind_param('ss', $current_student, $cname);
    $stmt1->execute();
    $res1 = $stmt1->get_result();
    while ($r = $res1->fetch_assoc()) {
        $my_submitted[] = $r;
    }
    $stmt1->close();

    $stmt2 = $conn->prepare("SELECT question_id, test_input, expected_output, received_at FROM student_received_testcases WHERE student_id = ? AND course_id = ? ORDER BY id DESC");
    $stmt2->bind_param('ss', $current_student, $cname);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    while ($r = $res2->fetch_assoc()) {
        $my_received[] = $r;
    }
    $stmt2->close();

    $res = $conn->query("SELECT COUNT(*) AS c, COUNT(DISTINCT student_id) AS s FROM student_testcases WHERE course_id = '{$conn->real_escape_string($cname)}'");
    if ($res) {
        $row = $res->fetch_assoc();
        $total_tests = (int)$row['c'];
        $total_students = (int)$row['s'];
    }

    $res_lead = $conn->query("
        SELECT student_id, COUNT(*) AS count, MAX(created_at) AS last_submit 
        FROM student_testcases 
        WHERE course_id = '{$conn->real_escape_string($cname)}'
        GROUP BY student_id 
        ORDER BY count DESC, last_submit ASC
    ");
    if ($res_lead) {
        while ($r = $res_lead->fetch_assoc()) {
            $leaderboard[] = $r;
        }
    }
    $conn->close();
}

echo $OUTPUT->header();
?>
<style>
.stat-card { border-radius: 8px; padding: 15px; color: white; margin-bottom: 20px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
.bg-blue { background: linear-gradient(135deg, #1e3c72, #2a5298); }
.bg-green { background: linear-gradient(135deg, #11998e, #38ef7d); }
.bg-orange { background: linear-gradient(135deg, #f2994a, #f2c94c); }
.testcase-pill { font-family: monospace; padding: 3px 8px; border-radius: 4px; background: #eef2f7; border: 1px solid #d0d7de; }
.card-form { border-top: 4px solid #0d6efd; }
.badge-true { background-color: #d1e7dd; color: #0f5132; font-weight: bold; padding: 4px 8px; border-radius: 4px; }
.badge-false { background-color: #f8d7da; color: #842029; font-weight: bold; padding: 4px 8px; border-radius: 4px; }
</style>

<div class="container-fluid mt-3">
    <?php if ($alert_msg): ?>
        <div class="alert alert-<?= $alert_type ?> alert-dismissible fade show" role="alert">
            <?= $alert_msg ?>
        </div>
    <?php endif; ?>

    <?php if ($error_db): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_db) ?></div>
    <?php else: ?>
        <div class="row">
            <div class="col-md-4">
                <div class="stat-card bg-blue">
                    <h5>Testcase Bạn Đã Đóng Góp</h5>
                    <h2 class="display-4 font-weight-bold"><?= count($my_submitted) ?></h2>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card bg-green">
                    <h5>Testcase Bạn Đã Mở Khóa (Thưởng)</h5>
                    <h2 class="display-4 font-weight-bold"><?= count($my_received) ?></h2>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card bg-orange">
                    <h5>Tổng Testcase Cả Lớp</h5>
                    <h2 class="display-4 font-weight-bold"><?= $total_tests ?></h2>
                </div>
            </div>
        </div>

        <!-- FORM ĐÓNG GÓP TESTCASE ĐỐI CHIẾU VỚI QUESTION.ANSWER -->
        <div class="card shadow-sm mb-4 card-form">
            <div class="card-header bg-light">
                <h5 class="mb-0 text-primary">💡 Đóng Góp Testcase (Đối chiếu tự động với Lời giải mẫu Thầy Cô)</h5>
                <small class="text-muted">Khi bạn gửi ca kiểm thử, hệ thống sẽ chạy <code>Input</code> qua đoạn mã giải thuật chuẩn của thầy cô (<code>question.answer</code>). Nếu <code>Expected Output</code> bạn phỏng đoán trùng khớp với kết quả từ code của thầy cô thì testcase được tính là <b>PASS</b> và bạn sẽ nhận được phần thưởng!</small>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="submit_testcase">
                    <input type="hidden" name="sesskey" value="<?= sesskey() ?>">
                    <div class="form-row align-items-center">
                        <div class="col-md-4 mb-2">
                            <label class="font-weight-bold" for="qSelect">1. Chọn Bài Tập:</label>
                            <select class="form-control" id="qSelect" name="question_id" required>
                                <?php foreach ($questions as $qid => $qname): ?>
                                    <option value="<?= $qid ?>"><?= htmlspecialchars($qname) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="font-weight-bold" for="testInput">2. Dữ liệu Input:</label>
                            <input type="text" class="form-control" id="testInput" name="test_input" placeholder="Ví dụ: 12 hoặc love hoặc 17..." required>
                        </div>
                        <div class="col-md-2 mb-2">
                            <label class="font-weight-bold" for="expectedOutput">3. Expected Output:</label>
                            <select class="form-control" id="expectedOutput" name="expected_output" required>
                                <option value="True">True</option>
                                <option value="False">False</option>
                            </select>
                        </div>
                        <div class="col-md-2 mb-2" style="margin-top: 1.8rem;">
                            <button type="submit" class="btn btn-success btn-block">🚀 Gửi Testcase</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <ul class="nav nav-tabs mb-4" id="testcaseTab" role="tablist">
            <li class="nav-item">
                <a class="nav-link active" id="my-tab" data-toggle="tab" href="#my-tests" role="tab">📦 Túi Đồ Testcase Cá Nhân</a>
            </li>
            <li class="nav-item">
                <a class="nav-link" id="lead-tab" data-toggle="tab" href="#leaderboard" role="tab">🏆 Bảng Xếp Hạng Lớp</a>
            </li>
        </ul>

        <div class="tab-content" id="testcaseTabContent">
            <!-- Tab Cá Nhân -->
            <div class="tab-pane fade show active" id="my-tests" role="tabpanel">
                <div class="row">
                    <div class="col-md-6">
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-primary text-white">
                                <strong>✏️ Các Testcase Bạn Đã Đóng Góp (<?= count($my_submitted) ?>)</strong>
                            </div>
                            <div class="card-body p-0 table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="thead-light">
                                        <tr><th>Bài tập</th><th>Dữ liệu Input</th><th>Expected</th><th>Thời gian</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($my_submitted)): ?>
                                            <tr><td colspan="4" class="text-center text-muted p-3">Chưa có testcase nào. Hãy dùng form bên trên để đóng góp nhé!</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($my_submitted as $r): ?>
                                                <tr>
                                                    <td><?= isset($questions[$r['question_id']]) ? $questions[$r['question_id']] : 'Bài ID ' . $r['question_id'] ?></td>
                                                    <td><span class="testcase-pill"><?= htmlspecialchars($r['test_input']) ?></span></td>
                                                    <td>
                                                        <span class="<?= ($r['expected_output'] === 'True') ? 'badge-true' : 'badge-false' ?>">
                                                            <?= htmlspecialchars($r['expected_output'] ?: 'N/A') ?>
                                                        </span>
                                                    </td>
                                                    <td><small><?= $r['created_at'] ?></small></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card shadow-sm mb-4">
                            <div class="card-header bg-success text-white">
                                <strong>🎁 Testcase Bạn Nhận Thưởng Từ Ngân Hàng (<?= count($my_received) ?>)</strong>
                            </div>
                            <div class="card-body p-0 table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="thead-light">
                                        <tr><th>Bài tập</th><th>Dữ liệu Input</th><th>Expected</th><th>Thời gian nhận</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($my_received)): ?>
                                            <tr><td colspan="4" class="text-center text-muted p-3">Chưa có testcase thưởng. Hãy đóng góp testcase mới để mở khóa!</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($my_received as $r): ?>
                                                <tr>
                                                    <td><?= isset($questions[$r['question_id']]) ? $questions[$r['question_id']] : 'Bài ID ' . $r['question_id'] ?></td>
                                                    <td><span class="testcase-pill text-success font-weight-bold"><?= htmlspecialchars($r['test_input']) ?></span></td>
                                                    <td>
                                                        <span class="<?= ($r['expected_output'] === 'True') ? 'badge-true' : 'badge-false' ?>">
                                                            <?= htmlspecialchars($r['expected_output'] ?: 'N/A') ?>
                                                        </span>
                                                    </td>
                                                    <td><small><?= $r['received_at'] ?></small></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tab Xếp Hạng -->
            <div class="tab-pane fade" id="leaderboard" role="tabpanel">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-dark text-white">
                        <strong>🏅 Bảng Vinh Danh Đóng Góp Testcase Độc Lạ</strong>
                    </div>
                    <div class="card-body p-0 table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="thead-light">
                                <tr><th>Hạng</th><th>Sinh Viên</th><th>Số Lượng Testcase Độc Nhất</th><th>Thời Điểm Gần Nhất</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($leaderboard as $idx => $r): ?>
                                    <tr <?= ($r['student_id'] === $current_student) ? 'class="table-warning font-weight-bold"' : '' ?>>
                                        <td><?= $idx + 1 ?></td>
                                        <td><?= htmlspecialchars($r['student_id']) ?> <?= ($r['student_id'] === $current_student) ? '(Bạn)' : '' ?></td>
                                        <td><span class="badge badge-primary"><?= $r['count'] ?></span></td>
                                        <td><small><?= $r['last_submit'] ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
echo $OUTPUT->footer();
