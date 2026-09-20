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

# Cập nhật mật khẩu admin nếu cần
echo "Thiết lập mật khẩu tài khoản quản trị admin..."
php /var/www/html/admin/cli/reset_password.php \
    --username="${MOODLE_ADMIN_USER:-admin}" \
    --password="${MOODLE_ADMIN_PASSWORD:-AdminPassword123!}" || true

# Cấu hình CodeRunner kết nối Jobe sandbox
echo "Cấu hình CodeRunner Jobe Host (${JOBE_HOST:-jobe})..."
php /var/www/html/admin/cli/cfg.php --component=qtype_coderunner --name=jobe_host --set="${JOBE_HOST:-jobe}" || true

# Cho phép Moodle kết nối tới các host nội bộ (Jobe sandbox trong mạng Docker)
echo "Cấu hình bỏ chặn cURL tới mạng nội bộ..."
php /var/www/html/admin/cli/cfg.php --name=curlsecurityblockedhosts --set="" || true

# Lưu một bản sao config.php sang moodledata
cp -f /var/www/html/config.php /var/www/moodledata/config.php
chown www-data:www-data /var/www/moodledata/config.php

echo "=== Moodle & CodeRunner đã sẵn sàng! ==="
echo "Khởi động Apache Web Server..."
exec moodle-docker-php-entrypoint apache2-foreground
