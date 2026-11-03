with open('local_testcase_exchange/tests/cli/run_rigorous_tests.php', 'r', encoding='utf-8') as f:
    text = f.read()

idx = text.find('// 2. Quiz-level setting independent check')
if idx == -1:
    idx = text.find('TEST A')
with open('scripts/test_a_body.txt', 'w', encoding='utf-8') as out:
    out.write(text[idx:idx+1500])

print("Written test_a_body.txt")
