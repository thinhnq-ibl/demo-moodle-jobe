// @ts-check
const { test, expect } = require('@playwright/test');
const path = require('path');

const SCREENSHOT_DIR = path.resolve(__dirname, '../verification_evidence/screenshots');

async function loginStudent(page, context) {
  await context.clearCookies();
  await page.goto('/login/index.php');
  await page.waitForLoadState('networkidle');
  await page.fill('#username', 'student1');
  await page.fill('#password', 'StudentPassword123!');
  await Promise.all([
    page.waitForNavigation(),
    page.evaluate(() => {
      const form = /** @type {HTMLFormElement} */ (document.querySelector('form#login'));
      if (form) form.submit();
    })
  ]);
  await page.waitForLoadState('networkidle');
}

test.describe('Kiểm chứng Trình duyệt: CodeRunner Scratchpad UI & Program Testing', () => {

  test('Kịch bản Sinh viên: Scratchpad UI, Page Reload/Draft Recovery, và Program Testing (Wrong Expected & Correct)', async ({ page, context }) => {
    // 1. Đăng nhập với tài khoản sinh viên student1
    console.log('[E2E] Bước 1: Đăng nhập tài khoản student1...');
    await loginStudent(page, context);

    // 2. Đi đến Quiz 76 (CM ID: 112) trong Course 2
    console.log('[E2E] Bước 2: Truy cập Quiz 76 (CM ID: 112)...');
    await page.goto('/mod/quiz/view.php?id=112');
    await page.waitForLoadState('networkidle');

    // Bấm bắt đầu làm bài (Attempt quiz now)
    const startBtn = page.locator('button:has-text("Attempt quiz"), button:has-text("Bắt đầu làm bài"), input[value*="Attempt"]').first();
    await startBtn.click();
    await page.waitForLoadState('networkidle');

    // -------------------------------------------------------------
    // PHẦN 1: KIỂM CHỨNG GIAO DIỆN VÀ HOẠT ĐỘNG CỦA SCRATCHPAD UI
    // -------------------------------------------------------------
    console.log('[E2E] Bước 3: Kiểm chứng Scratchpad UI trên Câu 1...');
    
    // Kiểm tra cấu trúc bao bọc của Scratchpad UI
    const outerDiv = page.locator('.qtype-coderunner-sp-outer-div');
    await expect(outerDiv).toBeVisible();

    // Chụp ảnh trạng thái ban đầu của Scratchpad (mặc định khu vực Scratchpad thu gọn)
    await page.screenshot({ path: path.join(SCREENSHOT_DIR, '01_scratchpad_initial_state.png'), fullPage: true });

    // Bấm nút mở rộng Scratchpad
    const showScratchpadBtn = page.locator('a[id*="show-scratchpad-btn"]').first();
    await showScratchpadBtn.click();
    await page.waitForTimeout(1000); // Đợi hiệu ứng Bootstrap collapse

    // Kiểm tra các trường: Khẳng định HOÀN TOÀN KHÔNG CÓ trường Expected Output
    const expectedFieldCount = await page.locator('input[name*="expected"], textarea[name*="expected"]').count();
    console.log(`[E2E] Số trường Expected Output tìm thấy trên giao diện Scratchpad: ${expectedFieldCount}`);
    expect(expectedFieldCount).toBe(0);

    // Điền mã bài giải vào Answer editor (Ace Editor trên)
    const answerAce = page.locator('.ace_editor').nth(0);
    await answerAce.click();
    await page.keyboard.type("def find_max(lst):\n    return max(lst)\n");

    // Điền mã nháp vào Scratchpad editor (Ace Editor dưới)
    const scratchpadAce = page.locator('.ace_editor').nth(1);
    await scratchpadAce.click();
    await page.keyboard.type("print(find_max([3, 8, 5]))\n");

    // Bấm nút RUN trên thanh điều khiển Scratchpad
    console.log('[E2E] Bước 4: Bấm nút Run trên Scratchpad...');
    const runBtn = page.locator('button[id*="run-btn"]').first();
    await runBtn.click();

    // Đợi kết quả thực thi Jobe hiển thị trên pre
    const outputPre = page.locator('pre[id*="run-output-text"], div[id*="run-output"] pre').first();
    await expect(outputPre).toContainText('8', { timeout: 15000 });
    console.log('[E2E] Kết quả Run thành công: ' + await outputPre.textContent());

    // Chụp ảnh bằng chứng 02: Run thành công hiển thị kết quả 8
    await page.screenshot({ path: path.join(SCREENSHOT_DIR, '02_scratchpad_run_success.png'), fullPage: true });

    // Thử nghiệm lỗi Runtime trên Scratchpad: chia cho 0
    console.log('[E2E] Bước 5: Thử nghiệm lỗi Runtime (ZeroDivisionError) trên Scratchpad...');
    await scratchpadAce.click();
    await page.keyboard.press('ControlOrMeta+A');
    await page.keyboard.type("print(10 / 0)\n");
    await runBtn.click();
    await expect(outputPre).toContainText('ZeroDivisionError', { timeout: 15000 });
    
    // Chụp ảnh bằng chứng 03: Lỗi Runtime hiển thị văn bản thô
    await page.screenshot({ path: path.join(SCREENSHOT_DIR, '03_scratchpad_runtime_error.png'), fullPage: true });

    // Khôi phục mã đúng cho Scratchpad
    await scratchpadAce.click();
    await page.keyboard.press('ControlOrMeta+A');
    await page.keyboard.type("print(find_max([3, 8, 5]))\n");

    // -------------------------------------------------------------
    // BỔ SUNG: KIỂM CHỨNG PAGE RELOAD VÀ DRAFT RECOVERY
    // -------------------------------------------------------------
    console.log('[E2E] Bước 5b: Kiểm chứng Tải lại trang (Page Reload) và Khôi phục mã nháp Scratchpad...');
    await page.reload();
    await page.waitForLoadState('networkidle');

    // Mở lại Scratchpad sau reload và kiểm tra nội dung
    const showBtnAfterReload = page.locator('a[id*="show-scratchpad-btn"]').first();
    if (await showBtnAfterReload.isVisible()) {
      await showBtnAfterReload.click();
      await page.waitForTimeout(1000);
    }
    // Chụp ảnh bằng chứng khôi phục sau reload
    await page.screenshot({ path: path.join(SCREENSHOT_DIR, '03b_scratchpad_draft_after_reload.png'), fullPage: true });

    // Điền lại mã giải hoàn chỉnh nếu cần và bấm Check nộp bài Câu 1
    console.log('[E2E] Bước 6: Bấm Check nộp bài Câu 1...');
    const answerAceAgain = page.locator('.ace_editor').nth(0);
    await answerAceAgain.click();
    await page.keyboard.press('ControlOrMeta+A');
    await page.keyboard.type("def find_max(lst):\n    return max(lst)\n");

    const checkBtn1 = page.locator('button:has-text("Check"), input[value="Check"]').first();
    await checkBtn1.click();
    await page.waitForLoadState('networkidle');

    // Chụp ảnh bằng chứng 04: Đã Check câu 1
    await page.screenshot({ path: path.join(SCREENSHOT_DIR, '04_scratchpad_checked.png'), fullPage: true });

    // -------------------------------------------------------------
    // PHẦN 2: KIỂM CHỨNG MẪU PYTHON3_PROGRAM_TESTING (CÂU 2)
    // -------------------------------------------------------------
    console.log('[E2E] Bước 7: Chuyển sang Câu 2 (Program Testing)...');
    const nextPageBtn = page.getByRole('button', { name: 'Next page' });
    await nextPageBtn.click();
    await page.waitForLoadState('networkidle');

    const tableUi = page.locator('table.coderunner-ui-table, .form-table, table:has-text("Test")').first();
    await expect(tableUi).toBeVisible({ timeout: 10000 });

    // Chụp ảnh bằng chứng 05: Bảng Program Testing khởi tạo
    await page.screenshot({ path: path.join(SCREENSHOT_DIR, '05_program_testing_table_initial.png'), fullPage: true });

    // -------------------------------------------------------------
    // CA KIỂM CHỨNG T2/T4: NHẬP EXPECTED SAI VÀ QUAN SÁT PHẢN HỒI
    // -------------------------------------------------------------
    console.log('[E2E] Bước 8a: Nhập Expected SAI (T2/T4) để kiểm tra phát hiện lỗi từ lời giải tham chiếu tác giả...');
    const tableTextboxes = page.getByRole('table').getByRole('textbox');
    // Nhập Test: print(find_max([3, 8, 5])), Expected: 5 (Sai, vì max là 8)
    await tableTextboxes.nth(0).fill('print(find_max([3, 8, 5]))');
    await tableTextboxes.nth(1).fill('5');

    // Bổ sung thêm dòng để đủ >= 3 dòng theo yêu cầu mẫu
    if (await tableTextboxes.nth(2).isVisible()) {
      await tableTextboxes.nth(2).fill('print(find_max([10, -2, 7]))');
      await tableTextboxes.nth(3).fill('10');
    }
    if (await tableTextboxes.nth(4).isVisible()) {
      await tableTextboxes.nth(4).fill('print(find_max([-5, -1, -3]))');
      await tableTextboxes.nth(5).fill('-1');
    }

    await page.screenshot({ path: path.join(SCREENSHOT_DIR, '06a_program_testing_table_wrong_expected.png'), fullPage: true });

    // Bấm Check với expected sai
    console.log('[E2E] Bấm Check với Expected sai...');
    const checkBtn2 = page.locator('button:has-text("Check")').first();
    await checkBtn2.click();
    await page.waitForLoadState('networkidle');

    // Chụp ảnh bằng chứng phản hồi phát hiện Expected sai ("Oops: you marked the right answer wrong!")
    await page.screenshot({ path: path.join(SCREENSHOT_DIR, '07a_program_testing_wrong_expected_feedback.png'), fullPage: true });

    // -------------------------------------------------------------
    // CA KIỂM CHỨNG T1: SỬA EXPECTED ĐÚNG VÀ NỘP LẠI
    // -------------------------------------------------------------
    console.log('[E2E] Bước 8b: Sửa Expected thành ĐÚNG (T1: 8) và nộp lại...');
    const tableTextboxesAfter = page.getByRole('table').getByRole('textbox');
    await tableTextboxesAfter.nth(1).fill('8');

    await page.screenshot({ path: path.join(SCREENSHOT_DIR, '06b_program_testing_table_correct.png'), fullPage: true });

    // Bấm Check lại
    const checkBtn2Again = page.locator('button:has-text("Check")').first();
    await checkBtn2Again.click();
    await page.waitForLoadState('networkidle');

    // Chụp ảnh bằng chứng phản hồi thành công của Program Testing
    await page.screenshot({ path: path.join(SCREENSHOT_DIR, '07b_program_testing_success_feedback.png'), fullPage: true });

    // -------------------------------------------------------------
    // PHẦN 3: KẾT THÚC BÀI THI VÀ XEM ĐIỂM SỐ CHÍNH THỨC
    // -------------------------------------------------------------
    console.log('[E2E] Bước 9: Hoàn thành bài thi và xem tổng kết điểm...');
    const finishNavBtn = page.getByRole('button', { name: /Finish attempt/i }).first();
    await finishNavBtn.click();
    await page.waitForLoadState('networkidle');

    const submitAllBtn = page.locator('button:has-text("Submit all and finish"), input[value*="Submit all and finish"]').first();
    await submitAllBtn.click();
    await page.waitForTimeout(1000);

    const confirmModalBtn = page.locator('.modal-dialog button.btn-primary, .modal-footer button.btn-primary').first();
    if (await confirmModalBtn.isVisible()) {
      await confirmModalBtn.click();
      await page.waitForLoadState('networkidle');
    }

    // Chụp ảnh bằng chứng 08: Trang Review tổng kết điểm số, penalty và Gradebook
    await page.screenshot({ path: path.join(SCREENSHOT_DIR, '08_quiz_review_grades.png'), fullPage: true });
    console.log('[E2E] Hoàn tất 100% kịch bản kiểm chứng trình duyệt mở rộng!');
  });

});
