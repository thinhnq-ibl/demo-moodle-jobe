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
  submitPrimeAnswer,
  waitForPage,
} = require('./demo_navigation');

const BASE_URL = process.env.DEMO_BASE_URL || 'http://localhost:8080';
const RECORDINGS_DIR = path.resolve(__dirname, 'recordings/single-student-scenes');
const EVIDENCE_DIR = path.resolve(__dirname, '../verification_evidence');
const OUTPUT_VIDEO = path.join(EVIDENCE_DIR, 'demo_testcase_exchange_single_student.mp4');

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

async function highlight(locator, pauseMs = 1500) {
  if (!(await locator.count()) || !(await locator.first().isVisible())) return;
  await locator.first().scrollIntoViewIfNeeded();
  await locator.first().evaluate((el) => {
    el.dataset.demoOutline = el.style.outline || '';
    el.dataset.demoShadow = el.style.boxShadow || '';
    el.style.outline = '4px solid #f59e0b';
    el.style.boxShadow = '0 0 0 8px rgba(245,158,11,.35)';
  });
  await locator.page().waitForTimeout(Math.max(1200, pauseMs));
  await locator.first().evaluate((el) => {
    el.style.outline = el.dataset.demoOutline || '';
    el.style.boxShadow = el.dataset.demoShadow || '';
  }).catch(() => {});
}

async function injectVideoStyles(page) {
  await page.addStyleTag({
    content: `
      body { font-size: 16px !important; }
      pre, code, textarea, input, select { font-size: 15px !important; }
      .table td, .table th { padding: .65rem !important; }
    `,
  });
}

function dockerPhp(scriptPath) {
  try {
    execFileSync('docker', ['exec', 'moodle_app', 'php', scriptPath], { stdio: 'inherit' });
  } catch {
    console.warn('dockerPhp notice: Docker command skipped or failed, using existing DB fixtures.');
  }
}

function moodleSql(sql) {
  try {
    execFileSync('docker', [
      'exec', 'moodle_mariadb', 'mariadb', '-uroot', '-prootpassword', 'moodle', '-e', sql,
    ], { stdio: 'inherit' });
  } catch {
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
     WHERE quiz_id=${DEMO.quizId} AND question_id=${DEMO.questionId};
    DELETE FROM mdl_local_tce_canonical
     WHERE quiz_id=${DEMO.quizId} AND question_id=${DEMO.questionId};
    DELETE FROM mdl_local_tce_contributions
     WHERE quiz_id=${DEMO.quizId} AND question_id=${DEMO.questionId};
    DELETE FROM mdl_local_tce_runs
     WHERE quiz_id=${DEMO.quizId} AND question_id=${DEMO.questionId};
    DELETE FROM mdl_local_tce_revisions
     WHERE quiz_id=${DEMO.quizId} AND question_id=${DEMO.questionId};
    DELETE FROM mdl_local_tce_seeds
     WHERE quiz_id=${DEMO.quizId} AND question_id=${DEMO.questionId};
    INSERT INTO mdl_local_tce_seeds (course_id, quiz_id, question_id, input_raw, input_normalized, input_fingerprint, expected_output, oracle_output, purpose, category, created_by, is_active, timecreated)
    VALUES (${DEMO.courseId}, ${DEMO.quizId}, ${DEMO.questionId}, 'print(is_prime(2))', 'print(is_prime(2))', SHA2('print(is_prime(2))', 256), 'True', 'True', 'Số nguyên tố chẵn duy nhất', 'edge', 3, 1, UNIX_TIMESTAMP());
  `);
}

async function loginAndSave(browser, username, password, filename) {
  const context = await browser.newContext({ baseURL: BASE_URL });
  const page = await context.newPage();
  await page.goto('/login/index.php', { waitUntil: 'networkidle' });
  await page.waitForSelector('#username', { state: 'visible' });
  await page.locator('#username').fill(username);
  await page.locator('#password').fill(password);
  await page.waitForTimeout(300);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
    page.click('#loginbtn'),
  ]);
  if (page.url().includes('/login/')) {
    const loginError = await page.locator('.loginerrors, #loginerrormessage, .alert-danger').allTextContents();
    throw new Error(`Đăng nhập thất bại cho tài khoản ${username}: ${loginError.join(' ').trim() || page.url()}`);
  }
  await context.storageState({ path: path.resolve(__dirname, filename) });
  await context.close();
}

async function prepareAuthSessions(browser) {
  await loginAndSave(browser, 'teacher1', 'TeacherPassword123!', 'auth_teacher.json');
  await loginAndSave(browser, 'student1', 'StudentPassword123!', 'auth_studentA.json');
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
      } catch {}
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
  await page.waitForTimeout(1500);
}

async function runWorkspace(page) {
  const runButton = page.locator('#btnSubmitRunTest');
  await highlight(runButton, 1500);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 120000 }),
    runButton.click(),
  ]);
  await waitForPage(page);
  const latestRun = page.locator('.tce-history-desktop-table tbody tr[id^="run-"]').first();
  await latestRun.waitFor({ state: 'visible', timeout: 30000 });
  await latestRun.scrollIntoViewIfNeeded();
  await highlight(latestRun, 2000);
  await page.waitForTimeout(1500);
}

async function contributeLatest(page) {
  const button = page.locator('.tce-history-desktop-table tbody tr[id^="run-"] form button[type="submit"]').first();
  await button.waitFor({ state: 'visible', timeout: 30000 });
  await highlight(button, 1500);
  await clickAndWait(page, button);
  await page.waitForTimeout(1800);
}

async function reviewContribution(page, username, decision, rating, note) {
  const row = page.locator('tr').filter({ hasText: username }).first();
  const openButton = row.locator('button[data-target^="#reviewModal_"]').first();
  await highlight(openButton, 1500);
  await openButton.click();
  const modal = page.locator('.modal.show').first();
  await modal.waitFor({ state: 'visible' });
  await page.waitForTimeout(1500);

  const decisionSelect = modal.locator('select[name="decision"]');
  await highlight(decisionSelect, 1200);
  await decisionSelect.selectOption(decision);
  await page.waitForTimeout(1000);

  if (rating) {
    const ratingSelect = modal.locator('select[name="rating"]');
    await highlight(ratingSelect, 1200);
    await ratingSelect.selectOption(String(rating));
    await page.waitForTimeout(1000);
  }

  const noteTextarea = modal.locator('textarea[name="review_note"]');
  await highlight(noteTextarea, 1200);
  await noteTextarea.fill(note);
  await page.waitForTimeout(1200);

  const saveBtn = modal.locator('button[type="submit"]').first();
  await highlight(saveBtn, 1200);
  await clickAndWait(page, saveBtn);
  await page.waitForTimeout(1800);
}

async function openTeacherHub(page) {
  const visibleHubLink = page.locator('a[href*="/local/testcase_exchange/index.php"]:visible').first();
  if (await visibleHubLink.count()) {
    await visibleHubLink.scrollIntoViewIfNeeded();
    await highlight(visibleHubLink, 1500);
    await clickAndWait(page, visibleHubLink);
  } else {
    await page.goto(`/local/testcase_exchange/index.php?course=${DEMO.courseId}&quiz=${DEMO.quizId}`);
    await waitForPage(page);
  }
}

async function showTab(page, hrefSelector) {
  const tab = page.locator(`a[href="${hrefSelector}"]`).first();
  if (await tab.count() && await tab.isVisible()) {
    await highlight(tab, 1200);
    await tab.click();
    await page.evaluate((sel) => {
      if (typeof $ !== 'undefined' && $.fn && $.fn.tab) {
        $(`a[href="${sel}"]`).tab('show');
      }
    }, hrefSelector).catch(() => {});
    await page.waitForTimeout(2200);
  }
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
  fs.writeFileSync(
    concatPath,
    sceneRecords.map((s) => `file '${s.path.replace(/'/g, "'\\''")}'`).join('\n'),
    'utf8',
  );
  const ffmpegBin = findFfmpeg();
  try {
    execFileSync(
      ffmpegBin,
      ['-y', '-f', 'concat', '-safe', '0', '-i', concatPath, '-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-r', '30', OUTPUT_VIDEO],
      { stdio: 'inherit' },
    );
    console.log('Video demo hoàn chỉnh:', OUTPUT_VIDEO);
  } catch {
    try {
      const workspaceRoot = path.resolve(__dirname, '..');
      const concatLinux = path.join(RECORDINGS_DIR, 'concat_list_linux.txt');
      const lines = sceneRecords.map((s) => {
        const rel = path.relative(workspaceRoot, s.path).replace(/\\/g, '/');
        return `file '/work/${rel}'`;
      }).join('\n');
      fs.writeFileSync(concatLinux, lines, 'utf8');
      execFileSync('docker', [
        'run', '--rm', '-v', `${workspaceRoot}:/work`, '-w', '/work', 'alpine',
        'sh', '-c',
        'apk add --no-cache ffmpeg >/dev/null 2>&1 && ffmpeg -y -f concat -safe 0 -i /work/e2e/recordings/single-student-scenes/concat_list_linux.txt -c:v libx264 -pix_fmt yuv420p -r 30 /work/verification_evidence/demo_testcase_exchange_single_student.mp4',
      ], { stdio: 'inherit' });
      console.log('Video demo hoàn chỉnh (Docker ffmpeg):', OUTPUT_VIDEO);
    } catch {
      console.warn('Ghép video thất bại; các cảnh riêng lẻ được lưu tại:', RECORDINGS_DIR);
    }
  }
}

(async () => {
  prepareFixtures();
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    await prepareAuthSessions(browser);

    // ==========================================
    // SCENE 1: Giảng viên kích hoạt Quiz & cấu hình Hub
    // ==========================================
    await recordScene(browser, 1, 'Giảng viên cấu hình Quiz & Hub', 'auth_teacher.json', async (page) => {
      await page.goto('/my/');
      await waitForPage(page);
      await openQuizFromDashboard(page, highlight);
      await injectVideoStyles(page);

      const settingsLink = page.locator('a[href*="/course/modedit.php?update="]').first();
      if (await settingsLink.count() && await settingsLink.isVisible()) {
        await highlight(settingsLink, 1500);
        await clickAndWait(page, settingsLink);
        const tceHeader = page.locator('#id_testcaseexchangehdr');
        if (await tceHeader.count()) await highlight(tceHeader, 1500);
        const tceSelect = page.locator('select[name="enable_testcase_exchange"]');
        if (await tceSelect.count()) await highlight(tceSelect, 1500);
        const saveAndDisplay = page.locator('#id_submitbutton2, input[name="submitbutton2"]').first();
        if (await saveAndDisplay.count()) {
          await highlight(saveAndDisplay, 1200);
          await clickAndWait(page, saveAndDisplay);
        }
      }

      await openTeacherHub(page);
      await injectVideoStyles(page);

      await showTab(page, '#tab_overview');
      await showTab(page, '#tab_policy');
      await showTab(page, '#tab_bank');
    });

    // ==========================================
    // SCENE 2: Sinh viên (1 tài khoản) thử nghiệm vi sai & đóng góp
    // ==========================================
    await recordScene(browser, 2, 'Sinh viên thử nghiệm & đóng góp testcase', 'auth_studentA.json', async (page) => {
      await page.goto('/my/');
      await waitForPage(page);
      await openCourseFromDashboard(page, highlight);
      await openQuizFromCourse(page, highlight);
      await startOrContinueAttempt(page, highlight);
      await openProgrammingQuestion(page, highlight);
      await submitPrimeAnswer(page, BUGGY_PRIME_CODE, highlight);
      await openTestcaseExchangeFromQuestion(page, highlight);
      await injectVideoStyles(page);

      // 1) Chạy thử code lỗi với testcase số âm -> phát hiện sai lệch (mismatch)
      await fillWorkspace(page, {
        code: BUGGY_PRIME_CODE,
        input: 'print(is_prime(-7))',
        expected: 'False',
        category: 'boundary',
        purpose: 'Kiểm tra số âm; thuật toán thiếu điều kiện n <= 1.',
      });
      await runWorkspace(page);

      // 2) Sửa code đúng + chạy thử -> khớp Oracle & gửi đóng góp
      await fillWorkspace(page, {
        code: CORRECT_PRIME_CODE,
        input: 'print(is_prime(997))',
        expected: 'True',
        category: 'normal',
        purpose: 'Kiểm tra số nguyên tố lớn không chia hết cho các số nhỏ.',
      });
      await runWorkspace(page);
      await contributeLatest(page);

      // 3) Xem danh sách đóng góp của sinh viên
      await showTab(page, '#tab_contributions');
    });

    // ==========================================
    // SCENE 3: Giảng viên duyệt đóng góp -> Sinh viên nhận thưởng & Bảng xếp hạng
    // ==========================================
    await recordScene(browser, 3, 'Thẩm định, kho testcase & bảng xếp hạng', 'auth_teacher.json', async (page) => {
      await page.goto('/my/');
      await waitForPage(page);
      await openCourseFromDashboard(page, highlight);
      await openQuizFromCourse(page, highlight);
      await openTeacherHub(page);
      await injectVideoStyles(page);

      await showTab(page, '#tab_review');
      const student1Row = page.locator('tr').filter({ hasText: 'student1' }).first();
      if (await student1Row.count() && await student1Row.isVisible()) {
        await reviewContribution(page, 'student1', 'approved', 5, 'Testcase hợp lệ, bao quát tốt.');
      }

      await showTab(page, '#tab_bank');
      await showTab(page, '#tab_overview');
    });

    // ==========================================
    // SCENE 4: Sinh viên nhận testcase thưởng & xem bảng xếp hạng
    // ==========================================
    await recordScene(browser, 4, 'Sinh viên nhận thưởng & bảng xếp hạng', 'auth_studentA.json', async (page) => {
      await page.goto('/my/');
      await waitForPage(page);
      await openCourseFromDashboard(page, highlight);
      await openQuizFromCourse(page, highlight);
      await startOrContinueAttempt(page, highlight);
      await openProgrammingQuestion(page, highlight);
      await openTestcaseExchangeFromQuestion(page, highlight);
      await injectVideoStyles(page);

      await showTab(page, '#tab_unlocked');
      await showTab(page, '#tab_leaderboard');
    });
  } finally {
    await browser.close();
  }

  if (process.env.DEMO_SKIP_VIDEO_POST !== '1') postProcessVideo();
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
