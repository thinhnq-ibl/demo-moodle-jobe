# Báo cáo pilot: Python cơ bản cho AI và đóng góp testcase

Ngày chạy: 07/10/2026

Khóa học: `AI-PY-PILOT` — *Python cơ bản cho AI — Pilot đóng góp*

Vai trò kiểm thử: giáo viên/quản trị, `student1`, `student2`

## Nội dung đã tạo

1. **Chuẩn hóa độ tin cậy**: đọc một số thực, chặn về đoạn `[0, 1]`, in hai chữ số thập phân.
2. **Đếm từ khóa AI**: đếm từ độc lập `ai`, không phân biệt hoa thường.
3. **Tính accuracy**: đọc `correct total`, xử lý `total = 0`, in phần trăm với hai chữ số thập phân.
4. **Bài đọc Alan Turing**: đoạn văn ngắn về máy Turing, công việc giải mã, Turing Test và trách nhiệm xã hội; sinh viên đặt câu hỏi mở liên hệ ít nhất hai ý.

## Kịch bản và kết quả chạy

Hai sinh viên đã chạy cả ba bài bằng các trường hợp biên khác nhau rồi gửi testcase. Tổng cộng:

- 6/6 lần chạy có `student_outcome = success` và `oracle_outcome = success`.
- 6/6 testcase được giáo viên duyệt.
- Sinh viên có quyền xem/chạy/đóng góp; không có quyền duyệt hoặc quản lý chính sách.
- Truy vấn dashboard và HTML của mỗi sinh viên chỉ chứa run/contribution của chính sinh viên đó.
- Chính sách thưởng đang tắt; cả hai sinh viên có `reward_count = 0`, vì vậy testcase đã duyệt không được phát lại cho sinh viên khác.
- Oracle được ẩn và leaderboard được tắt.
- Forum bài đọc đã được sửa fixture để có Moodle module context hợp lệ và mở được từ web.

## Đánh giá luồng sinh viên

Luồng cơ bản rõ: chọn bài → nhập code và input → chạy → xem kết quả → gửi đóng góp. Việc tách lần chạy riêng tư khỏi hành động “đóng góp” giúp sinh viên hiểu lúc nào dữ liệu được gửi cho giáo viên.

Các điểm nên cải thiện trước khi dùng rộng:

1. Dashboard yêu cầu dán lại toàn bộ code, bị trùng với thao tác làm Quiz. Nên có nút lấy code từ attempt gần nhất hoặc mở Testcase Exchange ngay trong trang review của Quiz.
2. Bảng lịch sử cần hiện tên Quiz/câu hỏi thay cho việc buộc người dùng suy ra từ ID.
3. Sau khi chạy/gửi nên cuộn tới và làm nổi bật bản ghi vừa tạo.
4. Trạng thái, empty state và lỗi Jobe cần dùng câu chữ thân thiện, nhất quán tiếng Việt.
5. Nên bổ sung hướng dẫn ngắn và ví dụ cho “mục đích testcase”, “loại testcase”, “đầu ra dự đoán”.

## Đánh giá luồng giáo viên

Giáo viên có thể bật chính sách, đặt giới hạn, ẩn oracle, tắt thưởng và duyệt đóng góp. Luồng đủ dùng cho pilot nhỏ nhưng màn hình duyệt chưa thuận tiện khi số lượng tăng.

Ưu tiên cải thiện:

1. Hiện họ tên sinh viên, tên Quiz và tên câu hỏi; ID chỉ nên là thông tin phụ.
2. Thêm lọc theo Quiz, câu hỏi, trạng thái, loại testcase, sinh viên và ngày gửi.
3. Thêm duyệt/từ chối hàng loạt và ghi chú mẫu.
4. Thêm thống kê số pending/approved/rejected trên dashboard.
5. Đổi các thuật ngữ kỹ thuật như `stdin`, `testcode`, `template` thành nhãn sư phạm kèm trợ giúp ngữ cảnh.

## Quyền riêng tư và quyết định còn lại

### Testcase đóng góp

Yêu cầu “sinh viên không thấy testcase của nhau” đang được đáp ứng khi giữ cấu hình pilot:

- dashboard chỉ truy vấn dữ liệu theo `userid` hiện tại;
- sinh viên không có capability review/manage;
- oracle ẩn;
- reward policy tắt;
- leaderboard tắt.

Không nên bật lại reward policy nếu yêu cầu là **không bao giờ chia sẻ testcase giữa sinh viên**.

### Câu hỏi đóng góp cho bài đọc

Forum loại Q&A chỉ đáp ứng mô hình **“đăng trước, xem sau”**. Theo logic chuẩn của Moodle, sau khi sinh viên đã đăng và hết thời gian sửa bài (`maxeditingtime`), họ có thể xem phản hồi của sinh viên khác. Vì vậy Forum Q&A không đáp ứng yêu cầu nghiêm ngặt **“không bao giờ thấy câu hỏi của nhau”**.

Khuyến nghị chọn một trong hai phương án:

- **Riêng tư tuyệt đối — khuyến nghị theo yêu cầu hiện tại:** thay Forum Q&A bằng Assignment nộp văn bản trực tuyến. Chỉ sinh viên và giáo viên thấy bài nộp; giáo viên chọn câu hay để công bố sau nếu muốn.
- **Học hỏi đồng đẳng:** giữ Forum Q&A; sinh viên bắt buộc tự đặt câu hỏi trước, sau đó được đọc và thảo luận câu hỏi của lớp.

Đây là quyết định sư phạm cần chốt trước khi hoàn thiện course template. Plugin Testcase Exchange hiện quản lý testcase, chưa có workflow riêng cho đóng góp câu hỏi đọc hiểu.

## Tiêu chí nghiệm thu pilot

- [x] Ba Quiz CodeRunner Python chạy qua Jobe.
- [x] Hai sinh viên tạo các testcase khác nhau và gửi đóng góp.
- [x] Giáo viên duyệt được đóng góp.
- [x] Sinh viên không có quyền review/manage.
- [x] Sinh viên không thấy run/contribution của nhau trên dashboard.
- [x] Không phân phối testcase thưởng cho sinh viên khác.
- [x] Bài đọc và hoạt động đặt câu hỏi mở được tạo.
- [ ] Chốt Q&A “đăng trước, xem sau” hay Assignment “riêng tư tuyệt đối”.
- [ ] Thực hiện các cải thiện UX ưu tiên trước khi public rộng.

## Script tái tạo và chạy lại

- `scripts/setup-ai-pilot.php`: tạo khóa học, ba Quiz, bài đọc và chính sách pilot theo cách idempotent.
- `scripts/run-ai-pilot.php`: chạy kịch bản hai sinh viên, gửi/duyệt testcase, kiểm tra capability và phạm vi dữ liệu.

Hai script chỉ phục vụ môi trường Docker phát triển và không nằm trong gói phát hành plugin.
