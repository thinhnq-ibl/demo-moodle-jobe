const { chromium } = require('playwright');

async function main() {
    const executablePath = 'C:\\Users\\Thinh Nguyen\\AppData\\Local\\ms-playwright\\chromium-1243\\chrome-win64\\chrome.exe';
    const browser = await chromium.launch({
        headless: true,
        executablePath: executablePath,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    const ctx = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: true });
    const page = await ctx.newPage();

    // Login
    await page.goto('http://localhost:8080/login/index.php');
    await page.fill('#username', 'student1');
    await page.fill('#password', 'StudentPassword123!');
    await page.click('#loginbtn');
    await page.waitForLoadState('networkidle');

    await page.goto('http://localhost:8080/local/testcase_exchange/index.php?course=3&question=2:119&lang=vi');
    await page.waitForLoadState('networkidle');

    const layoutReport = await page.evaluate(() => {
        const report = [];
        const moodleHeader = document.querySelector('header.navbar') || document.querySelector('.navbar');
        if (moodleHeader) report.push({ name: 'Moodle Navbar', height: moodleHeader.offsetHeight });

        const breadcrumbs = document.querySelector('.breadcrumb');
        if (breadcrumbs) report.push({ name: 'Breadcrumbs', height: breadcrumbs.offsetHeight });

        const pageHeader = document.querySelector('#page-header');
        if (pageHeader) report.push({ name: 'Page Header', height: pageHeader.offsetHeight });

        const tceHeader = document.querySelector('.tce-header-bar');
        if (tceHeader) report.push({ name: 'TCE Header Bar', height: tceHeader.offsetHeight });

        const unclaimedAlert = document.querySelector('.alert-info');
        if (unclaimedAlert) report.push({ name: 'Unclaimed Rewards Alert', height: unclaimedAlert.offsetHeight });

        const tabs = document.querySelector('.nav-tabs');
        if (tabs) report.push({ name: 'Nav Tabs', height: tabs.offsetHeight });

        const contextBar = document.querySelector('.tce-context-bar');
        if (contextBar) report.push({ name: 'Question Selector Bar', height: contextBar.offsetHeight });

        const promptCard = document.querySelector('#questionPromptBody')?.closest('.card');
        if (promptCard) report.push({ name: 'Question Prompt Card', height: promptCard.offsetHeight, isCollapsed: document.getElementById('questionPromptBody').classList.contains('show') });

        const edWrapper = document.getElementById('tceEditorWrapper');
        if (edWrapper) report.push({ name: 'Editor Wrapper Top', top: edWrapper.getBoundingClientRect().top });

        return report;
    });

    console.log(JSON.stringify(layoutReport, null, 2));
    await browser.close();
}

main().catch(console.error);
