with open('local_testcase_exchange/templates/dashboard.mustache', 'r', encoding='utf-8') as f:
    content = f.read()

# 1. Reward card in overview
target1 = """            <div class="col-sm-6 col-lg-3 mb-3">
                <div class="card h-100 border-0 bg-light p-3 shadow-sm" style="border-left: 4px solid #7c3aed !important;">
                    <div class="text-muted small font-weight-bold text-uppercase">Phần thưởng đã cấp</div>
                    <div class="h4 font-weight-bold mb-1 mt-2 text-dark">{{overview_stats.claimed_rewards}} / {{overview_stats.total_rewards}}</div>
                    <small class="text-muted">{{overview_stats.unclaimed_rewards}} testcase chưa nhận</small>
                </div>
            </div>"""

replacement1 = """            <div class="col-sm-6 col-lg-3 mb-3">
                <div class="card h-100 border-0 bg-light p-3 shadow-sm" style="border-left: 4px solid #7c3aed !important;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-muted small font-weight-bold text-uppercase">{{#str}} rewards_granted_title, local_testcase_exchange {{/str}}</div>
                        <span class="badge badge-light border text-muted small">{{overview_stats.scope_label}}</span>
                    </div>
                    <div class="h4 font-weight-bold mb-1 mt-2 text-dark">{{overview_stats.claimed_rewards}} <small class="text-muted" style="font-size: 0.95rem;">/ {{overview_stats.total_rewards}} {{#str}} claimed_short, local_testcase_exchange {{/str}}</small></div>
                    <small class="text-muted">{{overview_stats.unclaimed_rewards}} {{#str}} unclaimed_rewards_desc, local_testcase_exchange {{/str}}</small>
                </div>
            </div>"""

assert target1 in content, "target1 not found"
content = content.replace(target1, replacement1, 1)

# 2. Review modal options & help text
target2 = """                                    <select class="custom-select font-weight-bold" name="decision" required>
                                        <option value="" {{^status}}selected{{/status}}>-- Chọn quyết định --</option>
                                        <option value="approved" {{#is_approved}}selected{{/is_approved}}>✓ Chấp thuận (Approve) — Bổ sung vào kho & cấp thưởng</option>
                                        <option value="needs_explanation" {{#is_pending}}selected{{/is_pending}}>⚠️ Cần giải trình thêm (Needs Explanation)</option>
                                        <option value="rejected" {{#is_rejected}}selected{{/is_rejected}}>✗ Từ chối (Reject)</option>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <label class="font-weight-bold text-dark small mb-1">
                                        Đánh giá chất lượng (Tùy chọn):
                                    </label>
                                    <select class="custom-select" name="rating">
                                        <option value="0">Chưa chấm sao...</option>
                                        <option value="5" {{#is_rating_5}}selected{{/is_rating_5}}>⭐⭐⭐⭐⭐ 5 sao - Xuất sắc</option>
                                        <option value="4" {{#is_rating_4}}selected{{/is_rating_4}}>⭐⭐⭐⭐ 4 sao - Tốt</option>
                                        <option value="3" {{#is_rating_3}}selected{{/is_rating_3}}>⭐⭐⭐ 3 sao - Đạt chuẩn</option>
                                        <option value="2" {{#is_rating_2}}selected{{/is_rating_2}}>⭐⭐ 2 sao - Trung bình</option>
                                        <option value="1" {{#is_rating_1}}selected{{/is_rating_1}}>⭐ 1 sao - Cần cải thiện</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group mb-3">
                                <label class="font-weight-bold text-dark small mb-1">
                                    Nhận xét & phản hồi sư phạm cho sinh viên:
                                </label>
                                <textarea class="form-control" name="review_note" rows="3" placeholder="Nhập nhận xét chi tiết, góp ý sư phạm hoặc lý do chấp thuận/từ chối...">{{teacher_comment}}</textarea>
                            </div>

                            <div class="d-flex justify-content-end align-items-center" style="gap: 8px;">
                                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Đóng</button>
                                <button type="submit" class="btn btn-primary font-weight-bold btn-sm">
                                    <i class="fa fa-save mr-1"></i>Lưu quyết định thẩm định
                                </button>
                            </div>"""

replacement2 = """                                    <select class="custom-select font-weight-bold" name="decision" required>
                                        <option value="" {{^status}}selected{{/status}}>-- {{#str}} select_decision, local_testcase_exchange {{/str}} --</option>
                                        <option value="approved" {{#is_approved}}selected{{/is_approved}}>✓ {{#str}} decision_approved, local_testcase_exchange {{/str}}</option>
                                        <option value="needs_explanation" {{#is_pending}}selected{{/is_pending}}>⚠️ {{#str}} decision_needs_explanation, local_testcase_exchange {{/str}}</option>
                                        <option value="rejected" {{#is_rejected}}selected{{/is_rejected}}>✗ {{#str}} decision_rejected, local_testcase_exchange {{/str}}</option>
                                    </select>
                                    <small class="form-text text-muted mt-1">
                                        {{#str}} decision_help_text, local_testcase_exchange {{/str}}
                                    </small>
                                </div>
                                <div class="col-md-5">
                                    <label class="font-weight-bold text-dark small mb-1">
                                        {{#str}} quality_rating, local_testcase_exchange {{/str}}:
                                    </label>
                                    <select class="custom-select" name="rating">
                                        <option value="0">{{#str}} rating_none, local_testcase_exchange {{/str}}</option>
                                        <option value="5" {{#is_rating_5}}selected{{/is_rating_5}}>⭐⭐⭐⭐⭐ 5 sao - {{#str}} rating_5, local_testcase_exchange {{/str}}</option>
                                        <option value="4" {{#is_rating_4}}selected{{/is_rating_4}}>⭐⭐⭐⭐ 4 sao - {{#str}} rating_4, local_testcase_exchange {{/str}}</option>
                                        <option value="3" {{#is_rating_3}}selected{{/is_rating_3}}>⭐⭐⭐ 3 sao - {{#str}} rating_3, local_testcase_exchange {{/str}}</option>
                                        <option value="2" {{#is_rating_2}}selected{{/is_rating_2}}>⭐⭐ 2 sao - {{#str}} rating_2, local_testcase_exchange {{/str}}</option>
                                        <option value="1" {{#is_rating_1}}selected{{/is_rating_1}}>⭐ 1 sao - {{#str}} rating_1, local_testcase_exchange {{/str}}</option>
                                    </select>
                                </div>
                            </div>

                            <div class="form-group mb-3">
                                <label class="font-weight-bold text-dark small mb-1">
                                    {{#str}} pedagogical_feedback, local_testcase_exchange {{/str}}:
                                </label>
                                <textarea class="form-control" name="review_note" rows="3" placeholder="{{#str}} teacher_review_feedback_placeholder, local_testcase_exchange {{/str}}">{{teacher_comment}}</textarea>
                            </div>

                            <div class="d-flex justify-content-end align-items-center" style="gap: 8px;">
                                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">{{#str}} close, local_testcase_exchange {{/str}}</button>
                                <button type="submit" class="btn btn-primary font-weight-bold btn-sm">
                                    <i class="fa fa-save mr-1"></i>{{#str}} save_decision, local_testcase_exchange {{/str}}
                                </button>
                            </div>"""

assert target2 in content, "target2 not found"
content = content.replace(target2, replacement2, 1)

# 3. Seed testcase guidance replacing XML guidance
target3 = """                <!-- Ghi nhận tính năng nhập XML -->
                <div class="alert alert-light border small text-muted mb-0">
                    <i class="fa fa-info-circle mr-1 text-info"></i><strong>Nhập từ tệp XML:</strong> Để nạp hạt giống từ đề bài, thầy/cô xuất tệp XML câu hỏi từ Ngân hàng câu hỏi Moodle rồi tải lên form bên dưới.
                </div>"""

replacement3 = """                <!-- Hướng dẫn quản lý testcase mầm -->
                <div class="alert alert-light border small text-muted mb-0">
                    <i class="fa fa-info-circle mr-1 text-info"></i><strong>{{#str}} seed_testcases_title, local_testcase_exchange {{/str}}:</strong> {{#str}} seed_guidance_help, local_testcase_exchange {{/str}}
                </div>"""

assert target3 in content, "target3 not found"
content = content.replace(target3, replacement3, 1)

# 4. Bank table contributor column header and cell
target4 = """                                <th style="width: 140px;">Đóng góp bởi</th>"""
replacement4 = """                                <th style="width: 140px;">{{#str}} contributed_by, local_testcase_exchange {{/str}}</th>"""
assert target4 in content, "target4 not found"
content = content.replace(target4, replacement4, 1)

target5 = """                                <td><span class="badge badge-light border">{{contributor_name}}</span></td>"""
replacement5 = """                                <td><span class="badge badge-light border">{{#contributor_name}}{{contributor_name}}{{/contributor_name}}{{^contributor_name}}{{student_name}}{{/contributor_name}}</span></td>"""
assert target5 in content, "target5 not found"
content = content.replace(target5, replacement5, 1)

# 5. Back to quiz button
target6 = """        {{#has_return_url}}
        <a class="btn btn-outline-secondary btn-sm font-weight-bold" href="{{return_url}}">
            <i class="fa fa-arrow-left mr-1"></i>Quay lại làm bài Quiz
        </a>
        {{/has_return_url}}"""

replacement6 = """        {{#has_return_url}}
        <a class="btn btn-outline-secondary btn-sm font-weight-bold" href="{{return_url}}">
            <i class="fa fa-arrow-left mr-1"></i>{{#str}} backtocurrentquiz, local_testcase_exchange {{/str}}
        </a>
        {{/has_return_url}}"""

assert target6 in content, "target6 not found"
content = content.replace(target6, replacement6, 1)

# 6. Desktop table rows with is_extra_run and toggle button
target7 = """                            {{#runs}}
                            <tr id="run-{{id}}">"""

replacement7 = """                            {{#runs}}
                            <tr id="run-{{id}}" class="{{#is_extra_run}}tce-older-run d-none{{/is_extra_run}}">"""

assert target7 in content, "target7 not found"
content = content.replace(target7, replacement7, 1)

target8 = """                    </table>
                </div>

                <!-- Mobile Card View (Hiển thị 5 lượt gần nhất, có nút Xem thêm) -->"""

replacement8 = """                    </table>
                    {{#has_extra_runs}}
                    <div class="p-2 border-top bg-light text-center d-none d-md-block">
                        <button type="button" class="btn btn-outline-secondary btn-sm font-weight-bold btn-toggle-older-runs" onclick="toggleOlderRuns()">
                            <i class="fa fa-chevron-down mr-1"></i>{{#str}} show_more_runs, local_testcase_exchange, {{extra_runs_count}} {{/str}}
                        </button>
                    </div>
                    {{/has_extra_runs}}
                </div>

                <!-- Mobile Card View (Hiển thị 5 lượt gần nhất, có nút Xem thêm) -->"""

assert target8 in content, "target8 not found"
content = content.replace(target8, replacement8, 1)

with open('local_testcase_exchange/templates/dashboard.mustache', 'w', encoding='utf-8') as f:
    f.write(content)

print("Updated dashboard.mustache template successfully")
