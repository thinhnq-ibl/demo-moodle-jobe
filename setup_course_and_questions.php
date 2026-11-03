<?php
define('CLI_SCRIPT', true);
require_once('/var/www/html/config.php');
require_once('/var/www/html/course/lib.php');
require_once('/var/www/html/user/lib.php');
require_once('/var/www/html/lib/questionlib.php');
require_once('/var/www/html/mod/quiz/locallib.php');

global $DB, $CFG;

echo "=== 1. TẠO KHÓA HỌC MINI: CS102 ===\n";
$course = $DB->get_record('course', ['shortname' => 'CS102']);
if (!$course) {
    $cd = new stdClass();
    $cd->fullname = 'Lập trình Cơ bản & Đóng góp Testcase';
    $cd->shortname = 'CS102';
    $cd->category = 1;
    $cd->format = 'topics';
    $cd->numsections = 2;
    $cd->summary = 'Khóa học thực hành nộp testcase và nhận testcase chéo từ ngân hàng đề bài.';
    $course = create_course($cd);
    echo "Đã tạo khóa học CS102 (ID: {$course->id})\n";
} else {
    echo "Khóa học CS102 đã tồn tại (ID: {$course->id})\n";
}

// Ghi danh học viên student1, student2
$enrol = enrol_get_plugin('manual');
$instances = enrol_get_instances($course->id, true);
$manual = current(array_filter($instances, fn($i) => $i->enrol === 'manual'));
$role = $DB->get_record('role', ['shortname' => 'student']);

foreach ([['student1', 'Nguyễn', 'Văn An'], ['student2', 'Trần', 'Thị Bình']] as $u) {
    $user = $DB->get_record('user', ['username' => $u[0]]);
    if ($user) {
        $enrol->enrol_user($manual, $user->id, $role->id);
    }
}

// 2. TẠO QUIZ
echo "=== 2. TẠO QUIZ CHO KHÓA HỌC ===\n";
$quiz = $DB->get_record('quiz', ['course' => $course->id, 'name' => 'Mini Quiz: Kiểm thử tự động']);
if (!$quiz) {
    $qd = new stdClass();
    $qd->course = $course->id;
    $qd->name = 'Mini Quiz: Kiểm thử tự động';
    $qd->intro = '<p>Hãy nộp các ca kiểm thử (testcase) cho 2 bài toán dưới đây. Mỗi testcase đúng sẽ giúp bạn nhận lại 1 testcase mới từ ngân hàng!</p>';
    $qd->introformat = FORMAT_HTML;
    $qd->preferredbehaviour = 'adaptive';
    $qd->grade = 10.0;
    $qd->sumgrades = 2.0;
    $qd->timecreated = time();
    $qd->timemodified = time();
    $quizid = $DB->insert_record('quiz', $qd);
    $DB->insert_record('quiz_sections', ['quizid' => $quizid, 'firstslot' => 1, 'heading' => '', 'shufflequestions' => 0]);

    $mod = $DB->get_record('modules', ['name' => 'quiz']);
    $cm = new stdClass();
    $cm->course = $course->id;
    $cm->module = $mod->id;
    $cm->instance = $quizid;
    $cm->section = 1;
    $cm->added = time();
    $cm->visible = 1;
    $cmid = add_course_module($cm);
    course_add_cm_to_section($course, $cmid, 1);
    $quiz = $DB->get_record('quiz', ['id' => $quizid]);
    echo "Đã tạo Quiz (ID: {$quiz->id})\n";
} else {
    echo "Quiz đã tồn tại (ID: {$quiz->id})\n";
}

// 3. TẠO HOẶC LẤY DANH MỤC CÂU HỎI
$coursecontext = context_course::instance($course->id);
$cat = $DB->get_record('question_categories', ['contextid' => $coursecontext->id, 'name' => 'Mặc định cho CS102']);
if (!$cat) {
    $cat = new stdClass();
    $cat->name = 'Mặc định cho CS102';
    $cat->contextid = $coursecontext->id;
    $cat->info = 'Danh mục câu hỏi cho khóa học CS102';
    $cat->infoformat = FORMAT_HTML;
    $cat->stamp = make_unique_id_code();
    $cat->parent = 0;
    $cat->sortorder = 1;
    $cat->id = $DB->insert_record('question_categories', $cat);
}

// 4. TEMPLATE CHẤM BÀI VÀ PHÁT TESTCASE TỪ NGÂN HÀNG
function get_testcase_template($validation_code) {
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

    # 1. Kiem tra sinh vien da tung nop testcase nay cho bai nay chua
    cur.execute("SELECT id FROM student_testcases WHERE course_id=%s AND question_id=%s AND student_id=%s AND test_input=%s",
                (course_id, question_id, student_id, student_input))
    if cur.fetchone():
        print(f"⚠️ Ban da tung nop testcase \'{student_input}\' cho bai nay roi.")
        conn.close()
        sys.exit(0)

    # 2. Ghi nhan testcase moi vao kho student_testcases
    cur.execute("INSERT INTO student_testcases (course_id, question_id, student_id, test_input) VALUES (%s, %s, %s, %s)",
                (course_id, question_id, student_id, student_input))
    conn.commit()

    print(f"✅ TESTCASE HOP LE! Ca kiem thu \'{student_input}\' da vuot qua kiem tra va duoc ghi nhan.")

    # 3. Tim testcase trong ngan hang (seed + cua cac ban khac) chua tung nop va chua tung duoc tang
    # Danh sach testcase sinh vien da so huu (da nop hoac da duoc tang)
    cur.execute("""
        SELECT test_input FROM student_testcases WHERE course_id=%s AND question_id=%s AND student_id=%s
        UNION
        SELECT test_input FROM student_received_testcases WHERE course_id=%s AND question_id=%s AND student_id=%s
    """, (course_id, question_id, student_id, course_id, question_id, student_id))
    owned = set(r[0].strip() for r in cur.fetchall())

    # Lay tat ca testcase kha dung tu ca 2 nguon: ngan hang de (seed) + do sinh vien khac dong gop
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

// ĐỊNH NGHĨA 2 BÀI TẬP VÀ NGÂN HÀNG TESTCASE
$questions_data = [
    [
        'name' => 'Bài 01: Kiểm tra Số Nguyên Tố (Prime Number)',
        'text' => '<p><b>Đề bài:</b> Viết chương trình kiểm tra một số nguyên <code>n</code> có phải là số nguyên tố hay không.</p>
                   <p><b>Yêu cầu sinh viên:</b> Nhập vào 1 số nguyên <code>n</code> (ví dụ: <code>7</code>, <code>-5</code>, <code>1</code>) để làm testcase kiểm thử bài toán.</p>',
        'validation' => '
try:
    val = int(student_input)
except ValueError:
    print("❌ Testcase khong hop le! Vui long nhap dung 1 so nguyen.")
    sys.exit(0)
',
        'seeds' => ['2', '3', '7', '11', '13', '17', '1', '0', '-5', '4', '9', '15', '97']
    ],
    [
        'name' => 'Bài 02: Kiểm tra Chuỗi Đối Xứng (Palindrome)',
        'text' => '<p><b>Đề bài:</b> Viết chương trình kiểm tra một chuỗi ký tự có phải là Palindrome (chuỗi đối xứng) hay không.</p>
                   <p><b>Yêu cầu sinh viên:</b> Nhập vào một chuỗi ký tự bất kỳ để làm testcase kiểm thử (ví dụ: <code>radar</code>, <code>hello</code>, <code>racecar</code>).</p>',
        'validation' => '
if len(student_input) < 1:
    print("❌ Testcase khong duoc de trong!")
    sys.exit(0)
',
        'seeds' => ['radar', 'racecar', 'level', 'madam', 'hello', 'python', 'noon', '12321', 'abccba', 'world']
    ]
];

// 5. TẠO CÂU HỎI VÀ GẮN VÀO QUIZ
$slot = 1;
foreach ($questions_data as $qinfo) {
    $existing = $DB->get_record('question', ['name' => $qinfo['name']]);
    if ($existing) {
        $qid = $existing->id;
        echo "Câu hỏi đã có: {$qinfo['name']} (ID: {$qid})\n";
    } else {
        $q = new stdClass();
        $q->name = $qinfo['name'];
        $q->questiontext = $qinfo['text'];
        $q->questiontextformat = FORMAT_HTML;
        $q->generalfeedback = '';
        $q->generalfeedbackformat = FORMAT_HTML;
        $q->defaultmark = 1.0;
        $q->penalty = 0.0;
        $q->qtype = 'coderunner';
        $q->length = 1;
        $q->stamp = make_unique_id_code();
        $q->version = make_unique_id_code();
        $q->hidden = 0;
        $q->timecreated = time();
        $q->timemodified = time();
        $q->createdby = 2;
        $q->modifiedby = 2;
        $qid = $DB->insert_record('question', $q);

        $entry = new stdClass();
        $entry->questioncategoryid = $cat->id;
        $entry->ownerid = 2;
        $entryid = $DB->insert_record('question_bank_entries', $entry);

        $version = new stdClass();
        $version->questionbankentryid = $entryid;
        $version->version = 1;
        $version->questionid = $qid;
        $version->status = 'ready';
        $DB->insert_record('question_versions', $version);

        $opt = new stdClass();
        $opt->questionid = $qid;
        $opt->coderunnertype = 'python3';
        $opt->prototypetype = 0;
        $opt->allornothing = 1;
        $opt->penaltyregime = '0';
        $opt->precheck = 0;
        $opt->hidecheck = 0;
        $opt->showsource = 0;
        $opt->answerboxlines = 3;
        $opt->answerboxcolumns = 80;
        $opt->answer = $qinfo['seeds'][0];
        $opt->template = get_testcase_template($qinfo['validation']);
        $opt->iscombinatortemplate = 1;
        $opt->language = 'python3';
        $opt->grader = 'TemplateGrader';
        $opt->hoisttemplateparams = 1;
        $opt->templateparamslang = 'None';
        $opt->templateparamsevalpertry = 0;
        $opt->templateparamsevald = '{}';
        $DB->insert_record('question_coderunner_options', $opt);
        echo "Đã tạo câu hỏi: {$qinfo['name']} (ID: {$qid})\n";
    }

    // Gắn câu hỏi vào Quiz slot
    $qslot = $DB->get_record('quiz_slots', ['quizid' => $quiz->id, 'slot' => $slot]);
    if (!$qslot) {
        $qslot = new stdClass();
        $qslot->slot = $slot;
        $qslot->quizid = $quiz->id;
        $qslot->page = 1;
        $qslot->displaynumber = (string)$slot;
        $qslot->maxmark = 1.0;
        $slotid = $DB->insert_record('quiz_slots', $qslot);

        // Moodle 4.x question_references
        $qref = new stdClass();
        $qref->usingcontextid = $coursecontext->id;
        $qref->component = 'mod_quiz';
        $qref->questionarea = 'slot';
        $qref->itemid = $slotid;
        
        $ventry = $DB->get_record('question_versions', ['questionid' => $qid]);
        $qref->questionbankentryid = $ventry->questionbankentryid;
        $qref->version = null; // Always use latest
        $DB->insert_record('question_references', $qref);
        echo "Đã gắn câu hỏi vào Quiz tại Slot {$slot}\n";
    }
    $slot++;

    // Nạp ngân hàng testcase mẫu (Seeds) vào DB testcase_store
    $conn = new mysqli('mariadb', 'root', 'rootpassword', 'testcase_store');
    foreach ($qinfo['seeds'] as $seed) {
        $check = $conn->query("SELECT id FROM question_seed_testcases WHERE course_id='CS102' AND question_id={$qid} AND test_input='{$seed}'");
        if ($check->num_rows == 0) {
            $stmt = $conn->prepare("INSERT INTO question_seed_testcases (course_id, question_id, test_input) VALUES (?, ?, ?)");
            $cid = 'CS102';
            $stmt->bind_param('sis', $cid, $qid, $seed);
            $stmt->execute();
        }
    }
    $conn->close();
    echo "Đã nạp " . count($qinfo['seeds']) . " testcase hạt giống vào ngân hàng cho câu hỏi {$qid}!\n";
}

echo "=== HOÀN TẤT TẠO KHÓA HỌC & NGÂN HÀNG TESTCASE! ===\n";
