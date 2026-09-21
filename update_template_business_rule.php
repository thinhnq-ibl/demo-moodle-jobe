<?php
define('CLI_SCRIPT', true);
require_once('/var/www/html/config.php');

global $DB;

function get_strict_testcase_template($validation_code) {
    return 'import pymysql
import sys

DB_HOST = "mariadb"
DB_USER = "jobe_user"
DB_PASS = "JobeSecret123!"
DB_NAME = "testcase_store"

course_id = "{{ COURSE.shortname | default(\'CS102\') }}"
question_id = {{ QUESTION.id | default(100) }}
student_id = "{{ STUDENT.username | default(\'student\') }}"
student_input = """{{ STUDENT_ANSWER | e(\'py\') }}""".strip()

if not student_input:
    print("❌ Vui lòng nhập dữ liệu kiểm thử (testcase)!")
    sys.exit(0)

' . $validation_code . '

try:
    conn = pymysql.connect(host=DB_HOST, user=DB_USER, password=DB_PASS, database=DB_NAME)
    cur = conn.cursor()

    # 1. KIEM TRA XEM TESTCASE NAY SINH VIEN DA SO HUU CHUA:
    # A) Da tung tu nop chua?
    cur.execute("SELECT id FROM student_testcases WHERE course_id=%s AND question_id=%s AND student_id=%s AND test_input=%s",
                (course_id, question_id, student_id, student_input))
    if cur.fetchone():
        print(f"⚠️ Ban da tung nop testcase \'{student_input}\' cho bai nay roi.")
        conn.close()
        sys.exit(0)

    # B) Da tung duoc he thong / ngan hang tang cho chua?
    cur.execute("SELECT id FROM student_received_testcases WHERE course_id=%s AND question_id=%s AND student_id=%s AND test_input=%s",
                (course_id, question_id, student_id, student_input))
    if cur.fetchone():
        print(f"❌ TESTCASE KHONG DUOC TINH LA MOI!")
        print(f"👉 Ca kiem thu \'{student_input}\' la testcase ban da duoc ngan hang thuong truoc do!")
        print("💡 Vui long tu suy nghi mot testcase doc lap moi chua co trong kho cua ban.")
        conn.close()
        sys.exit(0)

    # C) Co trung voi testcase hat giong (seed) goc cua de bai khong?
    cur.execute("SELECT id FROM question_seed_testcases WHERE course_id=%s AND question_id=%s AND test_input=%s",
                (course_id, question_id, student_input))
    if cur.fetchone():
        print(f"❌ TESTCASE DA CO TRONG NGAN HANG GOC!")
        print(f"👉 Ca kiem thu \'{student_input}\' la testcase co san cua he thong.")
        print("💡 Vui long suy nghi mot ca kiem thu goc (edge case) khac de dong gop.")
        conn.close()
        sys.exit(0)

    # 2. GHI NHAN TESTCASE MOI HOP LE
    cur.execute("INSERT INTO student_testcases (course_id, question_id, student_id, test_input) VALUES (%s, %s, %s, %s)",
                (course_id, question_id, student_id, student_input))
    conn.commit()

    print(f"✅ CHUC MUNG! Ca kiem thu \'{student_input}\' cua ban la HOAN TOAN MOI va da duoc ghi nhan!")

    # 3. TIM TESTCASE TRONG NGAN HANG DE THUONG LAI
    cur.execute("""
        SELECT test_input FROM student_testcases WHERE course_id=%s AND question_id=%s AND student_id=%s
        UNION
        SELECT test_input FROM student_received_testcases WHERE course_id=%s AND question_id=%s AND student_id=%s
    """, (course_id, question_id, student_id, course_id, question_id, student_id))
    owned = set(r[0].strip() for r in cur.fetchall())

    cur.execute("""
        SELECT test_input FROM question_seed_testcases WHERE course_id=%s AND question_id=%s
        UNION
        SELECT test_input FROM student_testcases WHERE course_id=%s AND question_id=%s AND student_id != %s
    """, (course_id, question_id, course_id, question_id, student_id))
    all_available = [r[0].strip() for r in cur.fetchall()]

    candidates = [t for t in all_available if t not in owned]

    if candidates:
        import random
        reward = random.choice(candidates)
        cur.execute("INSERT INTO student_received_testcases (course_id, question_id, student_id, test_input) VALUES (%s, %s, %s, %s)",
                    (course_id, question_id, student_id, reward))
        conn.commit()
        print("\n" + "="*50)
        print("🎁 PHAN THUONG TESTCASE MOI TU NGAN HANG:")
        print(f"👉 Testcase ban nhan duoc: {reward}")
        print(f"📊 Ban da mo khoa {len(owned) + 1}/{len(all_available)} testcase cua bai tap nay!")
        print("="*50)
    else:
        print("\n" + "="*50)
        print("🎉 Chuc mung! Ban da co FULL testcase cua bai tap nay roi! (Khong con testcase nao chua mo khoa)")
        print("="*50)

    conn.close()
except Exception as e:
    print(f"⚠️ Loi ket noi CSDL testcase: {e}")
';
}

$v1 = '
try:
    val = int(student_input)
except ValueError:
    print("❌ Testcase khong hop le! Vui long nhap dung 1 so nguyen.")
    sys.exit(0)
';

$v2 = '
if len(student_input) < 1:
    print("❌ Testcase khong duoc de trong!")
    sys.exit(0)
';

// Cập nhật câu 119 và 120
$DB->set_field('question_coderunner_options', 'template', get_strict_testcase_template($v1), ['questionid' => 119]);
$DB->set_field('question_coderunner_options', 'template', get_strict_testcase_template($v2), ['questionid' => 120]);

echo "=== ĐÃ CẬP NHẬT TEMPLATE VỚI ĐÚNG BUSINESS RULE KIỂM TRA CHẶN TESTCASE ĐÃ ĐƯỢC CHO ===\n";
