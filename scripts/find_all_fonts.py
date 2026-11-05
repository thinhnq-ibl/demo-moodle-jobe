import os, re

for root, dirs, files in os.walk('.'):
    if 'node_modules' in root or '.git' in root or 'dist' in root or 'moodledata' in root:
        continue
    for f in files:
        if f.endswith(('.css', '.mustache', '.php', '.html')):
            p = os.path.join(root, f)
            with open(p, 'r', encoding='utf-8', errors='ignore') as fp:
                c = fp.read()
                matches = re.findall(r'font-family:[^;]+;', c)
                if matches:
                    print(f"{p}: {matches}")
