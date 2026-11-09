// @ts-check
const { test, expect } = require('@playwright/test');

async function login(page, context, username, password) {
  await context.clearCookies();
  await page.goto('/login/index.php');
  await page.fill('#username', username);
  await page.fill('#password', password);
  await Promise.all([
    page.waitForNavigation(),
    page.locator('form#login').evaluate((form) => form.submit()),
  ]);
  await page.waitForLoadState('networkidle');
}

test.describe('Simplified testcase exchange journeys', () => {
  test('student has three task-oriented destinations', async ({ page, context }) => {
    await login(page, context, 'student1', 'StudentPassword123!');
    await page.goto('/local/testcase_exchange/index.php?course=2');
    await page.waitForLoadState('networkidle');

    await expect(page.getByRole('heading', { name: /Testcase experimentation and exchange/i })).toBeVisible();
    const studentTabs = page.locator('.nav-tabs a[href="#student_solve"], .nav-tabs a[href="#contributions"], .nav-tabs a[href="#seed_search"]');
    await expect(studentTabs).toHaveCount(3);
    await expect(page.locator('.nav-tabs a[href="#rewards"]')).toHaveCount(0);
    await expect(page.locator('.nav-tabs a[href="#leaderboard"]')).toHaveCount(0);

    await page.locator('.nav-tabs a[href="#contributions"]').click();
    await expect(page.locator('#rewards')).toBeVisible();

    await page.locator('.nav-tabs a[href="#seed_search"]').click();
    await expect(page.locator('#leaderboard')).toBeVisible();
  });

  test('teacher sees the review action before secondary tools', async ({ page, context }) => {
    await login(page, context, 'teacher1', 'TeacherPassword123!');
    await page.goto('/local/testcase_exchange/index.php?course=3');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('.local-testcase-exchange-root h3')).toBeVisible();
    await expect(page.locator('.tce-next-action-card')).toBeVisible();
    await page.locator('.tce-next-action-card a[href="#tab_review"]').click();
    await expect(page.locator('#tab_review')).toBeVisible();
  });
});
