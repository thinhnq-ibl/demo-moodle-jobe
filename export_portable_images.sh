#!/bin/bash
set -e

echo "=================================================================="
echo " 📦 ĐÓNG GÓI & XUẤT PORTABLE DOCKER IMAGES (MOODLE + JOBE + MARIADB)"
echo "=================================================================="

# 1. Cập nhật backup CSDL và moodledata mới nhất
echo "[1/4] Đồng bộ bản sao lưu CSDL & moodledata mới nhất..."
./export_backup.sh

# 2. Build lại portable images với bản backup mới
echo ""
echo "[2/4] Build các Docker Images portable..."
docker build -t mariadb-portable:latest -f Dockerfile.mariadb.portable .
docker build -t moodle-portable:latest -f Dockerfile.moodle.portable .
docker tag trampgeek/jobeinabox:latest jobe-portable:latest

# 3. Tạo thư mục phát hành (distribution directory)
DIST_DIR="portable_dist"
rm -rf "$DIST_DIR"
mkdir -p "$DIST_DIR"

cp docker-compose.portable.yml "$DIST_DIR/"
cp run_stack.sh "$DIST_DIR/"
cp run_stack.bat "$DIST_DIR/"
cp HUONG_DAN_CAI_DAT_PORTABLE.md "$DIST_DIR/HUONG_DAN_CAI_DAT.md"
chmod +x "$DIST_DIR/run_stack.sh"

cat <<'EOF' > "$DIST_DIR/README.txt"
==================================================================
 HƯỚNG DẪN KHỞI CHẠY MOODLE PORTABLE STACK TRÊN MÁY BẤT KỲ
==================================================================

YÊU CẦU DUY NHẤT:
- Máy tính đã cài đặt và đang mở Docker Desktop.

CÁCH CHẠY:
1. Trên Windows:
   - Nhấp đúp chuột vào file: run_stack.bat

2. Trên macOS hoặc Linux:
   - Mở Terminal tại thư mục này và gõ:
     ./run_stack.sh

3. Truy cập hệ thống:
   - Moodle LMS:  http://localhost:8080
   - Tài khoản:   admin
   - Mật khẩu:    AdminPassword123!
   - Dashboard:   http://localhost:8080/testcase_dashboard.php
   - Jobe 1:      http://localhost:4001
   - Jobe 2:      http://localhost:4002

CÁCH DỪNG HỆ THỐNG:
   - docker compose -f docker-compose.portable.yml down
==================================================================
EOF

# 4. Xuất các Docker Images đã cập nhật ra file nén .tar.gz (bỏ qua Jobe vì không đổi)
echo ""
echo "[3/4] Đang xuất Docker Images cập nhật (moodle-portable & mariadb-portable) ra file nén..."
echo "(Jobe Sandbox không có thay đổi nên được bỏ qua để tối ưu dung lượng và thời gian export)"
IMAGE_FILE="$DIST_DIR/moodle_stack_images.tar.gz"

docker save moodle-portable:latest mariadb-portable:latest | gzip > "$IMAGE_FILE"

FILE_SIZE=$(ls -lh "$IMAGE_FILE" | awk '{print $5}')
echo "✓ Đã xuất thành công: $IMAGE_FILE ($FILE_SIZE)"

# 5. Đóng gói file zip hoàn chỉnh để gửi người khác
echo ""
echo "[4/4] Đóng gói trọn gói thành file zip..."
ZIP_NAME="moodle_portable_full_package.zip"
zip -rq "$ZIP_NAME" "$DIST_DIR"
ZIP_SIZE=$(ls -lh "$ZIP_NAME" | awk '{print $5}')

echo ""
echo "=================================================================="
echo "🎉 HOÀN TẤT ĐÓNG GÓI PORTABLE STACK!"
echo "📦 Thư mục phân phối: $DIST_DIR/"
echo "📦 File zip trọn gói: $ZIP_NAME ($ZIP_SIZE)"
echo ""
echo "Người dùng máy khác chỉ cần tải file zip hoặc thư mục $DIST_DIR về"
echo "và chạy run_stack.sh (Mac/Linux) hoặc run_stack.bat (Windows)!"
echo "=================================================================="
