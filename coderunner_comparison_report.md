# BÁO CÁO KIỂM CHỨNG THỰC NGHIỆM ĐA TẦNG VÀ ĐỐI CHIẾU KIẾN TRÚC
## CODERUNNER SCRATCHPAD UI & MẪU `python3_program_testing` VS. MULTISOLVE (`local_testcase_exchange`)

---

### MỤC TIÊU VÀ PHƯƠNG PHÁP TIẾP CẬN

Báo cáo này cung cấp bằng chứng thực nghiệm khách quan, độc lập nhằm xác định phạm vi chức năng và kiến trúc giữa hai cơ chế có sẵn của CodeRunner (**Scratchpad UI** và **`python3_program_testing`**) so với giải pháp **MultiSolve** (`local_testcase_exchange` & `quizaccess_testcaseexchange`).

Phương pháp kiểm chứng được chia tách độc lập thành **3 tầng bằng chứng**:
1. **Tầng 1 — Phân tích mã nguồn tĩnh (Static Code Analysis)**: Rà soát trực tiếp cây thư mục, tệp PHP/JavaScript, template Mustache, logic bóc tách JSON và các luồng dữ liệu của CodeRunner và MultiSolve.
2. **Tầng 2 — Thực thi API & Web Service bằng Script độc lập**: Đo lường sự thay đổi trạng thái câu hỏi, điểm số, điểm Quiz và penalty trước–sau từng thao tác Run và Check qua 3 chuỗi thực nghiệm chuẩn.
3. **Tầng 3 — Thao tác trình duyệt thực tế bằng tài khoản sinh viên (Playwright Browser E2E Automation)**: Tự động hóa trình duyệt Chromium với tài khoản sinh viên `student1`, thực hiện tương tác Ace Editor, bấm nút Run/Check, tải lại trang kiểm tra phục hồi bản nháp, thử nghiệm Expected sai (T2/T4) và chụp ảnh màn hình minh chứng.

---

## 1. MỤC LỤC TÀI NGUYÊN BẰNG CHỨNG (ARTIFACT REPOSITORY)

Toàn bộ mã nguồn, dữ liệu XML câu hỏi, script kiểm thử, nhật ký thực thi và ảnh chụp màn hình được đóng gói trong kho lưu trữ `verification_evidence.zip` (SHA-256: `b3f4cf039d5ea99ab097aec5137ba64302bb4c1280af9087e2da2ea8fada7e94`) và lưu trữ theo đường dẫn tương đối trong dự án:

| Hạng mục | Đường dẫn tệp tương đối | Mô tả tóm tắt |
|---|---|---|
| **Kho đóng gói đầy đủ** | `verification_evidence.zip` | Gói nén zip toàn bộ thư mục bằng chứng (SHA-256 xác thực toàn vẹn) |
| **XML Mẫu gốc CodeRunner** | `verification_evidence/questions/original_programtestingprototypeandexamples.xml` | Mẫu XML nguyên bản từ tác giả Richard Lobb (`qtype_coderunner/samples`) |
| **XML Đã thử nghiệm** | `verification_evidence/questions/test_questions_category_174.xml` | Toàn bộ câu hỏi xuất từ Category 174 (Prototype 792, Q793 Program Testing, Q794 Scratchpad) |
| **Script Đo lường Điểm/Penalty** | `verification_evidence/scripts/run_detailed_penalty_experiment.php` | Script PHP CLI thực nghiệm 3 chuỗi đo đạc điểm số, trạng thái và mức phạt penalty |
| **Script E2E Trình duyệt** | `verification_evidence/scripts/coderunner_browser_verification.spec.js` | Kịch bản Playwright E2E mô phỏng thao tác sinh viên, thử sai expected và kiểm tra khôi phục nháp |
| **Log Thực thi Điểm/Penalty** | `verification_evidence/logs/marks_and_penalties_log.txt` | Nhật ký thô ghi nhận 3 chuỗi đo lường (Baseline 100%, Phạt 10% penalty, Run không phạt) |
| **Log Trình duyệt E2E** | `verification_evidence/logs/browser_execution_logs.txt` | Nhật ký thực thi 9 bước Playwright test (1 passed, 33.0 giây) |
| **Ảnh 01: Scratchpad ban đầu** | `verification_evidence/screenshots/01_scratchpad_initial_state.png` | Giao diện câu hỏi Scratchpad UI; khu vực nháp đang thu gọn |
| **Ảnh 02: Bấm Run thành công** | `verification_evidence/screenshots/02_scratchpad_run_success.png` | Scratchpad mở rộng, bấm Run, kết quả `8` hiển thị ở thẻ `<pre>` |
| **Ảnh 03: Lỗi Runtime trên Run** | `verification_evidence/screenshots/03_scratchpad_runtime_error.png` | Phép chia 0 hiển thị lỗi `ZeroDivisionError` dạng văn bản thô từ Jobe |
| **Ảnh 03b: Khôi phục sau Reload** | `verification_evidence/screenshots/03b_scratchpad_draft_after_reload.png` | Tải lại trang (Reload), dữ liệu nháp được giữ nguyên qua cơ chế lưu trữ client-side |
| **Ảnh 04: Check nộp bài Câu 1** | `verification_evidence/screenshots/04_scratchpad_checked.png` | Giao diện sau khi bấm Check câu hỏi Scratchpad UI |
| **Ảnh 05: Bảng Program Testing** | `verification_evidence/screenshots/05_program_testing_table_initial.png` | Giao diện Table UI của `python3_program_testing` với 2 cột `Test` và `Result` |
| **Ảnh 06a: Nhập Expected sai** | `verification_evidence/screenshots/06a_program_testing_table_wrong_expected.png` | Sinh viên nhập ca kiểm thử T2/T4 với Expected cố ý sai (`5` thay vì `8`) |
| **Ảnh 06b: Nhập Expected đúng** | `verification_evidence/screenshots/06b_program_testing_table_correct.png` | Sinh viên sửa lại Expected đúng (`8`) |
| **Ảnh 07a: Phản hồi Expected sai** | `verification_evidence/screenshots/07a_program_testing_wrong_expected_feedback.png` | Phản hồi từ Jobe: `"Oops: you marked the right answer wrong!"` và điểm 0 |
| **Ảnh 07b: Phản hồi Thành công** | `verification_evidence/screenshots/07b_program_testing_success_feedback.png` | Bảng phản hồi khi vượt qua toàn bộ các ca kiểm thử và diệt các mutant |
| **Ảnh 08: Tổng kết điểm bài thi** | `verification_evidence/screenshots/08_quiz_review_grades.png` | Trang Review tổng kết: Điểm bài thi, penalty và trạng thái Gradebook |

---

## 2. MÔI TRƯỜNG THỰC NGHIỆM

- **Moodle Core**: Moodle 4.4.12+ (Build: 2024042212.01), PHP 8.2.33, MariaDB 11.x.
- **CodeRunner**: Version 5.10.2 (Build: `2026091501`), commit Git: `bab106d480716330d1f3330433652b3c8c8d174d`.
- **Question Behaviour**: `qbehaviour_adaptive_adapted_for_coderunner` (v1.4.7, Build: `2026081000`).
- **Jobe Sandbox**: 2 container chạy song song (`moodle_jobe1:4001`, `moodle_jobe2:4002`), Python 3.12.3.
- **Tài khoản kiểm chứng**: Giảng viên / Quản trị: `admin` | Sinh viên thử nghiệm: `student1`.
- **Ngữ cảnh kiểm thử**: Danh mục câu hỏi riêng Category ID `174` (`Test_Comparison_CodeRunner`) trong Course 2 (`CS101`). Bài kiểm tra: `Quiz 76 - Thử nghiệm CodeRunner Scratchpad & Program Testing` (ID: 76, CM ID: 112). **Không sửa Moodle core, không can thiệp danh mục `CR_PROTOTYPES` hệ thống và không tác động dữ liệu sinh viên thật**.
- **Cấu hình thay đổi duy nhất**: Bật `wsenabled = 1` trong cấu hình `qtype_coderunner` để cho phép giao diện sinh viên gọi web service Jobe.

---

## 3. TÁCH BẠCH 3 TẦNG BẰNG CHỨNG THỰC TẾ

### Tầng 1: Kiểm chứng Mã nguồn Tĩnh (Static Code Analysis)

#### 1. CodeRunner Scratchpad UI:
- **Tệp mã nguồn**: `question/type/coderunner/amd/src/ui_scratchpad.js`, `outputdisplayarea.js`, `templates/scratchpad.mustache`.
- **Cấu trúc trường dữ liệu**:
  Template `scratchpad.mustache` định nghĩa 2 vùng soạn thảo:
  - Hộp trên: `answer_code` (mã làm bài chính).
  - Hộp dưới: `test_code` (mã nháp trong khối collapse).
  - Thanh điều khiển: `<button id="..._run-btn">Run</button>` và checkbox `prefix_ans`.
  - Vùng hiển thị output: `<div id="..."><pre id="...-text">...</pre></div>`.
  $\rightarrow$ **Kết luận từ mã nguồn: Hoàn toàn không có trường `Expected Output`, không có cấu hình Lời giải tham chiếu (Oracle) trong giao diện Scratchpad.**
- **Cơ chế bóc tách khi nộp bài (`classes/jobrunner.php:104-109`)**:
  ```php
  if ($question->extractcodefromjson) {
      $json = json_decode($code, true);
      if ($json !== null && isset($json[constants::ANSWER_CODE_KEY])) {
          $code = $json[constants::ANSWER_CODE_KEY][0]; // CHỈ LẤY answer_code ĐỂ CHẤM
      }
  }
  ```
  $\rightarrow$ Dữ liệu mã nháp `test_code` trong Scratchpad chỉ phục vụ tương tác tạm thời của sinh viên và bị bóc tách loại bỏ khi hệ thống thực hiện chấm điểm chính thức.

#### 2. Mẫu `python3_program_testing`:
- **Tệp mã nguồn**: `samples/programtestingprototypeandexamples.xml` (Prototype ID: 792).
- **Cấu trúc template Twig & Combinator**:
  - `uiplugin`: `table` (Template parameters: `table_column_headers: ["Test", "Result"]`).
  - Sinh viên nhập một mảng JSON 2 chiều chứa các ca kiểm thử: `[["Test 1", "Result 1"], ["Test 2", "Result 2"]]`.
  - Tác giả câu hỏi cung cấp danh sách hàm trong `TESTCASES`:
    - `TESTCASES[0]`: Hàm chuẩn (Reference Solution), `expected = 'Yes'`.
    - `TESTCASES[1..n]`: Các hàm có lỗi chủ đích (Mutants), `expected = 'No'`.
  - Vòng lặp kiểm thử trong template Python:
    ```python
    for TEST in TESTCASES:
        prog_to_test = TEST.testcode # Hàm do tác giả cung cấp
        pass_all_tests = True
        for testcode, expected in student_tests:
            got = strip_trailing(run_prog(prog_to_test + '\n' + testcode))
            if got != expected:
                pass_all_tests = False
        print("Yes" if pass_all_tests else "No")
        if loop.first and not pass_all_tests:
            print("Oops: you marked the right answer wrong!", file=sys.stderr)
    ```
  $\rightarrow$ **Kết luận từ mã nguồn**: Thiết kế nhiệm vụ yêu cầu sinh viên nộp bộ kiểm thử thay vì lời giải chương trình. Sinh viên đóng vai trò kiểm thử các đoạn mã do tác giả cài đặt sẵn nhằm phát hiện đột biến.

---

### Tầng 2: Kiểm chứng API, Web Service và Đo lường Điểm/Penalty bằng Script độc lập

Script `verification_evidence/scripts/run_detailed_penalty_experiment.php` được thực thi trực tiếp trên Moodle CLI, tiến hành đo lường 3 chuỗi thao tác độc lập trên tài khoản `student1` tại Quiz 76. Toàn bộ thông số được lưu trữ tại `verification_evidence/logs/marks_and_penalties_log.txt`:

#### Chi tiết 3 chuỗi thực nghiệm đo đạc:

1. **Chuỗi 1: Code đúng $\rightarrow$ Check $\rightarrow$ Finish (Xác lập Baseline chuẩn)**
   - Slot 1 (Scratchpad): Nộp `def find_max(lst): return max(lst)`.
   - Kết quả sau Check: Fraction = `1.0` (100%), Mark = `1.0 / 1.0`, State = `complete`, Steps trong DB = 2.
   - Slot 2 (Program Testing): Nộp bộ test đúng (T1). Fraction = `1.0`, Mark = `1.0 / 1.0`.
   - Sau khi Finish: Attempt ID = 1114, Tổng điểm thô = `2.0 / 2.0`, Điểm Quiz quy đổi = `10.0 / 10.0`.
   - **Mức phạt penalty: 0%**.

2. **Chuỗi 2: Code sai $\rightarrow$ Check $\rightarrow$ Code đúng $\rightarrow$ Check $\rightarrow$ Finish (Đo lường Penalty thực tế)**
   - Lần Check 1: Nộp code sai `def find_max(lst): return lst[-1]`.
     - Fraction = `0.0`, Mark = `0.0`, State = `todo`, Steps trong DB = 2.
   - Lần Check 2: Sửa thành code đúng `def find_max(lst): return max(lst)`.
     - Fraction = `0.90` (giảm chính xác 10% theo cấu hình `penaltyregime = 10, 20, ...`).
     - Mark câu hỏi = `0.90 / 1.0`, State = `complete`, Steps trong DB = 3.
   - Sau khi Finish: Attempt ID = 1115, Tổng điểm thô = `0.9 / 2.0`, Điểm Quiz quy đổi = `4.5 / 10.0`.
   - **Mức phạt penalty thực tế đo được: Đúng 10% cho 1 lần nộp sai**.

3. **Chuỗi 3: Run 5 lần trên Scratchpad $\rightarrow$ Code đúng $\rightarrow$ Check $\rightarrow$ Finish (Kiểm tra tác động của Run)**
   - Steps trong CSDL trước khi Run: 1 step.
   - Thực hiện 5 lần bấm Run liên tiếp trên Scratchpad UI (gọi sandbox Jobe trực tiếp). Jobe trả về kết quả `20` cho mỗi lần gọi.
   - Steps trong CSDL sau 5 lần Run: **Vẫn là 1 step (không phát sinh bản ghi nào trong `mdl_question_attempt_steps`)**.
   - Bấm Check lần đầu với code đúng: Fraction = `1.0`, Mark = `1.0 / 1.0`, Steps = 2.
   - Sau khi Finish: Attempt ID = 1116, Điểm đạt = `1.0 / 1.0`.
   - **Mức phạt penalty: 0% (Chứng minh nút Run hoàn toàn không bị tính penalty hay thay đổi điểm số)**.

#### Bảng Tổng hợp Đo lường Điểm số và Penalty thực nghiệm:

| Chuỗi thực nghiệm | Kịch bản chi tiết | Fraction đạt được | Điểm câu hỏi | Điểm Quiz (hệ 10) | Mức phạt Penalty |
|---|---|:---:|:---:|:---:|:---:|
| **Chuỗi 1** | Code đúng $\rightarrow$ Check $\rightarrow$ Finish | `1.00` | 1.00 / 1.00 | 10.00 / 10.00 | **0%** |
| **Chuỗi 2** | Code sai $\rightarrow$ Check $\rightarrow$ Code đúng $\rightarrow$ Check $\rightarrow$ Finish | `0.90` | 0.90 / 1.00 | 4.50 / 10.00 | **10%** (theo penalty regime) |
| **Chuỗi 3** | Run 5 lần $\rightarrow$ Code đúng $\rightarrow$ Check $\rightarrow$ Finish | `1.00` | 1.00 / 1.00 | 5.00 / 10.00 (Slot 1) | **0%** (Run không phạt điểm) |

---

### Tầng 3: Kiểm chứng Trình duyệt Thực tế (Playwright Browser E2E Automation)

Kịch bản kiểm thử E2E tự động (`verification_evidence/scripts/coderunner_browser_verification.spec.js`) được thực thi trên trình duyệt Chromium với tài khoản sinh viên `student1`. Kịch bản tích hợp gồm 9 bước chuỗi kiểm chứng thực tế đã chạy thành công (kết quả `1 passed` trong 33.0 giây, xem log tại `verification_evidence/logs/browser_execution_logs.txt`):

```
Running 1 test using 1 worker
[E2E] Bước 1: Đăng nhập tài khoản student1...
[E2E] Bước 2: Truy cập Quiz 76 (CM ID: 112)...
[E2E] Bước 3: Kiểm chứng Scratchpad UI trên Câu 1...
[E2E] Số trường Expected Output tìm thấy trên giao diện Scratchpad: 0
[E2E] Bước 4: Bấm nút Run trên Scratchpad... Kết quả Run thành công: 8
[E2E] Bước 5: Thử nghiệm lỗi Runtime (ZeroDivisionError) trên Scratchpad...
[E2E] Bước 5b: Kiểm chứng Tải lại trang (Page Reload) và Khôi phục mã nháp Scratchpad...
[E2E] Bước 6: Bấm Check nộp bài Câu 1...
[E2E] Bước 7: Chuyển sang Câu 2 (Program Testing)...
[E2E] Bước 8a: Nhập Expected SAI (T2/T4) để kiểm tra phát hiện lỗi từ lời giải tham chiếu tác giả...
[E2E] Bấm Check với Expected sai...
[E2E] Bước 8b: Sửa Expected thành ĐÚNG (T1: 8) và nộp lại...
[E2E] Bước 9: Hoàn thành bài thi và xem tổng kết điểm...
[E2E] Hoàn tất 100% kịch bản kiểm chứng trình duyệt mở rộng!
  ✓ 1 [chromium] › coderunner_browser_verification.spec.js:23:3 (31.9s)
  1 passed (33.0s)
```

#### Danh mục Ảnh chụp Màn hình Minh chứng Giao diện:
1. `01_scratchpad_initial_state.png`: Khung Scratchpad mặc định thu gọn, sinh viên chỉ thấy khung soạn code chính.
2. `02_scratchpad_run_success.png`: Nhập code nháp, bấm Run, giá trị `8` hiển thị tức thời trong thẻ `<pre>` mà không tải lại trang.
3. `03_scratchpad_runtime_error.png`: Phép chia 0 hiển thị lỗi `ZeroDivisionError: division by zero` dạng văn bản thô.
4. `03b_scratchpad_draft_after_reload.png`: Thực hiện reload trang trình duyệt; nội dung soạn thảo trong Scratchpad được bảo toàn nguyên vẹn.
5. `04_scratchpad_checked.png`: Sinh viên bấm Check câu hỏi Scratchpad; kết quả chấm chính thức hiển thị đạt điểm tối đa.
6. `05_program_testing_table_initial.png`: Giao diện Table UI của `python3_program_testing` hiển thị 2 cột `Test` và `Result`.
7. `06a_program_testing_table_wrong_expected.png`: Sinh viên điền ca kiểm thử T2/T4 với Expected sai (`5` thay vì `8`).
8. `07a_program_testing_wrong_expected_feedback.png`: Phản hồi khi nộp Expected sai: Hiển thị thông báo *"Oops: you marked the right answer wrong!"* và nhận 0 điểm.
9. `06b_program_testing_table_correct.png`: Sinh viên sửa Expected thành `8` hợp lệ.
10. `07b_program_testing_success_feedback.png`: Bảng phản hồi khi vượt qua các test và diệt thành công các mutant của tác giả.
11. `08_quiz_review_grades.png`: Trang Review tổng kết bài thi và điểm số ghi nhận.

---

## 4. KẾT QUẢ THỰC NGHIỆM 4 CA KIỂM CHỨNG (T1 — T4) TRÊN BÀI TOÁN TÌM MAX

Bài toán: Tìm giá trị lớn nhất trong danh sách số nguyên không rỗng `[3, 8, 5]`. Giá trị lớn nhất là `8`.
- Hàm chuẩn Sol A: `def find_max(lst): return max(lst)` $\rightarrow$ Mong đợi: `Yes`.
- Hàm lỗi Sol B (trả về phần tử cuối): `def find_max(lst): return lst[-1]` $\rightarrow$ Mong đợi: `No`.

| Ca | Dữ liệu sinh viên nhập | Hàm chạy trên Jobe | Kết quả thực tế quan sát được | Phân loại & Cơ chế phản hồi |
|---|---|---|---|---|
| **T1** | Test: `print(find_max([3, 8, 5]))`<br>Expected: `8` | **Sol A (Chuẩn)** | `got = 8`, `expected = 8` $\rightarrow$ Khớp $\rightarrow$ Sol A ra `Yes`. | **ĐẠT (Pass)**: Testcase hợp lệ với lời giải chuẩn. |
| **T2** | Test: `print(find_max([3, 8, 5]))`<br>Expected: `5` (Sai) | **Sol A (Chuẩn)** | `got = 8`, `expected = 5` $\rightarrow$ Không khớp $\rightarrow$ Sol A ra `No`. In ra stderr: *"Oops: you marked the right answer wrong!"*. | **TRƯỢT (0 điểm)**: Phát hiện sinh viên dự đoán sai kết quả chuẩn. Đánh rớt toàn bộ câu hỏi. |
| **T3** | Test: `print(find_max([3, 8, 5]))`<br>Expected: `8` | **Sol B (Lỗi)** | `got = 5`, `expected = 8` $\rightarrow$ Không khớp $\rightarrow$ Sol B ra `No`. | **ĐẠT (Pass)**: Testcase phát hiện và diệt thành công đột biến (Mutant killed). |
| **T4** | Test: `print(find_max([3, 8, 5]))`<br>Expected: `5` (Sai) | **Sol B (Lỗi)** | `got = 5`, `expected = 5` $\rightarrow$ Khớp ngẫu nhiên $\rightarrow$ Sol B ra `Yes` (không diệt được lỗi). | **TRƯỢT**: Không diệt được Sol B. Đồng thời ở Sol A, ca T2 kích hoạt làm **bài thi nhận 0 điểm**. |

> [!NOTE]
> **Nhận định về ca T4:** Mẫu `python3_program_testing` có khả năng ngăn chặn sự trùng khớp ngẫu nhiên giữa testcase sai và code sai nhờ việc **luôn chạy test trên Hàm chuẩn Sol A của tác giả**. Tuy nhiên, cơ chế này đánh giá qua Check theo cấu hình chấm điểm chính thức của câu hỏi, không hỗ trợ chẩn đoán sư phạm phân tách giữa lỗi testcase và lỗi mã nguồn để sinh viên điều chỉnh trong quá trình làm bài.

---

## 5. RỦI RO THIẾT KẾ & CHIẾN LƯỢC CHUẨN HÓA WHITESPACE TRONG CHỐNG TRÙNG TESTCASE

Trong kiến trúc chống trùng lặp testcase của MultiSolve, việc sử dụng "Chuẩn hóa whitespace + SHA-256" tiềm ẩn rủi ro ngữ nghĩa nếu chỉ dựa vào hàm băm: **Hàm băm không quyết định hai testcase có tương đương hay không; quy tắc chuẩn hóa mới quyết định**.

Ví dụ thực tế:
- Trong bài toán xử lý chuỗi: `"a b"` và `"ab"` là hai input khác biệt hoàn toàn về mặt ngữ nghĩa. Nếu chuẩn hóa loại bỏ toàn bộ khoảng trắng, hai testcase này sẽ bị gộp nhầm (false collision).
- Trong bài toán đọc dữ liệu theo dòng: Ký tự xuống dòng `\n` mang cấu trúc phân tách bản ghi. Việc thay thế chuỗi xuống dòng bằng khoảng trắng đơn có thể làm hỏng logic của bài toán.

### Giải pháp Kiến trúc Đề xuất và Triển khai:

```
[Sinh viên nhập Input]
          │
          ├───► 1. Lưu trữ vĩnh viễn RAW INPUT nguyên bản (Không biến đổi)
          │
          └───► 2. Bộ chuẩn hóa theo ngữ cảnh bài toán (Normalizer Strategy)
                      │
                      ├── Loại A (Numeric / Array): Chuẩn hóa khoảng trắng & dấu phân cách
                      ├── Loại B (String / Line): Giữ nguyên khoảng trắng trong chuỗi, chỉ strip biên
                      └── Loại C (Strict Format): Không chuẩn hóa (Identical Match)
                      │
                      ▼
               3. Phiên bản hóa quy tắc (normalizer_version = 1, 2...)
                      │
                      ▼
               4. Sinh Fingerprint: SHA-256(normalized_input)
```

1. **Bảo tồn dữ liệu gốc (Raw Input Preservation)**:
   Trường `input_raw` trong bảng `testcase_store` luôn giữ nguyên 100% định dạng sinh viên đã nhập, bao gồm mọi khoảng trắng, tab và dấu xuống dòng. Mọi thao tác thực thi trên Jobe sandbox đều sử dụng `input_raw`.

2. **Quy tắc chuẩn hóa theo phân loại bài toán (Problem-Type-Specific Normalization)**:
   Hệ thống xác định cấu hình chuẩn hóa dựa trên loại tham số của bài toán:
   - *Dạng mảng số / biểu thức số học*: Chuẩn hóa co cụm khoảng trắng (`\s+` $\rightarrow$ `' '`), loại bỏ khoảng trắng quanh dấu phẩy và ngoặc (`[ 3,  8 ]` $\rightarrow$ `[3, 8]`).
   - *Dạng chuỗi ký tự / văn bản*: Chỉ strip khoảng trắng thừa ở đầu/cuối chuỗi (`trim`), bảo toàn tuyệt đối khoảng trắng bên trong nội dung.
   - *Dạng phân dòng (Multi-line input)*: Chuẩn hóa ký tự ngắt dòng (`\r\n` $\rightarrow$ `\n`), bảo toàn số lượng dòng trống mang ý nghĩa dữ liệu.

3. **Phiên bản hóa bộ quy tắc (Normalizer Versioning)**:
   Mỗi bản ghi testcase lưu thêm cột `normalizer_version` (ví dụ: `v1.0`). Khi nâng cấp thuật toán chuẩn hóa, hệ thống có thể chạy migration tính toán lại hash fingerprint mà không làm thay đổi hay mất mát dữ liệu kiểm thử gốc.

4. **Kiểm thử phân biệt Trùng thật (True Duplicates) vs Bị gộp nhầm (False Collisions)**:
   - *Trùng thật*: `[3, 8, 5]` và `[3,  8,   5]` $\rightarrow$ Cùng đưa về `[3, 8, 5]` $\rightarrow$ Trùng fingerprint (Chấp nhận loại trừ).
   - *Khác biệt có chủ đích*: `"a b"` và `"ab"` $\rightarrow$ Giữ nguyên chuỗi $\rightarrow$ Sinh hai hash khác nhau (Không bị gộp nhầm).

---

## 6. PHÂN ĐỊNH GIỮA TÍNH HỢP LỆ CỦA TESTCASE VÀ ĐIỀU KIỆN ĐỦ ĐỂ ĐÓNG GÓP

Trong thiết kế sư phạm của MultiSolve, điều kiện đóng góp là **chính sách có chủ đích (intentional pedagogical policy)** nhằm khuyến khích sự phát triển toàn diện của người học, chứ không phải một sai sót logic trong kiểm thử vi sai. 

Hệ thống phân tách rạch ròi hai khái niệm:
- **Tính hợp lệ của testcase (Testcase Validity)**: Phản ánh tính đúng đắn khách quan của ca kiểm thử so với đặc tả bài toán, được xác minh duy nhất bằng việc đối chiếu `Expected Output` của sinh viên với kết quả từ Lời giải tham chiếu của giảng viên (`Oracle`).
- **Điều kiện đủ để đóng góp (Contribution Eligibility)**: Ràng buộc sư phạm đòi hỏi sinh viên phải vừa thiết kế được testcase hợp lệ, vừa hiện thực được chương trình vượt qua chính testcase đó.

### Ma trận Trạng thái Xác minh và Quyền Đóng góp:

| Expected so với Lời giải Tham chiếu | Code sinh viên trên Testcase | Kết luận Sư phạm | Quyền Đóng góp | Phản hồi Giao diện (User Feedback) |
|---|---|---|:---:|---|
| **Khớp** | **Khớp** | Testcase hợp lệ; Code đã vượt qua testcase | **ĐƯỢC ĐÓNG GÓP** | Hiện nút *"🚀 Đề xuất Đóng góp"*. Trạng thái: `Đủ điều kiện đóng góp`. |
| **Khớp** | **Không khớp hoặc lỗi** | Testcase hợp lệ; Code chưa vượt qua testcase | **CHƯA ĐƯỢC ĐÓNG GÓP**<br>*(Cần sửa code)* | *"Kết quả mong đợi của bạn đã được xác nhận. Chương trình hiện tại chưa vượt qua testcase này. Hãy sửa chương trình và chạy lại để đủ điều kiện đóng góp."* |
| **Không khớp** | Bất kỳ | Expected chưa đúng so với đặc tả tham chiếu | **CHƯA ĐƯỢC ĐÓNG GÓP** | *"Kết quả kỳ vọng không khớp với Lời giải tham chiếu."* Trạng thái: `Lệch Tham chiếu`. |
| **Chưa xác minh được** | Bất kỳ | Lỗi môi trường, timeout hoặc sandbox ngoại tuyến | **TẠM HOÃN QUYẾT ĐỊNH** | *"Hệ thống chưa thể xác minh với Lời giải tham chiếu. Vui lòng thử lại sau."* |

### Chuẩn hóa Lập luận Khoa học & Giới hạn Năng lực:
- **Điều chỉnh luận điểm**: Cần bác bỏ lập luận đơn giản hóa *"hiểu rõ testcase thì phải lập trình được"* vì năng lực phân tích/dự đoán hành vi bài toán (comprehension/analysis) và năng lực hiện thực hóa giải thuật (implementation/synthesis) là hai cấp độ nhận thức khác nhau.
- **Phát biểu Mục tiêu Hoạt động**:
  > *"Hoạt động yêu cầu sinh viên kết hợp xây dựng testcase với hiện thực lời giải. Quyền đóng góp chỉ được mở khi expected output được xác nhận bằng tham chiếu và chương trình sinh viên vượt qua chính testcase đó."*
- **Xác lập Giới hạn Nhận thức (Epistemic Boundary)**:
  > *"Việc sinh viên vượt qua một testcase chỉ chứng minh mã nguồn phù hợp trên trường hợp kiểm thử cụ thể đó, chưa chứng minh chương trình đúng toàn bộ hoặc sinh viên đã có đầy đủ năng lực lập trình."*

### Đánh đổi Kiến trúc và Giá trị của Dữ liệu Lưu vết (Audit Trail):
- **Đánh đổi (Trade-off)**: Những testcase có giá trị cao (phát hiện góc khuất hoặc ca biên làm lộ lỗi trong code của chính sinh viên) sẽ tạm thời chưa được chia sẻ vào kho chung cho đến khi sinh viên tự sửa xong mã nguồn của mình.
- **Giá trị Nghiên cứu & Lưu vết Hành trình Học tập**:
  Hệ thống vẫn **lưu trữ vĩnh viễn toàn bộ các lần chạy thử hợp lệ mà code chưa vượt qua** (`pending_code_fix` trong `testcase_runs`). Dữ liệu này ghi nhận quá trình siêu nhận thức (metacognition) vô cùng quý giá:
  $$\text{Dự đoán đúng (Test hợp lệ)} \longrightarrow \text{Phát hiện code sai (Test failure)} \longrightarrow \text{Sửa code (Refactor)} \longrightarrow \text{Chạy lại} \longrightarrow \text{Đủ điều kiện đóng góp}$$

### Cơ chế Bảo đảm Toàn vẹn qua Gắn kết Phiên bản (Version Pinning & Cryptographic Binding):
- Mỗi lượt đề xuất đóng góp được liên kết cứng qua khóa ngoại duy nhất `run_id`, gắn chặt với bộ ba cố định:
  $$\big(\text{code\_revision\_id},\ \text{input\_fingerprint},\ \text{oracle\_version\_hash}\big)$$
- Nếu sinh viên chỉnh sửa mã nguồn hoặc thay đổi testcase trên trình soạn thảo, trạng thái đó tạo ra một phiên bản mới chưa qua kiểm chứng. Hệ thống **từ chối tiếp nhận đóng góp nếu không có một bản ghi `testcase_runs` hợp lệ tương ứng**; sinh viên bắt buộc phải chạy lại (Run) để sandbox xác nhận lại đồng thời cả hai điều kiện trước khi mở quyền đóng góp.

---

## 7. MA TRẬN ĐỐI CHIẾU CHỨC NĂNG VỚI MULTISOLVE

Đánh giá được phân định minh bạch theo hai nhóm trạng thái: **[ĐÃ KIỂM CHỨNG]** (kèm mã nguồn và script thực nghiệm) hoặc **[THEO MÔ TẢ THIẾT KẾ]** (mô hình thiết kế kiến trúc).

| Tiêu chí đối chiếu | CodeRunner Scratchpad UI | CodeRunner `python3_program_testing` | MultiSolve (`local_testcase_exchange`) | Trạng thái Minh chứng MultiSolve |
|---|:---:|:---:|:---:|:---:|
| **1. Tự chạy thử độc lập (Interactive Playground)** | **CÓ** | **KHÔNG** | **CÓ** | **ĐÃ KIỂM CHỨNG**<br>(Kiểm chứng qua `e2e/local_testcase_exchange.spec.js` kịch bản 2) |
| **2. Sinh viên nhập Expected Output** | **KHÔNG** | **CÓ** | **CÓ** | **ĐÃ KIỂM CHỨNG**<br>(Trường `#predicted_output` trên Playground) |
| **3. Xác minh bằng Lời giải tham chiếu (Oracle)** | **KHÔNG** | **CÓ** | **CÓ** | **ĐÃ KIỂM CHỨNG**<br>(Chạy vi sai qua `execution_service.php` trên Jobe) |
| **4. Kênh phản hồi bảo mật Lời giải tham chiếu** | *Không áp dụng* | **MỘT PHẦN** | **CÓ** | **ĐÃ KIỂM CHỨNG**<br>(Không quan sát thấy mã/raw output tham chiếu trong các kênh phản hồi) |
| **5. Phản hồi chẩn đoán riêng (Test vs Code)** | **KHÔNG** | **MỘT PHẦN** | **CÓ (3 TRẠNG THÁI)** | **ĐÃ KIỂM CHỨNG**<br>(Phân tách: Cả 2 đúng / Test đúng Code sai / Test sai) |
| **6. Sinh viên đồng thời nộp Code và Testcase** | **KHÔNG** | **KHÔNG** | **CÓ** | **ĐÃ KIỂM CHỨNG**<br>(Sinh viên nộp code giải thuật kèm đề xuất testcase) |
| **7. Lưu trữ testcase vào kho dùng chung** | **KHÔNG** | **KHÔNG** | **CÓ** | **ĐÃ KIỂM CHỨNG**<br>(Bảng `testcase_store` trên MariaDB 3306) |
| **8. Chống trùng lặp testcase có bảo toàn ngữ nghĩa** | **KHÔNG** | **KHÔNG** | **CÓ** | **ĐÃ KIỂM CHỨNG**<br>(Lưu raw input + normalizer theo loại bài toán + SHA-256) |
| **9. Quy trình kiểm duyệt sư phạm (Review & Rating)** | **KHÔNG** | **KHÔNG** | **CÓ** | **ĐÃ KIỂM CHỨNG**<br>(Giao diện `review.php`, chấm sao và nhận xét) |
| **10. Trao đổi testcase & Đổi thưởng (Claim Reward)** | **KHÔNG** | **KHÔNG** | **CÓ** | **ĐÃ KIỂM CHỨNG**<br>(Mở khóa testcase hạt giống / bạn học theo tỷ lệ 1:1) |
| **11. Bảng vinh danh (Gamified Leaderboard)** | **KHÔNG** | **KHÔNG** | **CÓ** | **ĐÃ KIỂM CHỨNG**<br>(Bảng xếp hạng khóa học theo số sao kiểm duyệt) |
| **12. Tính độc lập với điểm số bài thi chính thức** | **CÓ** (khi Run) | **KHÔNG** | **CÓ** | **ĐÃ KIỂM CHỨNG**<br>(Không thay đổi các trường điểm trong phép thử replay qua `verify_reproducibility.php`) |

---

## 8. MA TRẬN: TUYÊN BỐ $\rightarrow$ CA KIỂM THỬ $\rightarrow$ BẰNG CHỨNG $\rightarrow$ GIỚI HẠN

Để đảm bảo chuẩn mực khoa học nghiêm ngặt cho bài báo, bảng dưới đây liên kết trực tiếp giữa các tuyên bố kiến trúc với bằng chứng thực nghiệm cụ thể và giới hạn kiểm chứng:

| Tuyên bố kiến trúc (Claim) | Ca kiểm thử (Test Case) | Bằng chứng thực nghiệm (Artifact Evidence) | Giới hạn đã quan sát (Limitations) |
|---|---|---|---|
| **C1. Scratchpad UI là công cụ hỗ trợ chạy nháp độc lập, không tác động đến điểm số** | Thao tác Run 5 lần liên tiếp trên Scratchpad UI bằng script và trình duyệt | `run_detailed_penalty_experiment.php` (Chuỗi 3), `marks_and_penalties_log.txt`, ảnh `02_scratchpad_run_success.png` | Run không tạo step trong CSDL, không trừ penalty. Tuy nhiên, mã nháp `test_code` bị loại bỏ khi nộp bài và không có cơ chế đối chiếu kết quả mong đợi với Lời giải tham chiếu. |
| **C2. Scratchpad UI hỗ trợ xử lý lỗi runtime tại chỗ** | Nhập biểu thức chia cho 0 (`10 / 0`) vào ô Scratchpad và bấm Run | Ảnh `03_scratchpad_runtime_error.png`, `browser_execution_logs.txt` (Bước 5) | Lỗi được hiển thị dưới dạng chuỗi văn bản thô (raw stderr từ Python/Jobe), không có gợi ý phân tích cú pháp hay hỗ trợ sư phạm chuyên biệt. |
| **C3. Scratchpad UI lưu trữ trạng thái nháp qua phiên làm việc** | Tải lại trang (Reload) khi đang mở khung soạn nháp | Ảnh `03b_scratchpad_draft_after_reload.png`, `coderunner_browser_verification.spec.js` (Bước 5b) | Bản nháp được khôi phục thành công từ JSON client-side, nhưng nếu sinh viên xóa bộ nhớ đệm trình duyệt trước khi nộp, dữ liệu chưa nộp có thể không khôi phục được. |
| **C4. Mẫu `python3_program_testing` đánh giá qua Lời giải tham chiếu nhưng yêu cầu sinh viên đóng vai trò Tester** | Thử nghiệm 4 ca T1 — T4 trên bài toán tìm Max | `questions/original_programtestingprototypeandexamples.xml`, ảnh `06a`, `07a`, `07b` | Sinh viên không nộp mã chương trình giải thuật; ca kiểm thử T2/T4 kích hoạt `"Oops: you marked the right answer wrong!"` và đánh rớt câu hỏi qua Check theo cấu hình câu hỏi. |
| **C5. Điểm số và mức phạt penalty của CodeRunner tuân thủ quy tắc Adaptive Mode** | Thực nghiệm Chuỗi 1 (chuẩn) và Chuỗi 2 (sai 1 lần rồi sửa đúng) | `marks_and_penalties_log.txt`, Chuỗi 1: Fraction `1.0` (100%), Chuỗi 2: Fraction `0.9` (phạt 10% theo penalty regime `10, 20, ...`) | Phép thử xác nhận chính xác công thức adaptive của Moodle. Giới hạn: Phép thử thực hiện trên cấu hình `allornothing = 1` của câu hỏi Q794. |
| **C6. MultiSolve bảo mật Lời giải tham chiếu trong quá trình kiểm thử vi sai** | Chạy vi sai kiểm thử trên Jobe với expected output sinh viên tự nhập | Script `execution_service.php`, test suite `local_testcase_exchange.spec.js` | Không quan sát thấy mã nguồn hoặc raw output của Lời giải tham chiếu trong các kênh phản hồi trả về cho sinh viên; chỉ trả về cờ boolean và chẩn đoán trạng thái. |
| **C7. MultiSolve không làm thay đổi điểm thi hay tiến trình của Moodle Core** | Chạy kiểm tra replay trạng thái trước và sau khi kích hoạt trao đổi testcase | Script `verify_reproducibility.php`, checksum so sánh các bảng điểm | Không làm thay đổi các trường điểm thi, attempt layout hay gradebook trong các phép thử replay đã kiểm tra. Cần tiếp tục theo dõi khi tải đồng thời cao. |

---

## 9. PHÂN LOẠI MỨC ĐỘ KHẢ THI (LEVEL A / B / C)

- **Mức A (Có sẵn — Out of the box)**: Không có công cụ nào trong hệ thống hiện tại đáp ứng trọn vẹn quy trình tương tác hai chiều của MultiSolve ngay khi vừa cài đặt.
- **Mức B (Khả thi qua Cấu hình & Import mẫu có sẵn)**:
  - *CodeRunner Scratchpad UI*: Kích hoạt bằng cách bật cấu hình `wsenabled = 1` và đổi `Input UIs` sang `scratchpad`. Hỗ trợ chạy thử nháp nhưng không có Oracle xác minh dự đoán và không đóng góp dữ liệu.
  - *CodeRunner `python3_program_testing`*: Kích hoạt bằng cách import tệp mẫu `programtestingprototypeandexamples.xml`. Hỗ trợ đối chiếu Lời giải tham chiếu nhưng thay đổi hoàn toàn mục tiêu học tập (chuyển sang nộp bộ test kiểm thử hàm có sẵn).
- **Mức C (Bắt buộc phải phát triển module mở rộng chuyên biệt — MultiSolve)**:
  - Cơ chế kiểm thử vi sai bảo mật Lời giải tham chiếu.
  - Phân tách phản hồi chẩn đoán 3 trạng thái giữa tính đúng đắn của testcase và lỗi trong code sinh viên.
  - Cơ sở dữ liệu kho testcase dùng chung có phân tầng (`course_id` $\rightarrow$ `quiz_id` $\rightarrow$ `question_id`).
  - Thuật toán chống trùng lặp kết hợp bảo tồn raw input và quy tắc chuẩn hóa theo loại bài toán.
  - Quy trình kiểm duyệt sư phạm (Review & Rating) và cơ chế đổi thưởng (Claim Reward) ngang hàng.

---

## 10. HÀM Ý CHO BÀI BÁO KHOA HỌC

1. **Thừa nhận khách quan các công cụ nền tảng trong *Related Work*:**
   Bài báo cần ghi nhận rõ ràng: CodeRunner đã cung cấp tiện ích **Scratchpad UI** cho phép chạy thử độc lập không trừ điểm và mẫu **`python3_program_testing`** để đánh giá chất lượng bộ kiểm thử qua mutation testing.
2. **Xác định chính xác khoảng trống nghiên cứu (Research Gap):**
   - *Scratchpad UI* thiếu hoàn toàn liên kết với đặc tả bài toán (không có Oracle đối chiếu kết quả dự đoán).
   - *`python3_program_testing`* thiết kế nhiệm vụ yêu cầu sinh viên nộp bộ kiểm thử thay vì lời giải chương trình, đồng thời quá trình kiểm tra bị ràng buộc vào lượt nộp bài chính thức.
   - Cả hai công cụ đều giới hạn người học trong phiên làm bài cá nhân; chưa xác minh được cơ chế tuyển chọn testcase của sinh viên vào kho dữ liệu dùng chung để tái sử dụng trong cộng đồng học tập.
3. **Phát biểu đóng góp kiến trúc của MultiSolve:**
   MultiSolve đề xuất một **Mô hình Sư phạm Tích hợp (Integrated Pedagogical Framework)**:
   - Hỗ trợ đồng thời hai hoạt động: **Lập trình giải thuật ($S_1$) và Thiết kế ca kiểm thử ($S_2$)**.
   - Cơ chế **Kiểm thử vi sai bảo mật** cho phép sinh viên kiểm tra dự đoán với Lời giải tham chiếu mà không làm lộ mã nguồn chuẩn và không ảnh hưởng đến điểm thi chính thức.
   - Cơ chế **Học tập cộng tác qua kho testcase dùng chung** chuyển đổi nỗ lực kiểm thử của cá nhân thành tài nguyên học tập có kiểm duyệt chất lượng sư phạm.
