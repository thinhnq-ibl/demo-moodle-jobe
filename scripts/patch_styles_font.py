# Patch styles.css to use font-family: inherit
with open('local_testcase_exchange/styles.css', 'r', encoding='utf-8') as f:
    content = f.read()

target = 'font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;'
replacement = 'font-family: inherit;'
if target in content:
    content = content.replace(target, replacement, 1)
    with open('local_testcase_exchange/styles.css', 'w', encoding='utf-8') as f:
        f.write(content)
    print('Updated styles.css successfully')
else:
    print('Target not found, current font:', [line for line in content.splitlines() if 'font-family' in line])
