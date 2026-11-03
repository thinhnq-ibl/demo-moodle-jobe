// @ts-check
const { test, expect } = require('@playwright/test');
const { showCaption, highlight, slowLogin } = require('./tutorial_helpers');

test.describe('Video 1: Hướng dẫn Sử dụng dành cho Giảng viên (Teacher Flow)', () => {
  test.setTimeout(240000);

  test('Quy trình Giảng viên: Cấu hình Policy -> Quản lý Kho Seed -> Duyệt & Chấm Sao Testcase', async ({ page, context }) => {
    // Bước 1: Đăng nhập Giảng viên
    await slowLogin(
      page,
      context,
      'admin',
      'AdminPassword123!',
      'GIẢNG VIÊN • BƯỚC 1/6',
      'Đăng nhập & Điều hướng vào Khóa học'
    );
    await showCaption(
      page,
      'GIẢNG VIÊN • BƯỚC 1/6',
      'Trang Dashboard của Giảng viên',
      'Từ trang tổng quan Moodle, giảng viên truy cập vào khóa học "python 2".'
    );
    await page.waitForTimeout(2500);

    // Vào khóa học python 2
    await page.goto('/course/view.php?id=5');
    await page.waitForLoadState('networkidle');
    await showCaption(
      page,
      'GIẢNG VIÊN • BƯỚC 1/6',
      'Trang Khóa học "python 2"',
      'Giảng viên cuộn xem danh sách các bài thực hành Quiz và chọn "Bài thực hành 2: Xử lý chuỗi và Đối xứng".'
    );
    await page.waitForTimeout(2000);
    await page.evaluate(() => window.scrollBy({ top: 320, behavior: 'smooth' }));
    await page.waitForTimeout(2000);

    const quizLink = page.locator('a:has-text("Bài thực hành 2: Xử lý chuỗi và Đối xứng")').first();
    await highlight(quizLink, 1500);
    await quizLink.click();
    await page.waitForLoadState('networkidle');

    // Bước 2: Từ trang Quiz vào trang Cấu hình Testcase Exchange
    await showCaption(
      page,
      'GIẢNG VIÊN • BƯỚC 2/6',
      'Truy cập Quản trị Testcase từ Bài thi Quiz',
      'Tại trang Quiz, giảng viên mở Trung tâm Trao đổi Testcase hoặc vào trang Cấu hình Chính sách (Policy).'
    );
    await page.waitForTimeout(2500);

    await page.goto('/local/testcase_exchange/policy.php?course=5&question=7:131');
    await page.waitForLoadState('networkidle');

    // Bước 3: Thiết lập Quiz Policy & Question Policy
    await showCaption(
      page,
      'GIẢNG VIÊN • BƯỚC 3/6',
      'Thiết lập Chính sách Bài thi (Quiz Policy)',
      'Giảng viên chọn câu hỏi CodeRunner, bật tính năng đóng góp, bật Bảng xếp hạng (Leaderboard) và chọn Chế độ duyệt (Teacher review / Automatic approval).'
    );
    await page.waitForTimeout(2500);

    const questionSelect = page.locator('select#question');
    await highlight(questionSelect, 1200);

    const enabledCheck = page.locator('#enabled');
    await highlight(enabledCheck, 1000);

    const leaderboardCheck = page.locator('#leaderboard_enabled');
    await highlight(leaderboardCheck, 1000);

    const reviewModeSelect = page.locator('select[name="review_mode"]');
    await highlight(reviewModeSelect, 1500);

    // Bước 4: Quản lý Kho Hạt Giống Thưởng Độc Lập (Seed Testcase Bank)
    await page.evaluate(() => window.scrollBy({ top: 520, behavior: 'smooth' }));
    await showCaption(
      page,
      'GIẢNG VIÊN • BƯỚC 4/6',
      'Quản lý Kho Hạt Giống Thưởng Độc Lập (Seed Bank)',
      'Kho Seed được lưu tách biệt hoàn toàn với đề thi Quiz, dùng làm phần thưởng mở khóa khi sinh viên đóng góp testcase hợp lệ. Có thể thêm thủ công hoặc nhập file XML Moodle.'
    );
    await page.waitForTimeout(3000);

    const seedCodeInput = page.locator('textarea[name="seed_testcode"]');
    await highlight(seedCodeInput, 1000);
    await seedCodeInput.fill('print(is_palindrome("Mr. Owl ate my metal worm"))');
    await page.waitForTimeout(1000);

    const seedExpInput = page.locator('textarea[name="seed_expected"]');
    await highlight(seedExpInput, 1000);
    await seedExpInput.fill('True');
    await page.waitForTimeout(1000);

    const seedPurposeInput = page.locator('input[name="seed_purpose"]');
    await highlight(seedPurposeInput, 1000);
    await seedPurposeInput.fill('Kiểm tra câu đối xứng có dấu chấm và khoảng trắng');
    await page.waitForTimeout(1500);

    const addSeedBtn = page.locator('button[name="action"][value="add_manual_seed"]');
    await highlight(addSeedBtn, 1500);
    await addSeedBtn.click();
    await page.waitForLoadState('networkidle');

    await page.evaluate(() => window.scrollBy({ top: 450, behavior: 'smooth' }));
    await showCaption(
      page,
      'GIẢNG VIÊN • BƯỚC 4/6',
      'Đã thêm Hạt giống mới vào Kho Seed Bank',
      'Hạt giống mới lập tức xuất hiện trong bảng danh sách kèm nhãn "Thủ công". Bên phải là khu vực hỗ trợ tải lên file .xml xuất từ Moodle.'
    );
    await page.waitForTimeout(3500);

    // Bước 5: Chuyển sang trang Duyệt Đóng Góp Testcase (review.php)
    await page.goto('/local/testcase_exchange/review.php?course=5&question=7:131&status=pending');
    await page.waitForLoadState('networkidle');
    await showCaption(
      page,
      'GIẢNG VIÊN • BƯỚC 5/6',
      'Kiểm duyệt Testcase do Sinh viên Đóng góp (Tab Pending)',
      'Giảng viên xem Test Input, kết quả chạy trên hàm chuẩn (Oracle Output), mục đích kiểm thử của sinh viên; sau đó chọn số sao (1-5 ⭐), nhập nhận xét và bấm Approve.'
    );
    await page.waitForTimeout(3000);

    const approveBtn = page.locator('button[value="approved"]').first();
    if (await approveBtn.isVisible()) {
      const rowForm = approveBtn.locator('xpath=./ancestor::form');
      const starSelect = rowForm.locator('select[name="rating"]');
      await highlight(starSelect, 1200);
      await starSelect.selectOption('5');
      await page.waitForTimeout(1000);

      const noteInput = rowForm.locator('input[name="review_note"]');
      await highlight(noteInput, 1200);
      await noteInput.fill('Xuất sắc! Ca kiểm thử biên rất sáng tạo và có giải thích rõ ràng.');
      await page.waitForTimeout(1500);

      await highlight(approveBtn, 1500);
      await approveBtn.click();
      await page.waitForLoadState('networkidle');
    }

    // Bước 6: Hậu kiểm tại tab Approved (Cập nhật Sao & Comment với nút Save/Reject mới)
    await page.goto('/local/testcase_exchange/review.php?course=5&question=7:131&status=approved');
    await page.waitForLoadState('networkidle');
    await showCaption(
      page,
      'GIẢNG VIÊN • BƯỚC 6/6',
      'Chỉnh sửa Sao & Nhận xét sau khi Duyệt (Tab Approved)',
      'Kể cả khi dùng Auto-Approve hay đã duyệt trước đó, giảng viên vẫn có thể chỉnh sửa số sao và viết nhận xét dài (ô comment rộng 100%, nút Reject bên trái, nút Save bên phải).'
    );
    await page.waitForTimeout(3000);

    const approvedCommentInput = page.locator('input[name="review_note"]').first();
    if (await approvedCommentInput.isVisible()) {
      const approvedForm = approvedCommentInput.locator('xpath=./ancestor::form');
      const approvedStarSelect = approvedForm.locator('select[name="rating"]');
      await highlight(approvedStarSelect, 1000);
      await approvedStarSelect.selectOption('5');
      await page.waitForTimeout(800);

      await highlight(approvedCommentInput, 1200);
      await approvedCommentInput.fill('Nhận xét của GV: Testcase mẫu mực, cộng 5 sao vào Bảng xếp hạng!');
      await page.waitForTimeout(1500);

      const saveBtn = approvedForm.locator('button[value="rate_only"]:has-text("Save")');
      await highlight(saveBtn, 1500);
      await saveBtn.click();
      await page.waitForLoadState('networkidle');
    }

    await showCaption(
      page,
      'HOÀN TẤT VIDEO GIẢNG VIÊN',
      'Đã lưu Đánh giá Sao & Nhận xét thành công!',
      'Điểm sao và lời nhận xét đã được đồng bộ sang giao diện của sinh viên và Bảng vinh danh khóa học.'
    );
    await page.waitForTimeout(4000);
  });
});
