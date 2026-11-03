import re

with open('local_testcase_exchange/templates/dashboard.mustache', 'r', encoding='utf-8') as f:
    lines = f.readlines()

vi_char_pattern = re.compile(r'[àáạảãâầấậẩẫăằắặẳẵèéẹẻẽêềếệểễìíịỉĩòóọỏõôồốộổỗơờớợởỡùúụủũưừứựửữỳýỵỷỹđ]', re.IGNORECASE)

raw_vi_lines = []
for i, line in enumerate(lines):
    stripped = re.sub(r'\{\{[^}]+\}\}', '', line).strip()
    if vi_char_pattern.search(stripped):
        raw_vi_lines.append((i + 1, stripped))

with open('scripts/raw_vi_report.txt', 'w', encoding='utf-8') as out:
    out.write(f"Total lines with raw Vietnamese text: {len(raw_vi_lines)}\n")
    for num, text in raw_vi_lines:
        out.write(f"L{num}: {text}\n")

print(f"Total lines with raw Vietnamese text: {len(raw_vi_lines)}")
