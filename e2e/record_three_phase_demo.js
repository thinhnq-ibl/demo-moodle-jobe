// @ts-check

const { chromium } = require('@playwright/test');
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
const {
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
} = require('./demo_navigation');

const BASE_URL = process.env.DEMO_BASE_URL || 'http://localhost:8080';
const RECORDINGS_DIR = path.resolve(__dirname, 'recordings/three-phase-scenes');
const EVIDENCE_DIR = path.resolve(__dirname, '../verification_evidence');
const CORRECT_PRIME_CODE = `def is_prime(n):
    if n <= 1:
        return False
    for i in range(2, int(n ** 0.5) + 1):
        if n % i == 0:
            return False
    return True`;
const BUGGY_PRIME_CODE = `def is_prime(n):
    for i in range(2, abs(n)):
        if n % i == 0:
            return False
    return True`;

fs.mkdirSync(RECORDINGS_DIR, { recursive: true });
fs.mkdirSync(EVIDENCE_DIR, { recursive: true });

const sceneRecords = [];

async function showCaption(page, badge, title, subtitle) {
  await page.evaluate(({ badge, title, subtitle }) => {
    let box = document.getElementById('pw-demo-caption');
    if (!box) {
      box = document.createElement('div');
      box.id = 'pw-demo-caption';
      box.style.cssText = [
        'position:fixed', 'bottom:24px', 'left:50%', 'transform:translateX(-50%)',
        'z-index:9999999', 'width:90%', 'max-width:1100px',
        'background:rgba(15,23,42,.94)', 'color:#f8fafc', 'padding:14px 26px',
        'border-radius:14px', 'box-shadow:0 16px 40px rgba(0,0,0,.55)',
        'border-left:6px solid #38bdf8',
        'font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif',
        'pointer-events:none',
      ].join(';');
      document.body.appendChild(box);
    }
    box.innerHTML = `
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:5px">
        <span style="background:#0284c7;color:#fff;font-weight:800;font-size:12px;padding:3px 12px;border-radius:999px;letter-spacing:.8px">${badge}</span>
        <span style="font-size:17px;font-weight:700">${title}</span>
      </div>
      <div style="font-size:15px;color:#cbd5e1;line-height:1.45">${subtitle}</div>`;
  }, { badge, title, subtitle });
}

async function highlight(locator, pauseMs = 1200) {
  if (!(await locator.count()) || !(await locator.first().isVisible())) return;
  await locator.first().scrollIntoViewIfNeeded();
  await locator.first().evaluate((el) => {
    el.dataset.demoOutline = el.style.outline || '';
    el.dataset.demoShadow = el.style.boxShadow || '';
    el.style.outline = '4px solid #f59e0b';
    el.style.boxShadow = '0 0 0 8px rgba(245,158,11,.35)';
  });
  await locator.page().waitForTimeout(pauseMs);
  await locator.first().evaluate((el) => {
    el.style.outline = el.dataset.demoOutline || '';
    el.style.boxShadow = el.dataset.demoShadow || '';
  }).catch(() => {});
}

async function injectVideoStyles(page) {
  await page.addStyleTag({ content: `
    body { font-size: 16px !important; }
    pre, code, textarea, input, select { font-size: 15px !important; }
    .table td, .table th { padding: .65rem !important; }
  ` });
}

function dockerPhp(scriptPath) {
  try {
    execFileSync('docker', ['exec', 'moodle_app', 'php', scriptPath], { stdio: 'inherit' });
  } catch (err) {
    console.warn('dockerPhp notice: Docker command skipped or failed, using existing DB fixtures.');
  }
}

function moodleSql(sql) {
  try {
    execFileSync('docker', [
      'exec', 'moodle_mariadb', 'mariadb', '-uroot', '-prootpassword', 'moodle', '-e', sql,
    ], { stdio: 'inherit' });
  } catch (err) {
    console.warn('moodleSql notice: Docker SQL command skipped or failed, using existing DB fixtures.');
  }
}

function prepareFixtures() {
  dockerPhp('/var/www/html/local/testcase_exchange/cli/ensure_demo_users.php');
  moodleSql(`
    UPDATE mdl_local_tce_quiz_settings
       SET is_enabled=1, enabled=1, review_mode='teacher', reward_policy='one_for_one',
           leaderboard_enabled=1, max_runs_per_minute=30
     WHERE quiz_id=${DEMO.quizId};
    DELETE FROM mdl_local_tce_rewards
     WHERE quiz_id=${DEMO.quizId} AND question_id=${DEMO.questionId}
       AND receiver_user_id IN (SELECT id FROM mdl_user WHERE username IN ('student1','student2','student3'));
    DELETE FROM mdl_local_tce_canonical
     WHERE quiz_id=${DEMO.quizId} AND question_id=${DEMO.questionId};
    DELETE FROM mdl_local_tce_contributions
     WHERE quiz_id=${DEMO.quizId} AND question_id=${DEMO.questionId}
       AND user_id IN (SELECT id FROM mdl_user WHERE username IN ('student1','student2','student3'));
    DELETE FROM mdl_local_tce_runs
     WHERE quiz_id=${DEMO.quizId} AND question_id=${DEMO.questionId}
       AND user_id IN (SELECT id FROM mdl_user WHERE username IN ('student1','student2','student3'));
    DELETE FROM mdl_local_tce_revisions
     WHERE quiz_id=${DEMO.quizId} AND question_id=${DEMO.questionId}
       AND user_id IN (SELECT id FROM mdl_user WHERE username IN ('student1','student2','student3'));
  `);
}

async function loginAndSave(browser, username, password, filename) {
  const context = await browser.newContext({ baseURL: BASE_URL });
  const page = await context.newPage();
  await page.goto('/login/index.php');
  await page.fill('#username', username);
  await page.fill('#password', password);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
    page.locator('form#login').evaluate((form) => form.submit()),
  ]);
  if (page.url().includes('/login/')) {
    throw new Error(`Đăng nhập thất bại cho tài khoản ${username}`);
  }
  await context.storageState({ path: path.resolve(__dirname, filename) });
  await context.close();
}

async function prepareAuthSessions(browser) {
  await loginAndSave(browser, 'teacher1', 'TeacherPassword123!', 'auth_teacher.json');
  await loginAndSave(browser, 'student1', 'StudentPassword123!', 'auth_studentA.json');
  await loginAndSave(browser, 'student2', 'StudentPassword123!', 'auth_studentB.json');
  await loginAndSave(browser, 'student3', 'StudentPassword123!', 'auth_studentC.json');
}

async function recordScene(browser, number, name, authFile, task) {
  const started = Date.now();
  const sceneDir = path.join(RECORDINGS_DIR, `scene_${String(number).padStart(2, '0')}`);
  fs.mkdirSync(sceneDir, { recursive: true });
  const context = await browser.newContext({
    baseURL: BASE_URL,
    storageState: path.resolve(__dirname, authFile),
    viewport: { width: 1920, height: 1080 },
    recordVideo: { dir: sceneDir, size: { width: 1920, height: 1080 } },
  });
  const page = await context.newPage();
  await task(page);
  const video = page.video();
  await page.close();
  await context.close();
  sceneRecords.push({ number, name, duration: (Date.now() - started) / 1000, path: await video.path() });
}

async function fillWorkspace(page, { code, input, expected, category, purpose }) {
  await page.evaluate(({ code, input, expected, category, purpose }) => {
    const aceDiv = document.getElementById('student_code_ace');
    if (window.ace && aceDiv) {
      try {
        const ed = window.ace.edit(aceDiv);
        if (ed) {
          ed.getSession().setValue(code);
          ed.setValue(code, -1);
          ed.clearSelection();
        }
      } catch (e) {}
    }
    const codeEl = document.getElementById('student_code');
    if (codeEl) codeEl.value = code;
    const inputEl = document.getElementById('test_input');
    if (inputEl) inputEl.value = input;
    const expEl = document.getElementById('predicted_output');
    if (expEl) expEl.value = expected;
    const catEl = document.getElementById('category');
    if (catEl) catEl.value = category;
    const purpEl = document.getElementById('purpose');
    if (purpEl) purpEl.value = purpose;
  }, { code, input, expected, category, purpose });
  await page.locator('#test_input').dispatchEvent('input');
  await page.locator('#predicted_output').dispatchEvent('input');
  await page.waitForTimeout(500);
}

async function runWorkspace(page) {
  const runButton = page.locator('#btnSubmitRunTest');
  await highlight(runButton, 1200);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 120000 }),
    runButton.click(),
  ]);
  await waitForPage(page);
  await page.locator('#tceLatestResultCard').waitFor({ state: 'visible', timeout: 30000 });
  await page.waitForTimeout(900);
}

async function contributeLatest(page) {
  const button = page.locator('#btnSubmitLatestContrib').first();
  await button.waitFor({ state: 'visible', timeout: 30000 });
  await highlight(button, 1100);
  await clickAndWait(page, button);
}

async function returnToQuiz(page) {
  const returnLink = page.locator('a').filter({ hasText: /Quay lại bài quiz|Back to quiz/i }).first();
  if (await returnLink.count() && await returnLink.isVisible()) {
    await highlight(returnLink, 1000);
    await clickAndWait(page, returnLink);
  } else {
    await page.goBack();
    await waitForPage(page);
  }
}

async function reviewContribution(page, username, decision, rating, note) {
  const row = page.locator('tr').filter({ hasText: username }).first();
  const openButton = row.locator('button[data-target^="#reviewModal_"]').first();
  await highlight(openButton, 900);
  await openButton.click();
  const modal = page.locator('.modal.show').first();
  await modal.waitFor({ state: 'visible' });
  await modal.locator('select[name="decision"]').selectOption(decision);
  if (rating) await modal.locator('select[name="rating"]').selectOption(String(rating));
  await modal.locator('textarea[name="review_note"]').fill(note);
  await clickAndWait(page, modal.locator('button[type="submit"]').first());
}

function formatSrtTime(totalSec) {
  const hrs = Math.floor(totalSec / 3600);
  const mins = Math.floor((totalSec % 3600) / 60);
  const secs = Math.floor(totalSec % 60);
  const millis = Math.floor((totalSec % 1) * 1000);
  return `${String(hrs).padStart(2, '0')}:${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')},${String(millis).padStart(3, '0')}`;
}

function findFfmpeg() {
  const localApp = process.env.LOCALAPPDATA || '';
  const candidates = [
    path.join(localApp, 'ms-playwright', 'ffmpeg-1013', 'ffmpeg-win64.exe'),
    'ffmpeg',
  ];
  for (const bin of candidates) {
    if (bin !== 'ffmpeg' && fs.existsSync(bin)) return bin;
  }
  return 'ffmpeg';
}

function postProcessVideo() {
  const concatPath = path.join(RECORDINGS_DIR, 'concat_list.txt');
  fs.writeFileSync(concatPath, sceneRecords.map((s) => `file '${s.path.replace(/'/g, "'\\''")}'`).join('\n'), 'utf8');
  const outputPath = path.join(EVIDENCE_DIR, 'demo_testcase_exchange_three_phases.mp4');
  const ffmpegBin = findFfmpeg();
  try {
    execFileSync(ffmpegBin, ['-y', '-f', 'concat', '-safe', '0', '-i', concatPath, '-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-r', '30', outputPath], { stdio: 'inherit' });
    console.log('Video hoàn chỉnh đã được xuất tại:', outputPath);
  } catch (err) {
    console.warn('Ghép video ffmpeg thất bại; các video cảnh riêng lẻ được lưu đầy đủ tại:', RECORDINGS_DIR);
  }

  const captions = [
    'Phase 1: Giảng viên đi từ Dashboard vào khóa học, Quiz và khu vực soạn câu hỏi CodeRunner.',
    'Phase 1: Giảng viên mở Testcase Exchange từ Quiz và cấu hình chính sách lớp học.',
    'Phase 2: Sinh viên 1 làm câu hỏi trong Quiz, mở workspace và gửi testcase hợp lệ.',
    'Phase 2: Sinh viên 2 dùng testcase để phát hiện lỗi logic, sửa code và chạy lại.',
    'Phase 2: Sinh viên 3 nhận cảnh báo testcase trùng rồi thiết kế một testcase mới.',
    'Phase 3: Giảng viên trở lại từ Dashboard, vào Quiz và duyệt đóng góp của ba sinh viên.',
    'Phase 3: Ngân hàng testcase và bảng xếp hạng phản ánh kết quả review.',
  ];
  let offset = 0;
  const items = sceneRecords.map((scene, index) => {
    const start = formatSrtTime(offset + 0.5);
    const end = formatSrtTime(offset + Math.max(1, scene.duration - 0.5));
    offset += scene.duration;
    return `${index + 1}\n${start} --> ${end}\n${captions[index]}\n`;
  });
  fs.writeFileSync(path.join(EVIDENCE_DIR, 'demo_testcase_exchange_three_phases.srt'), items.join('\n'), 'utf8');
}

(async () => {
  prepareFixtures();
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    await prepareAuthSessions(browser);

    await recordScene(browser, 1, 'Giảng viên vào khóa học và soạn đề', 'auth_teacher.json', async (page) => {
      await page.goto('/my/');
      await waitForPage(page);
      await showCaption(page, 'PHASE 1/3 • GIẢNG VIÊN', 'Bắt đầu từ Moodle Dashboard', 'Giảng viên đi theo đúng luồng Dashboard → My courses → Khóa học → Quiz.');
      await page.waitForTimeout(1600);
      await openQuizFromDashboard(page, highlight);
      await injectVideoStyles(page);
      await showCaption(page, 'PHASE 1/3 • SOẠN ĐỀ', DEMO.quizName, 'Mở phần chỉnh sửa Quiz để xem câu hỏi CodeRunner và cấu hình bài tập.');
      const questionBank = page.locator('a[href*="/question/edit.php"], a[href*="/mod/quiz/edit.php"]').filter({
        hasText: /Questions|Question bank|Ngân hàng câu hỏi|Chỉnh sửa đề thi/i,
      }).first();
      if (await questionBank.count()) {
        await highlight(questionBank, 1200);
        await clickAndWait(page, questionBank);
      }
      const questionRow = page.locator('a[href*="question.php"], .questionname, .qtype_coderunner').filter({
        hasText: /is_prime|Prime|Số Nguyên Tố/i,
      }).first();
      if (await questionRow.count() && await questionRow.isVisible()) {
        await highlight(questionRow, 1200);
      }
      await showCaption(page, 'PHASE 1/3 • SOẠN ĐỀ', 'Câu hỏi CodeRunner sẵn sàng', 'Câu hỏi đã có đề bài, lời giải tham chiếu Python và các testcase công khai.');
      await page.waitForTimeout(2500);
    });

    await recordScene(browser, 2, 'Giảng viên cấu hình Testcase Exchange', 'auth_teacher.json', async (page) => {
      await openCourseFromDashboard(page, highlight);
      await openQuizFromCourse(page, highlight);
      await openTeacherHubFromQuiz(page, highlight);
      await injectVideoStyles(page);
      const policyTab = page.locator('a[href="#tab_policy"]').first();
      await highlight(policyTab, 1000);
      await policyTab.click();
      await showCaption(page, 'PHASE 1/3 • SETUP', 'Cấu hình chính sách Testcase Exchange', 'Bật đóng góp, giảng viên duyệt, thưởng 1 đổi 1 và bảng xếp hạng cho Quiz.');
      await page.locator('select[name="review_mode"]').selectOption('teacher');
      await page.locator('select[name="reward_policy"]').selectOption('one_for_one');
      const leaderboard = page.locator('#policyLeaderboard');
      if (!(await leaderboard.isChecked())) await leaderboard.check();
      await page.waitForTimeout(1800);

      const seedTab = page.locator('a[href="#tab_seed"]').first();
      if (await seedTab.count() && await seedTab.isVisible()) {
        await highlight(seedTab, 1000);
        await seedTab.click();
        await page.waitForTimeout(1200);
      }

      // Quay lại trang khóa học để xác nhận Quiz đã sẵn sàng
      const courseBreadcrumb = page.locator('.breadcrumb a[href*="/course/view.php"]').first();
      if (await courseBreadcrumb.count()) {
        await highlight(courseBreadcrumb, 1000);
        await clickAndWait(page, courseBreadcrumb);
      }
      await showCaption(page, 'PHASE 1/3 • SẴN SÀNG', DEMO.courseName, 'Hoàn tất thiết lập; Quiz đã mở và sẵn sàng cho sinh viên làm bài.');
      await page.waitForTimeout(2200);
    });

    await recordScene(browser, 3, 'Sinh viên 1 chạy và đóng góp testcase', 'auth_studentA.json', async (page) => {
      await page.goto('/my/');
      await waitForPage(page);
      await showCaption(page, 'PHASE 2/3 • SINH VIÊN 1', 'Dashboard → Khóa học → Quiz → Bài tập', 'Sinh viên làm câu hỏi CodeRunner và mở Testcase Exchange trực tiếp từ đề bài.');
      await page.waitForTimeout(1500);
      await openCourseFromDashboard(page, highlight);
      await openQuizFromCourse(page, highlight);
      await startOrContinueAttempt(page, highlight);
      await openProgrammingQuestion(page, highlight);
      await submitPrimeAnswer(page, CORRECT_PRIME_CODE, highlight);
      await openTestcaseExchangeFromQuestion(page, highlight);
      await injectVideoStyles(page);
      await fillWorkspace(page, {
        code: CORRECT_PRIME_CODE, input: 'print(is_prime(997))', expected: 'True', category: 'normal',
        purpose: 'Kiểm tra số nguyên tố lớn không chia hết cho các số nhỏ.',
      });
      await runWorkspace(page);
      await showCaption(page, 'PHASE 2/3 • SINH VIÊN 1', 'Kết quả chạy thử mới nhất', 'Trang tự cuộn xuống card kết quả; testcase hợp lệ có thể gửi đóng góp.');
      await page.waitForTimeout(1800);
      await contributeLatest(page);
      await returnToQuiz(page);
      await showCaption(page, 'PHASE 2/3 • SINH VIÊN 1', 'Quay lại Quiz', 'Sinh viên trở về lượt làm bài để tiếp tục hoàn thành Quiz.');
      await page.waitForTimeout(1800);
    });

    await recordScene(browser, 4, 'Sinh viên 2 phát hiện và sửa lỗi', 'auth_studentB.json', async (page) => {
      await page.goto('/my/');
      await waitForPage(page);
      await showCaption(page, 'PHASE 2/3 • SINH VIÊN 2', 'Đi vào bài tập như người học thật', 'Sinh viên 2 mở Quiz, làm câu hỏi rồi dùng testcase số âm để phát hiện lỗi logic.');
      await page.waitForTimeout(1500);
      await openCourseFromDashboard(page, highlight);
      await openQuizFromCourse(page, highlight);
      await startOrContinueAttempt(page, highlight);
      await openProgrammingQuestion(page, highlight);
      await submitPrimeAnswer(page, BUGGY_PRIME_CODE, highlight);
      await openTestcaseExchangeFromQuestion(page, highlight);
      await fillWorkspace(page, {
        code: BUGGY_PRIME_CODE, input: 'print(is_prime(-7))', expected: 'False', category: 'boundary',
        purpose: 'Kiểm tra số âm; thuật toán thiếu điều kiện n <= 1 nên nhận nhầm số âm là số nguyên tố.',
      });
      await runWorkspace(page);
      await showCaption(page, 'PHASE 2/3 • SINH VIÊN 2', 'Phát hiện lỗi logic từ ca số âm', 'Expected (False) và Oracle (False) khớp nhau; code sinh viên trả về True do thiếu kiểm tra n <= 1.');
      await page.waitForTimeout(2200);
      await fillWorkspace(page, {
        code: CORRECT_PRIME_CODE, input: 'print(is_prime(-7))', expected: 'False', category: 'boundary',
        purpose: 'Kiểm tra số âm; sau khi bổ sung điều kiện n <= 1 chương trình trả kết quả chính xác.',
      });
      await runWorkspace(page);
      await showCaption(page, 'PHASE 2/3 • SINH VIÊN 2', 'Đã sửa code và vượt qua kiểm thử', 'Kết quả chạy thử đạt chuẩn; sinh viên gửi đóng góp và quay lại Quiz.');
      await page.waitForTimeout(1800);
      await contributeLatest(page);
      await returnToQuiz(page);
      await page.waitForTimeout(1600);
    });

    await recordScene(browser, 5, 'Sinh viên 3 xử lý testcase trùng', 'auth_studentC.json', async (page) => {
      await page.goto('/my/');
      await waitForPage(page);
      await showCaption(page, 'PHASE 2/3 • SINH VIÊN 3', 'Đi từ Dashboard vào đúng câu hỏi', 'Sinh viên 3 thử một testcase đã có rồi thiết kế testcase mới.');
      await page.waitForTimeout(1500);
      await openCourseFromDashboard(page, highlight);
      await openQuizFromCourse(page, highlight);
      await startOrContinueAttempt(page, highlight);
      await openProgrammingQuestion(page, highlight);
      await submitPrimeAnswer(page, CORRECT_PRIME_CODE, highlight);
      await openTestcaseExchangeFromQuestion(page, highlight);
      await fillWorkspace(page, {
        code: CORRECT_PRIME_CODE, input: 'print(is_prime(997))', expected: 'True', category: 'normal',
        purpose: 'Kiểm tra lại một số nguyên tố lớn.',
      });
      await runWorkspace(page);
      await showCaption(page, 'PHASE 2/3 • SINH VIÊN 3', 'Hệ thống phát hiện testcase trùng', 'Không thể gửi lại testcase của sinh viên 1; sinh viên chuyển sang ca kiểm thử biên mới.');
      await page.waitForTimeout(2000);
      await fillWorkspace(page, {
        code: CORRECT_PRIME_CODE, input: 'print(is_prime(1009))', expected: 'True', category: 'boundary',
        purpose: 'Kiểm tra số nguyên tố ngay trên ngưỡng một nghìn.',
      });
      await runWorkspace(page);
      await showCaption(page, 'PHASE 2/3 • SINH VIÊN 3', 'Testcase biên mới đạt yêu cầu', 'Ca kiểm thử biên 1009 hợp lệ và độc lập; sinh viên gửi đóng góp.');
      await page.waitForTimeout(1800);
      await contributeLatest(page);
      await returnToQuiz(page);
      await page.waitForTimeout(1600);
    });

    await recordScene(browser, 6, 'Giảng viên review ba đóng góp', 'auth_teacher.json', async (page) => {
      await page.goto('/my/');
      await waitForPage(page);
      await showCaption(page, 'PHASE 3/3 • REVIEW', 'Giảng viên trở lại từ Dashboard', 'Dashboard → Khóa học → Quiz → Testcase Exchange Hub → Duyệt đóng góp.');
      await page.waitForTimeout(1500);
      await openCourseFromDashboard(page, highlight);
      await openQuizFromCourse(page, highlight);
      await openTeacherHubFromQuiz(page, highlight);
      const reviewTab = page.locator('a[href="#tab_review"]').first();
      await highlight(reviewTab, 1000);
      await reviewTab.click();
      await reviewContribution(page, 'student1', 'approved', 5, 'Testcase rõ ràng, hợp lệ và có mục đích kiểm thử tốt.');
      await reviewContribution(page, 'student2', 'approved', 4, 'Ca kiểm thử số âm (-7) phát hiện đúng lỗi biên n <= 1.');
      await reviewContribution(page, 'student3', 'needs_explanation', 0, 'Ca kiểm thử biên 1009 hợp lệ; hãy bổ sung thêm giải trình.');
      await showCaption(page, 'PHASE 3/3 • REVIEW', 'Đã phản hồi cho ba sinh viên', 'Hai testcase được duyệt tặng sao và một testcase được yêu cầu giải trình.');
      await page.waitForTimeout(2600);
    });

    await recordScene(browser, 7, 'Ngân hàng và bảng xếp hạng', 'auth_teacher.json', async (page) => {
      await openCourseFromDashboard(page, highlight);
      await openQuizFromCourse(page, highlight);
      await openTeacherHubFromQuiz(page, highlight);
      const bankTab = page.locator('a[href="#tab_bank"]').first();
      await highlight(bankTab, 1000);
      await bankTab.click();
      await showCaption(page, 'PHASE 3/3 • KẾT QUẢ', 'Ngân hàng testcase đã được cập nhật', 'Các đóng góp được duyệt trở thành nguồn testcase dùng chung của lớp học.');
      await page.waitForTimeout(2200);
      const leaderboardTab = page.locator('a[href="#tab_leaderboard"]').first();
      if (await leaderboardTab.count() && await leaderboardTab.isVisible()) {
        await highlight(leaderboardTab, 1000);
        await leaderboardTab.click();
      }
      await showCaption(page, 'HOÀN TẤT', 'Luồng demo ba phase hoàn chỉnh', 'Từ soạn đề, ba sinh viên tương tác đến giảng viên review đều đi qua luồng Moodle thật.');
      await page.waitForTimeout(3000);
    });
  } finally {
    await browser.close();
  }

  if (process.env.DEMO_SKIP_VIDEO_POST !== '1') postProcessVideo();
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
