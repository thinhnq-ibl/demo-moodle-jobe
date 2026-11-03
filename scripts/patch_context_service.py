with open('local_testcase_exchange/classes/context_service.php', 'r', encoding='utf-8') as f:
    code = f.read()

target = """            $comment = !empty($c['review_comment']) ? trim($c['review_comment']) : (!empty($c['latest_review_note']) ? trim($c['latest_review_note']) : '');
            $c['teacher_comment'] = $comment;
            $c['has_teacher_comment'] = ($comment !== '');
        }"""

replacement = """            $comment = !empty($c['review_comment']) ? trim($c['review_comment']) : (!empty($c['latest_review_note']) ? trim($c['latest_review_note']) : '');
            $c['teacher_comment'] = $comment;
            $c['has_teacher_comment'] = ($comment !== '');

            // Safe extraction and formatting for Expected Output according to strict requirements:
            // 1. Distinguish valid empty string, no data recorded (null), and numerical value 0 / string '0'.
            // 2. Never substitute missing expected output with oracle_output.
            $rawexpected = null;
            if (array_key_exists('expected_output', $c) && $c['expected_output'] !== null) {
                $rawexpected = $c['expected_output'];
            } else if (array_key_exists('predicted_output', $c) && $c['predicted_output'] !== null) {
                $rawexpected = $c['predicted_output'];
            } else if (array_key_exists('run_predicted_output', $c) && $c['run_predicted_output'] !== null) {
                $rawexpected = $c['run_predicted_output'];
            }

            if ($rawexpected === null) {
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
            $c['submitted_at'] = $subtime > 0 ? date('H:i:s d/m/Y', $subtime) : 'Không có thông tin';
        }"""

if target in code:
    code = code.replace(target, replacement, 1)
    with open('local_testcase_exchange/classes/context_service.php', 'w', encoding='utf-8') as f:
        f.write(code)
    print("SUCCESS")
else:
    print("ERROR: target not found")
