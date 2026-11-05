# -*- coding: utf-8 -*-
with open('e2e/capture_all_verification_evidence.js', 'r', encoding='utf-8') as f:
    text = f.read()

# Replace fragile waitForNavigation on btnSubmitRunTest and contributeBtn
old_run_1 = """    // Run Test
    await Promise.all([
        page.waitForNavigation(),
        page.click('#btnSubmitRunTest')
    ]);"""

new_run_1 = """    // Run Test
    await page.click('#btnSubmitRunTest');
    await page.waitForLoadState('networkidle');"""

old_contrib = """    // Click "Gửi đóng góp" if available
    const contributeBtn = await page.$('form button:has-text("Gửi đóng góp")');
    if (contributeBtn) {
        await Promise.all([
            page.waitForNavigation(),
            contributeBtn.click()
        ]);
        await page.waitForLoadState('networkidle');
        await page.screenshot({ path: `${outDir}/01c_student_contributed_state.png` });
        console.log('Saved: 01c_student_contributed_state.png');
    }"""

new_contrib = """    // Click "Gửi đóng góp" if available
    const contributeBtn = await page.$('form button:has-text("Gửi đóng góp")');
    if (contributeBtn) {
        await contributeBtn.click();
        await page.waitForLoadState('networkidle');
        await page.screenshot({ path: `${outDir}/01c_student_contributed_state.png` });
        console.log('Saved: 01c_student_contributed_state.png');
    }"""

old_run_2 = """    // Run Test with mismatch
    await Promise.all([
        page.waitForNavigation(),
        page.click('#btnSubmitRunTest')
    ]);"""

new_run_2 = """    // Run Test with mismatch
    await page.click('#btnSubmitRunTest');
    await page.waitForLoadState('networkidle');"""

text = text.replace(old_run_1, new_run_1).replace(old_contrib, new_contrib).replace(old_run_2, new_run_2)

with open('e2e/capture_all_verification_evidence.js', 'w', encoding='utf-8') as f:
    f.write(text)

print("Updated form submission waits in e2e/capture_all_verification_evidence.js")
