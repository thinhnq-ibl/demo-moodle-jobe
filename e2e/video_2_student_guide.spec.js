// @ts-check
const { test, expect } = require('@playwright/test');
const { showCaption, highlight, slowLogin } = require('./tutorial_helpers');

test.describe('Video 2: Hướng dẫn Sử dụng dành cho Sinh viên (Student Flow)', () => {
  test.setTimeout(240000);

  test('Quy trình Sinh viên: Vào Quiz -> Playground Vi Sai -> Đóng góp Testcase -> Mở Thưởng & Leaderboard', async ({ page, context }) => {
    // Bước 1: Sinh viên đăng nhập & vào môn học -> Quiz
    await slowLogin(
      page,
      context,
      'student2',
      'StudentPassword123!',
      'SINH VIÊN • BƯỚC 1/6',
      'Đăng nhập & Truy cập Khóa học'
    );
    await showCaption(
      page,
      'SINH VIÊN • BƯỚC 1/6',
      'Trang Dashboard của Sinh viên',
      'Sau khi đăng nhập, sinh viên vào môn học "python 2" để làm bài thực hành.'
    );
    await page.waitForTimeout(2500);

    await page.goto('/course/view.php?id=5');
    await page.waitForLoadState('networkidle');
    await showCaption(
      page,
      'SINH VIÊN • BƯỚC 1/6',
      'Chọn Bài thi Quiz trong Khóa học',
      'Sinh viên cuộn xuống danh sách hoạt động và bấm vào "Bài thực hành 2: Xử lý chuỗi và Đối xứng".'
    );
    await page.waitForTimeout(2000);
    await page.evaluate(() => window.scrollBy({ top: 320, behavior: 'smooth' }));
    await page.waitForTimeout(2000);

    const quizLink = page.locator('a:has-text("Bài thực hành 2: Xử lý chuỗi và Đối xứng")').first();
    await highlight(quizLink, 1500);
    await quizLink.click();
    await page.waitForLoadState('networkidle');

    // Bước 2: Từ trang Quiz mở Sân chơi Testcase Bank & Exchange
    await showCaption(
      page,
      'SINH VIÊN • BƯỚC 2/6',
      'Mở Sân chơi Testcase từ Bài thi Quiz',
      'Ngay tại giao diện bài thi Quiz, sinh viên bấm nút "Testcase Bank & Exchange" để vào môi trường thử nghiệm tự do (không bị trừ điểm phạt).'
    );
    await page.waitForTimeout(2500);

    const exchangeBtn = page.locator('a:has-text("Testcase Bank & Exchange")').first();
    await highlight(exchangeBtn, 1500);
    await exchangeBtn.click();
    await page.waitForLoadState('networkidle');

    // Chọn câu hỏi
    await showCaption(
      page,
      'SINH VIÊN • BƯỚC 2/6',
      'Chọn Câu hỏi CodeRunner cần Thử nghiệm',
      'Sinh viên chọn câu hỏi "Bài 1: Kiểm tra chuỗi Palindrome (đối xứng)" trong danh sách.'
    );
    const questionSelect = page.locator('select#question');
    if (await questionSelect.isVisible()) {
      await highlight(questionSelect, 1200);
      await questionSelect.selectOption('7:131');
      await page.waitForTimeout(1500);
    }

    // Bước 3: Dán code & Thiết kế Testcase
    await page.evaluate(() => window.scrollBy({ top: 280, behavior: 'smooth' }));
    await showCaption(
      page,
      'SINH VIÊN • BƯỚC 3/6',
      'Dán Mã nguồn & Tự Thiết kế Ca kiểm thử (Testcase Definition)',
      'Sinh viên dán code Python của mình vào khung Student Source Code, sau đó nhập Test Code, Expected Output, Phân loại và Mục đích kiểm thử theo đúng cấu trúc CodeRunner.'
    );
    await page.waitForTimeout(2500);

    const codeEditor = page.locator('#student_code');
    await highlight(codeEditor, 1200);
    await codeEditor.fill(
`def is_palindrome(s):
    # Lọc chỉ giữ chữ cái và chữ số, chuyển về chữ thường
    cleaned = ''.join(c.lower() for c in s if c.isalnum())
    return cleaned == cleaned[::-1]
`
    );
    await page.waitForTimeout(1500);

    const testInput = page.locator('#test_input');
    await highlight(testInput, 1000);
    await testInput.fill('print(is_palindrome("Yo, Banana Boy!"))');
    await page.waitForTimeout(1000);

    const predictedOutput = page.locator('#predicted_output');
    await highlight(predictedOutput, 1000);
    await predictedOutput.fill('True');
    await page.waitForTimeout(1000);

    const categorySelect = page.locator('#category');
    await highlight(categorySelect, 900);
    await categorySelect.selectOption('boundary');
    await page.waitForTimeout(800);

    const purposeInput = page.locator('#purpose');
    await highlight(purposeInput, 1000);
    await purposeInput.fill('Kiểm tra chuỗi đối xứng có dấu phẩy và dấu chấm than ở cuối');
    await page.waitForTimeout(1500);

    // Bước 4: Chạy Thử Nghiệm Vi Sai (Differential Testing)
    const runBtn = page.locator('button[type="submit"]:has-text("Chạy Thử Nghiệm Vi Sai")');
    await showCaption(
      page,
      'SINH VIÊN • BƯỚC 4/6',
      'Chạy Thử Nghiệm Vi Sai trên Cụm Jobe Sandbox',
      'Bấm "Chạy Thử Nghiệm Vi Sai": Hệ thống thực thi song song code của sinh viên và Solution của giảng viên. Kết quả Solution được ẩn để chống lộ đề, chỉ báo ĐÚNG CHUẨN ĐỀ hay SAI QUY ƯỚC.'
    );
    await highlight(runBtn, 1500);
    await runBtn.click();
    await page.waitForLoadState('networkidle');

    await page.evaluate(() => window.scrollBy({ top: 520, behavior: 'smooth' }));
    await showCaption(
      page,
      'SINH VIÊN • BƯỚC 4/6',
      'Quan sát Kết quả Đối chiếu Vi Sai',
      'Sinh viên thấy rõ: Kỳ vọng (True) == Code của bạn in ra (True -> ✓ Khớp kỳ vọng), đồng thời Solution báo "✓ ĐÚNG CHUẨN ĐỀ".'
    );
    await page.waitForTimeout(3500);

    // Bước 5: Đề xuất Đóng góp & Xem Phản hồi từ Giảng viên
    const contributeBtn = page.locator('button:has-text("Đề xuất Đóng góp")').first();
    if (await contributeBtn.isVisible()) {
      await showCaption(
        page,
        'SINH VIÊN • BƯỚC 5/6',
        'Đề xuất Đóng góp Testcase vào Ngân hàng Chung',
        'Khi ca kiểm thử đã đạt chuẩn, sinh viên bấm "🚀 Đề xuất Đóng góp" để gửi lên cho giảng viên duyệt (hoặc nhận duyệt tự động).'
      );
      await highlight(contributeBtn, 1500);
      await contributeBtn.click();
      await page.waitForLoadState('networkidle');
    }

    await page.evaluate(() => window.scrollBy({ top: 480, behavior: 'smooth' }));
    const contribTab = page.locator('a[href="#contributions"]').first();
    if (await contribTab.isVisible()) {
      await highlight(contribTab, 1200);
      await contribTab.click();
      await showCaption(
        page,
        'SINH VIÊN • BƯỚC 5/6',
        'Theo dõi Lịch sử Đóng góp, Số Sao ⭐ & Nhận xét của Giảng viên',
        'Tại tab Đóng góp (Contributions), sinh viên xem trạng thái các testcase đã nộp cùng đánh giá 5/5 ★ và lời khen trực tiếp từ giảng viên.'
      );
      await page.waitForTimeout(3500);
    }

    // Bước 6: Mở khóa Phần thưởng Hạt giống (Rewards) & Xem Bảng Xếp Hạng (Leaderboard)
    const rewardsTab = page.locator('a[href="#rewards"]').first();
    if (await rewardsTab.isVisible()) {
      await highlight(rewardsTab, 1200);
      await rewardsTab.click();
      await showCaption(
        page,
        'SINH VIÊN • BƯỚC 6/6',
        'Mở khóa Testcase Hạt giống Thưởng (Rewards)',
        'Mỗi đóng góp được duyệt giúp sinh viên nhận 1 phần thưởng từ Kho Seed của giảng viên. Bấm nút "🎁 Claim Reward" để mở khóa ca kiểm thử bí mật!'
      );
      await page.waitForTimeout(2500);

      const claimBtn = page.locator('form:has(input[name="action"][value="claim_reward"]) button').first();
      if (await claimBtn.isVisible()) {
        await highlight(claimBtn, 1500);
        await claimBtn.click();
        await page.waitForLoadState('networkidle');

        await page.evaluate(() => window.scrollBy({ top: 480, behavior: 'smooth' }));
        const rewardsTabAfter = page.locator('a[href="#rewards"]').first();
        if (await rewardsTabAfter.isVisible()) {
          await rewardsTabAfter.click();
        }
        await showCaption(
          page,
          'SINH VIÊN • BƯỚC 6/6',
          'Đã Mở khóa Thành công Testcase Hạt giống!',
          'Trạng thái chuyển thành "✓ Claimed", hiển thị đầy đủ Test Code và Expected Output để sinh viên dùng kiểm tra lại bài làm của mình.'
        );
        await page.waitForTimeout(3500);
      }
    }

    // Chuyển sang tab Leaderboard
    const leaderboardTab = page.locator('a[href="#leaderboard"]').first();
    await highlight(leaderboardTab, 1200);
    await leaderboardTab.click();
    await showCaption(
      page,
      'HOÀN TẤT VIDEO SINH VIÊN',
      'Bảng Vinh Danh Đóng Góp Khóa Học (Leaderboard 🏆)',
      'Sinh viên xem thứ hạng cá nhân (#1 🥇), huy hiệu Tổng sao gọn gàng (Total Stars ⭐) và tổng số testcase đã được phê duyệt.'
    );
    await page.waitForTimeout(4500);
  });
});
