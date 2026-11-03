with open('local_testcase_exchange/lang/en/local_testcase_exchange.php', 'r', encoding='utf-8') as f:
    en_content = f.read()

with open('local_testcase_exchange/lang/vi/local_testcase_exchange.php', 'r', encoding='utf-8') as f:
    vi_content = f.read()

missing_en = {
    'seed_testcases_title': 'Seed testcases',
    'contributed_by': 'Contributed by'
}

missing_vi = {
    'seed_testcases_title': 'Testcase mầm',
    'contributed_by': 'Đóng góp bởi'
}

for k, v in missing_en.items():
    if f"$string['{k}']" not in en_content:
        en_content = en_content.rstrip() + f"\n$string['{k}'] = '{v}';\n"

for k, v in missing_vi.items():
    if f"$string['{k}']" not in vi_content:
        vi_content = vi_content.rstrip() + f"\n$string['{k}'] = '{v}';\n"

with open('local_testcase_exchange/lang/en/local_testcase_exchange.php', 'w', encoding='utf-8') as f:
    f.write(en_content)

with open('local_testcase_exchange/lang/vi/local_testcase_exchange.php', 'w', encoding='utf-8') as f:
    f.write(vi_content)

print("Added missing keys successfully")
