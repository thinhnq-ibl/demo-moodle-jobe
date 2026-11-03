const { chromium } = require('playwright');
const path = require('path');
const OUT_DIR = path.join(__dirname, '..', 'verification_evidence', 'screenshots_after');

async function loginUser(page, context, username, password) {
    await context.clearCookies();
    await page.goto('http://localhost:8080/login/index.php');
    await page.waitForLoadState('networkidle');
    await page.fill('#username', username);
    await page.fill('#password', password);
    await Promise.all([
        page.waitForNavigation(),
        page.evaluate(() => {
            const form = document.querySelector('form#login');
            if (form) form.submit();
            else document.getElementById('loginbtn')?.click();
        })
    ]);
    await page.waitForLoadState('networkidle');
}

(async () => {
    const browser = await chromium.launch({ headless: true, args: ['--no-sandbox'] });
    const teacherCtx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await teacherCtx.newPage();
    await loginUser(page, teacherCtx, 'admin', 'AdminPassword123!');
    await page.goto('http://localhost:8080/local/testcase_exchange/index.php?course=2&question=76:794&tab=review');
    await page.waitForLoadState('networkidle');

    // Chụp cận cảnh form review
    const firstForm = await page.$('tr:has-text("#56") form, tbody tr:first-child form');
    if (firstForm) {
        await page.selectOption('select[name="decision"]', 'approved');
        await page.selectOption('select[name="rating"]', '5');
        await page.fill('input[name="review_note"]', 'Testcase rất tốt, dữ liệu kiểm thử bao quát và chuẩn xác!');
        await firstForm.screenshot({ path: path.join(OUT_DIR, '06b_review_action_dialog_populated.png') });
        console.log('Saved 06b_review_action_dialog_populated.png');

        // Bấm submit
        const submitBtn = await firstForm.$('button[type="submit"]');
        await Promise.all([
            page.waitForNavigation({ timeout: 15000 }).catch(() => {}),
            submitBtn.click()
        ]);
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(1000);

        // Chụp trạng thái sau khi duyệt
        await page.screenshot({ path: path.join(OUT_DIR, '06c_review_approved_state_verified.png'), fullPage: true });
        console.log('Saved 06c_review_approved_state_verified.png');
    }
    await browser.close();
})();
