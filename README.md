# Hệ Thống Moodle + CodeRunner + Cụm Jobe Song Song (Auto-Grading & Testcase Exchange)

Hệ thống triển khai LMS Moodle tích hợp plugin CodeRunner và cụm máy chủ Sandbox Jobe song song, hỗ trợ tự động chấm bài lập trình và nền tảng trao đổi / kiểm tra trùng lặp testcase giữa các sinh viên.

---

## 1. Cấu Trúc Thư Mục & Các Tệp Tin

| Tệp tin | Chức năng |
| :--- | :--- |
| `docker-compose.yml` | Cấu hình dịch vụ Docker: MariaDB, cụm Jobe (`jobe1`, `jobe2`), và Moodle |
| `Dockerfile` | Image Moodle 4.4 PHP 8.2 tích hợp sẵn CodeRunner và Adaptive Behaviour |
| `Dockerfile.jobe` | Image Jobe Sandbox tích hợp sẵn thư viện `python3-pymysql` kết nối MariaDB |
| `entrypoint.sh` | Kịch bản tự động khởi tạo CSDL, cấp quyền và cấu hình Moodle khi chạy container |
| `testcase_dashboard.php` | Mã nguồn trang Dashboard thống kê và xếp hạng đóng góp testcase trên Moodle |
| `test_concurrent_submission.py` | Kịch bản kiểm thử mô phỏng 2 sinh viên nộp bài đồng thời trên cụm 2 Jobe |

---

## 2. Thông Tin Truy Cập & Tài Khoản

- **Trang Moodle LMS**: [http://localhost:8080](http://localhost:8080)
- **Tài khoản Quản trị / Giảng viên**:
  - Username: `admin`
  - Password: `AdminPassword123!`
- **Tài khoản Sinh viên mẫu**:
  - Sinh viên 1: `student1` / `StudentPassword123!`
  - Sinh viên 2: `student2` / `StudentPassword123!`
- **Trang Dashboard Thống Kê Testcase**: [http://localhost:8080/testcase_dashboard.php?course=2](http://localhost:8080/testcase_dashboard.php?course=2)
- **Cụm Jobe Sandbox**:
  - `jobe1`: cổng `4001` (nội bộ `jobe1:80`)
  - `jobe2`: cổng `4002` (nội bộ `jobe2:80`)

---

## 3. Kiến Trúc Cơ Sở Dữ Liệu & Bảo Mật

Hệ thống sử dụng **MariaDB** với 2 cơ sở dữ liệu hoàn toàn tách biệt:
1. **`moodle`**: Lưu trữ dữ liệu hệ thống Moodle (users, courses, quizzes, grades).
2. **`testcase_store`**: Lưu trữ kho testcase do sinh viên đóng góp (`student_testcases`, `testcase_exchanges`).
   - User `jobe_user` (`JobeSecret123!`): Chỉ có quyền thêm/đọc trên `testcase_store` và **bị cấm 100% khỏi CSDL `moodle`**.
   - User `moodle_reader` (`ReaderSecret123!`): Moodle kết nối với quyền chỉ đọc (`SELECT`) để làm báo cáo thống kê.

---

## 4. Lệnh Vận Hành Nhanh

Khởi động hệ thống:
```bash
docker compose up -d
```

Xem trạng thái các container:
```bash
docker compose ps
```

Chạy kịch bản kiểm thử 2 sinh viên nộp bài đồng thời:
```bash
python3 test_concurrent_submission.py
```

Dừng hệ thống:
```bash
docker compose stop
```
