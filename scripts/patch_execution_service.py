with open('local_testcase_exchange/classes/execution_service.php', 'r', encoding='utf-8') as f:
    content = f.read()

target = """            if ($resolvedtemplate !== '') {
                $runquestion->template = $resolvedtemplate;
                $runquestion->iscombinatortemplate = 0;
            }"""

replacement = """            if ($resolvedtemplate !== '') {
                $runquestion->template = $resolvedtemplate;
            }"""

assert target in content
content = content.replace(target, replacement, 1)

with open('local_testcase_exchange/classes/execution_service.php', 'w', encoding='utf-8', newline='') as f:
    f.write(content)

print("Updated execution_service.php")
