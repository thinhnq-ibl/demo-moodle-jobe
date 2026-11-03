# Báo Cáo Luồng Trải Nghiệm Người Dùng Thực Tế (User Flows)

Tài liệu này mô tả chi tiết diễn biến thao tác thực tế của **Sinh viên** và **Giảng viên** trên hệ thống `local_testcase_exchange` kết hợp `quizaccess_testcaseexchange`, gắn liền với bằng chứng ảnh chụp giao diện thực tế.

---

## 1. Hành trình Sinh viên (Student Journey)

### Câu hỏi 1: Người dùng bắt đầu từ đâu?
- **Điểm xuất phát ban đầu:** Sinh viên bắt đầu từ bài trắc nghiệm lập trình (Quiz) trong khóa học Moodle (`SV_01_quiz_entry_banner_desktop.png`).
- Tại trang giới thiệu bài Quiz, subplugin `quizaccess_testcaseexchange` hiển thị một banner thông báo nổi bật kèm nút bấm: *"Đi đến bảng điều khiển trao đổi testcase"*.
- Trong quá trình đang làm bài (`SV_02_attempt_scratchpad_link_desktop.png`), sinh viên có thể chuyển đổi nhanh sang không gian thử nghiệm (Playground) mà không làm mất bài thi.

### Câu hỏi 2: Mỗi bước cần nhập/chọn gì?
1. **Bước 1 - Chọn bài tập:** Sinh viên chọn bài tập/câu hỏi cần thử nghiệm từ menu thả xuống (`SV_04_playground_ready_desktop.png`).
2. **Bước 2 - Mã nguồn:** Dán hoặc chỉnh sửa mã nguồn chương trình của mình vào khung soạn thảo mã nguồn. Hệ thống cung cấp danh sách 3 phiên bản code gần nhất kèm nút *"📋 Nạp code v..."* để hoàn tác mã nguồn nhanh chóng.
3. **Bước 3 - Định nghĩa testcase:**
   - **Lệnh gọi hàm / Input:** Nhập lệnh kiểm thử (ví dụ: `print(find_max([10, 20, 5]))`).
   - **Kết quả kỳ vọng (Expected Output):** Nhập kết quả mà sinh viên tin là đúng (ví dụ: `20`).
   - **Phân loại ca kiểm thử:** Chọn từ danh sách có chú giải tiếng Việt (ví dụ: `Normal case (Trường hợp thông thường)`).
   - **Mục đích kiểm thử:** Nhập giải thích hành vi vào khung nhiều dòng (`textarea`) (`SV_05_input_filled_before_run_desktop.png`).
4. **Bước 4 - Bấm nút chạy:** Bấm *"▶️ Chạy Kiểm Tra Vi Sai"*.

### Câu hỏi 3: Hệ thống phản hồi thế nào?
- Hệ thống gửi đồng thời mã nguồn sinh viên và Lời giải tham chiếu chuẩn (Oracle Solution) sang cụm Jobe Sandbox cô lập.
- Kết quả được trả về gần như tức thì và hiển thị ngay trên hàng đầu tiên của bảng *"4 & 5. Kết Quả Kiểm Tra & Cổng Đóng Góp"*:
  - **Nếu code sinh viên lỗi cú pháp/runtime:** Ô kết quả nhận được tô đỏ và in rõ dòng lỗi từ Python/Java compiler (`SV_06_run_syntax_error_desktop.png`).
  - **Nếu testcase sai quy ước đề bài:** Cột *"Thẩm định với lời giải"* gắn nhãn `❌ Sai`, cột đóng góp báo *"Lỗi thực thi đề bài"* (`SV_08_run_invalid_testcase_fail_oracle_desktop.png`).
  - **Nếu phát hiện bug trong code sinh viên (Pass Oracle, Fail Student):** Cột *"Thẩm định với lời giải"* gắn nhãn `✓ Đúng`, ô kết quả sinh viên đổi màu đỏ giúp sinh viên nhận biết bug. Cột Cổng Đóng Góp tự động kích hoạt nút màu xanh: `🚀 Đề xuất Đóng góp` (`SV_07_run_oracle_pass_student_fail_desktop.png`, `SV_09_contribution_button_eligible_desktop.png`).
  - **Nếu testcase đã có người khác đóng góp trước đó:** Hệ thống cảnh báo rõ ràng *"Trùng testcase ở lượt #..."* hoặc *"Đã có trong kho"* (`SV_11_contribution_duplicate_warning_desktop.png`).

### Câu hỏi 4: Người dùng có biết bước tiếp theo không?
- **Có, giao diện chỉ dẫn rất rõ ràng:**
  - Khi nút `🚀 Đề xuất Đóng góp` xuất hiện, sinh viên chỉ cần bấm nút để gửi testcase cho giảng viên thẩm định.
  - Sau khi gửi, hệ thống hiển thị thông báo thành công màu xanh và chuyển nút thành huy hiệu `Đã đóng góp #... (Pending)` (`SV_10_contribution_submitted_success_desktop.png`).
  - Sinh viên được hướng dẫn chuyển sang tab *"📤 Đóng góp của tôi"* (`SV_12_my_contributions_tab_desktop.png`) để theo dõi tiến độ duyệt và nhận xét của thầy cô.
  - Khi giảng viên duyệt đóng góp, phần thưởng testcase từ cộng đồng được giải phóng ở tab *"🎁 Testcase đã mở khóa"* (`SV_13_claimed_rewards_tab_desktop.png`). Sinh viên bấm *"Nhận testcase thưởng"* (`SV_14_claim_reward_action_desktop.png`) để mở rộng bộ dữ liệu kiểm thử cho bài làm của mình.
  - Sinh viên có thể xem vị trí của mình trên bảng tổng sắp thi đua tại tab *"🏆 Bảng xếp hạng"* (`SV_15_leaderboard_tab_desktop.png`).

### Câu hỏi 5: Khi lỗi, người dùng có biết cách sửa và tiếp tục không?
- **Có:**
  - Khi code bị lỗi cú pháp/runtime, thông báo lỗi chỉ ra rõ dòng code và nguyên nhân.
  - Khi kết quả in ra không khớp với kỳ vọng, ô kết quả tô màu đỏ cảnh báo trực quan; sinh viên có thể quay lại Khung 2 (Mã nguồn) sửa code, hoặc bấm nút *"🧹 Xóa testcase để nhập mới"* ở Khung 3 để thử bộ dữ liệu khác.
  - Nút quay lại *"← Quay lại bài trắc nghiệm / Quiz"* luôn cố định ở đầu trang (`SV_16_back_to_quiz_workflow_desktop.png`), cho phép sinh viên mang code đã kiểm tra kỹ lưỡng quay về nộp bài trong CodeRunner.

---

## 2. Hành trình Giảng viên (Teacher Journey)

### Câu hỏi 1: Người dùng bắt đầu từ đâu?
- **Điểm xuất phát:** Giảng viên truy cập vào module quản lý từ đường dẫn Khóa học hoặc qua Cổng Chọn Khóa Học (`GV_01_course_entry_hub_desktop.png`).
- Ngay khi vào khóa học, trang **Teacher Management Hub** hiển thị toàn bộ bức tranh vận hành của lớp (`GV_02_teacher_hub_overview_desktop.png`).

### Câu hỏi 2: Mỗi bước cần nhập/chọn gì?
1. **Quản lý đóng góp (Tab Contributions & Review):**
   - Giảng viên chọn tab *"📋 Duyệt & Thẩm định đóng góp"* (`GV_04_tab_review_contributions_desktop.png`).
   - Sử dụng bộ lọc trạng thái (`Chờ duyệt`, `Đã duyệt`, `Đã từ chối`) hoặc lọc theo tên sinh viên.
2. **Thẩm định chất lượng testcase:**
   - Giảng viên quan sát dữ liệu đầu vào, kết quả kỳ vọng, thẩm định của Oracle và mục đích kiểm thử do sinh viên viết.
   - Chọn thang điểm sư phạm: 1 đến 5 sao (`★`).
   - Nhập nhận xét sư phạm (comment) để khuyến khích hoặc hướng dẫn sinh viên (`GV_05_review_action_dialog_desktop.png`).
3. **Quyết định phê duyệt:** Bấm nút *"Chấp thuận"* (`Approve`) hoặc *"Từ chối"* (`Reject`).

### Câu hỏi 3: Hệ thống phản hồi thế nào?
- Hệ thống cập nhật trạng thái đóng góp sang `Approved` ngay lập tức (`GV_06_review_approved_state_desktop.png`).
- Điểm sao được cộng vào hồ sơ sinh viên và cập nhật lên Bảng xếp hạng tự động.
- Một testcase phần thưởng mới được tự động mở khóa cho sinh viên tương ứng.
- Lịch sử thẩm định (Review Audit Trail) được ghi nhận đầy đủ, hiển thị rõ người duyệt, thời gian duyệt và nội dung nhận xét.

### Câu hỏi 4: Người dùng có biết bước tiếp theo không?
- **Rất liền mạch:**
  - Sau khi duyệt hết danh sách, giảng viên có thể sang tab *"🏦 Ngân hàng Testcase"* (`GV_07_tab_testcase_bank_desktop.png`) để xem lại toàn bộ kho dữ liệu bài tập hoặc nạp thêm hạt giống từ XML (`GV_08_seed_import_xml_desktop.png`).
  - Giảng viên có thể sang tab *"👥 Giám sát tiến độ sinh viên"* (`GV_09_tab_student_activity_desktop.png`) để theo dõi các bước tư duy giải bài của sinh viên hoặc tải file CSV Timeline để phục vụ nghiên cứu sư phạm.
  - Tại tab *"⚙️ Cấu hình chính sách"* (`GV_10_tab_policy_configuration_desktop.png`), giảng viên dễ dàng điều chỉnh các ngưỡng rate-limit hoặc chuyển đổi giữa chế độ duyệt thủ công và tự động.
  - Khi cần đứng ở góc độ người học, giảng viên bấm *"👁️ Trải nghiệm giao diện như Sinh viên"* (`GV_11_teacher_preview_mode_desktop.png`) để kiểm tra toàn bộ luồng học mà không làm sai lệch số liệu thực tế của lớp.

### Câu hỏi 5: Khi lỗi, người dùng có biết cách sửa và tiếp tục không?
- Form thẩm định kiểm tra tính hợp lệ trước khi gửi: nếu chưa nhập nhận xét hoặc dữ liệu có xung đột, hệ thống sẽ đưa ra thông báo cảnh báo rõ ràng.
- Chế độ xem thử (Preview Mode) có banner cố định ở đầu trang kèm nút *"⬅️ Thoát chế độ xem thử"* nổi bật, đảm bảo giảng viên không bao giờ bị kẹt lại trong view sinh viên.
