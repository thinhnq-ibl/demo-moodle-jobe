with open('local_testcase_exchange/tests/cli/run_rigorous_tests.php', 'r', encoding='utf-8') as f:
    text = f.read()

idx = text.find('TEST C:')
with open('scripts/test_c_section.txt', 'w', encoding='utf-8') as out:
    out.write(text[idx:idx+2500])

print("Written test_c_section.txt")
