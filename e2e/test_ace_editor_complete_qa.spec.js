// @ts-check
const { test, expect } = require('@playwright/test');
const path = require('path');

const RECORDINGS_DIR = path.resolve(__dirname, 'recordings');

async function loginUser(page, context, username, password) {
  await context.clearCookies();
  await page.goto('/login/index.php');
  await page.waitForLoadState('networkidle');
  await page.fill('#username', username);
  await page.fill('#password', password);
  await Promise.all([
    page.waitForNavigation(),
    page.evaluate(() => {
      const form = /** @type {HTMLFormElement} */ (document.querySelector('form#login'));
      if (form) form.submit();
    })
  ]);
  await page.waitForLoadState('networkidle');
}

test.describe('P0, P1, P2 QA Verification for Ace Editor Integration', () => {

  test('1. P0 & P1 & P2: Python Question 120 (is_palindrome) - Run Test, Pure Stdout, Dark/Light, Focus & Layout', async ({ page, context }) => {
    // 1. Đăng nhập student2
    await loginUser(page, context, 'student2', 'StudentPassword123!');

    // 2. Vào trang Playground cho câu hỏi 120 (Course 3, Quiz 2, Q120)
    await page.goto('/local/testcase_exchange/index.php?course=3&question=2:120');
    await page.waitForLoadState('networkidle');

    const editorWrapper = page.locator('#tceEditorWrapper');
    await expect(editorWrapper).toBeVisible();

    const aceEditor = page.locator('#student_code_ace');
    await expect(aceEditor).toBeVisible();

    const originalTextarea = page.locator('#student_code');
    await expect(originalTextarea).toHaveClass(/d-none/);

    // Kiểm tra Badge ngôn ngữ
    const langBadge = page.locator('#tceEditorLangBadge');
    await expect(langBadge).toContainText('Python');

    // Chụp ảnh Light mode
    await page.screenshot({ path: path.join(RECORDINGS_DIR, 'ace_editor_light_mode.png'), fullPage: false });

    // P1: Kiểm tra Focus & Undo Stack sau khi chuyển Dark/Light
    const themeBtn = page.locator('#tceToggleThemeBtn');
    
    // Gõ một chuỗi vào Ace để tạo undo history
    await page.evaluate(() => {
      const ed = window.ace.edit('student_code_ace');
      ed.setValue('# Initial line for undo check\n', -1);
      ed.insert('# Added line 2\n');
    });

    // Bấm Dark mode
    await themeBtn.click();
    await page.waitForTimeout(300);
    await expect(page.locator('#tceThemeText')).toHaveText(/(Giao diện sáng|Light theme)/);

    // Chụp ảnh Dark mode
    await page.screenshot({ path: path.join(RECORDINGS_DIR, 'ace_editor_dark_mode.png'), fullPage: false });

    // Kiểm tra Undo hoạt động sau theme switch (không bị reset undo manager)
    const undoWorks = await page.evaluate(() => {
      const ed = window.ace.edit('student_code_ace');
      const valBefore = ed.getValue();
      ed.undo();
      const valAfter = ed.getValue();
      return (valBefore !== valAfter);
    });
    expect(undoWorks).toBe(true);

    // Chuyển lại Light mode
    await themeBtn.click();
    await page.waitForTimeout(300);
    await expect(page.locator('#tceThemeText')).toHaveText(/(Giao diện tối|Dark theme)/);

    // P1: Kiểm tra Fullscreen
    const fullscreenBtn = page.locator('#tceToggleFullscreenBtn');
    await fullscreenBtn.click();
    await page.waitForTimeout(300);
    await expect(editorWrapper).toHaveClass(/tce-fullscreen/);

    await fullscreenBtn.click();
    await page.waitForTimeout(300);
    await expect(editorWrapper).not.toHaveClass(/tce-fullscreen/);

    // P0: Kiểm tra Run Test trả về stdout thuần túy True (thay vì chuỗi All tests passed!)
    const pythonCode = `def is_palindrome(s):
    cleaned = ''.join(c.lower() for c in s if c.isalnum())
    return cleaned == cleaned[::-1]
`;
    await page.evaluate((code) => {
      const ed = window.ace.edit('student_code_ace');
      ed.setValue(code, -1);
    }, pythonCode);

    await page.waitForTimeout(200);
    const syncedVal = await originalTextarea.inputValue();
    expect(syncedVal).toBe(pythonCode);

    // Nhập testcase print(is_palindrome("level")) -> Expected stdout: True
    await page.fill('#test_input', 'print(is_palindrome("level"))');
    await page.fill('#predicted_output', 'True');
    await page.selectOption('#category', 'normal');
    await page.fill('#purpose', 'Kiểm tra chuỗi đối xứng level cho kết quả True');

    const runBtn = page.locator('#btnSubmitRunTest');
    await Promise.all([
      page.waitForNavigation(),
      runBtn.click()
    ]);
    await page.waitForLoadState('networkidle');

    // Xác minh kết quả hiển thị khớp và stdout là True
    const resultCard = page.locator('#tceLatestResultCard');
    await expect(resultCard).toBeVisible({ timeout: 20000 });
    await expect(resultCard).toContainText(/Kết quả khớp hoàn toàn|Full match/);
    
    // Kiểm tra nội dung chi tiết đầu ra là True (không phải thông báo chấm bài)
    const cardText = await resultCard.innerText();
    expect(cardText).toContain('True');
    expect(cardText).not.toContain('All tests passed!');

    // Chụp ảnh Verified Run
    await page.screenshot({ path: path.join(RECORDINGS_DIR, 'ace_editor_verified_run.png'), fullPage: false });

    // P2: Kiểm tra tỷ lệ 60/40 giữa Code Editor và Testcase Column trên desktop
    const codeColBox = await page.locator('.tce-code-column').boundingBox();
    const testcaseColBox = await page.locator('.tce-testcase-column').boundingBox();
    expect(codeColBox).not.toBeNull();
    expect(testcaseColBox).not.toBeNull();
    if (codeColBox && testcaseColBox) {
      const totalWidth = codeColBox.width + testcaseColBox.width;
      const codeRatio = codeColBox.width / totalWidth;
      console.log(`[QA] Desktop column ratio: Code = ${(codeRatio * 100).toFixed(1)}%, Testcase = ${((1 - codeRatio) * 100).toFixed(1)}%`);
      expect(codeRatio).toBeGreaterThanOrEqual(0.55);
      expect(codeRatio).toBeLessThanOrEqual(0.65);
    }

    // Chụp ảnh tổng thể desktop layout
    await page.screenshot({ path: path.join(RECORDINGS_DIR, 'ace_editor_desktop_layout.png'), fullPage: false });
  });

  test('2. P0: Python Question 120 - Phát hiện vi sai khi code sinh viên cố tình sai', async ({ page, context }) => {
    await loginUser(page, context, 'student2', 'StudentPassword123!');
    await page.goto('/local/testcase_exchange/index.php?course=3&question=2:120');
    await page.waitForLoadState('networkidle');

    // Đưa vào code cố tình sai: luôn trả về False
    const buggyCode = `def is_palindrome(s):
    return False
`;
    await page.evaluate((code) => {
      const ed = window.ace.edit('student_code_ace');
      ed.setValue(code, -1);
    }, buggyCode);

    // Dùng input mới "rotator" để tránh trùng lặp cache lượt chạy trước
    await page.fill('#test_input', 'print(is_palindrome("rotator"))');
    await page.fill('#predicted_output', 'True');
    await page.selectOption('#category', 'normal');
    await page.fill('#purpose', 'Kiểm tra phát hiện sai khác khi code trả về False');

    const runBtn = page.locator('#btnSubmitRunTest');
    await Promise.all([
      page.waitForNavigation(),
      runBtn.click()
    ]);
    await page.waitForLoadState('networkidle');

    const resultCard = page.locator('#tceLatestResultCard');
    await expect(resultCard).toBeVisible({ timeout: 20000 });
    
    // Giao diện phải nhận biết code chưa vượt qua và không cho phép đóng góp
    const cardText = await resultCard.innerText();
    expect(cardText).toMatch(/(Hãy sửa mã nguồn và chạy lại|Chưa đủ điều kiện đóng góp)/);
    expect(cardText).toContain('Không khớp');
    expect(cardText).toContain('False');

    // Nút gửi đóng góp không được xuất hiện
    const contribSubmitBtn = resultCard.locator('button:has-text("Gửi đóng góp"), button:has-text("Submit contribution")');
    await expect(contribSubmitBtn).toHaveCount(0);
    console.log('[QA] Phát hiện vi sai mã sai thành công! Không cho phép đóng góp khi student output sai.');
  });

  test('3. P0 & P1: Java Question 786 (isPrime) - Syntax Highlighting & Java Execution', async ({ page, context }) => {
    await loginUser(page, context, 'student2', 'StudentPassword123!');
    await page.goto('/local/testcase_exchange/index.php?course=3&question=2:786');
    await page.waitForLoadState('networkidle');

    const editorWrapper = page.locator('#tceEditorWrapper');
    await expect(editorWrapper).toBeVisible();

    const langBadge = page.locator('#tceEditorLangBadge');
    await expect(langBadge).toContainText('Java');

    const javaCode = `public static boolean isPrime(int n) {
    if (n <= 1) return false;
    for (int i = 2; i * i <= n; i++) {
        if (n % i == 0) return false;
    }
    return true;
}`;

    await page.evaluate((code) => {
      const ed = window.ace.edit('student_code_ace');
      ed.setValue(code, -1);
    }, javaCode);

    // Dùng input mới để không đụng cache cũ
    await page.fill('#test_input', 'System.out.println(isPrime(17));');
    await page.fill('#predicted_output', 'true');
    await page.selectOption('#category', 'normal');
    await page.fill('#purpose', 'Kiểm tra số nguyên tố 17 bằng Java');

    const runBtn = page.locator('#btnSubmitRunTest');
    await Promise.all([
      page.waitForNavigation(),
      runBtn.click()
    ]);
    await page.waitForLoadState('networkidle');

    const resultCard = page.locator('#tceLatestResultCard');
    await expect(resultCard).toBeVisible({ timeout: 25000 });
    await expect(resultCard).toContainText(/Kết quả khớp hoàn toàn|Full match/);

    const cardText = await resultCard.innerText();
    expect(cardText).toContain('true');
    console.log('[QA] Kiểm thử Java execution thành công với stdout true!');
  });

});
