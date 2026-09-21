<?php
define('CLI_SCRIPT', true);
require_once('/var/www/html/config.php');

global $DB;

/**
 * Tạo template Python chạy trong sandbox Jobe
 */
function build_advanced_combinator_template($func_name, $oracle_code, $initial_seeds_str) {
    return 'import sys
import json
import pymysql
import random

# 1. NHẬN MÃ NGUỒN VÀ THÔNG TIN TỪ MOODLE
course_id = "{{ COURSE.shortname | default(\'CS102\') }}"
question_id = {{ QUESTION.id | default(100) }}
student_id = "{{ STUDENT.username | default(\'student\') }}"

student_submission = """{{ STUDENT_ANSWER | e(\'py\') }}"""

def make_json_response(fraction, html_feedback, testresults=None):
    if testresults is None:
        testresults = []
    res = {
        "fraction": fraction,
        "epiloguehtml": html_feedback,
        "testresults": testresults
    }
    print(json.dumps(res))
    sys.exit(0)

# 2. THỰC THI CODE SINH VIÊN TRONG NAMESPACE CÔ LẬP
student_env = {}
try:
    exec(student_submission, student_env)
except Exception as e:
    err_html = f"<div class=\'alert alert-danger\'>❌ <b>Lỗi biên dịch / Runtime Error trong mã nguồn của bạn:</b><br><code>{e}</code></div>"
    make_json_response(0.0, err_html)

if "' . $func_name . '" not in student_env or not callable(student_env["' . $func_name . '"]):
    err_html = "<div class=\'alert alert-danger\'>❌ <b>Không tìm thấy hàm:</b> Bạn phải định nghĩa hàm <code>' . $func_name . '</code> như đề bài yêu cầu!</div>"
    make_json_response(0.0, err_html)

student_fn = student_env["' . $func_name . '"]

# Lấy biến MY_TESTCASE
has_my_testcase = "MY_TESTCASE" in student_env
my_testcase_raw = student_env.get("MY_TESTCASE", None)
if my_testcase_raw is not None:
    my_testcase = str(my_testcase_raw).strip()
else:
    my_testcase = None

# Hàm mẫu chuẩn của giáo viên
' . $oracle_code . '

# 3. KẾT NỐI CSDL TESTCASE_STORE
DB_HOST = "mariadb"
DB_USER = "jobe_user"
DB_PASS = "JobeSecret123!"
DB_NAME = "testcase_store"

try:
    conn = pymysql.connect(host=DB_HOST, user=DB_USER, password=DB_PASS, database=DB_NAME)
    cur = conn.cursor()
except Exception as e:
    make_json_response(0.0, f"<div class=\'alert alert-danger\'>Lỗi kết nối CSDL: {e}</div>")

# Lấy danh sách testcase sinh viên đã sở hữu (tự nộp + đã nhận thưởng)
cur.execute("""
    SELECT test_input FROM student_testcases WHERE course_id=%s AND question_id=%s AND student_id=%s
    UNION
    SELECT test_input FROM student_received_testcases WHERE course_id=%s AND question_id=%s AND student_id=%s
""", (course_id, question_id, student_id, course_id, question_id, student_id))
owned_testcases = [r[0].strip() for r in cur.fetchall()]

# Testcase khởi tạo ban đầu cho bài này nếu sinh viên chưa có gì
initial_seeds = ' . $initial_seeds_str . '
test_pool = list(dict.fromkeys(initial_seeds + owned_testcases))

# 4. CHẠY CODE SINH VIÊN VỚI TOÀN BỘ TESTCASE HIỆN CÓ TRONG KHO
test_results_table = []
failed_cases = []
passed_count = 0

for t_inp in test_pool:
    try:
        expected_val = oracle_fn(t_inp)
        actual_val = student_fn(int(t_inp) if t_inp.lstrip("-").isdigit() else t_inp)
        is_pass = (expected_val == actual_val)
        if is_pass:
            passed_count += 1
            test_results_table.append([f"{t_inp}", f"{expected_val}", f"{actual_val}", "Passed"])
        else:
            failed_cases.append((t_inp, expected_val, actual_val))
            test_results_table.append([f"{t_inp}", f"{expected_val}", f"{actual_val}", "Failed"])
    except Exception as e:
        failed_cases.append((t_inp, "N/A", f"Crash: {e}"))
        test_results_table.append([f"{t_inp}", "Expected", f"Crash: {e}", "Failed"])

# Nếu code sinh viên chưa vượt qua toàn bộ testcase hiện có
if failed_cases:
    first_fail = failed_cases[0]
    html = f"""
    <div class=\'alert alert-danger\'>
        <h4>❌ Mã nguồn của bạn CHƯA VƯỢT QUA các testcase hiện có!</h4>
        <p>Code đã pass: <b>{passed_count}/{len(test_pool)}</b> ca kiểm thử.</p>
        <p>👉 <b>Ca kiểm thử bị sai đầu tiên:</b> Input = <code>{first_fail[0]}</code><br>
           - Kết quả mong đợi (Expected): <code>{first_fail[1]}</code><br>
           - Code của bạn trả về (Got): <code>{first_fail[2]}</code></p>
        <p>💡 <i>Hãy chỉnh sửa lại hàm <code>' . $func_name . '</code> để vượt qua ca kiểm thử này trước khi nộp testcase mới nhé!</i></p>
    </div>
    """
    fraction = round(passed_count / len(test_pool), 2) * 0.5 # Cho tối đa 50% nếu chưa pass hết
    make_json_response(fraction, html)

# 5. NẾU CODE ĐÃ PASS HẾT TẤT CẢ TESTCASE HIỆN CÓ -> ĐÁNH GIÁ MY_TESTCASE
html_feedback = f"""
<div class=\'alert alert-success\'>
    <h4>🎉 TUYỆT VỜI! Code của bạn đã PASS toàn bộ {len(test_pool)} testcase hiện có!</h4>
</div>
"""

reward_html = ""
fraction = 1.0

if not my_testcase:
    reward_html = """
    <div class=\'alert alert-info\'>
        💡 <b>Gợi ý:</b> Hãy khai báo thêm biến <code>MY_TESTCASE = ...</code> ở cuối bài để đóng góp 1 ca kiểm thử mới và nhận lại 1 testcase thử thách từ ngân hàng nhé!
    </div>
    """
else:
    # A) Đã từng tự nộp chưa?
    cur.execute("SELECT id FROM student_testcases WHERE course_id=%s AND question_id=%s AND student_id=%s AND test_input=%s",
                (course_id, question_id, student_id, my_testcase))
    if cur.fetchone():
        reward_html = f"<div class=\'alert alert-warning\'>⚠️ <b>MY_TESTCASE \'{my_testcase}\'</b>: Bạn đã từng nộp ca này trước đó rồi! Hãy nghĩ ca khác độc đáo hơn.</div>"
    else:
        # B) Đã từng được ngân hàng tặng chưa?
        cur.execute("SELECT id FROM student_received_testcases WHERE course_id=%s AND question_id=%s AND student_id=%s AND test_input=%s",
                    (course_id, question_id, student_id, my_testcase))
        if cur.fetchone():
            reward_html = f"<div class=\'alert alert-warning\'>⚠️ <b>MY_TESTCASE \'{my_testcase}\'</b>: Đây là ca kiểm thử bạn đã được ngân hàng thưởng trước đó, không được tính là ca mới!</div>"
        else:
            # C) Có trùng seed gốc không?
            cur.execute("SELECT id FROM question_seed_testcases WHERE course_id=%s AND question_id=%s AND test_input=%s",
                        (course_id, question_id, my_testcase))
            if cur.fetchone():
                reward_html = f"<div class=\'alert alert-warning\'>⚠️ <b>MY_TESTCASE \'{my_testcase}\'</b>: Trùng với testcase hạt giống có sẵn của hệ thống! Hãy nghĩ một edge case độc đáo hơn.</div>"
            else:
                # D) Testcase hoàn toàn mới! Ghi nhận vào student_testcases
                cur.execute("INSERT INTO student_testcases (course_id, question_id, student_id, test_input) VALUES (%s, %s, %s, %s)",
                            (course_id, question_id, student_id, my_testcase))
                conn.commit()

                # Tìm testcase thưởng từ ngân hàng
                cur.execute("""
                    SELECT test_input FROM student_testcases WHERE course_id=%s AND question_id=%s AND student_id=%s
                    UNION
                    SELECT test_input FROM student_received_testcases WHERE course_id=%s AND question_id=%s AND student_id=%s
                """, (course_id, question_id, student_id, course_id, question_id, student_id))
                updated_owned = set(r[0].strip() for r in cur.fetchall())

                cur.execute("""
                    SELECT test_input FROM question_seed_testcases WHERE course_id=%s AND question_id=%s
                    UNION
                    SELECT test_input FROM student_testcases WHERE course_id=%s AND question_id=%s AND student_id != %s
                """, (course_id, question_id, course_id, question_id, student_id))
                all_available = [r[0].strip() for r in cur.fetchall()]

                candidates = [t for t in all_available if t not in updated_owned]

                if candidates:
                    reward = random.choice(candidates)
                    cur.execute("INSERT INTO student_received_testcases (course_id, question_id, student_id, test_input) VALUES (%s, %s, %s, %s)",
                                (course_id, question_id, student_id, reward))
                    conn.commit()
                    reward_html = f"""
                    <div class=\'alert alert-primary\'>
                        <h5>🎁 PHẦN THƯỞNG: ĐÓNG GÓP TESTCASE THÀNH CÔNG!</h5>
                        <p>Ca kiểm thử <code>{my_testcase}</code> của bạn là <b>HOÀN TOÀN MỚI</b> và đã được lưu vào kho CSDL lớp học!</p>
                        <hr>
                        <p>👉 <b>Hệ thống gửi tặng lại bạn 1 ca kiểm thử mới từ ngân hàng:</b></p>
                        <h4 style=\'color: #0d6efd;\'>Input = <code>{reward}</code></h4>
                        <p>📊 <i>Tiến độ: Bạn đã mở khóa <b>{len(updated_owned) + 1}/{len(all_available)}</b> testcase của bài tập này!</i></p>
                        <p>💡 <b>Nhiệm vụ tiếp theo:</b> Hãy kiểm tra xem hàm <code>' . $func_name . '</code> của bạn có chạy đúng với testcase <code>{reward}</code> này không, cập nhật lại code và tiếp tục đóng góp ca khác nhé!</p>
                    </div>
                    """
                else:
                    reward_html = f"""
                    <div class=\'alert alert-success\'>
                        <h5>🏆 CHÚC MỪNG! BẠN ĐÃ MỞ KHÓA FULL TESTCASE!</h5>
                        <p>Ca kiểm thử <code>{my_testcase}</code> đã được lưu. Bạn đã thu thập đủ toàn bộ kho testcase của bài tập này!</p>
                    </div>
                    """

conn.close()
make_json_response(fraction, html_feedback + reward_html)
';
}

// Oracle cho Bài 1: Số nguyên tố
$oracle_prime = '
def oracle_fn(x):
    n = int(x)
    if n <= 1:
        return False
    for i in range(2, int(n**0.5) + 1):
        if n % i == 0:
            return False
    return True
';

// Oracle cho Bài 2: Palindrome
$oracle_palin = '
def oracle_fn(x):
    s = str(x)
    return s == s[::-1]
';

$preload_prime = '# ==================== PHẦN 1: CHƯƠNG TRÌNH ====================
def is_prime(n):
    """
    Hàm kiểm tra số nguyên n có phải số nguyên tố hay không (True/False).
    Hãy hoàn thiện thuật toán để vượt qua tất cả testcase trong kho!
    """
    if n <= 1:
        return False
    for i in range(2, int(n**0.5) + 1):
        if n % i == 0:
            return False
    return True

# ==================== PHẦN 2: TESTCASE ĐÓNG GÓP ====================
# Đóng góp 1 testcase số nguyên độc nhất chưa từng có để nhận testcase thưởng mới
MY_TESTCASE = 19
';

$preload_palin = '# ==================== PHẦN 1: CHƯƠNG TRÌNH ====================
def is_palindrome(s):
    """
    Hàm kiểm tra chuỗi s có phải là chuỗi đối xứng (Palindrome) hay không (True/False).
    """
    return s == s[::-1]

# ==================== PHẦN 2: TESTCASE ĐÓNG GÓP ====================
# Đóng góp 1 testcase chuỗi độc nhất chưa từng có để nhận testcase thưởng mới
MY_TESTCASE = "civic"
';

$t1 = build_advanced_combinator_template('is_prime', $oracle_prime, "['2', '3', '4']");
$t2 = build_advanced_combinator_template('is_palindrome', $oracle_palin, "['radar', 'hello']");

// Cập nhật câu 119
$DB->set_field('question_coderunner_options', 'template', $t1, ['questionid' => 119]);
$DB->set_field('question_coderunner_options', 'answerpreload', $preload_prime, ['questionid' => 119]);
$DB->set_field('question_coderunner_options', 'iscombinatortemplate', 1, ['questionid' => 119]);
$DB->set_field('question_coderunner_options', 'grader', 'TemplateGrader', ['questionid' => 119]);
$DB->set_field('question_coderunner_options', 'templateparams', '', ['questionid' => 119]);
$DB->set_field('question_coderunner_options', 'templateparamsevald', '', ['questionid' => 119]);
$DB->set_field('question_coderunner_options', 'templateparamslang', 'None', ['questionid' => 119]);
$DB->set_field('question_coderunner_options', 'hoisttemplateparams', 0, ['questionid' => 119]);

// Cập nhật câu 120
$DB->set_field('question_coderunner_options', 'template', $t2, ['questionid' => 120]);
$DB->set_field('question_coderunner_options', 'answerpreload', $preload_palin, ['questionid' => 120]);
$DB->set_field('question_coderunner_options', 'iscombinatortemplate', 1, ['questionid' => 120]);
$DB->set_field('question_coderunner_options', 'grader', 'TemplateGrader', ['questionid' => 120]);
$DB->set_field('question_coderunner_options', 'templateparams', '', ['questionid' => 120]);
$DB->set_field('question_coderunner_options', 'templateparamsevald', '', ['questionid' => 120]);
$DB->set_field('question_coderunner_options', 'templateparamslang', 'None', ['questionid' => 120]);
$DB->set_field('question_coderunner_options', 'hoisttemplateparams', 0, ['questionid' => 120]);

// Cập nhật đề bài trực quan
$desc_prime = '<p><b>Yêu cầu:</b> Viết hàm <code>is_prime(n)</code> nhận vào số nguyên <code>n</code> và trả về <code>True</code> nếu là số nguyên tố, ngược lại <code>False</code>.</p>
<p><b>Quy trình luyện tập:</b></p>
<ol>
  <li>Viết mã nguồn hàm <code>is_prime(n)</code>.</li>
  <li>Khai báo biến <code>MY_TESTCASE = ...</code> để đóng góp 1 ca kiểm thử mới cho lớp học.</li>
  <li>Khi code của bạn chạy đúng các testcase hiện có và ca kiểm thử đóng góp là hợp lệ, hệ thống sẽ <b>tặng thưởng cho bạn 1 ca kiểm thử hiểm hóc từ ngân hàng</b>.</li>
  <li>Hãy kiểm tra xem code của bạn có xử lý được ca kiểm thử vừa nhận không, cập nhật lại code và tiếp tục đóng góp!</li>
</ol>';

$desc_palin = '<p><b>Yêu cầu:</b> Viết hàm <code>is_palindrome(s)</code> nhận vào chuỗi ký tự <code>s</code> và trả về <code>True</code> nếu là chuỗi đối xứng, ngược lại <code>False</code>.</p>
<p><b>Quy trình luyện tập:</b> Tương tự bài 1, hãy nộp code kèm biến <code>MY_TESTCASE = ...</code> để mở khóa các testcase chuỗi đối xứng từ ngân hàng.</p>';

$DB->set_field('question', 'questiontext', $desc_prime, ['id' => 119]);
$DB->set_field('question', 'questiontext', $desc_palin, ['id' => 120]);

echo "=== ĐÃ CẬP NHẬT HOÀN TOÀN QUY TRÌNH HỌC TẬP: NỘP CODE + ĐÓNG GÓP TESTCASE + MỞ KHÓA TESTCASE MỚI ===\n";
