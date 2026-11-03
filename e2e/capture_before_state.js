const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const OUT_DIR = path.join(__dirname, '..', 'verification_evidence', 'screenshots_before');
fs.mkdirSync(OUT_DIR, { recursive: true });

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

async function main() {
    const browser = await chromium.launch({
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    // 1. Admin / Health check Before
    console.log('[1/4] Capturing Admin Health Check Before...');
    const adminCtx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const adminPage = await adminCtx.newPage();
    await loginUser(adminPage, adminCtx, 'admin', 'AdminPassword123!');
    await adminPage.goto('http://localhost:8080/local/testcase_exchange/health.php');
    await adminPage.waitForLoadState('networkidle');
    await adminPage.screenshot({ path: path.join(OUT_DIR, '01_health_check_before_desktop.png'), fullPage: true });

    // 2. Teacher View Desktop & Mobile Before
    console.log('[2/4] Capturing Teacher Hub Before...');
    await adminPage.goto('http://localhost:8080/local/testcase_exchange/index.php?course=2');
    await adminPage.waitForLoadState('networkidle');
    await adminPage.screenshot({ path: path.join(OUT_DIR, '02_teacher_hub_desktop_before.png'), fullPage: true });

    await adminPage.click('a[href="#tab_review"]');
    await adminPage.waitForTimeout(600);
    await adminPage.screenshot({ path: path.join(OUT_DIR, '03_teacher_review_desktop_before.png'), fullPage: true });

    // 3. Student View Desktop Before
    console.log('[3/4] Capturing Student View Desktop Before...');
    const studentCtx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const studentPage = await studentCtx.newPage();
    await loginUser(studentPage, studentCtx, 'student1', 'StudentPassword123!');
    await studentPage.goto('http://localhost:8080/local/testcase_exchange/index.php?course=2&question=76:794');
    await studentPage.waitForLoadState('networkidle');
    await studentPage.screenshot({ path: path.join(OUT_DIR, '04_student_desktop_before.png'), fullPage: true });

    // 4. Student View Mobile Before (390x844)
    console.log('[4/4] Capturing Student View Mobile Before...');
    const mobileCtx = await browser.newContext({
        viewport: { width: 390, height: 844 },
        isMobile: true,
        hasTouch: true
    });
    const mobilePage = await mobileCtx.newPage();
    await loginUser(mobilePage, mobileCtx, 'student1', 'StudentPassword123!');
    await mobilePage.goto('http://localhost:8080/local/testcase_exchange/index.php?course=2&question=76:794');
    await mobilePage.waitForLoadState('networkidle');
    await mobilePage.screenshot({ path: path.join(OUT_DIR, '05_student_mobile_before.png'), fullPage: true });

    await browser.close();
    console.log('Done capturing BEFORE screenshots to ' + OUT_DIR);
}

main().catch(err => {
    console.error(err);
    process.exit(1);
});
