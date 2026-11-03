<?php
define('CLI_SCRIPT', true);
require_once('/var/www/html/config.php');

global $DB;

// 1. Mã nguồn giải thuật chuẩn của thầy cô
$teacher_answer_prime = 'def is_prime(n):
    if n <= 1:
        return False
    for i in range(2, int(n**0.5) + 1):
        if n % i == 0:
            return False
    return True
';

$teacher_answer_palin = 'def is_palindrome(s):
    return s == s[::-1]
';

// Cập nhật trường answer trong question_coderunner_options
$DB->set_field('question_coderunner_options', 'answer', $teacher_answer_prime, ['questionid' => 119]);
$DB->set_field('question_coderunner_options', 'answer', $teacher_answer_palin, ['questionid' => 120]);

// Đồng bộ vào bảng question_solutions trong testcase_store
$conn = new mysqli('mariadb', 'root', 'rootpassword', 'testcase_store');
$st = $conn->prepare("REPLACE INTO question_solutions (question_id, func_name, solution_code) VALUES (?, ?, ?)");

$q1 = 119;
$fn1 = 'is_prime';
$st->bind_param('iss', $q1, $fn1, $teacher_answer_prime);
$st->execute();

$q2 = 120;
$fn2 = 'is_palindrome';
$st->bind_param('iss', $q2, $fn2, $teacher_answer_palin);
$st->execute();
$conn->close();

// 2. Template CodeRunner: Lấy trực tiếp {{ QUESTION.answer }} làm đối chuẩn (Differential Testing)
$template_prime_diff = 'import sys

student_submission = """{{ STUDENT_ANSWER | e(\'py\') }}"""
teacher_solution = """{{ QUESTION.answer | e(\'py\') }}"""

# Chạy code sinh viên
student_env = {}
try:
    exec(student_submission, student_env)
except Exception as e:
    print(f"Lỗi cú pháp / Runtime Error: {e}")
    sys.exit(0)

if "is_prime" not in student_env or not callable(student_env["is_prime"]):
    print("Không tìm thấy định nghĩa hàm is_prime(n)!")
    sys.exit(0)

student_fn = student_env["is_prime"]

# Chạy code thầy cô (Oracle)
teacher_env = {}
exec(teacher_solution, teacher_env)
teacher_fn = teacher_env["is_prime"]

# Tập testcase chạy đối chiếu vi sai
test_inputs = [2, 3, 4, 5, 1, 0, -5, 17, 49, 97, 100]
for n in test_inputs:
    expected = teacher_fn(n)
    try:
        actual = student_fn(n)
        if actual != expected:
            print(f"Chưa chính xác tại n = {n}. Lời giải của thầy cô trả về: {expected}, nhưng code của bạn trả về: {actual}")
            sys.exit(0)
    except Exception as e:
        print(f"Lỗi Crash tại n = {n}: {e}")
        sys.exit(0)

print("All tests passed! Code của bạn hoàn toàn chính xác khi đối chiếu với lời giải mẫu.")
';

$template_palin_diff = 'import sys

student_submission = """{{ STUDENT_ANSWER | e(\'py\') }}"""
teacher_solution = """{{ QUESTION.answer | e(\'py\') }}"""

student_env = {}
try:
    exec(student_submission, student_env)
except Exception as e:
    print(f"Lỗi cú pháp / Runtime Error: {e}")
    sys.exit(0)

if "is_palindrome" not in student_env or not callable(student_env["is_palindrome"]):
    print("Không tìm thấy định nghĩa hàm is_palindrome(s)!")
    sys.exit(0)

student_fn = student_env["is_palindrome"]

teacher_env = {}
exec(teacher_solution, teacher_env)
teacher_fn = teacher_env["is_palindrome"]

test_inputs = ["radar", "racecar", "hello", "world", "noon", "level", "madam", "12321", "abccba"]
for s in test_inputs:
    expected = teacher_fn(s)
    try:
        actual = student_fn(s)
        if actual != expected:
            print(f"Chưa chính xác tại s = \"{s}\". Lời giải của thầy cô trả về: {expected}, nhưng code của bạn trả về: {actual}")
            sys.exit(0)
    except Exception as e:
        print(f"Lỗi Crash tại s = \"{s}\": {e}")
        sys.exit(0)

print("All tests passed! Code của bạn hoàn toàn chính xác khi đối chiếu với lời giải mẫu.")
';

$DB->set_field('question_coderunner_options', 'template', $template_prime_diff, ['questionid' => 119]);
$DB->set_field('question_coderunner_options', 'template', $template_palin_diff, ['questionid' => 120]);

$DB->set_field('question_coderunner_tests', 'expected', 'All tests passed! Code của bạn hoàn toàn chính xác khi đối chiếu với lời giải mẫu.', ['questionid' => 119]);
$DB->set_field('question_coderunner_tests', 'expected', 'All tests passed! Code của bạn hoàn toàn chính xác khi đối chiếu với lời giải mẫu.', ['questionid' => 120]);

echo "=== ĐÃ CHUYỂN TOÀN BỘ SOLUTION VÀO QUESTION.ANSWER & ĐỒNG BỘ THÀNH CÔNG ===\n";
