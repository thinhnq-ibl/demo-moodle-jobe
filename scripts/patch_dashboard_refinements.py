with open('local_testcase_exchange/templates/dashboard.mustache', 'r', encoding='utf-8') as f:
    content = f.read()

# 1. Overview Stat cards 2x2 on mobile
target1 = """        <div class="row">
            <div class="col-sm-6 col-lg-3 mb-3">
                <div class="card h-100 border-0 bg-light p-3 shadow-sm" style="border-left: 4px solid #0f6cbf !important;">
                    <div class="text-muted small font-weight-bold text-uppercase">Trạng thái bài Quiz</div>
                    <div class="h4 font-weight-bold mb-1 mt-2 text-dark">
                        {{#overview_stats.is_quiz_enabled}}
                        <span class="text-success"><i class="fa fa-check-circle mr-1"></i>Đang bật</span>
                        {{/overview_stats.is_quiz_enabled}}
                        {{^overview_stats.is_quiz_enabled}}
                        <span class="text-secondary"><i class="fa fa-pause-circle mr-1"></i>Tắt</span>
                        {{/overview_stats.is_quiz_enabled}}
                    </div>
                    <small class="text-muted">Áp dụng cho Quiz hiện tại</small>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3 mb-3">
                <div class="card h-100 border-0 bg-light p-3 shadow-sm" style="border-left: 4px solid #16a34a !important;">
                    <div class="text-muted small font-weight-bold text-uppercase">Lượt chạy thử nghiệm</div>
                    <div class="h4 font-weight-bold mb-1 mt-2 text-dark">{{overview_stats.total_runs}}</div>
                    <small class="text-muted">Tổng số lần sinh viên đã chạy</small>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3 mb-3">
                <div class="card h-100 border-0 bg-light p-3 shadow-sm" style="border-left: 4px solid #f59e0b !important;">
                    <div class="text-muted small font-weight-bold text-uppercase">Đóng góp testcase</div>
                    <div class="h4 font-weight-bold mb-1 mt-2 text-dark">
                        {{overview_stats.total_contributions}}
                        {{#overview_stats.pending_contributions}}
                        <span class="badge badge-warning text-dark ml-1" style="font-size: 0.75rem;">{{overview_stats.pending_contributions}} chờ</span>
                        {{/overview_stats.pending_contributions}}
                    </div>
                    <small class="text-muted">{{overview_stats.approved_contributions}} đã duyệt &bull; {{overview_stats.rejected_contributions}} từ chối</small>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3 mb-3">
                <div class="card h-100 border-0 bg-light p-3 shadow-sm" style="border-left: 4px solid #7c3aed !important;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-muted small font-weight-bold text-uppercase">{{#str}} rewards_granted_title, local_testcase_exchange {{/str}}</div>
                        <span class="badge badge-light border text-muted small">{{overview_stats.scope_label}}</span>
                    </div>
                    <div class="h4 font-weight-bold mb-1 mt-2 text-dark">{{overview_stats.claimed_rewards}} <small class="text-muted" style="font-size: 0.95rem;">/ {{overview_stats.total_rewards}} {{#str}} claimed_short, local_testcase_exchange {{/str}}</small></div>
                    <small class="text-muted">{{overview_stats.unclaimed_rewards}} {{#str}} unclaimed_rewards_desc, local_testcase_exchange {{/str}}</small>
                </div>
            </div>
        </div>"""

replacement1 = """        <div class="row mb-2">
            <div class="col-6 col-lg-3 mb-2 px-1">
                <div class="card h-100 border-0 bg-light p-2 p-md-3 shadow-sm tce-stat-card-mobile" style="border-left: 4px solid #0f6cbf !important;">
                    <div class="text-muted small font-weight-bold text-uppercase text-truncate">Trạng thái Quiz</div>
                    <div class="h4 font-weight-bold mb-1 mt-1 text-dark">
                        {{#overview_stats.is_quiz_enabled}}
                        <span class="text-success"><i class="fa fa-check-circle mr-1"></i>Bật</span>
                        {{/overview_stats.is_quiz_enabled}}
                        {{^overview_stats.is_quiz_enabled}}
                        <span class="text-secondary"><i class="fa fa-pause-circle mr-1"></i>Tắt</span>
                        {{/overview_stats.is_quiz_enabled}}
                    </div>
                    <small class="text-muted d-none d-md-block">Áp dụng cho Quiz hiện tại</small>
                </div>
            </div>
            <div class="col-6 col-lg-3 mb-2 px-1">
                <div class="card h-100 border-0 bg-light p-2 p-md-3 shadow-sm tce-stat-card-mobile" style="border-left: 4px solid #16a34a !important;">
                    <div class="text-muted small font-weight-bold text-uppercase text-truncate">Lượt chạy thử</div>
                    <div class="h4 font-weight-bold mb-1 mt-1 text-dark">{{overview_stats.total_runs}}</div>
                    <small class="text-muted d-none d-md-block">Tổng số lần sinh viên đã chạy</small>
                </div>
            </div>
            <div class="col-6 col-lg-3 mb-2 px-1">
                <div class="card h-100 border-0 bg-light p-2 p-md-3 shadow-sm tce-stat-card-mobile" style="border-left: 4px solid #f59e0b !important;">
                    <div class="text-muted small font-weight-bold text-uppercase text-truncate">Đóng góp</div>
                    <div class="h4 font-weight-bold mb-1 mt-1 text-dark">
                        {{overview_stats.total_contributions}}
                        {{#overview_stats.pending_contributions}}
                        <span class="badge badge-warning text-dark ml-1" style="font-size: 0.72rem;">{{overview_stats.pending_contributions}} chờ</span>
                        {{/overview_stats.pending_contributions}}
                    </div>
                    <small class="text-muted d-none d-md-block">{{overview_stats.approved_contributions}} đã duyệt &bull; {{overview_stats.rejected_contributions}} từ chối</small>
                </div>
            </div>
            <div class="col-6 col-lg-3 mb-2 px-1">
                <div class="card h-100 border-0 bg-light p-2 p-md-3 shadow-sm tce-stat-card-mobile" style="border-left: 4px solid #7c3aed !important;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-muted small font-weight-bold text-uppercase text-truncate">{{#str}} rewards_granted_title, local_testcase_exchange {{/str}}</div>
                        <span class="badge badge-light border text-muted small d-none d-md-inline">{{overview_stats.scope_label}}</span>
                    </div>
                    <div class="h4 font-weight-bold mb-1 mt-1 text-dark">{{overview_stats.claimed_rewards}} <small class="text-muted" style="font-size: 0.85rem;">/ {{overview_stats.total_rewards}}</small></div>
                    <small class="text-muted d-none d-md-block">{{overview_stats.unclaimed_rewards}} {{#str}} unclaimed_rewards_desc, local_testcase_exchange {{/str}}</small>
                </div>
            </div>
        </div>"""

assert target1 in content, "target1 not found"
content = content.replace(target1, replacement1, 1)

# 2. Review modal labels & values
target2 = """                                <div>
                                    <span class="text-muted small">Phân loại:</span>
                                    <span class="badge badge-light border text-dark ml-1">{{category}}</span>
                                </div>"""

replacement2 = """                                <div>
                                    <span class="text-muted small">{{#str}} category, local_testcase_exchange {{/str}}:</span>
                                    <span class="badge badge-light border text-dark ml-1">{{category_label}}</span>
                                </div>"""

assert target2 in content, "target2 not found"
content = content.replace(target2, replacement2, 1)

target3 = """                        <div class="row mb-3">
                            <div class="col-md-4 mb-2 mb-md-0">
                                <div class="small font-weight-bold text-dark mb-1">
                                    <i class="fa fa-terminal text-muted mr-1"></i>Dữ liệu đầu vào (Input):
                                </div>
                                <pre class="font-monospace bg-light p-2 border rounded text-dark mb-0" style="max-height: 140px; overflow-y: auto; white-space: pre-wrap; word-break: break-all;">{{input_normalized}}</pre>
                            </div>
                            <div class="col-md-4 mb-2 mb-md-0">
                                <div class="small font-weight-bold text-dark mb-1">
                                    <i class="fa fa-bullseye text-muted mr-1"></i>Expected Output (Sinh viên):
                                </div>
                                <pre class="font-monospace bg-light p-2 border rounded text-dark mb-0" style="max-height: 140px; overflow-y: auto; white-space: pre-wrap; word-break: break-all;">{{expected_output_display}}</pre>
                            </div>
                            <div class="col-md-4">
                                <div class="small font-weight-bold text-dark mb-1">
                                    <i class="fa fa-check text-muted mr-1"></i>Lời giải tham chiếu (Oracle):
                                </div>
                                <pre class="font-monospace bg-light p-2 border rounded text-dark mb-0" style="max-height: 140px; overflow-y: auto; white-space: pre-wrap; word-break: break-all;">{{oracle_output}}</pre>
                                <div class="mt-1 small"><span class="badge badge-success"><i class="fa fa-check mr-1"></i>{{oracle_outcome}}</span></div>
                            </div>
                        </div>"""

replacement3 = """                        <div class="row mb-3">
                            <div class="col-md-4 mb-2 mb-md-0">
                                <div class="small font-weight-bold text-dark mb-1">
                                    <i class="fa fa-terminal text-muted mr-1"></i>{{#str}} input_data_label, local_testcase_exchange {{/str}}:
                                </div>
                                <pre class="font-monospace bg-light p-2 border rounded text-dark mb-0" style="max-height: 140px; overflow-y: auto; white-space: pre-wrap; word-break: break-all;">{{input_normalized}}</pre>
                            </div>
                            <div class="col-md-4 mb-2 mb-md-0">
                                <div class="small font-weight-bold text-dark mb-1">
                                    <i class="fa fa-bullseye text-muted mr-1"></i>{{#str}} student_expected_output_label, local_testcase_exchange {{/str}}:
                                </div>
                                <pre class="font-monospace bg-light p-2 border rounded text-dark mb-0" style="max-height: 140px; overflow-y: auto; white-space: pre-wrap; word-break: break-all;">{{expected_output_display}}</pre>
                            </div>
                            <div class="col-md-4">
                                <div class="small font-weight-bold text-dark mb-1">
                                    <i class="fa fa-check text-muted mr-1"></i>{{#str}} reference_solution_output_label, local_testcase_exchange {{/str}}:
                                </div>
                                <pre class="font-monospace bg-light p-2 border rounded text-dark mb-0" style="max-height: 140px; overflow-y: auto; white-space: pre-wrap; word-break: break-all;">{{oracle_output}}</pre>
                                <div class="mt-1 small"><span class="badge badge-success"><i class="fa fa-check mr-1"></i>{{oracle_outcome_label}}</span></div>
                            </div>
                        </div>"""

assert target3 in content, "target3 not found"
content = content.replace(target3, replacement3, 1)

# 3. Student header compact mobile
target4 = """<!-- Header & Context Navigation -->
<div class="d-flex flex-wrap justify-content-between align-items-center mb-3 pb-2 border-bottom">
    <div>
        <h3 class="font-weight-bold text-dark mb-0">
            <i class="fa fa-flask text-primary mr-2"></i>{{#str}} studentview_title, local_testcase_exchange {{/str}}
        </h3>
        <div class="text-muted small mt-1">
            <span>{{current_course_name}}</span>
            {{#current_quiz_name}} &bull; <span>{{current_quiz_name}}</span>{{/current_quiz_name}}
        </div>
    </div>
    <div class="mt-2 mt-md-0 d-flex align-items-center flex-wrap" style="gap: 8px;">
        {{#show_course_switcher}}
        <div class="d-inline-flex align-items-center mr-2">
            <label for="switchCourseSelect" class="small text-muted mb-0 mr-1 text-nowrap">{{#str}} switchcourse, local_testcase_exchange {{/str}}:</label>
            <select id="switchCourseSelect" class="custom-select custom-select-sm" style="max-width: 260px;" onchange="if (this.value) window.location.href=this.value;">
                {{#available_courses}}
                <option value="{{switch_url}}" {{#is_current}}selected{{/is_current}}>{{shortname}} - {{fullname}}</option>
                {{/available_courses}}
            </select>
        </div>
        {{/show_course_switcher}}

        {{#has_return_url}}
        <a class="btn btn-outline-secondary btn-sm font-weight-bold" href="{{return_url}}">
            <i class="fa fa-arrow-left mr-1"></i>{{#str}} backtocurrentquiz, local_testcase_exchange {{/str}}
        </a>
        {{/has_return_url}}
    </div>
</div>"""

replacement4 = """<!-- Header & Context Navigation -->
<div class="tce-header-bar d-flex flex-wrap justify-content-between align-items-center mb-2 pb-2 border-bottom">
    <div class="tce-header-title" style="min-width: 0; max-width: 100%;">
        <h4 class="font-weight-bold text-dark mb-0 d-flex align-items-center">
            <i class="fa fa-flask text-primary mr-2"></i>
            <span class="text-truncate">{{#str}} studentview_title, local_testcase_exchange {{/str}}</span>
        </h4>
        <div class="text-muted small tce-header-subtitle text-truncate mt-1" style="max-width: 480px;">
            <span class="font-weight-bold">{{current_course_shortname}}</span> &bull; <span>{{current_quiz_name}}</span>
        </div>
    </div>
    <div class="tce-header-actions mt-2 mt-sm-0 d-flex align-items-center" style="gap: 6px;">
        {{#show_course_switcher}}
        <div class="d-none d-md-inline-flex align-items-center mr-1">
            <label for="switchCourseSelect" class="small text-muted mb-0 mr-1 text-nowrap">{{#str}} switchcourse, local_testcase_exchange {{/str}}:</label>
            <select id="switchCourseSelect" class="custom-select custom-select-sm" style="max-width: 220px;" onchange="if (this.value) window.location.href=this.value;">
                {{#available_courses}}
                <option value="{{switch_url}}" {{#is_current}}selected{{/is_current}}>{{shortname}} - {{fullname}}</option>
                {{/available_courses}}
            </select>
        </div>
        {{/show_course_switcher}}

        {{#has_return_url}}
        <a class="btn btn-outline-secondary btn-sm font-weight-bold text-nowrap py-1 px-2" href="{{return_url}}">
            <i class="fa fa-arrow-left mr-1"></i>{{#str}} backtocurrentquiz, local_testcase_exchange {{/str}}
        </a>
        {{/has_return_url}}
    </div>
</div>"""

assert target4 in content, "target4 not found"
content = content.replace(target4, replacement4, 1)

# 4. Student nav tabs with horizontal scrolling
target5 = """<ul class="nav nav-tabs font-weight-bold mb-3" role="tablist">"""
replacement5 = """<ul class="nav nav-tabs font-weight-bold mb-3 tce-nav-tabs-scroll" role="tablist">"""
assert target5 in content, "target5 not found"
content = content.replace(target5, replacement5, 1)

# 5. Question selector label and test type
target6 = """                <label for="question" class="font-weight-bold text-dark mb-1 mb-sm-0 mr-2 text-nowrap">
                    <i class="fa fa-book text-muted mr-1"></i>Bài tập:
                </label>"""
replacement6 = """                <label for="question" class="font-weight-bold text-dark mb-1 mb-sm-0 mr-2 text-nowrap">
                    <i class="fa fa-book text-muted mr-1"></i>{{#str}} question, local_testcase_exchange {{/str}}:
                </label>"""
assert target6 in content, "target6 not found"
content = content.replace(target6, replacement6, 1)

target7 = """                <span class="badge badge-light border text-muted">
                    Kiểu kiểm thử: <strong class="text-dark">{{coderunnertype_display}}</strong>
                </span>"""
replacement7 = """                <span class="badge badge-light border text-muted">
                    {{#str}} test_type_label, local_testcase_exchange {{/str}}: <strong class="text-dark">{{coderunnertype_display}}</strong>
                </span>"""
assert target7 in content, "target7 not found"
content = content.replace(target7, replacement7, 1)

# 6. Question prompt collapsible toggle button (never wrap)
target8 = """                <button class="btn btn-sm btn-link text-muted p-0 font-weight-bold" type="button" data-toggle="collapse" data-target="#questionPromptBody" aria-expanded="true" aria-controls="questionPromptBody" id="btnToggleQuestionPrompt">
                    <small id="lblToggleQuestionPrompt">Thu gọn</small>
                </button>"""
replacement8 = """                <button class="btn btn-sm btn-link text-muted p-0 font-weight-bold" type="button" data-toggle="collapse" data-target="#questionPromptBody" aria-expanded="true" aria-controls="questionPromptBody" id="btnToggleQuestionPrompt" style="white-space: nowrap !important; text-decoration: none; flex-shrink: 0;">
                    <small id="lblToggleQuestionPrompt" class="badge badge-light border px-2 py-1 text-nowrap" style="white-space: nowrap !important;">{{#str}} collapse, local_testcase_exchange {{/str}}</small>
                </button>"""
assert target8 in content, "target8 not found"
content = content.replace(target8, replacement8, 1)

# 7. Form labels deduplication and title
target9 = """                            <span class="font-weight-bold text-dark">
                                <i class="fa fa-flask text-success mr-1"></i>Định nghĩa Testcase kiểm thử
                            </span>"""
replacement9 = """                            <span class="font-weight-bold text-dark">
                                <i class="fa fa-flask text-success mr-1"></i>{{#str}} define_testcase_title, local_testcase_exchange {{/str}}
                            </span>"""
assert target9 in content, "target9 not found"
content = content.replace(target9, replacement9, 1)

target10 = """                                <label for="predicted_output" class="small font-weight-bold text-dark mb-1">
                                    {{#str}} predictedoutput, local_testcase_exchange {{/str}} (Kết quả kỳ vọng): <span class="text-danger">*</span>
                                </label>"""
replacement10 = """                                <label for="predicted_output" class="small font-weight-bold text-dark mb-1">
                                    {{#str}} clean_predicted_label, local_testcase_exchange {{/str}}: <span class="text-danger">*</span>
                                </label>"""
assert target10 in content, "target10 not found"
content = content.replace(target10, replacement10, 1)

target11 = """                                <label for="purpose" class="small font-weight-bold text-dark mb-1">
                                    {{#str}} purpose, local_testcase_exchange {{/str}} (Mục đích kiểm thử): <span class="text-danger">*</span>
                                </label>"""
replacement11 = """                                <label for="purpose" class="small font-weight-bold text-dark mb-1">
                                    {{#str}} clean_purpose_label, local_testcase_exchange {{/str}}: <span class="text-danger">*</span>
                                </label>"""
assert target11 in content, "target11 not found"
content = content.replace(target11, replacement11, 1)

with open('local_testcase_exchange/templates/dashboard.mustache', 'w', encoding='utf-8') as f:
    f.write(content)

print("Updated dashboard.mustache successfully")
