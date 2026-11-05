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

async function run() {
    const executablePath = 'C:\\Users\\Thinh Nguyen\\AppData\\Local\\ms-playwright\\chromium-1243\\chrome-win64\\chrome.exe';
    const browser = await chromium.launch({
        headless: true,
        executablePath: executablePath,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await ctx.newPage();

    // 1. Check Student Page in Vietnamese
    await loginUser(page, ctx, 'student1', 'StudentPassword123!');
    await page.goto('http://localhost:8080/local/testcase_exchange/index.php?course=3&question=2:119&lang=vi');
    await page.waitForLoadState('networkidle');

    const viData = await page.evaluate(() => {
        const tabs = Array.from(document.querySelectorAll('.nav-tabs .nav-link')).map(el => el.textContent.trim());
        const solveCardHeader = document.querySelector('.tce-code-column .card-header')?.textContent.trim();
        const testcaseCardHeader = document.querySelector('.tce-testcase-column .card-header')?.textContent.trim();
        const runBtn = document.getElementById('btnSubmitRunTest')?.textContent.trim();
        const backLink = document.querySelector('a[href*="attempt"], a[href*="review"]')?.textContent.trim();
        const rewardCard = document.querySelector('.alert-info')?.textContent.trim();
        const olderRunsBtn = document.querySelector('.btn-toggle-older-runs')?.textContent.trim();
        return { tabs, solveCardHeader, testcaseCardHeader, runBtn, backLink, rewardCard, olderRunsBtn };
    });
    console.log('VI Student Data:', JSON.stringify(viData, null, 2));

    await page.screenshot({ path: 'e2e/recordings/student_page_vi.png' });

    // 2. Check Student Page in English
    await page.goto('http://localhost:8080/local/testcase_exchange/index.php?course=3&question=2:119&lang=en');
    await page.waitForLoadState('networkidle');

    const enData = await page.evaluate(() => {
        const tabs = Array.from(document.querySelectorAll('.nav-tabs .nav-link')).map(el => el.textContent.trim());
        const solveCardHeader = document.querySelector('.tce-code-column .card-header')?.textContent.trim();
        const testcaseCardHeader = document.querySelector('.tce-testcase-column .card-header')?.textContent.trim();
        const runBtn = document.getElementById('btnSubmitRunTest')?.textContent.trim();
        const backLink = document.querySelector('a[href*="attempt"], a[href*="review"]')?.textContent.trim();
        const olderRunsBtn = document.querySelector('.btn-toggle-older-runs')?.textContent.trim();
        return { tabs, solveCardHeader, testcaseCardHeader, runBtn, backLink, olderRunsBtn };
    });
    console.log('EN Student Data:', JSON.stringify(enData, null, 2));

    await page.screenshot({ path: 'e2e/recordings/student_page_en.png' });

    // 3. Check Teacher Page in Vietnamese
    await loginUser(page, ctx, 'admin', 'AdminPassword123!');
    await page.goto('http://localhost:8080/local/testcase_exchange/index.php?course=3&question=2:119&lang=vi');
    await page.waitForLoadState('networkidle');

    const teacherViData = await page.evaluate(() => {
        const tabs = Array.from(document.querySelectorAll('.nav-tabs .nav-link')).map(el => el.textContent.trim());
        const seedAlert = document.querySelector('.alert-light.border')?.textContent.trim();
        const bankTableHeaders = Array.from(document.querySelectorAll('#tab_bank table th')).map(el => el.textContent.trim());
        const bankRows = Array.from(document.querySelectorAll('#tab_bank table tbody tr')).map(row => {
            const cols = Array.from(row.querySelectorAll('td')).map(td => td.textContent.trim());
            return cols;
        });
        const rewardCard = document.querySelectorAll('.card.h-100.border-0.bg-light')[3]?.textContent.trim();
        return { tabs, seedAlert, bankTableHeaders, bankRows, rewardCard };
    });
    console.log('VI Teacher Data:', JSON.stringify(teacherViData, null, 2));

    await page.screenshot({ path: 'e2e/recordings/teacher_page_vi.png' });

    // 4. Check Teacher Page in English
    await page.goto('http://localhost:8080/local/testcase_exchange/index.php?course=3&question=2:119&lang=en');
    await page.waitForLoadState('networkidle');

    const teacherEnData = await page.evaluate(() => {
        const tabs = Array.from(document.querySelectorAll('.nav-tabs .nav-link')).map(el => el.textContent.trim());
        const seedAlert = document.querySelector('.alert-light.border')?.textContent.trim();
        const bankTableHeaders = Array.from(document.querySelectorAll('#tab_bank table th')).map(el => el.textContent.trim());
        return { tabs, seedAlert, bankTableHeaders };
    });
    console.log('EN Teacher Data:', JSON.stringify(teacherEnData, null, 2));

    await page.screenshot({ path: 'e2e/recordings/teacher_page_en.png' });

    await browser.close();
    console.log('Bilingual check finished successfully.');
}

run().catch(err => {
    console.error(err);
    process.exit(1);
});
