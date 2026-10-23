// @ts-check
const { test, expect } = require('@playwright/test');

test.describe('Kịch bản Hướng dẫn Sử dụng Hệ thống Testcase Exchange (End-to-End Walkthrough)', () => {
  test.setTimeout(240000); // 4 phút để diễn hoạt chậm rãi, trực quan

  test('Video Hướng dẫn Trọn vẹn: Sinh viên làm bài -> Đóng góp -> Giáo viên duyệt -> Nhận thưởng & Leaderboard', async ({ page, context }) => {

    // Helper đăng nhập chậm rãi, rõ ràng
    const slowLogin = async (username, password) => {
      await context.clearCookies();
      await page.goto('/login/index.php');
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(1200);

      // Điền username từ từ
      await page.fill('#username', username);
      await page.waitForTimeout(700);

      // Điền password từ từ
      await page.fill('#password', password);
      await page.waitForTimeout(1000);

      await Promise.all([
        page.waitForNavigation(),
        page.evaluate(() => {
          const form = /** @type {HTMLFormElement} */ (document.querySelector('form#login'));
          if (form) form.submit();
        })
      ]);
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(2000);
    };

    // =========================================================================
    // PHẦN 1: HÀNH TRÌNH SINH VIÊN (Từ Dashboard -> Môn học -> Quiz -> Playground -> Đóng góp)
    // =========================================================================

    // Bước 1: Sinh viên đăng nhập vào hệ thống
    console.log('[Bước 1] Sinh viên student2 đăng nhập...');
    await slowLogin('student2', 'StudentPassword123!');
    await page.waitForTimeout(2500);

    // Bước 2: Từ Dashboard, vào môn học "python 2"
    console.log('[Bước 2] Điều hướng vào môn học python 2...');
    await page.goto('/course/view.php?id=5');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2500);

    // Cuộn nhẹ để người xem quan sát các bài tập trong khóa học
    await page.evaluate(() => window.scrollBy({ top: 350, behavior: 'smooth' }));
    await page.waitForTimeout(2500);

    // Bước 3: Click vào bài Quiz "Bài thực hành 2: Xử lý chuỗi và Đối xứng"
    console.log('[Bước 3] Mở bài thi Quiz 2...');
    const quizLink = page.locator('a:has-text("Bài thực hành 2: Xử lý chuỗi và Đối xứng")').first();
    await quizLink.click();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(3000);

    // Bước 4: Trong trang Quiz, click vào nút "Testcase Bank & Exchange"
    console.log('[Bước 4] Bấm nút Testcase Bank & Exchange để vào sân chơi...');
    const exchangeBtn = page.locator('a:has-text("Testcase Bank & Exchange")').first();
    await exchangeBtn.click();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(3000);

    // Bước 5: Chọn câu hỏi cần thực hành (Question 131: Palindrome)
    console.log('[Bước 5] Chọn câu hỏi Palindrome...');
    const questionSelect = page.locator('select#question');
    if (await questionSelect.isVisible()) {
      await questionSelect.selectOption('7:131');
      await page.waitForTimeout(2000);
    }

    // Cuộn xuống khu vực code editor
    await page.evaluate(() => window.scrollBy({ top: 350, behavior: 'smooth' }));
    await page.waitForTimeout(2000);

    // Bước 6: Dán mã nguồn Python của sinh viên vào khung soạn thảo
    console.log('[Bước 6] Dán mã nguồn Python sinh viên tự viết...');
    const studentCode = `def is_palindrome(s):
    # Lọc bỏ ký tự đặc biệt, chuyển về chữ thường
    cleaned = ''.join(c.lower() for c in s if c.isalnum())
    # So sánh với chuỗi đảo ngược
    return cleaned == cleaned[::-1]
`;
    await page.fill('#student_code', studentCode);
    await page.waitForTimeout(2500);

    // Bước 7: Tự thiết kế ca kiểm thử (Testcase Definition)
    console.log('[Bước 7] Tự thiết kế ca kiểm thử nâng cao...');
    const testCode = 'print(is_palindrome("A Santa at NASA"))';
    const expectedOutput = 'True';
    const purposeText = 'Kiểm tra câu đối xứng có khoảng trắng và viết hoa';

    await page.fill('#test_input', testCode);
    await page.waitForTimeout(1200);

    await page.fill('#predicted_output', expectedOutput);
    await page.waitForTimeout(1200);

    await page.selectOption('#category', 'boundary');
    await page.waitForTimeout(1200);

    await page.fill('#purpose', purposeText);
    await page.waitForTimeout(2500);

    // Bước 8: Bấm nút "Chạy Thử Nghiệm Vi Sai" (Differential Test)
    console.log('[Bước 8] Bấm chạy thử nghiệm vi sai trên Jobe Sandbox...');
    const runBtn = page.locator('button[type="submit"]:has-text("Chạy Thử Nghiệm Vi Sai")');
    await runBtn.click();
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(3000);

    // Cuộn xuống xem kết quả kiểm tra vi sai
    await page.evaluate(() => window.scrollBy({ top: 500, behavior: 'smooth' }));
    await page.waitForTimeout(3500);

    // Bước 9: Đề xuất đóng góp testcase này
    console.log('[Bước 9] Bấm đề xuất đóng góp testcase...');
    const contributeBtn = page.locator('button:has-text("Đề xuất Đóng góp")').first();
    if (await contributeBtn.isVisible()) {
      await contributeBtn.click();
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(3000);
    }

    // Cuộn nhẹ xem lịch sử đóng góp (trạng thái Pending)
    await page.evaluate(() => window.scrollBy({ top: 400, behavior: 'smooth' }));
    await page.waitForTimeout(3500);


    // =========================================================================
    // PHẦN 2: HÀNH TRÌNH GIẢNG VIÊN (Vào duyệt, chấm 5 sao ⭐, comment, dùng nút Save/Reject)
    // =========================================================================

    console.log('\n[Bước 10] Chuyển sang Giảng viên để kiểm duyệt...');
    await slowLogin('admin', 'AdminPassword123!');
    await page.waitForTimeout(2500);

    // Giảng viên vào môn học python 2
    console.log('[Bước 11] Giảng viên vào khóa học python 2...');
    await page.goto('/course/view.php?id=5');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2500);

    // Mở trang Duyệt đóng góp testcase
    console.log('[Bước 12] Mở trang Duyệt đóng góp testcase (review.php)...');
    await page.goto('/local/testcase_exchange/review.php?course=5&question=7:131&status=pending');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(3000);

    // Giảng viên tìm bài chờ duyệt, chấm 5 sao, nhập comment và bấm Approve
    const approveBtn = page.locator('button[value="approved"]').first();
    if (await approveBtn.isVisible()) {
      console.log('[Bước 13] Giảng viên chấm 5 sao và duyệt testcase...');
      const rowForm = approveBtn.locator('xpath=./ancestor::form');
      const starSelect = rowForm.locator('select[name="rating"]');
      if (await starSelect.isVisible()) {
        await starSelect.selectOption('5');
        await page.waitForTimeout(1200);
      }
      const noteInput = rowForm.locator('input[name="review_note"]');
      if (await noteInput.isVisible()) {
        await noteInput.fill('Xuất sắc! Testcase câu ngắn gọn, bao quát tốt hoa thường.');
        await page.waitForTimeout(2000);
      }
      await approveBtn.click();
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(3000);
    }

    // Giảng viên chuyển sang tab Approved để xem giao diện nút mới (Reject bên trái, Save bên phải)
    console.log('[Bước 14] Xem giao diện hậu duyệt (Reject bên trái, Save bên phải, Comment rộng)...');
    await page.goto('/local/testcase_exchange/review.php?course=5&question=7:131&status=approved');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(3000);

    // Giảng viên chỉnh sửa thêm vào ô comment rộng và bấm nút Save mới
    const commentInput = page.locator('input[name="review_note"]').first();
    if (await commentInput.isVisible()) {
      await commentInput.fill('Đã duyệt: Testcase mẫu mực, thưởng trọn 5 sao!');
      await page.waitForTimeout(2000);

      const saveBtn = page.locator('button[value="rate_only"]:has-text("Save")').first();
      await saveBtn.click();
      await page.waitForLoadState('networkidle');
      await page.waitForTimeout(3500);
    }


    // =========================================================================
    // PHẦN 3: SINH VIÊN NHẬN THƯỞNG & XEM BẢNG VINH DANH (LEADERBOARD)
    // =========================================================================

    console.log('\n[Bước 15] Đăng nhập lại Sinh viên để nhận quả ngọt...');
    await slowLogin('student2', 'StudentPassword123!');
    await page.waitForTimeout(2500);

    // Vào lại Testcase Exchange của môn học
    console.log('[Bước 16] Sinh viên mở lại Ngân hàng Trao đổi Testcase...');
    await page.goto('/local/testcase_exchange/index.php?course=5&question=7:131');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2500);

    // Cuộn xuống xem danh sách các tab
    await page.evaluate(() => window.scrollBy({ top: 400, behavior: 'smooth' }));
    await page.waitForTimeout(2500);

    // Mở tab Phần thưởng (Rewards)
    console.log('[Bước 17] Sinh viên mở Tab Phần thưởng (Rewards)...');
    const rewardsTab = page.locator('a[href="#rewards"]').first();
    if (await rewardsTab.isVisible()) {
      await rewardsTab.click();
      await page.waitForTimeout(3000);

      // Tìm nút nhận thưởng (Claim Reward)
      const claimBtn = page.locator('form:has(input[name="action"][value="claim_reward"]) button').first();
      if (await claimBtn.isVisible()) {
        console.log('[Bước 18] Bấm mở khóa testcase hạt giống bí mật...');
        await claimBtn.click();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(3500);
      }
    }

    // Mở tab Bảng xếp hạng (Leaderboard)
    console.log('[Bước 19] Mở Tab Bảng Xếp Hạng vinh danh (Leaderboard)...');
    const leaderboardTab = page.locator('a[href="#leaderboard"]').first();
    await leaderboardTab.click();
    await page.waitForTimeout(3000);

    // Cuộn nhẹ xem vị trí Top 1 của mình
    await page.evaluate(() => window.scrollBy({ top: 200, behavior: 'smooth' }));
    await page.waitForTimeout(5000);

    console.log('🎉 Hoàn thành xuất sắc toàn bộ kịch bản Walkthrough!');
  });
});
