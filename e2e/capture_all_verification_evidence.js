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

async function setAceValue(page, code) {
    await page.evaluate((c) => {
        const edDiv = document.getElementById('student_code_ace');
        if (edDiv && window.ace) {
            const editor = window.ace.edit(edDiv);
            if (editor) {
                editor.setValue(c, -1);
            }
        }
        const ta = document.getElementById('student_code');
        if (ta) {
            ta.value = c;
            ta.dispatchEvent(new Event('input', { bubbles: true }));
        }
    }, code);
}

async function main() {
    const outDir = 'e2e/recordings/evidence';
    fs.mkdirSync(outDir, { recursive: true });

    const executablePath = 'C:\\Users\\Thinh Nguyen\\AppData\\Local\\ms-playwright\\chromium-1243\\chrome-win64\\chrome.exe';
    const browser = await chromium.launch({
        headless: true,
        executablePath: executablePath,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    const isPrimeCode = `def is_prime(n):
    if not isinstance(n, int):
        return False
    if n <= 1:
        return False
    for i in range(2, int(n**0.5) + 1):
        if n % i == 0:
            return False
    return True`;

    // =========================================================================
    // 1. DESKTOP FLOWS: Matching Testcase (Before Run, After Run, Contribute)
    // =========================================================================
    const ctxDesktop = await browser.newContext({ viewport: { width: 1440, height: 950 } });
    const page = await ctxDesktop.newPage();

    await loginUser(page, ctxDesktop, 'student1', 'StudentPassword123!');
    await page.goto('http://localhost:8080/local/testcase_exchange/index.php?course=3&question=2:119&lang=vi');
    await page.waitForLoadState('networkidle');

    // Setup Matching Testcase: print(is_prime(7)) => True
    await setAceValue(page, isPrimeCode);
    await page.fill('#test_input', 'print(is_prime(7))');
    await page.fill('#predicted_output', 'True');
    await page.fill('#purpose', 'Kiểm thử số nguyên tố lẻ 7 (kỳ vọng True)');
    await page.waitForTimeout(600); // Allow draft save debounce

    await page.screenshot({ path: `${outDir}/01a_student_match_before_run.png` });
    console.log('Saved: 01a_student_match_before_run.png');

    // Run Test
    await Promise.all([
        page.waitForNavigation(),
        page.click('#btnSubmitRunTest')
    ]);
    await page.waitForLoadState('networkidle');
    await page.screenshot({ path: `${outDir}/01b_student_match_after_run.png` });
    console.log('Saved: 01b_student_match_after_run.png');

    // Click "Gửi đóng góp" if available
    const contributeBtn = await page.$('form button:has-text("Gửi đóng góp")');
    if (contributeBtn) {
        await Promise.all([
            page.waitForNavigation(),
            contributeBtn.click()
        ]);
        await page.waitForLoadState('networkidle');
        await page.screenshot({ path: `${outDir}/01c_student_contributed_state.png` });
        console.log('Saved: 01c_student_contributed_state.png');
    }

    // =========================================================================
    // 2. DESKTOP FLOWS: Intentional Mismatch Pair (Before Run vs After Run)
    // =========================================================================
    // Setup Mismatch Testcase: print(is_prime(7)) => Expected False
    await setAceValue(page, isPrimeCode);
    await page.fill('#test_input', 'print(is_prime(7))');
    await page.fill('#predicted_output', 'False'); // Intentional mismatch
    await page.fill('#purpose', 'Kiểm tra phản hồi khi kết quả kỳ vọng lệch chuẩn (expected False)');
    await page.waitForTimeout(600);

    await page.screenshot({ path: `${outDir}/02a_student_mismatch_before_run.png` });
    console.log('Saved: 02a_student_mismatch_before_run.png');

    // Run Test with mismatch
    await Promise.all([
        page.waitForNavigation(),
        page.click('#btnSubmitRunTest')
    ]);
    await page.waitForLoadState('networkidle');
    await page.screenshot({ path: `${outDir}/02b_student_mismatch_after_run.png` });
    console.log('Saved: 02b_student_mismatch_after_run.png');

    // =========================================================================
    // 3. Draft Auto-save and Revision Restore Proof
    // =========================================================================
    await page.evaluate(() => {
        const edDiv = document.getElementById('student_code_ace');
        if (edDiv && window.ace) {
            const editor = window.ace.edit(edDiv);
            editor.insert('\n# Thử nghiệm tính năng lưu nháp tức thời');
        }
    });
    await page.waitForTimeout(600);
    await page.screenshot({ path: `${outDir}/03_draft_and_revisions_sync.png` });
    console.log('Saved: 03_draft_and_revisions_sync.png');

    // =========================================================================
    // 4. Long Run History Windowing (5 default + Show more toggle)
    // =========================================================================
    const showMoreBtn = await page.$('.btn-toggle-older-runs');
    if (showMoreBtn) {
        await showMoreBtn.click();
        await page.waitForTimeout(400);
        await page.screenshot({ path: `${outDir}/07_long_history_windowing.png` });
        console.log('Saved: 07_long_history_windowing.png');
    }

    // =========================================================================
    // 5. Teacher Desktop: Overview, Review Modal, Testcase Bank
    // =========================================================================
    const pageTeacher = await ctxDesktop.newPage();
    await loginUser(pageTeacher, ctxDesktop, 'admin', 'AdminPassword123!');
    await pageTeacher.goto('http://localhost:8080/local/testcase_exchange/index.php?course=3&question=2:119&lang=vi');
    await pageTeacher.waitForLoadState('networkidle');

    // Stat cards & Overview
    await pageTeacher.screenshot({ path: `${outDir}/06_teacher_stat_cards_and_rewards.png` });
    console.log('Saved: 06_teacher_stat_cards_and_rewards.png');

    // Review Tab & Modal
    await pageTeacher.click('a[href="#tab_review"]');
    await pageTeacher.waitForTimeout(500);
    const reviewModalTrigger = await pageTeacher.$('button[data-target^="#reviewModal_"]');
    if (reviewModalTrigger) {
        await reviewModalTrigger.click();
        await pageTeacher.waitForTimeout(600);
        await pageTeacher.screenshot({ path: `${outDir}/04_teacher_review_modal_desktop.png` });
        console.log('Saved: 04_teacher_review_modal_desktop.png');
        await pageTeacher.click('.modal.show button[data-dismiss="modal"]');
        await pageTeacher.waitForTimeout(400);
    }

    // Testcase Bank Tab
    await pageTeacher.click('a[href="#tab_bank"]');
    await pageTeacher.waitForTimeout(500);
    await pageTeacher.screenshot({ path: `${outDir}/05_teacher_bank_contributor_display.png` });
    console.log('Saved: 05_teacher_bank_contributor_display.png');

    // =========================================================================
    // 6. Mobile 390x844: Student Top (Screen 1 reaches Editor) & Teacher Modal
    // =========================================================================
    const ctxMobile = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true });
    const pageMobStudent = await ctxMobile.newPage();
    await loginUser(pageMobStudent, ctxMobile, 'student1', 'StudentPassword123!');
    await pageMobStudent.goto('http://localhost:8080/local/testcase_exchange/index.php?course=3&question=2:119&lang=vi');
    await pageMobStudent.waitForLoadState('networkidle');
    await pageMobStudent.screenshot({ path: `${outDir}/08_student_mobile_390x844_top.png` });
    console.log('Saved: 08_student_mobile_390x844_top.png');

    const pageMobTeacher = await ctxMobile.newPage();
    await loginUser(pageMobTeacher, ctxMobile, 'admin', 'AdminPassword123!');
    await pageMobTeacher.goto('http://localhost:8080/local/testcase_exchange/index.php?course=3&question=2:119&lang=vi');
    await pageMobTeacher.waitForLoadState('networkidle');

    await pageMobTeacher.click('a[href="#tab_review"]');
    await pageMobTeacher.waitForTimeout(500);
    const mobReviewBtn = await pageMobTeacher.$('button[data-target^="#reviewModal_"]');
    if (mobReviewBtn) {
        await mobReviewBtn.click();
        await pageMobTeacher.waitForTimeout(600);
        await pageMobTeacher.screenshot({ path: `${outDir}/09_teacher_review_modal_mobile_390x844.png` });
        console.log('Saved: 09_teacher_review_modal_mobile_390x844.png');
    }

    await browser.close();
    console.log('All verification evidence captured successfully!');
}

main().catch(console.error);
