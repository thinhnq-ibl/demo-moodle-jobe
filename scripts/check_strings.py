import re

mustache_files = [
    'local_testcase_exchange/templates/dashboard.mustache',
    'local_testcase_exchange/templates/review.mustache'
]

keys = set()
for mf in mustache_files:
    with open(mf, 'r', encoding='utf-8') as f:
        content = f.read()
        found = re.findall(r'\{\{#str\}\}\s*([a-zA-Z0-9_]+)\s*,\s*local_testcase_exchange\s*\{\{/str\}\}', content)
        keys.update(found)

with open('local_testcase_exchange/lang/vi/local_testcase_exchange.php', 'r', encoding='utf-8') as f:
    vi_content = f.read()
    vi_keys = set(re.findall(r"\$string\['([a-zA-Z0-9_]+)'\]", vi_content))

with open('local_testcase_exchange/lang/en/local_testcase_exchange.php', 'r', encoding='utf-8') as f:
    en_content = f.read()
    en_keys = set(re.findall(r"\$string\['([a-zA-Z0-9_]+)'\]", en_content))

missing_vi = keys - vi_keys
missing_en = keys - en_keys
print('Missing VI:', missing_vi)
print('Missing EN:', missing_en)
