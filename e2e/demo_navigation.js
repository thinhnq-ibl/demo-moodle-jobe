// @ts-check

const DEMO = Object.freeze({
  courseId: 3,
  courseName: "Lập trình Cơ bản & Đóng góp Testcase",
  quizId: 2,
  quizCmId: 5,
  quizName: "Mini Quiz: Kiểm thử tự động",
  questionId: 119,
  questionName: "Bài 01: Kiểm tra Số Nguyên Tố (Prime Number)",
  questionValue: "2:119",
});

async function waitForPage(page) {
  await page.waitForLoadState("domcontentloaded");
  await page.waitForLoadState("networkidle").catch(() => {});

  // Fresh demo accounts may receive Moodle/FlexiTour overlays that intentionally
  // block the navigation bar until the user acknowledges them.
  const tourButton = page.getByRole('button', {
    name: /Got it|Đã hiểu|Tôi đã hiểu|Skip tour|Bỏ qua hướng dẫn/i,
  }).first();
  if (await tourButton.count() && await tourButton.isVisible()) {
    await tourButton.click();
    await page.waitForTimeout(250);
  }
}

async function clickAndWait(page, locator, options = {}) {
  await locator.scrollIntoViewIfNeeded().catch(() => {});
  await Promise.all([
    page.waitForNavigation({ waitUntil: "domcontentloaded", timeout: 120000 }),
    locator.click({ force: true, ...options }),
  ]);
  await waitForPage(page);
}

async function openCourseFromDashboard(page, highlight = async () => {}) {
  await page.goto("/my/");
  await waitForPage(page);

  const myCourses = page.locator('a[href*="/my/courses.php"]').first();
  await highlight(myCourses, 2000);
  await clickAndWait(page, myCourses);

  const course = page.locator(`a[href*="/course/view.php?id=${DEMO.courseId}"]`).first();
  await highlight(course, 2000);
  await clickAndWait(page, course);
}

async function openQuizFromCourse(page, highlight = async () => {}) {
  const quiz = page.locator(`main a[href*="/mod/quiz/view.php?id=${DEMO.quizCmId}"], #region-main a[href*="/mod/quiz/view.php?id=${DEMO.quizCmId}"], .activity a[href*="/mod/quiz/view.php?id=${DEMO.quizCmId}"]`).first();
  await highlight(quiz, 2000);
  await clickAndWait(page, quiz);
}

async function openQuizFromDashboard(page, highlight = async () => {}) {
  await openCourseFromDashboard(page, highlight);
  await openQuizFromCourse(page, highlight);
}

async function startOrContinueAttempt(page, highlight = async () => {}) {
  const attemptButton = page.getByRole('button', {
    name: /Attempt quiz|Continue your attempt|Start attempt|Re-attempt quiz|Tiếp tục làm bài|Bắt đầu làm bài|Làm lại bài thi/i,
  }).first();

  if (!(await attemptButton.count())) {
    throw new Error("Không tìm thấy nút bắt đầu/tiếp tục lượt làm Quiz trên trang hiện tại.");
  }

  await highlight(attemptButton, 2000);
  await clickAndWait(page, attemptButton);

  const confirm = page.locator('.modal.show').getByRole('button', {
    name: /Start attempt|Bắt đầu làm bài/i,
  }).first();
  if (await confirm.count() && await confirm.isVisible()) {
    await clickAndWait(page, confirm);
  }
}

async function setAceValue(editor, value) {
  await editor.scrollIntoViewIfNeeded();
  await editor.click();
  await editor.evaluate((element, text) => {
    const aceApi = window.ace;
    if (aceApi) {
      try {
        const instance = aceApi.edit(element);
        if (instance) {
          instance.getSession().setValue(text);
          instance.setValue(text, -1);
          instance.clearSelection();
          instance.focus();
        }
      } catch (e) {}
    }
    const form = element.closest('form') || element.parentElement;
    const textarea = form?.querySelector('textarea[name*="answer"], textarea#student_code, textarea');
    if (textarea) {
      textarea.value = text;
      textarea.dispatchEvent(new Event('input', { bubbles: true }));
      textarea.dispatchEvent(new Event('change', { bubbles: true }));
    }
  }, value);
}

async function submitPrimeAnswer(page, code, highlight = async () => {}) {
  const question = page.locator('.que').filter({ hasText: /is_prime/ }).first();
  await question.waitFor({ state: 'visible', timeout: 30000 });

  const editor = question.locator('.ace_editor').first();
  await setAceValue(editor, code);

  const checkButton = question.locator('button[type="submit"], input[type="submit"]').filter({
    hasText: /Check|Kiểm tra/i,
  }).first();
  await highlight(checkButton, 2000);
  await clickAndWait(page, checkButton);
}

async function openProgrammingQuestion(page, highlight = async () => {}) {
  const question = page.locator('.que').filter({ hasText: /is_prime|prime/i }).first();
  await question.waitFor({ state: 'visible', timeout: 30000 });
  await question.scrollIntoViewIfNeeded();
  await highlight(question, 2000);
  return question;
}

async function openTestcaseExchangeFromQuestion(page, highlight = async () => {}) {
  const contributionLink = page.locator('a[href*="/local/testcase_exchange/index.php"][href*="question="]').first();
  await contributionLink.waitFor({ state: 'visible', timeout: 30000 });
  await contributionLink.scrollIntoViewIfNeeded();
  await highlight(contributionLink, 2000);
  await clickAndWait(page, contributionLink);
}

async function openTeacherHubFromQuiz(page, highlight = async () => {}) {
  const hubLink = page.locator('a[href*="/local/testcase_exchange/index.php"]').first();
  await hubLink.waitFor({ state: 'visible', timeout: 30000 });
  await hubLink.scrollIntoViewIfNeeded();
  await highlight(hubLink, 2000);
  await clickAndWait(page, hubLink);
}

async function openStudentWorkspaceFromAttempt(page, highlight = async () => {}) {
  await openTestcaseExchangeFromQuestion(page, highlight);
}

async function openStudentWorkspaceFromDashboard(page, code, highlight = async () => {}) {
  await openQuizFromDashboard(page, highlight);
  await startOrContinueAttempt(page, highlight);
  await submitPrimeAnswer(page, code, highlight);
  await openTestcaseExchangeFromQuestion(page, highlight);
}

async function openTeacherWorkspaceFromDashboard(page, highlight = async () => {}) {
  await openQuizFromDashboard(page, highlight);
  await openTeacherHubFromQuiz(page, highlight);
}

module.exports = {
  DEMO,
  clickAndWait,
  openCourseFromDashboard,
  openQuizFromCourse,
  openQuizFromDashboard,
  startOrContinueAttempt,
  openProgrammingQuestion,
  openTestcaseExchangeFromQuestion,
  openTeacherHubFromQuiz,
  openStudentWorkspaceFromDashboard,
  openTeacherWorkspaceFromDashboard,
  setAceValue,
  submitPrimeAnswer,
  waitForPage,
};
