# Danh Mục Ảnh Khảo Sát Giao Diện UI/UX (UI Capture Index)

- **Phiên bản Plugin:** `local_testcase_exchange` v2.3.1-beta | `quizaccess_testcaseexchange` v2.3.1-beta
- **Phiên bản Moodle:** Moodle 4.4.12+ (Build: 20251212)
- **Giao diện (Theme):** Boost (Mặc định chuẩn Moodle 4.x)
- **Ngôn ngữ thử nghiệm:** Tiếng Việt (giao diện hỗ trợ song ngữ Anh - Việt)
- **Kích thước Viewport:**
  - Desktop: `1440 × 900`
  - Mobile: `390 × 844`

---

## 1. Vai trò Sinh viên (Student - SV)

| Mã ảnh | Vai trò | Trang / Trạng thái | Các bước để tới màn hình | Hành động vừa thực hiện | Kết quả quan sát |
|---|---|---|---|---|---|
| `SV_01_quiz_entry_banner_desktop.png` | Sinh viên | Trang bài Quiz (Landing page) | Đăng nhập `student1` -> Chọn khóa học CS101 -> Vào bài Quiz 76 | Truy cập trang bắt đầu làm bài Quiz | Xuất hiện khối thông báo (banner) của subplugin `quizaccess_testcaseexchange` với nút *"Đi đến bảng điều khiển trao đổi testcase"* |
| `SV_02_attempt_scratchpad_link_desktop.png` | Sinh viên | Màn hình làm bài Quiz | Nhấn *"Bắt đầu làm bài"* trong bài Quiz | Vào giao diện làm bài trắc nghiệm CodeRunner | Khung soạn thảo CodeRunner hiển thị, có hướng dẫn và nút mở nhanh không gian trao đổi testcase |
| `SV_03_dashboard_empty_desktop.png` | Sinh viên | Playground (Trạng thái rỗng) | Truy cập `/local/testcase_exchange/index.php` -> Chọn câu hỏi chưa có lượt chạy | Chọn câu hỏi mới từ danh sách dropdown | Giao diện hiển thị form soạn thảo nhưng bảng kết quả báo *"Chưa có lượt chạy thử nghiệm nào. Hãy nhập mã nguồn và testcase ở trên để bắt đầu!"* |
| `SV_04_playground_ready_desktop.png` | Sinh viên | Playground (Đầy đủ dữ liệu) | Chọn câu hỏi có dữ liệu (`76:794` Find Max Scratchpad Demo) | Tải trang Playground | Hiển thị đầy đủ 4 khối: Khối Đổi bài tập, Khối 2 Mã nguồn (chỉ hiển thị 3 version mới nhất), Khối 3 Định nghĩa testcase, Khối 4 & 5 Bảng kết quả vi sai |
| `SV_05_input_filled_before_run_desktop.png` | Sinh viên | Form nhập liệu trước khi chạy | Dán mã nguồn Python, nhập lệnh gọi hàm, output kỳ vọng, chọn phân loại và nhập mục đích | Nhập liệu hoàn chỉnh các trường bắt buộc | Textbox mục đích kiểm thử hiển thị nhiều dòng (textarea); Phân loại hiển thị song ngữ Anh-Việt; nút *"Chạy Kiểm Tra Vi Sai"* sáng xanh sẵn sàng bấm |
| `SV_06_run_syntax_error_desktop.png` | Sinh viên | Kết quả lỗi cú pháp (Syntax Error) | Nhập mã nguồn cố ý sai cú pháp (`retur numbers[0`) -> Nhấn chạy | Bấm nút *"▶️ Chạy Kiểm Tra Vi Sai"* | Bảng kết quả hiển thị thông báo lỗi runtime/syntax error màu đỏ, cột *"Thẩm định với lời giải"* gắn nhãn `❌ Sai`, nút đóng góp bị khóa |
| `SV_07_run_oracle_pass_student_fail_desktop.png` | Sinh viên | Testcase Độc Đáo (Pass Oracle, Fail Student) | Nhập code sinh viên có lỗi logic + testcase phát hiện được lỗi đó | Bấm *"Chạy Kiểm Tra Vi Sai"* | Code sinh viên trả về kết quả sai (đổi màu đỏ), Oracle chuẩn trả về đúng (`✓ Đúng`). Hệ thống kích hoạt nút `🚀 Đề xuất Đóng góp` |
| `SV_08_run_invalid_testcase_fail_oracle_desktop.png` | Sinh viên | Testcase sai quy ước đề bài | Nhập input sai kiểu dữ liệu (`print(find_max("khong_phai_list"))`) | Bấm *"Chạy Kiểm Tra Vi Sai"* | Cả code sinh viên và lời giải mẫu đều báo lỗi runtime. Cột *"Thẩm định với lời giải"* báo `❌ Sai`, cột đóng góp báo *"Lỗi thực thi đề bài"* |
| `SV_09_contribution_button_eligible_desktop.png` | Sinh viên | Khối Cổng Đóng Góp (Cận cảnh) | Chạy testcase đạt điều kiện vi sai | Cuộn xuống bảng kết quả kiểm tra | Cận cảnh hàng kết quả: nút xanh `🚀 Đề xuất Đóng góp` xuất hiện nổi bật tại cột Cổng Đóng Góp |
| `SV_10_contribution_submitted_success_desktop.png` | Sinh viên | Đóng góp thành công | Nhấn nút `🚀 Đề xuất Đóng góp` | Form POST đóng góp gửi lên máy chủ | Xuất hiện thông báo thành công màu xanh của Moodle, nút chuyển thành huy hiệu `Đã đóng góp #... (Pending)` chờ giảng viên duyệt |
| `SV_11_contribution_duplicate_warning_desktop.png` | Sinh viên | Trạng thái testcase trùng lặp | Chạy testcase có cùng bộ input/output với lượt chạy trước | Quan sát bảng kết quả lượt chạy | Cột đóng góp ghi rõ *"Trùng testcase ở lượt #..."* hoặc *"Đã có trong kho"* kèm liên kết nhảy tới lượt đại diện |
| `SV_12_my_contributions_tab_desktop.png` | Sinh viên | Tab Đóng góp của tôi | Bấm vào tab *"📤 Đóng góp của tôi"* trên thanh điều hướng | Chuyển tab trong trang sinh viên | Danh sách toàn bộ các đề xuất đóng góp của sinh viên, kèm trạng thái (Pending/Approved), số sao đánh giá (★) và nhận xét của giảng viên |
| `SV_13_claimed_rewards_tab_desktop.png` | Sinh viên | Tab Phần thưởng đã mở khóa | Bấm vào tab *"🎁 Testcase đã mở khóa"* | Chuyển tab phần thưởng | Danh sách các testcase chất lượng từ bạn bè hoặc kho hạt giống mà sinh viên đã mở khóa được kèm trạng thái nhận |
| `SV_14_claim_reward_action_desktop.png` | Sinh viên | Thao tác nhận testcase thưởng | Bấm nút *"🎁 Nhận testcase thưởng"* | Gửi yêu cầu claim phần thưởng | Testcase chuyển sang trạng thái đã nhận (`✓ Đã nhận`), hiển thị đầy đủ input và expected output để sinh viên dùng kiểm thử code |
| `SV_15_leaderboard_tab_desktop.png` | Sinh viên | Tab Bảng xếp hạng | Bấm vào tab *"🏆 Bảng xếp hạng"* | Xem bảng tổng sắp | Hiển thị bảng vinh danh Top sinh viên đóng góp nhiều testcase chất lượng nhất kèm số sao vàng tích lũy |
| `SV_16_back_to_quiz_workflow_desktop.png` | Sinh viên | Luồng quay lại Quiz | Bấm nút *"← Quay lại bài trắc nghiệm / Quiz"* | Điều hướng quay lại Moodle Quiz | Sinh viên quay về bài làm trắc nghiệm an toàn mà không làm mất tiến trình làm bài |

---

## 2. Vai trò Giảng viên (Teacher - GV)

| Mã ảnh | Vai trò | Trang / Trạng thái | Các bước để tới màn hình | Hành động vừa thực hiện | Kết quả quan sát |
|---|---|---|---|---|---|
| `GV_01_course_entry_hub_desktop.png` | Giảng viên | Cổng chọn khóa học (Course Hub) | Đăng nhập tài khoản giảng viên -> Vào `/local/testcase_exchange/index.php` | Truy cập trang Hub khóa học | Thống kê số lượng Quiz, số câu hỏi CodeRunner và tổng số đóng góp đang chờ duyệt của từng khóa học |
| `GV_02_teacher_hub_overview_desktop.png` | Giảng viên | Tổng quan Quản lý (Teacher Hub) | Chọn khóa học CS101 | Vào Dashboard Giảng viên | 4 thẻ thống kê trực quan (Trạng thái Quiz, Lượt chạy thử, Đóng góp testcase, Phần thưởng đã cấp) cùng các nút lối tắt nhanh |
| `GV_03_quiz_settings_accessrule_desktop.png` | Giảng viên | Cấu hình Quiz Access Rule | Vào trang cài đặt Quiz (`course/modedit.php?update=112`) | Mở mục cấu hình Bổ sung | Thấy trường *"Bật/Tắt trao đổi testcase"* do subplugin `quizaccess_testcaseexchange` cung cấp |
| `GV_04_tab_review_contributions_desktop.png` | Giảng viên | Tab Duyệt đóng góp | Nhấn tab *"📋 Duyệt & Thẩm định đóng góp"* | Xem danh sách đóng góp sinh viên | Bảng lọc theo trạng thái (Pending, Approved, Rejected) và lọc theo từng sinh viên cụ thể |
| `GV_05_review_action_dialog_desktop.png` | Giảng viên | Form thẩm định & cho điểm | Tìm một đóng góp đang chờ duyệt (Pending) | Xem chi tiết đóng góp | Cụm thao tác thẩm định gồm chọn số sao sư phạm (1-5 sao), ô nhập nhận xét sư phạm, nút Chấp thuận (`Approve`) và nút Từ chối (`Reject`) |
| `GV_06_review_approved_state_desktop.png` | Giảng viên | Trạng thái sau khi duyệt | Nhập nhận xét và bấm nút *"Chấp thuận"* | Gửi quyết định thẩm định | Đóng góp chuyển sang trạng thái `Approved`, hệ thống ghi nhận lịch sử audit và tự động giải phóng phần thưởng cho sinh viên |
| `GV_07_tab_testcase_bank_desktop.png` | Giảng viên | Tab Ngân hàng Testcase | Nhấn tab *"🏦 Ngân hàng Testcase"* | Xem kho testcase | Hiển thị rõ hai phần: 1. Testcase mẫu công khai (`useasexample=1`) và 2. Kho testcase Hạt giống (Seed bank) |
| `GV_08_seed_import_xml_desktop.png` | Giảng viên | Khung nạp hạt giống từ XML | Cuộn xuống phần nạp hạt giống | Xem form tải file XML | Hướng dẫn xuất XML từ Moodle Question Bank và form tải lên để tự động trích xuất testcase hạt giống |
| `GV_09_tab_student_activity_desktop.png` | Giảng viên | Tab Giám sát tiến độ sinh viên | Nhấn tab *"👥 Giám sát tiến độ sinh viên"* | Xem dòng thời gian làm bài | Thống kê số lần sửa code, số testcase đã chạy của từng sinh viên, kèm nút tải CSV Timeline để nghiên cứu học thuật |
| `GV_10_tab_policy_configuration_desktop.png` | Giảng viên | Tab Cấu hình chính sách bài tập | Nhấn tab *"⚙️ Cấu hình chính sách"* | Xem các tham số vận hành | Cấu hình chế độ duyệt (Thủ công / Tự động), chính sách thưởng (1 đổi 1), giới hạn tốc độ chạy (rate-limit) và kích thước input tối đa |
| `GV_11_teacher_preview_mode_desktop.png` | Giảng viên | Chế độ xem thử của Giảng viên | Bấm nút *"👁️ Trải nghiệm giao diện như Sinh viên"* | Kích hoạt Preview Mode | Giao diện sinh viên hiển thị kèm banner cảnh báo màu vàng; các nút chạy vi sai bị khóa an toàn để bảo vệ dữ liệu thống kê |

---

## 3. Vai trò Quản trị viên (Admin - QT)

| Mã ảnh | Vai trò | Trang / Trạng thái | Các bước để tới màn hình | Hành động vừa thực hiện | Kết quả quan sát |
|---|---|---|---|---|---|
| `QT_01_admin_menu_navigation_desktop.png` | Quản trị viên | Menu Quản trị Site | Đăng nhập tài khoản `admin` -> Vào Site Administration | Điều hướng menu quản trị Moodle | Vị trí plugin trong menu quản trị hệ thống (`Plugins -> Local plugins -> Testcase Exchange`) |
| `QT_02_local_plugin_settings_desktop.png` | Quản trị viên | Cấu hình máy chủ `local_testcase_exchange` | Vào `/admin/settings.php?section=local_testcase_exchange` | Mở trang cấu hình quản trị | Thiết lập cụm máy chủ Jobe sandbox song song (`jobe1;jobe2`), cổng mạng và API Key nội bộ |
| `QT_03_quizaccess_plugin_overview_desktop.png` | Quản trị viên | Danh sách tổng quan Plugins | Vào `/admin/plugins.php` | Kiểm tra trạng thái cài đặt plugin | Hiển thị phiên bản `v2.3.1-beta`, trạng thái hoạt động và đường dẫn cấu hình của cả hai plugin |
| `QT_04_system_health_check_desktop.png` | Quản trị viên | Kiểm tra sức khỏe hệ thống (Health Check) | Vào `/local/testcase_exchange/health.php` | Chạy chẩn đoán tự động | Báo cáo kiểm tra kết nối CSDL và kiểm tra độ phản hồi của từng node Jobe sandbox (`jobe1`, `jobe2`) trả về HTTP 200 OK |
| `QT_05_permission_denied_student_desktop.png` | Quản trị viên | Kiểm soát phân quyền (Access Control) | Dùng tài khoản sinh viên `student1` cố tình truy cập trang cấu hình quản trị | Thử truy cập URL quản trị | Moodle kích hoạt cơ chế bảo mật, từ chối truy cập và chuyển hướng về trang thông báo lỗi quyền hạn |

---

## 4. Giao diện Di động (Mobile - MB, Viewport: 390 × 844)

| Mã ảnh | Vai trò | Trang / Trạng thái | Các bước để tới màn hình | Hành động vừa thực hiện | Kết quả quan sát |
|---|---|---|---|---|---|
| `MB_01_student_playground_mobile.png` | Sinh viên | Playground làm bài trên điện thoại | Truy cập giao diện sinh viên với viewport 390 × 844 | Xem bố cục di động | Các thẻ card tự động co giãn 1 cột; ô soạn code và textarea mục đích kiểm thử hiển thị vừa vặn, không bị tràn màn hình |
| `MB_02_student_results_table_mobile.png` | Sinh viên | Bảng kết quả vi sai trên điện thoại | Chạy kiểm tra và cuộn xuống bảng kết quả | Thao tác cuộn bảng trên màn hình cảm ứng | Container cuộn ngang và cuộn dọc hoạt động trơn tru; tiêu đề bảng giữ cố định (sticky); không làm vỡ layout trang |
| `MB_03_teacher_hub_mobile.png` | Giảng viên | Trang quản lý giảng viên trên điện thoại | Đăng nhập giảng viên trên viewport di động | Xem Dashboard quản lý | Các thẻ card thống kê tự động xếp dọc (stack vertical); nút chuyển đổi xem thử hiển thị đầy đủ |
| `MB_04_teacher_review_mobile.png` | Giảng viên | Tab Duyệt đóng góp trên điện thoại | Bấm tab duyệt đóng góp trên mobile | Xem bảng duyệt và form đánh giá | Bảng duyệt hỗ trợ vuốt cuộn ngang; form cho điểm 1-5 sao và nút chấp thuận/từ chối dễ chạm bấm trên màn hình cảm ứng |
