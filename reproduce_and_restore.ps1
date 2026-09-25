<#
.SYNOPSIS
    Kịch bản tự động hóa tái tạo hoặc khôi phục toàn bộ hệ thống Moodle + CodeRunner + Jobe + Testcase Exchange.
.DESCRIPTION
    Script thực hiện:
    1. Kiểm tra Docker & Docker Compose.
    2. Build các image (Moodle, Jobe Sandbox có pymysql).
    3. Khởi động 4 container (MariaDB, Jobe1, Jobe2, Moodle).
    4. Sao chép plugin local_testcase_exchange và đồng bộ quyền.
    5. Tùy chọn khôi phục CSDL (Full Database Restore) từ backup_moodle_and_testcase.sql nếu có.
    6. Kiểm tra tình trạng sẵn sàng của hệ thống.
.PARAMETER RestoreBackup
    Nếu được bật, tự động nạp backup_moodle_and_testcase.sql vào MariaDB.
#>

[CmdletBinding()]
param (
    [switch]$RestoreBackup = $true,
    [string]$ComposeFile = "docker-compose.yml",
    [string]$SqlBackupFile = "backup_moodle_and_testcase.sql"
)

$ErrorActionPreference = "Stop"

Write-Host "=================================================================" -ForegroundColor Cyan
Write-Host " 🚀 HỆ THỐNG TÁI LẬP MOODLE + CODERUNNER + DUAL JOBE SANDBOX " -ForegroundColor Cyan
Write-Host "=================================================================" -ForegroundColor Cyan

# 1. Kiểm tra Docker
try {
    $dockerVer = docker --version
    Write-Host "✓ $dockerVer" -ForegroundColor Green
} catch {
    Write-Error "Không tìm thấy Docker! Vui lòng cài đặt Docker Desktop và khởi động trước khi chạy script."
}

# 2. Khởi động dịch vụ Docker Compose
Write-Host "`n[1/5] Khởi động các container Docker (mariadb, jobe1, jobe2, moodle)..." -ForegroundColor Yellow
docker compose -f $ComposeFile up -d --build

# 3. Chờ MariaDB sẵn sàng
Write-Host "`n[2/5] Đang chờ MariaDB khởi động và chấp nhận kết nối..." -ForegroundColor Yellow
$maxRetries = 30
$retry = 0
$dbReady = $false
while (-not $dbReady -and $retry -lt $maxRetries) {
    $retry++
    $check = docker exec moodle_mariadb /opt/bitnami/mariadb/bin/mariadb-admin ping -u root -prootpassword --silent 2>&1
    if ($LASTEXITCODE -eq 0) {
        $dbReady = $true
        Write-Host "✓ MariaDB đã sẵn sàng!" -ForegroundColor Green
    } else {
        Start-Sleep -Seconds 2
        Write-Host "  Đang đợi MariaDB ($retry/$maxRetries)..." -ForegroundColor Gray
    }
}

if (-not $dbReady) {
    Write-Error "MariaDB không phản hồi sau $maxRetries lần thử!"
}

# 4. Khôi phục CSDL nếu có file backup và được yêu cầu
if ($RestoreBackup -and (Test-Path $SqlBackupFile)) {
    Write-Host "`n[3/5] Phát hiện file sao lưu CSDL ($SqlBackupFile). Đang khôi phục toàn bộ CSDL..." -ForegroundColor Yellow
    Get-Content $SqlBackupFile | docker exec -i moodle_mariadb /opt/bitnami/mariadb/bin/mariadb -u root -prootpassword
    if ($LASTEXITCODE -eq 0) {
        Write-Host "✓ Đã khôi phục thành công 2 CSDL: moodle và testcase_store!" -ForegroundColor Green
    } else {
        Write-Warning "Quá trình nạp SQL gặp lỗi hoặc cảnh báo, tiếp tục kiểm tra..."
    }
} else {
    Write-Host "`n[3/5] Bỏ qua khôi phục SQL (Không có file hoặc không kích hoạt tham số)." -ForegroundColor Gray
}

# 5. Đồng bộ plugin local_testcase_exchange vào container Moodle
Write-Host "`n[4/5] Đồng bộ plugin local_testcase_exchange vào Moodle container..." -ForegroundColor Yellow
if (Test-Path "local_testcase_exchange") {
    docker exec moodle_app mkdir -p /var/www/html/local/testcase_exchange
    docker cp "local_testcase_exchange/." moodle_app:/var/www/html/local/testcase_exchange/
    docker exec moodle_app chown -R www-data:www-data /var/www/html/local/testcase_exchange/
    Write-Host "✓ Đã đồng bộ plugin local_testcase_exchange vào /var/www/html/local/testcase_exchange/" -ForegroundColor Green
}

# Cập nhật nâng cấp Moodle & cấu hình CodeRunner
docker exec moodle_app php /var/www/html/admin/cli/upgrade.php --non-interactive
docker exec moodle_app php /var/www/html/admin/cli/cfg.php --component=qtype_coderunner --name=jobe_host --set="jobe1;jobe2"
docker exec moodle_app php /var/www/html/admin/cli/cfg.php --name=curlsecurityblockedhosts --set=""

# 6. Kiểm tra sức khỏe hệ thống
Write-Host "`n[5/5] Kiểm tra sức khỏe toàn bộ hệ thống..." -ForegroundColor Yellow

$jobe1 = docker exec moodle_app curl -s http://jobe1/jobe/index.php/restapi/languages
if ($jobe1 -like "*python3*") {
    Write-Host "✓ Jobe1 Sandbox: Hoạt động bình thường" -ForegroundColor Green
} else {
    Write-Warning "Jobe1 chưa phản hồi API!"
}

$jobe2 = docker exec moodle_app curl -s http://jobe2/jobe/index.php/restapi/languages
if ($jobe2 -like "*python3*") {
    Write-Host "✓ Jobe2 Sandbox: Hoạt động bình thường" -ForegroundColor Green
} else {
    Write-Warning "Jobe2 chưa phản hồi API!"
}

$moodleCheck = docker exec moodle_app php -r '
define("CLI_SCRIPT", true);
require_once("/var/www/html/config.php");
global $DB;
$count = $DB->count_records("user");
echo "DB Moodle OK ($count users)";
'
Write-Host "✓ $moodleCheck" -ForegroundColor Green

Write-Host "`n=================================================================" -ForegroundColor Cyan
Write-Host " 🎉 HOÀN TẤT TÁI LẬP VÀ KHÔI PHỤC HỆ THỐNG!" -ForegroundColor Green
Write-Host " 🌐 Moodle LMS URL:    http://localhost:8080" -ForegroundColor White
Write-Host " 👤 Admin User:        admin / AdminPassword123!" -ForegroundColor White
Write-Host " 👥 Sinh viên demo:    student1 / StudentPassword123!" -ForegroundColor White
Write-Host " 📊 Plugin Dashboard:  http://localhost:8080/local/testcase_exchange/index.php?course=3" -ForegroundColor White
Write-Host "=================================================================" -ForegroundColor Cyan
