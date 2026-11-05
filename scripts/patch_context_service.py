with open('local_testcase_exchange/classes/context_service.php', 'r', encoding='utf-8') as f:
    content = f.read()

# 1. contributor_name mapping
target1 = """                $c['student_display'] = fullname($u) . ' (' . $u->username . ')';
            } else {
                $c['student_name'] = 'User #' . $uid;
                $c['student_username'] = '#' . $uid;
                $c['student_email'] = '';
                $c['student_display'] = 'User #' . $uid;
            }"""

replacement1 = """                $c['student_display'] = fullname($u) . ' (' . $u->username . ')';
                $c['contributor_name'] = fullname($u);
            } else {
                $c['student_name'] = 'User #' . $uid;
                $c['student_username'] = '#' . $uid;
                $c['student_email'] = '';
                $c['student_display'] = 'User #' . $uid;
                $c['contributor_name'] = 'User #' . $uid;
            }"""

assert target1 in content, "target1 not found"
content = content.replace(target1, replacement1, 1)

# 2. expected output string localization
target2 = """            if ($rawexpected === null) {
                $c['has_expected_output'] = false;
                $c['is_expected_missing'] = true;
                $c['is_expected_empty'] = false;
                $c['expected_output_display'] = 'Không có dữ liệu';
                $c['predicted_output'] = 'Không có dữ liệu';
            } else {
                $strval = (string) $rawexpected;
                $c['has_expected_output'] = true;
                $c['is_expected_missing'] = false;
                if ($strval === '') {
                    $c['is_expected_empty'] = true;
                    $c['expected_output_display'] = '(Chuỗi rỗng)';
                    $c['predicted_output'] = '(Chuỗi rỗng)';
                } else {
                    $c['is_expected_empty'] = false;
                    $c['expected_output_display'] = $strval;
                    $c['predicted_output'] = $strval;
                }
            }

            // Formatted submitted timestamp
            $subtime = !empty($c['timecreated']) ? (int) $c['timecreated'] : 0;
            $c['submitted_at'] = $subtime > 0 ? date('H:i:s d/m/Y', $subtime) : 'Không có thông tin';"""

replacement2 = """            if ($rawexpected === null) {
                $c['has_expected_output'] = false;
                $c['is_expected_missing'] = true;
                $c['is_expected_empty'] = false;
                $c['expected_output_display'] = get_string('no_data', 'local_testcase_exchange');
                $c['predicted_output'] = get_string('no_data', 'local_testcase_exchange');
            } else {
                $strval = (string) $rawexpected;
                $c['has_expected_output'] = true;
                $c['is_expected_missing'] = false;
                if ($strval === '') {
                    $c['is_expected_empty'] = true;
                    $c['expected_output_display'] = get_string('empty_string', 'local_testcase_exchange');
                    $c['predicted_output'] = get_string('empty_string', 'local_testcase_exchange');
                } else {
                    $c['is_expected_empty'] = false;
                    $c['expected_output_display'] = $strval;
                    $c['predicted_output'] = $strval;
                }
            }

            // Formatted submitted timestamp in user's timezone
            $subtime = !empty($c['timecreated']) ? (int) $c['timecreated'] : 0;
            $c['submitted_at'] = $subtime > 0 ? userdate($subtime, get_string('strftimedatetimeshort', 'core_langconfig')) : get_string('noinfo', 'local_testcase_exchange');"""

assert target2 in content, "target2 not found"
content = content.replace(target2, replacement2, 1)

# 3. last_run_at format in user's timezone
target3 = """            if (!empty($item['last_run_at'])) {
                $item['last_run_formatted'] = date('H:i:s d/m/Y', strtotime($item['last_run_at']));
            } else {"""

replacement3 = """            if (!empty($item['last_run_at'])) {
                $item['last_run_formatted'] = userdate(strtotime($item['last_run_at']), get_string('strftimedatetimeshort', 'core_langconfig'));
            } else {"""

assert target3 in content, "target3 not found"
content = content.replace(target3, replacement3, 1)

with open('local_testcase_exchange/classes/context_service.php', 'w', encoding='utf-8') as f:
    f.write(content)

print("Updated context_service.php successfully")
