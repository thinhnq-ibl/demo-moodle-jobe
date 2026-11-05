with open('local_testcase_exchange/index.php', 'r', encoding='utf-8') as f:
    content = f.read()

target = """if (stripos($coderunnertype, 'python') !== false) {
    $coderunnertype_display = $is_program_input ? 'Python (Dữ liệu stdin)' : 'Python (Hàm / Biểu thức)';
} else if (stripos($coderunnertype, 'java') !== false) {
    $coderunnertype_display = 'Java (Phương thức)';
} else if (stripos($coderunnertype, 'c') !== false) {
    $coderunnertype_display = 'C/C++ (Chương trình)';
}

$input_label = $is_program_input ? 'Dữ liệu đầu vào (stdin):' : 'Lệnh gọi hàm / Biểu thức kiểm thử:';
$input_placeholder = $is_program_input ?
    "Nhập dữ liệu đầu vào stdin cho chương trình (ví dụ: dòng 1 là số lượng, dòng 2 là các giá trị...)" :
    "Nhập lệnh gọi hàm kiểm thử (ví dụ: print(find_max([10, 20, 5])))";"""

replacement = """$is_vi = (current_language() === 'vi');
if (stripos($coderunnertype, 'python') !== false) {
    if ($is_vi) {
        $coderunnertype_display = $is_program_input ? 'Python (Dữ liệu stdin)' : 'Python (Hàm / Biểu thức)';
    } else {
        $coderunnertype_display = $is_program_input ? 'Python (stdin input)' : 'Python (Function / Expression)';
    }
} else if (stripos($coderunnertype, 'java') !== false) {
    $coderunnertype_display = $is_vi ? 'Java (Phương thức)' : 'Java (Method)';
} else if (stripos($coderunnertype, 'c') !== false) {
    $coderunnertype_display = $is_vi ? 'C/C++ (Chương trình)' : 'C/C++ (Program)';
}

$input_label = $is_program_input ?
    get_string('input_label_stdin', 'local_testcase_exchange') :
    get_string('input_label_testcode', 'local_testcase_exchange');

$input_placeholder = $is_program_input ?
    get_string('input_placeholder_stdin', 'local_testcase_exchange') :
    get_string('input_placeholder_testcode', 'local_testcase_exchange');"""

assert target in content, "target not found"
content = content.replace(target, replacement, 1)

with open('local_testcase_exchange/index.php', 'w', encoding='utf-8') as f:
    f.write(content)

print("Updated index.php successfully")
