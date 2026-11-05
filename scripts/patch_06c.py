with open('e2e/capture_after_state.js', 'r', encoding='utf-8') as f:
    c = f.read()

target = "await adminPage.screenshot({ path: path.join(OUT_DIR, '06c_review_approved_state_verified.png'), fullPage: true });"

replacement = """await adminPage.waitForTimeout(1000);
            await adminPage.goto('http://localhost:8080/local/testcase_exchange/index.php?course=3&question=2:119&tab=review');
            await adminPage.waitForLoadState('networkidle');
            await adminPage.screenshot({ path: path.join(OUT_DIR, '06c_review_approved_state_verified.png'), fullPage: true });"""

c = c.replace(target, replacement)

with open('e2e/capture_after_state.js', 'w', encoding='utf-8') as f:
    f.write(c)

print('Updated 06c capture cleanly!')
