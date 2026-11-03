with open('local_testcase_exchange/index.php', 'r', encoding='utf-8') as f:
    content = f.read()

is_crlf = '\r\n' in content
content_lf = '\n'.join(content.split('\r\n' if is_crlf else '\n'))

# 1. Ensure $quizsettings is defined in student view
t1 = """if ($is_student_view) {
    $runs = $service ? $service->runs_for_user($courseid, (int) $USER->id) : [];"""

r1 = """if ($is_student_view) {
    $student_quizid = !empty($selectedquestion) ? (int) $selectedquestion->quizid : (int) $selected_quizid;
    $quizsettings = ($service && $student_quizid > 0) ? $service->quiz_settings($student_quizid) : [];
    $runs = $service ? $service->runs_for_user($courseid, (int) $USER->id) : [];"""

assert t1 in content_lf, "t1 not found in index.php"
content_lf = content_lf.replace(t1, r1, 1)

# 2. Extract $oracle_out before applying show_oracle_output display filter
t2 = """    foreach ($runs as &$run) {
        // CRITICAL SECURITY: Never leak Oracle stdout in student view unless permitted by policy!
        if (empty($quizsettings['show_oracle_output'])) {
            $run['show_oracle'] = false;
            $run['oracle_output'] = '';
        } else {
            $run['show_oracle'] = true;
        }

        $oracle_valid = ($run['oracle_outcome'] === 'success');
        $run['oracle_valid'] = $oracle_valid;
        $student_out = trim((string) ($run['student_run_output'] ?? ''));
        $predicted_out = trim((string) ($run['predicted_output'] ?? ''));
        $oracle_out = trim((string) ($run['oracle_output'] ?? ''));"""

r2 = """    foreach ($runs as &$run) {
        $oracle_valid = ($run['oracle_outcome'] === 'success');
        $run['oracle_valid'] = $oracle_valid;
        $student_out = trim((string) ($run['student_run_output'] ?? ''));
        $predicted_out = trim((string) ($run['predicted_output'] ?? ''));
        $oracle_out = trim((string) ($run['oracle_output'] ?? ''));

        // CRITICAL SECURITY: Never leak Oracle stdout in student view unless permitted by policy!
        if (empty($quizsettings['show_oracle_output'])) {
            $run['show_oracle'] = false;
            $run['oracle_output'] = '';
        } else {
            $run['show_oracle'] = true;
        }"""

assert t2 in content_lf, "t2 not found in index.php"
content_lf = content_lf.replace(t2, r2, 1)

final_content = content_lf.replace('\n', '\r\n') if is_crlf else content_lf
with open('local_testcase_exchange/index.php', 'w', encoding='utf-8', newline='') as f:
    f.write(final_content)

print("Successfully fixed student quizsettings and oracle_out extraction order")
