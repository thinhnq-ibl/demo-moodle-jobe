# BÁO CÁO KIỂM CHỨNG THỰC NGHIỆM CHÍNH SÁCH VÀ MÃ NGUỒN MULTISOLVE
## KIỂM CHỨNG CÁC ĐIỀU KIỆN ĐÓNG GÓP, CHỐNG TRÙNG VÀ CƠ CHẾ CẤP PHẦN THƯỞNG

---

### THÔNG TIN MÔI TRƯỜNG VÀ PHIÊN BẢN HỆ THỐNG
- **Plugin MultiSolve**: `local_testcase_exchange` (phiên bản `2026101800`, commit: `d9c5317369daeb41805b8b8757a176fe8930f521`).
- **Subplugin Access Rule**: `quizaccess_testcaseexchange` (phiên bản `2026101800`).
- **Moodle Core**: Moodle 4.4.12+ (Build: 2024042212.01), PHP 8.2.33.
- **Cơ sở dữ liệu kho testcase**: MariaDB 11.x, schema `testcase_store` (người dùng `moodle_app_writer`, cổng nội bộ 3306).
- **Cụm Jobe Sandbox**: 2 container song song (`moodle_jobe1:4001`, `moodle_jobe2:4002`), Python 3.12.3.
- **Ngữ cảnh kiểm thử**: Khóa học `CS101` (ID: 2), `Quiz 76` (CM ID: 112), Câu hỏi `Find Max` (Q794, CodeRunner `python3`).
- **Tài khoản thử nghiệm**: Giảng viên / Quản trị: `admin` (ID: 2) | Sinh viên 1: `student1` (ID: 3) | Sinh viên 2: `student2` (ID: 4).
- **Tệp script thực nghiệm**: `verification_evidence/scripts/test_multisolve_full_verification.php`.
- **Nhật ký thực thi thô**: `verification_evidence/logs/multisolve_policy_test_log.txt`.

---

## BẢNG MA TRẬN TỔNG THỂ KIỂM CHỨNG CHÍNH SÁCH

| STT | Quy tắc mong muốn (Policy Rule) | Tệp mã nguồn thực hiện | Ca thử nghiệm thực tế | Kết quả thực tế quan sát được | Kết luận |
|:---:|---|---|---|---|:---:|
| **1.1** | Đóng góp khi Expected đúng VÀ Code đúng VÀ Testcase mới | `testcase_service.php:317-365`<br>`index.php:483-489` | Ca 1.1: `find_max([10, 50, 30])` $\rightarrow$ `50`, Code: `max(lst)` | `prediction_matches_oracle = true`, `student_outcome = success`. Đóng góp thành công với trạng thái `submitted`. | **ĐẠT** |
| **1.2** | Chặn đóng góp khi Expected đúng nhưng Code sinh viên sai | `testcase_service.php:337-341`<br>`index.php:489, 503-509` | Ca 1.2: `find_max([7, 9, 2])`, Expected: `9`, Code: `return lst[-1]` (ra `2`) | `can_contribute = false`. Gọi API bị chặn bởi `studentcodenotpassing`. Báo thông điệp hướng dẫn sửa code. | **ĐẠT** |
| **1.3** | Chặn đóng góp khi Expected lệch Lời giải tham chiếu | `testcase_service.php:332-336`<br>`index.php:501-503` | Ca 1.3: `find_max([1, 4, 2])`, Expected cố ý ghi `1` (Oracle ra `4`) | `prediction_matches_oracle = false`. Trạng thái: `Lệch Tham chiếu`. Ném lỗi `predictionmismatchoracle`. | **ĐẠT** |
| **1.4** | Xử lý khi Lời giải tham chiếu hoặc Jobe lỗi | `testcase_service.php:329-331`<br>`index.php:308-311` | Ca 1.4: Input gây lỗi cú pháp/biên dịch trên Oracle | `oracle_outcome = compile_error`. Báo lỗi `runcreatedoraclefailed`, không kết luận testcase sai mà từ chối đóng góp. | **ĐẠT** |
| **1.5** | Sửa code/input sau khi chạy đạt không làm sai lệch đóng góp | `index.php:310-313`<br>`testcase_service.php:318` | Ca 1.5: Đóng góp ghim chặt với `run_id` cố định | Bản ghi đóng góp lưu đúng snapshot của lượt chạy đạt (`run_id`). Form soạn thảo mới tạo `run_id` riêng. | **ĐẠT** |
| **2.1** | Phát hiện trùng nội dung giống hệt 100% | `normalizer.php:35-51`<br>`schema_manager.php:148` | Ca 2.1: Hai input giống hệt ký tự | Sinh cùng `input_fingerprint`. Bị chặn trùng bởi khóa `uq_canonical`. | **ĐẠT** |
| **2.2** | Phát hiện trùng khác khoảng trắng hai đầu | `normalizer.php:35-39` (`trim` + `rtrim`) | Ca 2.2: Thêm dấu cách, tab, xuống dòng ở đầu và cuối | Hàm `normalize()` đưa về chuỗi chuẩn giống hệt input cơ sở $\rightarrow$ Trùng hash SHA-256. | **ĐẠT** |
| **2.3** | Hành vi với khác biệt khoảng trắng bên trong | `normalizer.php:35-41` | Ca 2.3: `[12, 18, 15]` vs `[12,   18,     15]` | Giữ nguyên khoảng trắng bên trong $\rightarrow$ Hai hash khác nhau $\rightarrow$ Hệ thống coi là 2 testcase khác nhau. | **ĐẠT**<br>*(Đúng thiết kế hiện tại)* |
| **3.1** | Thưởng chỉ chọn trong tập testcase đủ điều kiện | `testcase_service.php:1428-1443` | Kiểm tra 1: Sinh viên nhận thưởng từ kho peer | Chọn trúng testcase hợp lệ của bạn học (`student2`) đã được duyệt (`approved`). | **ĐẠT** |
| **3.2** | Loại trừ testcase đã thưởng trước đó | `testcase_service.php:1434-1437` | Kiểm tra 1 & 2: Đối chiếu với `testcase_rewards` | Testcase đã nhận trước đó không bị cấp lại trong các lần thưởng tiếp theo. | **ĐẠT** |
| **3.3** | Loại trừ testcase do chính sinh viên đóng góp | `testcase_service.php:1432, 1438` | Kiểm tra 1: Đối chiếu với `testcase_contributions own` | Không cấp lại bài của chính `student1` (`user_id <> owner` và khớp fingerprint). | **ĐẠT** |
| **3.4** | Loại trừ testcase sinh viên đã thử trên Playground | `testcase_service.php:1428-1444` | Kiểm tra đặc biệt: Testcase có trong `testcase_runs` | **Chưa loại trừ**: Truy vấn SQL chỉ kiểm tra `testcase_contributions own`, không kiểm tra `testcase_runs`. | **CHƯA ĐẠT**<br>*(Sai lệch mã nguồn)* |
| **3.5** | Phân phối ngẫu nhiên đồng đều (Pure Random) | `testcase_service.php:1443` | Kiểm tra 6: Phân tích mệnh đề `ORDER BY` | **Chưa đồng đều**: Ưu tiên theo rating cao nhất trước (`rating DESC`), chỉ `RAND()` khi cùng rating. | **CHƯA ĐẠT**<br>*(Sai lệch mã nguồn)* |
| **4.1** | Cấp tuần tự không lặp lại ứng viên | `testcase_service.php:1433-1437` | Kiểm tra 2: Cấp 2 lần liên tiếp | Lần 1 cấp Ứng viên B; Lần 2 cấp Ứng viên A. Không trùng lặp. | **ĐẠT** |
| **4.2** | Bảo đảm an toàn khi nhận đồng thời (Concurrency) | `schema_manager.php:175-177`<br>`testcase_service.php:1296-1306` | Kiểm tra 3: Thử nghiệm Race condition trên MariaDB | Khóa UNIQUE `(receiver_user_id, contribution_id)` ngăn trùng tuyệt đối. `claim_reward` idempotent an toàn. | **ĐẠT** |
| **4.3** | Xử lý khi cạn kiệt toàn bộ kho ứng viên | `testcase_service.php:1484-1486` | Kiểm tra 4: Cấp khi hết cả Peer lẫn Seed | Hệ thống return an toàn, không tạo bản ghi rác, không trừ số lần quyền lợi. | **ĐẠT** |
| **4.4** | Xử lý khi chỉ còn đúng 1 ứng viên | `testcase_service.php:1455-1482` | Kiểm tra 5: Kho chỉ còn 1 Seed duy nhất | Hệ thống cấp chính xác ứng viên duy nhất đó. | **ĐẠT** |
| **5.1** | Giảng viên có thể rating và comment đóng góp | `testcase_service.php:447-505`<br>`review.php:60-95` | Phần 5: Chấm 5 sao ⭐ và ghi chú nhận xét | Cập nhật `rating`, `review_comment` và ghi vết kiểm toán trong `contribution_reviews`. | **ĐẠT** |

---

## 1. CHI TIẾT KIỂM CHỨNG ĐIỀU KIỆN ĐÓNG GÓP TESTCASE

Hệ thống đã triển khai đầy đủ và kiểm chứng chính xác 5 trường hợp theo chính sách:

### 1.1. Trường hợp Đạt: Expected đúng VÀ Code đúng VÀ Testcase mới
- **Mã thực nghiệm**: `print(find_max([10, 50, 30]))`, Expected: `50`.
- **Thực thi trên Jobe**:
  - Oracle solution chạy ra: `50` $\rightarrow$ Khớp với Expected (`prediction_matches_oracle = true`).
  - Student code chạy ra: `50` $\rightarrow$ Khớp với Oracle (`student_outcome = success`).
- **Kết quả**: Giao diện hiển thị nút *"🚀 Đề xuất Đóng góp"*. Hàm `submit_contribution()` thực thi thành công, tạo bản ghi đóng góp với trạng thái `submitted` và chiếm giữ khóa canonical duy nhất.

### 1.2. Trường hợp Expected đúng nhưng Code sinh viên sai
- **Mã thực nghiệm**: `print(find_max([7, 9, 2]))`, Expected: `9`.
- **Mã sinh viên sai**: `return lst[-1]` (trả về phần tử cuối là `2`).
- **Thực thi trên Jobe**:
  - Oracle solution chạy ra: `9` $\rightarrow$ Expected của sinh viên hoàn toàn chính xác.
  - Student code chạy ra: `2` $\rightarrow$ Code sinh viên bị lỗi logic trên testcase này.
- **Hành vi hệ thống**:
  - `can_contribute = false` (Chặn hoàn toàn việc tạo đóng góp).
  - Trạng thái hiển thị trong bảng lịch sử: Nhãn `Hợp lệ — Cần sửa code`.
  - Phản hồi giao diện chuẩn xác:
    > **"Kết quả mong đợi của bạn đã được xác nhận. Chương trình hiện tại chưa vượt qua testcase này. Hãy sửa chương trình và chạy lại để đủ điều kiện đóng góp."**
  - Nếu gọi API trực tiếp, hàm `submit_contribution()` ném ngoại lệ `studentcodenotpassing`.

### 1.3. Trường hợp Expected sai so với Lời giải tham chiếu
- **Mã thực nghiệm**: `print(find_max([1, 4, 2]))`, Expected nhập sai là `1` (Oracle chuẩn là `4`).
- **Hành vi hệ thống**: `prediction_matches_oracle = false`. Trạng thái hiển thị là `Lệch Tham chiếu`. Ném ngoại lệ `predictionmismatchoracle` nếu cố tình gửi đóng góp.

### 1.4. Trường hợp Tham chiếu (Oracle) hoặc Jobe gặp lỗi
- **Mã thực nghiệm**: Nhập input lỗi cú pháp hoặc gây lỗi runtime trên nghiệm chuẩn.
- **Hành vi hệ thống**: `oracle_outcome` trả về mã lỗi (`compile_error` hoặc `runtime_error`). Hệ thống hiển thị thông báo `runcreatedoraclefailed`:
  > *"Đã lưu lần thử riêng. Ca kiểm thử gây lỗi khi chạy với nghiệm chuẩn (Oracle): compile_error. Không thể đề xuất chia sẻ testcase này."*
  Hệ thống **không kết luận testcase sai**, mà chỉ khẳng định chưa đủ điều kiện để xác minh độ tin cậy của testcase.

### 1.5. Cơ chế Version Pinning khi sửa Code/Input/Expected
- Thao tác đóng góp trong giao diện được gắn chặt với tham số ẩn:
  `<input type="hidden" name="run_id" value="{{id}}">`
- Khi sinh viên bấm đóng góp, hệ thống nạp dữ liệu từ đúng bản ghi `testcase_runs` tương ứng với `run_id` đó (chứa `input_raw`, `input_normalized`, `oracle_output`, `student_source_hash`, `oracle_version_hash` và `code_revision_id`).
- Nếu sinh viên sửa code hoặc input trên form nhưng chưa bấm Run, form chưa tạo run mới. Nếu sinh viên bấm Run, hệ thống sinh ra một `run_id` hoàn toàn mới; không thể dùng kết quả kiểm thử của run cũ để bảo chứng cho dữ liệu mới.

---

## 2. CHI TIẾT KIỂM CHỨNG CHỐNG TRÙNG Ở PHẠM VI CƠ BẢN

Dựa trên mã nguồn tại `local_testcase_exchange/classes/normalizer.php`, `schema_manager.php` và `testcase_service.php`:

### 2.1. Giải đáp 5 câu hỏi kiến trúc cốt lõi:
1. **So sánh input hay cả input–expected?**
   - Hệ thống **chỉ so sánh `input`**. Fingerprint được tính bằng:
     $$\text{input\_fingerprint} = \text{SHA-256}(\text{normalized\_input})$$
     Expected output không tham gia vào hàm băm hay khóa chống trùng `uq_canonical`.
2. **`trim()` được áp dụng ở đâu? Có thêm bước chuẩn hóa nào không?**
   - Được áp dụng trong hàm `normalizer::normalize($input, 'trim')`:
     - Chuyển đổi mọi ký tự xuống dòng `\r\n` và `\r` thành `\n`.
     - Áp dụng `trim()` ở hai đầu chuỗi toàn thể.
     - Tách từng dòng và áp dụng `rtrim()` loại bỏ khoảng trắng ở cuối mỗi dòng.
     - Nối lại thành chuỗi qua ký tự `\n`.
     - **Không có bước chuẩn hóa ngữ nghĩa phức tạp nào khác** trong chế độ mặc định (`trim`).
3. **Phạm vi chống trùng là theo câu hỏi, Quiz, khóa học hay toàn kho?**
   - Khóa duy nhất trong MariaDB (`testcase_canonical_keys`):
     `UNIQUE KEY uq_canonical (course_id, quiz_id, question_id, input_fingerprint)`
   - Phạm vi chống trùng là **theo Câu hỏi trong từng Quiz của từng Khóa học** (`course_id` $\rightarrow$ `quiz_id` $\rightarrow$ `question_id`). Không chống trùng chéo giữa hai Quiz khác nhau dù cùng dùng một câu hỏi.
4. **Code hash có tham gia khóa chống trùng không?**
   - **HOÀN TOÀN KHÔNG**. `student_source_hash` chỉ được lưu trong `testcase_runs` để kiểm toán phiên bản (audit trail), không nằm trong khóa `uq_canonical`. Do đó, nếu sinh viên sửa code nhưng nộp lại cùng một testcase (cùng fingerprint), hệ thống vẫn phát hiện trùng lặp và từ chối.
5. **Dữ liệu gốc để thực thi có được bảo toàn không?**
   - **CÓ**. Cột `input_raw LONGTEXT` trong `testcase_runs` và `question_seed_testcases` lưu trữ 100% nguyên vẹn chuỗi sinh viên nhập. Sandbox Jobe luôn nhận tham số `$input` gốc để thực thi.

### 2.2. Kết quả thực nghiệm 3 ca thử khoảng trắng:
- **Ca 2.1 (Giống hệt 100%)**:
  - Hash 1 = Hash 2 = `3f46a1fe1980c758c6df7543a65119096cc43819257ea06cc6e4a7b0382b4661`.
  - $\rightarrow$ **Trùng lặp tuyệt đối (Phát hiện trùng thành công)**.
- **Ca 2.2 (Khác khoảng trắng hai đầu & xuống dòng)**:
  - Input: `"   \n\t  print(find_max([12, 18, 15]))   \n  "`.
  - Sau `normalize('trim')`: `"print(find_max([12, 18, 15]))"`.
  - Hash sinh ra: `3f46a1fe1980c758...` (Khớp 100% với Ca 2.1).
  - $\rightarrow$ **Phát hiện trùng thành công sau trim**.
- **Ca 2.3 (Khác khoảng trắng bên trong danh sách/chuỗi)**:
  - Input: `"print(find_max([12,   18,     15]))"`.
  - Sau `normalize('trim')`: `"print(find_max([12,   18,     15]))"` (Khoảng trắng bên trong không đổi).
  - Hash sinh ra: `0f9ceeb6ce94ee294ace4725302a2767b038a6a006c5dafe3f16f009bc812a78` ($\ne$ Hash cơ sở).
  - $\rightarrow$ **Hệ thống coi là 2 testcase KHÁC NHAU**. Xác nhận đúng thực tế: hệ thống hiện thời chỉ áp dụng `trim()` biên, chưa nhận diện tương đương cấu trúc bên trong.

---

## 3. CHI TIẾT KIỂM CHỨNG CƠ CHẾ CHỌN TESTCASE THƯỞNG

### 3.1. Phân tích mã nguồn câu truy vấn lựa chọn ứng viên (`testcase_service.php:1428-1444`):
```sql
SELECT c.id, c.input_fingerprint, 'contribution' AS source
  FROM testcase_contributions c
 WHERE c.course_id = ? AND c.quiz_id = ? AND c.question_id = ?
   AND c.status = 'approved' AND c.user_id <> ?
   AND NOT EXISTS (
       SELECT 1 FROM testcase_rewards r
        WHERE r.receiver_user_id = ? AND r.course_id = ? AND r.question_id = ?
          AND r.contribution_id = c.id
   )
   AND NOT EXISTS (
       SELECT 1 FROM testcase_contributions own
        WHERE own.user_id = ? AND own.course_id = ? AND own.question_id = ?
          AND own.input_fingerprint = c.input_fingerprint
   )
 ORDER BY COALESCE(c.rating, 0) DESC, RAND() LIMIT 1
```

### 3.2. Đánh giá tính nhất quán và các bộ lọc hiện có:
1. **Bộ lọc trạng thái kiểm duyệt**: Bắt buộc `c.status = 'approved'`. Các testcase ở trạng thái `submitted`, `rejected`, `needs_explanation` bị loại trừ 100%.
2. **Bộ lọc phiên bản câu hỏi**: Hệ thống lọc theo `question_id` (định danh câu hỏi trong ngân hàng đề Moodle). Chưa có bộ lọc theo `question_version` riêng; các đóng góp ở phiên bản trước của cùng một câu hỏi vẫn thuộc kho ứng viên.
3. **Quy tắc so sánh loại trừ**:
   - Loại trừ testcase đã thưởng: Dùng so sánh ID (`r.contribution_id = c.id`).
   - Loại trừ bài tự đóng góp: Dùng so sánh Fingerprint (`own.input_fingerprint = c.input_fingerprint`).
   - $\rightarrow$ **Tính nhất quán**: Có sự không đồng nhất giữa so sánh theo ID và so sánh theo Fingerprint. Nếu một testcase được thưởng dưới dạng `seed` (`reward_source = 'seed'`), nó lưu `seed_id` chứ không có `contribution_id`. Nếu sau đó một bạn học đóng góp lại đúng testcase đó thành contribution, bộ lọc `r.contribution_id = c.id` sẽ không bắt được.

### 3.3. Kiểm tra lịch sử "đã thử":
- **Testcase do chính sinh viên đóng góp**: **ĐÃ ĐƯỢC LOẠI TRỪ** nhờ mệnh đề `c.user_id <> ?` và `NOT EXISTS (SELECT 1 FROM testcase_contributions own ...)`.
- **Testcase sinh viên đã thử trên Playground (trong `testcase_runs`)**: **CHƯA ĐƯỢC LOẠI TRỪ**. Câu truy vấn hoàn toàn không kiểm tra bảng `testcase_runs`. Nếu sinh viên từng nhập testcase trên playground nhưng code chạy sai, hệ thống vẫn có thể cấp chính testcase đó làm phần thưởng nếu có bạn học khác đóng góp.

---

## 4. KẾT QUẢ THỰC NGHIỆM 6 CA THỬ BẮT BUỘC & SAI LỆCH PHÁT HIỆN

Thiết lập môi trường thử nghiệm sạch với bộ 6 thực thể kiểm soát:
1. `seed_1` (ID: 16): Seed testcase mẫu trong `question_seed_testcases`.
2. `tested_failed_1` (Run ID: 68): Testcase `student1` đã chạy thử nhưng code chạy sai.
3. `own_contributed_1` (Contrib ID: 47): Testcase do chính `student1` đóng góp đã duyệt.
4. `already_rewarded_1` (Contrib ID: 48): Testcase đã cấp thưởng cho `student1` trước đó.
5. `new_candidate_A` (Contrib ID: 49): Ứng viên mới của `student2`, rating 4 sao ⭐.
6. `new_candidate_B` (Contrib ID: 50): Ứng viên mới của `student2`, rating 5 sao ⭐.

### Kết quả chạy 6 ca kiểm tra:

1. **Kiểm tra 1 (Khảo sát tập ứng viên được chọn)**:
   - Hệ thống cấp lần 1: Đã chọn Contribution ID: `50` (Ứng viên mới B).
   - Xác nhận: Thuộc tập 2 ứng viên mới. Hoàn toàn không cấp trùng `seed_1`, không cấp trùng bài tự đóng góp `47`, không cấp trùng bài đã thưởng cũ `48`.
2. **Kiểm tra 2 (Lần nhận tiếp theo không cấp lại)**:
   - Sau khi `student1` nhận thưởng Contribution `50`, tiếp tục cấp lần 2.
   - Kết quả: Hệ thống cấp Contribution ID: `49` (Ứng viên mới A). Không cấp lặp lại bài `50`. $\rightarrow$ **ĐẠT**.
3. **Kiểm tra 3 (Hai lượt nhận đồng thời / Concurrency)**:
   - Khóa duy nhất `uq_reward (receiver_user_id, contribution_id)` trong MariaDB đảm bảo không bao giờ có 2 bản ghi thưởng trùng lặp cho một sinh viên.
   - Thao tác `claim_reward` có tính idempotent: nếu 2 request đồng thời gọi cùng một reward ID, cả hai đều trả về `true` mà không cấp trùng testcase mới hay trừ thêm quyền lợi. $\rightarrow$ **ĐẠT**.
4. **Kiểm tra 4 (Khi không còn ứng viên nào)**:
   - Khi cả 2 peer contribution và Seed đều đã được cấp hết: Giảng viên duyệt bài đóng góp tiếp theo cho sinh viên.
   - Kết quả: Tổng số reward trước và sau khi duyệt giữ nguyên (6 = 6). Hệ thống không tạo bản ghi rỗng, không trừ số lần quyền lợi của sinh viên. $\rightarrow$ **ĐẠT**.
5. **Kiểm tra 5 (Trường hợp chỉ còn 1 ứng viên)**:
   - Khi peer contribution đã hết, chỉ còn đúng 1 Seed `seed_1` trong kho: Hệ thống tự động fallback và cấp chính xác Seed đó (`reward_source = 'seed'`). $\rightarrow$ **ĐẠT**.
6. **Kiểm tra 6 (Rà soát thuật toán phân phối Random)**:
   - **PHÁT HIỆN SAI LỆCH THỰC TẾ**: Mệnh đề SQL thực tế là:
     `ORDER BY COALESCE(c.rating, 0) DESC, RAND() LIMIT 1`
   - **Hành vi thực tế**: Hệ thống ưu tiên chọn các testcase có **số sao rating cao nhất trước** (`rating DESC`), chỉ bốc ngẫu nhiên (`RAND()`) giữa các testcase có cùng rating. Minh chứng: Ứng viên B (5 sao) luôn luôn được cấp trước Ứng viên A (4 sao).

### Tổng hợp Hai Sai lệch Phát hiện & Đề xuất Sửa Tối thiểu:
> [!IMPORTANT]
> **Tuân thủ chỉ đạo của chủ dự án: Ghi nhận sai lệch và đề xuất phương án sửa tối thiểu, CHƯA TỰ Ý SỬA CODE.**

1. **Sai lệch 1: Chưa loại trừ lịch sử đã thử trong `testcase_runs`**:
   - *Hiện trạng*: Chưa có điều kiện loại trừ các testcase mà sinh viên đã chạy trên playground.
   - *Đề xuất sửa tối thiểu*: Bổ sung vào mệnh đề `WHERE` của truy vấn chọn ứng viên:
     ```sql
     AND NOT EXISTS (
         SELECT 1 FROM testcase_runs tr
          WHERE tr.user_id = ? AND tr.course_id = ? AND tr.question_id = ?
            AND tr.input_fingerprint = c.input_fingerprint
     )
     ```
2. **Sai lệch 2: Thứ tự chọn chưa phải là Random ngẫu nhiên đồng đều**:
   - *Hiện trạng*: Đang ưu tiên `rating DESC` trước `RAND()`.
   - *Đề xuất sửa tối thiểu*: Nếu chính sách yêu cầu chọn ngẫu nhiên đồng đều trong tập đủ điều kiện (không phân biệt số sao), chỉ cần sửa mệnh đề sắp xếp thành:
     ```sql
     ORDER BY RAND() LIMIT 1
     ```

---

## 5. RATING VÀ PHẠM VI NGHIÊN CỨU

- **Kiểm chứng tính năng Rating và Comment**:
  - Đã thực nghiệm hàm `testcase_service::review()` từ giao diện giảng viên:
    - Chấm điểm rating: `5` sao ⭐.
    - Ghi nhận nhận xét: *"Nhận xét: Bộ test kiểm tra tốt trường hợp số lớn."*
  - Dữ liệu đã lưu trữ thành công vào bảng `testcase_contributions` (`rating = 5`, `review_comment`), đồng thời ghi vết kiểm toán vào bảng `contribution_reviews`.
- **Phạm vi nghiên cứu**:
  - Xác nhận rõ ràng: **Không đưa AI, không đưa kiểm tra tính duy nhất của code, và không đưa đánh giá năng lực sinh viên vào phiên bản hiện tại**. Các tính năng tự động phân nhóm testcase, nhận định mức độ mới và gợi ý cho giảng viên là định hướng phát triển ở các giai đoạn sau.

---

## 6. DANH MỤC TÀI LIỆU VÀ BẰNG CHỨNG ĐÓNG GÓI

Toàn bộ script thực nghiệm, nhật ký chạy thực tế và dữ liệu XML được đóng gói trong tệp:
[`verification_evidence/multisolve_verification_evidence.zip`](file:///Users/nguyenquocthinh/Documents/demo-moodle-jobe/verification_evidence/multisolve_verification_evidence.zip)

- **Script kiểm thử chính**: [`verification_evidence/scripts/test_multisolve_full_verification.php`](file:///Users/nguyenquocthinh/Documents/demo-moodle-jobe/verification_evidence/scripts/test_multisolve_full_verification.php)
- **Nhật ký chạy CLI đầy đủ**: [`verification_evidence/logs/multisolve_policy_test_log.txt`](file:///Users/nguyenquocthinh/Documents/demo-moodle-jobe/verification_evidence/logs/multisolve_policy_test_log.txt)
- **Mã băm SHA-256 xác thực (từng tệp bằng chứng)**:
  - `multisolve_verification_evidence.zip`: `d49da3130608226f77bfcc81f963ab3ea5a9ec03b005dd1ca4ee1eec409d751c`
  - `verification_evidence/scripts/test_multisolve_full_verification.php`: `67360fb09b736e54eff76fc183aea41c221dbb2b49bf23d1e235db55411bdca4`
  - `verification_evidence/logs/multisolve_policy_test_log.txt`: `b04b4ab32713b096aef88003a5f4c82a14c3f27e1e4f25ab46b7fcf4441b4c95`
