// @ts-check

const { test, expect } = require('@playwright/test');
const { execFileSync } = require('child_process');
const {
  DEMO,
  openQuizFromDashboard,
  openStudentWorkspaceFromDashboard,
  openTeacherWorkspaceFromDashboard,
  setAceValue,
} = require('./demo_navigation');

const CORRECT_PRIME_CODE = `def is_prime(n):
    if n <= 1:
        return False
    for i in range(2, int(n ** 0.5) + 1):
        if n % i == 0:
            return False
    return True`;

async function login(page, username, password) {
  await page.goto('/login/index.php');
  await page.fill('#username', username);
  await page.fill('#password', password);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
    page.locator('form#login').evaluate((form) => form.submit()),
  ]);
  await expect(page).not.toHaveURL(/\/login\//);
}

test.beforeAll(() => {
  try {
    execFileSync('docker', [
      'exec', 'moodle_app', 'php',
      '/var/www/html/local/testcase_exchange/cli/ensure_demo_users.php',
    ], { stdio: 'pipe' });
  } catch (err) {
    // Docker CLI might be restricted in sandbox; continue if users already prepared.
  }
});

test('giảng viên đi từ Dashboard vào khóa học, Quiz và Testcase Exchange', async ({ page }) => {
  await login(page, 'teacher1', 'TeacherPassword123!');
  await openTeacherWorkspaceFromDashboard(page);

  await expect(page).toHaveURL(new RegExp(`/local/testcase_exchange/index\\.php\\?course=${DEMO.courseId}`));
  await expect(page.locator('a[href="#tab_policy"]')).toBeVisible();
  await expect(page.locator('a[href="#tab_review"]')).toBeVisible();
});

test('sinh viên đi từ Dashboard vào Quiz, Check bài rồi mở đúng câu hỏi', async ({ page }) => {
  await login(page, 'student3', 'StudentPassword123!');
  await openStudentWorkspaceFromDashboard(page, CORRECT_PRIME_CODE);

  await expect(page).toHaveURL(/local\/testcase_exchange\/index\.php/);
  await expect(page).toHaveURL(new RegExp(`question=${DEMO.quizId}(?:%3A|:)${DEMO.questionId}`));
  await expect(page.locator('#student_code_ace')).toBeVisible();
  await expect(page.locator('#btnSubmitRunTest')).toBeVisible();
});

test('chạy testcase tự cuộn và focus vào kết quả mới nhất', async ({ page }) => {
  await login(page, 'student3', 'StudentPassword123!');
  await openStudentWorkspaceFromDashboard(page, CORRECT_PRIME_CODE);

  await setAceValue(page.locator('#student_code_ace'), CORRECT_PRIME_CODE);
  await page.locator('#test_input').fill('print(is_prime(1013))');
  await page.locator('#predicted_output').fill('True');
  await page.locator('#category').selectOption('boundary');
  await page.locator('#purpose').fill('Kiểm tra tự động hành vi cuộn tới kết quả mới nhất.');

  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 120000 }),
    page.locator('#btnSubmitRunTest').click(),
  ]);
  await page.locator('#tceLatestResultCard').waitFor({ state: 'visible' });
  await page.waitForTimeout(700);

  await expect(page).toHaveURL(/#tceLatestResultCard$/);
  await expect(page.locator('#tceLatestResultCard')).toBeFocused();
  const top = await page.locator('#tceLatestResultCard').evaluate((element) => element.getBoundingClientRect().top);
  expect(top).toBeGreaterThanOrEqual(0);
  expect(top).toBeLessThan(180);
});
