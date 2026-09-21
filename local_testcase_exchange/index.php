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

// Lấy danh sách câu hỏi CodeRunner trong môn học
$questions = [
    119 => 'Bài 01: Kiểm tra Số Nguyên Tố (Prime Number)',
    120 => 'Bài 02: Kiểm tra Chuỗi Đối Xứng (Palindrome)',
];

// XỬ LÝ NỘP TESTCASE MỚI TỪ DASHBOARD (KHÔNG CHẠM VÀO DATABASE MOODLE)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_testcase' && confirm_sesskey()) {
    $selected_qid = required_param('question_id', PARAM_INT);
    $submitted_testcase = trim(required_param('test_input', PARAM_RAW));

    if (empty($submitted_testcase)) {
        $alert_msg = "❌ Vui lòng nhập dữ liệu testcase!";
        $alert_type = "danger";
    } else {
        // Kiểm tra hợp lệ cơ bản
        $valid = true;
        if ($selected_qid === 119) {
            if (!is_numeric($submitted_testcase) || strpos($submitted_testcase, '.') !== false) {
                $alert_msg = "❌ Testcase cho bài Số Nguyên Tố phải là một số nguyên hợp lệ!";
                $alert_type = "danger";
                $valid = false;
            }
        }

        if ($valid && !$error_db) {
            $cname = $course->shortname;

            // 1. Kiểm tra sinh viên đã từng nộp chưa
            $st1 = $conn->prepare("SELECT id FROM student_testcases WHERE course_id=? AND question_id=? AND student_id=? AND test_input=?");
            $st1->bind_param('siss', $cname, $selected_qid, $current_student, $submitted_testcase);
            $st1->execute();
            if ($st1->get_result()->fetch_assoc()) {
                $alert_msg = "⚠️ Bạn đã từng nộp testcase <code>" . htmlspecialchars($submitted_testcase) . "</code> cho bài này rồi!";
                $alert_type = "warning";
            } else {
                // 2. Kiểm tra có trùng testcase đã được ngân hàng tặng không
                $st2 = $conn->prepare("SELECT id FROM student_received_testcases WHERE course_id=? AND question_id=? AND student_id=? AND test_input=?");
                $st2->bind_param('siss', $cname, $selected_qid, $current_student, $submitted_testcase);
                $st2->execute();
                if ($st2->get_result()->fetch_assoc()) {
                    $alert_msg = "❌ <b>Không được tính là ca mới!</b> Ca kiểm thử <code>" . htmlspecialchars($submitted_testcase) . "</code> là testcase bạn đã được hệ thống thưởng trước đó.";
                    $alert_type = "danger";
                } else {
                    // 3. Kiểm tra có trùng seed gốc không
                    $st3 = $conn->prepare("SELECT id FROM question_seed_testcases WHERE course_id=? AND question_id=? AND test_input=?");
                    $st3->bind_param('sis', $cname, $selected_qid, $submitted_testcase);
                    $st3->execute();
                    if ($st3->get_result()->fetch_assoc()) {
                        $alert_msg = "❌ <b>Trùng testcase hạt giống!</b> Ca kiểm thử <code>" . htmlspecialchars($submitted_testcase) . "</code> đã có sẵn trong ngân hàng chuẩn của bài tập.";
                        $alert_type = "warning";
                    } else {
                        // 4. Hợp lệ! Ghi nhận vào student_testcases
                        $in_stmt = $conn->prepare("INSERT INTO student_testcases (course_id, question_id, student_id, test_input, jobe_server) VALUES (?, ?, ?, ?, 'web_dashboard')");
                        $in_stmt->bind_param('siss', $cname, $selected_qid, $current_student, $submitted_testcase);
                        $in_stmt->execute();

                        // 5. Cấp phát 1 testcase thưởng mới
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
                            SELECT test_input FROM question_seed_testcases WHERE course_id='{$conn->real_escape_string($cname)}' AND question_id={$selected_qid}
                            UNION
                            SELECT test_input FROM student_testcases WHERE course_id='{$conn->real_escape_string($cname)}' AND question_id={$selected_qid} AND student_id!='{$conn->real_escape_string($current_student)}'
                        ");
                        while ($row = $avail_res->fetch_assoc()) {
                            $ti = trim($row['test_input']);
                            if (!isset($cur_owned[$ti])) {
                                $candidates[] = $ti;
                            }
                        }

                        if (!empty($candidates)) {
                            $reward = $candidates[array_rand($candidates)];
                            $rew_stmt = $conn->prepare("INSERT INTO student_received_testcases (course_id, question_id, student_id, test_input) VALUES (?, ?, ?, ?)");
                            $rew_stmt->bind_param('siss', $cname, $selected_qid, $current_student, $reward);
                            $rew_stmt->execute();

                            $alert_msg = "🎉 <b>ĐÓNG GÓP THÀNH CÔNG!</b> Ca kiểm thử <code>" . htmlspecialchars($submitted_testcase) . "</code> là độc nhất!<br>" .
                                         "🎁 <b>Phần thưởng mới từ ngân hàng:</b> <code style='font-size: 1.2em; font-weight: bold; color: #198754;'>Input = " . htmlspecialchars($reward) . "</code> (Đã thêm vào túi đồ của bạn).";
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
    $stmt1 = $conn->prepare("SELECT question_id, test_input, created_at FROM student_testcases WHERE student_id = ? AND course_id = ? ORDER BY id DESC");
    $stmt1->bind_param('ss', $current_student, $cname);
    $stmt1->execute();
    $res1 = $stmt1->get_result();
    while ($r = $res1->fetch_assoc()) {
        $my_submitted[] = $r;
    }
    $stmt1->close();

    $stmt2 = $conn->prepare("SELECT question_id, test_input, received_at FROM student_received_testcases WHERE student_id = ? AND course_id = ? ORDER BY id DESC");
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

        <!-- KHU VỰC ĐÓNG GÓP TESTCASE ĐỘC LẬP (KHÔNG GHI VÀO MOODLE DB) -->
        <div class="card shadow-sm mb-4 card-form">
            <div class="card-header bg-light">
                <h5 class="mb-0 text-primary">💡 Đóng Góp Testcase Độc Lập & Nhận Thưởng Ngân Hàng</h5>
                <small class="text-muted">Đóng góp testcase tại đây sẽ lưu trực tiếp vào kho riêng biệt <code>testcase_store</code>, hoàn toàn không làm phát sinh dữ liệu thừa hay ảnh hưởng đến bài nộp chính của Moodle.</small>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="submit_testcase">
                    <input type="hidden" name="sesskey" value="<?= sesskey() ?>">
                    <div class="form-row align-items-center">
                        <div class="col-md-4 mb-2">
                            <label class="sr-only" for="qSelect">Chọn Bài Tập</label>
                            <select class="form-control" id="qSelect" name="question_id" required>
                                <?php foreach ($questions as $qid => $qname): ?>
                                    <option value="<?= $qid ?>"><?= htmlspecialchars($qname) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-5 mb-2">
                            <label class="sr-only" for="testInput">Dữ liệu testcase</label>
                            <input type="text" class="form-control" id="testInput" name="test_input" placeholder="Nhập testcase input (ví dụ: -99, 101, racecar...)" required>
                        </div>
                        <div class="col-md-3 mb-2">
                            <button type="submit" class="btn btn-success btn-block">🚀 Gửi Testcase & Mở Khóa</button>
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
                                <strong>✏️ Các Testcase Bạn Tự Nộp (<?= count($my_submitted) ?>)</strong>
                            </div>
                            <div class="card-body p-0 table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="thead-light">
                                        <tr><th>Bài tập</th><th>Dữ liệu testcase</th><th>Thời gian</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($my_submitted)): ?>
                                            <tr><td colspan="3" class="text-center text-muted p-3">Chưa có testcase nào. Hãy dùng form bên trên để đóng góp nhé!</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($my_submitted as $r): ?>
                                                <tr>
                                                    <td><?= isset($questions[$r['question_id']]) ? $questions[$r['question_id']] : 'Bài ID ' . $r['question_id'] ?></td>
                                                    <td><span class="testcase-pill"><?= htmlspecialchars($r['test_input']) ?></span></td>
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
                                        <tr><th>Bài tập</th><th>Dữ liệu testcase</th><th>Thời gian nhận</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($my_received)): ?>
                                            <tr><td colspan="3" class="text-center text-muted p-3">Chưa có testcase thưởng. Hãy đóng góp testcase mới để mở khóa!</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($my_received as $r): ?>
                                                <tr>
                                                    <td><?= isset($questions[$r['question_id']]) ? $questions[$r['question_id']] : 'Bài ID ' . $r['question_id'] ?></td>
                                                    <td><span class="testcase-pill text-success font-weight-bold"><?= htmlspecialchars($r['test_input']) ?></span></td>
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
