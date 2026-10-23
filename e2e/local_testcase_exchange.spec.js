// @ts-check
const { test, expect } = require('@playwright/test');

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

test.describe('Nghiệp vụ Local Plugin Testcase Exchange', () => {

  test('Kịch bản 1: Giảng viên cấu hình chính sách & nạp Hạt giống thưởng (Seed Bank)', async ({ page, context }) => {
    // 1. Đăng nhập Giảng viên / Admin
    await loginUser(page, context, 'admin', 'AdminPassword123!');

    // 2. Đi đến trang Policy của Quiz 2 (Question 131)
    await page.goto('/local/testcase_exchange/policy.php?course=5&question=7:131');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('text=Quiz policy')).toBeVisible();

    // 3. Kiểm tra các trường cấu hình
    await expect(page.locator('#leaderboard_enabled')).toBeChecked();
    
    // 4. Nạp ca kiểm thử hạt giống thủ công (Manual Seed)
    const seedCode = 'print(is_palindrome("Level"))';
    const seedExpected = 'True';
    const seedPurpose = 'Hạt giống tự động hóa: Kiểm tra hoa thường 1 từ';

    await page.fill('textarea[name="seed_testcode"]', seedCode);
    await page.fill('textarea[name="seed_expected"]', seedExpected);
    await page.fill('input[name="seed_purpose"]', seedPurpose);

    await page.click('button[name="action"][value="add_manual_seed"]');
    await page.waitForLoadState('networkidle');

    // 5. Kiểm chứng seed testcase xuất hiện trong bảng hạt giống
    await expect(page.locator('table')).toContainText(seedCode);
    await page.screenshot({ path: 'recordings/01_teacher_policy_seedbank.png', fullPage: true });
  });

  test('Kịch bản 2: Sinh viên thử nghiệm vi sai trên Playground và đề xuất đóng góp', async ({ page, context }) => {
    // 1. Đăng nhập Sinh viên (student2)
    await loginUser(page, context, 'student2', 'StudentPassword123!');

    // 2. Đi vào Playground của bài tập
    await page.goto('/local/testcase_exchange/index.php?course=5&question=7:131');
    await page.waitForLoadState('networkidle');

    // 3. Dán code sinh viên vào textarea
    const studentCode = `def is_palindrome(s):
    cleaned = ''.join(c.lower() for c in s if c.isalnum())
    return cleaned == cleaned[::-1]
`;
    await page.fill('#student_code', studentCode);

    // 4. Nhập ca kiểm thử do sinh viên tự thiết kế
    const candidateInput = 'print(is_palindrome("Live on time, emit no evil"))';
    const candidateExpected = 'True';
    const candidatePurpose = 'Kiểm tra câu Palindrome dài có khoảng trắng';

    await page.fill('#test_input', candidateInput);
    await page.fill('#predicted_output', candidateExpected);
    await page.selectOption('#category', 'boundary');
    await page.fill('#purpose', candidatePurpose);

    // 5. Bấm nút "Chạy Thử Nghiệm Vi Sai" (Differential Test)
    await page.click('button[type="submit"]:has-text("Chạy Thử Nghiệm Vi Sai")');
    await page.waitForLoadState('networkidle');

    // 6. Kiểm tra kết quả vi sai: Solution thẩm định đúng, code sinh viên khớp kỳ vọng
    await expect(page.locator('text=ĐÚNG CHUẨN ĐỀ').first()).toBeVisible();
    await expect(page.locator('text=Khớp kỳ vọng').first()).toBeVisible();

    // 7. Bấm đề xuất đóng góp testcase này
    const contributeBtn = page.locator('button:has-text("Đề xuất Đóng góp")').first();
    if (await contributeBtn.isVisible()) {
      await contributeBtn.click();
      await page.waitForLoadState('networkidle');
    }

    // 8. Chụp màn hình lưu lại bằng chứng
    await page.screenshot({ path: 'recordings/02_student_differential_test_contribute.png', fullPage: true });
  });

  test('Kịch bản 3: Giảng viên kiểm duyệt, cho điểm sao ⭐, comment và kiểm tra layout nút Save/Reject', async ({ page, context }) => {
    // 1. Đăng nhập Giảng viên
    await loginUser(page, context, 'admin', 'AdminPassword123!');

    // 2. Mở trang Review câu hỏi 131
    await page.goto('/local/testcase_exchange/review.php?course=5&question=7:131');
    await page.waitForLoadState('networkidle');

    // 3. Kiểm tra tab Pending nếu có testcase chờ duyệt thì duyệt
    const pendingTab = page.locator('a:has-text("Pending")').first();
    if (await pendingTab.isVisible()) {
      await pendingTab.click();
      await page.waitForLoadState('networkidle');
    }

    const approveBtn = page.locator('button[value="approved"]').first();
    if (await approveBtn.isVisible()) {
      // Nhập sao và comment khi approve
      const rowForm = approveBtn.locator('xpath=./ancestor::form');
      const starSelect = rowForm.locator('select[name="rating"]');
      if (await starSelect.isVisible()) {
        await starSelect.selectOption('5');
      }
      const noteInput = rowForm.locator('input[name="review_note"]');
      if (await noteInput.isVisible()) {
        await noteInput.fill('Testcase xuất sắc! Đã kiểm tra đầy đủ các góc cạnh.');
      }
      await approveBtn.click();
      await page.waitForLoadState('networkidle');
    }

    // 4. Mở tab Approved để kiểm tra giao diện mới (Reject bên trái, Save bên phải, Comment dài)
    await page.goto('/local/testcase_exchange/review.php?course=5&question=7:131&status=approved');
    await page.waitForLoadState('networkidle');

    // Kiểm tra các thành phần của form hậu kiểm duyệt
    const saveBtn = page.locator('button[value="rate_only"]:has-text("Save")').first();
    await expect(saveBtn).toBeVisible();

    const rejectBtn = page.locator('button[value="rejected"]:has-text("Reject")').first();
    await expect(rejectBtn).toBeVisible();

    // Thử cập nhật lại comment và rating bằng nút Save mới
    const commentInput = page.locator('input[name="review_note"]').first();
    await commentInput.fill('Cập nhật bởi GV: Testcase rất sáng tạo, xứng đáng 5 sao!');
    await saveBtn.click();
    await page.waitForLoadState('networkidle');

    // 5. Chụp ảnh màn hình giao diện nút Save và Reject
    await page.screenshot({ path: 'recordings/03_teacher_review_save_reject_layout.png', fullPage: true });
  });

  test('Kịch bản 4: Sinh viên nhận thưởng hạt giống và thăng hạng trên Bảng vinh danh (Leaderboard)', async ({ page, context }) => {
    // 1. Đăng nhập Sinh viên
    await loginUser(page, context, 'student2', 'StudentPassword123!');

    // 2. Vào lại trang Dashboard
    await page.goto('/local/testcase_exchange/index.php?course=5&question=7:131');
    await page.waitForLoadState('networkidle');

    // 3. Mở tab Phần thưởng (Rewards)
    const rewardsTab = page.locator('a[href="#rewards"]').first();
    if (await rewardsTab.isVisible()) {
      await rewardsTab.click();
      await page.waitForTimeout(1000);

      // Nếu có nút nhận thưởng chưa mở khóa thì click nhận
      const claimBtn = page.locator('button:has-text("Nhận Thưởng")').first();
      if (await claimBtn.isVisible()) {
        await claimBtn.click();
        await page.waitForLoadState('networkidle');
      }
    }

    // 4. Mở tab Bảng xếp hạng (Leaderboard)
    const leaderboardTab = page.locator('a[href="#leaderboard"]').first();
    await leaderboardTab.click();
    await page.waitForTimeout(1000);

    // Kiểm tra tên sinh viên trên Leaderboard
    await expect(page.locator('#leaderboard')).toContainText('Trần Thị Sinh Viên 2');

    // 5. Chụp màn hình Bảng xếp hạng & Phần thưởng
    await page.screenshot({ path: 'recordings/04_student_rewards_leaderboard.png', fullPage: true });
  });

});
