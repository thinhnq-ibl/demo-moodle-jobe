# -*- coding: utf-8 -*-
with open('e2e/capture_all_verification_evidence.js', 'r', encoding='utf-8') as f:
    text = f.read()

old_contrib_block = """    // Click "Gửi đóng góp" if available
    const contributeBtn = await page.$('form button:has-text("Gửi đóng góp")');
    if (contributeBtn) {
        await contributeBtn.click();
        await page.waitForLoadState('networkidle');
        await page.screenshot({ path: `${outDir}/01c_student_contributed_state.png` });
        console.log('Saved: 01c_student_contributed_state.png');
    }"""

new_contrib_block = """    // Click "Gửi đóng góp" in Latest Result Card if visible
    const contributeBtn = await page.$('#tceLatestResultCard form button[type="submit"]');
    if (contributeBtn && await contributeBtn.isVisible()) {
        await Promise.all([
            page.waitForNavigation({ waitUntil: 'load' }).catch(() => {}),
            contributeBtn.click()
        ]);
        await page.waitForLoadState('networkidle');
        await page.screenshot({ path: `${outDir}/01c_student_contributed_state.png` });
        console.log('Saved: 01c_student_contributed_state.png');
    } else {
        await page.screenshot({ path: `${outDir}/01c_student_contributed_state.png` });
        console.log('Saved: 01c_student_contributed_state.png (already in contributed state)');
    }"""

text = text.replace(old_contrib_block, new_contrib_block)

# Also ensure run button click uses waitForNavigation safe promise
old_run_btn_1 = """    // Run Test
    await page.click('#btnSubmitRunTest');
    await page.waitForLoadState('networkidle');"""
new_run_btn_1 = """    // Run Test
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'load' }).catch(() => {}),
        page.click('#btnSubmitRunTest')
    ]);
    await page.waitForLoadState('networkidle');"""

old_run_btn_2 = """    // Run Test with mismatch
    await page.click('#btnSubmitRunTest');
    await page.waitForLoadState('networkidle');"""
new_run_btn_2 = """    // Run Test with mismatch
    await Promise.all([
        page.waitForNavigation({ waitUntil: 'load' }).catch(() => {}),
        page.click('#btnSubmitRunTest')
    ]);
    await page.waitForLoadState('networkidle');"""

text = text.replace(old_run_btn_1, new_run_btn_1).replace(old_run_btn_2, new_run_btn_2)

with open('e2e/capture_all_verification_evidence.js', 'w', encoding='utf-8') as f:
    f.write(text)

print("Updated contributeBtn and runBtn in e2e/capture_all_verification_evidence.js")
