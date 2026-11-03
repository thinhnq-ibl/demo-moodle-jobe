# -*- coding: utf-8 -*-
import re

file_path = 'local_testcase_exchange/index.php'
with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

# 1. Action contribute message replacement
old_action_contribute = """            if (!empty($result['is_own_duplicate'])) {
                $message = 'Bạn đã đóng góp testcase này trước đó rồi (Đóng góp #' . $result['canonical_id'] . '). Hệ thống không tạo đề xuất mới.';
                $notifytype = \\core\\output\\notification::NOTIFY_WARNING;
            } else {
                $message = get_string('contributionstatus', 'local_testcase_exchange', $result['status']);
                $notifytype = ($result['status'] === 'duplicate') ?
                    \\core\\output\\notification::NOTIFY_WARNING : \\core\\output\\notification::NOTIFY_SUCCESS;
            }"""

new_action_contribute = """            if (!empty($result['is_own_duplicate'])) {
                $message = get_string('already_contributed_notice', 'local_testcase_exchange', $result['canonical_id']);
                $notifytype = \\core\\output\\notification::NOTIFY_WARNING;
            } else if (($result['status'] ?? '') === 'submitted') {
                $message = get_string('contributionsubmitted', 'local_testcase_exchange');
                $notifytype = \\core\\output\\notification::NOTIFY_SUCCESS;
            } else if (($result['status'] ?? '') === 'approved') {
                $message = get_string('contributionapproved', 'local_testcase_exchange');
                $notifytype = \\core\\output\\notification::NOTIFY_SUCCESS;
            } else {
                $message = get_string('contributionstatus', 'local_testcase_exchange', $result['status'] ?? '');
                $notifytype = (($result['status'] ?? '') === 'duplicate') ?
                    \\core\\output\\notification::NOTIFY_WARNING : \\core\\output\\notification::NOTIFY_SUCCESS;
            }"""

# 2. Contribution indexing replacement
old_indexing = """    $contributionbyrun = [];
    $user_contrib_by_fp = [];
    foreach ($contributions as $contribution) {
        $contributionbyrun[(int) $contribution['run_id']] = $contribution;
        $fp = (string) ($contribution['input_fingerprint'] ?? '');
        if ($fp !== '') {
            $key = (int) $contribution['question_id'] . ':' . $fp;
            if (!isset($user_contrib_by_fp[$key])) {
                $user_contrib_by_fp[$key] = $contribution;
            } else if (($user_contrib_by_fp[$key]['status'] ?? '') === 'duplicate' && ($contribution['status'] ?? '') !== 'duplicate') {
                $user_contrib_by_fp[$key] = $contribution;
            }
        }
    }"""

new_indexing = """    $contributionbyrun = [];
    $user_contrib_by_run_id = [];
    $user_contrib_by_exact_io = [];
    $user_contrib_by_input = [];
    foreach ($contributions as $contribution) {
        $run_id_val = (int) ($contribution['run_id'] ?? 0);
        if ($run_id_val > 0) {
            $user_contrib_by_run_id[$run_id_val] = $contribution;
            $contributionbyrun[$run_id_val] = $contribution;
        }
        $fp = (string) ($contribution['input_fingerprint'] ?? '');
        if ($fp !== '') {
            $qid = (int) $contribution['question_id'];
            $exp = trim((string) ($contribution['expected_output'] ?? ''));
            $exact_key = $qid . ':' . $fp . ':' . md5($exp);
            $input_key = $qid . ':' . $fp;

            if (!isset($user_contrib_by_exact_io[$exact_key])) {
                $user_contrib_by_exact_io[$exact_key] = $contribution;
            } else if (($user_contrib_by_exact_io[$exact_key]['status'] ?? '') === 'duplicate' && ($contribution['status'] ?? '') !== 'duplicate') {
                $user_contrib_by_exact_io[$exact_key] = $contribution;
            }

            if (!isset($user_contrib_by_input[$input_key])) {
                $user_contrib_by_input[$input_key] = $contribution;
            } else if (($user_contrib_by_input[$input_key]['status'] ?? '') === 'duplicate' && ($contribution['status'] ?? '') !== 'duplicate') {
                $user_contrib_by_input[$input_key] = $contribution;
            }
        }
    }"""

# 3. Decoration loop replacement
old_decoration_block = """    // Step 2: Presentation decoration adhering to duplicate grouping & contribution gates
    $run_count_total = count($runs);
    $run_idx = 0;
    foreach ($runs as &$run) {
        $run_idx++;
        $run['run_index'] = $run_idx;
        $run['is_extra_run'] = ($run_idx > 5);

        $run_id = (int) $run['id'];
        $fp_key = $run['group_key'];
        $user_contrib = $user_contrib_by_fp[$fp_key] ?? null;
        $existing_canonical = ($run['input_fingerprint'] !== '' && isset($existingfingerprints[$fp_key])) ? $existingfingerprints[$fp_key] : null;
        $rep_id = $group_rep_run_id[$fp_key] ?? null;

        $run['can_contribute'] = false;
        $run['is_older_duplicate'] = false;
        $run['rep_id'] = null;
        $run['has_eligible_peer'] = false;
        $run['pending_code_fix'] = false;
        $run['has_user_contrib'] = false;
        $run['contrib_id'] = null;
        $run['contrib_status'] = null;

        // 1. If student already contributed this testcase (group-wide priority)
        if ($user_contrib) {
            $run['has_user_contrib'] = true;
            $run['contrib_id'] = (int) $user_contrib['id'];
            $st = (string) $user_contrib['status'];
            $run['contrib_status'] = $st;

            if ($st === 'submitted') {
                $run['display_status'] = 'Đã gửi đóng góp — đang chờ giảng viên duyệt';
                $run['display_status_class'] = 'badge badge-info';
            } else if ($st === 'approved') {
                $run['display_status'] = 'Đóng góp đã được duyệt';
                $run['display_status_class'] = 'badge badge-success';
            } else if ($st === 'rejected') {
                $comment = !empty($user_contrib['teacher_comment']) ? ': ' . $user_contrib['teacher_comment'] : '';
                $run['display_status'] = 'Đóng góp chưa được chấp thuận' . $comment;
                $run['display_status_class'] = 'badge badge-danger';
            } else if ($st === 'needs_explanation') {
                $run['display_status'] = 'Đóng góp cần giải trình thêm';
                $run['display_status_class'] = 'badge badge-warning text-dark';
            } else {
                $status_lbl = $user_contrib['status_label'] ?? $st;
                $run['display_status'] = 'Đã đóng góp #' . $user_contrib['id'] . ' (' . $status_lbl . ')';
                $run['display_status_class'] = 'badge badge-info';
            }
        }
        // 2. Duplicate against teacher seeds or peer bank (NOT current student's contribution)
        else if ($existing_canonical) {
            $label = ($existing_canonical['type'] === 'seed') ?
                'Trùng testcase mẫu công khai của đề bài' :
                ('Trùng testcase đã có trong kho (#' . $existing_canonical['id'] . ')');
            $run['display_status'] = $label;
            $run['display_status_class'] = 'badge badge-secondary';
        }
        // 3. Unclaimed: Latest eligible run in group receives the contribution proposal button
        else if ($rep_id !== null && $run_id === $rep_id) {
            $run['can_contribute'] = true;
            $run['display_status'] = 'Đủ điều kiện đóng góp';
            $run['display_status_class'] = 'badge badge-success';
        }
        // 4. Unclaimed: Older eligible run in same group links to representative run
        else if ($run['is_eligible']) {
            $run['is_older_duplicate'] = true;
            $run['rep_id'] = $rep_id;
            $run['display_status'] = 'Trùng testcase ở lượt #' . $rep_id;
            $run['display_status_class'] = 'badge badge-secondary';
        }
        // 5. Ineligible run
        else {
            if (!$run['oracle_valid']) {
                $run['display_status'] = 'Lỗi thực thi đề bài';
                $run['display_status_class'] = 'badge badge-danger';
            } else if (!$run['expected_matches_oracle']) {
                $run['display_status'] = 'Kết quả kỳ vọng không khớp';
                $run['display_status_class'] = 'badge badge-warning text-dark';
            } else {
                $run['display_status'] = 'Hãy sửa mã nguồn và chạy lại trước khi đóng góp';
                $run['display_status_class'] = 'badge badge-warning text-dark';
                $run['pending_code_fix'] = true;
                $run['pending_fix_message'] = 'Kết quả mong đợi đã được xác nhận. Chương trình hiện tại chưa vượt qua testcase này. Hãy sửa chương trình và chạy lại để đủ điều kiện đóng góp.';
            }

            // If an older/other run in this same group was eligible, let student easily jump to it
            if ($rep_id !== null && $rep_id !== $run_id) {
                $run['has_eligible_peer'] = true;
                $run['rep_id'] = $rep_id;
            }
        }"""

new_decoration_block = """    // Step 2: Presentation decoration adhering to duplicate grouping & contribution gates
    $run_count_total = count($runs);
    $run_idx = 0;
    foreach ($runs as &$run) {
        $run_idx++;
        $run['run_index'] = $run_idx;
        $run['is_extra_run'] = ($run_idx > 5);

        $run_id = (int) $run['id'];
        $fp_key = $run['group_key'];
        $run_fp = (string) ($run['input_fingerprint'] ?? '');
        $run_exp = trim((string) ($run['predicted_output'] ?? ''));
        $run_io_key = (int) $run['question_id'] . ':' . $run_fp . ':' . md5($run_exp);

        $direct_contrib = null;
        if (isset($user_contrib_by_run_id[$run_id])) {
            $direct_contrib = $user_contrib_by_run_id[$run_id];
        } else if ($run['is_eligible'] && isset($user_contrib_by_exact_io[$run_io_key])) {
            $direct_contrib = $user_contrib_by_exact_io[$run_io_key];
        }

        $earlier_input_contrib = null;
        if (!$direct_contrib && isset($user_contrib_by_input[$fp_key])) {
            $earlier_input_contrib = $user_contrib_by_input[$fp_key];
        }

        $existing_canonical = ($run['input_fingerprint'] !== '' && isset($existingfingerprints[$fp_key])) ? $existingfingerprints[$fp_key] : null;
        $rep_id = $group_rep_run_id[$fp_key] ?? null;

        $run['can_contribute'] = false;
        $run['is_older_duplicate'] = false;
        $run['rep_id'] = null;
        $run['has_eligible_peer'] = false;
        $run['pending_code_fix'] = false;
        $run['has_user_contrib'] = false;
        $run['contrib_id'] = null;
        $run['contrib_status'] = null;
        $run['earlier_contrib_notice'] = null;

        // 1. If this run itself matches an already submitted contribution
        if ($direct_contrib) {
            $run['has_user_contrib'] = true;
            $run['contrib_id'] = (int) $direct_contrib['id'];
            $st = (string) $direct_contrib['status'];
            $run['contrib_status'] = $st;

            if ($st === 'submitted') {
                $run['display_status'] = 'Đã gửi đóng góp — đang chờ giảng viên duyệt';
                $run['display_status_class'] = 'badge badge-info';
            } else if ($st === 'approved') {
                $run['display_status'] = 'Đóng góp đã được duyệt';
                $run['display_status_class'] = 'badge badge-success';
            } else if ($st === 'rejected') {
                $comment = !empty($direct_contrib['teacher_comment']) ? ': ' . $direct_contrib['teacher_comment'] : '';
                $run['display_status'] = 'Đóng góp chưa được chấp thuận' . $comment;
                $run['display_status_class'] = 'badge badge-danger';
            } else if ($st === 'needs_explanation') {
                $run['display_status'] = 'Đóng góp cần giải trình thêm';
                $run['display_status_class'] = 'badge badge-warning text-dark';
            } else {
                $status_lbl = $direct_contrib['status_label'] ?? $st;
                $run['display_status'] = 'Đã đóng góp #' . $direct_contrib['id'] . ' (' . $status_lbl . ')';
                $run['display_status_class'] = 'badge badge-info';
            }
        }
        // 2. Duplicate against teacher seeds or peer bank (NOT current student's contribution)
        else if ($existing_canonical) {
            $label = ($existing_canonical['type'] === 'seed') ?
                'Trùng testcase mẫu công khai của đề bài' :
                ('Trùng testcase đã có trong kho (#' . $existing_canonical['id'] . ')');
            $run['display_status'] = $label;
            $run['display_status_class'] = 'badge badge-secondary';
        }
        // 3. Unclaimed: Latest eligible run in group receives the contribution proposal button
        else if ($rep_id !== null && $run_id === $rep_id) {
            $run['can_contribute'] = true;
            $run['display_status'] = 'Đủ điều kiện đóng góp';
            $run['display_status_class'] = 'badge badge-success';
        }
        // 4. Unclaimed: Older eligible run in same group links to representative run
        else if ($run['is_eligible']) {
            $run['is_older_duplicate'] = true;
            $run['rep_id'] = $rep_id;
            $run['display_status'] = 'Trùng testcase ở lượt #' . $rep_id;
            $run['display_status_class'] = 'badge badge-secondary';
        }
        // 5. Ineligible run
        else {
            if (!$run['oracle_valid']) {
                $run['display_status'] = 'Lỗi thực thi đề bài';
                $run['display_status_class'] = 'badge badge-danger';
            } else if (!$run['expected_matches_oracle']) {
                $run['display_status'] = 'Kết quả kỳ vọng không khớp';
                $run['display_status_class'] = 'badge badge-warning text-dark';
            } else {
                $run['display_status'] = 'Hãy sửa mã nguồn và chạy lại trước khi đóng góp';
                $run['display_status_class'] = 'badge badge-warning text-dark';
                $run['pending_code_fix'] = true;
                $run['pending_fix_message'] = 'Kết quả mong đợi đã được xác nhận. Chương trình hiện tại chưa vượt qua testcase này. Hãy sửa chương trình và chạy lại để đủ điều kiện đóng góp.';
            }

            // If an older/other run in this same group was eligible, let student easily jump to it
            if ($rep_id !== null && $rep_id !== $run_id) {
                $run['has_eligible_peer'] = true;
                $run['rep_id'] = $rep_id;
            }
        }

        // Informative note when user contributed an earlier run with the same input
        if (!$direct_contrib && $earlier_input_contrib) {
            $earlier_run_id = (int) ($earlier_input_contrib['run_id'] ?? 0);
            $earlier_exp = trim((string) ($earlier_input_contrib['expected_output'] ?? ''));
            $run['earlier_contrib_notice'] = get_string(
                'earlier_contrib_notice_text',
                'local_testcase_exchange',
                (object)['run_id' => $earlier_run_id, 'expected' => $earlier_exp]
            );
        }"""

# 4. Latest Result card block replacement
old_latest_block_pattern = r"if \(\$target_run !== null\) \{.*?\n    \}"
# Let's inspect exact lines for $latestrun
content_norm = content.replace('\r\n', '\n')
old_action_contribute_norm = old_action_contribute.replace('\r\n', '\n')
new_action_contribute_norm = new_action_contribute.replace('\r\n', '\n')

old_indexing_norm = old_indexing.replace('\r\n', '\n')
new_indexing_norm = new_indexing.replace('\r\n', '\n')

old_decoration_norm = old_decoration_block.replace('\r\n', '\n')
new_decoration_norm = new_decoration_block.replace('\r\n', '\n')

assert old_action_contribute_norm in content_norm, "old_action_contribute not found"
content_norm = content_norm.replace(old_action_contribute_norm, new_action_contribute_norm, 1)

assert old_indexing_norm in content_norm, "old_indexing not found"
content_norm = content_norm.replace(old_indexing_norm, new_indexing_norm, 1)

assert old_decoration_norm in content_norm, "old_decoration not found"
content_norm = content_norm.replace(old_decoration_norm, new_decoration_norm, 1)

# Now find latest run section:
start_marker = "if ($target_run !== null) {"
end_marker = "    }\n    $templatedata['latest_run'] = $latestrun;"
start_idx = content_norm.find(start_marker)
end_idx = content_norm.find(end_marker)
assert start_idx != -1 and end_idx != -1, f"latest run section not found: start={start_idx}, end={end_idx}"

new_latest_block = """if ($target_run !== null) {
        $first = $target_run;
        $fp_key = $first['group_key'];
        $first_fp = (string) ($first['input_fingerprint'] ?? '');
        $first_exp = trim((string) ($first['predicted_output'] ?? ''));
        $first_io_key = (int) $first['question_id'] . ':' . $first_fp . ':' . md5($first_exp);

        $direct_contrib = null;
        $first_id = (int) $first['id'];
        if (isset($user_contrib_by_run_id[$first_id])) {
            $direct_contrib = $user_contrib_by_run_id[$first_id];
        } else if ($first['is_eligible'] && isset($user_contrib_by_exact_io[$first_io_key])) {
            $direct_contrib = $user_contrib_by_exact_io[$first_io_key];
        }

        $earlier_input_contrib = null;
        if (!$direct_contrib && isset($user_contrib_by_input[$fp_key])) {
            $earlier_input_contrib = $user_contrib_by_input[$fp_key];
        }
        $existing_canonical = ($first['input_fingerprint'] !== '' && isset($existingfingerprints[$fp_key])) ? $existingfingerprints[$fp_key] : null;

        // 1. NHÓM THÔNG TIN 1: KẾT QUẢ CHẠY THỬ
        $run_res_title = '';
        $run_res_guidance = '';
        $run_res_badge = '';
        $run_res_icon = '';

        if (!$first['oracle_valid']) {
            $run_res_title = 'Lỗi thực thi / cú pháp với đề bài';
            $run_res_guidance = 'Testcase gây ra lỗi thực thi hoặc không phù hợp với quy ước của câu hỏi. Vui lòng xem chi tiết lỗi kỹ thuật bên dưới.';
            $run_res_badge = 'badge badge-danger';
            $run_res_icon = 'fa-times-circle text-danger';
        } else if (!$first['expected_matches_oracle']) {
            $run_res_title = 'Kết quả kỳ vọng không khớp lời giải tham chiếu';
            $run_res_guidance = 'Kết quả kỳ vọng bạn dự đoán không khớp với kết quả từ lời giải tham chiếu. Vui lòng kiểm tra lại đề bài, dữ liệu đầu vào và kết quả kỳ vọng.';
            $run_res_badge = 'badge badge-warning text-dark';
            $run_res_icon = 'fa-exclamation-triangle text-warning';
        } else if (!$first['student_passed_test']) {
            $run_res_title = 'Lời giải tham chiếu khớp, mã sinh viên không khớp';
            $run_res_guidance = 'Kết quả mong đợi đã được xác nhận bởi lời giải tham chiếu. Tuy nhiên chương trình hiện tại của bạn chưa vượt qua testcase này.';
            $run_res_badge = 'badge badge-info';
            $run_res_icon = 'fa-info-circle text-info';
        } else {
            $run_res_title = 'Kết quả khớp hoàn toàn';
            $run_res_guidance = 'Mã nguồn của bạn và lời giải tham chiếu đều cho kết quả khớp chính xác với kết quả kỳ vọng.';
            $run_res_badge = 'badge badge-success';
            $run_res_icon = 'fa-check-circle text-success';
        }

        // 2. NHÓM THÔNG TIN 2: TRẠNG THÁI ĐÓNG GÓP
        $cancontributelatest = false;
        $hascontriblink = false;
        $contribid = null;
        $contrib_title = '';
        $contrib_badge = '';
        $contrib_guidance = '';
        $contrib_icon = '';
        $latest_earlier_notice = null;

        // Priority 1: User's own contribution already submitted for this exact run or testcase
        if ($direct_contrib) {
            $cancontributelatest = false;
            $hascontriblink = true;
            $contribid = (int) $direct_contrib['id'];
            $st = (string) $direct_contrib['status'];

            if ($st === 'submitted') {
                $contrib_title = 'Đã gửi đóng góp — đang chờ giảng viên duyệt';
                $contrib_badge = 'badge badge-info';
                $contrib_icon = 'fa-hourglass-half text-info';
                $contrib_guidance = 'Bạn đã gửi đóng góp testcase này thành công (Mã #' . $contribid . '). Vui lòng theo dõi tiến độ duyệt tại tab "Đóng góp của tôi".';
            } else if ($st === 'approved') {
                $contrib_title = 'Đóng góp đã được duyệt';
                $contrib_badge = 'badge badge-success';
                $contrib_icon = 'fa-check-circle text-success';
                $contrib_guidance = 'Đóng góp của bạn đã được giảng viên phê duyệt và bổ sung vào ngân hàng testcase chung của lớp học!';
            } else if ($st === 'rejected') {
                $comment = !empty($direct_contrib['teacher_comment']) ? ' Nhận xét: ' . $direct_contrib['teacher_comment'] : '';
                $contrib_title = 'Đóng góp chưa được chấp thuận';
                $contrib_badge = 'badge badge-danger';
                $contrib_icon = 'fa-times-circle text-danger';
                $contrib_guidance = 'Đóng góp chưa được chấp thuận.' . $comment;
            } else if ($st === 'needs_explanation') {
                $contrib_title = 'Đóng góp cần giải trình thêm';
                $contrib_badge = 'badge badge-warning text-dark';
                $contrib_icon = 'fa-question-circle text-warning';
                $contrib_guidance = 'Giảng viên đã yêu cầu bạn bổ sung giải trình về tính độc đáo của testcase tại tab "Đóng góp của tôi".';
            } else {
                $contrib_title = 'Đã đóng góp #' . $contribid;
                $contrib_badge = 'badge badge-info';
                $contrib_icon = 'fa-check text-info';
                $contrib_guidance = 'Đóng góp #' . $contribid . ' đang được ghi nhận trên hệ thống.';
            }
        }
        // Priority 2: Unclaimed & fully eligible
        else if ($first['can_contribute']) {
            $cancontributelatest = true;
            $contrib_title = 'Đủ điều kiện đóng góp';
            $contrib_badge = 'badge badge-success';
            $contrib_icon = 'fa-paper-plane text-success';
            $contrib_guidance = 'Testcase pass cả lời giải tham chiếu và mã sinh viên. Bạn đủ điều kiện gửi đóng góp testcase này cho lớp học!';
        }
        // Priority 3: Older duplicate of own run
        else if ($first['is_older_duplicate']) {
            $cancontributelatest = false;
            $contrib_title = 'Trùng testcase ở lượt #' . $first['rep_id'];
            $contrib_badge = 'badge badge-secondary';
            $contrib_icon = 'fa-copy text-secondary';
            $contrib_guidance = 'Testcase này đã từng được chạy và đủ điều kiện ở lượt #' . $first['rep_id'] . '. Bạn có thể chuyển tới lượt đạt để đóng góp.';
        }
        // Priority 4: Duplicate against seed testcase
        else if ($existing_canonical && $existing_canonical['type'] === 'seed') {
            $cancontributelatest = false;
            $contrib_title = 'Trùng testcase mẫu công khai của đề bài';
            $contrib_badge = 'badge badge-secondary';
            $contrib_icon = 'fa-ban text-secondary';
            $contrib_guidance = 'Testcase này trùng với testcase mẫu trong đề thi hoặc kho hạt giống của giáo viên.';
        }
        // Priority 5: Duplicate against another student's contribution
        else if ($existing_canonical) {
            $cancontributelatest = false;
            $contrib_title = 'Trùng testcase đã có trong kho bài tập';
            $contrib_badge = 'badge badge-secondary';
            $contrib_icon = 'fa-ban text-secondary';
            $contrib_guidance = 'Testcase này đã được sinh viên khác đóng góp trước đó trong kho chung của lớp.';
        }
        // Priority 6: Code not passing testcase
        else if (!empty($first['pending_code_fix'])) {
            $cancontributelatest = false;
            $contrib_title = 'Hãy sửa mã nguồn và chạy lại trước khi đóng góp';
            $contrib_badge = 'badge badge-warning text-dark';
            $contrib_icon = 'fa-wrench text-warning';
            $contrib_guidance = 'Hãy sửa mã nguồn và chạy lại trước khi đóng góp.';
        }
        // Priority 7: Ineligible
        else {
            $cancontributelatest = false;
            $contrib_title = 'Chưa đủ điều kiện đóng góp';
            $contrib_badge = 'badge badge-secondary';
            $contrib_icon = 'fa-info-circle text-muted';
            $contrib_guidance = 'Chương trình chưa thỏa mãn toàn bộ các điều kiện để đề xuất đóng góp. Vui lòng kiểm tra lại kết quả đối chiếu.';
        }

        // Informative note when user contributed an earlier run with the same input
        if (!$direct_contrib && $earlier_input_contrib) {
            $earlier_run_id = (int) ($earlier_input_contrib['run_id'] ?? 0);
            $earlier_exp = trim((string) ($earlier_input_contrib['expected_output'] ?? ''));
            $latest_earlier_notice = get_string(
                'earlier_contrib_notice_text',
                'local_testcase_exchange',
                (object)['run_id' => $earlier_run_id, 'expected' => $earlier_exp]
            );
        }

        $latestrun = array_merge($first, [
            // Run outcome fields
            'run_result_title' => $run_res_title,
            'run_result_guidance' => $run_res_guidance,
            'run_result_badge' => $run_res_badge,
            'run_result_icon' => $run_res_icon,
            // Contribution status fields
            'contrib_title' => $contrib_title,
            'contrib_badge' => $contrib_badge,
            'contrib_guidance' => $contrib_guidance,
            'contrib_icon' => $contrib_icon,
            'can_contribute_latest' => $cancontributelatest,
            'has_contrib_link' => $hascontriblink,
            'contrib_id' => $contribid,
            'earlier_contrib_notice' => $latest_earlier_notice,
        ]);
    }"""

content_norm = content_norm[:start_idx] + new_latest_block + content_norm[end_idx:]

with open(file_path, 'w', encoding='utf-8') as f:
    f.write(content_norm)

print("Successfully patched local_testcase_exchange/index.php")
