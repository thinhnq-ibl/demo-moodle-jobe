# Báo Cáo Đánh Giá UI/UX & Các Vấn Đề Ghi Nhận Thực Tế (Observed Issues)

Báo cáo này tập hợp các phát hiện khách quan từ đợt khảo sát thực tế trên môi trường Moodle LMS + Jobe Sandbox, chỉ ra các điểm người dùng có thể gặp khó khăn, các chỗ dễ gây nhầm lẫn và khuyến nghị tối ưu trước khi mời nhóm người dùng thử nghiệm rộng rãi.

---

## 1. Các điểm gây khó hiểu hoặc dễ làm người dùng lúng túng

### 1.1. Khái niệm "Chạy kiểm tra vi sai" có thể còn trừu tượng với sinh viên năm nhất
- **Hiện tượng:** Tại nút bấm chính (`SV_04_playground_ready_desktop.png`, `SV_05_input_filled_before_run_desktop.png`), chữ ghi là *"▶️ Chạy Kiểm Tra Vi Sai"*.
- **Vấn đề:** Sinh viên mới bắt đầu học lập trình có thể chưa quen với thuật ngữ chuyên môn "Kiểm thử vi sai" (Differential Testing) và băn khoăn không biết nút này khác gì nút "Check" thông thường của Moodle/CodeRunner.
- **Khuyến nghị:** Bổ sung dòng chú thích ngắn ngay dưới nút: *"Hệ thống chạy song song code của bạn và lời giải của giảng viên trên cùng bộ testcase để đối chiếu."*

### 1.2. Nhận biết điều kiện để xuất hiện nút "Đề xuất Đóng góp"
- **Hiện tượng:** Nút `🚀 Đề xuất Đóng góp` chỉ xuất hiện khi và chỉ khi: Testcase đúng chuẩn đề (Pass Oracle) NHƯNG mã nguồn của sinh viên in ra sai (`SV_07_run_oracle_pass_student_fail_desktop.png`, `SV_09_contribution_button_eligible_desktop.png`).
- **Vấn đề:** Nếu một sinh viên viết code hoàn toàn đúng và chạy testcase đúng, cả hai đều ra kết quả đúng thì nút đóng góp **sẽ không xuất hiện** (cột đóng góp hiển thị trạng thái khác). Sinh viên có thể hiểu lầm rằng chức năng bị lỗi.
- **Khuyến nghị:** Cần có một tooltip hoặc banner hướng dẫn ngắn gọn: *"💡 Mẹo đóng góp: Hãy thiết kế một testcase biên hoặc trường hợp đặc biệt mà chương trình hiện tại của bạn chưa xử lý được để mở khóa quyền đóng góp!"*

---

## 2. Các điểm ma sát thao tác (Friction Points)

### 2.1. Nút "Quay lại bài trắc nghiệm / Quiz" nằm ở đầu trang
- **Hiện tượng:** Nút *"← Quay lại bài trắc nghiệm / Quiz"* nằm ở góc trên cùng bên trái (`SV_01_quiz_entry_banner_desktop.png`, `SV_04_playground_ready_desktop.png`).
- **Vấn đề:** Sau khi sinh viên cuộn trang xuống phần kết quả kiểm tra vi sai ở cuối màn hình (`SV_07_run_oracle_pass_student_fail_desktop.png`), để quay lại nộp bài trong Quiz, sinh viên phải cuộn ngược toàn bộ màn hình lên trên cùng.
- **Khuyến nghị:** Bổ sung thêm một nút phụ *"⬅️ Quay lại làm bài Quiz"* cố định hoặc ở ngay chân bảng kết quả cuối trang.

### 2.2. Nạp code từ phiên bản cũ (Code Revision Restore)
- **Hiện tượng:** Tại danh sách phiên bản code (`SV_04_playground_ready_desktop.png`), khi bấm *"📋 Nạp code v..."*, code được điền vào ô soạn thảo và tự động lưu draft.
- **Vấn đề:** Mặc dù hệ thống có hiển thị thông báo góc nhỏ, nhưng sinh viên đang tập trung ở ô soạn thảo có thể không chắc chắn code trong khung đã được thay thế hoàn toàn hay chưa nếu hai phiên bản có cấu trúc gần giống nhau.
- **Khuyến nghị:** Bổ sung hiệu ứng viền sáng nhẹ (flash highlight màu vàng nhạt trong 0.5s) trên khung soạn thảo mã nguồn `#student_code` mỗi khi người dùng bấm nạp code.

---

## 3. Khảo sát trên thiết bị Di động (Mobile Viewport: 390 × 844)

### 3.1. Bảng kết quả vi sai trên màn hình nhỏ
- **Hiện tượng:** Trên màn hình điện thoại 390px (`MB_02_student_results_table_mobile.png`), bảng có 7 cột thông tin.
- **Quan sát thực tế:** Nhờ container `.table-responsive` mới được trang bị thanh cuộn ngang độc lập và tiêu đề giữ cố định (`sticky`), giao diện không bị vỡ bố cục tổng thể. Sinh viên có thể dùng ngón tay vuốt ngang để xem các cột Cổng đóng góp và Thẩm định.
- **Khuyến nghị:** Trên màn hình di động cực nhỏ (< 400px), có thể cân nhắc ẩn bớt cột ID hoặc hiển thị dạng thẻ gấp gọn (Card View) để sinh viên không phải vuốt ngang quá nhiều.

### 3.2. Bàn phím ảo trên điện thoại che khuất textarea
- **Hiện tượng:** Khi sinh viên chạm vào khung mã nguồn hoặc textarea mục đích kiểm thử trên mobile (`MB_01_student_playground_mobile.png`), bàn phím ảo của điện thoại sẽ bật lên và chiếm khoảng 40% chiều cao màn hình.
- **Quan sát:** Giao diện vẫn phản hồi tốt, không làm mất dữ liệu draft nhờ cơ chế LocalStorage tự động lưu mỗi 500ms.

---

## 4. Bảng kiểm kê trạng thái hệ thống

| Trạng thái | Đã quan sát thực tế? | Minh chứng ảnh chụp | Nhận xét đánh giá |
|---|---|---|---|
| Chưa có dữ liệu (Empty State) | ĐÃ QUAN SÁT | `SV_03_dashboard_empty_desktop.png` | Hiển thị thông báo hướng dẫn người dùng nhập liệu, không bị trang trắng |
| Đầy đủ dữ liệu (Populated) | ĐÃ QUAN SÁT | `SV_04_playground_ready_desktop.png` | Đầy đủ 3 phiên bản code, bảng cuộn testcase mượt mà |
| Bỏ trống trường bắt buộc | ĐÃ QUAN SÁT | `SV_05_input_filled_before_run_desktop.png` | Trình duyệt kích hoạt HTML5 required validation chặn submit trống |
| Lỗi cú pháp / Lỗi thực thi | ĐÃ QUAN SÁT | `SV_06_run_syntax_error_desktop.png` | Hiển thị rõ ràng khối thông báo lỗi đỏ kèm chi tiết dòng code lỗi |
| Testcase trùng lặp (Duplicate) | ĐÃ QUAN SÁT | `SV_11_contribution_duplicate_warning_desktop.png` | Nhận diện chính xác hash canonical key và chỉ ra lượt trùng |
| Nút bị khóa trong chế độ xem thử | ĐÃ QUAN SÁT | `GV_11_teacher_preview_mode_desktop.png` | Nút chạy bị vô hiệu hóa an toàn, có tooltip giải thích rõ lý do |
| Truy cập trái phép (Access Denied) | ĐÃ QUAN SÁT | `QT_05_permission_denied_student_desktop.png` | Moodle chặn phân quyền nghiêm ngặt, bảo vệ tuyệt đối chức năng quản trị |
