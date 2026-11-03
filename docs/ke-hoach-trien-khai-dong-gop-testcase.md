# Kế hoạch triển khai tính năng đóng góp testcase

**Trạng thái:** MVP đã hiện thực; đang chờ E2E/pilot để nghiệm thu sản xuất  
**Phiên bản:** 1.0  
**Ngày cập nhật:** 07/10/2026  
**Phạm vi:** `local_testcase_exchange`, Moodle CodeRunner, Jobe và `testcase_store`

## 0. Trạng thái hiện thực ngày 07/10/2026

Đã hoàn thành trong code và môi trường Docker hiện tại:

- Một implementation chính tại `local_testcase_exchange`; route legacy không được deploy/include.
- Admin settings cho DB/Jobe và trang health check không lộ credential.
- Schema ngoài Moodle có version, migration legacy idempotent và giữ nguyên các bảng cũ.
- Đã migrate 11 run, 11 contribution, 11 canonical key và 4 reward; chạy migration lại không tăng số bản ghi.
- Selector chỉ lấy câu hỏi CodeRunner thuộc Quiz/course; request được kiểm tra lại ở server.
- Capability riêng cho `view`, `run`, `contribute`, `review` và `manage`.
- Private run, dự đoán, reflection, đề xuất chia sẻ, exact duplicate, review/audit và reward one-for-one.
- Policy theo Quiz/question cho review, reward, hiển thị oracle, rate/input limit, input mode và normalization.
- Docker tách `db_net` và `jobe_net`; Jobe không còn driver DB và không tham gia mạng MariaDB.
- PHP lint, Compose validation, normalizer smoke test và Java CodeRunner/Jobe smoke test đã đạt.

Chưa được xem là hoàn tất để phát hành production:

- Bộ PHPUnit/integration/E2E tự động, đặc biệt test race 10 request, capability theo từng role và outage/failover.
- Xác nhận đa ngôn ngữ bằng fixture Python, Java và C trong một course pilot.
- Dashboard thống kê/lọc nâng cao, leaderboard ẩn danh và correlation ID xuyên suốt request.
- Liên kết private run với quiz attempt/question attempt và lấy source trực tiếp từ latest attempt.
- Pilot thật với sinh viên/giảng viên, kiểm thử restore/rollback và biên bản nghiệm thu.

Vì vậy phần code hiện tại là MVP chạy được để pilot, không phải tuyên bố toàn bộ WP0–WP9 đã đạt Definition of Done.

## 1. Mục tiêu của tài liệu

Tài liệu này chuyển các định hướng trong [Đề xuất module đóng góp câu hỏi và testcase](./de-xuat-module-dong-gop-cau-hoi-testcase.md) thành các gói công việc có thể triển khai, kiểm thử và nghiệm thu.

MVP trong tài liệu này chỉ bao gồm testcase cho câu hỏi CodeRunner. Các nội dung sau chưa thuộc MVP:

- Đóng góp câu hỏi theo bài học hoặc video.
- Tìm nội dung gần trùng bằng mô hình ngữ nghĩa.
- Huy hiệu và tính điểm chất lượng vào điểm môn học.
- Tự động đưa testcase sinh viên vào bộ chấm chính thức.
- Hỗ trợ activity khác ngoài Quiz và CodeRunner.

## 2. Kết quả cuối cùng cần đạt

Luồng sử dụng sau khi hoàn thành MVP:

```text
Chọn Quiz và câu hỏi CodeRunner
                ↓
Nhập input + dự đoán + mục đích kiểm thử
                ↓
Chạy code sinh viên và oracle trên Jobe
                ↓
Lưu lần thử vào nhật ký riêng, kể cả khi dự đoán sai
                ↓
Sinh viên chọn “Đề xuất chia sẻ”
                ↓
Chuẩn hóa + kiểm tra trùng + chuyển trạng thái chờ duyệt
                ↓
Giảng viên duyệt hoặc từ chối
                ↓
Testcase được đưa vào ngân hàng tham khảo và có thể dùng để trao thưởng
```

Testcase được duyệt không tự động thay đổi `question_coderunner_tests`. Nếu sau này cần đưa testcase vào bộ chấm, phải có một workflow phát hành phiên bản bộ test riêng.

## 3. Hiện trạng cần xử lý trước

Hiện có nhiều implementation không đồng nhất:

- `local_testcase_exchange/index.php` đang được deploy nhưng dùng schema khác DB thực tế.
- `testcase_dashboard.php` dùng schema cũ, chỉ phục vụ bài `isPrime` và không được deploy.
- `quizaccess_testcaseexchange` có cấu hình bật/tắt theo Quiz nhưng không được cài trong container.
- DB hiện tại dùng `test_output` và `testcase_exchanges`; local plugin lại dùng `expected_output`, `student_received_testcases`, `question_seed_testcases` và `question_solutions`.
- Prototype CodeRunner đang chứa credential và ghi DB trực tiếp từ Jobe.

Quyết định kiến trúc bắt buộc:

1. `local_testcase_exchange` là implementation chính duy nhất.
2. Moodle PHP là thành phần duy nhất được ghi vào `testcase_store`.
3. Jobe chỉ nhận source code/input, chạy sandbox và trả kết quả.
4. Không sao chép lời giải giáo viên sang DB ngoài; lấy từ Moodle khi chạy oracle.
5. Dùng ID Moodle ổn định: `course_id`, `quiz_id`, `question_id`, `user_id`.
6. Không dùng `course.shortname` hoặc `username` làm khóa quan hệ.

## 4. Kiến trúc đích

```text
Moodle page/form
      │
      ├── context validator ── Moodle DB
      │                         quiz, question, attempt, teacher answer
      │
      ├── execution service ── Jobe cluster
      │                         student output + oracle output
      │
      └── repository ───────── testcase_store
                                runs, contributions, reviews, rewards
```

Các lớp dịch vụ dự kiến:

- `external_db`: tạo kết nối và quản lý transaction.
- `context_service`: xác minh course/quiz/question/user/attempt.
- `normalizer`: chuẩn hóa input theo chính sách từng câu hỏi.
- `jobe_client`: chạy code, timeout, failover và phân loại lỗi.
- `run_service`: thực hiện và lưu lần thử riêng tư.
- `contribution_service`: đề xuất, chống trùng và chuyển trạng thái.
- `review_service`: duyệt, từ chối và ghi audit log.
- `reward_service`: cấp testcase đã duyệt theo chính sách Quiz.

Không đặt toàn bộ nghiệp vụ trong `index.php`. Trang PHP chỉ nhận request, gọi service và render output.

## 5. Mô hình dữ liệu MVP

### 5.1. `schema_migrations`

Theo dõi phiên bản schema ngoài Moodle:

```text
version         varchar, primary key
applied_at      datetime
checksum        varchar
```

### 5.2. `quiz_settings`

```text
quiz_id                 bigint, primary key
enabled                 boolean
review_mode             enum(auto, teacher)
reward_policy           enum(disabled, one_for_one)
show_oracle_output      boolean
leaderboard_enabled     boolean
max_runs_per_minute     integer
max_input_bytes         integer
updated_by              bigint
updated_at              datetime
```

### 5.3. `testcase_runs`

Mỗi lần thử cá nhân là một bản ghi, không đặt unique theo input:

```text
id                      bigint, primary key
course_id               bigint
quiz_id                 bigint
question_id             bigint
user_id                 bigint
quiz_attempt_id         bigint, nullable
question_attempt_id     bigint, nullable
student_source_hash     char(64), nullable
input_raw               longtext
input_normalized        longtext
input_fingerprint       char(64)
predicted_output        longtext, nullable
student_run_output      longtext, nullable
oracle_output           longtext, nullable
student_outcome         varchar
oracle_outcome          varchar
purpose                 text, nullable
category                varchar, nullable
reflection              text, nullable
jobe_server             varchar
created_at              datetime
```

### 5.4. `testcase_contributions`

```text
id                      bigint, primary key
run_id                  bigint
course_id               bigint
quiz_id                 bigint
question_id             bigint
user_id                 bigint
input_normalized        longtext
input_fingerprint       char(64)
oracle_output           longtext
purpose                 text
category                varchar
status                  enum(submitted, needs_explanation, approved,
                             duplicate, rejected, archived)
duplicate_of            bigint, nullable
submitted_at            datetime
updated_at              datetime
```

Không đặt unique trực tiếp lên bảng này vì hệ thống cần giữ lịch sử các đề xuất bị xác định là trùng.

### 5.5. `testcase_canonical_keys`

Bảng này giải quyết race condition nhưng vẫn cho phép giữ contribution trùng:

```text
id                      bigint, primary key
course_id               bigint
quiz_id                 bigint
question_id             bigint
input_fingerprint       char(64)
canonical_contribution_id bigint
created_at              datetime
```

Ràng buộc:

```text
UNIQUE(course_id, quiz_id, question_id, input_fingerprint)
```

Request giành được canonical key là contribution chuẩn. Request thua unique race vẫn được lưu trong `testcase_contributions` với trạng thái `duplicate` và `duplicate_of` trỏ tới canonical contribution.

### 5.6. `contribution_reviews`

```text
id                      bigint, primary key
contribution_id         bigint
reviewer_user_id        bigint
from_status             varchar
to_status               varchar
review_note             text
created_at              datetime
```

### 5.7. `testcase_rewards`

```text
id                      bigint, primary key
course_id               bigint
quiz_id                 bigint
question_id             bigint
receiver_user_id        bigint
contribution_id         bigint
created_at              datetime
```

Ràng buộc tối thiểu:

```text
UNIQUE(receiver_user_id, contribution_id)
```

## 6. Kế hoạch triển khai theo gói công việc

## WP0 — Đóng băng và bảo toàn dữ liệu hiện tại

### Công việc

1. Sao lưu Moodle DB và `testcase_store` trước migration.
2. Ghi lại số bản ghi của `student_testcases`, `testcase_exchanges` và `quiz_settings`.
3. Không xóa bảng cũ trong lần phát hành đầu tiên; đổi tên hoặc giữ ở chế độ chỉ đọc.
4. Tạo script rollback cho mỗi migration.
5. Chặn code legacy tự thêm testcase vào `question_coderunner_tests`.

### Tiêu chí hoàn thành

- `mysqldump` của cả hai DB phục hồi được trên một DB tạm.
- Tổng số bản ghi trước và sau migration được ghi vào biên bản triển khai.
- Số bản ghi nguồn bằng số bản ghi đã migrate cộng số bản ghi bị loại có lý do.
- Số lượng `question_coderunner_tests` và quiz attempt không thay đổi trong migration.
- Có lệnh rollback đã được thử trên môi trường test.

## WP1 — Hợp nhất plugin và deployment

### Công việc

1. Chuyển mọi route sang `/local/testcase_exchange/...`.
2. Loại bỏ dependency vào `/testcase_dashboard.php`.
3. Chuyển cấu hình bật/tắt Quiz vào local plugin hoặc đóng gói access-rule đúng chuẩn.
4. Xóa credential hard-code khỏi PHP, Python template và quiz access rule.
5. Cập nhật Docker để chỉ deploy implementation chính.
6. Thêm trang kiểm tra trạng thái DB/Jobe dành cho admin.

### Tiêu chí hoàn thành

- Container chỉ có một dashboard đóng góp testcase được sử dụng.
- Không còn link nội bộ nào trỏ tới `/testcase_dashboard.php`.
- `rg -n "JobeSecret|ReaderSecret|rootpassword" local_testcase_exchange` không tìm thấy secret thực.
- Restart container không làm mất cấu hình plugin.
- Admin thấy trạng thái DB, Jobe node và language availability mà không thấy password/API key.
- `docker compose config --quiet` thành công.

## WP2 — Schema, migration và repository

### Công việc

1. Tạo migration versioned cho các bảng ở mục 5.
2. Chuyển dữ liệu cũ:
   - `student_testcases` hợp lệ → contribution `approved` hoặc dữ liệu legacy chờ rà soát.
   - `testcase_exchanges` → `testcase_rewards`.
   - `quiz_settings.is_enabled` → `quiz_settings.enabled`.
3. Tạo repository sử dụng prepared statement và transaction.
4. Chuẩn hóa charset/collation `utf8mb4`.
5. Bắt và phân loại duplicate key, connection error và constraint error.

### Tiêu chí hoàn thành

- Migration chạy hai lần không tạo dữ liệu trùng và không lỗi.
- Schema version sau migrate đúng phiên bản release.
- Không còn truy vấn tới bảng/cột không tồn tại.
- Tất cả truy vấn có dữ liệu người dùng đều dùng prepared statement.
- Khi DB lỗi, UI hiển thị lỗi an toàn và không lộ host, username hoặc password.
- Một transaction bị lỗi giữa chừng không để lại contribution mà thiếu review/reward liên quan.

## WP3 — Xác minh ngữ cảnh và chính sách Quiz

### Công việc

1. Chỉ liệt kê câu hỏi `qtype = coderunner`, đúng version đang dùng trong slot.
2. Mỗi request phải chứa và xác minh `course_id`, `quiz_id`, `question_id`.
3. Xác minh Quiz thuộc course và question thuộc Quiz.
4. Dùng `user_id` từ session, không nhận user ID từ form.
5. Áp capability riêng cho chạy thử, đóng góp và duyệt.
6. Áp `quiz_settings.enabled`, deadline và chính sách hiển thị oracle.

### Tiêu chí hoàn thành

- Sửa `question_id` trong request sang câu ngoài Quiz bị từ chối.
- Sửa `quiz_id` sang Quiz ở course khác bị từ chối.
- Sinh viên không gọi được action duyệt.
- Teacher không có quyền editing không thay đổi được policy.
- Quiz bị tắt không cho chạy hoặc đóng góp theo chính sách đã định.
- Câu hỏi không phải CodeRunner không xuất hiện trong selector.

## WP4 — Dịch vụ chạy Jobe tổng quát

### Công việc

1. Dùng language, template và teacher answer của chính câu hỏi CodeRunner.
2. Không hard-code Python, Java, `isPrime` hoặc tên hàm.
3. Chạy riêng code sinh viên và oracle.
4. Phân biệt `success`, compile error, runtime error, timeout, memory limit và Jobe unavailable.
5. Giới hạn CPU, memory, kích thước input và thời gian kết nối.
6. Hỗ trợ failover qua nhiều Jobe node nhưng không biến lỗi chạy code thành kết quả thành công.
7. Không cho Jobe kết nối trực tiếp tới MariaDB.

### Tiêu chí hoàn thành

- Một câu Python, một câu Java và một câu C chạy đúng bằng cùng service.
- Compile error của sinh viên được lưu đúng loại và không bị báo là lỗi hệ thống.
- Khi node thứ nhất tắt, request thành công qua node thứ hai và ghi đúng node sử dụng.
- Khi toàn bộ Jobe node tắt, không tạo contribution và lần thử ghi trạng thái hạ tầng lỗi nếu DB còn hoạt động.
- Source code giáo viên không xuất hiện trong HTML, log ứng dụng hoặc response gửi về trình duyệt.
- Jobe container không có DB credential và không kết nối được tới cổng MariaDB.

## WP5 — Nhật ký thử nghiệm cá nhân

### Công việc

1. Tạo form bằng Moodle Form API.
2. Thu input, dự đoán, mục đích, category và reflection.
3. Chạy code sinh viên và oracle theo policy.
4. Lưu mọi lần thử, kể cả dự đoán sai hoặc code sinh viên lỗi.
5. Chỉ chủ sở hữu và người có capability quản lý mới xem được run riêng tư.
6. Cho phép chạy lại và liên kết các lần thử theo attempt/question.

### Tiêu chí hoàn thành

- Dự đoán đúng tạo một `testcase_runs`.
- Dự đoán sai vẫn tạo một `testcase_runs` và không tạo contribution tự động.
- Compile/runtime error vẫn được lưu trong nhật ký với outcome đúng.
- Hai lần chạy cùng input được lưu thành hai run riêng.
- Sinh viên A không xem được run riêng của sinh viên B bằng cách đổi ID trên URL.
- Refresh trang sau POST không tạo lại run nhờ POST/Redirect/GET.

## WP6 — Đề xuất, chống trùng và duyệt

### Công việc

1. Chỉ cho đề xuất từ một run thuộc chính sinh viên.
2. Chuẩn hóa input theo policy của question.
3. Tạo SHA-256 fingerprint từ input đã chuẩn hóa.
4. Dùng `testcase_canonical_keys` để claim exact duplicate trong đúng scope ba tầng.
5. Cho phép bổ sung giải thích khi có khả năng gần trùng.
6. Cài state machine và audit log.
7. Tạo màn hình review cho giảng viên.

State transition hợp lệ:

```text
submitted → needs_explanation → submitted
submitted → approved
submitted → duplicate
submitted → rejected
approved  → archived
```

### Tiêu chí hoàn thành

- Cùng input trong cùng course/quiz/question chỉ có một canonical contribution.
- Cùng input ở hai Quiz khác nhau không bị xem là exact duplicate.
- Hai request đồng thời cùng fingerprint tạo đúng một canonical contribution.
- Request thua race nhận kết quả duplicate, không nhận HTTP 500.
- Mỗi lần đổi trạng thái có reviewer, thời gian và ghi chú.
- Không thể chuyển trực tiếp từ `rejected` sang `approved` nếu state machine không cho phép.
- Student không sửa input/fingerprint sau khi contribution được duyệt.

## WP7 — Trao thưởng và ngân hàng tham khảo

### Công việc

1. Chỉ chọn contribution `approved` để trao thưởng.
2. Không tặng testcase do chính người nhận đóng góp.
3. Không tặng lại testcase đã nhận.
4. Đóng góp và trao thưởng nằm trong một transaction hoặc có cơ chế retry idempotent.
5. Tôn trọng `reward_policy` của Quiz.
6. Không tự động thêm reward/contribution vào bộ chấm CodeRunner.

### Tiêu chí hoàn thành

- Policy `disabled` không tạo reward.
- Policy `one_for_one` cấp tối đa một reward cho một contribution được duyệt.
- Gửi lại cùng request idempotency không cấp hai reward.
- Không có reward trùng cặp `(receiver_user_id, contribution_id)`.
- Khi hết testcase khả dụng, hệ thống thông báo rõ và không sinh testcase giả.
- Trước và sau thao tác, số bản ghi `question_coderunner_tests` không đổi.

## WP8 — Dashboard, bảo mật và vận hành

### Công việc

1. Tách dashboard sinh viên và dashboard giảng viên.
2. Cho phép tắt leaderboard hoặc ẩn danh sinh viên.
3. Thêm thống kê run, dự đoán sai, category, status và duplicate.
4. Escape output theo ngữ cảnh; không ghép dữ liệu người dùng vào HTML thô.
5. Thêm rate limit, giới hạn input và audit log.
6. Thêm health check DB/Jobe và log có correlation ID.
7. Không ghi secret, teacher answer hoặc toàn bộ source sinh viên vào log mặc định.

### Tiêu chí hoàn thành

- Payload HTML/JavaScript trong input/output hiển thị như text và không thực thi.
- Input vượt giới hạn bị từ chối trước khi gọi Jobe.
- Vượt rate limit trả thông báo có thể thử lại, không tạo run thừa.
- Student chỉ thấy thống kê được policy cho phép.
- Teacher lọc được theo Quiz, question, status, category và khoảng thời gian.
- Log truy vết được một request từ Moodle tới Jobe bằng correlation ID mà không chứa secret.

## WP9 — Kiểm thử, rollout và loại bỏ legacy

### Công việc

1. Viết unit test cho normalizer, fingerprint và state machine.
2. Viết integration test cho repository/migration.
3. Viết test đồng thời cho duplicate và reward.
4. Viết end-to-end test cho student và teacher.
5. Chạy pilot trên một Quiz trước khi mở toàn khóa học.
6. Sau thời gian ổn định, archive bảng và code legacy.

### Tiêu chí hoàn thành

- Toàn bộ acceptance test ở mục 7 đạt.
- Không có lỗi PHP trong log khi chạy bộ test.
- Pilot ít nhất một Quiz hoàn thành mà không mất attempt hoặc thay đổi bộ chấm.
- Có tài liệu backup, restore, rollback và xử lý Jobe/DB outage.
- Code legacy không còn được route, include hoặc deploy.

## 7. Bộ acceptance test bắt buộc

| ID | Kịch bản | Kết quả mong đợi |
| --- | --- | --- |
| AC-CTX-01 | Student đổi `question_id` sang câu ngoài Quiz | HTTP 403 hoặc lỗi validation; không gọi Jobe |
| AC-CTX-02 | Student gửi vào Quiz đã tắt | Không tạo run/contribution theo policy |
| AC-RUN-01 | Dự đoán đúng | Lưu run với đủ 3 output và outcome |
| AC-RUN-02 | Dự đoán sai | Vẫn lưu run riêng; không tự công khai |
| AC-RUN-03 | Code sinh viên compile error | Lưu compile error; oracle vẫn theo policy |
| AC-RUN-04 | Một Jobe node dừng | Failover sang node còn lại |
| AC-RUN-05 | Tất cả Jobe node dừng | Không tạo contribution; báo lỗi hạ tầng an toàn |
| AC-CON-01 | Đề xuất từ run của chính mình | Tạo contribution `submitted` |
| AC-CON-02 | Đề xuất từ run của người khác | Bị từ chối |
| AC-DUP-01 | Hai student gửi input giống nhau đồng thời | Một canonical contribution; một duplicate |
| AC-DUP-02 | Input giống nhau ở Quiz khác | Được phép tạo contribution riêng |
| AC-REV-01 | Teacher duyệt | Status `approved`, có audit log |
| AC-REV-02 | Student gọi API duyệt | Bị từ chối; status không đổi |
| AC-REW-01 | Contribution được duyệt với one-for-one | Cấp tối đa một reward hợp lệ |
| AC-REW-02 | Retry request trao thưởng | Không cấp trùng |
| AC-GRADE-01 | Đóng góp và duyệt testcase | `question_coderunner_tests` không đổi |
| AC-GRADE-02 | Đang có quiz attempt in-progress | Attempt không bị xóa hoặc sửa |
| AC-SEC-01 | Input chứa `<script>` | Không thực thi trên dashboard |
| AC-SEC-02 | Input vượt giới hạn | Bị từ chối trước khi gọi Jobe |
| AC-PRIV-01 | Student A đọc private run của B | Bị từ chối |
| AC-MIG-01 | Chạy migration lần hai | Không lỗi, không nhân đôi dữ liệu |

## 8. Lệnh và bằng chứng kiểm tra

### 8.1. Kiểm tra tĩnh

```bash
docker compose config --quiet
bash -n entrypoint.sh
docker exec moodle_app php -l /var/www/html/local/testcase_exchange/index.php
docker exec moodle_app php -l /var/www/html/local/testcase_exchange/lib.php
git diff --check
```

Khi đã thêm Moodle PHPUnit:

```bash
docker exec moodle_app vendor/bin/phpunit local/testcase_exchange/tests
```

### 8.2. Kiểm tra upgrade và migration

```bash
docker exec moodle_app php /var/www/html/admin/cli/upgrade.php --non-interactive
docker exec moodle_app php /var/www/html/admin/cli/purge_caches.php
```

Bằng chứng cần lưu:

- Version plugin và schema migration.
- `SHOW CREATE TABLE` của các bảng mới.
- Số bản ghi trước/sau migration.
- Kết quả chạy migration lần hai.

### 8.3. Kiểm tra hạ tầng

```bash
docker compose ps
docker exec moodle_app curl -fsS http://jobe1/jobe/index.php/restapi/languages
docker exec moodle_app curl -fsS http://jobe2/jobe/index.php/restapi/languages
```

### 8.4. Kiểm tra bất biến bộ chấm

Trước và sau test đóng góp:

```sql
SELECT COUNT(*)
FROM mdl_question_coderunner_tests
WHERE questionid = :question_id;

SELECT COUNT(*)
FROM mdl_quiz_attempts
WHERE quiz = :quiz_id AND state = 'inprogress';
```

Hai kết quả phải không đổi.

### 8.5. Kiểm tra đồng thời

Chạy tối thiểu 10 request đồng thời cho cùng scope và input. Kết quả DB phải thỏa:

```sql
SELECT COUNT(*)
FROM testcase_canonical_keys
WHERE course_id = :course_id
  AND quiz_id = :quiz_id
  AND question_id = :question_id
  AND input_fingerprint = :fingerprint;
```

Số canonical contribution phải bằng `1`. Mọi request còn lại phải nhận phản hồi duplicate có kiểm soát.

## 9. Definition of Done cho mỗi work package

Một work package chỉ được đánh dấu hoàn thành khi:

- Code và migration đã được review.
- Không có secret mới trong Git.
- Unit/integration test liên quan đạt.
- Acceptance criteria của work package đạt và có bằng chứng.
- Có kiểm tra quyền cho student, teacher và admin.
- Có xử lý failure path, không chỉ happy path.
- Tài liệu cấu hình và rollback được cập nhật.
- Docker image được rebuild và smoke test thành công.
- Không còn TODO bắt buộc để vận hành chức năng của work package.

## 10. Thứ tự release đề xuất

| Release | Work package | Điều kiện phát hành |
| --- | --- | --- |
| R1 — Stabilize | WP0–WP3 | Schema thống nhất, đúng context, không còn route legacy |
| R2 — Private Runs | WP4–WP5 | Chạy đa ngôn ngữ và lưu nhật ký riêng ổn định |
| R3 — Contributions | WP6 | Đề xuất, chống trùng và review hoạt động |
| R4 — Rewards | WP7 | Reward idempotent, không sửa bộ chấm |
| R5 — Pilot | WP8–WP9 | Security, dashboard, E2E và pilot đạt |

Không phát hành R3 nếu R2 chưa ổn định; contribution phải được tạo từ một private run đã lưu, không quay lại mô hình nhập trực tiếp vào kho công khai.

## 11. Quyết định cần chốt trước khi bắt đầu WP4

1. Lấy code sinh viên từ latest quiz attempt hay cho phép nhập code tại trang thử nghiệm?
2. Có hiển thị `oracle_output` ngay, sau N lần thử hay chỉ sau deadline?
3. Normalization policy mặc định là raw text, line-based hay JSON-aware?
4. Quiz dùng auto-approve hay bắt buộc teacher review?
5. Category do hệ thống cố định hay giảng viên cấu hình theo question?
6. Giới hạn run/phút và input bytes cho pilot là bao nhiêu?

Các quyết định này phải được lưu cùng version policy để có thể giải thích vì sao hai Quiz xử lý testcase khác nhau.
