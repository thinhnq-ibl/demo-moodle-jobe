# -*- coding: utf-8 -*-
with open('local_testcase_exchange/templates/dashboard.mustache', 'r', encoding='utf-8') as f:
    text = f.read().replace('\r\n', '\n')

# 1. Add tce-nav-tabs-scroll to student tabs
old_tabs = '<!-- Student Navigation Tabs -->\n<ul class="nav nav-tabs font-weight-bold mb-3" role="tablist">'
new_tabs = '<!-- Student Navigation Tabs -->\n<ul class="nav nav-tabs font-weight-bold mb-3 tce-nav-tabs-scroll" role="tablist">'
if old_tabs in text:
    text = text.replace(old_tabs, new_tabs, 1)
    print("Patched student tabs to tce-nav-tabs-scroll")

# 2. Ensure #btnToggleQuestionPrompt style
old_btn = 'id="btnToggleQuestionPrompt" style="white-space: nowrap !important; text-decoration: none; flex-shrink: 0;">'
new_btn = 'id="btnToggleQuestionPrompt" style="white-space: nowrap !important; text-decoration: none; flex-shrink: 0; min-width: 80px; text-align: right;">'
if old_btn in text:
    text = text.replace(old_btn, new_btn, 1)
    print("Patched question prompt toggle button style")

# 3. Add mobile collapse script logic
old_collapse_listener = '// Question prompt toggle text update'
new_collapse_listener = """// Question prompt mobile initial collapse (leaves screen 1 clean for editor)
    if (window.innerWidth < 768) {
        var promptBody = document.getElementById('questionPromptBody');
        var promptLbl = document.getElementById('lblToggleQuestionPrompt');
        if (promptBody && window.jQuery) {
            window.jQuery(promptBody).collapse('hide');
            if (promptLbl) promptLbl.textContent = getStr('expand', 'Mở rộng');
        }
    }

    // Question prompt toggle text update"""
if old_collapse_listener in text:
    text = text.replace(old_collapse_listener, new_collapse_listener, 1)
    print("Patched mobile prompt initial collapse logic")

with open('local_testcase_exchange/templates/dashboard.mustache', 'w', encoding='utf-8') as f:
    f.write(text)
