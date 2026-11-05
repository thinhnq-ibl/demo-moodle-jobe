# -*- coding: utf-8 -*-
with open('e2e/capture_all_verification_evidence.js', 'r', encoding='utf-8') as f:
    text = f.read()

old_target = "await pageTeacher.locator('#tab_bank .table-responsive').scrollIntoViewIfNeeded();"
new_target = "await pageTeacher.locator('#tab_bank .table-responsive').last().scrollIntoViewIfNeeded();"

if old_target in text:
    text = text.replace(old_target, new_target, 1)
    with open('e2e/capture_all_verification_evidence.js', 'w', encoding='utf-8') as f:
        f.write(text)
    print("Fixed strict mode locator in e2e/capture_all_verification_evidence.js")
else:
    print("Target not found")
