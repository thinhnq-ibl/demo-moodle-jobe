<?php
require_once(__DIR__ . "/config.php");
require_login();

$courseid = optional_param("course", 2, PARAM_INT);
$course = $DB->get_record("course", ["id" => $courseid], "*", MUST_EXIST);

$PAGE->set_url(new moodle_url("/testcase_dashboard.php", ["course" => $courseid]));
$PAGE->set_context(context_course::instance($courseid));
$PAGE->set_title("Bảng Thống Kê & Xếp Hạng Đóng Góp Testcase");
$PAGE->set_heading($course->fullname . " - Thống Kê Testcase & Trao Đổi Chéo");

// Kết nối CSDL độc lập testcase_store bằng user chỉ đọc moodle_reader
$db_host = getenv("MOODLE_DB_HOST") ?: "mariadb";
$db_user = "moodle_reader";
$db_pass = "ReaderSecret123!";
$db_name = "testcase_store";

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
        $total_tests = (int)$row["c"];
        $total_students = (int)$row["s"];
    }

    $res_jobe = $conn->query("SELECT jobe_server, COUNT(*) AS c FROM student_testcases GROUP BY jobe_server");
    if ($res_jobe) {
        while ($r = $res_jobe->fetch_assoc()) {
            $jobe_stats[$r["jobe_server"]] = (int)$r["c"];
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
        ORDER BY id DESC 
        LIMIT 50
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

<div class="container-fluid my-3">
    <div class="alert alert-success d-flex justify-content-between align-items-center shadow-sm">
        <div>
            <h4 class="alert-heading mb-1">🚀 Bảng Thống Kê Testcase & Giám Sát Cụm Jobe Song Song</h4>
            <p class="mb-0 text-muted">Dữ liệu được Jobe Sandbox ghi độc lập vào CSDL <code>testcase_store</code> và Moodle truy xuất an toàn qua quyền chỉ đọc <code>moodle_reader</code>.</p>
        </div>
        <div>
            <a href="<?php echo $CFG->wwwroot; ?>/course/view.php?id=<?php echo $courseid; ?>" class="btn btn-outline-dark btn-sm">← Về môn học</a>
        </div>
    </div>

    <?php if ($error_db): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error_db); ?></div>
    <?php else: ?>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card bg-primary text-white shadow-sm border-0 h-100">
                <div class="card-body py-3">
                    <h6 class="card-title text-white-50">TỔNG TESTCASE ĐỘC NHẤT</h6>
                    <h2 class="display-6 fw-bold mb-0"><?php echo $total_tests; ?></h2>
                    <small>Đã kiểm tra chống trùng lặp</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-success text-white shadow-sm border-0 h-100">
                <div class="card-body py-3">
                    <h6 class="card-title text-white-50">SINH VIÊN THAM GIA</h6>
                    <h2 class="display-6 fw-bold mb-0"><?php echo $total_students; ?></h2>
                    <small>Đã đóng góp ca kiểm thử</small>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card bg-dark text-white shadow-sm border-0 h-100">
                <div class="card-body py-3">
                    <h6 class="card-title text-white-50">TẢI TRÊN CỤM JOBE SONG SONG</h6>
                    <div class="mt-2">
                        <?php foreach (["jobe1", "jobe2"] as $jname): ?>
                            <span class="badge bg-light text-dark me-2 py-2 px-3">
                                🖥️ <?php echo $jname; ?>: <b><?php echo $jobe_stats[$jname] ?? 0; ?></b> lượt
                            </span>
                        <?php endforeach; ?>
                    </div>
                    <small class="text-white-50 mt-1 d-block">Tự động cân bằng tải qua CodeRunner</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="mb-0 fw-bold">🏆 Bảng Xếp Hạng Đóng Góp</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 15%;">Hạng</th>
                                <th>Sinh viên</th>
                                <th class="text-center">Số Testcase</th>
                                <th>Thời gian gần nhất</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($leaderboard)): ?>
                                <tr><td colspan="4" class="text-center py-4 text-muted">Chưa có dữ liệu nộp bài</td></tr>
                            <?php else: ?>
                                <?php foreach ($leaderboard as $idx => $lead): ?>
                                    <tr>
                                        <td>
                                            <?php if ($idx == 0): ?>
                                                <span class="badge bg-warning text-dark px-2 py-1">🥇 1</span>
                                            <?php elseif ($idx == 1): ?>
                                                <span class="badge bg-secondary text-white px-2 py-1">🥈 2</span>
                                            <?php elseif ($idx == 2): ?>
                                                <span class="badge bg-danger text-white px-2 py-1">🥉 3</span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-muted px-2 py-1">#<?php echo $idx + 1; ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><b><?php echo htmlspecialchars($lead["student_id"]); ?></b></td>
                                        <td class="text-center"><span class="badge bg-primary rounded-pill fs-6"><?php echo $lead["count"]; ?></span></td>
                                        <td class="text-muted small"><?php echo htmlspecialchars($lead["last_submit"]); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold">📋 Lịch Sử Đóng Góp & Xử Lý (Gần nhất)</h5>
                    <span class="text-muted small">Tự động cập nhật</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Sinh viên</th>
                                    <th>Nội dung Testcase</th>
                                    <th>Máy chủ Jobe</th>
                                    <th>Thời điểm</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_tests)): ?>
                                    <tr><td colspan="5" class="text-center py-4 text-muted">Chưa có testcase nào được ghi nhận</td></tr>
                                <?php else: ?>
                                    <?php foreach ($recent_tests as $t): ?>
                                        <tr>
                                            <td>#<?php echo $t["id"]; ?></td>
                                            <td><span class="badge bg-info text-dark"><?php echo htmlspecialchars($t["student_id"]); ?></span></td>
                                            <td><code><?php echo htmlspecialchars($t["test_input"]); ?></code></td>
                                            <td>
                                                <span class="badge <?php echo $t["jobe_server"] == "jobe1" ? "bg-primary" : "bg-success"; ?>">
                                                    <?php echo htmlspecialchars($t["jobe_server"]); ?>
                                                </span>
                                            </td>
                                            <td class="text-muted small"><?php echo htmlspecialchars($t["created_at"]); ?></td>
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
    <?php endif; ?>
</div>

<?php
echo $OUTPUT->footer();
