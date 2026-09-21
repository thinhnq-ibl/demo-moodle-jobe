<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Hook cài đặt tự động tạo Prototype question vào Question Bank khi cài plugin.
 */
function xmldb_local_testcase_exchange_install() {
    global $DB;

    // 1. Tìm hoặc tạo Category CR_PROTOTYPES ở System Context
    $systemcontext = context_system::instance();
    $cat = $DB->get_record('question_categories', [
        'contextid' => $systemcontext->id,
        'name' => 'CR_PROTOTYPES'
    ]);

    if (!$cat) {
        $cat = new stdClass();
        $cat->name = 'CR_PROTOTYPES';
        $cat->contextid = $systemcontext->id;
        $cat->info = 'Danh mục câu hỏi khuôn mẫu (Prototypes) hệ thống';
        $cat->infoformat = FORMAT_HTML;
        $cat->stamp = make_unique_id_code();
        $cat->parent = 0;
        $cat->sortorder = 999;
        $cat->id = $DB->insert_record('question_categories', $cat);
    }

    // 2. Kiểm tra xem prototype python3_testcase_exchange đã tồn tại chưa
    $existing = $DB->get_record('question', [
        'name' => 'PROTOTYPE_python3_testcase_exchange'
    ]);

    if (!$existing) {
        $q = new stdClass();
        $q->name = 'PROTOTYPE_python3_testcase_exchange';
        $q->questiontext = 'Prototype câu hỏi trao đổi testcase tự động qua testcase_store.';
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
        $q->createdby = 2; // Admin ID
        $q->modifiedby = 2;

        $qid = $DB->insert_record('question', $q);

        // Nạp vào question_bank_entries và question_versions (Moodle 4.x)
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

        // Template Python gọi MariaDB
        $template = "import pymysql
import os
import sys

DB_HOST = \"mariadb\"
DB_USER = \"jobe_user\"
DB_PASS = \"JobeSecret123!\"
DB_NAME = \"testcase_store\"

course_id = \"{{ COURSE.shortname | default('CS101') }}\"
question_id = {{ QUESTION.id | default(100) }}
student_id = \"{{ STUDENT.username | default('student') }}\"
student_input = \"\"\"{{ STUDENT_ANSWER | e('py') }}\"\"\".strip()

try:
    jobe_server = open(\"/etc/hostname\").read().strip()
except:
    jobe_server = \"jobe\"

if not student_input:
    print(\"❌ Vui lòng nhập dữ liệu kiểm thử (testcase)!\")
    sys.exit(0)

try:
    conn = pymysql.connect(host=DB_HOST, user=DB_USER, password=DB_PASS, database=DB_NAME)
    cur = conn.cursor()

    cur.execute(\"SELECT student_id FROM student_testcases WHERE course_id=%s AND question_id=%s AND test_input=%s\", 
                (course_id, question_id, student_input))
    exist = cur.fetchone()

    if exist:
        author = exist[0]
        if author == student_id:
            print(f\"⚠️ Ban da tung nop testcase '{student_input}' cho bai nay roi.\")
        else:
            print(f\"❌ TESTCASE BI TRUNG! Da duoc ban [{author}] nop truoc do.\")
        conn.close()
        sys.exit(0)

    cur.execute(\"INSERT INTO student_testcases (course_id, question_id, student_id, test_input, jobe_server) VALUES (%s, %s, %s, %s, %s)\",
                (course_id, question_id, student_id, student_input, jobe_server))
    conn.commit()

    print(f\"🎉 CHUC MUNG! Testcase '{student_input}' cua ban la DOC NHAT!\")
    print(f\"🖥️ Xu ly boi cum: [{jobe_server}]\")

    cur.execute(\"\"\"
        SELECT id, student_id, test_input FROM student_testcases
        WHERE course_id=%s AND question_id=%s AND student_id != %s
          AND id NOT IN (SELECT testcase_id FROM testcase_exchanges WHERE receiver_student=%s)
        ORDER BY RAND() LIMIT 1
    \"\"\", (course_id, question_id, student_id, student_id))
    gift = cur.fetchone()

    if gift:
        gift_id, gift_author, gift_input = gift
        cur.execute(\"INSERT INTO testcase_exchanges (course_id, question_id, receiver_student, testcase_id) VALUES (%s, %s, %s, %s)\",
                    (course_id, question_id, student_id, gift_id))
        conn.commit()
        print(\"\\n🎁 PHAN THUONG TRAO DOI TESTCASE:\")
        print(f\"Nhan duoc testcase tu ban [{gift_author}]: {gift_input}\")

    conn.close()
except Exception as e:
    print(f\"⚠️ Loi ket noi: {e}\")
";

        $opt = new stdClass();
        $opt->questionid = $qid;
        $opt->coderunnertype = 'python3_testcase_exchange';
        $opt->prototypetype = 2; // Prototype
        $opt->allornothing = 1;
        $opt->penaltyregime = '0';
        $opt->precheck = 0;
        $opt->hidecheck = 0;
        $opt->showsource = 0;
        $opt->answerboxlines = 5;
        $opt->answerboxcolumns = 80;
        $opt->answer = '10 20';
        $opt->template = $template;
        $opt->iscombinatortemplate = 1;
        $opt->language = 'python3';
        $opt->grader = 'TemplateGrader';
        $opt->hoisttemplateparams = 1;
        $opt->templateparamslang = 'None';
        $opt->templateparamsevalpertry = 0;
        $opt->templateparamsevald = '{}';

        $DB->insert_record('question_coderunner_options', $opt);
    }

    return true;
}
