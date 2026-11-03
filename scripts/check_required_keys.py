keys_to_check = [
    'tab_experimentation', 'tab_my_contributions', 'tab_rewards', 'tab_leaderboard',
    'your_source_code', 'run_test_btn', 'submit_contribution_btn', 'latest_result_heading',
    'history_heading', 'tab_review', 'tab_bank', 'tab_activity', 'tab_settings',
    'pending', 'status_approved', 'save_changes'
]

with open('scripts/all_lang_keys.txt', 'r', encoding='utf-8') as f:
    lines = f.readlines()

with open('scripts/required_keys_status.txt', 'w', encoding='utf-8') as out:
    for k in keys_to_check:
        found = False
        for line in lines:
            if line.startswith(k + ' ||'):
                out.write(line)
                found = True
                break
        if not found:
            out.write(f"NOT FOUND: {k}\n")
print("Done checking keys")
