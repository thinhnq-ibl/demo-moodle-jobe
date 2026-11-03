const { chromium } = require('playwright');
const fs = require('fs');

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
    fs.mkdirSync('e2e/recordings/final', { recursive: true });

    const executablePath = 'C:\\Users\\Thinh Nguyen\\AppData\\Local\\ms-playwright\\chromium-1243\\chrome-win64\\chrome.exe';
    const browser = await chromium.launch({
        headless: true,
        executablePath: executablePath,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    // 1. Desktop Student VI (1440x900)
    const ctxDesktop = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const pageStudent = await ctxDesktop.newPage();
    await loginUser(pageStudent, ctxDesktop, 'student1', 'StudentPassword123!');
    await pageStudent.goto('http://localhost:8080/local/testcase_exchange/index.php?course=3&question=2:119&lang=vi');
    await pageStudent.waitForLoadState('networkidle');
    await pageStudent.screenshot({ path: 'e2e/recordings/final/01_student_experimentation_desktop_vi.png' });
    console.log('Captured 01_student_experimentation_desktop_vi.png');

    // 2. Desktop Student EN (1440x900)
    await pageStudent.goto('http://localhost:8080/local/testcase_exchange/index.php?course=3&question=2:119&lang=en');
    await pageStudent.waitForLoadState('networkidle');
    await pageStudent.screenshot({ path: 'e2e/recordings/final/02_student_experimentation_desktop_en.png' });
    console.log('Captured 02_student_experimentation_desktop_en.png');

    // 3. Desktop Teacher VI & Review Modal (1440x900)
    const pageTeacher = await ctxDesktop.newPage();
    await loginUser(pageTeacher, ctxDesktop, 'admin', 'AdminPassword123!');
    await pageTeacher.goto('http://localhost:8080/local/testcase_exchange/index.php?course=3&question=2:119&lang=vi');
    await pageTeacher.waitForLoadState('networkidle');

    // Click Review tab
    await pageTeacher.click('a[href="#tab_review"]');
    await pageTeacher.waitForTimeout(500);

    // Open first review modal
    const reviewBtn = await pageTeacher.$('button[data-target^="#reviewModal_"]');
    if (reviewBtn) {
        await reviewBtn.click();
        await pageTeacher.waitForTimeout(600);
        await pageTeacher.screenshot({ path: 'e2e/recordings/final/03_teacher_review_modal_vi.png' });
        console.log('Captured 03_teacher_review_modal_vi.png');
        await pageTeacher.click('.modal.show button[data-dismiss="modal"]');
        await pageTeacher.waitForTimeout(400);
    }

    // 4. Desktop Teacher Testcase Bank (1440x900)
    await pageTeacher.click('a[href="#tab_bank"]');
    await pageTeacher.waitForTimeout(500);
    await pageTeacher.screenshot({ path: 'e2e/recordings/final/04_teacher_testcase_bank_desktop.png' });
    console.log('Captured 04_teacher_testcase_bank_desktop.png');

    // 5. Mobile Student (390x844)
    const ctxMobile = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true });
    const pageMobileStudent = await ctxMobile.newPage();
    await loginUser(pageMobileStudent, ctxMobile, 'student1', 'StudentPassword123!');
    await pageMobileStudent.goto('http://localhost:8080/local/testcase_exchange/index.php?course=3&question=2:119&lang=vi');
    await pageMobileStudent.waitForLoadState('networkidle');
    await pageMobileStudent.screenshot({ path: 'e2e/recordings/final/05_student_mobile_390x844.png' });
    console.log('Captured 05_student_mobile_390x844.png');

    // 6. Mobile Teacher (390x844)
    const pageMobileTeacher = await ctxMobile.newPage();
    await loginUser(pageMobileTeacher, ctxMobile, 'admin', 'AdminPassword123!');
    await pageMobileTeacher.goto('http://localhost:8080/local/testcase_exchange/index.php?course=3&question=2:119&lang=vi');
    await pageMobileTeacher.waitForLoadState('networkidle');
    await pageMobileTeacher.screenshot({ path: 'e2e/recordings/final/06_teacher_mobile_390x844.png' });
    console.log('Captured 06_teacher_mobile_390x844.png');

    await browser.close();
    console.log('All final verification screenshots captured successfully!');
}

main().catch(err => {
    console.error(err);
    process.exit(1);
});
