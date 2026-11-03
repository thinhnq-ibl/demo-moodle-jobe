const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const OUT_DIR = path.join(__dirname, '..', 'verification_evidence', 'screenshots_after');
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

    console.log('=== BẮT ĐẦU CHỤP KIỂM CHỨNG GIAO DIỆN AFTER ===');

    // 1. HEALTH CHECK AFTER
    console.log('[1/7] Chụp Health Check After...');
    const adminCtx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const adminPage = await adminCtx.newPage();
    await loginUser(adminPage, adminCtx, 'admin', 'AdminPassword123!');
    await adminPage.goto('http://localhost:8080/local/testcase_exchange/health.php');
    await adminPage.waitForLoadState('networkidle');
    await adminPage.screenshot({ path: path.join(OUT_DIR, '01_health_check_after_desktop.png'), fullPage: true });

    // 2. STUDENT DESKTOP (1440x900)
    console.log('[2/7] Chụp Student Desktop View (1440x900)...');
    const studentCtx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const studentPage = await studentCtx.newPage();
    await loginUser(studentPage, studentCtx, 'student1', 'StudentPassword123!');
    await studentPage.goto('http://localhost:8080/local/testcase_exchange/index.php?course=2&question=76:794');
    await studentPage.waitForLoadState('networkidle');
    await studentPage.waitForTimeout(800);
    await studentPage.screenshot({ path: path.join(OUT_DIR, '02_student_desktop_after.png'), fullPage: true });

    // Thử chạy một testcase để sinh ra khối kết quả mới nhất & kiểm chứng
    console.log('[3/7] Sinh viên chạy thử một testcase và chụp khối Kết Quả Mới Nhất...');
    await studentPage.fill('#student_code', 'def find_max(numbers):\n    return max(numbers)');
    await studentPage.fill('#test_input', 'print(find_max([12, 45, 7, 23]))');
    await studentPage.fill('#predicted_output', '45');
    await studentPage.fill('#purpose', 'Kiểm tra trường hợp danh sách số nguyên dương có nhiều phần tử');
    
    // Bấm chạy thử
    await Promise.all([
        studentPage.waitForNavigation({ timeout: 15000 }).catch(() => {}),
        studentPage.click('#btnSubmitRunTest')
    ]);
    await studentPage.waitForLoadState('networkidle');
    await studentPage.waitForTimeout(1000);
    await studentPage.screenshot({ path: path.join(OUT_DIR, '03_student_run_result_after.png'), fullPage: true });

    // 3. STUDENT MOBILE (390x844)
    console.log('[4/7] Chụp Student Mobile View (390x844)...');
    const mobileCtx = await browser.newContext({
        viewport: { width: 390, height: 844 },
        isMobile: true,
        hasTouch: true
    });
    const mobilePage = await mobileCtx.newPage();
    await loginUser(mobilePage, mobileCtx, 'student1', 'StudentPassword123!');
    await mobilePage.goto('http://localhost:8080/local/testcase_exchange/index.php?course=2&question=76:794');
    await mobilePage.waitForLoadState('networkidle');
    await mobilePage.waitForTimeout(800);
    await mobilePage.screenshot({ path: path.join(OUT_DIR, '04_student_mobile_after.png'), fullPage: true });

    // 4. TEACHER MANAGEMENT HUB (1440x900)
    console.log('[5/7] Chụp Teacher Hub Desktop Overview (1440x900)...');
    await adminPage.goto('http://localhost:8080/local/testcase_exchange/index.php?course=2');
    await adminPage.waitForLoadState('networkidle');
    await adminPage.screenshot({ path: path.join(OUT_DIR, '05_teacher_hub_desktop_after.png'), fullPage: true });

    // 5. TEACHER REVIEW TAB
    console.log('[6/7] Chụp Teacher Review Tab (Duyệt đóng góp)...');
    await adminPage.click('a[href="#tab_review"]');
    await adminPage.waitForTimeout(800);
    await adminPage.screenshot({ path: path.join(OUT_DIR, '06_teacher_review_tab_after.png'), fullPage: true });

    // 6. TEACHER BANK & POLICY TABS
    console.log('[7/7] Chụp Teacher Bank & Policy Tabs...');
    await adminPage.click('a[href="#tab_bank"]');
    await adminPage.waitForTimeout(600);
    await adminPage.screenshot({ path: path.join(OUT_DIR, '07_teacher_bank_tab_after.png'), fullPage: true });

    await adminPage.click('a[href="#tab_policy"]');
    await adminPage.waitForTimeout(600);
    await adminPage.screenshot({ path: path.join(OUT_DIR, '08_teacher_policy_tab_after.png'), fullPage: true });

    // 7. TEACHER MOBILE VIEW (390x844)
    const teacherMobileCtx = await browser.newContext({
        viewport: { width: 390, height: 844 },
        isMobile: true,
        hasTouch: true
    });
    const teacherMobilePage = await teacherMobileCtx.newPage();
    await loginUser(teacherMobilePage, teacherMobileCtx, 'admin', 'AdminPassword123!');
    await teacherMobilePage.goto('http://localhost:8080/local/testcase_exchange/index.php?course=2');
    await teacherMobilePage.waitForLoadState('networkidle');
    await teacherMobilePage.waitForTimeout(800);
    await teacherMobilePage.screenshot({ path: path.join(OUT_DIR, '09_teacher_hub_mobile_after.png'), fullPage: true });

    await browser.close();
    console.log('=== HOÀN TẤT CHỤP TOÀN BỘ ẢNH KIỂM CHỨNG TẠI: ' + OUT_DIR + ' ===');
}

main().catch(err => {
    console.error('Lỗi chụp kiểm chứng:', err);
    process.exit(1);
});
