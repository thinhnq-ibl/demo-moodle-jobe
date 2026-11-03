with open('local_testcase_exchange/templates/review.mustache', 'r', encoding='utf-8') as f:
    text = f.read()

import re
matches = re.findall(r'<select[^>]+name="decision"[^>]*>.*?</select>', text, re.DOTALL)
with open('scripts/review_decision_selects.txt', 'w', encoding='utf-8') as out:
    for m in matches:
        out.write(m + "\n---\n")

print(f"Found {len(matches)} decision selects in review.mustache")
