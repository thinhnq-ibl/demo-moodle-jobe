#!/bin/bash
set -e

echo "=========================================================="
echo "🚀 KHỞI CHẠY HỆ THỐNG MOODLE + JOBE PORTABLE (1-CLICK)"
echo "=========================================================="

# 1. Kiểm tra Docker
if ! command -v docker &> /dev/null; then
    echo "❌ Lỗi: Máy tính chưa cài đặt Docker. Vui lòng cài Docker Desktop trước!"
    exit 1
fi

# 2. Tự động nạp images nếu chưa có trong máy
if ! docker image inspect moodle-portable:latest >/dev/null 2>&1; then
    if [ -f "moodle_stack_images.tar.gz" ]; then
        echo "⏳ Đang nạp Docker Images từ moodle_stack_images.tar.gz..."
        docker load -i moodle_stack_images.tar.gz
    elif [ -f "moodle-portable.tar.gz" ]; then
        echo "⏳ Đang nạp từng Docker Image..."
        docker load -i moodle-portable.tar.gz
        docker load -i mariadb-portable.tar.gz
        docker load -i jobe-portable.tar.gz
    else
        echo "❌ Lỗi: Không tìm thấy file Docker Image đóng gói (*.tar.gz) trong thư mục hiện tại!"
        exit 1
    fi
else
    echo "✓ Docker images đã có sẵn trong máy."
fi

# 3. Khởi chạy stack qua docker-compose.portable.yml
echo "⏳ Đang khởi động các container..."
docker compose -f docker-compose.portable.yml up -d

echo ""
echo "=========================================================="
echo "🎉 HỆ THỐNG ĐÃ KHỞI CHẠY THÀNH CÔNG!"
echo "👉 Moodle LMS: http://localhost:8080"
echo "   Tài khoản:  admin"
echo "   Mật khẩu:   AdminPassword123!"
echo "👉 Dashboard:  http://localhost:8080/testcase_dashboard.php"
echo "👉 Jobe1:      http://localhost:4001"
echo "👉 Jobe2:      http://localhost:4002"
echo "=========================================================="
