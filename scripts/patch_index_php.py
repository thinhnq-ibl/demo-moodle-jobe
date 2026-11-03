with open('local_testcase_exchange/index.php', 'r', encoding='utf-8') as f:
    content = f.read()

# 1. overview_stats scope_label
target1 = """    $templatedata['overview_stats'] = [
        'is_quiz_enabled' => !empty($currentquizsettings['enabled']),
        'total_runs' => $totalruns,
        'total_contributions' => $contributioncounts['all'] ?? 0,
        'pending_contributions' => $contributioncounts['pending'] ?? 0,
        'approved_contributions' => $contributioncounts['approved'] ?? 0,
        'rejected_contributions' => $contributioncounts['rejected'] ?? 0,
        'duplicate_contributions' => $contributioncounts['duplicate'] ?? 0,
        'total_rewards' => $rewardstats['total'],
        'claimed_rewards' => $rewardstats['claimed'],
        'unclaimed_rewards' => $rewardstats['unclaimed'],
        'active_questions_count' => count($questions),
    ];"""

replacement1 = """    $scopelabel = get_string('scope_course', 'local_testcase_exchange');
    if ($selected_questionid > 0) {
        $scopelabel = get_string('scope_question', 'local_testcase_exchange');
    } else if ($selected_quizid > 0) {
        $scopelabel = get_string('scope_quiz', 'local_testcase_exchange');
    }

    $templatedata['overview_stats'] = [
        'is_quiz_enabled' => !empty($currentquizsettings['enabled']),
        'total_runs' => $totalruns,
        'total_contributions' => $contributioncounts['all'] ?? 0,
        'pending_contributions' => $contributioncounts['pending'] ?? 0,
        'approved_contributions' => $contributioncounts['approved'] ?? 0,
        'rejected_contributions' => $contributioncounts['rejected'] ?? 0,
        'duplicate_contributions' => $contributioncounts['duplicate'] ?? 0,
        'total_rewards' => $rewardstats['total'],
        'claimed_rewards' => $rewardstats['claimed'],
        'unclaimed_rewards' => $rewardstats['unclaimed'],
        'active_questions_count' => count($questions),
        'scope_label' => $scopelabel,
    ];"""

assert target1 in content, "target1 not found"
content = content.replace(target1, replacement1, 1)

# 2. Oracle output server-side redaction
target2 = """    foreach ($runs as &$run) {
        // CRITICAL SECURITY: Never leak Oracle stdout in student view!
        $run['show_oracle'] = false;"""

replacement2 = """    foreach ($runs as &$run) {
        // CRITICAL SECURITY: Never leak Oracle stdout in student view unless permitted by policy!
        if (empty($quizsettings['show_oracle_output'])) {
            $run['show_oracle'] = false;
            $run['oracle_output'] = '';
        } else {
            $run['show_oracle'] = true;
        }"""

assert target2 in content, "target2 not found"
content = content.replace(target2, replacement2, 1)

# 3. created_at_formatted with userdate
target3 = """        if ($createdtimestamp > 0) {
            $run['created_at_formatted'] = date('H:i:s d/m/Y', $createdtimestamp);
        } else {"""

replacement3 = """        if ($createdtimestamp > 0) {
            $run['created_at_formatted'] = userdate($createdtimestamp, get_string('strftimedatetimeshort', 'core_langconfig'));
        } else {"""

assert target3 in content, "target3 not found"
content = content.replace(target3, replacement3, 1)

# 4. strings_for_js registration
target4 = """if ($PAGE->requires->should_create_one_time_item_now('local_testcase_exchange-ace-scripts')) {"""

replacement4 = """$PAGE->requires->strings_for_js([
    'draft_saved_device',
    'draft_saved_device_justnow',
    'draft_restored_device',
    'draft_earlier',
    'running',
    'revision_loaded',
    'run_code_loaded',
    'testcase_cleared',
    'show_more_runs',
    'hide_older_runs',
    'collapse',
    'expand',
], 'local_testcase_exchange');

if ($PAGE->requires->should_create_one_time_item_now('local_testcase_exchange-ace-scripts')) {"""

assert target4 in content, "target4 not found"
content = content.replace(target4, replacement4, 1)

with open('local_testcase_exchange/index.php', 'w', encoding='utf-8') as f:
    f.write(content)

print("Updated index.php successfully")
