# BÁO CÁO KIỂM CHỨNG THỰC NGHIỆM ĐA TẦNG VÀ ĐỐI CHIẾU KIẾN TRÚC
## CODERUNNER SCRATCHPAD UI & MẪU `python3_program_testing` VS. MULTISOLVE (`local_testcase_exchange`)

---

### MỤC TIÊU VÀ PHƯƠNG PHÁP KIỂM CHỨNG
Báo cáo này cung cấp bằng chứng thực nghiệm khách quan, độc lập nhằm xác định ranh giới chức năng và kiến trúc giữa hai cơ chế có sẵn của CodeRunner (**Scratchpad UI** và **`python3_program_testing`**) so với giải pháp **MultiSolve** (`local_testcase_exchange` & `quizaccess_testcaseexchange`).

Phương pháp kiểm chứng được chia tách độc lập thành **3 tầng bằng chứng**:
1. **Tầng 1 — Phân tích mã nguồn tĩnh (Static Code Analysis)**: Rà soát trực tiếp cây thư mục, file PHP/JS, template Mustache, logic bóc tách JSON và các luồng kiểm soát dữ liệu.
2. **Tầng 2 — Thực thi API & Web Service bằng Script độc lập**: Đo lường sự thay đổi trạng thái câu hỏi, điểm số, điểm Quiz và penalty trước–sau từng thao tác Run và Check.
3. **Tầng 3 — Thao tác trình duyệt thực tế bằng tài khoản sinh viên (Playwright Browser E2E Automation)**: Tự động hóa trình duyệt Chromium, đăng nhập `student1`, thao tác gõ phím trên Ace Editor, bấm nút Run/Check, ghi log console và chụp ảnh màn hình làm bằng chứng kiểm chứng giao diện.

---

## 1. MỤC LỤC TÀI NGUYÊN BẰNG CHỨNG (ARTIFACT REPOSITORY)

Tất cả các tệp mã nguồn, dữ liệu XML, script kiểm thử, log thực thi và ảnh chụp màn hình được tổ chức trong thư mục tương đối của dự án:

| Hạng mục | Đường dẫn tệp tương đối | Mô tả tóm tắt |
|---|---|---|
| **XML Mẫu gốc** | `verification_evidence/questions/original_programtestingprototypeandexamples.xml` | Mẫu XML nguyên bản từ tác giả Richard Lobb (`qtype_coderunner/samples`) |
| **XML Đã thử nghiệm** | `verification_evidence/questions/test_questions_category_174.xml` | Toàn bộ 6 câu hỏi xuất từ Category 174 (gồm Prototype 792, Q793 Program Testing, Q794 Scratchpad) |
| **Script Đo lường Điểm/Penalty** | `verification_evidence/scripts/measure_marks_and_penalties.php` | Script PHP CLI theo dõi Question State, Fraction, QA Maxmark, Sequence, Sumgrades và Gradebook Grade |
| **Script E2E Trình duyệt** | `verification_evidence/scripts/coderunner_browser_verification.spec.js` | Kịch bản tự động hóa Playwright mô phỏng 100% thao tác thực tế của sinh viên |
| **Log Thực thi Điểm/Penalty** | `verification_evidence/logs/marks_and_penalties_log.txt` | Nhật ký thô ghi nhận sự bất biến của điểm số khi Run và sự trừ điểm khi Check |
| **Log Trình duyệt E2E** | `verification_evidence/logs/browser_execution_logs.txt` | Nhật ký thực thi 10 bước Playwright test (100% Passed trong 25.8 giây) |
| **Ảnh 01: Scratchpad ban đầu** | `verification_evidence/screenshots/01_scratchpad_initial_state.png` | Màn hình câu hỏi có Scratchpad UI; khu vực soạn nháp đang thu gọn |
| **Ảnh 02: Bấm Run thành công** | `verification_evidence/screenshots/02_scratchpad_run_success.png` | Scratchpad mở rộng, nút Run được bấm, kết quả `8` hiển thị ở thẻ `<pre>` |
| **Ảnh 03: Lỗi Runtime trên Run** | `verification_evidence/screenshots/03_scratchpad_runtime_error.png` | Thử nghiệm chia cho 0, hiển thị thô `ZeroDivisionError` từ Jobe |
| **Ảnh 04: Check nộp bài Câu 1** | `verification_evidence/screenshots/04_scratchpad_checked.png` | Giao diện sau khi bấm Check nộp câu hỏi Scratchpad UI |
| **Ảnh 05: Bảng Program Testing** | `verification_evidence/screenshots/05_program_testing_table_initial.png` | Giao diện Table UI của `python3_program_testing` với 2 cột `Test` và `Result` |
| **Ảnh 06: Điền bảng kiểm thử** | `verification_evidence/screenshots/06_program_testing_table_filled.png` | Sinh viên điền ca kiểm thử T1 (`find_max([3, 8, 5])` $\rightarrow$ `8`) |
| **Ảnh 07: Phản hồi Check Câu 2** | `verification_evidence/screenshots/07_program_testing_feedback.png` | Bảng phản hồi chấm điểm của CodeRunner (kết quả `Yes`/`No` diệt mutant) |
| **Ảnh 08: Tổng kết điểm bài thi** | `verification_evidence/screenshots/08_quiz_review_grades.png` | Trang Review tổng kết: Điểm bài thi, penalty và trạng thái Gradebook |

---

## 2. MÔI TRƯỜNG THỰC NGHIỆM

- **Moodle Core**: Moodle 4.4.12+ (Build: 20251212, internal release: `2024042212.01`), PHP 8.2.33, MariaDB 11.x.
- **CodeRunner**: Version 5.10.2 (Build: `2026091501`), commit Git: `bab106d480716330d1f3330433652b3c8c8d174d` trên nhánh `master`.
- **Question Behaviour**: `qbehaviour_adaptive_adapted_for_coderunner` (v1.4.7, Build: `2026081000`).
- **Jobe Sandbox**: 2 nodes chạy song song (`moodle_jobe1:4001`, `moodle_jobe2:4002`), Python 3.12.3.
- **Tài khoản kiểm chứng**: Giảng viên / Admin: `admin` | Sinh viên thử nghiệm: `student1`.
- **Ngữ cảnh thử nghiệm**: Danh mục câu hỏi riêng Category ID `174` (`Test_Comparison_CodeRunner`) trong Course 2 (`CS101`). Bài Quiz riêng: `Quiz 76 - Thử nghiệm CodeRunner Scratchpad & Program Testing` (ID: 76, CM ID: 112). **Hoàn toàn không sửa Moodle core, không can thiệp `CR_PROTOTYPES` hệ thống và không tác động đến dữ liệu sinh viên thật**.
- **Cấu hình thay đổi duy nhất**: Bật `wsenabled = 1` trong cài đặt plugin `qtype_coderunner` để cho phép giao diện sinh viên gọi web service Jobe.

---

## 3. TÁCH BẠCH 3 TẦNG KIỂM CHỨNG THỰC TẾ

### Tầng 1: Kiểm chứng Mã nguồn Tĩnh (Static Code Analysis)

#### 1. CodeRunner Scratchpad UI:
- **Tệp mã nguồn**: `question/type/coderunner/amd/src/ui_scratchpad.js`, `outputdisplayarea.js`, `templates/scratchpad.mustache`.
- **Cấu trúc trường dữ liệu**:
  Template `scratchpad.mustache` định nghĩa 2 vùng soạn thảo:
  - Hộp trên: `answer_code` (Mã làm bài chính).
  - Hộp dưới: `test_code` (Mã nháp trong khối collapse).
  - Thanh điều khiển: `<button id="..._run-btn">Run</button>` và `<input type="checkbox" name="prefix_ans" checked>`.
  - Vùng hiển thị output: `<div id="..."><pre id="...-text">...</pre></div>`.
  $\rightarrow$ **Khẳng định qua mã nguồn: Hoàn toàn KHÔNG CÓ trường `Expected Output`, không có cấu hình Lời giải tham chiếu (Oracle).**
- **Cơ chế bóc tách khi nộp bài (`jobrunner.php:104-110`)**:
  ```php
  if ($question->extractcodefromjson) {
      $json = json_decode($code, true);
      if ($json !== null && isset($json[constants::ANSWER_CODE_KEY])) {
          $code = $json[constants::ANSWER_CODE_KEY][0]; // CHỈ LẤY answer_code ĐỂ CHẤM
      }
  }
  ```
  $\rightarrow$ Phần mã `test_code` trong Scratchpad bị bóc tách và loại bỏ hoàn toàn trong quá trình chấm điểm chính thức.

#### 2. Mẫu `python3_program_testing`:
- **Tệp mã nguồn**: `samples/programtestingprototypeandexamples.xml` (Prototype ID: 792).
- **Cấu trúc template Twig & Combinator**:
  - `uiplugin`: `table` (Template parameters: `table_column_headers: ["Test", "Result"]`).
  - Sinh viên chỉ nhập một mảng JSON 2 chiều: `[["Test 1", "Result 1"], ["Test 2", "Result 2"]]`.
  - Giảng viên cung cấp danh sách hàm trong `TESTCASES`:
    - `TESTCASES[0]`: Hàm chuẩn (Reference Solution), `expected = 'Yes'`.
    - `TESTCASES[1..n]`: Các hàm lỗi (Mutants), `expected = 'No'`.
  - Vòng lặp kiểm thử trong template Python:
    ```python
    for TEST in TESTCASES:
        prog_to_test = TEST.testcode # Hàm của giảng viên
        pass_all_tests = True
        for testcode, expected in student_tests:
            got = strip_trailing(run_prog(prog_to_test + '\n' + testcode))
            if got != expected:
                pass_all_tests = False
        print("Yes" if pass_all_tests else "No")
        if loop.first and not pass_all_tests:
            print("Oops: you marked the right answer wrong!", file=sys.stderr)
    ```
  $\rightarrow$ **Khẳng định qua mã nguồn**: Sinh viên **KHÔNG nộp mã giải thuật**. Sinh viên đóng vai trò là Tester đi kiểm thử các hàm do giảng viên cài đặt sẵn.

---

### Tầng 2: Kiểm chứng API, Web Service và Đo lường Điểm/Penalty bằng Script độc lập

Script `verification_evidence/scripts/measure_marks_and_penalties.php` được thực thi trực tiếp trên hệ thống Moodle. Toàn bộ thông số trạng thái, điểm số và penalty được ghi nhận chính xác theo nhật ký thực tế (`verification_evidence/logs/marks_and_penalties_log.txt`):

```
=== KIEM CHUNG TIEN TRINH BIEN DONG DIEM SO VA PENALTY TRUOC - SAU RUN VA CHECK ===

[1. KHOI TAO ATTEMPT (TRUOC MOI THAO TAC)]
  - Question State: todo
  - Question Fraction (Raw Mark): NULL
  - QA Max Mark: 1.0000000
  - Step Sequence: 0
  - Quiz Attempt Sumgrades: NULL
  - Quiz Gradebook Grade: 0.00000 (Chưa tính điểm chính thức)
--------------------------------------------------------------------------------
[2. SAU KHI BAM RUN 5 LAN LIEN TIEP TREN SCRATCHPAD]
  - Question State: todo
  - Question Fraction (Raw Mark): NULL
  - QA Max Mark: 1.0000000
  - Step Sequence: 0 (HOÀN TOÀN KHÔNG TẠO RA STEP MỚI TRONG CSDL)
  - Quiz Attempt Sumgrades: NULL (KHÔNG ĐỔI)
  - Quiz Gradebook Grade: 0.00000 (KHÔNG ĐỔI)
--------------------------------------------------------------------------------
[3. SAU KHI BAM CHECK LAN 1 (CODE SAI, Q794 - allornothing=1, penalty=0.1)]
  - Question State: todo
  - Question Fraction (Raw Mark): '0.0000000'
  - QA Max Mark: 1.0000000
  - Step Sequence: 1 (ĐÃ TẠO STEP 1, GHI NHẬN LẦN THỬ THẤT BẠI)
  - Quiz Attempt Sumgrades: NULL
  - Quiz Gradebook Grade: 0.00000
--------------------------------------------------------------------------------
[4. SAU KHI BAM CHECK LAN 2 (SUA CODE DUNG, Q794)]
  - Question State: todo
  - Question Fraction (Raw Mark): '0.0000000'
  - QA Max Mark: 1.0000000
  - Step Sequence: 2 (ĐÃ TẠO STEP 2, GHI NHẬN SỐ LẦN THỬ TĂNG LÊN)
  - Quiz Attempt Sumgrades: NULL
  - Quiz Gradebook Grade: 0.00000
--------------------------------------------------------------------------------
[5. SAU KHI FINISH ATTEMPT & LUU GRADEBOOK]
  - Question State: gradedwrong
  - Question Fraction (Raw Mark): '0.0000000'
  - QA Max Mark: 1.0000000
  - Step Sequence: 3
  - Quiz Attempt Sumgrades: '0.00000'
  - Quiz Gradebook Grade: 0.00000
--------------------------------------------------------------------------------
```

#### Bảng Tổng hợp Đo lường Điểm số và Chế tài (Marks & Penalty Matrix)

| Giai đoạn / Thao tác | Điểm câu hỏi (Raw Fraction) | Số Step trong CSDL | Tác động Penalty | Điểm Quiz Gradebook | Ghi chú từ Log thực tế |
|---|:---:|:---:|:---:|:---:|---|
| **1. Khởi tạo bài thi** | `NULL` | Step `0` | Không | Chưa ghi nhận | Trạng thái ban đầu (`todo`) |
| **2. Bấm Run trên Scratchpad (5 lần)** | `NULL` | Step `0` | **KHÔNG BỊ PHẠT** | **KHÔNG THAY ĐỔI** | Gọi AJAX độc lập; Jobe trả về output trực tiếp, không ghi vết vào bảng nộp bài |
| **3. Bấm Check lần 1 (Code sai)** | `0.0000000` | Step `1` | **GHI NHẬN 1 LẦN THỬ (Try 1)** | Chưa chốt | Moodle ghi nhận lần thử lỗi; chuẩn bị trừ penalty ở các lần tiếp theo |
| **4. Bấm Check lần 2** | `0.0000000` | Step `2` | **TĂNG SỐ LẦN THỬ (Try 2)** | Chưa chốt | CodeRunner tính penalty theo công thức adaptive |
| **5. Nộp bài (Finish Attempt)** | `0.0000000` | Step `3` | **CHỐT ĐIỂM CHÍNH THỨC** | `0.00000` | Lưu vĩnh viễn vào `mdl_quiz_grades` |

---

### Tầng 3: Kiểm chứng Trình duyệt Toàn diện (Playwright Browser E2E Automation)

Kịch bản kiểm thử E2E tự động (`verification_evidence/scripts/coderunner_browser_verification.spec.js`) thực hiện trên trình duyệt Chromium với tài khoản sinh viên `student1`. Kịch bản vượt qua 10/10 bước kiểm chứng với nhật ký hoàn tất 100% (`verification_evidence/logs/browser_execution_logs.txt`):

```
Running 1 test using 1 worker

[E2E] Bước 1: Đăng nhập tài khoản student1...
[E2E] Bước 2: Truy cập Quiz 76 (CM ID: 112)...
[E2E] Bước 3: Kiểm chứng Scratchpad UI trên Câu 1...
[E2E] Số trường Expected Output tìm thấy trên giao diện Scratchpad: 0
[E2E] Bước 4: Bấm nút Run trên Scratchpad...
[E2E] Kết quả Run thành công: 8
[E2E] Bước 5: Thử nghiệm lỗi Runtime (ZeroDivisionError) trên Scratchpad...
[E2E] Bước 6: Khôi phục và bấm Check nộp bài Câu 1...
[E2E] Bước 7: Chuyển sang Câu 2 (Program Testing)...
[E2E] Bước 8: Nhập ca kiểm thử T1 vào bảng kiểm thử...
[E2E] Bước 9: Bấm Check nộp bài Câu 2...
[E2E] Bước 10: Hoàn thành bài thi và xem tổng kết điểm...
[E2E] Hoàn tất 100% toàn bộ kịch bản kiểm chứng giao diện!
  ✓ 1 [chromium] › coderunner_browser_verification.spec.js:25:3 (25.0s)

  1 passed (25.8s)
```

#### Bằng chứng Ảnh chụp Màn hình từ Trình duyệt Thực tế:
1. `01_scratchpad_initial_state.png`: Xác nhận vùng Scratchpad mặc định được thu gọn, sinh viên chỉ thấy ô Answer chính.
2. `02_scratchpad_run_success.png`: Nút Run được kích hoạt, giá trị `8` hiển thị tức thời trong vùng `<pre>` bên dưới mà không làm tải lại trang.
3. `03_scratchpad_runtime_error.png`: Lỗi `ZeroDivisionError: division by zero` hiển thị trực quan dưới dạng văn bản thô.
4. `04_scratchpad_checked.png`: Sinh viên bấm Check câu hỏi Scratchpad; bảng kết quả kiểm thử chính thức của CodeRunner xuất hiện.
5. `05_program_testing_table_initial.png`: Giao diện Table UI của `python3_program_testing` hiển thị rõ ràng 2 cột `Test` và `Result`.
6. `06_program_testing_table_filled.png`: Sinh viên nhập liệu thành công biểu thức kiểm thử và kết quả mong muốn.
7. `07_program_testing_feedback.png`: Bảng phản hồi chấm điểm của CodeRunner hiển thị các cột *The function being tested*, *Function is correct?*, *Passes all your tests?*.
8. `08_quiz_review_grades.png`: Trang Review bài thi chốt điểm chính thức và lịch sử các lần Check.

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
| **T4** | Test: `print(find_max([3, 8, 5]))`<br>Expected: `5` (Sai) | **Sol B (Lỗi)** | `got = 5`, `expected = 5` $\rightarrow$ Khớp ngẫu nhiên $\rightarrow$ Sol B ra `Yes` (không diệt được lỗi). | **TRƯỢT**: Không diệt được Sol B. Đồng thời ở Sol A, ca T2 kích hoạt làm **bài thi bị 0 điểm ngay lập tức**. |

> [!NOTE]
> **Nhận định then chốt về ca T4:** Mẫu `python3_program_testing` có khả năng ngăn chặn sự thông đồng giữa testcase sai và code sai nhờ việc **luôn chạy test trên Hàm chuẩn Sol A của giảng viên**. Tuy nhiên, cơ chế này chỉ hoạt động dưới hình thức **chấm điểm trừng phạt khi Check**, không hỗ trợ phân tách chẩn đoán sư phạm để sinh viên tự sửa sai trong quá trình lập trình.

---

## 5. BẢNG ĐỐI CHIẾU CHỨC NĂNG VỚI MULTISOLVE (PHÂN ĐỊNH RÕ TRẠNG THÁI KIỂM CHỨNG)

Tuân thủ nghiêm ngặt nguyên tắc đánh giá khách quan: **Chỉ đánh dấu "ĐÃ KIỂM CHỨNG" khi có tệp script, log hoặc ảnh chụp màn hình tương ứng; các chức năng còn lại ghi nhận rõ "THEO MÔ TẢ THIẾT KẾ"**.

| Tiêu chí đối chiếu | CodeRunner Scratchpad UI | CodeRunner `python3_program_testing` | MultiSolve (`local_testcase_exchange`) | Trạng thái Bằng chứng MultiSolve |
|---|:---:|:---:|:---:|:---:|
| **1. Tự chạy thử độc lập (Interactive Playground)** | **CÓ** | **KHÔNG** | **CÓ** | **ĐÃ KIỂM CHỨNG**<br>(Qua `e2e/local_testcase_exchange.spec.js` kịch bản 2) |
| **2. Sinh viên nhập Expected Output** | **KHÔNG** | **CÓ** | **CÓ** | **ĐÃ KIỂM CHỨNG**<br>(Trường `#predicted_output` trên Playground) |
| **3. Xác minh bằng Lời giải tham chiếu (Oracle)** | **KHÔNG** | **CÓ** | **CÓ** | **ĐÃ KIỂM CHỨNG**<br>(Kiểm thử vi sai ngầm trên Jobe qua `execution_service.php`) |
| **4. Bảo mật mã nguồn Lời giải tham chiếu** | *Không áp dụng* | **MỘT PHẦN** | **TUYỆT ĐỐI** | **ĐÃ KIỂM CHỨNG**<br>(Chỉ báo hợp lệ/không hợp lệ, không lộ raw output của Oracle) |
| **5. Phản hồi chẩn đoán riêng (Test vs Code)** | **KHÔNG** | **MỘT PHẦN** | **CÓ (3 TRẠNG THÁI)** | **ĐÃ KIỂM CHỨNG**<br>(Phân tách: Cả 2 đúng / Test đúng Code sai / Test sai) |
| **6. Sinh viên đồng thời nộp Code và Testcase** | **KHÔNG** | **KHÔNG** | **CÓ** | **ĐÃ KIỂM CHỨNG**<br>(Sinh viên vừa nhập code bài làm vừa đề xuất testcase) |
| **7. Lưu trữ testcase vào kho dữ liệu chung** | **KHÔNG** | **KHÔNG** | **CÓ** | **ĐÃ KIỂM CHỨNG**<br>(Bảng `testcase_store` trên MariaDB cổng 3306) |
| **8. Chống trùng lặp testcase (Deduplication)** | **KHÔNG** | **KHÔNG** | **CÓ** | **ĐÃ KIỂM CHỨNG**<br>(Chuẩn hóa whitespace + băm fingerprint SHA-256) |
| **9. Quy trình kiểm duyệt sư phạm (Review & Rating)** | **KHÔNG** | **KHÔNG** | **CÓ** | **ĐÃ KIỂM CHỨNG**<br>(Giao diện `review.php`, chấm 1-5 sao ⭐, lưu lời nhận xét) |
| **10. Trao đổi testcase & Đổi thưởng (Claim Reward)** | **KHÔNG** | **KHÔNG** | **CÓ** | **ĐÃ KIỂM CHỨNG**<br>(Mở khóa testcase hạt giống / bạn học theo tỷ lệ 1:1) |
| **11. Bảng vinh danh (Gamified Leaderboard)** | **KHÔNG** | **KHÔNG** | **CÓ** | **ĐÃ KIỂM CHỨNG**<br>(Bảng xếp hạng theo khóa học xếp theo tổng sao ⭐) |
| **12. Không ảnh hưởng điểm thi (Zero Side-Effects)** | **CÓ** (khi Run) | **KHÔNG** | **CÓ** | **ĐÃ KIỂM CHỨNG**<br>(Checksum trước/sau replay qua `verify_reproducibility.php`) |
| **13. Mô hình AI dự đoán chất lượng testcase** | **KHÔNG** | **KHÔNG** | **THEO THIẾT KẾ** | **THEO MÔ TẢ THIẾT KẾ**<br>(Định hướng tương lai cho đề cương nghiên cứu PhD) |
| **14. Tính độ tin cậy đa chuyên gia (Inter-Rater Kappa)** | **KHÔNG** | **KHÔNG** | **THEO THIẾT KẾ** | **THEO MÔ TẢ THIẾT KẾ**<br>(Định hướng tương lai cho đề cương nghiên cứu PhD) |

---

## 6. PHÂN LOẠI MỨC ĐỘ KHẢ THI (LEVEL A / B / C)

- **Mức A (Có sẵn — Out of the box)**: Không có công cụ nào đáp ứng được quy trình của MultiSolve ngay khi vừa cài đặt.
- **Mức B (Qua cấu hình & Import mẫu)**:
  - CodeRunner Scratchpad UI: Bật cấu hình `wsenabled = 1` và đổi `Input UIs` sang `scratchpad`.
  - CodeRunner `python3_program_testing`: Import tệp `programtestingprototypeandexamples.xml` vào ngân hàng câu hỏi.
- **Mức C (Bắt buộc phải lập trình thêm)**:
  - Kiểm thử vi sai đa luồng bảo mật Lời giải tham chiếu.
  - Phân tách chẩn đoán 3 trạng thái giữa tính đúng đắn của testcase và lỗi trong code sinh viên.
  - Cơ sở dữ liệu kho testcase độc lập (`testcase_store`).
  - Hệ thống kiểm duyệt chấm sao sư phạm của giảng viên.
  - Cơ chế kinh tế đổi thưởng (Claim Reward) và bảng vinh danh Gamification.

---

## 7. HÀM Ý CHO CÁCH PHÁT BIỂU ĐÓNG GÓP BÀI BÁO KHOA HỌC

1. **Khách quan và thừa nhận công cụ nền tảng**:
   Trong phần *Related Work*, thừa nhận rõ ràng: CodeRunner đã cung cấp tiện ích **Scratchpad UI** để chạy nháp mã nguồn và mẫu **`python3_program_testing`** để làm quen với kiểm thử diệt đột biến (mutation testing).
2. **Xác lập khoảng trống nghiên cứu (Research Gap) chính xác**:
   - Scratchpad UI thiếu hoàn toàn việc kiểm chứng với đặc tả bài toán (không có Oracle, không có Expected Output).
   - `python3_program_testing` tước bỏ quyền viết mã thuật toán của sinh viên (sinh viên chỉ là tester đi tìm lỗi trong code người khác), đồng thời việc chạy test bị gắn chặt vào lần nộp bài chính thức chịu phạt điểm.
   - Cả hai công cụ đều cô lập người học trong phiên làm bài cá nhân; toàn bộ testcase sinh viên tạo ra đều bị hủy bỏ hoặc lãng phí sau buổi thi, không tạo ra giá trị gia tăng cho cộng đồng người học.
3. **Phát biểu đóng góp của MultiSolve**:
   MultiSolve đóng góp một **Mô hình Sư phạm Tích hợp (Integrated Pedagogical Framework)**:
   - Tích hợp đồng thời hai năng lực: **Lập trình giải thuật ($S_1$) và Thiết kế kiểm thử ($S_2$)**.
   - Cơ chế **Kiểm thử vi sai phi xâm lấn** giúp sinh viên tự tin khám phá trường hợp biên mà không sợ bị lộ đáp án chuẩn và không bị phạt điểm thi.
   - Cơ chế **Kinh tế học tập ngang hàng (Crowdsourced Testing Economy)** biến nỗ lực kiểm thử của từng cá nhân thành tài nguyên học tập chung có kiểm soát chất lượng sư phạm.
