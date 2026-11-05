with open('local_testcase_exchange/classes/context_service.php', 'r', encoding='utf-8') as f:
    text = f.read()

import re
idx = text.find('function enrich_contributions')
print(text[idx+1500:idx+3000].encode('ascii', errors='replace').decode('ascii'))
