# -*- coding: utf-8 -*-
import os

vi_file = 'local_testcase_exchange/lang/vi/local_testcase_exchange.php'
en_file = 'local_testcase_exchange/lang/en/local_testcase_exchange.php'

vi_additions = """
// UI/UX missing modal and notice strings
$string['quality_rating'] = 'Đánh giá chất lượng';
$string['rating_none'] = '-- Chưa đánh giá --';
$string['rating_5'] = '5 sao - Xuất sắc';
$string['rating_4'] = '4 sao - Rất tốt';
$string['rating_3'] = '3 sao - Khá';
$string['rating_2'] = '2 sao - Trung bình';
$string['rating_1'] = '1 sao - Cơ bản';
$string['pedagogical_feedback'] = 'Nhận xét sư phạm cho sinh viên';
$string['close'] = 'Đóng';
$string['select_decision'] = 'Chọn quyết định';
$string['nocourseswithcoderunner'] = 'Không tìm thấy khóa học nào có câu hỏi CodeRunner phù hợp.';
$string['contributionsubmitted'] = 'Đã gửi đóng góp — đang chờ giảng viên duyệt';
$string['form_dirty_warning'] = 'Dữ liệu đã thay đổi; kết quả bên dưới thuộc lần chạy trước.';
$string['earlier_contrib_notice_text'] = 'Bạn đã gửi một testcase có cùng đầu vào ở lượt #{$a->run_id}, với kết quả kỳ vọng {$a->expected}.';
$string['already_contributed_notice'] = 'Bạn đã đóng góp testcase này trước đó rồi (Đóng góp #{$a}). Hệ thống không tạo đề xuất mới.';
$string['status_submitted_label'] = 'Đã gửi đóng góp';
"""

en_additions = """
// UI/UX missing modal and notice strings
$string['quality_rating'] = 'Quality rating';
$string['rating_none'] = '-- No rating --';
$string['rating_5'] = '5 stars - Excellent';
$string['rating_4'] = '4 stars - Very good';
$string['rating_3'] = '3 stars - Good';
$string['rating_2'] = '2 stars - Fair';
$string['rating_1'] = '1 star - Basic';
$string['pedagogical_feedback'] = 'Pedagogical feedback for student';
$string['close'] = 'Close';
$string['select_decision'] = 'Select decision';
$string['nocourseswithcoderunner'] = 'No courses found with eligible CodeRunner questions.';
$string['contributionsubmitted'] = 'Contribution submitted — awaiting teacher review';
$string['form_dirty_warning'] = 'Data has changed; the results below are from the previous run.';
$string['earlier_contrib_notice_text'] = 'You have already submitted a testcase with the same input at run #{$a->run_id}, with expected output {$a->expected}.';
$string['already_contributed_notice'] = 'You have already contributed this testcase (Contribution #{$a}). No new proposal was created.';
$string['status_submitted_label'] = 'Submitted contribution';
"""

def append_strings(filepath, additions):
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read().rstrip()
    if content.endswith('?>'):
        content = content[:-2].rstrip()
    content += "\n" + additions.strip() + "\n"
    with open(filepath, 'w', encoding='utf-8') as f:
        f.write(content)
    print(f"Updated {filepath}")

append_strings(vi_file, vi_additions)
append_strings(en_file, en_additions)
