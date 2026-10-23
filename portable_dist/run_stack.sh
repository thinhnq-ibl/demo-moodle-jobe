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

# 2. Tự động nạp hoặc cập nhật images
NEED_LOAD=0
if [ -f "moodle_stack_images.tar.gz" ]; then
    IMAGE_FILE="moodle_stack_images.tar.gz"
    if [ ! -f ".loaded_image_hash" ] || [ "$IMAGE_FILE" -nt ".loaded_image_hash" ] || ! docker image inspect moodle-portable:latest >/dev/null 2>&1; then
        NEED_LOAD=1
    fi
elif [ -f "moodle-portable.tar.gz" ]; then
    IMAGE_FILE="moodle-portable.tar.gz"
    if [ ! -f ".loaded_image_hash" ] || [ "$IMAGE_FILE" -nt ".loaded_image_hash" ] || ! docker image inspect moodle-portable:latest >/dev/null 2>&1; then
        NEED_LOAD=1
    fi
fi

if [ "$NEED_LOAD" -eq 1 ]; then
    echo "⏳ Đang nạp/cập nhật Docker Images từ $IMAGE_FILE..."
    docker load -i "$IMAGE_FILE"
    if [ -f "mariadb-portable.tar.gz" ] && [ "$IMAGE_FILE" != "moodle_stack_images.tar.gz" ]; then
        docker load -i mariadb-portable.tar.gz
    fi
    if [ -f "jobe-portable.tar.gz" ] && [ "$IMAGE_FILE" != "moodle_stack_images.tar.gz" ]; then
        docker load -i jobe-portable.tar.gz
    fi
    touch .loaded_image_hash
    echo "✓ Đã nạp thành công các Docker image mới nhất."
else
    echo "✓ Docker images chính đã sẵn sàng."
fi

# 3. Đảm bảo Jobe sandbox sẵn sàng (nếu chưa có trong hệ thống)
if ! docker image inspect jobe-portable:latest >/dev/null 2>&1; then
    if docker image inspect trampgeek/jobeinabox:latest >/dev/null 2>&1; then
        echo "✓ Gán nhãn jobe-portable:latest từ trampgeek/jobeinabox:latest..."
        docker tag trampgeek/jobeinabox:latest jobe-portable:latest
    elif [ -f "jobe-portable.tar.gz" ]; then
        echo "⏳ Đang nạp jobe-portable.tar.gz..."
        docker load -i jobe-portable.tar.gz
    else
        echo "⏳ Jobe sandbox chưa có, đang tải từ Docker Hub (trampgeek/jobeinabox:latest)..."
        docker pull trampgeek/jobeinabox:latest
        docker tag trampgeek/jobeinabox:latest jobe-portable:latest
    fi
else
    echo "✓ Jobe sandbox image đã sẵn sàng."
fi

# 4. Khởi chạy stack qua docker-compose.portable.yml
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
