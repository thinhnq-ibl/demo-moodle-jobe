# Đề xuất module đóng góp câu hỏi và testcase của sinh viên

**Trạng thái:** Tài liệu định hướng nghiên cứu và phát triển  
**Phiên bản:** 0.1  
**Ngày cập nhật:** 05/10/2026  
**Phạm vi:** Moodle, CodeRunner, Jobe và kho dữ liệu `testcase_store`

## 1. Tóm tắt ý tưởng

Module tạo một không gian để sinh viên chủ động:

- Đặt câu hỏi khi học một bài tập hoặc xem một video.
- Tự nghĩ ra nhiều testcase để khám phá hành vi của chương trình.
- Dự đoán kết quả trước khi chạy và đối chiếu với kết quả thực tế.
- Chia sẻ những câu hỏi hoặc testcase có giá trị mới cho cộng đồng lớp học.
- Học từ câu hỏi và testcase của bạn học sau khi đã tự suy nghĩ.

Mục tiêu chính không phải là thu thập càng nhiều nội dung càng tốt. Module cần giúp sinh viên hình thành hai thói quen:

1. Gặp vấn đề thì biết đặt câu hỏi để tiếp tục tìm hiểu.
2. Viết chương trình thì chủ động kiểm thử, đặc biệt là các trường hợp biên và trường hợp dễ gây lỗi.

## 2. Vấn đề cần giải quyết

Trong quá trình học, sinh viên thường có các hành vi sau:

- Chỉ chạy chương trình với dữ liệu mẫu trong đề bài.
- Chờ hệ thống chấm đúng hoặc sai thay vì tự kiểm tra giả thuyết.
- Không biết bắt đầu đặt câu hỏi từ đâu khi chưa hiểu.
- Ngại hỏi vì sợ câu hỏi đơn giản hoặc đã có người hỏi.
- Tạo nhiều testcase khác giá trị nhưng giống nhau về bản chất để đạt chỉ tiêu.
- Sao chép testcase của bạn học mà không hiểu mục tiêu kiểm thử.

Module cần chuyển sinh viên từ cách học thụ động sang chu trình quan sát, dự đoán, thử nghiệm, giải thích và điều chỉnh.

## 3. Mục tiêu giáo dục

### 3.1. Mục tiêu chính

- Tăng tần suất sinh viên tự đặt câu hỏi có ngữ cảnh.
- Tăng số nhóm tình huống mà sinh viên chủ động kiểm thử.
- Giúp sinh viên hiểu rằng testcase là một giả thuyết về hành vi của chương trình.
- Khuyến khích sinh viên giải thích tại sao một testcase cần thiết.
- Tạo cơ hội học tập từ đóng góp của bạn học mà không làm mất quá trình tự khám phá.

### 3.2. Mục tiêu phụ

- Giúp giảng viên phát hiện phần kiến thức gây khó hiểu.
- Xây dựng dần ngân hàng câu hỏi và testcase của môn học.
- Phát hiện các trường hợp mà đề bài hoặc bộ kiểm thử chính thức chưa bao phủ.
- Cung cấp dữ liệu để đánh giá quá trình học, không chỉ kết quả cuối cùng.

### 3.3. Những điều module không nên trở thành

- Một cuộc thi nhập thật nhiều testcase.
- Một diễn đàn hỏi đáp không gắn với nội dung học.
- Một cách làm lộ hidden test hoặc lời giải chuẩn.
- Một cơ chế tự động đưa mọi đóng góp của sinh viên vào bộ chấm chính thức.
- Một bảng xếp hạng chỉ dựa trên số lượng.

## 4. Nguyên tắc thiết kế

### 4.1. Luật chung về tính khác biệt

> Mỗi đóng góp công khai hoặc được tính điểm phải bổ sung giá trị học tập mới, không chỉ khác nhau về câu chữ hoặc dữ liệu.

Luật này áp dụng cho cả câu hỏi và testcase, nhưng cách xác định khác nhau phải phù hợp với từng loại nội dung.

### 4.2. Tách thử nghiệm cá nhân và đóng góp cộng đồng

Sinh viên được phép thử riêng không giới hạn, kể cả khi testcase hoặc câu hỏi suy nghĩ của mình trùng với người khác. Chỉ khi sinh viên muốn công khai, trao đổi hoặc nhận điểm đóng góp thì hệ thống mới áp dụng luật khác biệt.

Sự tách biệt này rất quan trọng. Nếu chặn ngay mọi lần thử bị trùng, module có thể làm sinh viên ngại mày mò và ngại đặt câu hỏi.

### 4.3. Khuyến khích suy nghĩ trước khi xem đáp án

Đối với testcase, sinh viên nên nhập dự đoán trước khi hệ thống trả kết quả từ lời giải chuẩn. Dự đoán sai vẫn là một hoạt động học có giá trị và cần được lưu trong nhật ký cá nhân.

### 4.4. Phản hồi mang tính hướng dẫn

Khi phát hiện nội dung có khả năng trùng, hệ thống không chỉ báo lỗi. Hệ thống cần hiển thị đóng góp tương tự và hướng dẫn sinh viên:

- Theo dõi hoặc xác nhận “Tôi cũng gặp vấn đề này”.
- Bổ sung ví dụ mới vào câu hỏi cũ.
- Giải thích testcase mới kiểm tra hành vi nào khác.
- Tiếp tục lưu nội dung ở nhật ký cá nhân nếu không muốn công khai.

### 4.5. Chất lượng quan trọng hơn số lượng

Điểm, huy hiệu và bảng thống kê phải ưu tiên độ đa dạng, lập luận và giá trị phát hiện lỗi thay vì tổng số bản ghi.

## 5. Hai loại đóng góp chính

### 5.1. Câu hỏi của sinh viên

Câu hỏi có thể gắn với:

- Một khóa học.
- Một bài quiz hoặc bài tập.
- Một câu hỏi lập trình cụ thể.
- Một video hoặc một thời điểm trong video.
- Một lần chạy chương trình hoặc một testcase cụ thể.

Các nhóm câu hỏi đề xuất:

- Chưa hiểu khái niệm.
- Chưa hiểu yêu cầu đề bài.
- Chương trình chạy khác dự đoán.
- Muốn kiểm tra một giả thuyết.
- Muốn tìm cách giải khác.
- Phát hiện điểm chưa rõ hoặc có khả năng sai trong tài liệu.

Mẫu nhập câu hỏi ngắn gọn:

1. Em chưa hiểu điều gì?
2. Em đã thử hoặc đã quan sát điều gì?
3. Giả thuyết hiện tại của em là gì? Đây là trường không bắt buộc ở giai đoạn đầu.
4. Câu hỏi liên quan đến bài tập, đoạn code hoặc thời điểm video nào?

Sau khi câu hỏi được giải đáp, module có thể mời sinh viên ghi một câu ngắn: “Điều em hiểu thêm là gì?”.

### 5.2. Testcase của sinh viên

Một lần thử testcase nên đi qua chu trình:

```text
Chọn dữ liệu đầu vào
        ↓
Dự đoán kết quả
        ↓
Chạy chương trình của sinh viên trên Jobe
        ↓
So sánh thực tế với dự đoán
        ↓
Đối chiếu với lời giải chuẩn khi phù hợp
        ↓
Ghi lại điều phát hiện được
        ↓
Quyết định giữ riêng hoặc đề xuất chia sẻ
```

Thông tin đề xuất cho một testcase:

- Input.
- Output dự đoán của sinh viên.
- Output thực tế từ chương trình của sinh viên.
- Output tham chiếu từ lời giải chuẩn, nếu chính sách cho phép hiển thị.
- Mục đích kiểm thử.
- Nhóm testcase.
- Điều sinh viên phát hiện sau khi chạy.
- Trạng thái riêng tư, đề xuất công khai, được duyệt hoặc bị xem là trùng.

Các nhóm testcase ban đầu:

- Trường hợp thông thường.
- Giá trị biên.
- Dữ liệu rỗng hoặc thiếu.
- Dữ liệu không hợp lệ.
- Số âm, số không hoặc giá trị đặc biệt.
- Dữ liệu rất lớn hoặc kiểm thử hiệu năng.
- Trường hợp lặp hoặc trùng dữ liệu.
- Trường hợp liên quan đến một nhánh logic cụ thể.
- Nhóm khác do giảng viên định nghĩa theo từng bài.

## 6. Định nghĩa “khác nhau”

### 6.1. Phạm vi so sánh

Mọi phép kiểm tra trùng cần được giới hạn trong đúng ngữ cảnh. Kiến trúc hiện tại sử dụng ba tầng:

```text
course_id → quiz_id → question_id
```

Đối với video, có thể dùng cấu trúc tương đương:

```text
course_id → activity_id/video_id → timestamp hoặc segment_id
```

Hai đóng góp ở hai bài khác nhau không nên bị xem là trùng chỉ vì có cùng nội dung.

### 6.2. Câu hỏi khác nhau

Hai câu hỏi được xem là trùng khi chúng cùng yêu cầu giải thích một vấn đề trong cùng ngữ cảnh, dù cách viết khác nhau.

Ví dụ gần như trùng:

- “Tại sao vòng lặp này không dừng?”
- “Vì sao chương trình chạy mãi ở vòng `while`?”

Hai câu hỏi có thể được xem là khác nếu:

- Hỏi về hai nguyên nhân hoặc khái niệm khác nhau.
- Có cùng chủ đề nhưng ngữ cảnh lỗi khác nhau.
- Câu sau cung cấp ví dụ phản chứng hoặc tình huống mới làm thay đổi cách trả lời.

Khi trùng, hệ thống nên chuyển hành động từ “tạo câu hỏi mới” sang:

- “Tôi cũng gặp vấn đề này”.
- Theo dõi câu hỏi.
- Bổ sung ví dụ hoặc bình luận.

Số lượt gặp cùng vấn đề là tín hiệu quan trọng cho giảng viên và không nên bị mất.

### 6.3. Testcase khác nhau

Testcase khác input chưa chắc khác về giá trị kiểm thử.

Ví dụ với bài kiểm tra số nguyên dương:

- `5`, `6`, `7` khác dữ liệu nhưng có thể cùng thuộc nhóm dữ liệu thông thường.
- `0` kiểm tra giá trị biên.
- `-1` kiểm tra số âm.
- `2147483647` kiểm tra giới hạn kiểu số nguyên hoặc hiệu năng.

Một testcase công khai được xem là khác có ý nghĩa nếu thỏa ít nhất một điều kiện:

- Kiểm tra một lớp tương đương mới.
- Chạm một giá trị biên mới.
- Đi qua một nhánh logic chưa được kiểm tra.
- Gây ra một hành vi hoặc loại lỗi khác.
- Kiểm tra một thuộc tính khác của đầu ra.
- Cung cấp phản ví dụ cho một giả thuyết đang tồn tại.

### 6.4. Ba mức kiểm tra trùng

1. **Trùng chính xác:** cùng dữ liệu chuẩn hóa hoặc cùng dấu vân tay; hệ thống có thể xử lý tự động.
2. **Có khả năng trùng:** nội dung gần giống, cùng nhóm hoặc cùng mục đích; hệ thống cảnh báo và yêu cầu giải thích điểm khác biệt.
3. **Khác có ý nghĩa:** có mục tiêu hoặc hành vi kiểm thử mới; hệ thống cho phép đề xuất công khai.

Ở giai đoạn đầu, chỉ nên tự động kết luận mức 1. Mức 2 cần cho sinh viên giải thích và có thể cần giảng viên hoặc người đánh giá xác nhận.

## 7. Luồng sử dụng đề xuất

### 7.1. Luồng đặt câu hỏi

1. Sinh viên đang xem video hoặc làm bài tập.
2. Sinh viên chọn “Đặt câu hỏi”.
3. Hệ thống tự gắn ngữ cảnh bài học, câu hỏi hoặc timestamp.
4. Khi sinh viên nhập nội dung, hệ thống hiển thị các câu hỏi tương tự.
5. Sinh viên chọn theo dõi câu có sẵn, bổ sung ví dụ hoặc tiếp tục gửi câu mới kèm lý do khác biệt.
6. Câu hỏi được trả lời bởi bạn học, trợ giảng hoặc giảng viên.
7. Sinh viên xác nhận câu trả lời hữu ích và có thể ghi lại điều đã hiểu.

### 7.2. Luồng thử testcase cá nhân

1. Sinh viên chọn bài lập trình.
2. Nhập input, dự đoán output và chọn mục đích kiểm thử.
3. Hệ thống chạy bài làm của sinh viên trên Jobe.
4. Hệ thống hiển thị kết quả thực tế và so sánh với dự đoán.
5. Khi phù hợp, hệ thống chạy lời giải chuẩn để thẩm định.
6. Dù dự đoán đúng hay sai, lần thử vẫn được lưu trong nhật ký cá nhân.
7. Sinh viên ghi chú điều phát hiện được hoặc sửa chương trình rồi chạy lại.

### 7.3. Luồng đề xuất testcase cho cộng đồng

1. Từ nhật ký cá nhân, sinh viên chọn “Đề xuất chia sẻ”.
2. Hệ thống kiểm tra tính hợp lệ và trùng chính xác.
3. Hệ thống tìm các testcase có khả năng tương tự.
4. Nếu có cảnh báo, sinh viên mô tả điểm khác biệt.
5. Testcase chuyển sang trạng thái chờ duyệt hoặc được chấp nhận tự động theo chính sách của bài.
6. Testcase được mở cho bạn học theo thời điểm hoặc điều kiện do giảng viên cấu hình.

### 7.4. Chính sách mở khóa testcase cộng đồng

Có thể hỗ trợ một hoặc nhiều chính sách:

- Chỉ mở sau khi sinh viên đã tự thử một số testcase.
- Đóng góp một testcase hợp lệ để mở một testcase mới.
- Mở sau deadline.
- Mở theo nhóm testcase, không hiển thị toàn bộ kho ngay lập tức.
- Giảng viên mở thủ công.

## 8. Trạng thái nội dung

Trạng thái gợi ý cho câu hỏi và testcase:

- `private`: chỉ sinh viên tạo nội dung nhìn thấy.
- `draft`: đang hoàn thiện.
- `submitted`: đã đề xuất chia sẻ.
- `needs_explanation`: có khả năng trùng, cần giải thích thêm.
- `approved`: được công nhận là đóng góp mới.
- `duplicate`: trùng đóng góp khác và có liên kết `duplicate_of`.
- `rejected`: không hợp lệ, có lý do.
- `archived`: ngừng hiển thị nhưng vẫn giữ lịch sử.

Không nên xóa bản ghi trùng khỏi lịch sử học tập cá nhân. Có thể không tính điểm đóng góp nhưng vẫn ghi nhận sinh viên đã thử hoặc đã gặp vấn đề đó.

## 9. Kiểm tra tính hợp lệ và chống trùng

### 9.1. Chuẩn hóa testcase

Tùy loại bài, hệ thống có thể:

- Chuẩn hóa xuống dòng và khoảng trắng cuối dòng.
- Chuẩn hóa định dạng JSON nhưng không thay đổi ý nghĩa của chuỗi thông thường.
- Chuẩn hóa cách biểu diễn số nếu đề bài quy định rõ.
- Tạo `input_fingerprint` từ nội dung đã chuẩn hóa.
- Đặt ràng buộc duy nhất theo `course_id`, `quiz_id`, `question_id` và `input_fingerprint`.

Việc chuẩn hóa phải được cấu hình theo loại bài. Không được tự ý bỏ mọi khoảng trắng vì với một số bài, khoảng trắng là dữ liệu có ý nghĩa.

### 9.2. Chuẩn hóa câu hỏi

Giai đoạn đầu có thể:

- Chuyển chữ thường.
- Loại khoảng trắng thừa.
- Chuẩn hóa một số dấu câu.
- Tìm kiếm các từ khóa và câu hỏi gần giống.

Phát hiện tương đồng ngữ nghĩa chỉ nên dùng để gợi ý, không nên tự động từ chối khi chưa có độ tin cậy và cơ chế giải trình phù hợp.

### 9.3. Xử lý đồng thời

Kiểm tra trùng trong mã ứng dụng là chưa đủ vì hai sinh viên có thể gửi cùng lúc. Cơ sở dữ liệu cần có unique index theo phạm vi đóng góp. Ứng dụng phải bắt lỗi xung đột và chuyển đóng góp đến nội dung đã được ghi trước.

### 9.4. Thẩm định bằng lời giải chuẩn

Lời giải chuẩn trên Jobe có thể tạo output tham chiếu. Tuy nhiên cần tách rõ:

- `predicted_output`: dự đoán ban đầu của sinh viên.
- `student_run_output`: kết quả từ chương trình của sinh viên.
- `oracle_output`: kết quả từ lời giải chuẩn.

Dự đoán sai không nên làm mất nhật ký thử nghiệm. Nó chỉ có thể khiến testcase chưa đủ điều kiện để công khai cho đến khi được sửa hoặc giải thích.

## 10. Ghi nhận, điểm và động lực

### 10.1. Không tính điểm chỉ theo số lượng

Công thức chỉ dựa trên số testcase sẽ khuyến khích spam các giá trị tương tự. Điểm đóng góp nên xét:

- Testcase thuộc nhóm mới.
- Mục đích kiểm thử được giải thích rõ.
- Testcase phát hiện lỗi thực tế.
- Testcase được bạn học đánh dấu hữu ích.
- Sinh viên sửa lại dự đoán hoặc lập luận sau phản hồi.
- Sinh viên duy trì hoạt động qua nhiều tuần.

### 10.2. Ví dụ điểm chất lượng

Một mô hình đơn giản để thử nghiệm:

| Thành phần | Điểm gợi ý |
| --- | ---: |
| Testcase hợp lệ và không trùng chính xác | 1 |
| Thuộc nhóm kiểm thử chưa có | +1 |
| Có mô tả mục đích rõ ràng | +1 |
| Phát hiện được ít nhất một bài làm sai | +2 |
| Được giảng viên duyệt vào ngân hàng tham khảo | +2 |
| Bị xác định là spam hoặc cố tình đổi hình thức | 0 và cảnh báo |

Đây chỉ là giả thuyết ban đầu. Cần thử nghiệm thực tế trước khi dùng điểm này vào điểm môn học.

### 10.3. Huy hiệu gợi ý

- Thợ săn trường hợp biên.
- Người kiểm chứng giả thuyết.
- Câu hỏi hữu ích.
- Phát hiện lỗi đầu tiên.
- Đã thử nhiều chiến lược kiểm thử.
- Người giải thích rõ ràng.

### 10.4. Bảng xếp hạng

Nếu sử dụng bảng xếp hạng, nên hiển thị nhiều tiêu chí thay vì một cột tổng số:

- Số nhóm testcase khác nhau.
- Số testcase hữu ích.
- Số câu hỏi được cộng đồng quan tâm.
- Số lần phản hồi hoặc cải tiến đóng góp.
- Chuỗi tuần tham gia liên tục.

Giảng viên cần có tùy chọn tắt bảng xếp hạng hoặc chỉ cho sinh viên xem tiến độ của chính mình.

## 11. Dashboard cho giảng viên

Dashboard nên trả lời được các câu hỏi:

- Phần video hoặc bài tập nào phát sinh nhiều câu hỏi nhất?
- Những câu hỏi nào có nhiều sinh viên cùng gặp?
- Sinh viên đang thử những nhóm testcase nào và đang bỏ sót nhóm nào?
- Bao nhiêu sinh viên chỉ dùng dữ liệu mẫu?
- Testcase nào phát hiện được nhiều bài làm sai?
- Sinh viên có sửa chương trình sau một lần chạy thất bại hay không?
- Có hiện tượng spam, đổi cách viết hoặc tạo dữ liệu gần giống để lấy điểm không?

Các chỉ số nên ưu tiên:

- Tỷ lệ sinh viên có ít nhất một câu hỏi theo tuần.
- Tỷ lệ câu hỏi có ngữ cảnh và mô tả điều đã thử.
- Số nhóm testcase trung bình trên mỗi sinh viên.
- Tỷ lệ sinh viên dự đoán trước khi chạy.
- Số lỗi được phát hiện trước lần nộp chính thức.
- Tỷ lệ đóng góp được đánh giá hữu ích.
- Tỷ lệ nội dung trùng chính xác và gần trùng.

## 12. Mô hình dữ liệu định hướng

Phần này là gợi ý để nghiên cứu, chưa phải migration chính thức.

### 12.1. Mở rộng `student_testcases`

Các trường có thể bổ sung:

- `quiz_id` để duy trì phân tầng `course_id → quiz_id → question_id`.
- `normalized_input`.
- `input_fingerprint`.
- `predicted_output`.
- `student_run_output`.
- `oracle_output` hoặc tiếp tục dùng `expected_output` với tên được chuẩn hóa rõ nghĩa.
- `purpose`.
- `category`.
- `reflection`.
- `visibility`.
- `status`.
- `duplicate_of`.
- `reviewed_by`, `reviewed_at`, `review_note`.
- `created_at`, `updated_at`.

### 12.2. Bảng `student_inquiries`

Tên `student_inquiries` giúp tránh nhầm với bảng câu hỏi của Moodle.

Các trường chính:

- `id`.
- `course_id`.
- `activity_type`: `quiz`, `assignment`, `video` hoặc loại khác.
- `activity_id`.
- `question_id` nếu gắn với câu hỏi CodeRunner.
- `video_timestamp` hoặc `segment_id` nếu gắn với video.
- `student_id`.
- `title`.
- `content`.
- `normalized_content`.
- `category`.
- `status`.
- `duplicate_of`.
- `created_at`, `updated_at`.

### 12.3. Các bảng hỗ trợ

- `inquiry_reactions`: lưu “Tôi cũng gặp vấn đề này”, theo dõi và đánh giá hữu ích.
- `inquiry_responses`: câu trả lời và phản hồi.
- `contribution_reviews`: lịch sử duyệt, lý do trùng hoặc từ chối.
- `testcase_runs`: nhật ký mỗi lần chạy trên Jobe.
- `testcase_equivalence_groups`: nhóm các testcase cùng mục tiêu hoặc lớp tương đương.
- `student_reflections`: ghi nhận điều sinh viên học được sau câu hỏi hoặc lần thử.

## 13. Quyền, riêng tư và an toàn

- Sinh viên chỉ được xem thử nghiệm riêng của mình.
- Nội dung công khai phải tuân theo chính sách của từng hoạt động.
- Có thể ẩn danh tác giả với bạn học nhưng vẫn lưu danh tính cho giảng viên.
- Giới hạn kích thước input, số lần chạy và tài nguyên Jobe.
- Không để dữ liệu testcase trở thành mã thực thi ngoài sandbox.
- Không hiển thị lời giải chuẩn hoặc thông tin nội bộ của Jobe.
- Ghi log thao tác duyệt và thay đổi trạng thái.
- Không tự động thay đổi bộ chấm chính thức khi chưa có quyền và bước duyệt phù hợp.

## 14. Liên hệ với hệ thống hiện tại

Hệ thống hiện có các nền tảng thuận lợi:

- Plugin `local_testcase_exchange`.
- Kho MariaDB riêng `testcase_store`.
- Phân tầng `course_id → quiz_id → question_id`.
- Cụm Jobe song song `jobe1` và `jobe2`.
- Đối chiếu output bằng lời giải chuẩn.
- Kiểm tra trùng input chính xác.
- Cơ chế nộp một testcase và nhận một testcase mới.
- Dashboard và bảng xếp hạng ban đầu.

Các khoảng trống cần phát triển:

- Chưa có không gian thử testcase cá nhân trước khi công khai.
- Chưa tách dự đoán, kết quả chương trình sinh viên và kết quả lời giải chuẩn.
- Chưa lưu mục đích, nhóm testcase và điều sinh viên học được.
- Kiểm tra trùng mới chủ yếu dựa trên input chính xác.
- Chưa có chức năng đóng góp câu hỏi cho bài tập hoặc video.
- Bảng xếp hạng đang thiên về số lượng hơn chất lượng.
- Chưa có luồng duyệt và giải trình cho trường hợp gần trùng.

Một lưu ý quan trọng: testcase của sinh viên không nên tự động đi thẳng vào bộ chấm chính thức đang áp dụng cho cả lớp. Cách an toàn hơn là đưa vào ngân hàng ứng viên, cho giảng viên duyệt, tạo phiên bản bộ test mới và áp dụng theo một thời điểm rõ ràng. Điều này tránh thay đổi tiêu chí chấm giữa lúc sinh viên đang làm bài.

## 15. Phạm vi MVP đề xuất

### Giai đoạn 1: Nhật ký khám phá testcase

- Cho phép chạy testcase trên bài làm của chính sinh viên.
- Lưu input, dự đoán, kết quả chạy và ghi chú.
- Cho phép phân loại testcase.
- Không bắt buộc công khai.
- Tách lần thử sai khỏi đóng góp hợp lệ.

**Kết quả mong đợi:** Sinh viên bắt đầu thử nhiều tình huống hơn mà không sợ lần thử sai bị từ chối.

### Giai đoạn 2: Đề xuất và kiểm tra trùng chính xác

- Cho phép đề xuất một testcase từ nhật ký cá nhân.
- Chuẩn hóa input theo loại bài.
- Tạo fingerprint và unique index theo ba tầng.
- Hiển thị testcase tương tự trước khi gửi.
- Thêm trạng thái duyệt cơ bản.

**Kết quả mong đợi:** Kho công khai không chứa bản ghi trùng chính xác và mỗi testcase có mục đích rõ ràng.

### Giai đoạn 3: Câu hỏi theo ngữ cảnh

- Đặt câu hỏi theo bài tập, câu hỏi CodeRunner hoặc timestamp video.
- Gợi ý câu hỏi tương tự.
- Cho phép “Tôi cũng gặp vấn đề này”.
- Có luồng trả lời và xác nhận hữu ích.

**Kết quả mong đợi:** Giảng viên thấy được điểm nghẽn học tập và sinh viên hình thành thói quen hỏi có ngữ cảnh.

### Giai đoạn 4: Chất lượng và tương đồng ngữ nghĩa

- Nhóm testcase theo lớp tương đương hoặc mục tiêu kiểm thử.
- Cảnh báo testcase và câu hỏi gần trùng.
- Chấm chất lượng đóng góp.
- Bổ sung dashboard về độ đa dạng và tác động.

**Kết quả mong đợi:** Giảm spam và chuyển động lực từ số lượng sang giá trị học tập.

### Giai đoạn 5: Nghiên cứu và đánh giá hiệu quả

- Thử nghiệm với một hoặc hai lớp học.
- So sánh hành vi trước và sau khi dùng module.
- Phỏng vấn sinh viên và giảng viên.
- Điều chỉnh điểm, huy hiệu và chính sách mở khóa.

## 16. Tiêu chí nghiệm thu ban đầu

MVP có thể được xem là đạt khi:

- Sinh viên chạy được testcase riêng mà không bắt buộc công khai.
- Mỗi lần thử lưu được dự đoán và kết quả thực tế.
- Testcase trùng chính xác không được tính là đóng góp công khai mới.
- Testcase gần trùng có thể được giải thích thay vì bị từ chối ngay.
- Câu hỏi trùng có thể chuyển thành lượt “Tôi cũng gặp vấn đề này”.
- Giảng viên xem được các nhóm câu hỏi và testcase phổ biến.
- Testcase sinh viên không tự động thay đổi bộ chấm chính thức khi chưa duyệt.
- Hệ thống xử lý an toàn hai lượt gửi đồng thời bằng ràng buộc cơ sở dữ liệu.

## 17. Câu hỏi nghiên cứu còn mở

- “Khác có ý nghĩa” nên do hệ thống, sinh viên hay giảng viên quyết định ở từng giai đoạn?
- Nên công khai output tham chiếu ngay hay sau một số lần thử?
- Chính sách “đóng góp một, mở khóa một” có thực sự tăng học tập hay chỉ tăng số lượng?
- Sinh viên có ngại nhập mục đích và ghi chú nếu form quá dài không?
- Nên tính điểm vào môn học hay chỉ dùng huy hiệu và phản hồi quá trình?
- Khi nào testcase được phép trở thành testcase chính thức?
- Làm thế nào đo được testcase đã phát hiện một loại lỗi mới thay vì chỉ một input mới?
- Nên mở kho testcase trước hay sau deadline để cân bằng cộng tác và công bằng?
- Với video, câu hỏi nên gắn theo timestamp chính xác hay theo đoạn nội dung do giảng viên đánh dấu?

## 18. Đề xuất bước tiếp theo

Kế hoạch thực thi, mô hình dữ liệu, work package và tiêu chí nghiệm thu chi tiết được quản lý tại [Kế hoạch triển khai tính năng đóng góp testcase](./ke-hoach-trien-khai-dong-gop-testcase.md).

1. Chốt phạm vi của giai đoạn 1: chỉ bài lập trình hay gồm cả video.
2. Thiết kế wireframe cho nhật ký testcase cá nhân và form đặt câu hỏi.
3. Chuẩn hóa mô hình dữ liệu hiện có của `testcase_store`.
4. Xây dựng quy tắc chuẩn hóa input cho từng kiểu bài.
5. Thêm unique index theo `course_id → quiz_id → question_id` và fingerprint.
6. Tách ba loại output: dự đoán, kết quả bài làm và kết quả tham chiếu.
7. Thử nghiệm với một bài CodeRunner trước khi áp dụng toàn khóa học.
8. Thu thập chỉ số hành vi và phản hồi để điều chỉnh luật tính điểm.

---

Tài liệu này nên được cập nhật như một tài liệu sống. Mỗi quyết định triển khai cần ghi rõ giả thuyết giáo dục, thay đổi kỹ thuật, dữ liệu đo lường và kết quả sau thử nghiệm.
