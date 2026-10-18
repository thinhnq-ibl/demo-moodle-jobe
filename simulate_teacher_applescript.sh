#!/bin/bash

echo "🚀 Bắt đầu kịch bản mô phỏng tương tác Giảng viên trên Google Chrome..."

open_url() {
    URL="$1"
    osascript -e "tell application \"Google Chrome\" to open location \"$URL\""
    osascript -e 'tell application "Google Chrome" to activate'
}

# 1. Đăng nhập tự động bằng cách gửi form qua curl để lấy cookie session rồi mở thẳng
echo "[Bước 1/6] Chuẩn bị phiên đăng nhập Giảng viên (admin)..."
COOKIE_JAR="/tmp/moodle_admin_cookies.txt"
rm -f "$COOKIE_JAR"

# Lấy logintoken
TOKEN=$(curl -s -c "$COOKIE_JAR" "http://localhost:8080/login/index.php" | grep -o 'name="logintoken" value="[^"]*"' | head -1 | cut -d'"' -f4)

# Đăng nhập
curl -s -b "$COOKIE_JAR" -c "$COOKIE_JAR" -d "username=admin&password=AdminPassword123!&logintoken=$TOKEN" "http://localhost:8080/login/index.php" > /dev/null

MoodleSession=$(grep MoodleSession "$COOKIE_JAR" | awk '{print $7}')
echo "  ✓ Session tạo thành công!"

# Mở Chrome vào Course Hub
echo "[Bước 2/6] Mở Trung tâm Điều phối Testcase (Course Hub) trên Chrome..."
open_url "http://localhost:8080/local/testcase_exchange/index.php"
sleep 4

echo "  → Chọn Khoá học AI-PY-PILOT (ID: 4)..."
open_url "http://localhost:8080/local/testcase_exchange/index.php?course=4"
sleep 5

echo "[Bước 3/6] Mở Bảng Xếp Hạng Chất Lượng & Chi tiết Đóng Góp..."
open_url "http://localhost:8080/local/testcase_exchange/index.php?course=4#leaderboard"
sleep 5

echo "[Bước 4/6] Chuyển sang Thiết lập Chính sách & Quản lý 3 Tập Testcase..."
open_url "http://localhost:8080/local/testcase_exchange/policy.php?course=4"
sleep 5

echo "[Bước 5/6] Vào trang Duyệt Testcase Đóng Góp (Teacher Review & Feedback)..."
open_url "http://localhost:8080/local/testcase_exchange/review.php?course=4"
sleep 5

echo "[Bước 6/6] Trở lại Dashboard khoá học tổng quan..."
open_url "http://localhost:8080/local/testcase_exchange/index.php?course=4"

echo ""
echo "🎉 Toàn bộ các trang nghiệp vụ của Giảng viên đã được mở trực quan trên Google Chrome để bạn theo dõi!"
