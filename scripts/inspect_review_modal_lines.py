with open('local_testcase_exchange/templates/dashboard.mustache', 'r', encoding='utf-8') as f:
    lines = f.readlines()

for i in range(300, 375):
    if i < len(lines):
        print(f"{i+1}: {lines[i].strip().encode('ascii', errors='replace').decode('ascii')}")
