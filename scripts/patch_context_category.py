with open('local_testcase_exchange/classes/context_service.php', 'r', encoding='utf-8') as f:
    content = f.read()

target = """            $st = (string) $c['status'];
            $c['status_label'] = $statuslabels[$st] ?? $st;
            $c['status_badge_class'] = $badgemap[$st] ?? 'badge badge-light';
            $c['is_pending'] = ($st === 'submitted' || $st === 'needs_explanation');
            $c['is_approved'] = ($st === 'approved');
            $c['is_rejected'] = ($st === 'rejected');
            $c['is_duplicate'] = ($st === 'duplicate');"""

replacement = """            $st = (string) $c['status'];
            $c['status_label'] = $statuslabels[$st] ?? $st;
            $c['status_badge_class'] = $badgemap[$st] ?? 'badge badge-light';
            $c['is_pending'] = ($st === 'submitted' || $st === 'needs_explanation');
            $c['is_approved'] = ($st === 'approved');
            $c['is_rejected'] = ($st === 'rejected');
            $c['is_duplicate'] = ($st === 'duplicate');

            // Category & Outcome localization
            $cat = (string) ($c['category'] ?? 'normal');
            $categorylabels = [
                'normal' => get_string('category_normal', 'local_testcase_exchange'),
                'boundary' => get_string('category_boundary', 'local_testcase_exchange'),
                'branch' => get_string('category_branch', 'local_testcase_exchange'),
                'invalid' => get_string('category_invalid', 'local_testcase_exchange'),
                'empty' => get_string('category_empty', 'local_testcase_exchange'),
                'large' => get_string('category_large', 'local_testcase_exchange'),
                'other' => get_string('category_other', 'local_testcase_exchange'),
                'edge_case' => get_string('category_boundary', 'local_testcase_exchange'),
            ];
            $c['category_label'] = $categorylabels[$cat] ?? get_string('category_normal', 'local_testcase_exchange');

            $outcome = (string) ($c['oracle_outcome'] ?? '');
            if ($outcome === 'success') {
                $c['oracle_outcome_label'] = get_string('outcome_success', 'local_testcase_exchange');
            } else if ($outcome !== '') {
                $c['oracle_outcome_label'] = get_string('outcome_failed', 'local_testcase_exchange');
            } else {
                $c['oracle_outcome_label'] = get_string('outcome_success', 'local_testcase_exchange');
            }"""

assert target in content, "target not found in context_service.php"
content = content.replace(target, replacement, 1)

with open('local_testcase_exchange/classes/context_service.php', 'w', encoding='utf-8') as f:
    f.write(content)

print("Updated context_service.php with category_label and oracle_outcome_label successfully")
