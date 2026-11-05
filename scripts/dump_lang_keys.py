import re

with open('local_testcase_exchange/lang/en/local_testcase_exchange.php', 'r', encoding='utf-8') as f:
    en_text = f.read()
with open('local_testcase_exchange/lang/vi/local_testcase_exchange.php', 'r', encoding='utf-8') as f:
    vi_text = f.read()

en_matches = dict(re.findall(r"\$string\['([^']+)'\]\s*=\s*'([^']*)';", en_text))
vi_matches = dict(re.findall(r"\$string\['([^']+)'\]\s*=\s*'([^']*)';", vi_text))

with open('scripts/all_lang_keys.txt', 'w', encoding='utf-8') as out:
    for k in sorted(en_matches.keys()):
        out.write(f"{k} || EN: {en_matches.get(k, '')} || VI: {vi_matches.get(k, '')}\n")

print(f"Total keys: {len(en_matches)}")
