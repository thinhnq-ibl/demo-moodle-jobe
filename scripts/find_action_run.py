with open('local_testcase_exchange/index.php', 'r', encoding='utf-8') as f:
    lines = f.readlines()
for i, l in enumerate(lines):
    if "action === 'run'" in l:
        print(i+1, l.strip())
