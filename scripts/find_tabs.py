with open('scripts/all_lang_keys.txt', 'r', encoding='utf-8') as f:
    lines = f.readlines()

for line in lines:
    if 'tab_' in line or 'save' in line or 'reward' in line or 'source' in line or 'history' in line:
        print(line.strip()[:100])
