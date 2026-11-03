const { chromium } = require('playwright');

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
    const executablePath = 'C:\\Users\\Thinh Nguyen\\AppData\\Local\\ms-playwright\\chromium-1243\\chrome-win64\\chrome.exe';
    const browser = await chromium.launch({
        headless: true,
        executablePath: executablePath,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const page = await ctx.newPage();

    await loginUser(page, ctx, 'student1', 'StudentPassword123!');
    console.log('After login, URL:', page.url());

    await page.goto('http://localhost:8080/local/testcase_exchange/index.php?course=3&question=2:119');
    await page.waitForLoadState('networkidle');
    console.log('After navigating to plugin, URL:', page.url());

    const fonts = await page.evaluate(() => {
        function getF(sel) {
            const el = document.querySelector(sel);
            return el ? window.getComputedStyle(el).fontFamily : 'NOT_FOUND';
        }
        return {
            body: getF('body'),
            root: getF('.local-testcase-exchange-root'),
            h3: getF('.local-testcase-exchange-root h3'),
            navTabs: getF('.nav-tabs'),
            btn: getF('#btnSubmitRunTest'),
            pre: getF('pre'),
            textarea: getF('#student_code'),
            table: getF('.table'),
            modalH5: getF('.modal-title')
        };
    });
    console.log('Detailed computed fonts on plugin page:', fonts);
    await browser.close();
}

main().catch(err => {
    console.error(err);
    process.exit(1);
});
