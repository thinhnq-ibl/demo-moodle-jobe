with open('e2e/capture_after_state.js', 'r', encoding='utf-8') as f:
    c = f.read()

target = "console.log(`[PLUGIN OVERFLOW CHECK] ${label}: scrollWidth=${pluginOverflow.scrollW}, clientWidth=${pluginOverflow.clientW}, diff=${pluginOverflow.diff}`);"

replacement = """console.log(`[PLUGIN OVERFLOW CHECK] ${label}: scrollWidth=${pluginOverflow.scrollW}, clientWidth=${pluginOverflow.clientW}, diff=${pluginOverflow.diff}`);
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
    }"""

c = c.replace(target, replacement)

with open('e2e/capture_after_state.js', 'w', encoding='utf-8') as f:
    f.write(c)

print('Updated capture_after_state.js with offPlugin check!')
