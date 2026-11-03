const { chromium } = require('playwright');

async function main() {
    const executablePath = 'C:\\Users\\Thinh Nguyen\\AppData\\Local\\ms-playwright\\chromium-1243\\chrome-win64\\chrome.exe';
    const browser = await chromium.launch({
        headless: true,
        executablePath: executablePath,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await ctx.newPage();

    // Log in
    await page.goto('http://localhost:8080/login/index.php');
    await page.fill('#username', 'student1');
    await page.fill('#password', 'StudentPassword123!');
    await Promise.all([
        page.waitForNavigation(),
        page.click('#loginbtn')
    ]);

    // Go to Moodle home page / regular Moodle page
    await page.goto('http://localhost:8080/my/');
    await page.waitForLoadState('networkidle');
    const moodleHomeFonts = await page.evaluate(() => {
        const body = getComputedStyle(document.body).fontFamily;
        const h1 = document.querySelector('h1') ? getComputedStyle(document.querySelector('h1')).fontFamily : '';
        const nav = document.querySelector('.navbar') ? getComputedStyle(document.querySelector('.navbar')).fontFamily : '';
        return { body, h1, nav };
    });
    console.log('Moodle Normal Page (/my/) computed fonts:', moodleHomeFonts);

    // Go to local_testcase_exchange
    await page.goto('http://localhost:8080/local/testcase_exchange/index.php?course=3&question=2:119');
    await page.waitForLoadState('networkidle');

    const pluginFonts = await page.evaluate(() => {
        const body = getComputedStyle(document.body).fontFamily;
        const root = document.querySelector('.local-testcase-exchange-root') ? getComputedStyle(document.querySelector('.local-testcase-exchange-root')).fontFamily : '';
        const h3 = document.querySelector('.local-testcase-exchange-root h3') ? getComputedStyle(document.querySelector('.local-testcase-exchange-root h3')).fontFamily : '';
        const label = document.querySelector('label') ? getComputedStyle(document.querySelector('label')).fontFamily : '';
        const btn = document.querySelector('button') ? getComputedStyle(document.querySelector('button')).fontFamily : '';
        const table = document.querySelector('table') ? getComputedStyle(document.querySelector('table')).fontFamily : '';
        const pre = document.querySelector('pre') ? getComputedStyle(document.querySelector('pre')).fontFamily : '';
        const textarea = document.querySelector('textarea') ? getComputedStyle(document.querySelector('textarea')).fontFamily : '';
        return { body, root, h3, label, btn, table, pre, textarea };
    });
    console.log('Plugin Page computed fonts:', pluginFonts);

    await browser.close();
}

main().catch(err => {
    console.error(err);
    process.exit(1);
});
