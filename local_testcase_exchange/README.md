# CodeRunner Testcase Exchange (`local_testcase_exchange`)

Plugin Moodle cho phép sinh viên thử testcase riêng trên câu hỏi CodeRunner, đề xuất chia sẻ, giảng viên duyệt và mở khóa testcase đã duyệt theo chính sách từng Quiz.

## Chức năng MVP

- Chỉ liệt kê câu hỏi CodeRunner thực sự thuộc Quiz trong course hiện tại.
- Chạy tách biệt mã sinh viên và lời giải tham chiếu qua cơ chế chấm của CodeRunner/Jobe.
- Lưu nhật ký chạy riêng trước khi sinh viên chủ động đề xuất chia sẻ.
- Chuẩn hóa input, chống trùng theo fingerprint và xử lý tranh chấp bằng canonical key.
- Workflow `submitted → needs_explanation / approved / rejected`; có audit log.
- Trao testcase đã duyệt theo chính sách `one_for_one`, idempotent theo người nhận/testcase.
- Capability tách biệt cho xem, chạy, đóng góp, duyệt và quản lý policy.
- Trang health check DB/Jobe tại **Site administration > Plugins > Local plugins > Testcase service health**.

Testcase được duyệt không tự động thay đổi bộ test chấm chính thức của CodeRunner.

## Cài đặt và cấu hình

Đặt thư mục tại `moodle/local/testcase_exchange`, sau đó chạy Moodle upgrade. Cấu hình tại:

**Site administration > Plugins > Local plugins > CodeRunner Testcase Exchange**

Cần cung cấp MariaDB host/port/database/user/password và một hoặc nhiều Jobe server. Tài khoản DB phải có quyền đọc, ghi và tạo/cập nhật schema trong lần migration đầu.

Với Docker Compose, tạo `.env` cạnh `docker-compose.yml`:

```dotenv
TESTCASE_DB_HOST=mariadb
TESTCASE_DB_PORT=3306
TESTCASE_DB_NAME=testcase_store
TESTCASE_DB_USER=moodle_app_writer
TESTCASE_DB_PASSWORD=replace-with-a-secret
TESTCASE_JOBE_SERVERS=jobe1;jobe2
TESTCASE_JOBE_API_KEY=
```

Entrypoint đồng bộ các biến này vào cấu hình Moodle khi container khởi động. Mật khẩu/API key không xuất hiện trên trang health check.

## Sử dụng

1. Giảng viên mở **Kho Testcase & Đổi thưởng** trong course và chọn **Cấu hình chính sách testcase**.
2. Bật Quiz, chọn chế độ duyệt, trao thưởng và giới hạn chạy/input.
3. Với từng câu hỏi, chọn cách tạo test (`stdin`, `testcode`, hoặc template chứa `{{INPUT}}`).
4. Sinh viên nhập mã hiện tại, input, dự đoán và mục đích; chạy riêng rồi chọn **Đề xuất chia sẻ**.
5. Người có capability duyệt xử lý đề xuất trong trang review.

Chi tiết kiến trúc và tiêu chí nghiệm thu nằm trong [kế hoạch triển khai](../docs/ke-hoach-trien-khai-dong-gop-testcase.md).

## Bản quyền

GPL v3.0 hoặc mới hơn.
