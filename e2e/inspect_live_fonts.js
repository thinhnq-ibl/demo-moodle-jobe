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

    // Login student1
    await page.goto('http://localhost:8080/login/index.php');
    await page.fill('#username', 'student1');
    await page.fill('#password', 'StudentPassword123!');
    await page.click('#loginbtn');
    await page.waitForLoadState('networkidle');

    await page.goto('http://localhost:8080/local/testcase_exchange/index.php?course=3&question=2:119&lang=vi');
    await page.waitForLoadState('networkidle');

    const elementsFont = await page.evaluate(() => {
        const results = [];
        const targets = [
            'body',
            '#page-header',
            'h1', 'h2', 'h3', 'h4', 'h5',
            '.nav-tabs',
            '.nav-link',
            '.btn',
            'table',
            'th', 'td',
            'pre', 'code',
            'input', 'select', 'textarea',
            '.card-header',
            '.badge',
            '.breadcrumb'
        ];
        for (const t of targets) {
            const els = document.querySelectorAll(t);
            if (els.length > 0) {
                const el = els[0];
                const cs = window.getComputedStyle(el);
                results.push({
                    selector: t,
                    fontFamily: cs.fontFamily,
                    fontSize: cs.fontSize,
                    fontWeight: cs.fontWeight
                });
            }
        }
        return results;
    });

    console.log('Elements Font Inspection:');
    console.log(JSON.stringify(elementsFont, null, 2));

    await browser.close();
}

main().catch(console.error);
