#!/usr/bin/env python3
import threading
import subprocess
import time
import pymysql
import shutil
import sys

if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
    sys.stderr.reconfigure(encoding="utf-8", errors="replace")

print("================================================================")
print("🚀 BẮT ĐẦU KIỂM THỬ: 2 SINH VIÊN NỘP BÀI ĐỒNG THỜI TRÊN CỤM JOBE")
print("================================================================")

# Xóa dữ liệu cũ trong testcase_store để kiểm thử sạch sẽ
import os

DB_HOST = os.environ.get("DB_HOST", "127.0.0.1")
conn = pymysql.connect(host=DB_HOST, user="root", password="rootpassword", database="testcase_store", port=3306)
cur = conn.cursor()
cur.execute("DELETE FROM testcase_exchanges")
cur.execute("DELETE FROM student_testcases")
conn.commit()
conn.close()
print("🧹 Đã làm sạch CSDL testcase_store trước khi kiểm thử.\n")

results = {}

def submit_testcase(student_user, test_value, thread_name):
    php_code = f"""
    define('CLI_SCRIPT', true);
    require_once('/var/www/html/config.php');
    require_once('/var/www/html/question/type/coderunner/classes/sandbox.php');
    require_once('/var/www/html/question/type/coderunner/classes/jobesandbox.php');

    global $DB;
    $opt = $DB->get_record_sql("SELECT o.* FROM {{question_coderunner_options}} o JOIN {{question}} q ON q.id = o.questionid WHERE q.name = 'Testcase exchange demo' LIMIT 1");
    if (!$opt) {{
        fwrite(STDERR, "Testcase exchange demo question not found\\n");
        exit(1);
    }}
    $question_id = $opt->questionid;
    $template = $opt->template;

    // Thay thế biến ngữ cảnh Twig giả lập Moodle
    $template = str_replace("{{{{ COURSE.shortname | default('CS101') }}}}", "CS101", $template);
    $template = str_replace("{{{{ QUESTION.id | default(100) }}}}", (string) $question_id, $template);
    $template = str_replace("{{{{ STUDENT.username | default('student') }}}}", "{student_user}", $template);
    $template = str_replace("{{{{ STUDENT_ANSWER | e('py') }}}}", "{test_value}", $template);

    $sandbox = new qtype_coderunner_jobesandbox();
    $res = $sandbox->execute($template, "python3", "");
    echo $res->output;
    """
    
    docker_bin = shutil.which("docker") or "docker"
    cmd = [
        docker_bin, "exec", "moodle_app", "php", "-r", php_code
    ]
    start_t = time.time()
    proc = subprocess.run(cmd, capture_output=True, text=True)
    duration = time.time() - start_t
    results[thread_name] = {
        "student": student_user,
        "input": test_value,
        "output": proc.stdout.strip(),
        "duration": duration
    }

print("1️⃣ Đang gửi 2 bài nộp ĐỒNG THỜI (Concurrent Threads)...")
t1 = threading.Thread(target=submit_testcase, args=("student1", "-99999", "Thread-1 (student1)"))
t2 = threading.Thread(target=submit_testcase, args=("student2", "0", "Thread-2 (student2)"))

t1.start()
t2.start()

t1.join()
t2.join()

print("\n--- KẾT QUẢ NỘP BÀI ĐỒNG THỜI ---")
for t_name, data in results.items():
    print(f"\n[{t_name}] Sinh viên: {data['student']} | Testcase: {data['input']} (Thời gian: {data['duration']:.2f}s)")
    print("Phản hồi từ hệ thống:")
    print(data["output"])

print("\n" + "="*64)
print("2️⃣ THỬ NGHIỆM CHECK TRÙNG: student2 cố tình nộp lại testcase '-99999' của student1...")
results_dup = {}
submit_testcase("student2", "-99999", "Dup-Test (student2)")
print("Phản hồi khi nộp trùng:")
print(results["Dup-Test (student2)"]["output"])

print("\n" + "="*64)
print("3️⃣ KIỂM TRA TRUY XUẤT CƠ SỞ DỮ LIỆU testcase_store TRÊN MARIADB...")
conn = pymysql.connect(host=DB_HOST, user="moodle_reader", password="ReaderSecret123!", database="testcase_store", port=3306)
cur = conn.cursor()
cur.execute("SELECT id, course_id, question_id, student_id, test_input, jobe_server, created_at FROM student_testcases")
rows = cur.fetchall()

print(f"\n📊 Danh sách testcase trong testcase_store (Tổng: {len(rows)} testcase độc nhất):")
for r in rows:
    print(f"  - ID #{r[0]} | Môn: {r[1]} | QID: {r[2]} | SV: {r[3]:<10} | Input: {r[4]:<10} | Server: {r[5]} | Lúc: {r[6]}")

cur.execute("SELECT receiver_student, testcase_id, received_at FROM testcase_exchanges")
exchanges = cur.fetchall()
print(f"\n🎁 Lịch sử trao đổi testcase (Tổng: {len(exchanges)} lượt):")
for e in exchanges:
    print(f"  - Sinh viên [{e[0]}] được tặng Testcase ID #{e[1]} vào lúc {e[2]}")

conn.close()
print("\n✅ HOÀN TẤT KIỂM THỬ THÀNH CÔNG RỰC RỠ!")
