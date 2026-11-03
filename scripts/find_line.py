with open('local_testcase_exchange/index.php', 'r', encoding='utf-8') as f:
    lines = f.readlines()
for i, l in enumerate(lines):
    if 'runs' in l and 'templatedata' in l:
        print(f'{i+1}: {l.strip()}')
