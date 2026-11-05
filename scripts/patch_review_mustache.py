with open('local_testcase_exchange/templates/review.mustache', 'r', encoding='utf-8') as f:
    content = f.read()

target = """                            <select class="custom-select font-weight-bold" name="decision" required>
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

replacement = """                            <select class="custom-select font-weight-bold" name="decision" required>
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

assert target in content, "target not found"
content = content.replace(target, replacement, 1)

with open('local_testcase_exchange/templates/review.mustache', 'w', encoding='utf-8') as f:
    f.write(content)

print("Updated review.mustache successfully")
