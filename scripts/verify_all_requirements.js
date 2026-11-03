const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

async function loginUser(page, context, username, password) {
    await context.clearCookies();
    await page.goto('http://localhost:8080/login/index.php');
    await page.waitForLoadState('networkidle');
    await page.fill('#username', username);
    await page.fill('#password', password);
    await page.click('#loginbtn');
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
    const outDir = path.resolve(__dirname, '../e2e/recordings/evidence');
    fs.mkdirSync(outDir, { recursive: true });

    const browser = await chromium.launch({
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    console.log('=== STEP 1: VERIFY COMPUTED FONTS ===');
    const ctxDesktop = await browser.newContext({ viewport: { width: 1440, height: 950 } });
    const page = await ctxDesktop.newPage();

    await loginUser(page, ctxDesktop, 'student1', 'StudentPassword123!');
    await page.goto('http://localhost:8080/local/testcase_exchange/index.php?course=3&question=2:119&lang=vi');
    await page.waitForLoadState('networkidle');

    const computedFonts = await page.evaluate(() => {
        const results = {};
        const selectors = {
            'body': 'body',
            'root': '.local-testcase-exchange-root',
            'heading_h3': '.local-testcase-exchange-root h3, .tce-heading',
            'subheading_h5': '.local-testcase-exchange-root h5',
            'paragraph': '.local-testcase-exchange-root p',
            'button_run': '#btnSubmitRunTest',
            'badge': '.badge',
            'table_header': '.tce-history-desktop-table th',
            'table_cell': '.tce-history-desktop-table td',
            'monospace_pre': 'pre.font-monospace',
            'input_box': '#test_input'
        };
        for (const [key, sel] of Object.entries(selectors)) {
            const el = document.querySelector(sel);
            if (el) {
                const style = window.getComputedStyle(el);
                results[key] = {
                    fontFamily: style.fontFamily,
                    fontSize: style.fontSize,
                    fontWeight: style.fontWeight
                };
            }
        }
        return results;
    });
    console.log('Computed Fonts Report:\n', JSON.stringify(computedFonts, null, 2));

    console.log('\n=== STEP 2: VERIFY REWARD STATS (NO AMBIGUOUS SLASH) ===');
    // Student view reward alert
    const studentRewardText = await page.evaluate(() => {
        const el = document.querySelector('.alert-info span[style*="1e3a8a"], .alert-info div[style*="1e3a8a"]');
        return el ? el.innerText.trim() : 'NOT_FOUND';
    });
    console.log('Student Reward Alert Text:', studentRewardText);

    // Teacher view reward card
    const pageTeacher = await ctxDesktop.newPage();
    await loginUser(pageTeacher, ctxDesktop, 'admin', 'AdminPassword123!');
    await pageTeacher.goto('http://localhost:8080/local/testcase_exchange/index.php?course=3&question=2:119&lang=vi');
    await pageTeacher.waitForLoadState('networkidle');

    const teacherRewardCardText = await pageTeacher.evaluate(() => {
        const card = document.querySelector('.tce-stat-card-mobile[style*="7c3aed"]');
        return card ? card.innerText.trim().replace(/\n+/g, ' | ') : 'NOT_FOUND';
    });
    console.log('Teacher Reward Stat Card Text:', teacherRewardCardText);
    await pageTeacher.screenshot({ path: path.join(outDir, '06_teacher_stat_cards_and_rewards.png') });
    console.log('Saved: 06_teacher_stat_cards_and_rewards.png');

    console.log('\n=== STEP 3: VERIFY RUN HISTORY WINDOWING (5 DEFAULT + EXPAND) ===');
    const historyStats = await page.evaluate(() => {
        const totalRows = document.querySelectorAll('.tce-history-desktop-table tbody tr').length;
        const hiddenRows = document.querySelectorAll('.tce-history-desktop-table tbody tr.tce-older-run.d-none').length;
        const visibleRows = totalRows - hiddenRows;
        const button = document.querySelector('.btn-toggle-older-runs');
        const codeSnapshots = document.querySelectorAll('.tce-history-desktop-table .run-code-content').length;
        return {
            totalRows,
            visibleRows,
            hiddenRows,
            buttonText: button ? button.innerText.trim() : null,
            codeSnapshotsCount: codeSnapshots
        };
    });
    console.log('History Table Stats:', historyStats);

    const showMoreBtn = await page.$('.btn-toggle-older-runs');
    if (showMoreBtn) {
        await showMoreBtn.click();
        await page.waitForTimeout(400);
        await page.screenshot({ path: path.join(outDir, '07_long_history_windowing.png') });
        console.log('Saved: 07_long_history_windowing.png');
    }

    console.log('\n=== STEP 4: VERIFY INTENTIONAL MISMATCH (#75) STATE ORDERING ===');
    // Input: print(is_prime(7)), Expected: False (Oracle is True)
    const isPrimeCode = `def is_prime(n):
    if not isinstance(n, int):
        return False
    if n <= 1:
        return False
    for i in range(2, int(n**0.5) + 1):
        if n % i == 0:
            return False
    return True`;
    await setAceValue(page, isPrimeCode);
    await page.fill('#test_input', 'print(is_prime(7))');
    await page.fill('#predicted_output', 'False');
    await page.selectOption('#category', 'normal');
    await page.fill('#purpose', 'Kiểm tra trường hợp dự đoán False khi tham chiếu là True');
    await page.waitForTimeout(500);

    await Promise.all([
        page.waitForNavigation({ waitUntil: 'load' }).catch(() => {}),
        page.click('#btnSubmitRunTest')
    ]);
    await page.waitForLoadState('networkidle');

    const mismatchCardTexts = await page.evaluate(() => {
        const card = document.getElementById('tceLatestResultCard');
        if (!card) return null;
        const runOutcome = card.querySelector('.col-md-6:first-child')?.innerText.trim().replace(/\n+/g, ' | ');
        const contribStatus = card.querySelector('.col-md-6:last-child')?.innerText.trim().replace(/\n+/g, ' | ');
        return { runOutcome, contribStatus };
    });
    console.log('Mismatch Latest Result Card:\n', mismatchCardTexts);
    await page.screenshot({ path: path.join(outDir, '02b_student_mismatch_after_run.png') });
    console.log('Saved: 02b_student_mismatch_after_run.png');

    console.log('\n=== STEP 5: VERIFY ELIGIBLE BUT UNCHANGED -> DIRTY CHANGED FLOW ===');
    // Setup a new fresh eligible testcase: print(is_prime(89)) => True
    await setAceValue(page, isPrimeCode);
    await page.fill('#test_input', 'print(is_prime(89))');
    await page.fill('#predicted_output', 'True');
    await page.selectOption('#category', 'normal');
    await page.fill('#purpose', 'Kiểm thử số nguyên tố 89 (đủ điều kiện đóng góp)');
    await page.waitForTimeout(500);

    await Promise.all([
        page.waitForNavigation({ waitUntil: 'load' }).catch(() => {}),
        page.click('#btnSubmitRunTest')
    ]);
    await page.waitForLoadState('networkidle');

    // 10a: Eligible, clean, unsubmitted state
    await page.screenshot({ path: path.join(outDir, '10a_student_eligible_unsubmitted_clean.png') });
    console.log('Saved: 10a_student_eligible_unsubmitted_clean.png');

    // Now edit code/input to trigger DIRTY state
    await page.fill('#test_input', 'print(is_prime(89)) # Sửa dữ liệu');
    await page.waitForTimeout(400);

    const dirtyCardState = await page.evaluate(() => {
        const banner = document.getElementById('tceFormDirtyWarning');
        const btnLbl = document.getElementById('btnSubmitContribLabel');
        const hint = document.getElementById('dirtyContribHint');
        return {
            bannerVisible: banner ? !banner.classList.contains('d-none') : false,
            bannerText: banner ? banner.innerText.trim() : null,
            buttonLabel: btnLbl ? btnLbl.innerText.trim() : null,
            hintVisible: hint ? !hint.classList.contains('d-none') : false,
            hintText: hint ? hint.innerText.trim() : null
        };
    });
    console.log('Dirty State Verification:', dirtyCardState);
    await page.screenshot({ path: path.join(outDir, '10b_student_eligible_unsubmitted_dirty_changed.png') });
    console.log('Saved: 10b_student_eligible_unsubmitted_dirty_changed.png');

    console.log('\n=== STEP 6: CAPTURE MOBILE EVIDENCE (EDITOR, RESULTS, MODAL FOOTER) ===');
    const ctxMobile = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true });
    const pageMob = await ctxMobile.newPage();
    await loginUser(pageMob, ctxMobile, 'student1', 'StudentPassword123!');
    await pageMob.goto('http://localhost:8080/local/testcase_exchange/index.php?course=3&question=2:119&lang=vi');
    await pageMob.waitForLoadState('networkidle');

    // 08a: Top Screen 1
    await pageMob.screenshot({ path: path.join(outDir, '08a_student_mobile_top.png') });
    console.log('Saved: 08a_student_mobile_top.png');

    // 08b: Scrolled to Ace Editor & Inputs & Run Button
    await pageMob.evaluate(() => {
        const ed = document.getElementById('student_code_ace');
        if (ed) ed.scrollIntoView({ behavior: 'instant', block: 'start' });
    });
    await pageMob.waitForTimeout(400);
    await pageMob.screenshot({ path: path.join(outDir, '08b_student_mobile_editor_and_run_btn.png') });
    console.log('Saved: 08b_student_mobile_editor_and_run_btn.png');

    // 08c: Scrolled to Result Card and History Cards
    await pageMob.evaluate(() => {
        const resCard = document.getElementById('tceLatestResultCard');
        if (resCard) resCard.scrollIntoView({ behavior: 'instant', block: 'start' });
    });
    await pageMob.waitForTimeout(400);
    await pageMob.screenshot({ path: path.join(outDir, '08c_student_mobile_latest_result_and_history.png') });
    console.log('Saved: 08c_student_mobile_latest_result_and_history.png');

    // Teacher Review Modal on Mobile
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

        // 09a: Modal Top / Header
        await pageMobTeacher.screenshot({ path: path.join(outDir, '09a_teacher_review_modal_mobile_header.png') });
        console.log('Saved: 09a_teacher_review_modal_mobile_header.png');

        // 09b: Modal Footer with Save Button
        await pageMobTeacher.evaluate(() => {
            const saveBtn = document.querySelector('.modal.show button[type="submit"], .modal.show .modal-footer, .modal.show .btn-primary');
            if (saveBtn) saveBtn.scrollIntoView({ behavior: 'instant', block: 'center' });
        });
        await pageMobTeacher.waitForTimeout(400);
        await pageMobTeacher.screenshot({ path: path.join(outDir, '09b_teacher_review_modal_mobile_footer.png') });
        console.log('Saved: 09b_teacher_review_modal_mobile_footer.png');
    }

    await browser.close();
    console.log('\n=== ALL VERIFICATION COMPLETED SUCCESSFULLY ===');
}

main().catch(err => {
    console.error('Error running verification:', err);
    process.exit(1);
});
