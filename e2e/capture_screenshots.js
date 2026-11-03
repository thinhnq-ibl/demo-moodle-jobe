const { chromium } = require('playwright');
const path = require('path');

const ARTIFACT_DIR = '/Users/nguyenquocthinh/.gemini/antigravity/brain/5e306872-7220-4e07-b6b6-f1c463a55719';

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

    // 1. Capture Student Page
    console.log('[1/2] Logging in as student1...');
    const studentContext = await browser.newContext({
        viewport: { width: 1400, height: 950 }
    });
    const studentPage = await studentContext.newPage();
    await loginUser(studentPage, studentContext, 'student1', 'StudentPassword123!');

    console.log('[1/2] Navigating to student playground for quiz 76 question 794...');
    await studentPage.goto('http://localhost:8080/local/testcase_exchange/index.php?course=2&question=76:794');
    await studentPage.waitForLoadState('networkidle');
    await studentPage.waitForTimeout(1500);

    const studentImgPath = path.join(ARTIFACT_DIR, 'student_view_updated.png');
    await studentPage.screenshot({ path: studentImgPath, fullPage: true });
    console.log(`[1/2] Saved student screenshot to: ${studentImgPath}`);

    // Capture focused area of Playground
    const studentPlaygroundPath = path.join(ARTIFACT_DIR, 'student_playground_focus.png');
    const playgroundCard = await studentPage.$('#tce-student-form');
    if (playgroundCard) {
        await playgroundCard.screenshot({ path: studentPlaygroundPath });
        console.log(`[1/2] Saved student playground screenshot to: ${studentPlaygroundPath}`);
    }

    // Capture results table
    const resultsCard = await studentPage.$('.card.mb-3.border-dark, .card:has(table)');
    if (resultsCard) {
        const resultsImgPath = path.join(ARTIFACT_DIR, 'student_results_table.png');
        await resultsCard.screenshot({ path: resultsImgPath });
        console.log(`[1/2] Saved results table screenshot to: ${resultsImgPath}`);
    }

    // 2. Capture Teacher Page
    console.log('[2/2] Logging in as admin/teacher...');
    const teacherContext = await browser.newContext({
        viewport: { width: 1400, height: 950 }
    });
    const teacherPage = await teacherContext.newPage();
    await loginUser(teacherPage, teacherContext, 'admin', 'AdminPassword123!');

    console.log('[2/2] Navigating to teacher management hub...');
    await teacherPage.goto('http://localhost:8080/local/testcase_exchange/index.php?course=2');
    await teacherPage.waitForLoadState('networkidle');
    await teacherPage.waitForTimeout(1500);

    const teacherImgPath = path.join(ARTIFACT_DIR, 'teacher_view_updated.png');
    await teacherPage.screenshot({ path: teacherImgPath, fullPage: true });
    console.log(`[2/2] Saved teacher screenshot to: ${teacherImgPath}`);

    // Switch to Review tab and capture
    await teacherPage.click('a[href="#tab_review"]');
    await teacherPage.waitForTimeout(600);
    const teacherReviewImgPath = path.join(ARTIFACT_DIR, 'teacher_review_tab.png');
    await teacherPage.screenshot({ path: teacherReviewImgPath, fullPage: true });
    console.log(`[2/2] Saved teacher review tab screenshot to: ${teacherReviewImgPath}`);

    await browser.close();
    console.log('All screenshots captured successfully!');
}

main().catch(err => {
    console.error('Error taking screenshots:', err);
    process.exit(1);
});
