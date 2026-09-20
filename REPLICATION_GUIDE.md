# TÀI LIỆU HƯỚNG DẪN TÁI LẬP HỆ THỐNG MOODLE + CODERUNNER + DUAL JOBE (PLAYBOOK FOR AGENT / DEVOPS)

> **Mục đích tài liệu:** Cung cấp đầy đủ đặc tả kỹ thuật, mã nguồn cấu hình và quy trình tự động hóa từng bước để bất kỳ AI Agent hoặc kỹ sư DevOps nào cũng có thể dựng lại chính xác 100% hệ thống này trên một máy tính hoặc máy chủ (Server/VPS) mới từ con số 0.

---

## I. TỔNG QUAN KIẾN TRÚC HỆ THỐNG

Hệ thống bao gồm 4 container Docker chạy trong cùng một bridge network (`moodle_net`):
1. **`moodle_app`** (Cổng `8080`): Moodle 4.4 LMS chạy trên nền PHP 8.2 Apache, tích hợp sẵn plugin `qtype_coderunner` và `qbehaviour_adaptive_adapted_for_coderunner`.
2. **`moodle_mariadb`** (Cổng `3306`): Cơ sở dữ liệu chứa 2 CSDL hoàn toàn tách biệt:
   - `moodle`: Lưu toàn bộ dữ liệu gốc của LMS.
   - `testcase_store`: Kho lưu trữ testcase do sinh viên đóng góp, được phân quyền độc lập.
3. **`moodle_jobe1`** (Cổng `4001`): Máy chủ Sandbox Jobe thứ nhất (`jobeinabox`) có cài `python3-pymysql`.
4. **`moodle_jobe2`** (Cổng `4002`): Máy chủ Sandbox Jobe thứ hai (`jobeinabox`) có cài `python3-pymysql`.
   - CodeRunner tự động cân bằng tải giữa `jobe1` và `jobe2` theo cơ chế Round-robin.

---

## II. DANH SÁCH FILE NGUỒN CẦN TẠO

Tạo một thư mục dự án (ví dụ `demo-moodle-jobe`) và tạo các file sau:

### 1. File `Dockerfile` (Dành cho Moodle)
```dockerfile
FROM moodlehq/moodle-php-apache:8.2

# Cài đặt công cụ cần thiết
RUN apt-get update && apt-get install -y git curl tar unzip && rm -rf /var/lib/apt/lists/*

# Tải Moodle 4.4 Stable
RUN curl -sL "https://packaging.moodle.org/stable404/moodle-latest-404.tgz" | tar -xz -C /var/www/ \
    && rm -rf /var/www/html \
    && mv /var/www/moodle /var/www/html

# Tải plugin CodeRunner và Adaptive Behaviour
RUN git clone --depth 1 -b master https://github.com/trampgeek/moodle-qtype_coderunner.git /var/www/html/question/type/coderunner \
    && git clone --depth 1 -b master https://github.com/trampgeek/moodle-qbehaviour_adaptive_adapted_for_coderunner.git /var/www/html/question/behaviour/adaptive_adapted_for_coderunner

# Tạo thư mục moodledata và phân quyền
RUN mkdir -p /var/www/moodledata \
    && chown -R www-data:www-data /var/www/moodledata /var/www/html \
    && chmod -R 777 /var/www/moodledata

# Cấu hình tối ưu PHP cho Moodle
RUN { \
        echo "max_input_vars = 5000"; \
        echo "upload_max_filesize = 128M"; \
        echo "post_max_size = 128M"; \
        echo "memory_limit = 512M"; \
        echo "max_execution_time = 300"; \
    } > /usr/local/etc/php/conf.d/moodle.ini

COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

WORKDIR /var/www/html

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
```

---

### 2. File `Dockerfile.jobe` (Dành cho Jobe Sandbox)
```dockerfile
FROM trampgeek/jobeinabox:latest

# Cài đặt thư viện pymysql để Python trong Jobe kết nối được MariaDB
RUN apt-get update && apt-get install -y python3-pymysql && rm -rf /var/lib/apt/lists/*
```

---

### 3. File `entrypoint.sh` (Kịch bản khởi tạo tự động Moodle)
> **Lưu ý quan trọng:** PHP 8.1+ mặc định ném ngoại lệ `mysqli_sql_exception`. Phải có lệnh `mysqli_report(MYSQLI_REPORT_OFF)` và `try/catch` để không bị văng khi chờ MariaDB khởi động.

```bash
#!/bin/bash
set -e

echo "=== Moodle CodeRunner Initialization ==="

# Chờ MariaDB khởi động xong
echo "Đang chờ MariaDB tại ${MOODLE_DB_HOST:-mariadb}:${MOODLE_DB_PORT:-3306}..."
php -r '
$host = getenv("MOODLE_DB_HOST") ?: "mariadb";
$user = getenv("MOODLE_DB_USER") ?: "moodle";
$pass = getenv("MOODLE_DB_PASSWORD") ?: "moodlepassword";
$db   = getenv("MOODLE_DB_NAME") ?: "moodle";
$port = (int)(getenv("MOODLE_DB_PORT") ?: 3306);

$max_retries = 60;
$retry = 0;
mysqli_report(MYSQLI_REPORT_OFF);
while ($retry < $max_retries) {
    try {
        $conn = @new mysqli($host, $user, $pass, $db, $port);
        if ($conn && !$conn->connect_error) {
            echo "Kết nối CSDL MariaDB thành công!\n";
            $conn->close();
            exit(0);
        }
    } catch (Throwable $e) {}
    $retry++;
    echo "Đang chờ MariaDB khởi động xong ($retry/$max_retries)...\n";
    sleep(2);
}
echo "Lỗi: Không thể kết nối tới MariaDB!\n";
exit(1);
'

# Phân quyền moodledata
mkdir -p /var/www/moodledata
chown -R www-data:www-data /var/www/moodledata

# Tạo file config.php chuẩn cho Moodle
echo "Tạo cấu hình config.php..."
cat <<EOF > /var/www/html/config.php
<?php  // Moodle configuration file

unset(\$CFG);
global \$CFG;
\$CFG = new stdClass();

\$CFG->dbtype    = 'mariadb';
\$CFG->dblibrary = 'native';
\$CFG->dbhost    = '${MOODLE_DB_HOST:-mariadb}';
\$CFG->dbname    = '${MOODLE_DB_NAME:-moodle}';
\$CFG->dbuser    = '${MOODLE_DB_USER:-moodle}';
\$CFG->dbpass    = '${MOODLE_DB_PASSWORD:-moodlepassword}';
\$CFG->prefix    = 'mdl_';
\$CFG->dboptions = array (
  'dbpersist' => 0,
  'dbport' => ${MOODLE_DB_PORT:-3306},
  'dbsocket' => '',
  'dbcollation' => 'utf8mb4_unicode_ci',
);

\$CFG->wwwroot   = '${MOODLE_WWWROOT:-http://localhost:8080}';
\$CFG->dataroot  = '/var/www/moodledata';
\$CFG->admin     = 'admin';

\$CFG->directorypermissions = 0777;

require_once(__DIR__ . '/lib/setup.php');
EOF

chown www-data:www-data /var/www/html/config.php

# Kiểm tra xem CSDL đã có bảng hay chưa
HAS_TABLES=$(php -r '
$host = getenv("MOODLE_DB_HOST") ?: "mariadb";
$user = getenv("MOODLE_DB_USER") ?: "moodle";
$pass = getenv("MOODLE_DB_PASSWORD") ?: "moodlepassword";
$db   = getenv("MOODLE_DB_NAME") ?: "moodle";
$port = (int)(getenv("MOODLE_DB_PORT") ?: 3306);
$conn = new mysqli($host, $user, $pass, $db, $port);
$res = $conn->query("SHOW TABLES LIKE \"mdl_config\"");
echo ($res && $res->num_rows > 0) ? "yes" : "no";
$conn->close();
')

if [ "$HAS_TABLES" = "yes" ]; then
    echo "CSDL Moodle đã tồn tại. Đang cập nhật hệ thống và plugin..."
    php /var/www/html/admin/cli/upgrade.php --non-interactive || true
else
    echo "CSDL trống. Đang khởi tạo CSDL Moodle lần đầu..."
    php /var/www/html/admin/cli/install_database.php \
        --agree-license \
        --adminuser="${MOODLE_ADMIN_USER:-admin}" \
        --adminpass="${MOODLE_ADMIN_PASSWORD:-AdminPassword123!}" \
        --adminemail="${MOODLE_ADMIN_EMAIL:-admin@example.com}" \
        --fullname="Moodle CodeRunner LMS" \
        --shortname="CodeRunner"
fi

# Thiết lập mật khẩu tài khoản quản trị admin
echo "Thiết lập mật khẩu tài khoản quản trị admin..."
php /var/www/html/admin/cli/reset_password.php \
    --username="${MOODLE_ADMIN_USER:-admin}" \
    --password="${MOODLE_ADMIN_PASSWORD:-AdminPassword123!}" || true

# Cấu hình CodeRunner kết nối cụm Jobe song song
echo "Cấu hình CodeRunner Jobe Host (${JOBE_HOST:-jobe1;jobe2})..."
php /var/www/html/admin/cli/cfg.php --component=qtype_coderunner --name=jobe_host --set="${JOBE_HOST:-jobe1;jobe2}" || true

# QUAN TRỌNG: Bỏ chặn cURL internal host để Moodle gọi được Jobe trong mạng Docker
echo "Cấu hình bỏ chặn cURL tới mạng nội bộ..."
php /var/www/html/admin/cli/cfg.php --name=curlsecurityblockedhosts --set="" || true

# Lưu bản sao config.php sang moodledata
cp -f /var/www/html/config.php /var/www/moodledata/config.php
chown www-data:www-data /var/www/moodledata/config.php

echo "=== Moodle & CodeRunner đã sẵn sàng! ==="
echo "Khởi động Apache Web Server..."
exec moodle-docker-php-entrypoint apache2-foreground
```

---

### 4. File `docker-compose.yml`
```yaml
services:
  mariadb:
    image: bitnami/mariadb:latest
    container_name: moodle_mariadb
    restart: unless-stopped
    environment:
      - MARIADB_ROOT_PASSWORD=rootpassword
      - MARIADB_USER=moodle
      - MARIADB_PASSWORD=moodlepassword
      - MARIADB_DATABASE=moodle
      - MARIADB_CHARACTER_SET=utf8mb4
      - MARIADB_COLLATE=utf8mb4_unicode_ci
    volumes:
      - mariadb_data:/bitnami/mariadb
    ports:
      - "3306:3306"
    networks:
      - moodle_net

  jobe1:
    build:
      context: .
      dockerfile: Dockerfile.jobe
    container_name: moodle_jobe1
    restart: unless-stopped
    hostname: jobe1
    ports:
      - "4001:80"
    networks:
      - moodle_net

  jobe2:
    build:
      context: .
      dockerfile: Dockerfile.jobe
    container_name: moodle_jobe2
    restart: unless-stopped
    hostname: jobe2
    ports:
      - "4002:80"
    networks:
      - moodle_net

  moodle:
    build:
      context: .
      dockerfile: Dockerfile
    container_name: moodle_app
    restart: unless-stopped
    ports:
      - "8080:80"
    environment:
      - MOODLE_WWWROOT=http://localhost:8080
      - MOODLE_DB_HOST=mariadb
      - MOODLE_DB_PORT=3306
      - MOODLE_DB_NAME=moodle
      - MOODLE_DB_USER=moodle
      - MOODLE_DB_PASSWORD=moodlepassword
      - MOODLE_ADMIN_USER=admin
      - MOODLE_ADMIN_PASSWORD=AdminPassword123!
      - MOODLE_ADMIN_EMAIL=admin@example.com
      - JOBE_HOST=jobe1;jobe2
    volumes:
      - moodledata:/var/www/moodledata
    depends_on:
      - mariadb
      - jobe1
      - jobe2
    networks:
      - moodle_net

volumes:
  mariadb_data:
  moodledata:

networks:
  moodle_net:
    driver: bridge
```

---

## III. QUY TRÌNH TRIỂN KHAI TỪNG BƯỚC CHO AGENT

Khi Agent chạy trên máy mới, hãy ra lệnh thực thi theo đúng thứ tự 5 bước sau:

### Bước 1: Khởi động hệ thống Docker
```bash
docker compose up -d --build
```
*Thời gian chờ: khoảng 1 - 2 phút để Moodle tự động cài đặt schema CSDL lần đầu.*
*Kiểm tra khi nào hoàn tất:* Chạy `docker logs -f moodle_app` đến khi thấy dòng `AH00094: Command line: 'apache2 -D FOREGROUND'`.

---

### Bước 2: Tạo Cơ Sở Dữ Liệu độc lập `testcase_store` và phân quyền
Chạy lệnh trực tiếp vào container MariaDB:
```bash
docker exec -i moodle_mariadb mariadb -uroot -prootpassword << 'EOF'
CREATE DATABASE IF NOT EXISTS testcase_store CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE testcase_store;

CREATE TABLE IF NOT EXISTS student_testcases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id VARCHAR(50) NOT NULL,
    question_id INT NOT NULL,
    student_id VARCHAR(50) NOT NULL,
    test_input TEXT NOT NULL,
    jobe_server VARCHAR(50) DEFAULT 'unknown',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_test (course_id, question_id, test_input(255))
);

CREATE TABLE IF NOT EXISTS testcase_exchanges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    course_id VARCHAR(50) NOT NULL,
    question_id INT NOT NULL,
    receiver_student VARCHAR(50) NOT NULL,
    testcase_id INT NOT NULL,
    received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_exchange (receiver_student, testcase_id)
);

-- Phân quyền cho Jobe (Chỉ ghi/đọc testcase_store, không được chạm vào database moodle)
CREATE USER IF NOT EXISTS 'jobe_user'@'%' IDENTIFIED BY 'JobeSecret123!';
GRANT ALL PRIVILEGES ON testcase_store.* TO 'jobe_user'@'%';

-- Phân quyền cho Moodle (Chỉ đọc để làm Dashboard thống kê)
CREATE USER IF NOT EXISTS 'moodle_reader'@'%' IDENTIFIED BY 'ReaderSecret123!';
GRANT SELECT ON testcase_store.* TO 'moodle_reader'@'%';

FLUSH PRIVILEGES;
EOF
```

---

### Bước 3: Tạo Khóa học, Sinh viên và Quiz mẫu trong Moodle
Chạy đoạn lệnh PHP CLI sau bên trong container `moodle_app`:
```bash
docker exec moodle_app php -r '
define("CLI_SCRIPT", true);
require_once("/var/www/html/config.php");
require_once("/var/www/html/course/lib.php");
require_once("/var/www/html/user/lib.php");
require_once("/var/www/html/lib/questionlib.php");

global $DB, $CFG;

// 1. Tạo Khóa học CS101
$course = $DB->get_record("course", ["shortname" => "CS101"]);
if (!$course) {
    $cd = new stdClass();
    $cd->fullname = "Nhập môn Lập trình (Demo CodeRunner)";
    $cd->shortname = "CS101";
    $cd->category = 1;
    $cd->format = "topics";
    $cd->numsections = 4;
    $cd->summary = "Lớp học mẫu thử nghiệm tự động chấm bài lập trình.";
    $course = create_course($cd);
}

// 2. Tạo 2 tài khoản sinh viên: student1 & student2
$enrol = enrol_get_plugin("manual");
$instances = enrol_get_instances($course->id, true);
$manual = current(array_filter($instances, fn($i) => $i->enrol === "manual"));
$role = $DB->get_record("role", ["shortname" => "student"]);

foreach ([["student1", "Nguyễn Văn", "Sinh Viên 1"], ["student2", "Trần Thị", "Sinh Viên 2"]] as $u) {
    $user = $DB->get_record("user", ["username" => $u[0]]);
    if (!$user) {
        $ud = new stdClass();
        $ud->username = $u[0];
        $ud->password = hash_internal_user_password("StudentPassword123!");
        $ud->firstname = $u[1];
        $ud->lastname = $u[2];
        $ud->email = $u[0] . "@example.com";
        $ud->confirmed = 1;
        $ud->policyagreed = 1;
        $ud->mnethostid = $CFG->mnet_localhost_id;
        $uid = user_create_user($ud);
        $enrol->enrol_user($manual, $uid, $role->id);
    }
}

// 3. Tạo Quiz bài tập thực hành
$quiz = $DB->get_record("quiz", ["course" => $course->id, "name" => "Bài thực hành 01: Lập trình Python (Chấm tự động)"]);
if (!$quiz) {
    $qd = new stdClass();
    $qd->course = $course->id;
    $qd->name = "Bài thực hành 01: Lập trình Python (Chấm tự động)";
    $qd->intro = "<p>Bài tập thực hành tự động chấm điểm với CodeRunner.</p>";
    $qd->introformat = FORMAT_HTML;
    $qd->preferredbehaviour = "adaptive";
    $qd->grade = 10.0;
    $qd->sumgrades = 10.0;
    $qd->timecreated = time();
    $qd->timemodified = time();
    $qid = $DB->insert_record("quiz", $qd);
    $DB->insert_record("quiz_sections", ["quizid" => $qid, "firstslot" => 1, "heading" => "", "shufflequestions" => 0]);

    // Gắn vào Section 1
    $mod = $DB->get_record("modules", ["name" => "quiz"]);
    $cm = new stdClass();
    $cm->course = $course->id;
    $cm->module = $mod->id;
    $cm->instance = $qid;
    $cm->section = 1;
    $cm->added = time();
    $cm->visible = 1;
    $cmid = add_course_module($cm);
    course_add_cm_to_section($course, $cmid, 1);
}
echo "Đã tạo môn học, tài khoản sinh viên và Quiz thành công!\n";
'
```

---

### Bước 4: Tạo câu hỏi Trao đổi Testcase & Chống trùng vào Quiz
Chạy lệnh tạo câu hỏi CodeRunner có nhúng Template kết nối `testcase_store`:
```bash
docker exec moodle_app php -r '
define("CLI_SCRIPT", true);
require_once("/var/www/html/config.php");
require_once("/var/www/html/lib/questionlib.php");
require_once("/var/www/html/mod/quiz/locallib.php");

global $DB;

$existing = $DB->get_record("question", ["name" => "Thử thách: Đóng góp Testcase độc nhất & Trao đổi chéo"]);
if ($existing) {
    echo "Câu hỏi đã tồn tại ID: " . $existing->id . "\n";
    exit(0);
}

// Lấy default category của CS101
$ctx = context_course::instance(2);
$cats = $DB->get_records("question_categories", ["contextid" => $ctx->id]);
$cat = reset($cats) ?: question_make_default_categories([$ctx]);

// 1. Tạo question
$q = new stdClass();
$q->parent = 0;
$q->name = "Thử thách: Đóng góp Testcase độc nhất & Trao đổi chéo";
$q->questiontext = "<p>Hãy đóng góp một testcase số nguyên độc nhất. Cụm Jobe song song sẽ kiểm tra chống trùng lặp trực tiếp trên CSDL MariaDB độc lập (<code>testcase_store</code>).</p>";
$q->questiontextformat = FORMAT_HTML;
$q->defaultmark = 1.0;
$q->qtype = "coderunner";
$q->stamp = make_unique_id_code();
$q->timecreated = time();
$q->timemodified = time();
$q->createdby = 2;
$q->modifiedby = 2;
$qid = $DB->insert_record("question", $q);

$entry = new stdClass();
$entry->questioncategoryid = is_object($cat) ? $cat->id : 4;
$entry->ownerid = 2;
$entryid = $DB->insert_record("question_bank_entries", $entry);

$version = new stdClass();
$version->questionbankentryid = $entryid;
$version->version = 1;
$version->questionid = $qid;
$version->status = "ready";
$DB->insert_record("question_versions", $version);

$template = "import pymysql
import os
import sys

DB_HOST = \"mariadb\"
DB_USER = \"jobe_user\"
DB_PASS = \"JobeSecret123!\"
DB_NAME = \"testcase_store\"

course_id = \"{{ COURSE.shortname | default(\x27CS101\x27) }}\"
question_id = {{ QUESTION.id | default(100) }}
student_id = \"{{ STUDENT.username | default(\x27student\x27) }}\"
student_input = \"\"\"{{ STUDENT_ANSWER | e(\x27py\x27) }}\"\"\".strip()

try:
    jobe_server = open(\"/etc/hostname\").read().strip()
except:
    jobe_server = \"jobe\"

conn = pymysql.connect(host=DB_HOST, user=DB_USER, password=DB_PASS, database=DB_NAME)
cur = conn.cursor()

# 1. Kiem tra trung
cur.execute(\"SELECT student_id, jobe_server FROM student_testcases WHERE course_id=%s AND question_id=%s AND test_input=%s\", (course_id, question_id, student_input))
exist = cur.fetchone()

if exist:
    author = exist[0]
    if author == student_id:
        print(f\"⚠️ Ban da tung nop testcase \x27{student_input}\x27 cho bai nay roi.\")
    else:
        print(f\"❌ TESTCASE BI TRUNG!\")
        print(f\"👉 Ca kiem thu \x27{student_input}\x27 nay da duoc ban [{author}] nop truoc do.\")
        print(\"💡 Hay suy nghi mot ca kiem thu goc (edge case) khac chua ai nghi ra!\")
    conn.close()
    sys.exit(0)

# 2. Ghi nhan testcase moi
cur.execute(\"INSERT INTO student_testcases (course_id, question_id, student_id, test_input, jobe_server) VALUES (%s, %s, %s, %s, %s)\",
            (course_id, question_id, student_id, student_input, jobe_server))
conn.commit()

print(f\"🎉 CHUC MUNG! Testcase \x27{student_input}\x27 cua ban la DOC NHAT va da duoc ghi nhan vao kho CSDL!\")
print(f\"🖥️ Xu ly boi cum may chu: [{jobe_server}]\")

# 3. Trao doi testcase tu ban khac
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
    print(\"\\n\" + \"=\"*55)
    print(f\"🎁 PHAN THUONG TRAO DOI TESTCASE:\")
    print(f\"Ban nhan duoc 1 ca kiem thu tu ban [{gift_author}]:\")
    print(f\"👉 Du lieu dau vao: {gift_input}\")
    print(\"=\"*55)
else:
    print(\"\\n🎁 Ban la mot trong nhung nguoi nop dau tien cho bai nay! Kho testcase dang tich luy.\")

conn.close()
";

$opt = new stdClass();
$opt->questionid = $qid;
$opt->coderunnertype = "python3";
$opt->prototypetype = 0;
$opt->allornothing = 1;
$opt->answerboxlines = 3;
$opt->answerboxcolumns = 60;
$opt->answerpreload = "# Nhập testcase số nguyên (ví dụ: -99999 hoặc 0)\n";
$opt->useace = 1;
$opt->answer = "0";
$opt->template = $template;
$opt->sandbox = "jobesandbox";
$DB->insert_record("question_coderunner_options", $opt);

$t = new stdClass();
$t->questionid = $qid;
$t->seq = 1;
$t->mark = 1.0;
$DB->insert_record("question_coderunner_tests", $t);

$quiz = $DB->get_record("quiz", ["name" => "Bài thực hành 01: Lập trình Python (Chấm tự động)"]);
quiz_add_quiz_question($qid, $quiz);
quiz_update_sumgrades($quiz);

echo "Tạo câu hỏi Trao đổi Testcase thành công! ID: $qid\n";
'
```

---

### Bước 5: Cài đặt trang Testcase Dashboard trên Moodle
Copy file `testcase_dashboard.php` vào container Moodle và gắn link vào Section 1:
```bash
docker cp testcase_dashboard.php moodle_app:/var/www/html/testcase_dashboard.php
docker exec moodle_app chown www-data:www-data /var/www/html/testcase_dashboard.php

# Gắn link vào môn học CS101
docker exec moodle_app php -r '
define("CLI_SCRIPT", true);
require_once("/var/www/html/config.php");
require_once("/var/www/html/course/lib.php");

global $DB;
$mod = $DB->get_record("modules", ["name" => "url"]);
$existing = $DB->get_record("url", ["course" => 2, "externalurl" => "http://localhost:8080/testcase_dashboard.php?course=2"]);
if (!$existing) {
    $u = new stdClass();
    $u->course = 2;
    $u->name = "📊 Bảng Thống Kê Testcase & Xếp Hạng Đóng Góp (Cụm Jobe Song Song)";
    $u->externalurl = "http://localhost:8080/testcase_dashboard.php?course=2";
    $u->display = 0;
    $u->timemodified = time();
    $uid = $DB->insert_record("url", $u);

    $cm = new stdClass();
    $cm->course = 2;
    $cm->module = $mod->id;
    $cm->instance = $uid;
    $cm->section = 1;
    $cm->added = time();
    $cm->visible = 1;
    $cmid = add_course_module($cm);
    course_add_cm_to_section($DB->get_record("course", ["id" => 2]), $cmid, 1);
    rebuild_course_cache(2);
    echo "Đã gắn Dashboard vào môn học thành công!\n";
}
'
```

---

## IV. BƯỚC KIỂM THỬ XÁC NHẬN (VERIFICATION)

Chạy script kiểm thử để kiểm chứng 2 sinh viên nộp bài đồng thời:
```bash
pip3 install pymysql
python3 test_concurrent_submission.py
```

**Kết quả kỳ vọng:**
1. Thread `student1` và Thread `student2` chạy đồng thời trong ~1.6 giây.
2. Một bài được xử lý bởi `jobe1`, một bài được xử lý bởi `jobe2`.
3. Hai sinh viên được trao đổi chéo testcase của nhau.
4. Khi nộp trùng, hệ thống báo lỗi `❌ TESTCASE BỊ TRÙNG!`.
5. Truy cập `http://localhost:8080/testcase_dashboard.php?course=2` hiển thị đầy đủ bảng xếp hạng và các thẻ KPI.
