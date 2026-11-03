with open('local_testcase_exchange/index.php', 'r', encoding='utf-8') as f:
    code = f.read()

# Replace the run decoration and latest run determination
target = """    // Step 2: Presentation decoration adhering to duplicate grouping & contribution gates
    foreach ($runs as &$run) {
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

        // 1. If student already contributed this testcase (group-wide)
        if ($user_contrib) {
            $status_lbl = $user_contrib['status_label'] ?? $user_contrib['status'];
            $run['display_status'] = 'Đã đóng góp #' . $user_contrib['id'] . ' (' . $status_lbl . ')';
            $run['display_status_class'] = 'badge badge-info p-1';
        }
        // 2. Duplicate against teacher seeds or peer bank
        else if ($existing_canonical) {
            $label = ($existing_canonical['type'] === 'seed') ?
                'Trùng testcase Đề thi' :
                ('Đã có trong kho (#' . $existing_canonical['id'] . ')');
            $run['display_status'] = $label;
            $run['display_status_class'] = 'badge badge-secondary p-1';
        }
        // 3. Unclaimed: Latest eligible run in group receives the contribution proposal button
        else if ($rep_id !== null && $run_id === $rep_id) {
            $run['can_contribute'] = true;
            $run['display_status'] = 'Đủ điều kiện đóng góp!';
            $run['display_status_class'] = 'badge badge-success p-1';
        }
        // 4. Unclaimed: Older eligible run in same group links to representative run
        else if ($run['is_eligible']) {
            $run['is_older_duplicate'] = true;
            $run['rep_id'] = $rep_id;
            $run['display_status'] = 'Trùng testcase ở lượt #' . $rep_id;
            $run['display_status_class'] = 'badge badge-secondary p-1';
        }
        // 5. Ineligible run
        else {
            if (!$run['oracle_valid']) {
                $run['display_status'] = 'Lỗi thực thi đề bài';
                $run['display_status_class'] = 'badge badge-danger p-1';
            } else if (!$run['expected_matches_oracle']) {
                $run['display_status'] = 'Chưa đóng góp được';
                $run['display_status_class'] = 'badge badge-warning text-dark p-1';
            } else {
                $run['display_status'] = 'Code chưa vượt qua testcase này';
                $run['display_status_class'] = 'badge badge-info p-1';
                $run['pending_code_fix'] = true;
                $run['pending_fix_message'] = 'Kết quả mong đợi đã được xác nhận. Chương trình hiện tại chưa vượt qua testcase này. Hãy sửa chương trình và chạy lại để đủ điều kiện đóng góp.';
            }

            // If an older/other run in this same group was eligible, let student easily jump to it
            if ($rep_id !== null && $rep_id !== $run_id) {
                $run['has_eligible_peer'] = true;
                $run['rep_id'] = $rep_id;
            }
        }

        $run['has_revision'] = !empty($run['revision_step']);
        $run['revision_badge'] = !empty($run['revision_step']) ? 'Code v' . $run['revision_step'] : null;

        // Định dạng thời gian tạo lượt chạy: ưu tiên timecreated int, không hiển thị 01/01/1970 nếu thiếu.
        $createdtimestamp = !empty($run['timecreated']) ? (int) $run['timecreated'] : (!empty($run['created_at']) ? strtotime($run['created_at']) : 0);
        if ($createdtimestamp > 0) {
            $run['created_at_formatted'] = date('H:i:s d/m/Y', $createdtimestamp);
        } else {
            $run['created_at_formatted'] = get_string('noinfo', 'local_testcase_exchange');
        }
        $run['has_code_snapshot'] = !empty($run['student_code_snapshot']);
    }
    unset($run);

    // Tách kết quả lần chạy mới nhất (Latest Run) phục vụ khối hiển thị phản hồi tức thì
    $latestrun = null;
    if (!empty($runs)) {
        $first = $runs[0];
        $stateclass = 'state-mismatch-student';
        $statustitle = '';
        $guidance = '';
        $cancontributelatest = false;

        if ($first['can_contribute']) {
            $stateclass = 'state-eligible';
            $statustitle = 'Kết quả khớp, đủ điều kiện đóng góp!';
            $guidance = 'Kết quả chương trình của bạn và kết quả kỳ vọng đều khớp với lời giải tham chiếu của giảng viên. Bạn đủ điều kiện gửi đóng góp testcase này cho lớp học.';
            $cancontributelatest = true;
        } else if (!empty($first['pending_code_fix'])) {
            $stateclass = 'state-mismatch-student';
            $statustitle = 'Lời giải tham chiếu khớp, mã sinh viên không khớp';
            $guidance = 'Kết quả mong đợi đã được xác nhận bởi lời giải tham chiếu. Tuy nhiên chương trình hiện tại của bạn chưa vượt qua testcase này. Hãy sửa lại mã nguồn và chạy lại để đủ điều kiện đóng góp.';
        } else if (!$first['expected_matches_oracle'] && $first['oracle_valid']) {
            $stateclass = 'state-mismatch-oracle';
            $statustitle = 'Kết quả kỳ vọng không khớp lời giải tham chiếu';
            $guidance = 'Kết quả kỳ vọng bạn dự đoán không khớp với kết quả từ lời giải tham chiếu. Vui lòng kiểm tra lại đề bài, dữ liệu đầu vào và kết quả kỳ vọng.';
        } else if (!$first['oracle_valid']) {
            $stateclass = 'state-error';
            $statustitle = 'Lỗi cú pháp / thực thi với đề bài';
            $guidance = 'Testcase gây ra lỗi thực thi hoặc không phù hợp với quy ước của câu hỏi. Vui lòng xem chi tiết lỗi kỹ thuật bên dưới.';
        } else if ($first['is_older_duplicate']) {
            $stateclass = 'state-duplicate';
            $statustitle = 'Trùng testcase ở lượt #' . $first['rep_id'];
            $guidance = 'Testcase này đã từng được chạy và đủ điều kiện ở lượt #' . $first['rep_id'] . '. Bạn có thể chuyển tới lượt đạt để đóng góp.';
        } else if (isset($existingfingerprints[$first['group_key']])) {
            $stateclass = 'state-duplicate';
            $statustitle = 'Trùng testcase với kho bài tập';
            $guidance = 'Testcase này trùng với testcase mẫu của đề thi hoặc đã được sinh viên khác đóng góp trước đó trong kho chung.';
        } else if (isset($user_contrib_by_fp[$first['group_key']])) {
            $stateclass = 'state-contributed';
            $statustitle = 'Đã gửi đóng góp';
            $guidance = 'Bạn đã gửi đóng góp testcase này thành công. Vui lòng theo dõi tiến độ thẩm định tại tab "Đóng góp của tôi".';
        } else {
            $stateclass = 'state-mismatch-student';
            $statustitle = 'Chưa đủ điều kiện đóng góp';
            $guidance = 'Chương trình chưa thỏa mãn toàn bộ các điều kiện để đề xuất đóng góp. Vui lòng kiểm tra lại kết quả đối chiếu.';
        }

        $latestrun = array_merge($first, [
            'state_class' => $stateclass,
            'status_title' => $statustitle,
            'guidance' => $guidance,
            'can_contribute_latest' => $cancontributelatest,
        ]);
    }"""

replacement = """    // Step 2: Presentation decoration adhering to duplicate grouping & contribution gates
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
        }

        // Run outcome display badge (independent from contribution status)
        if (!$run['oracle_valid']) {
            $run['run_outcome_badge'] = 'badge badge-danger';
            $run['run_outcome_label'] = 'Lỗi thực thi';
        } else if (!$run['expected_matches_oracle']) {
            $run['run_outcome_badge'] = 'badge badge-warning text-dark';
            $run['run_outcome_label'] = 'Không khớp tham chiếu';
        } else if (!$run['student_passed_test']) {
            $run['run_outcome_badge'] = 'badge badge-info';
            $run['run_outcome_label'] = 'Mã SV không khớp';
        } else {
            $run['run_outcome_badge'] = 'badge badge-success';
            $run['run_outcome_label'] = 'Khớp hoàn toàn';
        }

        $run['has_revision'] = !empty($run['revision_step']);
        $run['revision_badge'] = !empty($run['revision_step']) ? 'Code v' . $run['revision_step'] : null;

        // Định dạng thời gian tạo lượt chạy: ưu tiên timecreated int, không hiển thị 01/01/1970 nếu thiếu.
        $createdtimestamp = !empty($run['timecreated']) ? (int) $run['timecreated'] : (!empty($run['created_at']) ? strtotime($run['created_at']) : 0);
        if ($createdtimestamp > 0) {
            $run['created_at_formatted'] = date('H:i:s d/m/Y', $createdtimestamp);
        } else {
            $run['created_at_formatted'] = get_string('noinfo', 'local_testcase_exchange');
        }
        $run['has_code_snapshot'] = !empty($run['student_code_snapshot']);
    }
    unset($run);

    // Tách kết quả lần chạy mới nhất (Latest Run) phục vụ khối hiển thị phản hồi tức thì
    // Đảm bảo 2 nhóm thông tin ĐỘC LẬP: Kết quả chạy vs Trạng thái đóng góp
    $latestrun = null;
    if (!empty($runs)) {
        $first = $runs[0];
        $fp_key = $first['group_key'];
        $user_contrib = $user_contrib_by_fp[$fp_key] ?? null;
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

        // Priority 1: User's own contribution already submitted
        if ($user_contrib) {
            $cancontributelatest = false;
            $hascontriblink = true;
            $contribid = (int) $user_contrib['id'];
            $st = (string) $user_contrib['status'];

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
                $comment = !empty($user_contrib['teacher_comment']) ? ' Nhận xét: ' . $user_contrib['teacher_comment'] : '';
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
        ]);
    }"""

if target in code:
    code = code.replace(target, replacement, 1)
    with open('local_testcase_exchange/index.php', 'w', encoding='utf-8') as f:
        f.write(code)
    print("SUCCESS: index.php patched")
else:
    print("ERROR: target in index.php not found")
