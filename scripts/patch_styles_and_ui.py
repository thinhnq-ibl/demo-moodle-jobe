# 1. Patch styles.css
with open('local_testcase_exchange/styles.css', 'r', encoding='utf-8') as f:
    css_content = f.read()

is_crlf_css = '\r\n' in css_content
css_lf = '\n'.join(css_content.split('\r\n' if is_crlf_css else '\n'))

old_grid = """/* 7. STUDENT WORKSPACE (2 Columns on Desktop: 54% Code / 46% Testcase) */
.tce-workspace-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 1.25rem;
    margin-bottom: 1.5rem;
    width: 100%;
}

.tce-code-column {
    flex: 1 1 54%;
    min-width: 0;
}

.tce-testcase-column {
    flex: 1 1 42%;
    min-width: 0;
}"""

new_grid = """/* 7. STUDENT WORKSPACE (2 Columns on Desktop: ~60% Code / 40% Testcase) */
.tce-workspace-grid {
    display: flex;
    flex-wrap: wrap;
    gap: 1.25rem;
    margin-bottom: 1.25rem;
    width: 100%;
}

.tce-code-column {
    flex: 3 1 58%;
    min-width: 320px;
}

.tce-testcase-column {
    flex: 2 1 38%;
    min-width: 280px;
}"""

assert old_grid in css_lf, "old_grid not found in styles.css"
css_lf = css_lf.replace(old_grid, new_grid, 1)

final_css = css_lf.replace('\n', '\r\n') if is_crlf_css else css_lf
with open('local_testcase_exchange/styles.css', 'w', encoding='utf-8', newline='') as f:
    f.write(final_css)
print("Updated styles.css with 60/40 ratio")

# 2. Patch editor.js to ensure focus retention on theme switch
with open('local_testcase_exchange/amd/src/editor.js', 'r', encoding='utf-8') as f:
    js_content = f.read()

is_crlf_js = '\r\n' in js_content
js_lf = '\n'.join(js_content.split('\r\n' if is_crlf_js else '\n'))

old_theme_handler = """                    if (themeBtn) {
                        themeBtn.addEventListener('click', function() {
                            var newTheme = (editor.getTheme() === THEME_DARK) ? THEME_LIGHT : THEME_DARK;
                            editor.setTheme(newTheme);
                            window.localStorage.setItem(STORAGE_THEME_KEY, newTheme);
                            EditorManager.updateThemeButton(newTheme);
                        });
                    }"""

new_theme_handler = """                    if (themeBtn) {
                        themeBtn.addEventListener('click', function() {
                            var newTheme = (editor.getTheme() === THEME_DARK) ? THEME_LIGHT : THEME_DARK;
                            editor.setTheme(newTheme);
                            window.localStorage.setItem(STORAGE_THEME_KEY, newTheme);
                            EditorManager.updateThemeButton(newTheme);
                            editor.focus();
                        });
                    }"""

assert old_theme_handler in js_lf, "old_theme_handler not found in editor.js"
js_lf = js_lf.replace(old_theme_handler, new_theme_handler, 1)

final_js = js_lf.replace('\n', '\r\n') if is_crlf_js else js_lf
with open('local_testcase_exchange/amd/src/editor.js', 'w', encoding='utf-8', newline='') as f:
    f.write(final_js)
print("Updated editor.js with theme focus retention")

# 3. Patch dashboard.mustache for compact prompt height
with open('local_testcase_exchange/templates/dashboard.mustache', 'r', encoding='utf-8') as f:
    mustache_content = f.read()

is_crlf_m = '\r\n' in mustache_content
m_lf = '\n'.join(mustache_content.split('\r\n' if is_crlf_m else '\n'))

old_prompt_div = """                    <div class="question-prompt-content text-dark">
                        {{{questiontext_html}}}
                    </div>"""

new_prompt_div = """                    <div class="question-prompt-content text-dark" style="max-height: 160px; overflow-y: auto;">
                        {{{questiontext_html}}}
                    </div>"""

if old_prompt_div in m_lf:
    m_lf = m_lf.replace(old_prompt_div, new_prompt_div, 1)
    final_m = m_lf.replace('\n', '\r\n') if is_crlf_m else m_lf
    with open('local_testcase_exchange/templates/dashboard.mustache', 'w', encoding='utf-8', newline='') as f:
        f.write(final_m)
    print("Updated dashboard.mustache with compact prompt height")
else:
    print("old_prompt_div already patched or not found")
