with open('local_testcase_exchange/templates/dashboard.mustache', 'r', encoding='utf-8') as f:
    text = f.read()

import re
idx = text.find('id="reviewModal_')
if idx != -1:
    print(text[idx:idx+2500].encode('ascii', errors='replace').decode('ascii'))
