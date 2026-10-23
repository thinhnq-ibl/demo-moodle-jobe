// @ts-check

/**
 * Hiển thị thanh phụ đề (HUD Caption) nổi ở cuối màn hình để người xem video dễ theo dõi.
 * @param {import('@playwright/test').Page} page
 * @param {string} stepBadge Ví dụ: "GIÁO VIÊN • BƯỚC 1/5"
 * @param {string} title Tiêu đề chính của bước
 * @param {string} subtitle Giải thích chi tiết nghiệp vụ
 */
async function showCaption(page, stepBadge, title, subtitle) {
  await page.evaluate(({ stepBadge, title, subtitle }) => {
    let box = document.getElementById('pw-tutorial-caption');
    if (!box) {
      box = document.createElement('div');
      box.id = 'pw-tutorial-caption';
      box.style.cssText = [
        'position: fixed',
        'bottom: 20px',
        'left: 50%',
        'transform: translateX(-50%)',
        'z-index: 999999',
        'width: 86%',
        'max-width: 980px',
        'background: rgba(15, 23, 42, 0.92)',
        'color: #f8fafc',
        'padding: 14px 22px',
        'border-radius: 12px',
        'box-shadow: 0 10px 30px rgba(0,0,0,0.45)',
        'border-left: 6px solid #38bdf8',
        'font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
        'pointer-events: none',
        'transition: all 0.25s ease',
      ].join(';');
      document.body.appendChild(box);
    }
    box.innerHTML = `
      <div style="display:flex; align-items:center; gap:10px; margin-bottom:4px;">
        <span style="background:#0284c7; color:#fff; font-weight:700; font-size:12px; padding:3px 10px; border-radius:999px; letter-spacing:0.5px;">
          ${stepBadge}
        </span>
        <span style="font-size:17px; font-weight:700; color:#fff;">
          ${title}
        </span>
      </div>
      <div style="font-size:14px; color:#cbd5e1; line-height:1.45;">
        ${subtitle}
      </div>
    `;
  }, { stepBadge, title, subtitle });
}

/**
 * Tô sáng phần tử trên UI trước khi tương tác để người xem nhìn rõ vị trí thao tác.
 * @param {import('@playwright/test').Locator} locator
 * @param {number} pauseMs
 */
async function highlight(locator, pauseMs = 1200) {
  try {
    if (await locator.isVisible()) {
      await locator.scrollIntoViewIfNeeded();
      await locator.evaluate((el) => {
        const htmlEl = /** @type {HTMLElement} */ (el);
        htmlEl.dataset.origOutline = htmlEl.style.outline || '';
        htmlEl.dataset.origBoxShadow = htmlEl.style.boxShadow || '';
        htmlEl.style.outline = '3px solid #f59e0b';
        htmlEl.style.boxShadow = '0 0 0 6px rgba(245, 158, 11, 0.35)';
        htmlEl.style.transition = 'all 0.2s ease';
      });
      await locator.page().waitForTimeout(pauseMs);
      await locator.evaluate((el) => {
        const htmlEl = /** @type {HTMLElement} */ (el);
        htmlEl.style.outline = htmlEl.dataset.origOutline || '';
        htmlEl.style.boxShadow = htmlEl.dataset.origBoxShadow || '';
      });
    }
  } catch (e) {
    // Ignore if element detached during navigation
  }
}

/**
 * Đăng nhập chậm rãi kèm phụ đề hướng dẫn.
 * @param {import('@playwright/test').Page} page
 * @param {import('@playwright/test').BrowserContext} context
 * @param {string} username
 * @param {string} password
 * @param {string} roleBadge
 * @param {string} roleTitle
 */
async function slowLogin(page, context, username, password, roleBadge, roleTitle) {
  await context.clearCookies();
  await page.goto('/login/index.php');
  await page.waitForLoadState('networkidle');
  await showCaption(
    page,
    roleBadge,
    roleTitle,
    `Đăng nhập vào Moodle với tài khoản "${username}" để bắt đầu quy trình.`
  );
  await page.waitForTimeout(1500);

  const userLoc = page.locator('#username');
  await highlight(userLoc, 800);
  await userLoc.fill(username);
  await page.waitForTimeout(800);

  const passLoc = page.locator('#password');
  await highlight(passLoc, 800);
  await passLoc.fill(password);
  await page.waitForTimeout(1000);

  await Promise.all([
    page.waitForNavigation(),
    page.evaluate(() => {
      const form = /** @type {HTMLFormElement} */ (document.querySelector('form#login'));
      if (form) form.submit();
    })
  ]);
  await page.waitForLoadState('networkidle');
}

module.exports = {
  showCaption,
  highlight,
  slowLogin,
};
