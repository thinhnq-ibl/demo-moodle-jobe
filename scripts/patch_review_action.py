with open('local_testcase_exchange/templates/review.mustache', 'r', encoding='utf-8') as f:
    c = f.read()

target = '<input type="hidden" name="contribution_id" value="{{id}}">'
replacement = '<input type="hidden" name="action" value="teacher_review">\n            <input type="hidden" name="contribution_id" value="{{id}}">'

c = c.replace(target, replacement)

with open('local_testcase_exchange/templates/review.mustache', 'w', encoding='utf-8') as f:
    f.write(c)

print('Added action=teacher_review to review.mustache cleanly!')
