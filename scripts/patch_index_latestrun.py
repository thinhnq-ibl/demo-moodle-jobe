with open('local_testcase_exchange/index.php', 'r', encoding='utf-8') as f:
    content = f.read()

is_crlf = '\r\n' in content
content_lf = '\n'.join(content.split('\r\n' if is_crlf else '\n'))

target = """    // Tách kết quả lần chạy mới nhất (Latest Run) phục vụ khối hiển thị phản hồi tức thì
    // Đảm bảo 2 nhóm thông tin ĐỘC LẬP: Kết quả chạy vs Trạng thái đóng góp
    $latestrun = null;
    if (!empty($runs)) {
        $first = $runs[0];"""

replacement = """    // Tách kết quả lần chạy mới nhất (Latest Run) phục vụ khối hiển thị phản hồi tức thì
    // Đảm bảo 2 nhóm thông tin ĐỘC LẬP: Kết quả chạy vs Trạng thái đóng góp
    $latestrun = null;
    $target_run = null;
    if (!empty($selectedquestion)) {
        foreach ($runs as $r) {
            if ((int) $r['question_id'] === (int) $selectedquestion->questionid) {
                $target_run = $r;
                break;
            }
        }
    }
    if ($target_run === null && !empty($runs)) {
        $target_run = $runs[0];
    }

    if ($target_run !== null) {
        $first = $target_run;"""

assert target in content_lf, "target not found in index.php"
content_lf = content_lf.replace(target, replacement, 1)

final_content = content_lf.replace('\n', '\r\n') if is_crlf else content_lf
with open('local_testcase_exchange/index.php', 'w', encoding='utf-8', newline='') as f:
    f.write(final_content)

print("Successfully patched index.php to scope latestrun to selected question")
