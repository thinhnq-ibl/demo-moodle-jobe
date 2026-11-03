<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Vietnamese language strings.
 *
 * @package    local_testcase_exchange
 * @copyright  2026 Nguyen Quoc Thinh
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Trao đổi Testcase CodeRunner';
$string['testcase_exchange:view'] = 'Xem bảng xếp hạng và trao đổi testcase';
$string['testcase_exchange:run'] = 'Chạy thử testcase riêng tư';
$string['testcase_exchange:contribute'] = 'Đóng góp testcase';
$string['testcase_exchange:review'] = 'Duyệt testcase đóng góp';
$string['testcase_exchange:manage'] = 'Quản lý kho dữ liệu testcase';
$string['heading_dashboard'] = 'Bảng Thống Kê & Xếp Hạng Đóng Góp Testcase';
$string['settings_db_heading'] = 'Cơ sở dữ liệu testcase';
$string['settings_db_heading_desc'] = 'Kết nối được module sử dụng để đọc và ghi dữ liệu testcase.';
$string['settings_db_host'] = 'Máy chủ MariaDB';
$string['settings_db_host_desc'] = 'Tên host của cơ sở dữ liệu, không bao gồm giao thức hoặc cổng.';
$string['settings_db_port'] = 'Cổng cơ sở dữ liệu';
$string['settings_db_port_desc'] = 'Cổng TCP của MariaDB/MySQL.';
$string['settings_db_user'] = 'Tài khoản DB';
$string['settings_db_user_desc'] = 'Dùng tài khoản riêng. Migration schema cần thêm CREATE, ALTER và INDEX; vận hành thường cần SELECT, INSERT, UPDATE và DELETE.';
$string['settings_db_pass'] = 'Mật khẩu DB';
$string['settings_db_pass_desc'] = 'Mật khẩu của tài khoản cơ sở dữ liệu testcase.';
$string['settings_db_name'] = 'Tên DB';
$string['settings_db_name_desc'] = 'Tên cơ sở dữ liệu testcase độc lập.';
$string['settings_jobe_heading'] = 'Jobe runner';
$string['settings_jobe_heading_desc'] = 'Các endpoint được module dùng để kiểm tra trạng thái. Việc thực thi dùng cấu hình Jobe của CodeRunner, vì vậy hai cấu hình phải trỏ cùng cluster.';
$string['settings_jobe_servers'] = 'URL Jobe server';
$string['settings_jobe_servers_desc'] = 'Nhập mỗi server trên một dòng hoặc phân cách bằng dấu chấm phẩy. Để trống để kiểm tra jobe_host của CodeRunner. Module tự thêm đường dẫn REST.';
$string['settings_jobe_api_key'] = 'Jobe API key';
$string['settings_jobe_api_key_desc'] = 'API key tùy chọn cho health check. Hãy cấu hình credential tương ứng trong CodeRunner để thực thi.';
$string['nav_testcase_bank'] = 'Kho Testcase & Đổi thưởng';
$string['my_testcases'] = 'Testcase Của Tôi';
$string['received_testcases'] = 'Testcase Được Tặng';
$string['privateexploration'] = 'Nhật ký khám phá testcase riêng tư';
$string['nocoderunnerquestions'] = 'Khóa học chưa có câu hỏi CodeRunner trong Quiz.';
$string['selectquestion'] = 'Quiz và câu hỏi CodeRunner';
$string['studentsource'] = 'Mã nguồn hiện tại của bạn';
$string['testinput'] = 'Input / biểu thức kiểm thử';
$string['predictedoutput'] = 'Kết quả mong đợi';
$string['studentoutput'] = 'Kết quả code sinh viên';
$string['oracleoutput'] = 'Kết quả tham chiếu';
$string['purpose'] = 'Bạn muốn kiểm tra hành vi nào?';
$string['reflection'] = 'Bạn đã phát hiện hoặc học được gì?';
$string['category'] = 'Nhóm testcase';
$string['category_normal'] = 'Normal case (Trường hợp thông thường)';
$string['category_boundary'] = 'Boundary value (Giá trị biên / cận)';
$string['category_empty'] = 'Empty or missing data (Dữ liệu rỗng / thiếu)';
$string['category_invalid'] = 'Invalid data (Dữ liệu không hợp lệ / ngoại lệ)';
$string['category_large'] = 'Large input / performance (Dữ liệu lớn / hiệu năng)';
$string['category_branch'] = 'Logic branch (Nhánh logic / rẽ nhánh)';
$string['category_other'] = 'Other (Trường hợp khác)';
$string['runprivate'] = 'Chạy thử riêng';
$string['myruns'] = 'Lần thử riêng của tôi';
$string['mycontributions'] = 'Đóng góp của tôi';
$string['myrewards'] = 'Testcase đã mở khóa';
$string['proposesharing'] = 'Đề xuất chia sẻ';
$string['managepolicies'] = 'Cấu hình chính sách testcase';
$string['configuretestcasecontributions'] = 'Cấu hình đóng góp testcase';
$string['testcaseexchangeheading'] = 'Đóng góp testcase';
$string['enabletestcasecontributions'] = 'Cho phép đóng góp testcase';
$string['enabletestcasecontributions_help'] = 'Sinh viên có thể chạy testcase riêng tư và gửi các testcase chạy thành công để giáo viên duyệt. Các thiết lập nâng cao về quyền riêng tư, duyệt và kiểu đầu vào nằm trong liên kết cấu hình testcase của Quiz.';
$string['contributetestcaseafterquiz'] = 'Đóng góp testcase';
$string['contributetestcaseafterquizintro'] = 'Code vừa submit sẽ được nạp sẵn để bạn chỉnh sửa và khám phá nhiều testcase.';
$string['codeprefilledfromattempt'] = 'Code submit gần nhất đã được nạp. Bạn có thể chỉnh sửa và chạy nhiều testcase riêng tư.';
$string['backtocurrentquiz'] = 'Quay lại Quiz đang làm';
$string['submitcodebeforetesting'] = 'Hãy submit code bằng nút Check trong Quiz, sau đó dùng nút Đóng góp testcase của câu hỏi.';
$string['contributingtestcasefor'] = 'Bạn đang đóng góp testcase cho:';
$string['currentquiz'] = 'Bài kiểm tra';
$string['currentquestion'] = 'Câu hỏi';
$string['reviewcontributions'] = 'Duyệt testcase đóng góp';
$string['backtodashboard'] = 'Quay lại trang testcase';
$string['runcreatedmatch'] = 'Đã lưu lần thử riêng. Dự đoán của bạn khớp kết quả tham chiếu.';
$string['runcreatedmismatch'] = 'Đã lưu lần thử riêng. Hãy xem lại khác biệt trước khi đề xuất chia sẻ.';
$string['runcreatedoraclefailed'] = 'Đã lưu lần thử riêng. Ca kiểm thử gây lỗi khi chạy với nghiệm chuẩn (Oracle): {$a}. Không thể đề xuất chia sẻ testcase này.';
$string['runcreatedstudenterror'] = 'Đã lưu lần thử riêng. Mã nguồn của bạn gặp lỗi với testcase này trong khi nghiệm chuẩn chạy thành công.';
$string['runcreatedsaved'] = 'Đã lưu lần thử riêng và ghi nhận kết quả từ nghiệm chuẩn.';
$string['contributionstatus'] = 'Trạng thái đóng góp: {$a}';
$string['databaseunavailable'] = 'Không thể kết nối kho testcase. Vui lòng liên hệ quản trị viên.';
$string['unexpectederror'] = 'Không thể hoàn tất thao tác. Vui lòng thử lại hoặc liên hệ quản trị viên.';
$string['featuredisabled'] = 'Tính năng khám phá testcase đang tắt cho Quiz này.';
$string['inputtoolarge'] = 'Input vượt quá giới hạn kích thước đã cấu hình.';
$string['ratelimited'] = 'Bạn chạy quá nhiều lần. Vui lòng chờ một phút rồi thử lại.';
$string['invalidjsoninput'] = 'Input không phải JSON hợp lệ theo chính sách của câu hỏi.';
$string['invalidcontext'] = 'Câu hỏi đã chọn không thuộc khóa học và Quiz này.';
$string['notcoderunner'] = 'Câu hỏi đã chọn không phải câu hỏi CodeRunner.';
$string['missingoracle'] = 'Câu hỏi chưa có lời giải tham chiếu của giảng viên.';
$string['invalidtestcodetemplate'] = 'Chế độ template yêu cầu test-code template chứa {{INPUT}}.';
$string['oraclenotsuccessful'] = 'Không thể chia sẻ lần thử vì lượt chạy tham chiếu chưa thành công.';
$string['invalidrun'] = 'Lần thử không tồn tại hoặc không thuộc về bạn.';
$string['invalidstatus'] = 'Trạng thái đóng góp không hợp lệ.';
$string['invalidtransition'] = 'Không được phép chuyển sang trạng thái này.';
$string['invalidcontribution'] = 'Đóng góp không tồn tại trong khóa học này.';
$string['reviewnote'] = 'Ghi chú duyệt';
$string['needsexplanation'] = 'Cần giải thích';
$string['approvecontribution'] = 'Duyệt';
$string['rejectcontribution'] = 'Từ chối';
$string['reviewsaved'] = 'Đã lưu quyết định duyệt.';
$string['nocontributionspending'] = 'Không có đóng góp nào đang chờ duyệt.';
$string['policysaved'] = 'Đã lưu chính sách Quiz và câu hỏi.';
$string['enablefeature'] = 'Bật khám phá và đóng góp testcase';
$string['showoracle'] = 'Hiển thị kết quả tham chiếu cho sinh viên';
$string['enableleaderboard'] = 'Bật bảng xếp hạng';
$string['explanationrequired'] = 'Vui lòng giải thích testcase này bổ sung giá trị khác biệt nào.';
$string['explanationsaved'] = 'Đã lưu giải thích và gửi lại đóng góp để duyệt.';
$string['resubmitexplanation'] = 'Gửi lại giải thích';
$string['quizpolicy'] = 'Chính sách Quiz';
$string['questionpolicy'] = 'Chính sách câu hỏi';
$string['reviewmode'] = 'Chế độ duyệt';
$string['reviewmode_teacher'] = 'Giảng viên duyệt';
$string['reviewmode_auto'] = 'Tự động duyệt';
$string['rewardpolicy'] = 'Chính sách trao thưởng';
$string['rewardpolicy_oneforone'] = 'Một đóng góp được duyệt mở khóa một testcase';
$string['runsperminute'] = 'Số lượt chạy mỗi phút';
$string['maxinputbytes'] = 'Kích thước input tối đa theo byte';
$string['inputmode'] = 'Chế độ input';
$string['testcodetemplate'] = 'Template test-code chứa {{INPUT}}';
$string['normalization'] = 'Chuẩn hóa input';
$string['categories'] = 'Các nhóm được phép';
$string['userid'] = 'ID người dùng';
$string['quizquestion'] = 'Quiz / câu hỏi';
$string['review'] = 'Duyệt';
$string['healthcheck'] = 'Trạng thái dịch vụ testcase';
$string['databasehealth'] = 'Cơ sở dữ liệu ngoài';
$string['jobehealth'] = 'Các Jobe node';
$string['schemaversion'] = 'Đã kết nối; phiên bản schema {$a}.';
$string['server'] = 'Máy chủ';
$string['languages'] = 'Ngôn ngữ khả dụng';
$string['available'] = 'Sẵn sàng';
$string['unavailable'] = 'Không sẵn sàng';
$string['privacy:path'] = 'Trao đổi testcase';
$string['privacy:metadata:runs'] = 'Các lượt chạy thử nghiệm testcase riêng của sinh viên.';
$string['privacy:metadata:runs:user_id'] = 'ID người dùng Moodle của sinh viên đã thực hiện lượt chạy.';
$string['privacy:metadata:runs:input_raw'] = 'Dữ liệu đầu vào thô dùng để chạy thử nghiệm testcase.';
$string['privacy:metadata:runs:predicted_output'] = 'Kết quả đầu ra do sinh viên tự dự đoán.';
$string['privacy:metadata:runs:student_run_output'] = 'Kết quả đầu ra thu được khi chạy chương trình của sinh viên.';
$string['privacy:metadata:runs:oracle_output'] = 'Kết quả đầu ra thu được khi chạy lời giải tham chiếu (oracle).';
$string['privacy:metadata:runs:student_code_snapshot'] = 'Ảnh chụp mã nguồn sinh viên tại thời điểm chạy thử nghiệm.';
$string['privacy:metadata:runs:reflection'] = 'Ghi chú hoặc phản ánh của sinh viên về testcase.';
$string['privacy:metadata:runs:timecreated'] = 'Thời điểm thực hiện lượt chạy thử nghiệm.';
$string['privacy:metadata:contributions'] = 'Testcase do sinh viên đóng góp để trao đổi và chấm điểm.';
$string['privacy:metadata:contributions:user_id'] = 'ID người dùng Moodle của sinh viên đóng góp testcase.';
$string['privacy:metadata:contributions:input_normalized'] = 'Dữ liệu đầu vào testcase đã qua chuẩn hóa.';
$string['privacy:metadata:contributions:oracle_output'] = 'Kết quả đầu ra của lời giải tham chiếu cho testcase này.';
$string['privacy:metadata:contributions:purpose'] = 'Mục đích kiểm thử hoặc danh mục của testcase.';
$string['privacy:metadata:contributions:status'] = 'Trạng thái duyệt hoặc công bố của testcase.';
$string['privacy:metadata:contributions:rating'] = 'Điểm đánh giá số sao của giáo viên cho đóng góp này.';
$string['privacy:metadata:contributions:review_comment'] = 'Nhận xét phản hồi của giáo viên.';
$string['privacy:metadata:contributions:timecreated'] = 'Thời điểm gửi đóng góp testcase.';
$string['privacy:metadata:rewards'] = 'Phần thưởng và testcase mở khóa cấp cho sinh viên.';
$string['privacy:metadata:rewards:receiver_user_id'] = 'ID người dùng Moodle nhận phần thưởng testcase.';
$string['privacy:metadata:rewards:is_claimed'] = 'Trạng thái phần thưởng đã được mở khóa hay chưa.';
$string['privacy:metadata:rewards:claimed_at'] = 'Thời điểm mở khóa phần thưởng.';
$string['privacy:metadata:rewards:timecreated'] = 'Thời điểm phân bổ phần thưởng.';
$string['privacy:metadata:reviews'] = 'Nhật ký đánh giá và nhận xét của giáo viên đối với testcase của sinh viên.';
$string['privacy:metadata:reviews:reviewer_user_id'] = 'ID người dùng Moodle của giáo viên chấm duyệt testcase.';
$string['privacy:metadata:reviews:review_note'] = 'Nội dung nhận xét hoặc đánh giá của giáo viên.';
$string['privacy:metadata:reviews:rating'] = 'Điểm đánh giá số sao do người duyệt chấm.';
$string['privacy:metadata:reviews:timecreated'] = 'Thời điểm ghi nhận đánh giá.';
$string['privacy:metadata:revisions'] = 'Các bản chụp mã nguồn theo từng bước tiến trình giải bài của sinh viên.';
$string['privacy:metadata:revisions:user_id'] = 'ID người dùng Moodle sở hữu bản sửa đổi mã nguồn.';
$string['privacy:metadata:revisions:code_content'] = 'Nội dung mã nguồn chương trình.';
$string['privacy:metadata:revisions:step_number'] = 'Số thứ tự bước của bản chụp mã nguồn.';
$string['privacy:metadata:revisions:timecreated'] = 'Thời điểm lưu bản chụp mã nguồn.';
$string['privacy:metadata:jobe'] = 'Mã nguồn và input testcase được gửi tới Jobe sandbox để thực thi; plugin không chủ ý lưu chúng tại Jobe.';
$string['privacy:metadata:jobe:source'] = 'Mã nguồn sinh viên hoặc lời giải tham chiếu CodeRunner.';
$string['privacy:metadata:jobe:input'] = 'Input testcase dùng khi chạy sandbox.';
$string['coursecontributions'] = 'Kho testcase sinh viên đóng góp';
$string['allcontributions'] = 'Tất cả đóng góp';
$string['student'] = 'Sinh viên';
$string['quiz'] = 'Bài Quiz';
$string['question'] = 'Câu hỏi';
$string['filterbyquestion'] = 'Lọc theo câu hỏi';
$string['filterbystatus'] = 'Lọc theo trạng thái';
$string['allquestions'] = 'Tất cả câu hỏi trong khóa học';
$string['allstatuses'] = 'Tất cả trạng thái';
$string['pending'] = 'Chờ duyệt';
$string['status_submitted'] = 'Chờ duyệt';
$string['status_approved'] = 'Đã duyệt';
$string['status_rejected'] = 'Từ chối';
$string['status_duplicate'] = 'Trùng lặp';
$string['status_needs_explanation'] = 'Cần giải thích';
$string['status_archived'] = 'Đã lưu trữ';
$string['nocontributionsfound'] = 'Chưa có testcase nào do sinh viên đóng góp theo bộ lọc này.';
$string['actions'] = 'Thao tác';
$string['starrating'] = 'Đánh giá sao';
$string['stars'] = 'sao';
$string['norating'] = 'Chưa chấm sao';
$string['teachercomment'] = 'Nhận xét của giảng viên';
$string['saverating'] = 'Lưu đánh giá';
$string['rating_5_desc'] = 'Xuất sắc / Biên hiểm hóc';
$string['rating_4_desc'] = 'Rất tốt';
$string['rating_3_desc'] = 'Tốt';
$string['rating_2_desc'] = 'Bình thường';
$string['rating_1_desc'] = 'Cơ bản';
$string['coursehub'] = 'Trung Tâm Kho Testcase — Danh Sách Khóa Học';
$string['coursehub_desc'] = 'Chọn một khóa học bên dưới để xem kho testcase của sinh viên, duyệt các đóng góp mới, hoặc cấu hình chính sách.';
$string['coursehub_review_title'] = 'Duyệt Đóng Góp — Chọn Khóa Học';
$string['coursehub_review_desc'] = 'Chọn một khóa học bên dưới để duyệt các testcase do sinh viên đóng góp.';
$string['courseswithcoderunner'] = 'Các khóa học có bài tập CodeRunner';
$string['switchcourse'] = 'Đổi khóa học';
$string['allcourses'] = 'Tất cả khóa học';
$string['viewtestcasebank'] = 'Vào kho testcase';
$string['reviewcontributionsbtn'] = 'Duyệt đóng góp';
$string['totalcontributions'] = 'Tổng đóng góp';
$string['pendingcount'] = 'Chờ duyệt';
$string['approvedcount'] = 'Đã duyệt';
$string['backtocoursehub'] = 'Về danh sách khóa học';

$string['leaderboard'] = 'Bảng Xếp Hạng';
$string['course_leaderboard_title'] = 'Bảng Vinh Danh Đóng Góp Testcase Khóa Học';
$string['leaderboard_rules_hint'] = 'Ưu tiên: 1. Tổng số sao ⭐ | 2. Số lượng testcase | 3. Thời gian đóng góp sớm nhất';
$string['rank'] = 'Hạng';
$string['rank_1'] = 'Quán quân';
$string['your_rank'] = 'Vị trí của bạn';
$string['no_leaderboard_yet'] = 'Chưa có dữ liệu xếp hạng trong khóa học này.';
$string['total_stars'] = 'Tổng sao ⭐';
$string['approved_testcases'] = 'Testcase được duyệt';
$string['first_contribution'] = 'Đóng góp đầu tiên';
$string['claimed'] = 'Đã nhận thưởng';
$string['unclaimed'] = 'Chưa nhận';
$string['claim_reward_btn'] = 'Nhận thưởng';
$string['rewardclaimedsuccess'] = 'Chúc mừng bạn đã mở khóa thành công testcase phần thưởng mới!';
$string['invalidreward'] = 'Phần thưởng không hợp lệ hoặc đã được nhận trước đó.';
$string['unclaimed_rewards_alert'] = 'Phần thưởng mới chờ bạn nhận';
$string['ready_for_check'] = 'Sẵn sàng dùng cho nút Check';
$string['norewardsfound'] = 'Bạn chưa có phần thưởng nào. Hãy đóng góp testcase mới để được thưởng!';

// Phân tách Teacher Hub và Student View.
$string['teacherview_title'] = 'Trung Tâm Quản Lý Giáo Viên';
$string['studentview_title'] = 'Phòng Thí Nghiệm Kiểm Thử Vi Sai';
$string['view_as_student'] = 'Xem thử như sinh viên';
$string['previewmode_banner'] = 'Chế độ xem thử — Tài khoản giáo viên: Bạn đang trải nghiệm giao diện với tư cách sinh viên. Trình soạn thảo, chạy vi sai và kiểm tra điều kiện hoạt động đúng góc nhìn của người học.';
$string['previewmode_exit'] = 'Quay lại quản lý';
$string['previewmode_contribute_notice'] = 'Chế độ xem thử: Thao tác đóng góp của giáo viên không được ghi nhận vào kho thật để bảo vệ dữ liệu học tập của sinh viên.';
$string['previewmode_reward_notice'] = 'Chế độ xem thử: Không thể nhận thưởng của sinh viên trong chế độ xem thử.';
$string['previewmode_run_disabled_notice'] = 'Chế độ xem thử: Thao tác chạy thử nghiệm vi sai bị vô hiệu hóa trong chế độ xem thử để bảo vệ dữ liệu thống kê của sinh viên.';
$string['tab_overview'] = 'Tổng quan';
$string['tab_review'] = 'Đóng góp & Thẩm định';
$string['tab_bank'] = 'Kho testcase';
$string['tab_activity'] = 'Hoạt động sinh viên & Xuất';
$string['tab_policy'] = 'Cấu hình chính sách';
$string['tab_solve'] = 'Làm bài';
$string['tab_my_contributions'] = 'Đóng góp của tôi';
$string['tab_my_rewards'] = 'Testcase nhận được';
$string['question_prompt_title'] = 'Đề bài';
$string['public_samples_title'] = 'Testcase Mẫu / Công Khai';
$string['public_samples_desc'] = 'Các ca kiểm thử hiển thị trực tiếp trong đề bài CodeRunner. Được hệ thống loại trừ tự động, không dùng để trao thưởng và không cho phép đóng góp lại.';
$string['seed_bank_title'] = 'Kho Testcase Mầm (Giảng Viên)';
$string['seed_bank_desc'] = 'Các ca kiểm thử do Giảng viên chuẩn bị sẵn, dùng để trao thưởng ban đầu khi kho đóng góp của bạn học chưa đủ dữ liệu.';
$string['approved_peer_title'] = 'Testcase Bạn Học Đã Duyệt';
$string['approved_peer_desc'] = 'Các ca kiểm thử chất lượng cao do sinh viên đóng góp và Giảng viên đã duyệt, đang luân chuyển trong kho phần thưởng.';
$string['all_students'] = 'Tất cả sinh viên';
$string['filter_by_student'] = 'Lọc theo sinh viên';
$string['filter_by_status'] = 'Lọc theo trạng thái';


