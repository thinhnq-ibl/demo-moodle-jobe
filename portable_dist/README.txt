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
