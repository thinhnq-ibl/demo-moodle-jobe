with open('local_testcase_exchange/lang/en/local_testcase_exchange.php', 'r', encoding='utf-8') as f:
    en_content = f.read()
if "$string['cursor_pos_default']" not in en_content:
    en_content = en_content.rstrip() + "\n$string['cursor_pos_default'] = 'Line 1, Column 1';\n"
    with open('local_testcase_exchange/lang/en/local_testcase_exchange.php', 'w', encoding='utf-8') as f:
        f.write(en_content)

with open('local_testcase_exchange/lang/vi/local_testcase_exchange.php', 'r', encoding='utf-8') as f:
    vi_content = f.read()
if "$string['cursor_pos_default']" not in vi_content:
    vi_content = vi_content.rstrip() + "\n$string['cursor_pos_default'] = 'Dòng 1, Cột 1';\n"
    with open('local_testcase_exchange/lang/vi/local_testcase_exchange.php', 'w', encoding='utf-8') as f:
        f.write(vi_content)

with open('local_testcase_exchange/templates/dashboard.mustache', 'r', encoding='utf-8') as f:
    tpl = f.read()

target = '<span id="tceEditorCursorPos">Dòng 1, Cột 1</span>'
replacement = '<span id="tceEditorCursorPos">{{#str}} cursor_pos_default, local_testcase_exchange {{/str}}</span>'
if target in tpl:
    tpl = tpl.replace(target, replacement, 1)
    with open('local_testcase_exchange/templates/dashboard.mustache', 'w', encoding='utf-8') as f:
        f.write(tpl)
    print("Updated cursor_pos in dashboard.mustache")
else:
    print("Target already updated or not found")
