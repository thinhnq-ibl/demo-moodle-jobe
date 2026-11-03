#!/bin/bash
set -e

echo "=== Moodle CodeRunner Portable Stack Initialization ==="

# 1. Tự động bung dữ liệu moodledata nếu volume còn mới/trống
mkdir -p /var/www/moodledata
if [ ! -d "/var/www/moodledata/filedir" ] && [ -f "/var/www/moodledata_template.tar.gz" ]; then
    echo "Khởi tạo dữ liệu moodledata từ template pre-baked..."
    tar -xzf /var/www/moodledata_template.tar.gz -C /var/www/moodledata
fi
chown -R www-data:www-data /var/www/moodledata
chmod -R 777 /var/www/moodledata

# 2. Chờ MariaDB khởi động xong
echo "Đang chờ MariaDB tại ${MOODLE_DB_HOST:-mariadb}:${MOODLE_DB_PORT:-3306}..."
php -r '
$host = getenv("MOODLE_DB_HOST") ?: "mariadb";
$user = getenv("MOODLE_DB_USER") ?: "moodle";
$pass = getenv("MOODLE_DB_PASSWORD") ?: "moodlepassword";
$db   = getenv("MOODLE_DB_NAME") ?: "moodle";
$port = (int)(getenv("MOODLE_DB_PORT") ?: 3306);

$max_retries = 90;
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
    echo "Đang chờ MariaDB khởi động và import CSDL... ($retry/$max_retries)\n";
    sleep(2);
}
echo "Lỗi: Không thể kết nối tới MariaDB sau thời gian chờ!\n";
exit(1);
'

# 3. Tạo file config.php chuẩn cho Moodle
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

# 4. Kiểm tra xem CSDL đã có bảng hay chưa
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
    echo "CSDL Moodle đã có sẵn. Đang kiểm tra upgrade plugin..."
    php /var/www/html/admin/cli/upgrade.php --non-interactive || true
else
    echo "Cảnh báo: CSDL chưa có bảng, tiến hành khởi tạo mặc định..."
    php /var/www/html/admin/cli/install_database.php \
        --agree-license \
        --adminuser="${MOODLE_ADMIN_USER:-admin}" \
        --adminpass="${MOODLE_ADMIN_PASSWORD:-AdminPassword123!}" \
        --adminemail="${MOODLE_ADMIN_EMAIL:-admin@example.com}" \
        --fullname="Moodle CodeRunner LMS" \
        --shortname="CodeRunner"
fi

# 5. Cập nhật mật khẩu admin nếu cần
echo "Thiết lập mật khẩu tài khoản quản trị admin..."
php /var/www/html/admin/cli/reset_password.php \
    --username="${MOODLE_ADMIN_USER:-admin}" \
    --password="${MOODLE_ADMIN_PASSWORD:-AdminPassword123!}" || true

# 6. Cấu hình CodeRunner kết nối Jobe sandbox
echo "Cấu hình CodeRunner Jobe Host (${JOBE_HOST:-jobe1;jobe2})..."
php /var/www/html/admin/cli/cfg.php --component=qtype_coderunner --name=jobe_host --set="${JOBE_HOST:-jobe1;jobe2}" || true

# 7. Đồng bộ cấu hình plugin testcase exchange
echo "Cấu hình plugin Testcase Exchange..."
php /var/www/html/admin/cli/cfg.php --component=local_testcase_exchange --name=db_host \
    --set="${TESTCASE_DB_HOST:-mariadb}" || true
php /var/www/html/admin/cli/cfg.php --component=local_testcase_exchange --name=db_port \
    --set="${TESTCASE_DB_PORT:-3306}" || true
php /var/www/html/admin/cli/cfg.php --component=local_testcase_exchange --name=db_name \
    --set="${TESTCASE_DB_NAME:-testcase_store}" || true
php /var/www/html/admin/cli/cfg.php --component=local_testcase_exchange --name=db_user \
    --set="${TESTCASE_DB_USER:-moodle_app_writer}" || true
php /var/www/html/admin/cli/cfg.php --component=local_testcase_exchange --name=db_pass \
    --set="${TESTCASE_DB_PASSWORD:-JobeSecret123!}" || true
php /var/www/html/admin/cli/cfg.php --component=local_testcase_exchange --name=jobe_servers \
    --set="${TESTCASE_JOBE_SERVERS:-${JOBE_HOST:-jobe1;jobe2}}" || true
php /var/www/html/admin/cli/cfg.php --component=local_testcase_exchange --name=jobe_api_key \
    --set="${TESTCASE_JOBE_API_KEY:-}" || true

# 8. Cho phép Moodle kết nối tới các host nội bộ (Jobe sandbox trong mạng Docker)
echo "Cấu hình bỏ chặn cURL tới mạng nội bộ..."
php /var/www/html/admin/cli/cfg.php --name=curlsecurityblockedhosts --set="" || true

# 9. Lưu một bản sao config.php sang moodledata
cp -f /var/www/html/config.php /var/www/moodledata/config.php
chown www-data:www-data /var/www/moodledata/config.php

echo "=== Moodle & CodeRunner Portable Stack đã sẵn sàng! ==="
echo "Khởi động Apache Web Server..."
exec moodle-docker-php-entrypoint apache2-foreground
