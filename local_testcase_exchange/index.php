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

$is_teacher = has_capability('moodle/course:update', $context);
$current_student = $USER->username;

// Cấu hình CSDL
$db_host = get_config('local_testcase_exchange', 'db_host') ?: 'mariadb';
$db_user = get_config('local_testcase_exchange', 'db_user') ?: 'moodle_reader';
$db_pass = get_config('local_testcase_exchange', 'db_pass') ?: 'ReaderSecret123!';
$db_name = get_config('local_testcase_exchange', 'db_name') ?: 'testcase_store';

$conn = @new mysqli($db_host, $db_user, $db_pass, $db_name, 3306);
$error_db = null;
if ($conn->connect_error) {
    $error_db = "Không thể kết nối CSDL testcase_store: " . $conn->connect_error;
}

$my_submitted = [];
$my_received = [];
$total_tests = 0;
$total_students = 0;
$leaderboard = [];

if (!$error_db) {
    // 1. Dữ liệu cá nhân sinh viên
    $stmt1 = $conn->prepare("SELECT question_id, test_input, created_at FROM student_testcases WHERE student_id = ? AND course_id = ? ORDER BY id DESC");
    $cname = $course->shortname;
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

    // 2. Thống kê chung
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
</style>

<div class="container-fluid mt-3">
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
                                        <tr><th>Câu hỏi (ID)</th><th>Dữ liệu testcase</th><th>Thời gian</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($my_submitted)): ?>
                                            <tr><td colspan="3" class="text-center text-muted p-3">Bạn chưa nộp testcase nào. Hãy vào Quiz làm bài nhé!</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($my_submitted as $r): ?>
                                                <tr>
                                                    <td>Bài ID: <?= $r['question_id'] ?></td>
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
                                        <tr><th>Câu hỏi (ID)</th><th>Dữ liệu testcase</th><th>Thời gian nhận</th></tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($my_received)): ?>
                                            <tr><td colspan="3" class="text-center text-muted p-3">Chưa có testcase thưởng. Hãy nộp testcase hợp lệ để nhận thưởng!</td></tr>
                                        <?php else: ?>
                                            <?php foreach ($my_received as $r): ?>
                                                <tr>
                                                    <td>Bài ID: <?= $r['question_id'] ?></td>
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
