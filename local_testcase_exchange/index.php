<?php
require_once(__DIR__ . '/../../config.php');
require_login();

$courseid = optional_param('course', 2, PARAM_INT);
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

$PAGE->set_url(new moodle_url('/local/testcase_exchange/index.php', ['course' => $courseid]));
$PAGE->set_context(context_course::instance($courseid));
$PAGE->set_title(get_string('heading_dashboard', 'local_testcase_exchange'));
$PAGE->set_heading($course->fullname . ' - ' . get_string('heading_dashboard', 'local_testcase_exchange'));

// Lấy cấu hình DB từ Site Administration (nếu chưa lưu thì lấy mặc định)
$db_host = get_config('local_testcase_exchange', 'db_host') ?: 'mariadb';
$db_user = get_config('local_testcase_exchange', 'db_user') ?: 'moodle_reader';
$db_pass = get_config('local_testcase_exchange', 'db_pass') ?: 'ReaderSecret123!';
$db_name = get_config('local_testcase_exchange', 'db_name') ?: 'testcase_store';

$conn = @new mysqli($db_host, $db_user, $db_pass, $db_name, 3306);
$error_db = null;
if ($conn->connect_error) {
    $error_db = "Không thể kết nối CSDL testcase_store: " . $conn->connect_error;
}

$total_tests = 0;
$total_students = 0;
$jobe_stats = [];
$leaderboard = [];
$recent_tests = [];

if (!$error_db) {
    $res = $conn->query("SELECT COUNT(*) AS c, COUNT(DISTINCT student_id) AS s FROM student_testcases");
    if ($res) {
        $row = $res->fetch_assoc();
        $total_tests = (int)$row['c'];
        $total_students = (int)$row['s'];
    }

    $res_jobe = $conn->query("SELECT jobe_server, COUNT(*) AS c FROM student_testcases GROUP BY jobe_server");
    if ($res_jobe) {
        while ($r = $res_jobe->fetch_assoc()) {
            $jobe_stats[$r['jobe_server']] = (int)$r['c'];
        }
    }

    $res_lead = $conn->query("
        SELECT student_id, COUNT(*) AS count, MAX(created_at) AS last_submit 
        FROM student_testcases 
        GROUP BY student_id 
        ORDER BY count DESC, last_submit ASC
    ");
    if ($res_lead) {
        while ($r = $res_lead->fetch_assoc()) {
            $leaderboard[] = $r;
        }
    }

    $res_list = $conn->query("
        SELECT id, course_id, question_id, student_id, test_input, jobe_server, created_at 
        FROM student_testcases 
        ORDER BY id DESC LIMIT 50
    ");
    if ($res_list) {
        while ($r = $res_list->fetch_assoc()) {
            $recent_tests[] = $r;
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
.bg-purple { background: linear-gradient(135deg, #6a11cb, #2575fc); }
.table-hover tbody tr:hover { background-color: rgba(0,0,0,0.03); }
</style>

<div class="container-fluid mt-3">
    <?php if ($error_db): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_db) ?></div>
    <?php else: ?>
        <div class="row">
            <div class="col-md-4">
                <div class="stat-card bg-blue">
                    <h3>Tổng Testcase Đóng Góp</h3>
                    <h1 class="display-4 font-weight-bold"><?= $total_tests ?></h1>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card bg-green">
                    <h3>Sinh Viên Tham Gia</h3>
                    <h1 class="display-4 font-weight-bold"><?= $total_students ?></h1>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-card bg-purple">
                    <h3>Cân Bằng Tải Jobe</h3>
                    <p class="mb-0">
                        <?php foreach ($jobe_stats as $server => $count): ?>
                            <strong><?= htmlspecialchars($server) ?>:</strong> <?= $count ?> lượt<br>
                        <?php endforeach; ?>
                    </p>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-5">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">🏆 Bảng Xếp Hạng Đóng Góp</h5>
                    </div>
                    <div class="card-body p-0 table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="thead-light">
                                <tr><th>Hạng</th><th>Sinh Viên</th><th>Số Lượng</th><th>Lần Cuối</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($leaderboard as $idx => $row): ?>
                                    <tr>
                                        <td><?= $idx + 1 ?></td>
                                        <td><strong><?= htmlspecialchars($row['student_id']) ?></strong></td>
                                        <td><span class="badge badge-success"><?= $row['count'] ?></span></td>
                                        <td><small class="text-muted"><?= $row['last_submit'] ?></small></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-md-7">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0">🕒 50 Testcase Mới Nhất</h5>
                    </div>
                    <div class="card-body p-0 table-responsive">
                        <table class="table table-sm table-striped mb-0">
                            <thead class="thead-light">
                                <tr><th>ID</th><th>Sinh Viên</th><th>Dữ Liệu Đầu Vào</th><th>Jobe</th><th>Thời Gian</th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_tests as $row): ?>
                                    <tr>
                                        <td><?= $row['id'] ?></td>
                                        <td><?= htmlspecialchars($row['student_id']) ?></td>
                                        <td><code><?= htmlspecialchars($row['test_input']) ?></code></td>
                                        <td><span class="badge badge-info"><?= htmlspecialchars($row['jobe_server']) ?></span></td>
                                        <td><small><?= $row['created_at'] ?></small></td>
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
