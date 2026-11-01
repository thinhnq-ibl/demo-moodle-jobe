// @ts-check
const { test, expect } = require('@playwright/test');
const path = require('path');
const fs = require('fs');
const { execSync } = require('child_process');

function runDbQuery(sql) {
  const sanitized = sql.replace(/"/g, '\\"');
  const res = execSync(`docker exec moodle_app php -r "define('CLI_SCRIPT', true); require_once('/var/www/html/config.php'); require_once('/var/www/html/local/testcase_exchange/classes/external_database.php'); \\$c = \\local_testcase_exchange\\external_database::connect(); \\$res = \\$c->query(\\"${sanitized}\\"); if (\\$res instanceof \\mysqli_result) { \\$rows = \\$res->fetch_all(MYSQLI_ASSOC); echo json_encode(\\$rows); } else { echo json_encode(['affected_rows' => \\$c->affected_rows]); }"`);
  return JSON.parse(res.toString());
}

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

test.describe('Phân tách rõ nét View Sinh viên và View Giáo viên (UI Separation)', () => {

  const evidenceDir = path.resolve(__dirname, '../verification_evidence');
  if (!fs.existsSync(evidenceDir)) {
    fs.mkdirSync(evidenceDir, { recursive: true });
  }

  test('1. View Sinh viên: Tài khoản student1 vào thẳng trang làm bài mặc định, không lộ chức năng quản lý', async ({ page, context }) => {
    // 1. Đăng nhập tài khoản sinh viên thông thường (student1)
    await loginUser(page, context, 'student1', 'StudentPassword123!');

    // 2. Truy cập dashboard khóa học
    await page.goto('/local/testcase_exchange/index.php?course=2');
    await page.waitForLoadState('networkidle');

    // 1.1 Tiêu đề và nội dung sinh viên (hỗ trợ cả tiếng Anh và tiếng Việt)
    await expect(page.locator('body')).toContainText(/Differential Testing Playground|Phòng Thí Nghiệm Kiểm Thử Vi Sai/);
    
    // 1.2 Kiểm tra thứ tự các thành phần: Đề bài -> Mã nguồn -> Testcase -> Kết quả & Đóng góp
    await expect(page.locator('text=/1\..*(Question Prompt|Đề bài)/i')).toBeVisible();
    await expect(page.locator('text=/2\..*(Student Source Code|Mã Nguồn Của Bạn)/i')).toBeVisible();
    await expect(page.locator('#student_code')).toBeVisible();
    await expect(page.locator('text=/3\..*(Testcase & Expected Output|Định Nghĩa Ca Kiểm Thử)/i')).toBeVisible();
    await expect(page.locator('#test_input')).toBeVisible();
    await expect(page.locator('#predicted_output')).toBeVisible();
    await expect(page.locator('text=/4 & 5\..*(Test Results|Kết Quả Kiểm Tra)/i')).toBeVisible();

    // 1.3 Sub-tabs của sinh viên
    await expect(page.locator('.nav-tabs a.nav-link[href="#student_solve"]')).toBeVisible();
    await expect(page.locator('.nav-tabs a.nav-link[href="#contributions"]')).toBeVisible();
    await expect(page.locator('.nav-tabs a.nav-link[href="#rewards"]')).toBeVisible();
    await expect(page.locator('.nav-tabs a.nav-link[href="#leaderboard"]')).toBeVisible();

    // 1.4 Đảm bảo KHÔNG xuất hiện view quản lý của giáo viên
    await expect(page.locator('h4:has-text("Teacher Management Hub"), h4:has-text("Trung Tâm Quản Lý Giáo Viên")')).not.toBeVisible();
    await expect(page.locator('a.nav-link[href="#tab_overview"]')).not.toBeVisible();
    await expect(page.locator('a.nav-link[href="#tab_policy"]')).not.toBeVisible();
    await expect(page.locator('a.nav-link[href="#tab_bank"]')).not.toBeVisible();

    // Chụp ảnh màn hình Student View
    await page.screenshot({ path: path.join(evidenceDir, 'student_view.png'), fullPage: true });
  });

  test('2. An toàn & Phân quyền Backend: Sinh viên bị chặn khi truy cập URL quản lý của giáo viên', async ({ page, context }) => {
    await loginUser(page, context, 'student1', 'StudentPassword123!');

    // Thử truy cập policy.php
    await page.goto('/local/testcase_exchange/policy.php?course=2');
    await page.waitForLoadState('networkidle');
    const pageContent = await page.content();
    expect(pageContent).toMatch(/error\/nopermissions|accessdenied|permissions|Access denied|không có quyền/i);

    // Thử truy cập review.php
    await page.goto('/local/testcase_exchange/review.php?course=2');
    await page.waitForLoadState('networkidle');
    const reviewContent = await page.content();
    expect(reviewContent).toMatch(/error\/nopermissions|accessdenied|permissions|Access denied|không có quyền/i);
  });

  test('3. View Giáo viên thông thường (teacher1, Non-Admin): Mặc định vào Management Hub 5 Tabs', async ({ page, context }) => {
    // Đăng nhập tài khoản Giáo viên thông thường (teacher1 - không phải Admin)
    await loginUser(page, context, 'teacher1', 'TeacherPassword123!');

    // Truy cập index.php
    await page.goto('/local/testcase_exchange/index.php?course=2');
    await page.waitForLoadState('networkidle');

    // 3.1 Kiểm tra tiêu đề Hub và nút Xem thử
    await expect(page.locator('h4:has-text("Teacher Management Hub"), h4:has-text("Trung Tâm Quản Lý Giáo Viên")')).toBeVisible();
    const previewBtn = page.locator('a:has-text("Preview as Student"), a:has-text("Xem thử như sinh viên")');
    await expect(previewBtn).toBeVisible();

    // 3.2 Kiểm tra 5 Tabs chuyên biệt
    await expect(page.locator('.nav-tabs a.nav-link[href="#tab_overview"]')).toBeVisible();
    await expect(page.locator('.nav-tabs a.nav-link[href="#tab_review"]')).toBeVisible();
    await expect(page.locator('.nav-tabs a.nav-link[href="#tab_bank"]')).toBeVisible();
    await expect(page.locator('.nav-tabs a.nav-link[href="#tab_activity"]')).toBeVisible();
    await expect(page.locator('.nav-tabs a.nav-link[href="#tab_policy"]')).toBeVisible();

    // 3.3 Tab 1 - Tổng quan
    await expect(page.locator('.nav-tabs a.nav-link[href="#tab_overview"]')).toHaveClass(/active/);
    await expect(page.locator('#tab_overview')).toContainText(/Tổng Quan Hoạt Động|Overview/i);
    await expect(page.locator('#tab_overview')).toContainText(/Lượt Chạy Thử|Runs/i);

    // Chụp ảnh màn hình Teacher Management Hub bằng tài khoản giáo viên thông thường
    await page.screenshot({ path: path.join(evidenceDir, 'teacher_management_view.png'), fullPage: true });

    // 3.4 Tab 2 - Đóng góp & Thẩm định
    await page.click('.nav-tabs a.nav-link[href="#tab_review"]');
    await expect(page.locator('#tab_review')).toBeVisible();
    await expect(page.locator('select[name="review_status"]')).toBeVisible();

    // 3.5 Tab 3 - Kho Testcase (3 nguồn)
    await page.click('.nav-tabs a.nav-link[href="#tab_bank"]');
    await expect(page.locator('#tab_bank')).toBeVisible();
    await expect(page.locator('#tab_bank')).toContainText(/1️⃣.*(Public Sample|Testcase Mẫu)/i);
    await expect(page.locator('#tab_bank')).toContainText(/2️⃣.*(Seed Bank|Testcase Mầm)/i);
    await expect(page.locator('#tab_bank')).toContainText(/3️⃣.*(Approved Peer|Testcase Bạn Học Đã Duyệt)/i);

    // 3.6 Tab 4 - Hoạt động sinh viên & Xuất
    await page.click('.nav-tabs a.nav-link[href="#tab_activity"]');
    await expect(page.locator('#tab_activity')).toBeVisible();
    await expect(page.locator('#tab_activity a[href*="export.php"]')).toHaveCount(3);

    // 3.7 Tab 5 - Cấu hình chính sách
    await page.click('.nav-tabs a.nav-link[href="#tab_policy"]');
    await expect(page.locator('#tab_policy')).toBeVisible();
    await expect(page.locator('input#policyEnabled')).toBeVisible();
    await expect(page.locator('select[name="review_mode"]')).toBeVisible();
    await expect(page.locator('select[name="reward_policy"]')).toBeVisible();
  });

  test('4. Chế độ Xem thử (Teacher Preview Mode): Giáo viên xem góc nhìn học viên và quay lại an toàn', async ({ page, context }) => {
    await loginUser(page, context, 'teacher1', 'TeacherPassword123!');

    await page.goto('/local/testcase_exchange/index.php?course=2');
    await page.waitForLoadState('networkidle');

    // Bấm nút Xem thử như sinh viên
    const previewBtn = page.locator('a:has-text("Preview as Student"), a:has-text("Xem thử như sinh viên")');
    await previewBtn.click();
    await page.waitForLoadState('networkidle');

    // 4.1 Kiểm tra URL và banner cảnh báo màu vàng
    expect(page.url()).toContain('preview=1');
    await expect(page.locator('text=/Preview Mode — Teacher Account|Chế độ xem thử — Tài khoản giáo viên/i')).toBeVisible();
    const returnBtn = page.locator('a:has-text("Return to Management"), a:has-text("Quay lại quản lý")');
    await expect(returnBtn).toBeVisible();

    // 4.2 Kiểm tra giao diện chuyển sang view sinh viên
    await expect(page.locator('text=/1\..*(Question Prompt|Đề bài)/i')).toBeVisible();
    await expect(page.locator('#student_code')).toBeVisible();
    await expect(page.locator('h4:has-text("Teacher Management Hub"), h4:has-text("Trung Tâm Quản Lý Giáo Viên")')).not.toBeVisible();

    // Chụp ảnh màn hình Teacher Preview Mode
    await page.screenshot({ path: path.join(evidenceDir, 'teacher_preview_mode.png'), fullPage: true });

    // 4.3 Bấm nút quay lại quản lý
    await returnBtn.click();
    await page.waitForLoadState('networkidle');

    // Kiểm tra đã trở lại View Quản lý của Giáo viên
    await expect(page.locator('h4:has-text("Teacher Management Hub"), h4:has-text("Trung Tâm Quản Lý Giáo Viên")')).toBeVisible();
    expect(page.url()).not.toContain('preview=1');
  });

  test('5. Sinh viên bị chặn khi gửi POST các action quản trị (save_policy, teacher_review, add_manual_seed, delete_seed) và dữ liệu bảo toàn', async ({ page, context }) => {
    await loginUser(page, context, 'student1', 'StudentPassword123!');
    await page.goto('/local/testcase_exchange/index.php?course=2');
    await page.waitForLoadState('networkidle');

    // Lấy sesskey của sinh viên
    const sesskey = await page.evaluate(() => (window['M'] && window['M'].cfg) ? window['M'].cfg.sesskey : '');
    expect(sesskey).toBeTruthy();

    // 5.1 POST action save_policy
    const policyBefore = runDbQuery('SELECT * FROM quiz_settings WHERE quiz_id = 76')[0];
    const qPolicyBefore = runDbQuery('SELECT * FROM question_policies WHERE course_id = 2 AND quiz_id = 76 AND question_id = 794')[0];

    const resPolicy = await page.request.post('http://localhost:8080/local/testcase_exchange/index.php?course=2', {
      form: {
        sesskey: sesskey,
        action: 'save_policy',
        target_quiz_id: 76,
        target_question_id: 794,
        review_mode: 'auto',
        reward_policy: 'disabled',
        max_runs_per_minute: 99,
        max_input_bytes: 9999
      }
    });
    const textPolicy = await resPolicy.text();
    // Bắt buộc kiểm tra thông báo lỗi phân quyền cụ thể
    expect(textPolicy).toMatch(/permissions|không có quyền|Manage testcase exchange system/i);
    // Xác nhận trạng thái cơ sở dữ liệu hoàn toàn không bị biến đổi
    const policyAfter = runDbQuery('SELECT * FROM quiz_settings WHERE quiz_id = 76')[0];
    const qPolicyAfter = runDbQuery('SELECT * FROM question_policies WHERE course_id = 2 AND quiz_id = 76 AND question_id = 794')[0];
    expect(policyAfter).toEqual(policyBefore);
    expect(qPolicyAfter).toEqual(qPolicyBefore);

    // 5.2 POST action teacher_review
    const reviewBefore = runDbQuery('SELECT * FROM testcase_contributions WHERE id = 122')[0];

    const resReview = await page.request.post('http://localhost:8080/local/testcase_exchange/index.php?course=2', {
      form: {
        sesskey: sesskey,
        action: 'teacher_review',
        contribution_id: 122,
        decision: 'approved',
        rating: 5,
        review_note: 'Hacked by student'
      }
    });
    const textReview = await resReview.text();
    expect(textReview).toMatch(/permissions|không có quyền|Review peer testcases/i);
    const reviewAfter = runDbQuery('SELECT * FROM testcase_contributions WHERE id = 122')[0];
    expect(reviewAfter).toEqual(reviewBefore);

    // 5.3 POST action add_manual_seed
    const seedCountBefore = runDbQuery('SELECT COUNT(*) as cnt FROM question_seed_testcases WHERE quiz_id = 76 AND question_id = 794')[0].cnt;

    const resAddSeed = await page.request.post('http://localhost:8080/local/testcase_exchange/index.php?course=2', {
      form: {
        sesskey: sesskey,
        action: 'add_manual_seed',
        seed_quiz_id: 76,
        seed_question_id: 794,
        seed_testcode: 'print("student malicious seed")',
        seed_expected: 'malicious'
      }
    });
    const textAddSeed = await resAddSeed.text();
    expect(textAddSeed).toMatch(/permissions|không có quyền|Manage testcase exchange system/i);
    const seedCountAfter = runDbQuery('SELECT COUNT(*) as cnt FROM question_seed_testcases WHERE quiz_id = 76 AND question_id = 794')[0].cnt;
    expect(seedCountAfter).toEqual(seedCountBefore);

    // 5.4 POST action delete_seed
    const seedRowsBefore = runDbQuery('SELECT * FROM question_seed_testcases WHERE id = 29');

    const resDelSeed = await page.request.post('http://localhost:8080/local/testcase_exchange/index.php?course=2', {
      form: {
        sesskey: sesskey,
        action: 'delete_seed',
        seed_id: 29
      }
    });
    const textDelSeed = await resDelSeed.text();
    expect(textDelSeed).toMatch(/permissions|không có quyền|Manage testcase exchange system/i);
    const seedRowsAfter = runDbQuery('SELECT * FROM question_seed_testcases WHERE id = 29');
    expect(seedRowsAfter).toEqual(seedRowsBefore);
  });

  test('6. Giáo viên lưu cấu hình chính sách (Tab 5), chặn Quiz ngoài quyền (Quiz 2 thuộc Course 3), và bảo toàn roundtrip', async ({ page, context }) => {
    await loginUser(page, context, 'teacher1', 'TeacherPassword123!');
    await page.goto('/local/testcase_exchange/index.php?course=2&tab=policy');
    await page.waitForLoadState('networkidle');

    // 6.1 Kiểm tra giáo viên gửi ID Quiz thực sự tồn tại ở khóa học khác (Quiz 2 thuộc Course 3) bị từ chối
    const sesskey = await page.evaluate(() => (window['M'] && window['M'].cfg) ? window['M'].cfg.sesskey : '');
    const quiz2SettingsBefore = runDbQuery('SELECT * FROM quiz_settings WHERE quiz_id = 2');

    const resInvalidQuiz = await page.request.post('http://localhost:8080/local/testcase_exchange/index.php?course=2', {
      form: {
        sesskey: sesskey,
        action: 'save_policy',
        target_quiz_id: 2, // Quiz 2 thuộc Course 3: 'Huong dan check so nguyen to'
        target_question_id: 0,
        review_mode: 'auto',
        reward_policy: 'disabled',
        max_runs_per_minute: 99,
        max_input_bytes: 9999
      }
    });
    const textInvalidQuiz = await resInvalidQuiz.text();
    // Bắt buộc xác nhận thông báo lỗi dmlmissingrecord và KHÔNG dựa vào 200/303
    expect(textInvalidQuiz).toMatch(/dmlmissingrecord|Can't find data record in database table quiz|Không tìm thấy bản ghi/i);
    const quiz2SettingsAfter = runDbQuery('SELECT * FROM quiz_settings WHERE quiz_id = 2');
    expect(quiz2SettingsAfter).toEqual(quiz2SettingsBefore);

    // 6.2 Kiểm thử TẮT Leaderboard (bỏ chọn checkbox) và đổi normalization sang 'raw'
    await page.goto('/local/testcase_exchange/index.php?course=2&tab=policy');
    await page.waitForLoadState('networkidle');

    const leaderboardCheckbox = page.locator('#policyLeaderboard');
    if (await leaderboardCheckbox.isChecked()) {
      await leaderboardCheckbox.uncheck({ force: true });
    }
    await page.selectOption('select[name="review_mode"]', 'auto');
    await page.selectOption('select[name="reward_policy"]', 'disabled');
    await page.fill('input[name="max_runs_per_minute"]', '35');
    await page.fill('input[name="max_input_bytes"]', '4096');
    await page.selectOption('select[name="normalization_mode"]', 'raw');

    // Submit form
    await Promise.all([
      page.waitForNavigation(),
      page.click('button:has-text("Lưu Cấu Hình Chính Sách")')
    ]);
    await page.waitForLoadState('networkidle');

    // Reload lại trang Tab Policy để xác minh roundtrip persistence (Leaderboard TẮT)
    await page.goto('/local/testcase_exchange/index.php?course=2&tab=policy');
    await page.waitForLoadState('networkidle');

    expect(await page.locator('#policyLeaderboard').isChecked()).toBeFalsy();
    await expect(page.locator('select[name="review_mode"]')).toHaveValue('auto');
    await expect(page.locator('select[name="reward_policy"]')).toHaveValue('disabled');
    await expect(page.locator('input[name="max_runs_per_minute"]')).toHaveValue('35');
    await expect(page.locator('input[name="max_input_bytes"]')).toHaveValue('4096');
    await expect(page.locator('select[name="normalization_mode"]')).toHaveValue('raw');

    // 6.3 Kiểm thử BẬT Leaderboard (tích chọn checkbox) và khôi phục normalization chuẩn
    await page.locator('#policyLeaderboard').check({ force: true });
    await page.selectOption('select[name="review_mode"]', 'teacher');
    await page.selectOption('select[name="reward_policy"]', 'one_for_one');
    await page.fill('input[name="max_runs_per_minute"]', '10');
    await page.fill('input[name="max_input_bytes"]', '8192');
    await page.selectOption('select[name="normalization_mode"]', 'trim');

    await Promise.all([
      page.waitForNavigation(),
      page.click('button:has-text("Lưu Cấu Hình Chính Sách")')
    ]);
    await page.waitForLoadState('networkidle');

    // Reload lại trang Tab Policy để xác minh Leaderboard đã BẬT lại
    await page.goto('/local/testcase_exchange/index.php?course=2&tab=policy');
    await page.waitForLoadState('networkidle');
    expect(await page.locator('#policyLeaderboard').isChecked()).toBeTruthy();
    await expect(page.locator('select[name="review_mode"]')).toHaveValue('teacher');
    await expect(page.locator('select[name="reward_policy"]')).toHaveValue('one_for_one');
    await expect(page.locator('select[name="normalization_mode"]')).toHaveValue('trim');
  });

  test('7. Ca duyệt đóng góp: Tạo fixture riêng, bắt buộc tìm thấy form thẩm định, lưu duyệt và xác nhận MariaDB', async ({ page, context }) => {
    // 1. Tạo fixture riêng biệt cho ca thẩm định (run_id = 999888)
    runDbQuery("DELETE FROM testcase_contributions WHERE run_id = 999888");
    runDbQuery(`INSERT INTO testcase_contributions 
      (run_id, course_id, quiz_id, question_id, user_id, input_normalized, input_fingerprint, oracle_output, purpose, status, rating, review_comment) 
      VALUES 
      (999888, 2, 76, 794, 3, 'fixture_test_input_42', 'fingerprint_fixture_test_42', 'fixture_oracle_output', 'Kiểm thử biên tự động fixture', 'submitted', 0, '')`);
    
    const fixtureRows = runDbQuery("SELECT id FROM testcase_contributions WHERE run_id = 999888");
    expect(fixtureRows.length).toBeGreaterThan(0);
    const fixtureId = fixtureRows[0].id;

    try {
      // 2. Đăng nhập giáo viên và truy cập thẳng tab Review của Quiz 76, Question 794
      await loginUser(page, context, 'teacher1', 'TeacherPassword123!');
      await page.goto('/local/testcase_exchange/index.php?course=2&quiz=76&question=76%3A794&tab=review');
      await page.waitForLoadState('networkidle');

      // 3. BẮT BUỘC tìm thấy form thẩm định của fixture (KHÔNG dùng if isVisible)
      const reviewForm = page.locator(`form:has(input[name="contribution_id"][value="${fixtureId}"])`);
      await expect(reviewForm).toBeVisible();

      // 4. Thực hiện thao tác duyệt (Approve + 5 sao + Review note)
      await reviewForm.locator('select[name="decision"]').selectOption('approved');
      await reviewForm.locator('select[name="rating"]').selectOption('5');
      await reviewForm.locator('input[name="review_note"]').fill('E2E Dedicated Fixture Review Passed');
      await Promise.all([
        page.waitForNavigation(),
        reviewForm.locator('button[type="submit"]:has-text("Lưu Đánh Giá")').click()
      ]);
      await page.waitForLoadState('networkidle');

      // 5. Xác nhận thông báo thành công trên giao diện
      await expect(page.locator('.alert-success, .notifysuccess')).toBeVisible();
      await expect(page.locator('.alert-success, .notifysuccess')).toContainText(/Đã lưu quyết định duyệt|review decision was saved/i);

      // 6. Truy vấn trực tiếp MariaDB xác nhận dữ liệu đã lưu chính xác
      const savedRow = runDbQuery(`SELECT status, rating, review_comment FROM testcase_contributions WHERE id = ${fixtureId}`)[0];
      expect(savedRow.status).toBe('approved');
      expect(Number(savedRow.rating)).toBe(5);
      expect(savedRow.review_comment).toBe('E2E Dedicated Fixture Review Passed');
    } finally {
      // Dọn dẹp fixture sau khi kiểm thử xong
      runDbQuery("DELETE FROM testcase_contributions WHERE run_id = 999888");
    }
  });

  test('8. Chế độ Xem thử: Gửi POST run trực tiếp với preview=1 bị chặn, nút UI vô hiệu hóa & không gây ô nhiễm số liệu sinh viên', async ({ page, context }) => {
    // 1. Vào Tab Overview đo số lượt chạy ban đầu của sinh viên
    await loginUser(page, context, 'teacher1', 'TeacherPassword123!');
    await page.goto('/local/testcase_exchange/index.php?course=2&tab=overview');
    await page.waitForLoadState('networkidle');
    const runsText = await page.locator('#tab_overview').innerText();

    // 2. Chuyển sang Chế độ xem thử Preview
    await page.goto('/local/testcase_exchange/index.php?course=2&preview=1');
    await page.waitForLoadState('networkidle');

    // 3. Kiểm tra trên UI: nút chạy bị vô hiệu hóa và có thông báo bảo vệ dữ liệu
    const runBtn = page.locator('button[type="submit"]:has-text("Chạy Kiểm Tra Vi Sai")');
    await expect(runBtn).toBeDisabled();
    await expect(page.locator('text=/Chế độ xem thử.*Nút chạy bị vô hiệu hóa/i')).toBeVisible();

    // 4. GỬI TRỰC TIẾP POST action=run khi preview=1 với sesskey hợp lệ (Bỏ qua UI disabled button)
    const sesskey = await page.evaluate(() => (window['M'] && window['M'].cfg) ? window['M'].cfg.sesskey : '');
    expect(sesskey).toBeTruthy();

    const runsCountBefore = Number(runDbQuery("SELECT COUNT(*) as cnt FROM testcase_runs")[0].cnt);

    const resDirectRun = await page.request.post('http://localhost:8080/local/testcase_exchange/index.php?course=2&preview=1', {
      form: {
        sesskey: sesskey,
        action: 'run',
        question: '76:794',
        student_code: 'def find_max(a, b): return a if a > b else b',
        test_input: '10 20',
        predicted_output: '20'
      }
    });

    // Xác nhận phản hồi chứa thông báo vô hiệu hóa lượt chạy trong preview mode
    const postRunText = await resDirectRun.text();
    expect(postRunText).toMatch(/previewmode_run_disabled_notice|Chế độ xem thử.*bị vô hiệu hóa|Differential test execution is disabled in preview mode/i);

    // Xác nhận trong MariaDB: Số lượng runs KHÔNG đổi (hoàn toàn không tạo lượt chạy mới)
    const runsCountAfter = Number(runDbQuery("SELECT COUNT(*) as cnt FROM testcase_runs")[0].cnt);
    expect(runsCountAfter).toBe(runsCountBefore);

    // 5. Quay lại trang Overview xác nhận số liệu thống kê sinh viên không đổi
    await page.goto('/local/testcase_exchange/index.php?course=2&tab=overview');
    await page.waitForLoadState('networkidle');
    const runsTextAfter = await page.locator('#tab_overview').innerText();
    expect(runsTextAfter).toEqual(runsText);

    // 6. Tải file xuất timeline sau khi xem thử preview và xác minh cách ly tuyệt đối
    const exportRes = await page.request.get('http://localhost:8080/local/testcase_exchange/export.php?course=2&quiz=76&format=csv');
    expect(exportRes.status()).toBe(200);
    const csvContent = await exportRes.text();
    expect(csvContent).toContain('event_time,username,fullname');
    // Tuyệt đối không chứa tài khoản teacher1 hoặc admin trong file timeline xuất của lớp
    expect(csvContent).not.toContain(',teacher1,');
    expect(csvContent).not.toContain(',admin,');
  });

});
