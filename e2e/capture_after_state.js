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

async function checkOverflow(page, label) {
    const pluginOverflow = await page.evaluate(() => {
        const root = document.querySelector('.local-testcase-exchange-root');
        if (!root) return { scrollW: 0, clientW: 0, diff: 0 };
        return { scrollW: root.scrollWidth, clientW: root.clientWidth, diff: root.scrollWidth - root.clientWidth };
    });
    console.log(`[PLUGIN OVERFLOW CHECK] ${label}: scrollWidth=${pluginOverflow.scrollW}, clientWidth=${pluginOverflow.clientW}, diff=${pluginOverflow.diff}`);
    if (pluginOverflow.diff > 2) {
        const offPlugin = await page.evaluate(() => {
            const root = document.querySelector('.local-testcase-exchange-root');
            if (!root) return [];
            const rRect = root.getBoundingClientRect();
            const bad = [];
            for (const el of root.querySelectorAll('*')) {
                const rect = el.getBoundingClientRect();
                if (rect.right > rRect.right + 2) {
                    bad.push({ tag: el.tagName, id: el.id, cls: el.className, right: Math.round(rect.right), rootRight: Math.round(rRect.right) });
                }
            }
            return bad.slice(0, 5);
        });
        console.warn(`[PLUGIN OVERFLOW ELEMENTS] for ${label}:`, offPlugin);
    }
    if (pluginOverflow.diff > 2) {
        console.warn(`⚠️ PLUGIN OVERFLOW DETECTED for ${label}!`);
    } else {
        console.log(`✅ PLUGIN PERFECTLY FITS (NO OVERFLOW) for ${label}`);
    }
    const overflowInfo = await page.evaluate(() => {
        const root = document.documentElement;
        const body = document.body;
        const scrollW = Math.max(root.scrollWidth, body.scrollWidth);
        const clientW = root.clientWidth;
        const diff = scrollW - clientW;
        
        let offendingElements = [];
        if (diff > 2) {
            const all = document.querySelectorAll('*');
            for (const el of all) {
                const rect = el.getBoundingClientRect();
                if (rect.right > clientW + 2) {
                    offendingElements.push({
                        tag: el.tagName,
                        id: el.id,
                        className: el.className,
                        rectRight: Math.round(rect.right),
                        clientW: clientW
                    });
                }
            }
        }
        return { scrollW, clientW, diff, offendingElements: offendingElements.slice(0, 5) };
    });
    console.log(`[OVERFLOW CHECK] ${label}: scrollWidth=${overflowInfo.scrollW}, clientWidth=${overflowInfo.clientW}, diff=${overflowInfo.diff}`);
    if (overflowInfo.diff > 2) {
        console.warn(`⚠️ OVERFLOW DETECTED for ${label}:`, overflowInfo.offendingElements);
    } else {
        console.log(`✅ NO OVERFLOW for ${label}`);
    }
    return overflowInfo;
}

async function main() {
    const executablePath = 'C:\\Users\\Thinh Nguyen\\AppData\\Local\\ms-playwright\\chromium-1243\\chrome-win64\\chrome.exe';
    const browser = await chromium.launch({
        headless: true,
        executablePath: executablePath,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    console.log('=== BẮT ĐẦU CHẠY KIỂM CHỨNG TOÀN DIỆN & CHỤP ẢNH EVIDENCE ===');

    // 1. HEALTH CHECK AFTER (1440x900)
    console.log('[1/10] Chụp Health Check After...');
    const adminCtx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const adminPage = await adminCtx.newPage();
    await loginUser(adminPage, adminCtx, 'admin', 'AdminPassword123!');
    await adminPage.goto('http://localhost:8080/local/testcase_exchange/health.php');
    await adminPage.waitForLoadState('networkidle');
    await adminPage.screenshot({ path: path.join(OUT_DIR, '01_health_check_after_desktop.png'), fullPage: true });

    // 2. STUDENT DESKTOP (1440x900)
    console.log('[2/10] Chụp Student Desktop Initial...');
    const studentCtx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const studentPage = await studentCtx.newPage();
    await loginUser(studentPage, studentCtx, 'student1', 'StudentPassword123!');
    await studentPage.goto('http://localhost:8080/local/testcase_exchange/index.php?course=3&question=2:119');
    await studentPage.waitForLoadState('networkidle');
    await studentPage.screenshot({ path: path.join(OUT_DIR, '02_student_desktop_after.png'), fullPage: true });
    console.log('[DEBUG] Student page URL:', studentPage.url());
    const bodySnippet = await studentPage.evaluate(() => document.body.innerText.slice(0, 400));
    console.log('[DEBUG] Body text snippet:', bodySnippet.replace(/\n+/g, ' '));
    const textareaInfo = await studentPage.$$eval('textarea', els => els.map(e => ({ id: e.id, name: e.name, visible: e.offsetParent !== null })));
    console.log('[DEBUG] Textarea info:', JSON.stringify(textareaInfo));
    const pageHtml = await studentPage.content();
    fs.writeFileSync(path.join(OUT_DIR, 'student_page_debug.html'), pageHtml, 'utf8');

    // 3. STUDENT RUN TEST (Chạy testcase mới và kiểm chứng)
    console.log('[3/10] Sinh viên chạy testcase...');
    await studentPage.fill('#student_code', 'def is_prime(n):\n    if n <= 1:\n        return False\n    for i in range(2, int(n**0.5) + 1):\n        if n % i == 0:\n            return False\n    return True');
    const uniqueVal = (Math.floor(Math.random() * 4000) + 1000) * 2;
    await studentPage.fill('#test_input', `print(is_prime(${uniqueVal}))`);
    await studentPage.fill('#predicted_output', 'False');
    await studentPage.fill('#purpose', `Kiểm thử giá trị cực đại ${uniqueVal}`);
    await Promise.all([
        studentPage.waitForNavigation({ timeout: 15000 }).catch(() => {}),
        studentPage.click('#btnSubmitRunTest')
    ]);
    await studentPage.waitForLoadState('networkidle');
    await studentPage.waitForTimeout(1000);
    await studentPage.screenshot({ path: path.join(OUT_DIR, '03_student_run_result_after.png'), fullPage: true });

    // 4. SUBMIT CONTRIBUTION & VERIFY DECOUPLED STATUS
    console.log('[4/10] Sinh viên gửi đóng góp & chụp 03b_student_contribution_submitted.png...');
    const contributeBtn = await studentPage.$('button:has-text("Gửi đóng góp"), button:has-text("Submit contribution")');
    if (contributeBtn) {
        await Promise.all([
            studentPage.waitForNavigation({ timeout: 15000 }).catch(() => {}),
            contributeBtn.click()
        ]);
        await studentPage.waitForLoadState('networkidle');
        await studentPage.waitForTimeout(1000);
        await studentPage.screenshot({ path: path.join(OUT_DIR, '03b_student_contribution_submitted.png'), fullPage: true });
        console.log('✅ Đã lưu 03b_student_contribution_submitted.png');
    } else {
        console.log('Nút Gửi đóng góp không khả dụng (có thể đã gửi)');
    }

    // 5. STUDENT MOBILE (390x844) & (768x1024)
    console.log('[5/10] Kiểm tra Student Mobile 390x844 & 768x1024...');
    const mobileCtx = await browser.newContext({
        viewport: { width: 390, height: 844 },
        isMobile: true,
        hasTouch: true
    });
    const mobilePage = await mobileCtx.newPage();
    await loginUser(mobilePage, mobileCtx, 'student1', 'StudentPassword123!');
    await mobilePage.goto('http://localhost:8080/local/testcase_exchange/index.php?course=3&question=2:119');
    await mobilePage.waitForLoadState('networkidle');
    await mobilePage.waitForTimeout(800);
    await checkOverflow(mobilePage, 'Student Mobile 390x844');
    await mobilePage.screenshot({ path: path.join(OUT_DIR, '04_student_mobile_after.png'), fullPage: true });

    // Kiểm tra nút Xem thêm trên mobile history
    const viewMoreBtn = await mobilePage.$('#btnToggleRunHistoryMobile');
    if (viewMoreBtn) {
        console.log('Tìm thấy nút Xem thêm trên mobile run history, bấm thử toggle...');
        await viewMoreBtn.click();
        await mobilePage.waitForTimeout(400);
        await mobilePage.screenshot({ path: path.join(OUT_DIR, '04b_student_mobile_history_expanded.png'), fullPage: true });
    }

    // Tablet 768px
    const tabletCtx = await browser.newContext({ viewport: { width: 768, height: 1024 } });
    const tabletPage = await tabletCtx.newPage();
    await loginUser(tabletPage, tabletCtx, 'student1', 'StudentPassword123!');
    await tabletPage.goto('http://localhost:8080/local/testcase_exchange/index.php?course=3&question=2:119');
    await tabletPage.waitForLoadState('networkidle');
    await checkOverflow(tabletPage, 'Student Tablet 768x1024');

    // 6. TEACHER DESKTOP OVERVIEW (1440x900)
    console.log('[6/10] Chụp Teacher Hub Desktop Overview...');
    await adminPage.goto('http://localhost:8080/local/testcase_exchange/index.php?course=3');
    await adminPage.waitForLoadState('networkidle');
    await adminPage.screenshot({ path: path.join(OUT_DIR, '05_teacher_hub_desktop_after.png'), fullPage: true });

    // 7. TEACHER REVIEW TAB WITH DATA (06a, 06b, 06c)
    console.log('[7/10] Chụp Teacher Review Tab with Data & Modal Review Workflow...');
    await adminPage.goto('http://localhost:8080/local/testcase_exchange/index.php?course=3&question=2:119&tab=review');
    await adminPage.waitForLoadState('networkidle');
    await adminPage.waitForTimeout(800);
    await adminPage.screenshot({ path: path.join(OUT_DIR, '06a_teacher_review_with_data_desktop.png'), fullPage: true });
    await adminPage.screenshot({ path: path.join(OUT_DIR, '06_teacher_review_tab_after.png'), fullPage: true });

    // Tìm nút mở review modal theo data-target
    const openModalBtn = await adminPage.$('button[data-target^="#reviewModal_"]');
    if (openModalBtn) {
        console.log('Tìm thấy nút mở review modal, click mở modal...');
        await openModalBtn.click();
        await adminPage.waitForTimeout(800);
        
        // Tìm modal đang mở
        const modal = await adminPage.$('.modal.show, div[id^="reviewModal_"]');
        if (modal) {
            console.log('Modal review đã mở thành công!');
            // Điền quyết định Chấp thuận
            const decisionSelect = await modal.$('select[name="decision"]');
            if (decisionSelect) await decisionSelect.selectOption('approved');

            // Điền 5 sao
            const ratingSelect = await modal.$('select[name="rating"]');
            if (ratingSelect) await ratingSelect.selectOption('5');

            // Điền nhận xét sư phạm
            const noteTextarea = await modal.$('textarea[name="review_note"]');
            if (noteTextarea) await noteTextarea.fill('Testcase rất tốt, dữ liệu kiểm thử bao quát và đạt chuẩn chấp thuận!');

            await adminPage.screenshot({ path: path.join(OUT_DIR, '06b_review_action_dialog_populated.png') });
            console.log('✅ Đã lưu 06b_review_action_dialog_populated.png');

            // Bấm Lưu quyết định thẩm định
            const submitBtn = await modal.$('button[type="submit"]');
            if (submitBtn) {
                console.log('Bấm nút Lưu quyết định thẩm định...');
            const formAction = await modal.$eval('form', f => ({ action: f.action, method: f.method, actVal: f.querySelector('input[name="action"]')?.value, cid: f.querySelector('input[name="contribution_id"]')?.value, dec: f.querySelector('select[name="decision"]')?.value, sesskey: f.querySelector('input[name="sesskey"]')?.value }));
            console.log('[DEBUG] Modal Form submission payload:', JSON.stringify(formAction));
                await Promise.all([
                    adminPage.waitForNavigation({ timeout: 15000 }).catch(() => {}),
                    submitBtn.click()
                ]);
                await adminPage.waitForLoadState('networkidle');
                await adminPage.waitForTimeout(1000);
                await adminPage.waitForTimeout(1000);
            await adminPage.goto('http://localhost:8080/local/testcase_exchange/index.php?course=3&question=2:119&tab=review');
            await adminPage.waitForLoadState('networkidle');
            await adminPage.screenshot({ path: path.join(OUT_DIR, '06c_review_approved_state_verified.png'), fullPage: true });
                console.log('✅ Đã lưu 06c_review_approved_state_verified.png');
            }
        }
    } else {
        console.log('Chưa tìm thấy nút mở review modal.');
    }

    // 8. TEACHER BANK & POLICY TABS
    console.log('[8/10] Chụp Teacher Bank & Policy Tabs...');
    await adminPage.click('a[href="#tab_bank"]');
    await adminPage.waitForTimeout(600);
    await adminPage.screenshot({ path: path.join(OUT_DIR, '07_teacher_bank_tab_after.png'), fullPage: true });

    await adminPage.click('a[href="#tab_policy"]');
    await adminPage.waitForTimeout(600);
    await adminPage.screenshot({ path: path.join(OUT_DIR, '08_teacher_policy_tab_after.png'), fullPage: true });

    // 9. TEACHER MOBILE VIEW (390x844)
    console.log('[9/10] Kiểm tra và chụp Teacher Hub Mobile (390x844)...');
    const teacherMobileCtx = await browser.newContext({
        viewport: { width: 390, height: 844 },
        isMobile: true,
        hasTouch: true
    });
    const teacherMobilePage = await teacherMobileCtx.newPage();
    await loginUser(teacherMobilePage, teacherMobileCtx, 'admin', 'AdminPassword123!');
    await teacherMobilePage.goto('http://localhost:8080/local/testcase_exchange/index.php?course=3');
    await teacherMobilePage.waitForLoadState('networkidle');
    await teacherMobilePage.waitForTimeout(800);
    await checkOverflow(teacherMobilePage, 'Teacher Mobile 390x844');
    await teacherMobilePage.screenshot({ path: path.join(OUT_DIR, '09_teacher_hub_mobile_after.png'), fullPage: true });

    // 10. ĐÓNG TRÌNH DUYỆT
    console.log('[10/10] Hoàn tất chụp toàn bộ ảnh evidence!');
    await browser.close();
}

main().catch(err => {
    console.error('Lỗi khi chạy e2e capture:', err);
    process.exit(1);
});
