# Quy định Không gian làm việc demo-moodle-jobe

## Quản lý Ngữ cảnh và Bộ nhớ MCP Memory
- Khi thực hiện các tác vụ phát triển, gỡ lỗi hoặc mở rộng hệ thống demo Moodle - Jobe, Antigravity phải luôn sử dụng MCP server `memory` để đọc và ghi các thông tin kiến trúc quan trọng.
- **Dữ liệu kiến trúc cần ghi nhớ:**
  1. Mô hình phân tầng 3 cấp: `course_id` -> `quiz_id` -> `question_id`.
  2. Plugin độc lập `testcase_store` trên MariaDB (cổng 3306).
  3. Cụm Jobe sandbox song song (`jobe1:4001`, `jobe2:4002`).
  4. Subplugin bật/tắt `quizaccess_testcaseexchange` trong `mod/quiz/accessrule/testcaseexchange`.
