import re

with open('local_testcase_exchange/lang/en/local_testcase_exchange.php', 'r', encoding='utf-8') as f:
    en_content = f.read()
en_keys = set(re.findall(r"\$string\['([^']+)'\]", en_content))

with open('local_testcase_exchange/lang/vi/local_testcase_exchange.php', 'r', encoding='utf-8') as f:
    vi_content = f.read()
vi_keys = set(re.findall(r"\$string\['([^']+)'\]", vi_content))

mustache_files = [
    'local_testcase_exchange/templates/dashboard.mustache',
    'local_testcase_exchange/templates/review.mustache'
]
all_called = set()
for mf in mustache_files:
    with open(mf, 'r', encoding='utf-8') as f:
        m_content = f.read()
    matches = re.findall(r"\{\{#str\}\}\s*([a-zA-Z0-9_]+)\s*,\s*local_testcase_exchange\s*\{\{/str\}\}", m_content)
    for k in matches:
        all_called.add(k)

missing_en = sorted(list(all_called - en_keys))
missing_vi = sorted(list(all_called - vi_keys))

print('Missing in EN (count: %d):' % len(missing_en), missing_en)
print('Missing in VI (count: %d):' % len(missing_vi), missing_vi)
