with open('local_testcase_exchange/tests/cli/run_rigorous_tests.php', 'r', encoding='utf-8') as f:
    text = f.read()

idx = text.find('TEST A:')
with open('scripts/test_a_setup.txt', 'w', encoding='utf-8') as out:
    out.write(text[idx-500:idx+800])

print("Written test_a_setup.txt")
