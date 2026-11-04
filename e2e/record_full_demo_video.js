// @ts-check
const { chromium } = require("@playwright/test");
const fs = require("fs");
const path = require("path");
const { execSync } = require("child_process");

const BASE_URL = "http://localhost:8080";
const RECORDINGS_DIR = path.resolve(__dirname, "recordings/scenes");
const EVIDENCE_DIR = path.resolve(__dirname, "../verification_evidence");

if (!fs.existsSync(RECORDINGS_DIR)) {
  fs.mkdirSync(RECORDINGS_DIR, { recursive: true });
}
if (!fs.existsSync(EVIDENCE_DIR)) {
  fs.mkdirSync(EVIDENCE_DIR, { recursive: true });
}

/**
 * Hiển thị thanh phụ đề (HUD Caption) chuẩn phong cách bài báo / tutorial ở cuối màn hình.
 * @param {import("@playwright/test").Page} page
 * @param {string} badge Ví dụ: "CẢNH 1/12 • GIỚI THIỆU"
 * @param {string} title Tiêu đề chính của tính năng
 * @param {string} subtitle Nội dung caption theo yêu cầu người dùng
 */
async function showCaption(page, badge, title, subtitle) {
  await page.evaluate(({ badge, title, subtitle }) => {
    let box = document.getElementById("pw-demo-caption");
    if (!box) {
      box = document.createElement("div");
      box.id = "pw-demo-caption";
      box.style.cssText = [
        "position: fixed",
        "bottom: 24px",
        "left: 50%",
        "transform: translateX(-50%)",
        "z-index: 9999999",
        "width: 90%",
        "max-width: 1100px",
        "background: rgba(15, 23, 42, 0.94)",
        "backdrop-filter: blur(10px)",
        "color: #f8fafc",
        "padding: 14px 26px",
        "border-radius: 14px",
        "box-shadow: 0 16px 40px rgba(0,0,0,0.55)",
        "border-left: 6px solid #38bdf8",
        "border-top: 1px solid rgba(255,255,255,0.12)",
        "font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, Helvetica, Arial, sans-serif",
        "pointer-events: none",
        "transition: all 0.35s ease",
      ].join(";");
      document.body.appendChild(box);
    }
    box.innerHTML = `
      <div style="display:flex; align-items:center; gap:12px; margin-bottom:5px;">
        <span style="background:#0284c7; color:#fff; font-weight:800; font-size:12px; padding:3px 12px; border-radius:999px; letter-spacing:0.8px; text-transform:uppercase;">
          ${badge}
        </span>
        <span style="font-size:16px; font-weight:700; color:#f1f5f9;">
          ${title}
        </span>
      </div>
      <div style="font-size:15px; color:#cbd5e1; font-weight:500; line-height:1.45;">
        ${subtitle}
      </div>
    `;
  }, { badge, title, subtitle });
}

/**
 * Tô sáng một phần tử để người xem chú ý trước khi click.
 * @param {import("@playwright/test").Locator} locator
 * @param {number} pauseMs
 */
async function highlight(locator, pauseMs = 1800) {
  try {
    if (await locator.count() > 0 && await locator.first().isVisible()) {
      await locator.first().scrollIntoViewIfNeeded();
      await locator.first().evaluate((el) => {
        const htmlEl = /** @type {HTMLElement} */ (el);
        htmlEl.dataset.origOutline = htmlEl.style.outline || "";
        htmlEl.dataset.origBoxShadow = htmlEl.style.boxShadow || "";
        htmlEl.style.outline = "4px solid #f59e0b";
        htmlEl.style.boxShadow = "0 0 0 8px rgba(245, 158, 11, 0.4)";
        htmlEl.style.transition = "all 0.25s ease";
      });
      await locator.page().waitForTimeout(pauseMs);
      await locator.first().evaluate((el) => {
        const htmlEl = /** @type {HTMLElement} */ (el);
        htmlEl.style.outline = htmlEl.dataset.origOutline || "";
        htmlEl.style.boxShadow = htmlEl.dataset.origBoxShadow || "";
      });
    }
  } catch (e) {}
}

/**
 * Gõ ký tự chậm rãi với delay 90ms để người xem theo dõi rõ ràng.
 * @param {import("@playwright/test").Locator} locator
 * @param {string} text
 */
async function slowType(locator, text) {
  await locator.fill("");
  await locator.type(text, { delay: 90 });
}

/**
 * Điền mã nguồn qua Ace Editor nếu editor đang được bật.
 * Textarea #student_code là phần tử tương thích submit nhưng bị ẩn khi Ace
 * khởi tạo, vì vậy locator.fill() sẽ timeout trên giao diện hiện tại.
 * @param {import("@playwright/test").Page} page
 * @param {string} text
 */
async function setStudentCode(page, text) {
  await page.waitForFunction(() => {
    const textarea = document.getElementById("student_code");
    const editorDiv = document.getElementById("student_code_ace");
    return Boolean(textarea && editorDiv && (!window.ace || window.ace.edit));
  });
  await page.evaluate((value) => {
    const textarea = document.getElementById("student_code");
    const editorDiv = document.getElementById("student_code_ace");
    if (!textarea) return;

    if (editorDiv && window.ace && window.ace.edit) {
      const editor = window.ace.edit(editorDiv);
      editor.setValue(value, -1);
      editor.clearSelection();
      textarea.value = editor.getValue();
    } else {
      textarea.value = value;
    }

    textarea.dispatchEvent(new Event("input", { bubbles: true }));
    textarea.dispatchEvent(new Event("change", { bubbles: true }));
    window.dispatchEvent(new Event("tce_editor_changed"));
  }, text);
}

async function submitRunForm(page) {
  await page.locator("#runTestForm").evaluate((form) => {
    if (typeof form.requestSubmit === "function") {
      form.requestSubmit();
    } else {
      form.submit();
    }
  });
}

/**
 * Điều chỉnh độ phân giải và typography trang để chữ đọc rõ trên 1080p.
 * @param {import("@playwright/test").Page} page
 */
async function injectComfortableReadingStyles(page) {
  await page.addStyleTag({
    content: `
      body { font-size: 16px !important; }
      pre, code, textarea, input, select { font-size: 15px !important; }
      .card-header h5 { font-size: 1.25rem !important; }
      .table td, .table th { padding: 0.65rem !important; }
    `
  });
}

function runDbQuery(sql) {
  const sanitized = sql.replace(/"/g, '\\"');
  const res = execSync(`docker exec moodle_mariadb mariadb -u moodle_app_writer -pJobeSecret123! testcase_store -e "${sanitized}"`);
  return res.toString();
}

function runMoodleQuery(sql) {
  const sanitized = sql.replace(/"/g, '\\"');
  const res = execSync(`docker exec moodle_mariadb mariadb -uroot -prootpassword moodle -e "${sanitized}"`);
  return res.toString();
}

async function prepareAuthSessions(browser) {
  console.log("[AUTH] Đang chuẩn bị phiên làm việc riêng cho 3 tài khoản...");
  const users = [
    { username: "teacher1", password: "TeacherPassword123!", file: "auth_teacher.json" },
    { username: "student1", password: "StudentPassword123!", file: "auth_studentA.json" },
    { username: "student2", password: "StudentPassword123!", file: "auth_studentB.json" },
  ];
  for (const u of users) {
    const ctx = await browser.newContext({ baseURL: BASE_URL });
    const p = await ctx.newPage();
    await p.goto("/login/index.php");
    await p.waitForLoadState("networkidle");
    await p.fill("#username", u.username);
    await p.fill("#password", u.password);
    await Promise.all([
      p.waitForNavigation(),
      p.evaluate(() => {
        const form = /** @type {HTMLFormElement} */ (document.querySelector("form#login"));
        if (form) form.submit();
      })
    ]);
    await p.waitForLoadState("networkidle");
    await ctx.storageState({ path: path.resolve(__dirname, u.file) });
    await ctx.close();
    console.log(`[AUTH] ✓ Đã lưu phiên cho ${u.username} vào ${u.file}`);
  }
}

function initDemoFixtures() {
  console.log("[DB] Đang thiết lập cấu hình và dữ liệu demo...");
  // 1. Cấu hình Quiz 76: Giáo viên duyệt + Thưởng 1-đổi-1 + Leaderboard bật
  runDbQuery("UPDATE quiz_settings SET is_enabled=1, enabled=1, review_mode=\"teacher\", reward_policy=\"one_for_one\", leaderboard_enabled=1, max_runs_per_minute=30 WHERE quiz_id=76");

  // 2. Xóa các đóng góp, lượt chạy và phần thưởng cũ của student1 (user_id=3) trên testcase A
  runDbQuery("DELETE FROM testcase_rewards WHERE receiver_user_id = 3 AND quiz_id = 76");
  runDbQuery("DELETE FROM testcase_contributions WHERE user_id = 3 AND quiz_id = 76");
  runDbQuery("DELETE FROM testcase_runs WHERE user_id = 3 AND question_id = 794");
  runDbQuery("DELETE FROM testcase_canonical_keys WHERE quiz_id = 76 AND question_id = 794");

  // 3. Xóa hạt giống trùng nếu có để testcase A hoàn toàn hợp lệ
  runDbQuery("DELETE FROM question_seed_testcases WHERE quiz_id = 76 AND question_id = 794 AND id = 30");

  // 4. Chuẩn bị trước Testcase B của bạn học (student2, user_id=4) đã được duyệt sẵn:
  // print(find_max([-2, -9, -5])) => Expected: -2
  const fpB = "639f7524945d7d3c52a0a2df3d85d774a3f3a8b4b73f8a6501c6cfd8dfb3b890";
  runDbQuery("DELETE FROM testcase_contributions WHERE quiz_id = 76 AND question_id = 794 AND user_id = 4");
  runDbQuery(`INSERT INTO testcase_contributions 
    (run_id, course_id, quiz_id, question_id, user_id, input_normalized, input_fingerprint, oracle_output, purpose, status, rating, review_comment) 
    VALUES 
    (888777, 2, 76, 794, 4, \"print(find_max([-2, -9, -5]))\", \"${fpB}\", \"-2\", \"Kiểm thử danh sách số nguyên âm\", \"approved\", 5, \"Testcase danh sách số âm chuẩn xác\")`);

  // Portable stack hiện tại dùng các bảng Moodle {local_tce_*}; làm sạch
  // fixture cũ để lần quay nào cũng bắt đầu từ cùng một trạng thái.
  runMoodleQuery("UPDATE mdl_local_tce_quiz_settings SET is_enabled=1, enabled=1, review_mode='teacher', reward_policy='one_for_one', leaderboard_enabled=1, max_runs_per_minute=30 WHERE quiz_id=76");
  runMoodleQuery("DELETE FROM mdl_local_tce_rewards WHERE quiz_id=76 AND question_id=794 AND receiver_user_id IN (3,4)");
  runMoodleQuery("DELETE FROM mdl_local_tce_contributions WHERE quiz_id=76 AND question_id=794 AND user_id IN (3,4)");
  runMoodleQuery("DELETE FROM mdl_local_tce_canonical WHERE quiz_id=76 AND question_id=794");
  runMoodleQuery("DELETE FROM mdl_local_tce_runs WHERE quiz_id=76 AND question_id=794 AND user_id IN (3,4)");
  runMoodleQuery("DELETE FROM mdl_local_tce_revisions WHERE quiz_id=76 AND question_id=794 AND user_id IN (3,4)");

  // Candidate B đã duyệt sẵn để cảnh thưởng 1-đổi-1 luôn có dữ liệu ổn định.
  const now = Math.floor(Date.now() / 1000);
  runMoodleQuery(`INSERT INTO mdl_local_tce_runs
    (course_id, quiz_id, question_id, user_id, input_raw, input_normalized, input_fingerprint,
     predicted_output, student_run_output, oracle_output, student_outcome, oracle_outcome,
     purpose, category, student_code_snapshot, oracle_solution_snapshot, timecreated)
    VALUES
    (2, 76, 794, 4, 'print(find_max([-2, -9, -5]))', 'print(find_max([-2, -9, -5]))', '${fpB}',
     '-2', '-2', '-2', 'success', 'success', 'Kiểm thử danh sách số nguyên âm', 'boundary',
     'def find_max(numbers): return max(numbers)', 'def find_max(lst): return max(lst)', ${now})`);
  runMoodleQuery(`INSERT INTO mdl_local_tce_contributions
    (run_id, course_id, quiz_id, question_id, user_id, input_normalized, input_fingerprint,
     expected_output, oracle_output, purpose, category, status, rating, review_comment, timecreated, timemodified)
    SELECT id, 2, 76, 794, 4, 'print(find_max([-2, -9, -5]))', '${fpB}', '-2', '-2',
      'Kiểm thử danh sách số nguyên âm', 'boundary', 'approved', 5,
      'Testcase danh sách số âm chuẩn xác', ${now}, ${now}
    FROM mdl_local_tce_runs WHERE quiz_id=76 AND question_id=794 AND user_id=4 ORDER BY id DESC LIMIT 1`);
  runMoodleQuery(`INSERT INTO mdl_local_tce_canonical
    (course_id, quiz_id, question_id, input_fingerprint, canonical_contribution_id, timecreated)
    SELECT 2, 76, 794, '${fpB}', id, ${now}
    FROM mdl_local_tce_contributions WHERE quiz_id=76 AND question_id=794 AND user_id=4 ORDER BY id DESC LIMIT 1`);

  console.log("[DB] ✓ Đã thiết lập xong dữ liệu demo và candidate B.");
}

// Bảng lưu thông tin các cảnh để sinh file phụ đề SRT và ghép video
const sceneRecords = [];

async function recordScene(browser, sceneNum, sceneName, targetDurationSec, authFile, sceneTask) {
  console.log(`\n================================================================================`);
  console.log(`[QUAY PHIM] Bắt đầu Cảnh ${sceneNum}: ${sceneName}...`);
  console.log(`================================================================================`);

  const startTime = Date.now();
  const sceneDir = path.resolve(RECORDINGS_DIR, `scene_${String(sceneNum).padStart(2, "0")}`);
  if (!fs.existsSync(sceneDir)) {
    fs.mkdirSync(sceneDir, { recursive: true });
  }

  const context = await browser.newContext({
    baseURL: BASE_URL,
    storageState: path.resolve(__dirname, authFile),
    viewport: { width: 1920, height: 1080 },
    recordVideo: {
      dir: sceneDir,
      size: { width: 1920, height: 1080 }
    }
  });

  const page = await context.newPage();
  await injectComfortableReadingStyles(page);

  // Thực thi kịch bản của cảnh
  await sceneTask(page, context);

  // Đóng page để flush video hoàn tất ra file
  const videoObj = page.video();
  await page.close();
  await context.close();

  const videoPath = await videoObj.path();
  const finalDurationSec = (Date.now() - startTime) / 1000;
  console.log(`[QUAY PHIM] ✓ Cảnh ${sceneNum} hoàn tất: ${finalDurationSec.toFixed(1)}s -> File: ${videoPath}`);

  sceneRecords.push({
    sceneNum,
    sceneName,
    durationSec: finalDurationSec,
    videoPath
  });
}

(async () => {
  console.log("Khởi chạy tiến trình quay video Demo Testcase Exchange (Chuẩn 1080p, 30fps)...");
  const browser = await chromium.launch({
    headless: true,
    args: ["--no-sandbox", "--disable-setuid-sandbox", "--window-size=1920,1080"]
  });

  try {
    await prepareAuthSessions(browser);
    initDemoFixtures();

    // -------------------------------------------------------------------------
    // CẢNH 1: GIỚI THIỆU TỔNG QUAN
    // -------------------------------------------------------------------------
    await recordScene(browser, 1, "Giới thiệu plugin Testcase Exchange", 0, "auth_studentA.json", async (page) => {
      await page.goto("/local/testcase_exchange/index.php?course=2&question=76%3A794");
      await page.waitForLoadState("networkidle");
      await injectComfortableReadingStyles(page);

      await showCaption(
        page,
        "CẢNH 1/12 • GIỚI THIỆU",
        "Tổng quan Testcase Exchange Plugin",
        "Tự tạo testcase, kiểm tra, đóng góp và nhận testcase từ kho chung."
      );
      await page.waitForTimeout(3000);

      // Cuộn quan sát tiêu đề và các khu vực chức năng
      await page.evaluate(() => window.scrollBy({ top: 300, behavior: "smooth" }));
      await page.waitForTimeout(3000);
      await page.evaluate(() => window.scrollBy({ top: 400, behavior: "smooth" }));
      await page.waitForTimeout(3000);
      await page.evaluate(() => window.scrollTo({ top: 0, behavior: "smooth" }));
      await page.waitForTimeout(3000);
    });

    // -------------------------------------------------------------------------
    // CẢNH 2: HAI VAI TRÒ GIÁO VIÊN & SINH VIÊN
    // -------------------------------------------------------------------------
    await recordScene(browser, 2, "Hai vai trò: Giáo viên & Sinh viên", 0, "auth_teacher.json", async (page, context) => {
      // 1. Giáo viên mở Management Hub
      await page.goto("/local/testcase_exchange/index.php?course=2");
      await page.waitForLoadState("networkidle");
      await injectComfortableReadingStyles(page);

      await showCaption(
        page,
        "CẢNH 2/12 • HAI VAI TRÒ",
        "Góc nhìn Giáo viên — Teacher Management Hub",
        "Giáo viên quản lý bài tập và đóng góp. Sinh viên tập trung vào làm bài."
      );
      await page.waitForTimeout(3500);

      // Mở Tab 5: Cấu hình chính sách
      const policyTab = page.locator(".nav-tabs a[href=\"#tab_policy\"]");
      await highlight(policyTab, 1800);
      await policyTab.click();
      await page.waitForTimeout(3000);

      await showCaption(
        page,
        "CẢNH 2/12 • HAI VAI TRÒ",
        "Cấu hình chính sách thực thi & Bảng xếp hạng",
        "Chính sách: Giáo viên duyệt (teacher), Thưởng 1-đổi-1 (one_for_one) & Bảng xếp hạng kích hoạt."
      );
      await page.waitForTimeout(4000);

      // Cuộn xem các tham số cấu hình
      await page.evaluate(() => window.scrollBy({ top: 250, behavior: "smooth" }));
      await page.waitForTimeout(3000);

      // 2. Chuyển sang góc nhìn Sinh viên A (student1)
      await showCaption(
        page,
        "CẢNH 2/12 • HAI VAI TRÒ",
        "Chuyển sang góc nhìn Sinh viên A (student1)",
        "Sinh viên tập trung hoàn toàn vào việc làm bài, không bị phân tâm bởi các tab quản lý."
      );
      await page.waitForTimeout(3000);

      // Mở trang làm bài của sinh viên
      await page.goto("/local/testcase_exchange/index.php?course=2&question=76%3A794");
      await page.waitForLoadState("networkidle");
      await injectComfortableReadingStyles(page);
      await page.waitForTimeout(3500);
    });

    // -------------------------------------------------------------------------
    // CẢNH 3: EXPECTED SAI
    // -------------------------------------------------------------------------
    await recordScene(browser, 3, "Thử nghiệm Expected sai", 0, "auth_studentA.json", async (page) => {
      await page.goto("/local/testcase_exchange/index.php?course=2&question=76%3A794");
      await page.waitForLoadState("networkidle");
      await injectComfortableReadingStyles(page);

      await showCaption(
        page,
        "CẢNH 3/12 • KIỂM THỬ VI SAI",
        "Dự đoán kết quả (Expected) sai lệch",
        "Expected chưa khớp tham chiếu. Testcase chưa đủ điều kiện đóng góp."
      );
      await page.waitForTimeout(3000);

      // 1. Nhập code đúng
      await setStudentCode(page, "def find_max(numbers):\n    return max(numbers)");
      await page.waitForTimeout(3500); // Đủ thời gian đọc code

      // 2. Nhập testcase A
      const inputEditor = page.locator("#test_input");
      await slowType(inputEditor, "print(find_max([3, 8, 5]))");
      await page.waitForTimeout(3000);

      // 3. Nhập expected sai: 5 (thay vì 8)
      const expectedInput = page.locator("#predicted_output");
      await slowType(expectedInput, "5");
      await slowType(page.locator("#purpose"), "Kiểm tra dự đoán kết quả của danh sách số nguyên dương");
      await page.waitForTimeout(3000);

      // 4. Bấm chạy kiểm tra vi sai
      const runBtn = page.locator("#btnSubmitRunTest");
      await highlight(runBtn, 1800);
      await Promise.all([
        page.waitForNavigation({ waitUntil: "domcontentloaded", timeout: 120000 }),
        submitRunForm(page)
      ]);
      await page.waitForLoadState("networkidle");
      await injectComfortableReadingStyles(page);

      const resCard = page.locator(".card:has-text(\"Kết Quả Kiểm Tra\"), .card:has-text(\"Differential Test Results\")").first();
      if (await resCard.count() > 0) {
        await resCard.scrollIntoViewIfNeeded();
      }
      await page.waitForTimeout(3000);

      await showCaption(
        page,
        "CẢNH 3/12 • KIỂM THỬ VI SAI",
        "Kết quả đối chiếu vi sai",
        "Expected chưa khớp tham chiếu (Dự đoán: 5 vs Oracle: 8). Testcase chưa đủ điều kiện đóng góp."
      );
      await page.waitForTimeout(5500);
    });

    // -------------------------------------------------------------------------
    // CẢNH 4: SỬA EXPECTED
    // -------------------------------------------------------------------------
    await recordScene(browser, 4, "Sửa Expected đúng chuẩn", 0, "auth_studentA.json", async (page) => {
      await page.goto("/local/testcase_exchange/index.php?course=2&question=76%3A794");
      await page.waitForLoadState("networkidle");
      await injectComfortableReadingStyles(page);

      await showCaption(
        page,
        "CẢNH 4/12 • ĐIỀU CHỈNH KỲ VỌNG",
        "Sửa dự đoán kết quả mà không cần đổi code",
        "Có thể sửa dự đoán mà giữ nguyên code và input."
      );
      await page.waitForTimeout(3000);

      // Lặp lại dữ liệu cần thiết, chỉ thay expected thành 8.
      await setStudentCode(page, "def find_max(numbers):\n    return max(numbers)");
      await slowType(page.locator("#test_input"), "print(find_max([3, 8, 5]))");
      const expectedInput = page.locator("#predicted_output");
      await expectedInput.fill("");
      await page.waitForTimeout(1000);
      await slowType(expectedInput, "8");
      await slowType(page.locator("#purpose"), "Xác nhận kết quả lớn nhất của danh sách số nguyên dương");
      await page.waitForTimeout(3000);

      // Bấm chạy vi sai
      const runBtn = page.locator("#btnSubmitRunTest");
      await highlight(runBtn, 1800);
      await Promise.all([
        page.waitForNavigation({ waitUntil: "domcontentloaded", timeout: 120000 }),
        submitRunForm(page)
      ]);
      await page.waitForLoadState("networkidle");
      await injectComfortableReadingStyles(page);

      const resCard = page.locator(".card:has-text(\"Kết Quả Kiểm Tra\"), .card:has-text(\"Differential Test Results\")").first();
      if (await resCard.count() > 0) {
        await resCard.scrollIntoViewIfNeeded();
      }
      await page.waitForTimeout(3000);

      await showCaption(
        page,
        "CẢNH 4/12 • ĐIỀU CHỈNH KỲ VỌNG",
        "Dự đoán mới được xác nhận",
        "Dự đoán 8 khớp hoàn toàn với Lời giải mẫu (Oracle). Lưu ý: Chưa thực hiện đóng góp ở cảnh này."
      );
      await page.waitForTimeout(5500);
    });

    // -------------------------------------------------------------------------
    // CẢNH 5: CODE SAI CÓ CHỦ ĐÍCH
    // -------------------------------------------------------------------------
    await recordScene(browser, 5, "Code sai có chủ đích", 0, "auth_studentA.json", async (page) => {
      await page.goto("/local/testcase_exchange/index.php?course=2&question=76%3A794");
      await page.waitForLoadState("networkidle");
      await injectComfortableReadingStyles(page);

      await showCaption(
        page,
        "CẢNH 5/12 • KIỂM ĐỊNH CHƯƠNG TRÌNH",
        "Chương trình sinh viên trả về kết quả sai",
        "Dự đoán đúng chưa đủ: chương trình cũng phải vượt qua testcase."
      );
      await page.waitForTimeout(3000);

      // Đổi sang code sai có chủ đích: return numbers[-1]
      await setStudentCode(page, "def find_max(numbers):\n    return numbers[-1]");
      await page.waitForTimeout(3500);

      // Giữ nguyên input và expected 8
      await slowType(page.locator("#test_input"), "print(find_max([3, 8, 5]))");
      const expectedInput = page.locator("#predicted_output");
      await expectedInput.fill("8");
      await slowType(page.locator("#purpose"), "Kiểm tra chương trình sai khi phần tử lớn nhất ở giữa danh sách");
      await page.waitForTimeout(3000);

      // Bấm chạy vi sai
      const runBtn = page.locator("#btnSubmitRunTest");
      await highlight(runBtn, 1800);
      await Promise.all([
        page.waitForNavigation({ waitUntil: "domcontentloaded", timeout: 120000 }),
        submitRunForm(page)
      ]);
      await page.waitForLoadState("networkidle");
      await injectComfortableReadingStyles(page);

      const resCard = page.locator(".card:has-text(\"Kết Quả Kiểm Tra\"), .card:has-text(\"Differential Test Results\")").first();
      if (await resCard.count() > 0) {
        await resCard.scrollIntoViewIfNeeded();
      }
      await page.waitForTimeout(3000);

      await showCaption(
        page,
        "CẢNH 5/12 • KIỂM ĐỊNH CHƯƠNG TRÌNH",
        "Chương trình không pass testcase",
        "Dự đoán đúng chưa đủ: chương trình cũng phải vượt qua testcase (Sinh viên ra 5 != Oracle 8)."
      );
      await page.waitForTimeout(5500);
    });

    // -------------------------------------------------------------------------
    // CẢNH 6: SỬA CODE ĐÚNG & ĐÓNG GÓP TESTCASE
    // -------------------------------------------------------------------------
    await recordScene(browser, 6, "Sửa code đúng & Đóng góp testcase", 0, "auth_studentA.json", async (page) => {
      await page.goto("/local/testcase_exchange/index.php?course=2&question=76%3A794");
      await page.waitForLoadState("networkidle");
      await injectComfortableReadingStyles(page);

      await showCaption(
        page,
        "CẢNH 6/12 • ĐỀ XUẤT ĐÓNG GÓP",
        "Hoàn thiện bài tập và gửi đóng góp",
        "Đóng góp được tiếp nhận khi cả dự đoán và kết quả chương trình đều phù hợp."
      );
      await page.waitForTimeout(3000);

      // 1. Sửa lại code đúng
      await setStudentCode(page, "def find_max(numbers):\n    return max(numbers)");
      await slowType(page.locator("#test_input"), "print(find_max([3, 8, 5]))");
      await slowType(page.locator("#predicted_output"), "8");
      await slowType(page.locator("#purpose"), "Kiểm thử danh sách số nguyên dương có số lớn nhất ở giữa");
      await page.waitForTimeout(3000);

      // 2. Chạy lại vi sai
      const runBtn = page.locator("#btnSubmitRunTest");
      await highlight(runBtn, 1800);
      await Promise.all([
        page.waitForNavigation({ waitUntil: "domcontentloaded", timeout: 120000 }),
        submitRunForm(page)
      ]);
      await page.waitForLoadState("networkidle");
      await injectComfortableReadingStyles(page);

      // Cuộn xuống khu vực Cổng đóng góp
      const purposeInput = page.locator("#purpose, input[name=\"purpose\"]").first();
      if (await purposeInput.count() > 0) {
        await purposeInput.scrollIntoViewIfNeeded();
        await page.waitForTimeout(1500);
        await slowType(purposeInput, "Kiểm thử danh sách số nguyên dương có số lớn nhất ở giữa");
        await page.waitForTimeout(3000);
      }

      // 3. Bấm nút Đề xuất đóng góp trên lượt mới nhất đủ điều kiện
      const contribBtn = page.locator('button:has-text("Gửi đóng góp"):visible').first();
      await highlight(contribBtn, 1800);
      await Promise.all([
        page.waitForNavigation({ waitUntil: "domcontentloaded", timeout: 120000 }),
        contribBtn.click()
      ]);
      await page.waitForLoadState("networkidle");
      await injectComfortableReadingStyles(page);

      // Mở Tab Đóng góp cá nhân xem trạng thái
      const contribTab = page.locator(".nav-tabs a[href=\"#contributions\"]");
      await highlight(contribTab, 1800);
      await contribTab.click();
      await page.waitForTimeout(3000);

      await showCaption(
        page,
        "CẢNH 6/12 • ĐỀ XUẤT ĐÓNG GÓP",
        "Đóng góp thành công",
        "Đóng góp đã được tiếp nhận, chuyển sang trạng thái \"Chờ giáo viên duyệt\" (submitted)."
      );
      await page.waitForTimeout(5500);
    });

    // -------------------------------------------------------------------------
    // CẢNH 7: GIÁO VIÊN DUYỆT & CHẤM SAO
    // -------------------------------------------------------------------------
    await recordScene(browser, 7, "Giáo viên thẩm định & Chấm sao", 0, "auth_teacher.json", async (page) => {
      await page.goto("/local/testcase_exchange/review.php?course=2");
      await page.waitForLoadState("networkidle");
      await injectComfortableReadingStyles(page);

      await showCaption(
        page,
        "CẢNH 7/12 • THẨM ĐỊNH ĐÓNG GÓP",
        "Giáo viên duyệt và xếp hạng testcase",
        "Giáo viên xem xét đóng góp và phản hồi trực tiếp cho sinh viên."
      );
      await page.waitForTimeout(3500);

      // 1. Mở modal thẩm định của đóng góp student1 vừa tạo ở cảnh trước.
      const reviewBtn = page.locator('tr:has-text("student1") button:has-text("Xem và duyệt")').first();
      await highlight(reviewBtn, 1800);
      await reviewBtn.click();
      await page.locator('.modal.show form:has(select[name="decision"])').waitFor({ state: "visible" });

      // 2. Điền form thẩm định trong modal.
      const reviewForm = page.locator('.modal.show form:has(select[name="decision"])').first();
      await reviewForm.scrollIntoViewIfNeeded();
      await page.waitForTimeout(3000);

      // Chọn Duyệt (Approve)
      const decisionSelect = reviewForm.locator("select[name=\"decision\"]");
      await decisionSelect.selectOption("approved");
      await page.waitForTimeout(1000);

      // Chấm 5 sao
      const ratingSelect = reviewForm.locator("select[name=\"rating\"]");
      await ratingSelect.selectOption("5");
      await page.waitForTimeout(1000);

      // Nhập nhận xét
      const noteInput = reviewForm.locator("textarea[name=\"review_note\"]");
      await slowType(noteInput, "Testcase chuẩn xác, bao quát giá trị lớn nhất ở giữa mảng");
      await page.waitForTimeout(3000);

      // 3. Bấm Lưu Đánh Giá
      const saveBtn = reviewForm.locator("button[type=\"submit\"]");
      await highlight(saveBtn, 1800);
      await Promise.all([
        page.waitForNavigation({ waitUntil: "domcontentloaded", timeout: 120000 }),
        saveBtn.click()
      ]);
      await page.waitForLoadState("networkidle");
      await injectComfortableReadingStyles(page);

      await showCaption(
        page,
        "CẢNH 7/12 • THẨM ĐỊNH ĐÓNG GÓP",
        "Đã duyệt và chấm 5 sao",
        "Đóng góp được phê duyệt thành công, kích hoạt cơ chế thưởng 1-đổi-1 cho sinh viên."
      );
      await page.waitForTimeout(5500);
    });

    // -------------------------------------------------------------------------
    // CẢNH 8: NHẬN TESTCASE THƯỞNG 1-ĐỔI-1 & DÙNG THỬ
    // -------------------------------------------------------------------------
    await recordScene(browser, 8, "Sinh viên nhận Testcase thưởng & Dùng thử", 0, "auth_studentA.json", async (page) => {
      await page.goto("/local/testcase_exchange/index.php?course=2&question=76%3A794");
      await page.waitForLoadState("networkidle");
      await injectComfortableReadingStyles(page);

      await showCaption(
        page,
        "CẢNH 8/12 • PHẦN THƯỞNG 1-ĐỔI-1",
        "Nhận testcase thưởng từ kho bạn học",
        "Testcase thưởng được chọn từ kho đủ điều kiện, loại các testcase đã tiếp cận theo chính sách."
      );
      await page.waitForTimeout(3000);

      // 1. Chuyển sang Tab Phần thưởng
      const rewardsTab = page.locator(".nav-tabs a[href=\"#rewards\"]");
      await highlight(rewardsTab, 1800);
      await rewardsTab.click();
      await page.waitForTimeout(3000);

      // 2. Nhận phần thưởng (nếu có nút Claim)
      const claimBtn = page.locator("button:has-text(\"Claim Reward\"), button:has-text(\"Nhận Thưởng\"), button:has-text(\"🎁\")").first();
      if (await claimBtn.count() > 0 && await claimBtn.isVisible()) {
        await highlight(claimBtn, 1800);
        await Promise.all([
          page.waitForNavigation({ waitUntil: "domcontentloaded", timeout: 120000 }),
          claimBtn.click()
        ]);
        await page.waitForLoadState("networkidle");
        await page.locator(".nav-tabs a[href=\"#rewards\"]").click();
        await page.waitForTimeout(3000);
      }

      // Quan sát testcase B nhận được
      await page.waitForTimeout(3000);

      // 3. Chuyển về Tab Làm bài và thử nghiệm testcase B
      const solveTab = page.locator(".nav-tabs a[href=\"#student_solve\"]");
      await solveTab.click();
      await page.waitForTimeout(3000);

      await page.locator("#test_input").fill("print(find_max([-2, -9, -5]))");
      await page.locator("#predicted_output").fill("-2");
      await page.locator("#purpose").fill("Kiểm thử testcase thưởng với toàn bộ số nguyên âm");
      await page.waitForTimeout(3000);

      const runBtn = page.locator("#btnSubmitRunTest");
      await highlight(runBtn, 1800);
      await Promise.all([
        page.waitForNavigation({ waitUntil: "domcontentloaded", timeout: 120000 }),
        submitRunForm(page)
      ]);
      await page.waitForLoadState("networkidle");
      await injectComfortableReadingStyles(page);

      const resCard = page.locator(".card:has-text(\"Kết Quả Kiểm Tra\"), .card:has-text(\"Differential Test Results\")").first();
      if (await resCard.count() > 0) {
        await resCard.scrollIntoViewIfNeeded();
      }
      await page.waitForTimeout(3000);

      await showCaption(
        page,
        "CẢNH 8/12 • PHẦN THƯỞNG 1-ĐỔI-1",
        "Chạy thử nghiệm testcase thưởng",
        "Chương trình vượt qua testcase thưởng của bạn học (Kết quả: -2, khớp kỳ vọng)."
      );
      await page.waitForTimeout(5500);
    });

    // -------------------------------------------------------------------------
    // CẢNH 9: BẢNG VINH DANH XẾP HẠNG (LEADERBOARD)
    // -------------------------------------------------------------------------
    await recordScene(browser, 9, "Bảng vinh danh xếp hạng đóng góp (Leaderboard)", 0, "auth_studentA.json", async (page) => {
      await page.goto("/local/testcase_exchange/index.php?course=2&question=76%3A794");
      await page.waitForLoadState("networkidle");
      await injectComfortableReadingStyles(page);

      await showCaption(
        page,
        "CẢNH 9/12 • BẢNG VINH DANH XẾP HẠNG",
        "Vinh danh đóng góp xuất sắc trên Leaderboard",
        "Bảng vinh danh ghi nhận đóng góp tích cực của sinh viên qua tổng số sao ⭐ và số lượng testcase được phê duyệt."
      );
      await page.waitForTimeout(3000);

      // 1. Highlight Banner thứ hạng cá nhân ở đầu trang
      const rankBanner = page.locator(".card:has-text(\"Bảng Xếp Hạng\"), .card:has-text(\"Leaderboard\")").first();
      await highlight(rankBanner, 2000);
      await page.waitForTimeout(3000);

      // 2. Mở Tab Bảng Xếp Hạng
      const lbTab = page.locator(".nav-tabs a[href=\"#leaderboard\"]");
      await highlight(lbTab, 1800);
      await lbTab.click();
      await page.waitForTimeout(3000);

      // 3. Highlight dòng xếp hạng của sinh viên hiện tại trong bảng
      const myRow = page.locator("#leaderboard tr.table-primary, #leaderboard tr:has-text(\"student1\")").first();
      if (await myRow.count() > 0) {
        await highlight(myRow, 2200);
      }
      await page.waitForTimeout(3000);

      await showCaption(
        page,
        "CẢNH 9/12 • BẢNG VINH DANH XẾP HẠNG",
        "Huy chương & Tổng số sao tích lũy",
        "Sinh viên A được vinh danh trong Top đầu với 5 ⭐ và huy chương danh dự."
      );
      await page.waitForTimeout(6000);
    });

    // -------------------------------------------------------------------------
    // CẢNH 10: TESTCASE TRÙNG LẶP (STUDENT B)
    // -------------------------------------------------------------------------
    await recordScene(browser, 10, "Phát hiện đóng góp trùng lặp", 0, "auth_studentB.json", async (page) => {
      await page.goto("/local/testcase_exchange/index.php?course=2&question=76%3A794");
      await page.waitForLoadState("networkidle");
      await injectComfortableReadingStyles(page);

      await showCaption(
        page,
        "CẢNH 10/12 • CƠ CHẾ CHỐNG TRÙNG",
        "Sinh viên B đề xuất lại testcase của Sinh viên A",
        "Kho nhận diện đóng góp trùng theo quy tắc chuẩn hóa đã cấu hình."
      );
      await page.waitForTimeout(3000);

      // Sinh viên B nhập Code đúng và đề xuất lại Testcase A đã được duyệt
      await setStudentCode(page, "def find_max(numbers):\n    return max(numbers)");
      await slowType(page.locator("#test_input"), "print(find_max([3, 8, 5]))");
      await slowType(page.locator("#predicted_output"), "8");
      await slowType(page.locator("#purpose"), "Thử đóng góp testcase đã tồn tại để kiểm tra chống trùng lặp");
      await page.waitForTimeout(3000);

      const runBtn = page.locator("#btnSubmitRunTest");
      await highlight(runBtn, 1800);
      await Promise.all([
        page.waitForNavigation({ waitUntil: "domcontentloaded", timeout: 120000 }),
        submitRunForm(page)
      ]);
      await page.waitForLoadState("networkidle");
      await injectComfortableReadingStyles(page);

      const resCard = page.locator(".card:has-text(\"Kết Quả Kiểm Tra\"), .card:has-text(\"Differential Test Results\")").first();
      if (await resCard.count() > 0) {
        await resCard.scrollIntoViewIfNeeded();
      }
      await page.waitForTimeout(3000);

      await showCaption(
        page,
        "CẢNH 10/12 • CƠ CHẾ CHỐNG TRÙNG",
        "Nhận diện trùng lặp tự động",
        "Hệ thống tự động phát hiện testcase này đã có trong kho và ngăn chặn đề xuất trùng lặp."
      );
      await page.waitForTimeout(5500);
    });

    // -------------------------------------------------------------------------
    // CẢNH 11: THEO DÕI HOẠT ĐỘNG & XUẤT DỮ LIỆU
    // -------------------------------------------------------------------------
    await recordScene(browser, 11, "Theo dõi hoạt động sinh viên & Xuất dữ liệu", 0, "auth_teacher.json", async (page) => {
      await page.goto("/local/testcase_exchange/index.php?course=2&tab=activity");
      await page.waitForLoadState("networkidle");
      await injectComfortableReadingStyles(page);

      await showCaption(
        page,
        "CẢNH 11/12 • THEO DÕI & XUẤT DỮ LIỆU",
        "Bảng thống kê hoạt động lớp học",
        "Lịch sử tương tác có thể được xuất để phân tích quá trình sử dụng."
      );
      await page.waitForTimeout(3500);

      // Quan sát bảng thống kê
      await page.evaluate(() => window.scrollBy({ top: 200, behavior: "smooth" }));
      await page.waitForTimeout(3000);

      // Highlight các nút xuất dữ liệu
      const exportCsvBtn = page.locator("a[href*=\"export.php\"][href*=\"format=csv\"]").first();
      await highlight(exportCsvBtn, 1800);
      await page.waitForTimeout(3000);

      await showCaption(
        page,
        "CẢNH 11/12 • THEO DÕI & XUẤT DỮ LIỆU",
        "Xuất dữ liệu dòng thời gian (Timeline CSV)",
        "Dữ liệu phân tích chứa đầy đủ log tương tác, hỗ trợ nghiên cứu và đánh giá sư phạm."
      );
      await page.waitForTimeout(5500);
    });

    // -------------------------------------------------------------------------
    // CẢNH 12: XEM THỬ AN TOÀN (PREVIEW MODE)
    // -------------------------------------------------------------------------
    await recordScene(browser, 12, "Chế độ Xem thử an toàn (Preview Mode)", 0, "auth_teacher.json", async (page) => {
      await page.goto("/local/testcase_exchange/index.php?course=2");
      await page.waitForLoadState("networkidle");
      await injectComfortableReadingStyles(page);

      await showCaption(
        page,
        "CẢNH 12/12 • XEM THỬ AN TOÀN",
        "Giáo viên kích hoạt Chế độ Xem thử",
        "Chế độ xem thử giúp kiểm tra giao diện sinh viên mà không thực hiện lượt chạy."
      );
      await page.waitForTimeout(3000);

      // 1. Bấm nút Xem thử như sinh viên
      const previewBtn = page.locator("a:has-text(\"Xem thử như sinh viên\"), a:has-text(\"Preview as Student\"), a:has-text(\"View as student\")").first();
      await highlight(previewBtn, 1800);
      await Promise.all([
        page.waitForNavigation({ waitUntil: "domcontentloaded", timeout: 120000 }),
        previewBtn.click()
      ]);
      await page.waitForLoadState("networkidle");
      await injectComfortableReadingStyles(page);

      // Quan sát banner vàng cảnh báo
      await page.waitForTimeout(3000);

      // Cuộn xuống xem nút chạy bị vô hiệu hóa
      await page.evaluate(() => window.scrollBy({ top: 350, behavior: "smooth" }));
      await page.waitForTimeout(3000);

      // 2. Bấm nút Quay lại quản lý
      const returnBtn = page.locator("a:has-text(\"Quay lại quản lý\"), a:has-text(\"Return to Management\"), a:has-text(\"Back to management\")").first();
      await highlight(returnBtn, 1800);
      await returnBtn.click();
      await page.waitForTimeout(2500);
      await injectComfortableReadingStyles(page);

      await showCaption(
        page,
        "CẢNH 12/12 • XEM THỬ AN TOÀN",
        "Trở về Management Hub an toàn",
        "Quay lại màn hình quản lý nguyên vẹn, số liệu sinh viên hoàn toàn cách ly và được bảo vệ."
      );
      await page.waitForTimeout(4000);
    });

  } finally {
    await browser.close();
  }

  // ---------------------------------------------------------------------------
  // XUẤT BẢN VIDEO MP4 VÀ PHỤ ĐỀ SRT
  // ---------------------------------------------------------------------------
  console.log("\n================================================================================");
  console.log("[HẬU KỲ] Đang tổng hợp các cảnh và xuất bản video MP4 + SRT...");
  console.log("================================================================================");

  // 1. Tạo file concat list cho ffmpeg
  const concatListPath = path.resolve(RECORDINGS_DIR, "concat_list.txt");
  const concatLines = sceneRecords.map(r => `file \x27${r.videoPath}\x27`).join("\n");
  fs.writeFileSync(concatListPath, concatLines);

  const finalMp4Path = path.resolve(EVIDENCE_DIR, "demo_testcase_exchange_new.mp4");
  console.log("[FFMPEG] Ghép 12 cảnh vào:", finalMp4Path);

  execSync(`ffmpeg -y -f concat -safe 0 -i "${concatListPath}" -c:v h264_videotoolbox -b:v 4500k -pix_fmt yuv420p -r 30 "${finalMp4Path}"`, { stdio: "inherit" });

  // 2. Tạo phụ đề SRT đồng bộ theo thời gian thực tế
  const srtPath = path.resolve(EVIDENCE_DIR, "demo_testcase_exchange_new.srt");
  let currentSec = 0;
  const srtItems = [];

  const captionsData = [
    { num: 1, text: "Tự tạo testcase, kiểm tra, đóng góp và nhận testcase từ kho chung." },
    { num: 2, text: "Giáo viên quản lý bài tập và đóng góp. Sinh viên tập trung vào làm bài." },
    { num: 3, text: "Expected chưa khớp tham chiếu. Testcase chưa đủ điều kiện đóng góp." },
    { num: 4, text: "Có thể sửa dự đoán mà giữ nguyên code và input." },
    { num: 5, text: "Dự đoán đúng chưa đủ: chương trình cũng phải vượt qua testcase." },
    { num: 6, text: "Đóng góp được tiếp nhận khi cả dự đoán và kết quả chương trình đều phù hợp." },
    { num: 7, text: "Giáo viên xem xét đóng góp và phản hồi trực tiếp cho sinh viên." },
    { num: 8, text: "Testcase thưởng được chọn từ kho đủ điều kiện, loại các testcase đã tiếp cận theo chính sách." },
    { num: 9, text: "Bảng vinh danh ghi nhận đóng góp tích cực của sinh viên qua tổng số sao và số lượng testcase được duyệt." },
    { num: 10, text: "Kho nhận diện đóng góp trùng theo quy tắc chuẩn hóa đã cấu hình." },
    { num: 11, text: "Lịch sử tương tác có thể được xuất để phân tích quá trình sử dụng." },
    { num: 12, text: "Chế độ xem thử giúp kiểm tra giao diện sinh viên mà không thực hiện lượt chạy." },
  ];

  function formatSrtTime(totalSec) {
    const hrs = Math.floor(totalSec / 3600);
    const mins = Math.floor((totalSec % 3600) / 60);
    const secs = Math.floor(totalSec % 60);
    const millis = Math.floor((totalSec % 1) * 1000);
    return `${String(hrs).padStart(2, "0")}:${String(mins).padStart(2, "0")}:${String(secs).padStart(2, "0")},${String(millis).padStart(3, "0")}`;
  }

  for (let i = 0; i < sceneRecords.length; i++) {
    const rec = sceneRecords[i];
    const cap = captionsData[i];
    const startStr = formatSrtTime(currentSec + 0.8);
    const endStr = formatSrtTime(currentSec + Math.max(1, rec.durationSec - 0.8));
    srtItems.push(`${i + 1}\n${startStr} --> ${endStr}\n${cap.text}\n`);
    currentSec += rec.durationSec;
  }

  fs.writeFileSync(srtPath, srtItems.join("\n"), "utf8");
  console.log("[SRT] ✓ Đã lưu phụ đề tiếng Việt vào:", srtPath);

  const probeOut = execSync(`ffprobe -v error -show_entries format=duration,size:stream=width,height,r_frame_rate -of default=noprint_wrappers=1 "${finalMp4Path}"`).toString();
  console.log("\n[KIỂM ĐỊNH VIDEO]:\n" + probeOut);

  console.log(`\n================================================================================`);
  console.log(`HOÀN TẤT: Video demo và phụ đề đã được xuất bản thành công!`);
  console.log(`Tổng thời lượng video: ${(currentSec / 60).toFixed(2)} phút (${currentSec.toFixed(1)} giây).`);
  console.log(`================================================================================`);
})();
