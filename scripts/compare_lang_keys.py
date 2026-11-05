with open('local_testcase_exchange/lang/en/local_testcase_exchange.php', 'r', encoding='utf-8') as f:
    en_content = f.read()
with open('local_testcase_exchange/lang/vi/local_testcase_exchange.php', 'r', encoding='utf-8') as f:
    vi_content = f.read()

import re
en_keys = set(re.findall(r"\$string\['([^']+)'\]", en_content))
vi_keys = set(re.findall(r"\$string\['([^']+)'\]", vi_content))
print('EN keys count:', len(en_keys))
print('VI keys count:', len(vi_keys))
print('In EN but not VI:', sorted(list(en_keys - vi_keys)))
print('In VI but not EN:', sorted(list(vi_keys - en_keys)))
