with open('local_testcase_exchange/amd/src/editor.js', 'r', encoding='utf-8') as f:
    content = f.read()

# 1. Cursor pos
target1 = """                    var cursorPosEl = document.getElementById('tceEditorCursorPos');
                    function updateCursorPos() {
                        if (!cursorPosEl) return;
                        var pos = editor.getCursorPosition();
                        cursorPosEl.textContent = 'Dòng ' + (pos.row + 1) + ', Cột ' + (pos.column + 1);
                    }"""

replacement1 = """                    var cursorPosEl = document.getElementById('tceEditorCursorPos');
                    function updateCursorPos() {
                        if (!cursorPosEl) return;
                        var pos = editor.getCursorPosition();
                        var isVi = (document.documentElement.lang === 'vi');
                        var lineWord = isVi ? 'Dòng' : 'Line';
                        var colWord = isVi ? 'Cột' : 'Col';
                        cursorPosEl.textContent = lineWord + ' ' + (pos.row + 1) + ', ' + colWord + ' ' + (pos.column + 1);
                    }"""

assert target1 in content, "target1 not found"
content = content.replace(target1, replacement1, 1)

# 2. toggleFullscreen labels
target2 = """            if (isFullscreen) {
                document.body.classList.add('tce-noscroll');
                if (icon) icon.className = 'fa fa-compress mr-1';
                if (text) text.textContent = 'Thu nhỏ';
            } else {
                document.body.classList.remove('tce-noscroll');
                if (icon) icon.className = 'fa fa-arrows-alt mr-1';
                if (text) text.textContent = 'Toàn màn hình';
            }"""

replacement2 = """            var isVi = (document.documentElement.lang === 'vi');
            if (isFullscreen) {
                document.body.classList.add('tce-noscroll');
                if (icon) icon.className = 'fa fa-compress mr-1';
                if (text) text.textContent = isVi ? 'Thu nhỏ' : 'Exit fullscreen';
            } else {
                document.body.classList.remove('tce-noscroll');
                if (icon) icon.className = 'fa fa-arrows-alt mr-1';
                if (text) text.textContent = isVi ? 'Toàn màn hình' : 'Fullscreen';
            }"""

assert target2 in content, "target2 not found"
content = content.replace(target2, replacement2, 1)

# 3. updateThemeButton labels
target3 = """            if (theme === THEME_DARK) {
                icon.className = 'fa fa-sun-o mr-1';
                text.textContent = 'Giao diện sáng';
            } else {
                icon.className = 'fa fa-moon-o mr-1';
                text.textContent = 'Giao diện tối';
            }"""

replacement3 = """            var isVi = (document.documentElement.lang === 'vi');
            if (theme === THEME_DARK) {
                icon.className = 'fa fa-sun-o mr-1';
                text.textContent = isVi ? 'Giao diện sáng' : 'Light theme';
            } else {
                icon.className = 'fa fa-moon-o mr-1';
                text.textContent = isVi ? 'Giao diện tối' : 'Dark theme';
            }"""

assert target3 in content, "target3 not found"
content = content.replace(target3, replacement3, 1)

with open('local_testcase_exchange/amd/src/editor.js', 'w', encoding='utf-8') as f:
    f.write(content)

print("Updated editor.js successfully")
