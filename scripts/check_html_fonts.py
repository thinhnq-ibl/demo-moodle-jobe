with open('verification_evidence/screenshots_after/student_page_debug.html', 'r', encoding='utf-8') as f:
    c = f.read()

import re
print('Has Times New Roman / serif in HTML:')
for m in re.finditer(r'(serif|Times)', c, re.IGNORECASE):
    snippet = c[max(0, m.start()-50):min(len(c), m.end()+50)]
    print(repr(snippet))

css_links = re.findall(r'<link[^>]+rel=[\'"]stylesheet[\'"][^>]*>', c)
print('\nStylesheets count:', len(css_links))
for l in css_links:
    print(l)
