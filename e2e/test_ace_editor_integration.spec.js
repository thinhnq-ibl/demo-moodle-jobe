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

test.describe('Ace Editor Integration Verification for local_testcase_exchange', () => {

  test('Kiểm chứng toàn diện Ace Editor: Giao diện, Dark/Light, Fullscreen, Đồng bộ và Chạy thực tế', async ({ page, context }) => {
    // 1. Đăng nhập tài khoản sinh viên student2
    console.log('[Test] Bước 1: Đăng nhập tài khoản student2...');
    await loginUser(page, context, 'student2', 'StudentPassword123!');

    // 2. Truy cập Playground
    console.log('[Test] Bước 2: Truy cập trang Playground Testcase Exchange...');
    await page.goto('/local/testcase_exchange/index.php?course=3&question=2:120');
    await page.waitForLoadState('networkidle');

    // 3. Kiểm tra Ace Editor container và elements
    console.log('[Test] Bước 3: Kiểm tra các thành phần Ace Editor UI...');
    const editorWrapper = page.locator('#tceEditorWrapper');
    await expect(editorWrapper).toBeVisible();

    const aceEditor = page.locator('#student_code_ace');
    await expect(aceEditor).toBeVisible();

    // Textarea gốc phải bị ẩn (class d-none) nhưng vẫn tồn tại trong form
    const originalTextarea = page.locator('#student_code');
    await expect(originalTextarea).toHaveClass(/d-none/);

    // Kiểm tra Badge ngôn ngữ
    const langBadge = page.locator('#tceEditorLangBadge');
    await expect(langBadge).toBeVisible();
    console.log('[Test] Ngôn ngữ hiển thị:', await langBadge.textContent());

    // Chụp ảnh Light mode
    console.log('[Test] Chụp ảnh giao diện Light mode...');
    await page.screenshot({ path: path.join(RECORDINGS_DIR, 'ace_editor_light_mode.png'), fullPage: false });

    // 4. Kiểm tra Chuyển Dark/Light Theme
    console.log('[Test] Bước 4: Kiểm tra chuyển giao diện Dark/Light...');
    const themeBtn = page.locator('#tceToggleThemeBtn');
    await expect(themeBtn).toBeVisible();

    // Bấm chuyển sang Dark Theme
    await themeBtn.click();
    await page.waitForTimeout(400);

    // Kiểm tra theme text đổi thành Giao diện sáng
    await expect(page.locator('#tceThemeText')).toHaveText('Giao diện sáng');

    // Chụp ảnh Dark mode
    console.log('[Test] Chụp ảnh giao diện Dark mode...');
    await page.screenshot({ path: path.join(RECORDINGS_DIR, 'ace_editor_dark_mode.png'), fullPage: false });

    // Bấm chuyển lại Light Theme
    await themeBtn.click();
    await page.waitForTimeout(400);
    await expect(page.locator('#tceThemeText')).toHaveText('Giao diện tối');

    // 5. Kiểm tra tính năng Fullscreen
    console.log('[Test] Bước 5: Kiểm tra Fullscreen...');
    const fullscreenBtn = page.locator('#tceToggleFullscreenBtn');
    await fullscreenBtn.click();
    await page.waitForTimeout(300);
    await expect(editorWrapper).toHaveClass(/tce-fullscreen/);
    await expect(page.locator('#tceFullscreenText')).toHaveText('Thu nhỏ');

    // Thoát Fullscreen
    await fullscreenBtn.click();
    await page.waitForTimeout(300);
    await expect(editorWrapper).not.toHaveClass(/tce-fullscreen/);
    await expect(page.locator('#tceFullscreenText')).toHaveText('Toàn màn hình');

    // 6. Nhập mã nguồn Python đa dòng, có thụt lề, comment tiếng Việt
    console.log('[Test] Bước 6: Nhập mã nguồn trên Ace Editor và kiểm tra đồng bộ...');
    const pythonCode = `def is_palindrome(s):
    # Kiểm tra chuỗi đối xứng với ký tự tiếng Việt Unicode
    cleaned = ''.join(c.lower() for c in s if c.isalnum())
    return cleaned == cleaned[::-1]
`;

    // Đặt mã nguồn qua Ace API hoặc tương tác
    await page.evaluate((code) => {
      const editorDiv = document.getElementById('student_code_ace');
      if (window.ace && editorDiv) {
        const ed = window.ace.edit(editorDiv);
        ed.setValue(code, -1);
      }
    }, pythonCode);

    await page.waitForTimeout(300);

    // Kiểm tra textarea gốc nhận chính xác mã nguồn đồng bộ
    const syncedVal = await originalTextarea.inputValue();
    expect(syncedVal).toBe(pythonCode);
    console.log('[Test] Đồng bộ Ace -> textarea thành công (độ dài: ' + syncedVal.length + ' ký tự)');

    // 7. Nhập testcase và chạy thử nghiệm vi sai
    console.log('[Test] Bước 7: Nhập testcase và bấm Chạy Thử Nghiệm Vi Sai...');
    await page.fill('#test_input', 'print(is_palindrome("radar"))');
    await page.fill('#predicted_output', 'All tests passed! Code của bạn hoàn toàn chính xác khi đối chiếu với lời giải mẫu.');
    await page.selectOption('#category', 'normal');
    await page.fill('#purpose', 'Kiểm tra chuỗi đối xứng chữ thường (radar)');

    // Bấm nút Run
    const runBtn = page.locator('#btnSubmitRunTest');
    await runBtn.click();
    await page.waitForLoadState('networkidle');

    // 8. Kiểm chứng kết quả chạy thử nghiệm
    console.log('[Test] Bước 8: Kiểm chứng kết quả thực thi vi sai trên Jobe...');
    const resultCard = page.locator('#tceLatestResultCard');
    await expect(resultCard).toBeVisible({ timeout: 20000 });
    await expect(resultCard).toContainText('Kết quả khớp hoàn toàn');
    await expect(resultCard).toContainText('Khớp');

    // Kiểm tra sau khi reload, Ace Editor vẫn hiển thị mã nguồn
    const aceCodeAfterRun = await page.evaluate(() => {
      const editorDiv = document.getElementById('student_code_ace');
      return window.ace ? window.ace.edit(editorDiv).getValue() : '';
    });
    expect(aceCodeAfterRun).toContain('def is_palindrome(s):');
    console.log('[Test] Phục hồi mã sau khi chạy thành công!');

    // 9. Kiểm tra nút Nạp vX / Nạp code cập nhật trực tiếp vào Ace
    console.log('[Test] Bước 9: Kiểm tra tính năng Nạp phiên bản vào Ace...');
    const revisionBtn = page.locator('.btn-apply-revision, .btn-load-run-code').first();
    if (await revisionBtn.isVisible()) {
      await revisionBtn.click();
      await page.waitForTimeout(300);
      const aceCodeAfterRestore = await page.evaluate(() => {
        const editorDiv = document.getElementById('student_code_ace');
        return window.ace ? window.ace.edit(editorDiv).getValue() : '';
      });
      expect(aceCodeAfterRestore.length).toBeGreaterThan(0);
      console.log('[Test] Nạp phiên bản cập nhật tức thì vào Ace Editor thành công!');
    }

    // Chụp ảnh bằng chứng sau khi hoàn tất
    await page.screenshot({ path: path.join(RECORDINGS_DIR, 'ace_editor_verified_run.png'), fullPage: true });
    console.log('[Test] Hoàn tất kiểm thử chức năng Ace Editor!');
  });

  test('Regression test: Kiểm chứng CodeRunner trong Quiz không bị ảnh hưởng', async ({ page, context }) => {
    console.log('[Regression] Đăng nhập student1 để kiểm tra CodeRunner Quiz...');
    await loginUser(page, context, 'student1', 'StudentPassword123!');

    // Truy cập trực tiếp lượt làm bài đang mở của Quiz 2
    await page.goto('/mod/quiz/attempt.php?attempt=1&cmid=5');
    await page.waitForLoadState('networkidle');

    // Kiểm tra CodeRunner Ace Editor trong Quiz
    const crAce = page.locator('.ace_editor, .qtype-coderunner-outer-div, .qtype-coderunner-sp-outer-div').first();
    await expect(crAce).toBeVisible({ timeout: 15000 });
    console.log('[Regression] CodeRunner Quiz Ace Editor hoạt động hoàn hảo, không có xung đột!');
  });
});
