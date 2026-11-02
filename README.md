# Moodle CodeRunner & Testcase Exchange Platform (v2.3.1-beta)

Nền tảng kiểm thử và trao đổi testcase giữa sinh viên cho các câu hỏi lập trình CodeRunner trên Moodle LMS, tích hợp cụm Jobe Sandbox chạy song song.

---

## 1. Cấu Trúc Dự Án & Các Thành Phần

| Thư mục / Tệp tin | Vai trò kiến trúc |
| :--- | :--- |
| `local_testcase_exchange/` | Plugin Moodle chính: Quản lý quy trình chạy thử riêng tư, nộp đề xuất, chuẩn hóa testcase, thẩm định, đổi thưởng và bảng xếp hạng (`local_testcase_exchange`). CSDL lưu tại các bảng `{local_tce_*}` của Moodle. |
| `quizaccess_testcaseexchange/` | Subplugin quy tắc truy cập Quiz: Bật/tắt tính năng trao đổi testcase theo từng bài Quiz (`quizaccess_testcaseexchange`). |
| `docker-compose.yml` | Môi trường Docker tích hợp: MariaDB, Cụm 2 Jobe sandbox (`jobe1:4001`, `jobe2:4002`), và ứng dụng Moodle (`moodle_app:8080`). |
| `docs/` | Tài liệu đặc tả, checklist phát hành (`PUBLIC_RELEASE_CHECKLIST.md`), bản nháp Moodle Marketplace (`MARKETPLACE_LISTING.md`). |

---

## 2. Kiến Trúc Hệ Thống & Bảo Mật

1. **CSDL Nội Bộ Chuẩn Moodle**:
   - Toàn bộ dữ liệu (chính sách, lịch sử chạy, đóng góp, thẩm định, trao thưởng) lưu trữ trực tiếp trong CSDL Moodle thông qua Moodle XMLDB và Core DML (`$DB`).
   - Tương thích đầy đủ với MariaDB, MySQL và PostgreSQL.
   - Không yêu cầu CSDL bên ngoài và không yêu cầu cấp quyền CSDL cho máy chủ sandbox Jobe.

2. **Cụm Jobe Sandbox Độc Lập (Stateless REST API)**:
   - Cụm Jobe sandbox chạy độc lập trên mạng Docker cô lập, chỉ tiếp nhận mã nguồn và testcase qua REST API (`/jobe/index.php/restapi/...`) và trả về kết quả stdout/stderr/outcome.
   - Cơ chế cân bằng tải và chịu lỗi (Failover) tự động luân chuyển giữa các node `jobe1` và `jobe2`.

3. **Tường Lửa Phân Quyền & Bảo Mật Dữ Liệu**:
   - **Tách biệt quyền hạn**: Sinh viên chỉ có quyền chạy thử và gửi đề xuất (`local/testcase_exchange:contribute`); chỉ giảng viên/trợ giảng mới có quyền thẩm định (`review`) và cấu hình chính sách (`manage`).
   - **Zero Oracle Leakage**: Sinh viên không bao giờ nhìn thấy mã giải tham chiếu (oracle) trong giao diện.
   - **Tuân thủ Moodle Privacy API**: Cung cấp đầy đủ chức năng tìm kiếm ngữ cảnh, xuất dữ liệu và xóa dữ liệu cá nhân theo GDPR.

---

## 3. Bản Quyền & Giấy Phép (Licensing)

- Mã nguồn 2 Moodle plugins (`local_testcase_exchange` và `quizaccess_testcaseexchange`): Phát hành theo **GNU General Public License v3.0 or later (GPL-3.0-or-later)**.
- Các script cấu hình Docker và kiểm thử hỗ trợ: Phát hành theo **MIT License**.
- Chi tiết xem tại [LICENSE](file:///d:/demo-moodle-jobe/LICENSE).

---

## 4. Kiểm Thử & Kiểm Định Phát Hành (Release Readiness Audit)

Hệ thống đã trải qua kiểm định 4 phần bắt buộc:
- **Test A (Isolation & Migration)**: Xác minh độc lập chính sách giữa các Quiz dùng chung câu hỏi (`uq_quiz_question`) và kiểm thử kịch bản nâng cấp schema.
- **Test B (Permission & Privacy)**: Kiểm tra tường lửa capabilities, chặn rò rỉ oracle và kiểm tra Moodle Privacy API.
- **Test C (CodeRunner Compatibility)**: Kiểm định tương thích thực thi `python3` và `python3_w_input` trên sandbox Jobe.
- **Test D (Concurrent Deduplication)**: Kiểm thử nộp bài đồng thời, xác minh khóa canonical chống trùng lặp và tính idempotent khi nhận thưởng.
