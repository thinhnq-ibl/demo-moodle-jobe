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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

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
$string['predictedoutput'] = 'Kết quả dự đoán';
$string['studentoutput'] = 'Kết quả code sinh viên';
$string['oracleoutput'] = 'Kết quả tham chiếu';
$string['purpose'] = 'Bạn muốn kiểm tra hành vi nào?';
$string['reflection'] = 'Bạn đã phát hiện hoặc học được gì?';
$string['category'] = 'Nhóm testcase';
$string['category_normal'] = 'Trường hợp thông thường';
$string['category_boundary'] = 'Giá trị biên';
$string['category_empty'] = 'Dữ liệu rỗng hoặc thiếu';
$string['category_invalid'] = 'Dữ liệu không hợp lệ';
$string['category_large'] = 'Dữ liệu lớn / hiệu năng';
$string['category_branch'] = 'Nhánh logic';
$string['category_other'] = 'Nhóm khác';
$string['runprivate'] = 'Chạy thử riêng';
$string['myruns'] = 'Lần thử riêng của tôi';
$string['mycontributions'] = 'Đóng góp của tôi';
$string['myrewards'] = 'Testcase đã mở khóa';
$string['proposesharing'] = 'Đề xuất chia sẻ';
$string['managepolicies'] = 'Cấu hình chính sách testcase';
$string['reviewcontributions'] = 'Duyệt testcase đóng góp';
$string['backtodashboard'] = 'Quay lại trang testcase';
$string['runcreatedmatch'] = 'Đã lưu lần thử riêng. Dự đoán của bạn khớp kết quả tham chiếu.';
$string['runcreatedmismatch'] = 'Đã lưu lần thử riêng. Hãy xem lại khác biệt trước khi đề xuất chia sẻ.';
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
$string['privacy:metadata:testcase_store'] = 'Kho testcase bên ngoài lưu lượt chạy riêng, đóng góp, lịch sử duyệt và phần thưởng.';
$string['privacy:metadata:testcase_store:user_id'] = 'ID người dùng Moodle xác định chủ sở hữu, người duyệt hoặc người nhận thưởng.';
$string['privacy:metadata:testcase_store:input'] = 'Input testcase do người dùng gửi.';
$string['privacy:metadata:testcase_store:source_hash'] = 'Mã băm một chiều của mã nguồn chương trình đã gửi.';
$string['privacy:metadata:testcase_store:outputs'] = 'Kết quả dự đoán, chương trình sinh viên và lời giải tham chiếu.';
$string['privacy:metadata:testcase_store:reflection'] = 'Mục đích, nhóm và nội dung phản ánh đi cùng testcase.';
$string['privacy:metadata:jobe'] = 'Mã nguồn và input testcase được gửi tới Jobe sandbox để thực thi; plugin không chủ ý lưu chúng tại Jobe.';
$string['privacy:metadata:jobe:source'] = 'Mã nguồn sinh viên hoặc lời giải tham chiếu CodeRunner.';
$string['privacy:metadata:jobe:input'] = 'Input testcase dùng khi chạy sandbox.';
