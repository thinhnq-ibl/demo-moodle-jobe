# Báo Cáo Kiểm Định Bản Ứng Viên Phát Hành (Release Candidate Audit v2.3.1-beta)

- **Ngày kiểm định:** 2026-11-02
- **Môi trường thực thi:** Moodle 4.4.12+ (Build: 20251212) | PHP 8.2.27 | MariaDB 11.4 | Cụm 2 Jobe Sandbox (`jobe1:4001`, `jobe2:4002`)
- **Tập dữ liệu kiểm thử:** Khóa học lập trình Python thực tế (Course ID=4, 68 Quizzes, 656 câu hỏi CodeRunner)
- **Tệp kiểm thử tự động:** `local/testcase_exchange/tests/cli/run_rigorous_tests.php`

---

## 1. Kết Quả Kiểm Thử Thực Nghiệm 4 Hạng Mục

| Bài Kiểm Thử | Trạng Thái | Phương Pháp & Bằng Chứng Cụ Thể |
| :--- | :---: | :--- |
| **Test A: Isolation & Schema Migration** | **PASSED** | • Khóa đơn cũ `uq_question` trên riêng `question_id` bị drop sạch.<br>• Khóa liên hợp `uq_quiz_question` trên `(quiz_id, question_id)` tồn tại trong MariaDB.<br>• Cấu hình Quiz 3 (`stdin, trim`) và Quiz 4 (`testcode, raw`) trên cùng Question ID=567 hoàn toàn độc lập; cập nhật Quiz 3 không làm ảnh hưởng Quiz 4. |
| **Test B: Permission & Full Privacy API** | **PASSED** | • Tường lửa capabilities: Chặn sinh viên truy cập hàm quản trị (`required_capability_exception`).<br>• Zero Oracle Leakage: `show_oracle = false` tuyệt đối trong student view.<br>• Chu trình Privacy API hoàn chỉnh: `get_contexts_for_userid` tìm ra đúng context; `export_user_data` xuất thành công; `delete_data_for_user` xóa sạch các bản ghi trong `local_tce_runs` và `local_tce_rewards` (runs còn 0, rewards còn 0). |
| **Test C: CodeRunner Parity & Zero Penalty** | **PASSED** | • Kiểm định thành công `python3` (MidTestQ04 - Stdin '20') và `python3_w_input` (InputOut01 - non-prototype).<br>• Xử lý an toàn chuỗi rỗng `""` và khoảng trắng `"   \n\t  "` không phát sinh ngoại lệ.<br>• Zero Interference: Không làm tăng `attempts`, không thay đổi điểm số và không áp penalty lên bài thi của sinh viên (0 -> 0 attempts). |
| **Test D: True Multi-Process Concurrency** | **PASSED** | • **Subtest D1 (Parallel Submit)**: Hai tiến trình OS độc lập (PID 580 và PID 582) chạy song song qua barrier file lock. Kết quả: 1 tiến trình nhận `submitted` (canonical), 1 tiến trình nhận `duplicate` trỏ về canonical. CSDL ghi nhận chính xác 1 bản ghi canonical và 2 contributions không lỗi transaction.<br>• **Subtest D2 (Parallel Claim)**: Hai tiến trình OS độc lập (PID 584 và PID 586) cùng claim 1 reward qua `SELECT ... FOR UPDATE`. Cả 2 kết thúc thành công, reward `is_claimed = 1`, bảng `local_tce_views` chỉ ghi nhận đúng 1 lượt xem (chống nhân đôi reward). |

---

## 2. Rà Soát Bảo Mật & Làm Sạch Release (P0)

1. **Làm sạch CSDL & Secrets**:
   - Loại bỏ toàn bộ các biến môi trường cũ `TESTCASE_DB_*` khỏi `docker-compose.yml`.
   - Plugin lưu trữ 100% dữ liệu trong bảng nội bộ Moodle `{local_tce_*}` qua Core DML.
   - Cụm Jobe sandbox chạy độc lập qua REST API, không có quyền hay tài khoản truy cập MariaDB.
2. **Di chuyển Test Harness vào Plugin Codebase**:
   - Chuyển toàn bộ kịch bản kiểm thử vào `local_testcase_exchange/tests/cli/`:
     - `run_rigorous_tests.php`
     - `concurrency_worker_submit.php`
     - `concurrency_worker_claim.php`
   - Bất kỳ kỹ sư hoặc người phản biện nào đều có thể tự tái lập kết quả bằng lệnh:
     `php /var/www/html/local/testcase_exchange/tests/cli/run_rigorous_tests.php`.
3. **Phân Định Bản Quyền (License Scoping)**:
   - Plugin Moodle (`local_testcase_exchange` và `quizaccess_testcaseexchange`): **GPL-3.0-or-later**.
   - Công cụ Docker và script hỗ trợ: **MIT License**.

---

## 3. Đánh Giá Rủi Ro & Lộ Trình Phát Hành

### Rủi ro 1: Vòng đời hỗ trợ bảo mật Moodle (Moodle Support Lifecycle)
- **Hiện trạng:** Moodle 4.4 kết thúc hỗ trợ bảo mật vào **08/12/2025**.
- **Giải pháp:** Bản v2.3.1-beta được chốt làm **Release Candidate (RC)** đã được kiểm chứng trên Moodle 4.4.12+. Mục tiêu tiếp theo trước khi công bố bản Stable và nộp Moodle Marketplace là chạy kiểm thử toàn diện trên **Moodle 4.5 LTS** (hỗ trợ bảo mật đến **04/10/2027**).

### Rủi ro 2: Khả năng tương thích PostgreSQL
- **Hiện trạng:** Mã nguồn plugin được viết theo chuẩn Moodle Core DML `$DB`. Tuy nhiên, các truy vấn khóa hàng đồng thời (`FOR UPDATE`) và tính năng phân trang giữa các hệ quản trị có thể có dị biệt.
- **Giải pháp:** Trong giai đoạn Public Beta, **chỉ tuyên bố hỗ trợ chính thức MariaDB / MySQL**. Đưa việc thiết lập container kiểm thử tự động với PostgreSQL vào danh mục P1 trước khi nộp Marketplace.

---

## 4. Quyết Định Phát Hành

- **GitHub Public Beta (Release Candidate v2.3.1-beta):** **ĐẠT ĐIỀU KIỆN (Giai đoạn 1)** sau khi hoàn thành các hạng mục P0, tích hợp test harness tự động hóa vào codebase và dọn dẹp toàn bộ secrets.
- **Moodle Marketplace (Giai đoạn 2):** **CẦN HOÀN THIỆN P1** (Kiểm thử trên Moodle 4.5 LTS, kiểm thử thực nghiệm PostgreSQL, tách 2 repository riêng biệt, thiết lập workflow `moodle-plugin-ci`).
