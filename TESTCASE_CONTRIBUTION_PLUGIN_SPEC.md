# TÀI LIỆU ĐẶC TẢ TÍNH NĂNG VÀ KIẾN TRÚC PLUGIN: TESTCASE CONTRIBUTION & EXCHANGE
## (CodeRunner Testcase Exchange - `local_testcase_exchange`)

> **Mục tiêu:** Xây dựng phương pháp sư phạm kiểm thử tương hỗ (Mutual Testing & Test-Driven Learning). Sinh viên vừa viết mã nguồn giải thuật, vừa tham gia thiết kế và đóng góp các ca kiểm thử (testcases) độc lạ. Hệ thống tự động thẩm định dựa trên lời giải mẫu của giảng viên (`question.answer`), trao đổi các ca kiểm thử mới từ ngân hàng đề và xếp hạng thi đua trong lớp học.

---

## I. TỔNG QUAN VÀ GIÁ TRỊ SƯ PHẠM

1. **Rèn luyện tư duy phản biện & bao phủ kiểm thử:** Sinh viên không chỉ viết code giải thuật một cách thụ động đối phó với các testcase có sẵn, mà phải tự suy nghĩ các ca biên (edge cases), các trường hợp ngoại lệ để đóng góp.
2. **Học tập lặp (Iterative Learning Loop):** Khi đóng góp 1 testcase hợp lệ, sinh viên sẽ được ngân hàng "thưởng" lại 1 testcase hiểm hóc từ ngân hàng hoặc từ bạn bè trong lớp $\rightarrow$ Thúc đẩy sinh viên quay lại đọc code, tối ưu và xử lý triệt để các lỗi tiềm ẩn trong bài làm của mình.
3. **Phân tách trách nhiệm (Decoupled Architecture):**
   - **Moodle LMS:** Chỉ tập trung ghi nhận mã nguồn giải thuật chính thức làm minh chứng học tập, phục vụ chấm điểm và cấp chứng chỉ/bảng điểm chuẩn hóa.
   - **Plugin & MariaDB độc lập (`testcase_store`):** Đảm nhiệm toàn bộ việc đóng góp testcase, trao đổi chéo và bảng vinh danh thi đua mà không làm phình bảng lịch sử (`mdl_question_attempt_steps`) của Moodle.

---

## II. KIẾN TRÚC KỸ THUẬT VÀ PHÂN TẦNG DỮ LIỆU

### 1. Sơ đồ luồng hoạt động tổng thể

```text
┌───────────────────────────────────────────────────────────────────────────┐
│                           MÔN HỌC MOODLE (COURSE)                        │
├─────────────────────────────────────┬─────────────────────────────────────┤
│   A. BÀI THI / QUIZ (CODERUNNER)    │   B. KHO TESTCASE & ĐỔI THƯỞNG      │
│      (Chỉ nộp Mã Nguồn - Code)      │      (Form nộp Testcase Độc lập)    │
├─────────────────────────────────────┼─────────────────────────────────────┤
│ 1. Sinh viên viết hàm giải thuật    │ 1. Sinh viên nhập cặp:              │
│    (is_prime, is_palindrome...)     │    (Input, Expected Output)         │
│ 2. Jobe chạy kiểm thử vi sai        │ 2. Hệ thống lấy code thầy cô        │
│    (Differential Testing):          │    trong `question.answer` chạy thử │
│    student_code vs teacher_code     │ 3. Nếu Expected == Actual:          │
│ 3. Chấm điểm chính thức             │    ✓ PASS! Lưu vào `testcase_store` │
│    Lưu minh chứng chuẩn vào Moodle  │    ✓ Thưởng 1 testcase hiểm hóc mới │
│    (Không sinh rác attempt testcase)│    ✓ Cộng điểm Bảng xếp hạng lớp    │
└─────────────────────────────────────┴─────────────────────────────────────┘
```

---

## III. CHI TIẾT CÁC TÍNH NĂNG NỔI BẬT

### 1. Thẩm định Testcase động bằng Lời giải chuẩn của Thầy cô (`question.answer`)
* **Không cần hardcode kết quả:** Giảng viên chỉ cần dán đoạn mã nguồn mẫu của mình vào ô **Answer** (`question.answer`) khi tạo câu hỏi.
* **Cơ chế kiểm tra PASS của Testcase:**
  - Khi sinh viên nộp cặp `(Input, Expected Output)` trên trang Dashboard:
  - Hệ thống tự động nạp `Input` chạy qua đoạn mã `question.answer` của thầy cô:
    $$\text{actual\_result} = \text{teacher\_solution}(\text{Input})$$
  - **Nếu `Expected Output == actual\_result`:** Testcase được xác nhận là **HỢP LỆ VÀ PASS**.
  - **Nếu `Expected Output != actual\_result`:** Hệ thống lập tức từ chối và giải thích rõ lỗi sai kiến thức:
    > *"Khi chạy Input `12` qua lời giải chuẩn của thầy cô, kết quả thực tế phải là `False`, nhưng bạn chọn là `True`. Vui lòng xác định lại!"*
* **Độc lập tuyệt đối:** Việc testcase có được duyệt hay không hoàn toàn dựa vào tính đúng đắn đối chiếu với code của thầy cô; mã nguồn sinh viên nộp trong Quiz có đang sai logic cũng không ảnh hưởng đến quyền đóng góp testcase.

### 2. Thuật toán chống trùng lặp 3 lớp (Anti-Cheating & Duplication)
Khi testcase đã pass thẩm định, hệ thống lọc qua 3 lớp trước khi lưu:
1. **Lớp 1 (Chống tự nộp lặp):** Kiểm tra sinh viên đã từng nộp `Input` này cho bài tập chưa.
2. **Lớp 2 (Chống lấy quà tặng nộp lại):** Kiểm tra xem `Input` này có phải là testcase mà ngân hàng đã từng tặng cho sinh viên đó trước đây không.
3. **Lớp 3 (Chống trùng ngân hàng gốc):** Kiểm tra xem `Input` có nằm trong tập testcase hạt giống (`question_seed_testcases`) có sẵn của bài tập không.

### 3. Cơ chế Trao thưởng Testcase mới từ Ngân hàng (Reward Mechanism)
* **Nguyên lý bù trừ tập hợp:**
  $$\text{Tập khả dụng} = \text{Hạt giống gốc} \cup \text{Testcase của bạn bè khác nộp}$$
  $$\text{Tập sở hữu} = \text{Tự nộp thành công} \cup \text{Đã được tặng trước đó}$$
  $$\text{Ứng viên thưởng} = \text{Tập khả dụng} \setminus \text{Tập sở hữu}$$
* **Kết quả phần thưởng:**
  - Hệ thống rút ngẫu nhiên 1 ca kiểm thử mới và hiển thị đầy đủ cả:
    - **Input:** Ví dụ `n = 97` hoặc `s = "world"`
    - **Expected Output:** Nhãn `True` hoặc `False` do code thầy cô tự động sinh ra.
  - Khi sinh viên đã mở khóa toàn bộ testcase của bài tập, hệ thống thông báo chúc mừng: *"Bạn đã thu thập FULL toàn bộ testcase của bài tập này!"*.

### 4. Giao diện Cá nhân hóa & Bảng Vinh danh (Dashboard & Leaderboard)
* **Khối thống kê tổng quan:** Số lượng testcase đã đóng góp, số lượng testcase đã mở khóa, tổng testcase của lớp học.
* **Tab Túi đồ cá nhân:**
  - Bảng 1: Danh sách các testcase do chính sinh viên nghĩ ra và nộp thành công.
  - Bảng 2: Danh sách các testcase độc lạ nhận được từ ngân hàng để sinh viên làm tư liệu kiểm thử bài làm của mình.
* **Tab Bảng xếp hạng lớp:**
  - Vinh danh các sinh viên đóng góp nhiều ca kiểm thử độc nhất nhất trong môn học.
  - Tự động highlight dòng tài khoản của sinh viên đang đăng nhập.

### 5. Định tuyến động 100% theo Môn học (Dynamic Course Routing)
* **Tích hợp thanh Menu Moodle (`lib.php`):** Tự động gắn menu **"Kho Testcase & Đổi thưởng"** vào mọi khóa học có cài plugin, tự động truyền `?course=<current_course_id>`.
* **Dropdown quét tự động:** Chuyển sang môn học nào, ô lựa chọn bài tập tự động truy vấn ngân hàng đề để load đúng các bài tập CodeRunner của môn đó (không cần cấu hình thủ công ID câu hỏi).

---

## IV. CẤU TRÚC THƯ MỤC CHUẨN MOODLE PLUGINS DIRECTORY

Plugin được đóng gói tại thư mục `local/testcase_exchange` tuân thủ 100% chuẩn Frankenstyle:

```text
local/testcase_exchange/
├── version.php                          # Khai báo version, maturity, phụ thuộc qtype_coderunner
├── settings.php                         # Cấu hình MariaDB host/user/pass trong Site Administration
├── index.php                            # Toàn bộ giao diện Dashboard, form nộp testcase và bảng xếp hạng
├── lib.php                              # Hook extend_navigation_course tự động sinh menu điều hướng
├── README.md                            # Tài liệu tổng quan mã nguồn
├── lang/
│   └── en/
│       └── local_testcase_exchange.php  # Chuỗi bản địa hóa đa ngôn ngữ (i18n)
└── db/
    ├── access.php                       # Định nghĩa Capabilities (view, manage) cho Student & Teacher
    └── install.php                      # Hook tự động nạp Prototype vào Question Bank khi cài plugin
```

---

## V. CƠ SỞ DỮ LIỆU ĐỘC LẬP (`testcase_store`)

| Tên bảng | Chức năng |
| :--- | :--- |
| `question_solutions` | Lưu mã nguồn giải thuật chuẩn của thầy cô (`func_name`, `solution_code`). |
| `question_seed_testcases` | Kho testcase hạt giống ban đầu do giảng viên thiết lập cho từng bài. |
| `student_testcases` | Lưu các ca kiểm thử do sinh viên nộp hợp lệ (`test_input`, `expected_output`). |
| `student_received_testcases` | Lưu lịch sử các testcase sinh viên đã nhận thưởng để không bị trùng lặp. |
| `testcase_exchanges` | Lưu lịch sử ghép cặp trao đổi testcase giữa các sinh viên trong lớp. |

---

## VI. QUY TRÌNH SỬ DỤNG THỰC TẾ

1. **Giảng viên:** 
   - Tạo bài tập CodeRunner trong Moodle.
   - Nhập đề bài và dán code giải thuật chuẩn vào ô **Answer** (`question.answer`).
2. **Sinh viên:**
   - Vào môn học $\rightarrow$ Click mục **"Kho Testcase & Đổi thưởng"** trên menu hoặc trong bài học.
   - Chọn bài tập, nhập Input và Expected Output $\rightarrow$ Bấm **Gửi Testcase**.
   - Nếu khớp với code của thầy cô $\rightarrow$ Nhận ngay 1 testcase thưởng mới kèm đáp án mẫu.
   - Mở bài thi trong Quiz, hoàn thiện mã nguồn giải thuật để vượt qua toàn bộ các testcase thu thập được và nộp bài lấy điểm minh chứng chính thức.
