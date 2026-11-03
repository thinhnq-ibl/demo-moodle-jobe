with open('e2e/capture_after_state.js', 'r', encoding='utf-8') as f:
    c = f.read()

target = "console.log('Bấm nút Lưu quyết định thẩm định...');"
replacement = """console.log('Bấm nút Lưu quyết định thẩm định...');
            const formAction = await modal.$eval('form', f => ({ action: f.action, method: f.method, actVal: f.querySelector('input[name="action"]')?.value, cid: f.querySelector('input[name="contribution_id"]')?.value, dec: f.querySelector('select[name="decision"]')?.value, sesskey: f.querySelector('input[name="sesskey"]')?.value }));
            console.log('[DEBUG] Modal Form submission payload:', JSON.stringify(formAction));"""

c = c.replace(target, replacement)

with open('e2e/capture_after_state.js', 'w', encoding='utf-8') as f:
    f.write(c)

print('Added debug form inspection to capture_after_state.js cleanly!')
