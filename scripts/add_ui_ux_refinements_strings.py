en_strings = {
    'input_label_stdin': 'Input data (stdin):',
    'input_label_testcode': 'Function call / Test expression:',
    'input_placeholder_stdin': 'Enter stdin input for program (e.g. line 1 count, line 2 values...)',
    'input_placeholder_testcode': 'Enter test function call (e.g. print(is_prime(5)))',
    'define_testcase_title': 'Define testcase',
    'test_type_label': 'Test type',
    'outcome_success': 'Success',
    'outcome_failed': 'Failed',
    'student_expected_output_label': 'Student expected output',
    'reference_solution_output_label': 'Reference solution output',
    'review_contribution_modal_title': 'Review contribution #{$a}',
    'student_contributor': 'Student contributor',
    'submitted_time_and_status': 'Submitted time & status',
    'question_label': 'Question',
    'input_data_label': 'Input data (Input)',
    'current_review_result': 'Current review result',
    'edit_review': 'Edit review',
    'no_review_comment': '(No comment)',
    'unrated': 'Unrated',
    'question': 'Question',
    'clean_predicted_label': 'Expected output',
    'clean_purpose_label': 'Testing purpose'
}

vi_strings = {
    'input_label_stdin': 'Dữ liệu đầu vào (stdin):',
    'input_label_testcode': 'Lệnh gọi hàm / Biểu thức kiểm thử:',
    'input_placeholder_stdin': 'Nhập dữ liệu đầu vào stdin cho chương trình (ví dụ: dòng 1 là số lượng, dòng 2 là các giá trị...)',
    'input_placeholder_testcode': 'Nhập lệnh gọi hàm kiểm thử (ví dụ: print(is_prime(5)))',
    'define_testcase_title': 'Định nghĩa Testcase kiểm thử',
    'test_type_label': 'Kiểu kiểm thử',
    'outcome_success': 'Chạy thành công',
    'outcome_failed': 'Lỗi thực thi',
    'student_expected_output_label': 'Kết quả kỳ vọng của sinh viên',
    'reference_solution_output_label': 'Kết quả lời giải tham chiếu',
    'review_contribution_modal_title': 'Thẩm định đóng góp #{$a}',
    'student_contributor': 'Sinh viên đóng góp',
    'submitted_time_and_status': 'Thời gian gửi & Trạng thái',
    'question_label': 'Bài tập / Câu hỏi',
    'input_data_label': 'Dữ liệu đầu vào (Input)',
    'current_review_result': 'Kết quả thẩm định hiện tại',
    'edit_review': 'Chỉnh sửa thẩm định',
    'no_review_comment': '(Không có nhận xét)',
    'unrated': 'Chưa chấm sao',
    'question': 'Bài tập',
    'clean_predicted_label': 'Kết quả kỳ vọng',
    'clean_purpose_label': 'Mục đích kiểm thử'
}

def update_lang(filepath, strings):
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    to_add = []
    for k, v in strings.items():
        needle = f"$string['{k}']"
        if needle not in content:
            escaped = v.replace("'", "\\'")
            to_add.append(f"$string['{k}'] = '{escaped}';\n")

    if to_add:
        content = content.rstrip() + "\n\n// Added refined UI strings\n" + "".join(to_add)
        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(content)
        print(f"Added {len(to_add)} strings to {filepath}")
    else:
        print(f"All strings already present in {filepath}")

update_lang('local_testcase_exchange/lang/en/local_testcase_exchange.php', en_strings)
update_lang('local_testcase_exchange/lang/vi/local_testcase_exchange.php', vi_strings)
