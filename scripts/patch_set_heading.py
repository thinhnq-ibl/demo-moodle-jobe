# -*- coding: utf-8 -*-
with open('local_testcase_exchange/index.php', 'r', encoding='utf-8') as f:
    text = f.read()

old_heading = "$PAGE->set_heading($course->fullname . ' — ' . $title);"
new_heading = "$PAGE->set_heading($course->fullname);"

if old_heading in text:
    text = text.replace(old_heading, new_heading, 1)
    with open('local_testcase_exchange/index.php', 'w', encoding='utf-8') as f:
        f.write(text)
    print("Updated set_heading in index.php")
