#!/bin/bash
set -e

echo "================================================================="
echo " 🚀 HỆ THỐNG TÁI LẬP MOODLE + CODERUNNER + DUAL JOBE SANDBOX "
echo "================================================================="

# 1. Kiểm tra Docker
if ! command -v docker &> /dev/null; then
    echo "❌ Lỗi: Không tìm thấy lệnh docker! Vui lòng cài đặt Docker trước."
    exit 1
fi

COMPOSE_FILE="docker-compose.yml"
BACKUP_SQL="backup_moodle_and_testcase.sql"

# 2. Khởi động dịch vụ Docker Compose
echo ""
echo "[1/5] Khởi động các container Docker (mariadb, jobe1, jobe2, moodle)..."
docker compose -f "$COMPOSE_FILE" up -d --build

# 3. Chờ MariaDB sẵn sàng
echo ""
echo "[2/5] Đang chờ MariaDB khởi động và chấp nhận kết nối..."
MAX_RETRIES=30
RETRY=0
DB_READY=false
while [ "$DB_READY" = false ] && [ "$RETRY" -lt "$MAX_RETRIES" ]; do
    RETRY=$((RETRY+1))
    if docker exec moodle_mariadb /opt/bitnami/mariadb/bin/mariadb-admin ping -u root -prootpassword --silent &> /dev/null; then
        DB_READY=true
        echo "✓ MariaDB đã sẵn sàng!"
    else
        echo "  Đang đợi MariaDB ($RETRY/$MAX_RETRIES)..."
        sleep 2
    fi
done

if [ "$DB_READY" = false ]; then
    echo "❌ Lỗi: MariaDB không phản hồi sau $MAX_RETRIES lần thử!"
    exit 1
fi

# 4. Khôi phục CSDL nếu có file backup
if [ -f "$BACKUP_SQL" ]; then
    echo ""
    echo "[3/5] Phát hiện file sao lưu CSDL ($BACKUP_SQL). Đang khôi phục toàn bộ CSDL..."
    docker exec -i moodle_mariadb /opt/bitnami/mariadb/bin/mariadb -u root -prootpassword < "$BACKUP_SQL"
    echo "✓ Đã khôi phục thành công 2 CSDL: moodle và testcase_store!"
else
    echo ""
    echo "[3/5] Không tìm thấy file $BACKUP_SQL, bỏ qua bước nạp SQL."
fi

# 5. Đồng bộ plugin local_testcase_exchange vào container Moodle
echo ""
echo "[4/5] Đồng bộ plugin local_testcase_exchange vào Moodle container..."
if [ -d "local_testcase_exchange" ]; then
    docker exec moodle_app mkdir -p /var/www/html/local/testcase_exchange
    docker cp local_testcase_exchange/. moodle_app:/var/www/html/local/testcase_exchange/
    docker exec moodle_app chown -R www-data:www-data /var/www/html/local/testcase_exchange/
    echo "✓ Đã đồng bộ plugin local_testcase_exchange vào /var/www/html/local/testcase_exchange/"
fi

# Cập nhật nâng cấp Moodle & cấu hình CodeRunner
docker exec moodle_app php /var/www/html/admin/cli/upgrade.php --non-interactive || true
docker exec moodle_app php /var/www/html/admin/cli/cfg.php --component=qtype_coderunner --name=jobe_host --set="jobe1;jobe2" || true
docker exec moodle_app php /var/www/html/admin/cli/cfg.php --name=curlsecurityblockedhosts --set="" || true

# 6. Kiểm tra sức khỏe hệ thống
echo ""
echo "[5/5] Kiểm tra sức khỏe toàn bộ hệ thống..."
if docker exec moodle_app curl -s http://jobe1/jobe/index.php/restapi/languages | grep -q "python3"; then
    echo "✓ Jobe1 Sandbox: Hoạt động bình thường"
else
    echo "⚠️ Cảnh báo: Jobe1 chưa phản hồi API!"
fi

if docker exec moodle_app curl -s http://jobe2/jobe/index.php/restapi/languages | grep -q "python3"; then
    echo "✓ Jobe2 Sandbox: Hoạt động bình thường"
else
    echo "⚠️ Cảnh báo: Jobe2 chưa phản hồi API!"
fi

docker exec moodle_app php -r '
define("CLI_SCRIPT", true);
require_once("/var/www/html/config.php");
global $DB;
$count = $DB->count_records("user");
echo "✓ DB Moodle OK ($count users)\n";
'

echo ""
echo "================================================================="
echo " 🎉 HOÀN TẤT TÁI LẬP VÀ KHÔI PHỤC HỆ THỐNG!"
echo " 🌐 Moodle LMS URL:    http://localhost:8080"
echo " 👤 Admin User:        admin / AdminPassword123!"
echo " 👥 Sinh viên demo:    student1 / StudentPassword123!"
echo " 📊 Plugin Dashboard:  http://localhost:8080/local/testcase_exchange/index.php?course=3"
echo "================================================================="
