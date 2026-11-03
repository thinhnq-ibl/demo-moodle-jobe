# Update lang files with missing strings
new_en_strings = {
    'draft_saved_device': 'Draft auto-saved on this device at {$a}',
    'draft_saved_device_justnow': 'Draft auto-saved on this device (just now)',
    'draft_restored_device': 'Restored draft from this device ({$a})',
    'draft_earlier': 'earlier',
    'running': 'Running...',
    'revision_loaded': 'Loaded revision into editor.',
    'run_code_loaded': 'Loaded code from run into editor.',
    'testcase_cleared': 'Cleared testcase inputs for new entry.',
    'show_more_runs': 'Show more ({$a} older runs)',
    'hide_older_runs': 'Hide older runs',
    'collapse': 'Collapse',
    'expand': 'Expand',
    'no_data': 'No data',
    'empty_string': '(Empty string)',
    'seed_guidance_help': 'Seed testcases can be added directly via the form above, or configured as question testcases in the Moodle Question Bank.',
    'rewards_granted_title': 'Rewards granted',
    'claimed_short': 'claimed',
    'unclaimed_rewards_desc': 'testcases available to claim',
    'scope_quiz': 'Entire Quiz',
    'scope_question': 'Current Question',
    'scope_course': 'Entire Course',
    'decision_approved': 'Approve',
    'decision_needs_explanation': 'Needs Explanation',
    'decision_rejected': 'Reject',
    'decision_help_text': 'Approve adds testcase to peer bank and unlocks reward. Needs explanation requests clarification. Reject archives invalid testcase.',
    'save_decision': 'Save review decision',
    'teacher_review_feedback_placeholder': 'Enter detailed pedagogical feedback or reason for approval/rejection...',
    'time_and_version': 'Time / Version',
    'expected_vs_actual': 'Expected vs Actual',
    'comparison_result': 'Comparison Result',
    'contribution_status_col': 'Contribution Status',
    'load_code': 'Load code',
    'expected_label': 'Expected:',
    'actual_label': 'Actual:',
    'match_exact': 'Exact match',
    'mismatch': 'Output mismatch',
    'execution_error': 'Execution error',
    'duplicate_in_run': 'Duplicate with run #{$a}',
    'view_contribution_btn': 'View contribution (#{$a})',
    'anonymous': 'Anonymous'
}

new_vi_strings = {
    'draft_saved_device': 'Đã lưu nháp tự động trên thiết bị này lúc {$a}',
    'draft_saved_device_justnow': 'Đã lưu nháp tự động trên thiết bị này (vừa xong)',
    'draft_restored_device': 'Đã khôi phục bản nháp từ thiết bị này ({$a})',
    'draft_earlier': 'trước đó',
    'running': 'Đang chạy...',
    'revision_loaded': 'Đã nạp phiên bản vào vùng soạn thảo.',
    'run_code_loaded': 'Đã nạp mã nguồn từ lượt chạy vào vùng soạn thảo.',
    'testcase_cleared': 'Đã xóa dữ liệu testcase để nhập mới.',
    'show_more_runs': 'Xem thêm ({$a} lượt chạy cũ hơn)',
    'hide_older_runs': 'Thu gọn lượt chạy cũ',
    'collapse': 'Thu gọn',
    'expand': 'Mở rộng',
    'no_data': 'Không có dữ liệu',
    'empty_string': '(Chuỗi rỗng)',
    'seed_guidance_help': 'Giảng viên có thể thêm trực tiếp các ca kiểm thử mầm bằng biểu mẫu phía trên, hoặc bổ sung trực tiếp trong cấu hình câu hỏi CodeRunner tại Ngân hàng câu hỏi Moodle.',
    'rewards_granted_title': 'Phần thưởng đã cấp',
    'claimed_short': 'đã nhận',
    'unclaimed_rewards_desc': 'testcase chưa nhận',
    'scope_quiz': 'Toàn bài Quiz',
    'scope_question': 'Câu hỏi hiện tại',
    'scope_course': 'Toàn khóa học',
    'decision_approved': 'Chấp thuận',
    'decision_needs_explanation': 'Cần giải trình',
    'decision_rejected': 'Từ chối',
    'decision_help_text': 'Chấp thuận sẽ bổ sung testcase vào kho và cấp thưởng. Cần giải trình yêu cầu sinh viên làm rõ mục đích. Từ chối sẽ lưu trữ testcase không đạt.',
    'save_decision': 'Lưu quyết định thẩm định',
    'teacher_review_feedback_placeholder': 'Nhập nhận xét chi tiết, góp ý sư phạm hoặc lý do chấp thuận/từ chối...',
    'time_and_version': 'Thời gian / Phiên bản',
    'expected_vs_actual': 'Kỳ vọng vs Thực tế',
    'comparison_result': 'Kết quả đối chiếu',
    'contribution_status_col': 'Trạng thái đóng góp',
    'load_code': 'Nạp code',
    'expected_label': 'Kỳ vọng:',
    'actual_label': 'Thực tế:',
    'match_exact': 'Khớp hoàn toàn',
    'mismatch': 'Không khớp',
    'execution_error': 'Lỗi thực thi',
    'duplicate_in_run': 'Trùng ở lượt #{$a}',
    'view_contribution_btn': 'Xem đóng góp (#{$a})',
    'anonymous': 'Ẩn danh'
}

def patch_lang_file(filepath, new_strings):
    with open(filepath, 'r', encoding='utf-8') as f:
        content = f.read()

    lines_to_add = []
    for k, v in new_strings.items():
        needle = f"$string['{k}']"
        if needle not in content:
            # Escape single quotes in value
            escaped_v = v.replace("'", "\\'")
            lines_to_add.append(f"$string['{k}'] = '{escaped_v}';\n")

    if lines_to_add:
        # Append before closing or at end
        content = content.rstrip() + "\n\n// Added UI/UX strings\n" + "".join(lines_to_add)
        with open(filepath, 'w', encoding='utf-8') as f:
            f.write(content)
        print(f"Added {len(lines_to_add)} strings to {filepath}")
    else:
        print(f"All strings already exist in {filepath}")

patch_lang_file('local_testcase_exchange/lang/en/local_testcase_exchange.php', new_en_strings)
patch_lang_file('local_testcase_exchange/lang/vi/local_testcase_exchange.php', new_vi_strings)
