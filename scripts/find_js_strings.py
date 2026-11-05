with open('local_testcase_exchange/templates/dashboard.mustache', 'r', encoding='utf-8') as f:
    c = f.read()

import re
matches = re.findall(r'<script[\s\S]*?</script>', c)
script = matches[0]
lines = script.splitlines()
for i, l in enumerate(lines):
    if any(ord(ch) > 127 for ch in l):
        enc = l.strip().encode('ascii', errors='backslashreplace').decode('ascii')
        print(f'{i+1}: {enc[:120]}')
