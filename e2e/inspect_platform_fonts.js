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

    await page.goto('http://localhost:8080/login/index.php');
    await page.fill('#username', 'student1');
    await page.fill('#password', 'StudentPassword123!');
    await page.click('#loginbtn');
    await page.waitForLoadState('networkidle');

    await page.goto('http://localhost:8080/local/testcase_exchange/index.php?course=3&question=2:119&lang=vi');
    await page.waitForLoadState('networkidle');

    const client = await page.context().newCDPSession(page);
    await client.send('DOM.enable');
    await client.send('CSS.enable');

    const selectors = ['h3', 'table', 'th', '.btn-primary', 'pre', 'body', '.nav-link.active'];
    const results = [];

    for (const sel of selectors) {
        const handle = await page.$(sel);
        if (handle) {
            const doc = await client.send('DOM.getDocument', { depth: -1 });
            const node = await client.send('DOM.querySelector', { nodeId: doc.root.nodeId, selector: sel });
            if (node && node.nodeId) {
                const fonts = await client.send('CSS.getPlatformFontsForNode', { nodeId: node.nodeId });
                results.push({ selector: sel, fonts: fonts.fonts });
            }
        }
    }

    console.log('PLATFORM FONTS USED BY CHROMIUM (DirectWrite on disk):');
    console.log(JSON.stringify(results, null, 2));

    await browser.close();
}

main().catch(console.error);
