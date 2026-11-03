# -*- coding: utf-8 -*-
import os

dash_file = 'local_testcase_exchange/templates/dashboard.mustache'
with open(dash_file, 'r', encoding='utf-8') as f:
    dash = f.read().replace('\r\n', '\n')

# 1. Un-truncate rewards card title
old_card_head = """                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-muted small font-weight-bold text-uppercase text-truncate">{{#str}} rewards_granted_title, local_testcase_exchange {{/str}}</div>
                        <span class="badge badge-light border text-muted small d-none d-md-inline">{{overview_stats.scope_label}}</span>
                    </div>"""
new_card_head = """                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <div class="text-muted small font-weight-bold text-uppercase" style="line-height: 1.2;">{{#str}} rewards_granted_title, local_testcase_exchange {{/str}}</div>
                        <span class="badge badge-light border text-muted small d-none d-md-inline">{{overview_stats.scope_label}}</span>
                    </div>"""
if old_card_head in dash:
    dash = dash.replace(old_card_head, new_card_head, 1)
    print("Patched rewards card title")

# 2. Form dirty warning & Latest result card
old_latest_start = """        {{#has_latest_run}}
        {{#latest_run}}
        <div class="tce-latest-result-card card p-3 shadow-sm mb-4" id="tceLatestResultCard">"""

new_latest_start = """        {{#has_latest_run}}
        {{#latest_run}}
        <div id="tceFormDirtyWarning" class="alert alert-warning py-2 px-3 mb-3 d-none border-warning shadow-sm" style="background-color: #fffbeb; color: #92400e;">
            <i class="fa fa-exclamation-triangle mr-1 text-warning"></i><strong>{{#str}} form_dirty_warning, local_testcase_exchange {{/str}}</strong> Hãy bấm "Chạy thử testcase" để nhận kết quả mới nhất.
        </div>
        <div class="tce-latest-result-card card p-3 shadow-sm mb-4" id="tceLatestResultCard">"""

if old_latest_start in dash:
    dash = dash.replace(old_latest_start, new_latest_start, 1)
    print("Patched form dirty warning before tceLatestResultCard")

# 3. earlier_contrib_notice in Latest result card
old_guidance = """                        <p class="mb-2 text-dark small" style="color: #334155; line-height: 1.45;">
                            {{contrib_guidance}}
                        </p>
                        <div class="d-flex align-items-center justify-content-between pt-1 border-top">"""

new_guidance = """                        <p class="mb-2 text-dark small" style="color: #334155; line-height: 1.45;">
                            {{contrib_guidance}}
                        </p>
                        {{#earlier_contrib_notice}}
                        <div class="alert alert-info py-1 px-2 mt-2 mb-2 small" style="font-size: 0.82rem; background-color: #f0fdf4; border-color: #bbf7d0; color: #166534;">
                            <i class="fa fa-info-circle mr-1"></i>{{earlier_contrib_notice}}
                        </div>
                        {{/earlier_contrib_notice}}
                        <div class="d-flex align-items-center justify-content-between pt-1 border-top">"""

if old_guidance in dash:
    dash = dash.replace(old_guidance, new_guidance, 1)
    print("Patched earlier_contrib_notice in Latest result card")

# 4. earlier_contrib_notice in Desktop run history table
old_desk_status = """                                    <span class="{{display_status_class}}">{{display_status}}</span>
                                    {{#pending_code_fix}}"""

new_desk_status = """                                    <span class="{{display_status_class}}">{{display_status}}</span>
                                    {{#earlier_contrib_notice}}
                                    <div class="small text-info text-wrap mt-1 text-left" style="font-size: 0.75rem; line-height: 1.25;">
                                        <i class="fa fa-info-circle mr-1"></i>{{earlier_contrib_notice}}
                                    </div>
                                    {{/earlier_contrib_notice}}
                                    {{#pending_code_fix}}"""

if old_desk_status in dash:
    dash = dash.replace(old_desk_status, new_desk_status, 1)
    print("Patched earlier_contrib_notice in Desktop table")

# 5. earlier_contrib_notice in Mobile run history card
old_mob_status = """                                <span class="{{display_status_class}} small">{{display_status}}</span>
                                {{/can_contribute}}"""

new_mob_status = """                                <span class="{{display_status_class}} small">{{display_status}}</span>
                                {{#earlier_contrib_notice}}
                                <div class="small text-info mt-1" style="font-size: 0.75rem;">
                                    <i class="fa fa-info-circle mr-1"></i>{{earlier_contrib_notice}}
                                </div>
                                {{/earlier_contrib_notice}}
                                {{/can_contribute}}"""

if old_mob_status in dash:
    dash = dash.replace(old_mob_status, new_mob_status, 1)
    print("Patched earlier_contrib_notice in Mobile card")

# 6. Event listeners in script
old_script_tail = """    // Smooth scroll to latest result card if user just ran a test"""
new_script_tail = """    // Form dirty tracking
    function markFormDirty() {
        var banner = document.getElementById('tceFormDirtyWarning');
        if (banner) {
            banner.classList.remove('d-none');
        }
    }
    ['test_input', 'predicted_output', 'testcase_category', 'testcase_purpose', 'student_code'].forEach(function(id) {
        var el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', markFormDirty);
            el.addEventListener('change', markFormDirty);
        }
    });
    window.addEventListener('tce_editor_changed', markFormDirty);

    // Smooth scroll to latest result card if user just ran a test"""

if old_script_tail in dash:
    dash = dash.replace(old_script_tail, new_script_tail, 1)
    print("Patched markFormDirty script listeners in dashboard.mustache")

with open(dash_file, 'w', encoding='utf-8') as f:
    f.write(dash)

# Also update amd/src/editor.js to dispatch tce_editor_changed
editor_src_file = 'local_testcase_exchange/amd/src/editor.js'
with open(editor_src_file, 'r', encoding='utf-8') as f:
    ed_src = f.read().replace('\r\n', '\n')

old_ed_change = """                        textarea.value = editor.getValue();
                        // Dispatch standard input and change events for existing auto-save listeners.
                        textarea.dispatchEvent(new Event('input', { bubbles: true }));"""

new_ed_change = """                        textarea.value = editor.getValue();
                        // Dispatch standard input and change events for existing auto-save listeners.
                        textarea.dispatchEvent(new Event('input', { bubbles: true }));
                        window.dispatchEvent(new Event('tce_editor_changed'));"""

if old_ed_change in ed_src:
    ed_src = ed_src.replace(old_ed_change, new_ed_change, 1)
    with open(editor_src_file, 'w', encoding='utf-8') as f:
        f.write(ed_src)
    print("Patched amd/src/editor.js with tce_editor_changed")

# Also update amd/build/editor.min.js
editor_min_file = 'local_testcase_exchange/amd/build/editor.min.js'
with open(editor_min_file, 'r', encoding='utf-8') as f:
    ed_min = f.read()
if "textarea.dispatchEvent(new Event('input',{bubbles:!0}))" in ed_min:
    ed_min = ed_min.replace(
        "textarea.dispatchEvent(new Event('input',{bubbles:!0}))",
        "textarea.dispatchEvent(new Event('input',{bubbles:!0}));window.dispatchEvent(new Event('tce_editor_changed'))"
    )
    with open(editor_min_file, 'w', encoding='utf-8') as f:
        f.write(ed_min)
    print("Patched amd/build/editor.min.js with tce_editor_changed")
