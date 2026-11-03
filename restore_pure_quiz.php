<?php
define('CLI_SCRIPT', true);
require_once('/var/www/html/config.php');

global $DB;

// Mẫu câu hỏi thuần túy: Sinh viên chỉ nộp mã nguồn giải thuật
$clean_template_prime = 'import sys

student_submission = """{{ STUDENT_ANSWER | e(\'py\') }}"""

# Thực thi code sinh viên
student_env = {}
try:
    exec(student_submission, student_env)
except Exception as e:
    print(f"Lỗi biên dịch / Runtime Error: {e}")
    sys.exit(0)

if "is_prime" not in student_env or not callable(student_env["is_prime"]):
    print("Không tìm thấy định nghĩa hàm is_prime(n)!")
    sys.exit(0)

student_fn = student_env["is_prime"]

# Nghiệm chuẩn của giáo viên
def oracle_fn(n):
    if n <= 1:
        return False
    for i in range(2, int(n**0.5) + 1):
        if n % i == 0:
            return False
    return True

# Kiểm thử với các ca cơ bản và nâng cao
test_cases = [2, 3, 4, 5, 1, 0, -5, 17, 49, 97, 100]
for n in test_cases:
    exp = oracle_fn(n)
    try:
        got = student_fn(n)
        if got != exp:
            print(f"Failed at n = {n}. Expected: {exp}, Got: {got}")
            sys.exit(0)
    except Exception as e:
        print(f"Crash at n = {n}: {e}")
        sys.exit(0)

print("All tests passed! Thuật toán của bạn hoạt động chính xác.")
';

$clean_template_palin = 'import sys

student_submission = """{{ STUDENT_ANSWER | e(\'py\') }}"""

student_env = {}
try:
    exec(student_submission, student_env)
except Exception as e:
    print(f"Lỗi biên dịch / Runtime Error: {e}")
    sys.exit(0)

if "is_palindrome" not in student_env or not callable(student_env["is_palindrome"]):
    print("Không tìm thấy định nghĩa hàm is_palindrome(s)!")
    sys.exit(0)

student_fn = student_env["is_palindrome"]

def oracle_fn(s):
    return s == s[::-1]

test_cases = ["radar", "racecar", "hello", "world", "noon", "level", "madam", "12321", "abccba"]
for s in test_cases:
    exp = oracle_fn(s)
    try:
        got = student_fn(s)
        if got != exp:
            print(f"Failed at s = \"{s}\". Expected: {exp}, Got: {got}")
            sys.exit(0)
    except Exception as e:
        print(f"Crash at s = \"{s}\": {e}")
        sys.exit(0)

print("All tests passed! Thuật toán của bạn hoạt động chính xác.")
';

$preload_code_prime = 'def is_prime(n):
    """
    Hàm kiểm tra số nguyên n có phải số nguyên tố hay không (True/False).
    Hãy viết giải thuật hoàn chỉnh để vượt qua tất cả các trường hợp kiểm thử.
    """
    if n <= 1:
        return False
    for i in range(2, int(n**0.5) + 1):
        if n % i == 0:
            return False
    return True
';

$preload_code_palin = 'def is_palindrome(s):
    """
    Hàm kiểm tra chuỗi s có phải là chuỗi đối xứng (Palindrome) hay không (True/False).
    """
    return s == s[::-1]
';

// Cập nhật câu 119
$DB->set_field('question_coderunner_options', 'template', $clean_template_prime, ['questionid' => 119]);
$DB->set_field('question_coderunner_options', 'answerpreload', $preload_code_prime, ['questionid' => 119]);
$DB->set_field('question_coderunner_options', 'iscombinatortemplate', NULL, ['questionid' => 119]);
$DB->set_field('question_coderunner_options', 'grader', 'EqualityGrader', ['questionid' => 119]);

// Cập nhật câu 120
$DB->set_field('question_coderunner_options', 'template', $clean_template_palin, ['questionid' => 120]);
$DB->set_field('question_coderunner_options', 'answerpreload', $preload_code_palin, ['questionid' => 120]);
$DB->set_field('question_coderunner_options', 'iscombinatortemplate', NULL, ['questionid' => 120]);
$DB->set_field('question_coderunner_options', 'grader', 'EqualityGrader', ['questionid' => 120]);

// Cập nhật câu hỏi trong Quiz
$desc_clean_prime = '<p><b>Yêu cầu:</b> Viết hàm <code>is_prime(n)</code> nhận vào số nguyên <code>n</code> và trả về <code>True</code> nếu là số nguyên tố, ngược lại <code>False</code>.</p>
<p><i>Lưu ý:</i> Hãy kiểm tra và thử nghiệm thuật toán của bạn với các ca kiểm thử thu thập được tại mục <b><a href="/local/testcase_exchange/index.php?course=3" target="_blank">Kho Testcase & Đổi thưởng</a></b> trước khi hoàn thành bài nộp minh chứng.</p>';

$desc_clean_palin = '<p><b>Yêu cầu:</b> Viết hàm <code>is_palindrome(s)</code> nhận vào chuỗi ký tự <code>s</code> và trả về <code>True</code> nếu là chuỗi đối xứng, ngược lại <code>False</code>.</p>
<p><i>Lưu ý:</i> Tham khảo thêm các testcase bạn đã mở khóa tại mục <b><a href="/local/testcase_exchange/index.php?course=3" target="_blank">Kho Testcase & Đổi thưởng</a></b>.</p>';

$DB->set_field('question', 'questiontext', $desc_clean_prime, ['id' => 119]);
$DB->set_field('question', 'questiontext', $desc_clean_palin, ['id' => 120]);

// Đặt Expected output cho test
$DB->set_field('question_coderunner_tests', 'expected', 'All tests passed! Thuật toán của bạn hoạt động chính xác.', ['questionid' => 119]);
$DB->set_field('question_coderunner_tests', 'expected', 'All tests passed! Thuật toán của bạn hoạt động chính xác.', ['questionid' => 120]);

echo "=== ĐÃ PHỤC HỒI QUIZ VỀ CHẾ ĐỘ NỘP CODE MINH CHỨNG THUẦN TÚY ===\n";
