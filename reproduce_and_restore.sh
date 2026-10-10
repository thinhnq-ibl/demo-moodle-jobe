#!/bin/bash
set -e

FORCE=false
if [ "$1" = "--force" ] || [ "$1" = "-f" ] || [ "$CI" = "true" ]; then
    FORCE=true
fi

echo "================================================================="
echo " 🚀 HỆ THỐNG TÁI LẬP MOODLE + CODERUNNER + DUAL JOBE SANDBOX "
echo "================================================================="

# 1. Kiểm tra Docker
if ! command -v docker &> /dev/null; then
    echo "❌ Lỗi: Không tìm thấy lệnh docker! Vui lòng cài đặt Docker trước."
    exit 1
fi

COMPOSE_FILE="docker-compose.yml"
BACKUP_SQL_GZ="backup_moodle_and_testcase.sql.gz"
BACKUP_SQL="backup_moodle_and_testcase.sql"
BACKUP_MOODLEDATA="moodledata.tar.gz"
MANIFEST_FILE="metadata/manifest.json"

# 2. Xác thực tính toàn vẹn nếu có manifest
if [ -f "$MANIFEST_FILE" ]; then
    echo ""
    echo "[0/5] Phát hiện file kiểm thử tính toàn vẹn ($MANIFEST_FILE)..."
    python3 -c "
import json, hashlib

with open('$MANIFEST_FILE') as f:
    m = json.load(f)

for fpath, expected in m.get('checksums_sha256', {}).items():
    real_path = fpath.replace('database_sql_gz', '$BACKUP_SQL_GZ').replace('moodledata_tar_gz', '$BACKUP_MOODLEDATA').replace('docker_compose_yml', 'docker-compose.yml').replace('dockerfile_moodle', 'Dockerfile').replace('dockerfile_jobe', 'Dockerfile.jobe')
    try:
        with open(real_path, 'rb') as f:
            h = hashlib.sha256(f.read()).hexdigest()
        if h == expected:
            print(f'✓ Checksum SHA-256 khớp: {real_path}')
        else:
            print(f'⚠️ Cảnh báo: Checksum khác: {real_path}')
    except Exception:
        pass
" 2>/dev/null || true
fi

# 3. Khởi động dịch vụ Docker Compose
echo ""
echo "[1/5] Khởi động các container Docker (mariadb, jobe1, jobe2, moodle)..."
docker compose -f "$COMPOSE_FILE" up -d --build

# 4. Chờ MariaDB sẵn sàng
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

# 5. Kiểm tra an toàn trước khi khôi phục CSDL (Tránh vô tình ghi đè)
HAS_EXISTING_DATA=$(docker exec moodle_mariadb mariadb -u root -prootpassword -sse "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='moodle' AND table_name='mdl_user';" 2>/dev/null || echo "0")

DO_RESTORE=true
if [ "$HAS_EXISTING_DATA" != "0" ] && [ "$FORCE" = false ]; then
    echo ""
    echo "⚠️ CẢNH BÁO: CSDL Moodle hiện tại đã có sẵn dữ liệu."
    read -p " Bạn có chắc chắn muốn ghi đè để khôi phục từ bản sao lưu? [y/N]: " confirm
    if [[ ! "$confirm" =~ ^[yY]$ ]]; then
        echo "Bỏ qua bước nạp CSDL để bảo vệ dữ liệu hiện tại."
        DO_RESTORE=false
    fi
fi

if [ "$DO_RESTORE" = true ]; then
    echo ""
    echo "[3/5] Khôi phục CSDL và Docker Volume moodledata..."
    
    # Khôi phục CSDL
    if [ -f "$BACKUP_SQL_GZ" ]; then
        echo "  Đang nạp CSDL từ tệp nén $BACKUP_SQL_GZ..."
        gunzip -c "$BACKUP_SQL_GZ" | docker exec -i moodle_mariadb /opt/bitnami/mariadb/bin/mariadb -u root -prootpassword
        echo "  ✓ Đã nạp thành công 2 CSDL: moodle và testcase_store!"
    elif [ -f "$BACKUP_SQL" ]; then
        echo "  Đang nạp CSDL từ tệp $BACKUP_SQL..."
        docker exec -i moodle_mariadb /opt/bitnami/mariadb/bin/mariadb -u root -prootpassword < "$BACKUP_SQL"
        echo "  ✓ Đã nạp thành công 2 CSDL: moodle và testcase_store!"
    fi

    # Khôi phục volume moodledata
    if [ -f "$BACKUP_MOODLEDATA" ]; then
        echo "  Đang giải nén volume moodledata từ $BACKUP_MOODLEDATA..."
        docker run --rm \
            -v demo-moodle-jobe_moodledata:/moodledata \
            -v "$(pwd)":/backup \
            alpine sh -c "tar -xzf /backup/$BACKUP_MOODLEDATA -C /moodledata"
        docker exec moodle_app chown -R www-data:www-data /var/www/moodledata
        echo "  ✓ Đã khôi phục thành công Docker volume moodledata!"
    fi
fi

# 6. Đồng bộ plugin local_testcase_exchange và quizaccess vào container Moodle
echo ""
echo "[4/5] Đồng bộ plugin và tệp chuyển tiếp vào Moodle container..."
if [ -d "local_testcase_exchange" ]; then
    docker exec moodle_app mkdir -p /var/www/html/local/testcase_exchange
    docker cp local_testcase_exchange/. moodle_app:/var/www/html/local/testcase_exchange/
    docker exec moodle_app chown -R www-data:www-data /var/www/html/local/testcase_exchange/
    echo "✓ Đã đồng bộ plugin local_testcase_exchange"
fi

if [ -d "quizaccess_testcaseexchange" ]; then
    docker exec moodle_app mkdir -p /var/www/html/mod/quiz/accessrule/testcaseexchange
    docker cp quizaccess_testcaseexchange/. moodle_app:/var/www/html/mod/quiz/accessrule/testcaseexchange/
    docker exec moodle_app chown -R www-data:www-data /var/www/html/mod/quiz/accessrule/testcaseexchange/
    echo "✓ Đã đồng bộ subplugin quizaccess_testcaseexchange"
fi

if [ -f "testcase_dashboard.php" ]; then
    docker cp testcase_dashboard.php moodle_app:/var/www/html/testcase_dashboard.php
    docker exec moodle_app chown www-data:www-data /var/www/html/testcase_dashboard.php
    echo "✓ Đã đồng bộ tệp chuyển tiếp testcase_dashboard.php"
fi

# Cập nhật nâng cấp Moodle & cấu hình CodeRunner & làm mới cache
docker exec moodle_app php /var/www/html/admin/cli/upgrade.php --non-interactive || true
docker exec moodle_app php /var/www/html/admin/cli/cfg.php --component=qtype_coderunner --name=jobe_host --set="jobe1;jobe2" || true
docker exec moodle_app php /var/www/html/admin/cli/cfg.php --name=curlsecurityblockedhosts --set="" || true
docker exec moodle_app php /var/www/html/admin/cli/purge_caches.php || true

# 7. Kiểm tra sức khỏe hệ thống
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
echo " 🌐 Moodle LMS URL:        http://localhost:8080"
echo " 👤 Admin User:            admin / AdminPassword123!"
echo " 👥 Sinh viên demo:        student1 / StudentPassword123!"
echo " 📊 Kho testcase (Hub):    http://localhost:8080/local/testcase_exchange/index.php"
echo " ⚖️ Duyệt testcase (Hub):  http://localhost:8080/local/testcase_exchange/review.php"
echo " 📚 Khóa học CS101:        http://localhost:8080/course/view.php?id=2"
echo " 📚 Khóa học AI-PY-PILOT:  http://localhost:8080/course/view.php?id=4"
echo "================================================================="
