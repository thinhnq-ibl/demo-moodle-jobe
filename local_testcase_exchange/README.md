# CodeRunner Testcase Exchange Moodle Plugin (`local_testcase_exchange`)

Plugin mở rộng dành cho Moodle và CodeRunner, hỗ trợ sinh viên nộp các ca kiểm thử (testcase) độc nhất, tự động lưu trữ vào cơ sở dữ liệu riêng biệt và trao đổi testcase ngẫu nhiên giữa các sinh viên.

## 1. Tính năng nổi bật
* **Tự động cấu hình Prototype:** Tự động tạo kiểu câu hỏi mẫu `python3_testcase_exchange` trong Question Bank (`CR_PROTOTYPES`). Giảng viên không cần biết code template bên dưới.
* **Cấu hình trực quan:** Quản trị viên cấu hình MariaDB `testcase_store` ngay trong **Site Administration > Plugins > Local plugins > CodeRunner Testcase Exchange**.
* **Dashboard tích hợp:** Xem trực tiếp thống kê lượt nộp, tải máy chủ Jobe và bảng xếp hạng sinh viên tại đường dẫn `/local/testcase_exchange/index.php`.

## 2. Cài đặt
1. Tải thư mục này và đặt vào thư mục `moodle/local/testcase_exchange`.
2. Đăng nhập quyền Administrator vào Moodle, hệ thống sẽ tự phát hiện và yêu cầu **Upgrade Moodle database now**.
3. Xác nhận để hoàn tất cài đặt.

## 3. Bản quyền
GPL v3.0 hoặc mới hơn.
