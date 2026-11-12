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
        'background:rgba(15,23,42,.95)', 'color:#f8fafc', 'padding:14px 26px',
        'border-radius:14px', 'box-shadow:0 16px 40px rgba(0,0,0,.6)',
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

async function highlight(locator, pauseMs = 2000) {
  if (!(await locator.count()) || !(await locator.first().isVisible())) return;
  await locator.first().scrollIntoViewIfNeeded();
  await locator.first().evaluate((el) => {
    el.dataset.demoOutline = el.style.outline || '';
    el.dataset.demoShadow = el.style.boxShadow || '';
    el.style.outline = '4px solid #f59e0b';
    el.style.boxShadow = '0 0 0 8px rgba(245,158,11,.35)';
  });
  await locator.page().waitForTimeout(Math.max(2000, pauseMs));
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
  await page.goto('/login/index.php');
  await page.fill('#username', username);
  await page.fill('#password', password);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded' }),
    page.locator('form#login').evaluate((form) => form.submit()),
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
  await loginAndSave(browser, 'student2', 'StudentPassword123!', 'auth_studentB.json');
  await loginAndSave(browser, 'student3', 'StudentPassword123!', 'auth_studentC.json');
}

async function switchToSavedSession(page, filename) {
  const savedState = JSON.parse(fs.readFileSync(path.resolve(__dirname, filename), 'utf8'));
  await page.context().clearCookies();
  await page.context().addCookies(savedState.cookies || []);
  await page.goto('/my/');
  await waitForPage(page);
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
  await page.waitForTimeout(2000);
}

async function runWorkspace(page) {
  const runButton = page.locator('#btnSubmitRunTest');
  await highlight(runButton, 2000);
  await Promise.all([
    page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 120000 }),
    runButton.click(),
  ]);
  await waitForPage(page);
  const latestRun = page.locator('.tce-history-desktop-table tbody tr[id^="run-"]').first();
  await latestRun.waitFor({ state: 'visible', timeout: 30000 });
  await latestRun.scrollIntoViewIfNeeded();
  await highlight(latestRun, 2500);
  await page.waitForTimeout(2000);
}

async function contributeLatest(page) {
  const button = page.locator('.tce-history-desktop-table tbody tr[id^="run-"] form button[type="submit"]').first();
  await button.waitFor({ state: 'visible', timeout: 30000 });
  await highlight(button, 2000);
  await clickAndWait(page, button);
  await page.waitForTimeout(2200);
}

async function returnToQuiz(page) {
  const returnLink = page.locator('a').filter({ hasText: /Quay lại bài quiz|Back to quiz/i }).first();
  if (await returnLink.count() && await returnLink.isVisible()) {
    await highlight(returnLink, 2000);
    await clickAndWait(page, returnLink);
  } else {
    await page.goBack();
    await waitForPage(page);
  }
  await page.waitForTimeout(2000);
}

async function reviewContribution(page, username, decision, rating, note) {
  const row = page.locator('tr').filter({ hasText: username }).first();
  const openButton = row.locator('button[data-target^="#reviewModal_"]').first();
  await highlight(openButton, 2000);
  await openButton.click();
  const modal = page.locator('.modal.show').first();
  await modal.waitFor({ state: 'visible' });
  await page.waitForTimeout(2200);

  const decisionSelect = modal.locator('select[name="decision"]');
  await highlight(decisionSelect, 2000);
  await decisionSelect.selectOption(decision);
  await page.waitForTimeout(2000);

  if (rating) {
    const ratingSelect = modal.locator('select[name="rating"]');
    await highlight(ratingSelect, 2000);
    await ratingSelect.selectOption(String(rating));
    await page.waitForTimeout(2000);
  }

  const noteTextarea = modal.locator('textarea[name="review_note"]');
  await highlight(noteTextarea, 2000);
  await noteTextarea.fill(note);
  await page.waitForTimeout(2000);

  const saveBtn = modal.locator('button[type="submit"]').first();
  await highlight(saveBtn, 2000);
  await clickAndWait(page, saveBtn);
  await page.waitForTimeout(2200);
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
  let merged = false;
  const ffmpegBin = findFfmpeg();
  try {
    execFileSync(ffmpegBin, ['-y', '-f', 'concat', '-safe', '0', '-i', concatPath, '-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-r', '30', outputPath], { stdio: 'inherit' });
    merged = true;
    console.log('Video hoàn chỉnh đã được xuất tại:', outputPath);
  } catch (err) {
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
        `apk add --no-cache ffmpeg >/dev/null 2>&1 && ffmpeg -y -f concat -safe 0 -i /work/e2e/recordings/three-phase-scenes/concat_list_linux.txt -c:v libx264 -pix_fmt yuv420p -r 30 /work/verification_evidence/demo_testcase_exchange_three_phases.mp4`
      ], { stdio: 'inherit' });
      fs.unlinkSync(concatLinux);
      merged = true;
      console.log('Video hoàn chỉnh đã được ghép qua Docker ffmpeg tại:', outputPath);
    } catch (dockerErr) {
      console.warn('Ghép video thất bại; các video cảnh riêng lẻ được lưu đầy đủ tại:', RECORDINGS_DIR);
    }
  }

  const captions = [
    'Phase 1: Giảng viên kích hoạt quy tắc Quiz Settings và kiểm tra câu hỏi CodeRunner.',
    'Phase 1: Giảng viên mở Testcase Exchange từ Quiz, xem KPI và cấu hình chính sách lớp học.',
    'Phase 2: Sinh viên 1 làm bài, mở workspace chung của Quiz và gửi testcase hợp lệ.',
    'Phase 2: Sinh viên 2 phát hiện lỗi logic từ ca số âm, sửa code trong Ace và đóng góp.',
    'Phase 2: Sinh viên 3 phát hiện testcase trùng lặp và thiết kế testcase biên mới.',
    'Phase 3: Giảng viên thẩm định chậm rãi từng đóng góp và xem nhật ký Audit Log.',
    'Phase 3: Sinh viên 3 gửi giải trình bổ sung và Sinh viên 1 nhận thưởng mở khóa 1-đổi-1.',
    'Phase 3: Giảng viên duyệt giải trình, xem kho testcase chung và bảng xếp hạng cập nhật.',
  ];
  let offset = 0;
  const items = sceneRecords.map((scene, index) => {
    const start = formatSrtTime(offset + 0.5);
    const end = formatSrtTime(offset + Math.max(1, scene.duration - 0.5));
    offset += scene.duration;
    return `${index + 1}\n${start} --> ${end}\n${captions[index] || scene.name}\n`;
  });
  fs.writeFileSync(path.join(EVIDENCE_DIR, 'demo_testcase_exchange_three_phases.srt'), items.join('\n'), 'utf8');
}

(async () => {
  prepareFixtures();
  const browser = await chromium.launch({ channel: 'chrome', headless: true });
  try {
    await prepareAuthSessions(browser);

    // ==========================================
    // SCENE 1: Giảng viên cài đặt Quiz & Soạn đề
    // ==========================================
    await recordScene(browser, 1, 'Giảng viên cài đặt Quiz và soạn đề', 'auth_teacher.json', async (page) => {
      await page.goto('/my/');
      await waitForPage(page);
      await showCaption(page, 'PHASE 1/3 • GIẢNG VIÊN', 'Bắt đầu từ Moodle Dashboard', 'Giảng viên đi theo đúng luồng Dashboard → My courses → Khóa học → Quiz.');
      await page.waitForTimeout(2200);
      await openQuizFromDashboard(page, highlight);
      await injectVideoStyles(page);

      // Mở phần Cài đặt Quiz (Settings) để thấy subplugin quizaccess_testcaseexchange
      const settingsLink = page.locator('a[href*="/course/modedit.php?update="]').first();
      if (await settingsLink.count() && await settingsLink.isVisible()) {
        await highlight(settingsLink, 2000);
        await clickAndWait(page, settingsLink);
        await showCaption(page, 'PHASE 1/3 • QUIZ SETTINGS', 'Kích hoạt quy tắc Testcase Exchange', 'Subplugin kiểm soát quyền truy cập trao đổi testcase được tích hợp trực tiếp vào cài đặt Quiz.');
        const tceHeader = page.locator('#id_testcaseexchangehdr');
        if (await tceHeader.count()) {
          await highlight(tceHeader, 2200);
        }
        const tceSelect = page.locator('select[name="enable_testcase_exchange"]');
        if (await tceSelect.count()) {
          await highlight(tceSelect, 2200);
        }
        const saveAndDisplay = page.locator('#id_submitbutton2, input[name="submitbutton2"]').first();
        if (await saveAndDisplay.count()) {
          await highlight(saveAndDisplay, 2000);
          await clickAndWait(page, saveAndDisplay);
        }
      }

      // Trên trang Quiz view, thấy nút tích hợp và mở xem câu hỏi
      const hubButton = page.locator('a[href*="/local/testcase_exchange/index.php"]').first();
      if (await hubButton.count() && await hubButton.isVisible()) {
        await highlight(hubButton, 2200);
      }

      await showCaption(page, 'PHASE 1/3 • SOẠN ĐỀ', DEMO.quizName, 'Mở phần chỉnh sửa đề thi để xem câu hỏi CodeRunner và cấu hình bài tập.');
      const questionBank = page.locator('.secondary-navigation a[href*="/mod/quiz/edit.php"], a[href*="/mod/quiz/edit.php?cmid="]').first();
      if (await questionBank.count() && await questionBank.isVisible()) {
        await highlight(questionBank, 2000);
        await clickAndWait(page, questionBank);
      }
      const questionRow = page.locator('a[href*="question.php"], .questionname, .qtype_coderunner').filter({
        hasText: /is_prime|Prime|Số Nguyên Tố/i,
      }).first();
      if (await questionRow.count() && await questionRow.isVisible()) {
        await highlight(questionRow, 2200);
      }
      await showCaption(page, 'PHASE 1/3 • SOẠN ĐỀ', 'Câu hỏi CodeRunner sẵn sàng', 'Câu hỏi đã có đề bài, lời giải tham chiếu Python và các testcase công khai.');
      await page.waitForTimeout(2500);
    });

    // ==========================================
    // SCENE 2: Giảng viên cấu hình Testcase Exchange Hub
    // ==========================================
    await recordScene(browser, 2, 'Giảng viên cấu hình Testcase Exchange', 'auth_teacher.json', async (page) => {
      await openCourseFromDashboard(page, highlight);
      await openQuizFromCourse(page, highlight);
      await openTeacherHubFromQuiz(page, highlight);
      await injectVideoStyles(page);

      // Xem Overview KPI
      const overviewTab = page.locator('a[href="#tab_overview"]').first();
      if (await overviewTab.count() && await overviewTab.isVisible()) {
        await highlight(overviewTab, 2000);
        await overviewTab.click();
        await showCaption(page, 'PHASE 1/3 • TỔNG QUAN & KPI', 'Trung tâm điều khiển giảng viên', 'Theo dõi tổng quan lượt chạy, đóng góp, tỷ lệ chấp thuận và phần thưởng.');
        await page.waitForTimeout(2500);
      }

      // Cấu hình Policy
      const policyTab = page.locator('a[href="#tab_policy"]').first();
      await highlight(policyTab, 2000);
      await policyTab.click();
      await showCaption(page, 'PHASE 1/3 • SETUP', 'Cấu hình chính sách Testcase Exchange', 'Bật đóng góp, giảng viên duyệt, thưởng 1 đổi 1 và bảng xếp hạng cho Quiz.');
      const reviewModeSelect = page.locator('select[name="review_mode"]');
      await highlight(reviewModeSelect, 2000);
      await reviewModeSelect.selectOption('teacher');

      const rewardPolicySelect = page.locator('select[name="reward_policy"]');
      await highlight(rewardPolicySelect, 2000);
      await rewardPolicySelect.selectOption('one_for_one');

      const leaderboard = page.locator('#policyLeaderboard');
      await highlight(leaderboard, 2000);
      if (!(await leaderboard.isChecked())) await leaderboard.check();
      await page.waitForTimeout(2000);

      // Xem Seed & Sample Testcases trong Kho testcase
      const bankTab = page.locator('a[href="#tab_bank"]').first();
      if (await bankTab.count() && await bankTab.isVisible()) {
        await highlight(bankTab, 2000);
        await bankTab.click();
        await showCaption(page, 'PHASE 1/3 • SEED TESTCASES', 'Kho testcase hạt giống & mẫu ban đầu', 'Giảng viên thiết lập sẵn các testcase mẫu làm điểm tựa khởi đầu cho sinh viên.');
        await page.waitForTimeout(2500);
      }

      // Quay lại trang khóa học để xác nhận Quiz đã sẵn sàng
      const courseBreadcrumb = page.locator('.breadcrumb a[href*="/course/view.php"]').first();
      if (await courseBreadcrumb.count()) {
        await highlight(courseBreadcrumb, 2000);
        await clickAndWait(page, courseBreadcrumb);
      }
      await showCaption(page, 'PHASE 1/3 • SẴN SÀNG', DEMO.courseName, 'Hoàn tất thiết lập; Quiz đã mở và sẵn sàng cho sinh viên làm bài.');
      await page.waitForTimeout(2500);
    });

    // ==========================================
    // SCENE 3: Sinh viên 1 làm bài & Đóng góp
    // ==========================================
    await recordScene(browser, 3, 'Sinh viên 1 chạy và đóng góp testcase', 'auth_studentA.json', async (page) => {
      await page.goto('/my/');
      await waitForPage(page);
      await showCaption(page, 'PHASE 2/3 • SINH VIÊN 1', 'Dashboard → Khóa học → Quiz → Bài tập', 'Sinh viên làm câu hỏi CodeRunner và mở Testcase Exchange trực tiếp từ đề bài.');
      await page.waitForTimeout(2000);
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
      await showCaption(page, 'PHASE 2/3 • SINH VIÊN 1', 'Lịch sử chạy thử được cập nhật', 'Lượt chạy mới xuất hiện trong bảng lịch sử; testcase hợp lệ có thể gửi đóng góp ngay tại đó.');
      await page.waitForTimeout(2500);
      await contributeLatest(page);
      await returnToQuiz(page);
      await showCaption(page, 'PHASE 2/3 • SINH VIÊN 1', 'Quay lại Quiz', 'Sinh viên trở về lượt làm bài để tiếp tục hoàn thành Quiz.');
      await page.waitForTimeout(2200);
    });

    // ==========================================
    // SCENE 4: Sinh viên 2 phát hiện và sửa lỗi
    // ==========================================
    await recordScene(browser, 4, 'Sinh viên 2 phát hiện và sửa lỗi', 'auth_studentB.json', async (page) => {
      await page.goto('/my/');
      await waitForPage(page);
      await showCaption(page, 'PHASE 2/3 • SINH VIÊN 2', 'Đi vào bài tập như người học thật', 'Sinh viên 2 mở Quiz, làm câu hỏi rồi dùng testcase số âm để phát hiện lỗi logic.');
      await page.waitForTimeout(2000);
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
      await showCaption(page, 'PHASE 2/3 • SINH VIÊN 2', 'Phát hiện lỗi logic từ ca số âm', 'Sinh viên so sánh kết quả dự đoán với hành vi chương trình và nhận ra cần bổ sung điều kiện n <= 1.');
      await page.waitForTimeout(2500);
      await fillWorkspace(page, {
        code: CORRECT_PRIME_CODE, input: 'print(is_prime(-7))', expected: 'False', category: 'boundary',
        purpose: 'Kiểm tra số âm; sau khi bổ sung điều kiện n <= 1 chương trình trả kết quả chính xác.',
      });
      await runWorkspace(page);
      await showCaption(page, 'PHASE 2/3 • SINH VIÊN 2', 'Đã sửa code và vượt qua kiểm thử', 'Kết quả chạy thử đạt chuẩn; sinh viên gửi đóng góp và quay lại Quiz.');
      await page.waitForTimeout(2500);
      await contributeLatest(page);
      await returnToQuiz(page);
      await page.waitForTimeout(2000);
    });

    // ==========================================
    // SCENE 5: Sinh viên 3 xử lý testcase trùng
    // ==========================================
    await recordScene(browser, 5, 'Sinh viên 3 xử lý testcase trùng', 'auth_studentC.json', async (page) => {
      await page.goto('/my/');
      await waitForPage(page);
      await showCaption(page, 'PHASE 2/3 • SINH VIÊN 3', 'Đi từ Dashboard vào đúng câu hỏi', 'Sinh viên 3 thử một testcase đã có rồi thiết kế testcase mới.');
      await page.waitForTimeout(2000);
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
      await page.waitForTimeout(2500);
      await fillWorkspace(page, {
        code: CORRECT_PRIME_CODE, input: 'print(is_prime(1009))', expected: 'True', category: 'boundary',
        purpose: 'Kiểm tra số nguyên tố ngay trên ngưỡng một nghìn.',
      });
      await runWorkspace(page);
      await showCaption(page, 'PHASE 2/3 • SINH VIÊN 3', 'Testcase biên mới đạt yêu cầu', 'Ca kiểm thử biên 1009 hợp lệ và độc lập; sinh viên gửi đóng góp.');
      await page.waitForTimeout(2500);
      await contributeLatest(page);
      await returnToQuiz(page);
      await page.waitForTimeout(2000);
    });

    // ==========================================
    // SCENE 6: Giảng viên review ba đóng góp & Audit Log
    // ==========================================
    await recordScene(browser, 6, 'Giảng viên review ba đóng góp', 'auth_teacher.json', async (page) => {
      await page.goto('/my/');
      await waitForPage(page);
      await showCaption(page, 'PHASE 3/3 • REVIEW', 'Giảng viên trở lại từ Dashboard', 'Dashboard → Khóa học → Quiz → Testcase Exchange Hub → Duyệt đóng góp.');
      await page.waitForTimeout(2000);
      await openCourseFromDashboard(page, highlight);
      await openQuizFromCourse(page, highlight);
      await openTeacherHubFromQuiz(page, highlight);
      const reviewTab = page.locator('a[href="#tab_review"]').first();
      await highlight(reviewTab, 2000);
      await reviewTab.click();
      await reviewContribution(page, 'student1', 'approved', 5, 'Testcase rõ ràng, hợp lệ và có mục đích kiểm thử tốt.');
      await reviewContribution(page, 'student2', 'approved', 4, 'Ca kiểm thử số âm (-7) phát hiện đúng lỗi biên n <= 1.');
      await reviewContribution(page, 'student3', 'needs_explanation', 0, 'Ca kiểm thử biên 1009 hợp lệ; hãy bổ sung thêm giải trình.');
      await showCaption(page, 'PHASE 3/3 • REVIEW', 'Đã phản hồi cho ba sinh viên', 'Hai testcase được duyệt tặng sao và một testcase được yêu cầu giải trình.');
      await page.waitForTimeout(2500);

      // Xem Tab Hoạt động (Audit log)
      const activityTab = page.locator('a[href="#tab_activity"]').first();
      if (await activityTab.count() && await activityTab.isVisible()) {
        await highlight(activityTab, 2000);
        await activityTab.click();
        await showCaption(page, 'PHASE 3/3 • AUDIT LOG', 'Nhật ký hoạt động toàn diện', 'Hệ thống lưu trữ minh bạch mọi lượt chạy thử, gửi bài và quyết định review.');
        await page.waitForTimeout(3000);
      }
    });

    // ==========================================
    // SCENE 7: SV3 gửi giải trình & SV1 nhận thưởng mở khóa
    // ==========================================
    await recordScene(browser, 7, 'Sinh viên giải trình và nhận thưởng', 'auth_studentC.json', async (page) => {
      await page.goto('/my/');
      await waitForPage(page);
      await showCaption(page, 'PHASE 3/3 • GIẢI TRÌNH', 'Sinh viên 3 phản hồi yêu cầu', 'Mở workspace → Tab Đóng góp của tôi → Điền giải trình cho testcase.');
      await page.waitForTimeout(2000);
      await openCourseFromDashboard(page, highlight);
      await openQuizFromCourse(page, highlight);
      await startOrContinueAttempt(page, highlight);
      await openProgrammingQuestion(page, highlight);
      await openTestcaseExchangeFromQuestion(page, highlight);
      await injectVideoStyles(page);

      // SV3 mở tab Đóng góp của tôi
      const contribTab = page.locator('a[href="#contributions"]').first();
      await highlight(contribTab, 2000);
      await contribTab.click();
      await page.waitForTimeout(2000);

      const explainInput = page.locator('#contributions [name="explanation"]').first();
      if (await explainInput.count() && await explainInput.isVisible()) {
        await highlight(explainInput, 2000);
        await explainInput.fill('1009 là số nguyên tố ngay sau 1000, kiểm tra tính đúng đắn ở ngưỡng chuyển bậc phân tích.');
        await page.waitForTimeout(2000);
        const submitExplain = explainInput.locator('xpath=ancestor::form').locator('button[type="submit"]').first();
        await highlight(submitExplain, 2000);
        await clickAndWait(page, submitExplain);
        await showCaption(page, 'PHASE 3/3 • GIẢI TRÌNH', 'Đã gửi giải trình thành công', 'Giảng viên sẽ nhận được thông báo để xem xét phê duyệt chính thức.');
        await page.waitForTimeout(2500);
      }

      // Chuyển sang sinh viên 1 để nhận phần thưởng đã mở khóa.
      await switchToSavedSession(page, 'auth_studentA.json');
      await openCourseFromDashboard(page, highlight);
      await openQuizFromCourse(page, highlight);
      await startOrContinueAttempt(page, highlight);
      await openProgrammingQuestion(page, highlight);
      await openTestcaseExchangeFromQuestion(page, highlight);
      await injectVideoStyles(page);

      const rewardsTab = page.locator('a[href="#contributions"]').first();
      await highlight(rewardsTab, 2000);
      await rewardsTab.click();
      await page.locator('#rewards').scrollIntoViewIfNeeded();
      await showCaption(page, 'PHASE 3/3 • PHẦN THƯỞNG 1-ĐỔI-1', 'Kho phần thưởng mở khóa testcase', 'Sinh viên 1 sở hữu 1 suất phần thưởng từ testcase được duyệt.');
      await page.waitForTimeout(2200);

      const claimBtn = page.locator('#rewards form:has(input[name="action"][value="claim_reward"]) button[type="submit"]').first();
      if (await claimBtn.count() && await claimBtn.isVisible()) {
        await highlight(claimBtn, 2000);
        await clickAndWait(page, claimBtn);
        await showCaption(page, 'PHASE 3/3 • PHẦN THƯỞNG', 'Đã mở khóa testcase thành công', 'Đầu vào và kết quả kiểm thử của ca mẫu đã được mở khóa hoàn toàn.');
        await page.waitForTimeout(2500);
      }
    });

    // ==========================================
    // SCENE 8: Duyệt giải trình, Ngân hàng & Bảng xếp hạng
    // ==========================================
    await recordScene(browser, 8, 'Ngân hàng và bảng xếp hạng', 'auth_teacher.json', async (page) => {
      await page.goto('/my/');
      await waitForPage(page);
      await openCourseFromDashboard(page, highlight);
      await openQuizFromCourse(page, highlight);
      await openTeacherHubFromQuiz(page, highlight);
      await injectVideoStyles(page);

      // Giảng viên duyệt giải trình của SV3
      const reviewTab = page.locator('a[href="#tab_review"]').first();
      await highlight(reviewTab, 2000);
      await reviewTab.click();
      await page.waitForTimeout(2000);
      const student3Row = page.locator('tr').filter({ hasText: 'student3' }).first();
      if (await student3Row.count() && await student3Row.isVisible()) {
        await reviewContribution(page, 'student3', 'approved', 5, 'Đã duyệt sau khi nhận giải trình hợp lệ.');
      }

      // Mở Kho testcase (Bank)
      const bankTab = page.locator('a[href="#tab_bank"]').first();
      await highlight(bankTab, 2000);
      await bankTab.click();
      await showCaption(page, 'PHASE 3/3 • KHO TESTCASE', 'Ngân hàng testcase đã được cập nhật', 'Các đóng góp được duyệt trở thành nguồn testcase dùng chung của lớp học.');
      await page.waitForTimeout(3000);

      // Xem Overview KPI cập nhật đầy đủ
      const overviewTab = page.locator('a[href="#tab_overview"]').first();
      if (await overviewTab.count() && await overviewTab.isVisible()) {
        await highlight(overviewTab, 2000);
        await overviewTab.click();
        await showCaption(page, 'PHASE 3/3 • TỔNG KẾT', 'Chỉ số KPI lớp học', '100% testcase đã thẩm định, tỷ lệ chấp thuận cao và nguồn tài nguyên phong phú.');
        await page.waitForTimeout(3000);
      }

      // Chuyển sang góc nhìn sinh viên để kết thúc bằng bảng xếp hạng của lớp.
      const previewLink = page.locator('a[href*="preview=1"]').first();
      await previewLink.waitFor({ state: 'visible', timeout: 30000 });
      await highlight(previewLink, 2000);
      await clickAndWait(page, previewLink);
      await injectVideoStyles(page);

      const leaderboardTab = page.locator('a[href="#seed_search"]').first();
      if (await leaderboardTab.count() && await leaderboardTab.isVisible()) {
        await highlight(leaderboardTab, 2000);
        await leaderboardTab.click();
        await page.locator('#leaderboard').scrollIntoViewIfNeeded();
        await showCaption(page, 'HOÀN TẤT DEMO', 'Bảng xếp hạng lớp học cập nhật đầy đủ', 'Góc nhìn sinh viên cho thấy điểm sao và số lượng đóng góp được ghi nhận công bằng.');
        await page.waitForTimeout(3500);
      }
    });

  } finally {
    await browser.close();
  }

  if (process.env.DEMO_SKIP_VIDEO_POST !== '1') postProcessVideo();
})().catch((error) => {
  console.error(error);
  process.exitCode = 1;
});
