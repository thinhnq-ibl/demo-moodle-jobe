with open('local_testcase_exchange/tests/cli/run_rigorous_tests.php', 'r', encoding='utf-8') as f:
    content = f.read()

target = """$service->save_quiz_settings($quizA, [
    'enabled' => 1,
    'approval_mode' => 'teacher',
    'reward_policy' => 'one_for_one',
    'max_runs_per_minute' => 20,
    'max_input_bytes' => 8192
]);"""

replacement = """$service->save_quiz_settings($quizA, (int)$teacher->id, [
    'enabled' => 1,
    'approval_mode' => 'teacher',
    'reward_policy' => 'one_for_one',
    'max_runs_per_minute' => 20,
    'max_input_bytes' => 8192
]);"""

assert target in content, "target not found in run_rigorous_tests.php"
content = content.replace(target, replacement, 1)

with open('local_testcase_exchange/tests/cli/run_rigorous_tests.php', 'w', encoding='utf-8') as f:
    f.write(content)

print("Updated patch in run_rigorous_tests.php")
