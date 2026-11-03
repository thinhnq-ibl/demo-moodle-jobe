# -*- coding: utf-8 -*-
with open('local_testcase_exchange/templates/dashboard.mustache', 'r', encoding='utf-8') as f:
    text = f.read()

old_arr = "['test_input', 'predicted_output', 'testcase_category', 'testcase_purpose', 'student_code']"
new_arr = "['test_input', 'predicted_output', 'category', 'purpose', 'student_code']"
if old_arr in text:
    text = text.replace(old_arr, new_arr, 1)
    with open('local_testcase_exchange/templates/dashboard.mustache', 'w', encoding='utf-8') as f:
        f.write(text)
    print("Fixed IDs in dashboard.mustache")

with open('e2e/capture_all_verification_evidence.js', 'r', encoding='utf-8') as f:
    e2e = f.read()

e2e = e2e.replace("#testcase_category", "#category")
with open('e2e/capture_all_verification_evidence.js', 'w', encoding='utf-8') as f:
    f.write(e2e)
print("Fixed IDs in e2e/capture_all_verification_evidence.js")
