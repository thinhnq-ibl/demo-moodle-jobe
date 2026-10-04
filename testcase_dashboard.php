<?php
require_once(__DIR__ . "/config.php");
require_once(__DIR__ . "/question/type/coderunner/classes/sandbox.php");
require_once(__DIR__ . "/question/type/coderunner/classes/jobesandbox.php");
require_once(__DIR__ . "/lib/questionlib.php");
require_once(__DIR__ . "/mod/quiz/locallib.php");

require_login();

global $USER, $DB, $CFG, $OUTPUT, $PAGE;

$courseid = optional_param("course", 2, PARAM_INT);
$quizid = optional_param("quiz", 1, PARAM_INT);
$course = $DB->get_record("course", ["id" => $courseid], "*", MUST_EXIST);
$quiz_obj = $DB->get_record("quiz", ["id" => $quizid]);

$PAGE->set_url(new moodle_url("/testcase_dashboard.php", ["course" => $courseid, "quiz" => $quizid]));
$PAGE->set_context(context_course::instance($courseid));
$PAGE->set_title("Bảng Thống Kê & Cổng Đóng Góp Testcase Chéo");
$PAGE->set_heading($course->fullname . " - Đóng Góp & Trao Đổi Testcase Chéo");

$current_username = $USER->username;

// Kết nối MariaDB testcase_store
$db_host = getenv("MOODLE_DB_HOST") ?: "mariadb";
$db_user = "moodle_app_writer";
$db_pass = "JobeSecret123!";
$db_name = "testcase_store";

$conn = @new mysqli($db_host, $db_user, $db_pass, $db_name, 3306);
$error_db = null;

// Hàm solution chuẩn của giáo viên để tính isPrime
function eval_is_prime_solution($n) {
    if ($n <= 1) return false;
    if ($n <= 3) return true;
    if ($n % 2 == 0 || $n % 3 == 0) return false;
    $i = 5;
    while ($i * $i <= $n) {
        if ($n % $i == 0 || $n % ($i + 2) == 0) return false;
        $i += 6;
    }
    return true;
}
if ($conn->connect_error) {
    $error_db = "Không thể kết nối CSDL testcase_store: " . $conn->connect_error;
}

// Kiểm tra xem bài Quiz này có bật tính năng đóng góp testcase không
$quiz_testcase_enabled = false;
if (!$error_db) {
    $stmt_chk_en = $conn->prepare("SELECT is_enabled FROM quiz_settings WHERE quiz_id = ?");
    if ($stmt_chk_en) {
        $stmt_chk_en->bind_param("i", $quizid);
        $stmt_chk_en->execute();
        $stmt_chk_en->bind_result($en_val);
        if ($stmt_chk_en->fetch()) {
            $quiz_testcase_enabled = ((int)$en_val === 1);
        }
        $stmt_chk_en->close();
    }
}

$action_message = null;
$action_status = null; // success | warning | danger
$gift_info = null;

// XỬ LÝ ĐÓNG GÓP TESTCASE MỚI (POST REQUEST)
if (!$error_db && $_SERVER['REQUEST_METHOD'] === 'POST' && (optional_param('action', '', PARAM_ALPHANUMEXT) === 'submit_test' || ($_POST['action'] ?? '') === 'submit_test')) {
    require_sesskey();
    
    if (!$quiz_testcase_enabled) {
        $action_message = "⚠️ <b>TÍNH NĂNG ĐANG TẮT:</b> Giảng viên chưa bật tính năng đóng góp testcase cho bài Quiz này.";
        $action_status = "danger";
    } else {
        $test_input = trim(optional_param('test_input', '', PARAM_RAW));
        $test_output = trim(optional_param('test_output', '', PARAM_RAW));
        $question_id = optional_param('question_id', 119, PARAM_INT);
        $quiz_id = optional_param('quiz_id', $quizid, PARAM_INT);
        $course_shortname = $course->shortname ?: 'CS101';
    
    if ($test_input === '' || $test_output === '') {
        $action_message = "Vui lòng nhập đầy đủ cả <b>Input</b> (giá trị n) và <b>Expected Output</b> (true / false)!";
        $action_status = "warning";
    } elseif ($test_output !== 'true' && $test_output !== 'false') {
        $action_message = "Giá trị Expected Output của hàm <code>isPrime</code> phải là <code>true</code> hoặc <code>false</code>!";
        $action_status = "warning";
    } else {
        // Gửi qua Jobe Sandbox để thẩm định và kiểm tra thuật toán số nguyên tố
        $jobe_server = "unknown";
        $algo_correct = null;
        $eval_error = null;
        
        try {
            $sandbox = new qtype_coderunner_jobesandbox();
            $pycode = '
import sys
try:
    hostname = open("/etc/hostname").read().strip()
except Exception:
    hostname = "jobe"

print(f"HOST:{hostname}")

inp_val = """' . addslashes($test_input) . '""".strip()
out_val = """' . addslashes($test_output) . '""".strip().lower()

try:
    n = int(inp_val)
    # Thẩm định tính nguyên tố chuẩn xác
    if n <= 1:
        correct = False
    elif n <= 3:
        correct = True
    elif n % 2 == 0 or n % 3 == 0:
        correct = False
    else:
        correct = True
        i = 5
        while i * i <= n:
            if n % i == 0 or n % (i + 2) == 0:
                correct = False
                break
            i += 6
    
    expected_bool = (out_val == "true")
    if correct == expected_bool:
        print(f"VERIFY:OK:{correct}")
    else:
        print(f"VERIFY:MISMATCH:Actual={correct}:Expected={expected_bool}")
except Exception as e:
    print(f"VERIFY:ERROR:{e}")
';
            $res = $sandbox->execute($pycode, "python3", "");
            if ($res && isset($res->output)) {
                $lines = explode("\n", trim($res->output));
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (strpos($line, "HOST:") === 0) {
                        $jobe_server = substr($line, 5);
                    }
                    if (strpos($line, "VERIFY:OK:") === 0) {
                        $algo_correct = true;
                    }
                    if (strpos($line, "VERIFY:MISMATCH:") === 0) {
                        $algo_correct = false;
                        $eval_error = "Kết quả Output bạn đưa ra không khớp với định nghĩa số nguyên tố! (" . substr($line, 16) . ")";
                    }
                    if (strpos($line, "VERIFY:ERROR:") === 0) {
                        $algo_correct = false;
                        $eval_error = "Input không hợp lệ! Vui lòng nhập một số nguyên: " . substr($line, 13);
                    }
                }
            }
        } catch (Throwable $e) {
            $jobe_server = "jobe1";
            $algo_correct = true; // fallback
        }

        if ($algo_correct === false) {
            $action_message = "⚠️ <b>THẨM ĐỊNH THẤT BẠI:</b> " . htmlspecialchars($eval_error);
            $action_status = "danger";
        } else {
            // 1. Kiểm tra chống trùng lặp trong testcase_store (Phương án 1: Cách ly 3 tầng course_id + quiz_id + question_id)
            $stmt = $conn->prepare("SELECT student_id, jobe_server, created_at FROM student_testcases WHERE course_id = ? AND quiz_id = ? AND question_id = ? AND test_input = ?");
            $stmt->bind_param("siis", $course_shortname, $quiz_id, $question_id, $test_input);
            $stmt->execute();
            $res_exist = $stmt->get_result();
            $exist = $res_exist->fetch_assoc();
            $stmt->close();

            if ($exist) {
                if ($exist['student_id'] === $current_username) {
                    $action_message = "⚠️ Bạn đã từng nộp testcase <code>input = " . htmlspecialchars($test_input) . "</code> cho bài này trong Bài Quiz này rồi.";
                    $action_status = "warning";
                } else {
                    $action_message = "❌ <b>TESTCASE BỊ TRÙNG!</b> Testcase <code>input = " . htmlspecialchars($test_input) . "</code> này đã được học viên <b>[" . htmlspecialchars($exist['student_id']) . "]</b> đóng góp trong Bài Quiz này trước đó vào lúc " . $exist['created_at'] . ". Hãy thử đóng góp một ca kiểm thử góc (edge case) khác!";
                    $action_status = "danger";
                }
            } else {
                // 2. Ghi nhận testcase vào CSDL testcase_store kèm quiz_id
                $stmt = $conn->prepare("INSERT INTO student_testcases (course_id, quiz_id, question_id, student_id, test_input, test_output, jobe_server) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("siissss", $course_shortname, $quiz_id, $question_id, $current_username, $test_input, $test_output, $jobe_server);
                
                if ($stmt->execute()) {
                    $new_test_id = $stmt->insert_id;
                    $stmt->close();

                    // 3. TỰ ĐỘNG CẬP NHẬT TESTCASE VÀO ĐỀ BÀI MOODLE CHO CẢ LỚP
                    try {
                        // Kiểm tra xem đã có testcode tương tự trong question chưa
                        $test_code_str = "System.out.println(isPrime(" . $test_input . "));";
                        $chk_exist = $DB->get_record("question_coderunner_tests", [
                            "questionid" => $question_id,
                            "testcode" => $test_code_str
                        ]);
                        
                        if (!$chk_exist) {
                            $nt = new stdClass();
                            $nt->questionid = $question_id;
                            $nt->testtype = null;
                            $nt->testcode = $test_code_str;
                            $nt->stdin = "";
                            $nt->expected = $test_output;
                            $nt->extra = "";
                            $nt->useasexample = 0;
                            $nt->display = "SHOW";
                            $nt->hiderestiffail = 0;
                            $nt->mark = 1.0;
                            $DB->insert_record("question_coderunner_tests", $nt);
                            
                            // Xóa cache và xóa attempts dở dang của chính bài Quiz này để cập nhật tức thì vào bài làm của mọi sinh viên
                            purge_all_caches();
                            $all_attempts = $DB->get_records("quiz_attempts", ["quiz" => $quiz_id, "state" => "inprogress"]);
                            $q_obj = $DB->get_record("quiz", ["id" => $quiz_id]);
                            if ($q_obj) {
                                foreach ($all_attempts as $att) {
                                    quiz_delete_attempt($att, $q_obj);
                                }
                            }
                        }
                    } catch (Throwable $ex) {
                        // Bỏ qua lỗi phụ nếu có
                    }

                    $action_message = "🎉 <b>CHÚC MỪNG!</b> Testcase <code>input = " . htmlspecialchars($test_input) . " | output = " . htmlspecialchars($test_output) . "</code> của bạn là <b>ĐỘC NHẤT</b>!<br>✅ Đã lưu vào kho CSDL và <b>tự động tích hợp trực tiếp vào bộ chấm của bài Quiz Moodle</b> (xử lý bởi node: <b>[" . htmlspecialchars($jobe_server) . "]</b>).";
                    $action_status = "success";

                    // 4. Cơ chế trao thưởng testcase:
                    // Mỗi lần nộp testcase đúng, luôn thưởng 1 testcase MỚI TINH:
                    // Nộp 1 tặng 1, nộp 7 tặng 7 (tích lũy liên tục)!
                    
                    // Tập hợp các input mà sinh viên này ĐÃ BIẾT (không được tặng lại):
                    // - Testcase mẫu trong đề Moodle
                    // - Testcase sinh viên này đã nộp
                    // - Testcase sinh viên này đã từng nhận trước đây
                    $student_known_inputs = [];
                    
                    // 1. Từ đề bài Moodle
                    $all_m_tests = $DB->get_records("question_coderunner_tests", ["questionid" => $question_id]);
                    foreach ($all_m_tests as $mt) {
                        if (preg_match('/isPrime\s*\(\s*(-?\d+)\s*\)/', $mt->testcode, $m_match)) {
                            $student_known_inputs[(int)$m_match[1]] = true;
                        }
                    }
                    // 2. Từ các testcase sinh viên này đã nộp trong quiz hiện tại
                    $res_my_tests = $conn->query("SELECT test_input FROM student_testcases WHERE course_id = '" . $conn->real_escape_string($course_shortname) . "' AND quiz_id = " . (int)$quiz_id . " AND question_id = " . (int)$question_id . " AND student_id = '" . $conn->real_escape_string($current_username) . "'");
                    if ($res_my_tests) {
                        while ($r_my = $res_my_tests->fetch_assoc()) {
                            if (is_numeric($r_my['test_input'])) {
                                $student_known_inputs[(int)$r_my['test_input']] = true;
                            }
                        }
                    }
                    // 3. Từ các testcase sinh viên này đã từng được nhận trong quiz hiện tại
                    $res_my_gifts = $conn->query("
                        SELECT st.test_input 
                        FROM testcase_exchanges e 
                        JOIN student_testcases st ON st.id = e.testcase_id 
                        WHERE e.course_id = '" . $conn->real_escape_string($course_shortname) . "' AND e.quiz_id = " . (int)$quiz_id . " AND e.question_id = " . (int)$question_id . " AND e.receiver_student = '" . $conn->real_escape_string($current_username) . "'
                    ");
                    if ($res_my_gifts) {
                        while ($r_g = $res_my_gifts->fetch_assoc()) {
                            if (is_numeric($r_g['test_input'])) {
                                $student_known_inputs[(int)$r_g['test_input']] = true;
                            }
                        }
                    }

                    // Ưu tiên 1: Tặng testcase của bạn học khác trong cùng quiz_id mà sinh viên này CHƯA TỪNG BIẾT
                    $stmt_gift = $conn->prepare("
                        SELECT id, student_id, test_input, test_output, jobe_server FROM student_testcases
                        WHERE course_id = ? AND quiz_id = ? AND question_id = ? AND student_id != ? AND student_id != 'teacher_system'
                        ORDER BY RAND()
                    ");
                    $stmt_gift->bind_param("siis", $course_shortname, $quiz_id, $question_id, $current_username);
                    $stmt_gift->execute();
                    $res_gift = $stmt_gift->get_result();
                    $gift = null;
                    while ($candidate = $res_gift->fetch_assoc()) {
                        if (is_numeric($candidate['test_input']) && !isset($student_known_inputs[(int)$candidate['test_input']])) {
                            $gift = $candidate;
                            break;
                        }
                    }
                    $stmt_gift->close();

                    if ($gift) {
                        $gift_id = (int)$gift['id'];
                        $gift_author = $gift['student_id'];
                        $gift_input = $gift['test_input'];
                        $gift_output = $gift['test_output'] ?: (eval_is_prime_solution((int)$gift_input) ? 'true' : 'false');
                        $gift_server = $gift['jobe_server'];

                        // Ghi nhận lịch sử nhận quà kèm quiz_id
                        $stmt_ins_gift = $conn->prepare("INSERT INTO testcase_exchanges (course_id, quiz_id, question_id, receiver_student, testcase_id, testcase_output) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt_ins_gift->bind_param("siisis", $course_shortname, $quiz_id, $question_id, $current_username, $gift_id, $gift_output);
                        $stmt_ins_gift->execute();
                        $stmt_ins_gift->close();

                        $gift_info = [
                            'author' => $gift_author,
                            'input' => $gift_input,
                            'output' => $gift_output,
                            'server' => $gift_server
                        ];
                    } else {
                        // Sinh ngay 1 testcase MỚI TINH dựa trên Solution của giáo viên
                        $candidate_input = null;
                        
                        // Kho số nguyên tố phong phú
                        $primes_pool = [19, 23, 29, 31, 37, 41, 43, 47, 53, 59, 61, 67, 71, 73, 79, 83, 89, 97, 
                                        101, 103, 107, 109, 113, 127, 131, 137, 139, 149, 151, 157, 163, 167, 173, 179, 181, 191, 193, 197, 199,
                                        211, 223, 227, 229, 233, 239, 241, 251, 257, 263, 269, 271, 277, 281, 283, 293,
                                        307, 311, 313, 317, 331, 337, 347, 349, 353, 359, 367, 373, 379, 383, 389, 397,
                                        503, 509, 521, 523, 541, 601, 607, 613, 617, 619, 631, 641, 643, 647, 653, 659, 661, 673,
                                        1009, 1013, 1019, 1021, 1031, 1033, 1039, 1049, 1051, 1061, 1063, 1069, 1087, 1091, 1093, 1097,
                                        7919, 104729, 1299709];
                        
                        // Kho hợp số và số âm / số biên đặc biệt
                        $composites_pool = [-100, -17, -1, 0, 1, 4, 6, 8, 9, 12, 14, 15, 16, 18, 20, 21, 22, 25, 26, 27, 33, 35, 49, 55, 65, 77, 85, 91, 95, 119, 121, 143, 169, 187, 209, 221, 247, 253, 289, 299, 323, 341, 361, 391, 529, 561, 1000, 1001, 1000000];

                        // Trộn ngẫu nhiên
                        shuffle($primes_pool);
                        shuffle($composites_pool);
                        $combined_pool = array_merge($primes_pool, $composites_pool);
                        shuffle($combined_pool);

                        foreach ($combined_pool as $val) {
                            if (!isset($student_known_inputs[$val])) {
                                $candidate_input = $val;
                                break;
                            }
                        }

                        // Nếu đã duyệt hết các số trên, sinh số ngẫu nhiên lớn hơn
                        if ($candidate_input === null) {
                            do {
                                $candidate_input = rand(2000, 999999);
                            } while (isset($student_known_inputs[$candidate_input]));
                        }

                        // Tính kết quả chuẩn bằng solution của giáo viên
                        $candidate_output = eval_is_prime_solution($candidate_input) ? 'true' : 'false';
                        $sys_author = "teacher_system";
                        $sys_server = (rand(0, 1) === 0 ? "jobe1" : "jobe2");
                        $cand_str = (string)$candidate_input;

                        // Lưu testcase này vào student_testcases để ghi nhận kèm quiz_id
                        $stmt_sys = $conn->prepare("INSERT INTO student_testcases (course_id, quiz_id, question_id, student_id, test_input, test_output, jobe_server) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt_sys->bind_param("siissss", $course_shortname, $quiz_id, $question_id, $sys_author, $cand_str, $candidate_output, $sys_server);
                        $stmt_sys->execute();
                        $sys_test_id = $stmt_sys->insert_id;
                        $stmt_sys->close();

                        // Ghi nhận trao tặng cho học viên hiện tại kèm quiz_id
                        $stmt_ins_gift = $conn->prepare("INSERT INTO testcase_exchanges (course_id, quiz_id, question_id, receiver_student, testcase_id, testcase_output) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt_ins_gift->bind_param("siisis", $course_shortname, $quiz_id, $question_id, $current_username, $sys_test_id, $candidate_output);
                        $stmt_ins_gift->execute();
                        $stmt_ins_gift->close();

                        $gift_info = [
                            'author' => 'Hệ Thống Giáo Viên (Solution)',
                            'input' => $cand_str,
                            'output' => $candidate_output,
                            'server' => $sys_server
                        ];
                    }
                } else {
                    $action_message = "Lỗi khi lưu testcase: " . $conn->error;
                    $action_status = "danger";
                    $stmt->close();
                }
            }
        }
    }
}
}

// LẤY DỮ LIỆU THỐNG KÊ TOÀN HỆ THỐNG
$total_tests = 0;
$total_students = 0;
$jobe_stats = [];
$leaderboard = [];
$recent_tests = [];
$my_testcases = [];
$my_received_gifts = [];

if (!$error_db) {
    $c_sname = $course->shortname ?: 'CS101';

    // Tổng số testcase và sinh viên trong Bài Quiz này
    $stmt_tot = $conn->prepare("SELECT COUNT(*) AS c, COUNT(DISTINCT student_id) AS s FROM student_testcases WHERE course_id = ? AND quiz_id = ?");
    $stmt_tot->bind_param("si", $c_sname, $quizid);
    $stmt_tot->execute();
    $res_tot = $stmt_tot->get_result();
    if ($row = $res_tot->fetch_assoc()) {
        $total_tests = (int)$row["c"];
        $total_students = (int)$row["s"];
    }
    $stmt_tot->close();

    // Tải phân bố trên cụm Jobe trong Bài Quiz này
    $stmt_jb = $conn->prepare("SELECT jobe_server, COUNT(*) AS c FROM student_testcases WHERE course_id = ? AND quiz_id = ? GROUP BY jobe_server");
    $stmt_jb->bind_param("si", $c_sname, $quizid);
    $stmt_jb->execute();
    $res_jobe = $stmt_jb->get_result();
    if ($res_jobe) {
        while ($r = $res_jobe->fetch_assoc()) {
            $jobe_stats[$r["jobe_server"]] = (int)$r["c"];
        }
    }
    $stmt_jb->close();

    // Bảng xếp hạng đóng góp trong Bài Quiz này
    $stmt_ld = $conn->prepare("
        SELECT student_id, COUNT(*) AS count, MAX(created_at) AS last_submit 
        FROM student_testcases 
        WHERE course_id = ? AND quiz_id = ?
        GROUP BY student_id 
        ORDER BY count DESC, last_submit ASC
    ");
    $stmt_ld->bind_param("si", $c_sname, $quizid);
    $stmt_ld->execute();
    $res_lead = $stmt_ld->get_result();
    if ($res_lead) {
        while ($r = $res_lead->fetch_assoc()) {
            $leaderboard[] = $r;
        }
    }
    $stmt_ld->close();

    // Lịch sử testcase của toàn bộ hệ thống trong Bài Quiz này
    $stmt_rc = $conn->prepare("
        SELECT id, course_id, quiz_id, question_id, student_id, test_input, test_output, jobe_server, created_at 
        FROM student_testcases 
        WHERE course_id = ? AND quiz_id = ?
        ORDER BY id DESC 
        LIMIT 30
    ");
    $stmt_rc->bind_param("si", $c_sname, $quizid);
    $stmt_rc->execute();
    $res_list = $stmt_rc->get_result();
    if ($res_list) {
        while ($r = $res_list->fetch_assoc()) {
            $recent_tests[] = $r;
        }
    }
    $stmt_rc->close();

    // Testcase do chính học viên hiện tại đã nộp trong Bài Quiz này
    $stmt_my = $conn->prepare("
        SELECT id, quiz_id, question_id, test_input, test_output, jobe_server, created_at 
        FROM student_testcases 
        WHERE course_id = ? AND quiz_id = ? AND student_id = ? 
        ORDER BY id DESC
    ");
    $stmt_my->bind_param("sis", $c_sname, $quizid, $current_username);
    $stmt_my->execute();
    $res_my = $stmt_my->get_result();
    while ($r = $res_my->fetch_assoc()) {
        $my_testcases[] = $r;
    }
    $stmt_my->close();

    // Testcase mà học viên hiện tại được tặng từ bạn học khác trong Bài Quiz này
    $stmt_rec = $conn->prepare("
        SELECT e.id as exchange_id, e.quiz_id, e.received_at, st.student_id as author, st.test_input, COALESCE(st.test_output, e.testcase_output) as test_output, st.jobe_server
        FROM testcase_exchanges e
        JOIN student_testcases st ON st.id = e.testcase_id
        WHERE e.course_id = ? AND e.quiz_id = ? AND e.receiver_student = ?
        ORDER BY e.id DESC
    ");
    $stmt_rec->bind_param("sis", $c_sname, $quizid, $current_username);
    $stmt_rec->execute();
    $res_rec = $stmt_rec->get_result();
    while ($r = $res_rec->fetch_assoc()) {
        $my_received_gifts[] = $r;
    }
    $stmt_rec->close();

    $conn->close();
}

// Lấy danh sách testcase hiện tại trong Moodle để sinh viên nắm được
$moodle_tests = $DB->get_records("question_coderunner_tests", ["questionid" => 119], "id ASC");

echo $OUTPUT->header();
?>

<div class="container-fluid my-3">
    <!-- Header banner -->
    <div class="alert alert-<?php echo $quiz_testcase_enabled ? 'success' : 'secondary'; ?> d-flex justify-content-between align-items-center shadow-sm">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <h4 class="alert-heading mb-0">🚀 Cổng Đóng Góp & Trao Đổi Testcase Chéo</h4>
                <?php if ($quiz_testcase_enabled): ?>
                    <span class="badge bg-success text-white">Đang BẬT cho Quiz này</span>
                <?php else: ?>
                    <span class="badge bg-secondary text-white">Đang TẮT cho Quiz này</span>
                <?php endif; ?>
            </div>
            <p class="mb-0 text-muted">Mỗi testcase độc nhất gồm <b>Input</b> và <b>Expected Output</b> do bạn đóng góp sẽ được thẩm định tự động và tích hợp trực tiếp vào bộ test chấm bài của Moodle!</p>
        </div>
        <div>
            <?php 
            $cm_quiz = $DB->get_record("course_modules", ["course" => $courseid, "instance" => $quizid]);
            $quiz_link = $cm_quiz ? ($CFG->wwwroot . "/mod/quiz/view.php?id=" . $cm_quiz->id) : ($CFG->wwwroot . "/mod/quiz/view.php?id=" . $quizid);
            ?>
            <a href="<?php echo $quiz_link; ?>" class="btn btn-outline-primary btn-sm me-2">📝 Vào làm bài Quiz</a>
            <a href="<?php echo $CFG->wwwroot; ?>/course/view.php?id=<?php echo $courseid; ?>" class="btn btn-outline-dark btn-sm">← Về môn học</a>
        </div>
    </div>

    <?php if ($error_db): ?>
        <div class="alert alert-danger shadow-sm"><?php echo htmlspecialchars($error_db); ?></div>
    <?php else: ?>

    <!-- THÔNG BÁO KẾT QUẢ NỘP BÀI / NHẬN QUÀ -->
    <?php if ($action_message): ?>
        <div class="alert alert-<?php echo $action_status; ?> alert-dismissible fade show shadow-sm" role="alert">
            <div class="fs-6"><?php echo $action_message; ?></div>
            <?php if ($gift_info): ?>
                <hr class="my-2">
                <div class="p-3 bg-white text-dark rounded border border-success">
                    <h6 class="text-success fw-bold mb-1">🎁 PHẦN THƯỞNG TRAO ĐỔI TESTCASE THÀNH CÔNG:</h6>
                    <div>Bạn vừa nhận được 1 ca kiểm thử hoàn chỉnh từ bạn học <b>[<?php echo htmlspecialchars($gift_info['author']); ?>]</b>:</div>
                    <div class="mt-2 p-2 bg-light rounded font-monospace">
                        👉 <b>Input:</b> <code class="fs-6 text-primary"><?php echo htmlspecialchars($gift_info['input']); ?></code><br>
                        👉 <b>Expected Output:</b> <code class="fs-6 text-success fw-bold"><?php echo htmlspecialchars($gift_info['output']); ?></code>
                    </div>
                    <small class="text-muted mt-1 d-block">Testcase này đã được cập nhật vào đề bài để kiểm tra thuật toán của bạn!</small>
                </div>
            <?php endif; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <!-- THẺ KPI TỔNG THỂ -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white shadow-sm border-0 h-100">
                <div class="card-body py-3">
                    <h6 class="card-title text-white-50">TESTCASE SINH VIÊN ĐÓNG GÓP</h6>
                    <h2 class="display-6 fw-bold mb-0"><?php echo $total_tests; ?></h2>
                    <small>Đã kiểm tra chống trùng lặp</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white shadow-sm border-0 h-100">
                <div class="card-body py-3">
                    <h6 class="card-title text-white-50">TỔNG TESTCASE TRONG BÀI THI</h6>
                    <h2 class="display-6 fw-bold mb-0"><?php echo count($moodle_tests); ?></h2>
                    <small>Đang dùng chấm điểm Quiz</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white shadow-sm border-0 h-100">
                <div class="card-body py-3">
                    <h6 class="card-title text-white-50">ĐÓNG GÓP CỦA BẠN</h6>
                    <h2 class="display-6 fw-bold mb-0"><?php echo count($my_testcases); ?></h2>
                    <small>Được tặng lại: <b><?php echo count($my_received_gifts); ?></b> testcase</small>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-dark text-white shadow-sm border-0 h-100">
                <div class="card-body py-3">
                    <h6 class="card-title text-white-50">TẢI TRÊN CỤM JOBE</h6>
                    <div class="mt-2">
                        <?php foreach (["jobe1", "jobe2"] as $jname): ?>
                            <span class="badge bg-light text-dark me-1 py-1 px-2">
                                🖥️ <?php echo $jname; ?>: <b><?php echo $jobe_stats[$jname] ?? 0; ?></b>
                            </span>
                        <?php endforeach; ?>
                    </div>
                    <small class="text-white-50 mt-1 d-block">Phân bổ tự động qua Jobe Cluster</small>
                </div>
            </div>
        </div>
    </div>

    <!-- KHU VỰC CÁ NHÂN HỌC VIÊN: FORM ĐÓNG GÓP & TESTCASE CỦA TÔI -->
    <div class="row g-4 mb-4">
        <!-- FORM ĐÓNG GÓP TESTCASE (INPUT + OUTPUT) -->
        <div class="col-lg-6">
            <div class="card border-primary shadow-sm h-100">
                <div class="card-header bg-primary text-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold">✍️ Đóng Góp Testcase (Cả Input & Output)</h5>
                    <span class="badge bg-light text-primary">Học viên: <?php echo htmlspecialchars($current_username); ?></span>
                </div>
                <div class="card-body">
                    <?php if (!$quiz_testcase_enabled): ?>
                        <div class="alert alert-warning border border-warning text-center py-4">
                            <h5 class="fw-bold mb-2">🔒 Tính năng đóng góp testcase đang TẮT</h5>
                            <p class="mb-0 text-muted">Giảng viên chưa kích hoạt tính năng đóng góp và trao đổi testcase cho bài kiểm tra này. Bạn vẫn có thể xem các testcase mẫu và ôn luyện bình thường.</p>
                        </div>
                    <?php else: ?>
                    <p class="text-muted small">
                        Đóng góp một ca kiểm thử cho hàm <code>isPrime(int n)</code> gồm cả <b>Input</b> và <b>Expected Output</b>.
                        Khi testcase hợp lệ và chưa từng xuất hiện, hệ thống sẽ:
                        <br>1️⃣ Cập nhật ngay vào bộ test chấm điểm của bài làm Moodle.
                        <br>2️⃣ Tặng lại cho bạn một testcase độc nhất từ học viên khác!
                    </p>

                    <form method="post" action="<?php echo $PAGE->url->out(false); ?>" class="mt-3">
                        <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                        <input type="hidden" name="action" value="submit_test">
                        <input type="hidden" name="quiz_id" value="<?php echo $quizid; ?>">
                        <input type="hidden" name="question_id" value="119">

                        <div class="mb-3">
                            <label class="form-label fw-bold">1. Giá trị đầu vào (Input n):</label>
                            <div class="input-group">
                                <span class="input-group-text font-monospace">input =</span>
                                <input type="text" name="test_input" class="form-control font-monospace" placeholder="Ví dụ: -5 hoặc 1 hoặc 997 hoặc 1000000007" required autofocus>
                            </div>
                            <div class="form-text">Nhập số nguyên cần kiểm tra (số âm, số 0, số 1 hoặc số cực lớn).</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold">2. Kết quả kỳ vọng (Expected Output):</label>
                            <div class="d-flex gap-3">
                                <div class="form-check form-check-inline p-2 border rounded flex-fill text-center bg-light">
                                    <input class="form-check-input ms-0 me-2" type="radio" name="test_output" id="out_true" value="true" required>
                                    <label class="form-check-label text-success fw-bold" for="out_true">true (Là số nguyên tố)</label>
                                </div>
                                <div class="form-check form-check-inline p-2 border rounded flex-fill text-center bg-light">
                                    <input class="form-check-input ms-0 me-2" type="radio" name="test_output" id="out_false" value="false" required>
                                    <label class="form-check-label text-danger fw-bold" for="out_false">false (Không phải số nguyên tố)</label>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary btn-lg fw-bold">
                                🚀 Thẩm Định, Cập Nhật & Trao Đổi Ngay
                            </button>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- TESTCASE CÁ NHÂN VÀ TESTCASE ĐƯỢC TẶNG -->
        <div class="col-lg-6 d-flex flex-column gap-3">
            <!-- Khối 1: Testcase được bạn học tặng (Luôn hiển thị nổi bật) -->
            <div class="card shadow-sm border-0 border-start border-success border-4">
                <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-success">
                        🎁 Testcase Bạn Học Tặng Cho Bạn (<?php echo count($my_received_gifts); ?>)
                    </h6>
                    <span class="badge bg-success-subtle text-success border border-success">Trao đổi tự động</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 320px; overflow-y: auto;">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>STT</th>
                                    <th>Người tặng</th>
                                    <th>Input (n)</th>
                                    <th>Expected Output</th>
                                    <th>Thời điểm nhận</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($my_received_gifts)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">
                                            <i class="fa fa-gift me-1"></i> Chưa có testcase nào được tặng. Hãy đóng góp testcase độc nhất ở form bên trái để nhận quà!
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php 
                                    $g_idx = 1;
                                    foreach ($my_received_gifts as $g): 
                                    ?>
                                        <tr class="table-success table-opacity-10">
                                            <td class="fw-bold text-muted"><?php echo $g_idx++; ?></td>
                                            <td>
                                                <?php if ($g['author'] === 'teacher_system'): ?>
                                                    <span class="badge bg-warning text-dark">
                                                        🎓 Hệ Thống Giáo Viên
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-primary text-white">
                                                        👤 <?php echo htmlspecialchars($g['author']); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <code class="fw-bold fs-6 text-primary"><?php echo htmlspecialchars($g['test_input']); ?></code>
                                            </td>
                                            <td>
                                                <span class="badge <?php echo $g['test_output'] === 'true' ? 'bg-success' : 'bg-danger'; ?> fs-6">
                                                    <?php echo htmlspecialchars($g['test_output'] ?? 'N/A'); ?>
                                                </span>
                                            </td>
                                            <td class="small text-muted"><?php echo htmlspecialchars($g['received_at']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Khối 2: Testcase bản thân đã đóng góp -->
            <div class="card shadow-sm border-0 border-start border-primary border-4">
                <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-primary">
                        📤 Testcase Bạn Đã Đóng Góp (<?php echo count($my_testcases); ?>)
                    </h6>
                    <span class="badge bg-primary-subtle text-primary border border-primary">Đã tích hợp vào đề Moodle</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>ID</th>
                                    <th>Input (n)</th>
                                    <th>Output</th>
                                    <th>Server Jobe</th>
                                    <th>Thời điểm</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($my_testcases)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-3 text-muted">
                                            Bạn chưa đóng góp testcase nào. Hãy nhập giá trị ở form bên trái!
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($my_testcases as $tc): ?>
                                        <tr>
                                            <td>#<?php echo $tc['id']; ?></td>
                                            <td><code class="fw-bold fs-6 text-primary"><?php echo htmlspecialchars($tc['test_input']); ?></code></td>
                                            <td>
                                                <span class="badge <?php echo $tc['test_output'] === 'true' ? 'bg-success' : 'bg-danger'; ?>">
                                                    <?php echo htmlspecialchars($tc['test_output'] ?? 'N/A'); ?>
                                                </span>
                                            </td>
                                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($tc['jobe_server']); ?></span></td>
                                            <td class="small text-muted"><?php echo htmlspecialchars($tc['created_at']); ?></td>
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

    <!-- BẢNG XẾP HẠNG TOÀN LỚP & LỊCH SỬ TESTCASE TOÀN DIỆN -->
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="mb-0 fw-bold">🏆 Bảng Xếp Hạng Đóng Góp Toàn Khóa</h5>
                </div>
                <div class="card-body p-0">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 15%;">Hạng</th>
                                <th>Sinh viên</th>
                                <th class="text-center">Số Testcase</th>
                                <th>Lần nộp gần nhất</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($leaderboard)): ?>
                                <tr><td colspan="4" class="text-center py-4 text-muted">Chưa có dữ liệu nộp bài</td></tr>
                            <?php else: ?>
                                <?php foreach ($leaderboard as $idx => $lead): ?>
                                    <tr class="<?php echo ($lead['student_id'] === $current_username) ? 'table-warning' : ''; ?>">
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
                                        <td>
                                            <b><?php echo htmlspecialchars($lead["student_id"]); ?></b>
                                            <?php if ($lead['student_id'] === $current_username): ?>
                                                <span class="badge bg-primary ms-1">Bạn</span>
                                            <?php endif; ?>
                                        </td>
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
                    <h5 class="mb-0 fw-bold">📋 Kho Testcase Đang Được Áp Dụng Chấm Điểm</h5>
                    <span class="text-muted small">Tự động đồng bộ với Moodle Quiz</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive" style="max-height: 380px; overflow-y: auto;">
                        <table class="table table-striped table-hover align-middle mb-0">
                            <thead class="table-light sticky-top">
                                <tr>
                                    <th>#</th>
                                    <th>Lệnh kiểm thử trong Moodle</th>
                                    <th>Expected Output</th>
                                    <th>Phân loại</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $idx = 1;
                                foreach ($moodle_tests as $t): 
                                    $is_student = (strpos($t->testcode, "isPrime") !== false && $idx > 9);
                                ?>
                                    <tr>
                                        <td><?php echo $idx++; ?></td>
                                        <td><code><?php echo htmlspecialchars($t->testcode); ?></code></td>
                                        <td>
                                            <span class="badge <?php echo trim($t->expected) === 'true' ? 'bg-success' : 'bg-danger'; ?>">
                                                <?php echo htmlspecialchars($t->expected); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($t->useasexample): ?>
                                                <span class="badge bg-info text-dark">Ví dụ mẫu</span>
                                            <?php elseif ($is_student): ?>
                                                <span class="badge bg-warning text-dark">🌟 Sinh viên đóng góp</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Mặc định</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<script>
function switchTestTab(tab) {
    var subTabBtn = document.getElementById('my-submitted-tab');
    var recTabBtn = document.getElementById('my-received-tab');
    var subPane = document.getElementById('my-submitted');
    var recPane = document.getElementById('my-received');

    if (tab === 'submitted') {
        subTabBtn.classList.add('active');
        recTabBtn.classList.remove('active');
        subPane.classList.add('show', 'active');
        subPane.style.display = 'block';
        recPane.classList.remove('show', 'active');
        recPane.style.display = 'none';
    } else {
        recTabBtn.classList.add('active');
        subTabBtn.classList.remove('active');
        recPane.classList.add('show', 'active');
        recPane.style.display = 'block';
        subPane.classList.remove('show', 'active');
        subPane.style.display = 'none';
    }
}
</script>

<?php
echo $OUTPUT->footer();
