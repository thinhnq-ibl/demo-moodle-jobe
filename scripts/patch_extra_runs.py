with open('local_testcase_exchange/index.php', 'r', encoding='utf-8') as f:
    content = f.read()

target = """    $templatedata['runs'] = $runs;
    $templatedata['run_count'] = count($runs);"""

replacement = """    $templatedata['runs'] = $runs;
    $templatedata['run_count'] = count($runs);
    $templatedata['has_extra_runs'] = (count($runs) > 5);
    $templatedata['extra_runs_count'] = max(0, count($runs) - 5);"""

assert target in content, "target not found"
content = content.replace(target, replacement, 1)

with open('local_testcase_exchange/index.php', 'w', encoding='utf-8') as f:
    f.write(content)

print("Added has_extra_runs to index.php")
