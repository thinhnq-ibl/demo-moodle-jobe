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

    const elementsToCheck = [
        '.tce-heading',
        '#page-header h1',
        '.nav-tabs .nav-link.active',
        '.card-header strong',
        '#btnSubmitRunTest',
        'label[for="test_input"]',
        'table thead th',
        'pre',
        '#questionPromptBody'
    ];

    for (const sel of elementsToCheck) {
        const doc = await client.send('DOM.getDocument', { depth: -1 });
        try {
            const node = await client.send('DOM.querySelector', { nodeId: doc.root.nodeId, selector: sel });
            if (node && node.nodeId) {
                const fonts = await client.send('CSS.getPlatformFontsForNode', { nodeId: node.nodeId });
                console.log(`Selector "${sel}":`, fonts.fonts.map(f => `${f.familyName} (${f.glyphCount} glyphs)`).join(', '));
            }
        } catch (e) {
            console.log(`Selector "${sel}": not found or error`, e.message);
        }
    }

    await browser.close();
}

main().catch(console.error);
