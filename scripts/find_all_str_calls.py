import re
import glob

with open('local_testcase_exchange/lang/en/local_testcase_exchange.php', 'r', encoding='utf-8') as f:
    en_content = f.read()
en_keys = set(re.findall(r"\$string\['([^']+)'\]", en_content))

mustache_files = glob.glob('local_testcase_exchange/templates/**/*.mustache', recursive=True)
all_calls = []
for mf in mustache_files:
    with open(mf, 'r', encoding='utf-8') as f:
        m_content = f.read()
    matches = re.findall(r"\{\{#str\}\}\s*([a-zA-Z0-9_]+)\s*,\s*([a-zA-Z0-9_]+)\s*\{\{/str\}\}", m_content)
    for k, comp in matches:
        all_calls.append((mf, k, comp))

print("Total {{#str}} calls:", len(all_calls))
tce_calls = set(k for _, k, comp in all_calls if comp == 'local_testcase_exchange')
print("Missing in EN from all templates:", sorted(list(tce_calls - en_keys)))
