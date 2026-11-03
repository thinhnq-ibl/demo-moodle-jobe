# Public release checklist — `local_testcase_exchange` & `quizaccess_testcaseexchange` (v2.3.1-beta)

## 7 Core Release Readiness Checks (Release Candidate)

- [x] **1. Chuyển các kiểm thử thay đổi dữ liệu sang môi trường thử nghiệm cô lập**:
  - Script test harness [run_rigorous_tests.php](file:///d:/demo-moodle-jobe/local_testcase_exchange/tests/cli/run_rigorous_tests.php) nằm riêng trong `tests/cli/` của plugin.
  - Toàn bộ dữ liệu sinh ra bởi test (runs, rewards, transient users) được dọn sạch sau khi test kết thúc.
- [x] **2. Chặn chạy test phá hủy dữ liệu trên Moodle production**:
  - Tích hợp Production Safety Guard: Chặn script ngay lập tức nếu không bật chế độ `DEVELOPER` debugging hoặc không cung cấp cờ tường minh `--force-test-environment`.
- [x] **3. Tạo fixture độc lập, không phụ thuộc tài khoản và Quiz có sẵn**:
  - Cơ chế Dynamic Fixture Resolver tự động tìm hoặc sinh fixture `tce_fixture_*` cho giáo viên, học sinh, khóa học và câu hỏi CodeRunner, không phụ thuộc cứng vào dữ liệu IUH cũ.
- [x] **4. Kiểm tra các script CLI không thể gọi từ HTTP**:
  - Cả 3 script CLI (`run_rigorous_tests.php`, `concurrency_worker_submit.php`, `concurrency_worker_claim.php`) đều có `define('CLI_SCRIPT', true)`.
  - Kiểm chứng HTTP: Gọi qua web server trả về thông báo từ chối `Command line scripts can not be executed from the web interface`.
- [x] **5. Quét secret, dữ liệu cá nhân và lịch sử Git**:
  - Không có secret, API key hay private key thật trong codebase.
  - Lịch sử Git sạch, không có file `.env` nào bị commit.
- [x] **6. Xác nhận commit, version, ZIP và SHA-256 thống nhất**:
  - Cả 2 plugin đồng bộ version: `2026110201`, release `2.3.1-beta`, maturity `MATURITY_BETA`.
  - Cấu trúc thư mục gốc ZIP chuẩn: `testcase_exchange\` và `testcaseexchange\`.
  - Checksums:
    * `moodle-local_testcase_exchange-2.3.1-beta.zip`: `8c48e9fea9f8ddfae614b3ccc0511fa6e82519e15be9b2673492aad8a3cfa84d`
    * `moodle-quizaccess_testcaseexchange-2.3.1-beta.zip`: `5b5c67a2c55593b6d131e2b1e9e958b53fe7efd619b5bf323015a34974c61a31`
- [x] **7. Tài liệu chỉ tuyên bố những môi trường đã kiểm chứng**:
  - Đã kiểm chứng thực nghiệm: Moodle 4.4.12+ (Build: 20251212), PHP 8.2.33, MariaDB 11.4, Cụm Jobe Sandbox REST API.
  - Kỳ vọng tương thích theo thiết kế (chưa kiểm thử thực nghiệm độc lập): Moodle 4.3, Moodle 4.5 LTS, PHP 8.1, PostgreSQL 15+.

## Latest automated audit — 2026-11-02

- Release version: `2.3.1-beta` (build `2026110201`).
- Moodle upgrade: pass via CLI upgrade to `2026110201`.
- Schema: `mdl_local_tce_policies` with compound unique index `uq_quiz_question (quiz_id, question_id)`.
- 4-Part Multi-Process Concurrency Audit:
  - Test A: PASSED.
  - Test B: PASSED.
  - Test C: PASSED.
  - Test D: PASSED (True Multi-Process Submit & Claim).
