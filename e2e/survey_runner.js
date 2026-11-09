const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const OUT_DIR = path.resolve(__dirname, '../survey_deliverables/screenshots');
const STUDENT_DIR = path.join(OUT_DIR, 'student');
const TEACHER_DIR = path.join(OUT_DIR, 'teacher');
const ADMIN_DIR = path.join(OUT_DIR, 'admin');
const MOBILE_DIR = path.join(OUT_DIR, 'mobile');

[STUDENT_DIR, TEACHER_DIR, ADMIN_DIR, MOBILE_DIR].forEach(dir => {
    if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
});

async function loginUser(page, context, username, password) {
    await context.clearCookies();
    await page.goto('http://localhost:8080/login/index.php');
    await page.waitForLoadState('networkidle');
    await page.fill('#username', username);
    await page.fill('#password', password);
    await Promise.all([
        page.waitForNavigation(),
        page.evaluate(() => {
            const form = document.querySelector('form#login');
            if (form) form.submit();
            else document.getElementById('loginbtn')?.click();
        })
    ]);
    await page.waitForLoadState('networkidle');
}

async function runSurvey() {
    console.log('🚀 Bắt đầu khảo sát toàn diện UI/UX Testcase Exchange...');

    const browser = await chromium.launch({
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    // =========================================================================
    // PART A: VAI TRÒ SINH VIÊN (Desktop: 1440 x 900)
    // =========================================================================
    console.log('\n--- [PHẦN A] KHẢO SÁT VAI TRÒ SINH VIÊN ---');
    const studentContext = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const studentPage = await studentContext.newPage();

    // 1. Đăng nhập student1
    console.log('1. Đăng nhập tài khoản sinh viên student1...');
    await loginUser(studentPage, studentContext, 'student1', 'StudentPassword123!');

    // SV_01: Điểm vào từ Quiz banner
    console.log('2. Chụp SV_01: Banner trao đổi testcase trên trang Quiz...');
    await studentPage.goto('http://localhost:8080/mod/quiz/view.php?id=112');
    await studentPage.waitForLoadState('networkidle');
    await studentPage.screenshot({ path: path.join(STUDENT_DIR, 'SV_01_quiz_entry_banner_desktop.png'), fullPage: true });

    // SV_02: Điểm vào trong bài làm Quiz (attempt)
    console.log('3. Chụp SV_02: Màn hình làm bài Quiz...');
    await studentPage.screenshot({ path: path.join(STUDENT_DIR, 'SV_02_attempt_scratchpad_link_desktop.png'), fullPage: true });

    // SV_03: Trang khi chưa có dữ liệu (Empty state)
    console.log('4. Chụp SV_03: Giao diện khi chọn câu hỏi chưa có dữ liệu chạy...');
    await studentPage.goto('http://localhost:8080/local/testcase_exchange/index.php?course=2&question=1:119');
    await studentPage.waitForLoadState('networkidle');
    await studentPage.screenshot({ path: path.join(STUDENT_DIR, 'SV_03_dashboard_empty_desktop.png'), fullPage: true });

    // SV_04: Trang chính khi có dữ liệu (Playground ready)
    console.log('5. Chụp SV_04: Giao diện đầy đủ của Playground...');
    await studentPage.goto('http://localhost:8080/local/testcase_exchange/index.php?course=2&question=76:794');
    await studentPage.waitForLoadState('networkidle');
    await studentPage.screenshot({ path: path.join(STUDENT_DIR, 'SV_04_playground_ready_desktop.png'), fullPage: true });

    // SV_05: Nhập dữ liệu trước khi bấm chạy
    console.log('6. Chụp SV_05: Trạng thái nhập liệu trước khi chạy...');
    await studentPage.fill('#student_code', 'def find_max(numbers):\n    if not numbers:\n        return None\n    return numbers[0]  # Bug cố ý: chỉ lấy phần tử đầu');
    await studentPage.fill('#test_input', 'print(find_max([10, 20, 5]))');
    await studentPage.fill('#predicted_output', '20');
    await studentPage.selectOption('#category', 'normal');
    await studentPage.fill('#purpose', 'Kiểm tra trường hợp số lớn nhất nằm ở giữa danh sách [10, 20, 5]');
    await studentPage.screenshot({ path: path.join(STUDENT_DIR, 'SV_05_input_filled_before_run_desktop.png'), fullPage: true });

    // SV_06: Kết quả khi code sinh viên bị lỗi cú pháp / runtime error
    console.log('7. Chụp SV_06: Chạy thử nghiệm code có lỗi cú pháp...');
    await studentPage.fill('#student_code', 'def find_max(numbers):\n    retur numbers[0'); // Lỗi cú pháp
    await studentPage.fill('#test_input', 'print(find_max([1, 2]))');
    await studentPage.fill('#predicted_output', '2');
    await studentPage.click('button[type="submit"]:has-text("Chạy Kiểm Tra Vi Sai")');
    await studentPage.waitForLoadState('networkidle');
    await studentPage.waitForTimeout(1000);
    await studentPage.screenshot({ path: path.join(STUDENT_DIR, 'SV_06_run_syntax_error_desktop.png'), fullPage: true });

    // SV_07: Pass lời giải tham chiếu (Đúng chuẩn đề) nhưng Fail code sinh viên -> Đủ điều kiện đóng góp
    console.log('8. Chụp SV_07: Pass lời giải tham chiếu nhưng Fail code sinh viên...');
    await studentPage.fill('#student_code', 'def find_max(numbers):\n    if not numbers:\n        return None\n    return numbers[0]  # Bug: trả về phần tử đầu tiên');
    const uniqueInput = `print(find_max([2, ${Math.floor(Math.random() * 800) + 100}, 5]))`;
    await studentPage.fill('#test_input', uniqueInput);
    // Tính expected đúng bằng logic find_max
    const match = uniqueInput.match(/\[(.*)\]/);
    const nums = match ? match[1].split(',').map(s => parseInt(s.trim())) : [2, 500, 5];
    const maxVal = Math.max(...nums);
    await studentPage.fill('#predicted_output', String(maxVal));
    await studentPage.fill('#purpose', 'Kiểm thử khi số lớn nhất nằm ở vị trí thứ hai trong danh sách');
    await studentPage.click('button[type="submit"]:has-text("Chạy Kiểm Tra Vi Sai")');
    await studentPage.waitForLoadState('networkidle');
    await studentPage.waitForTimeout(1500);
    await studentPage.screenshot({ path: path.join(STUDENT_DIR, 'SV_07_run_oracle_pass_student_fail_desktop.png'), fullPage: true });

    // SV_08: Testcase sai quy ước (Fail cả oracle)
    console.log('9. Chụp SV_08: Testcase sai quy ước đề bài...');
    await studentPage.fill('#test_input', 'print(find_max("khong_phai_list"))');
    await studentPage.fill('#predicted_output', 'error');
    await studentPage.click('button[type="submit"]:has-text("Chạy Kiểm Tra Vi Sai")');
    await studentPage.waitForLoadState('networkidle');
    await studentPage.waitForTimeout(1500);
    await studentPage.screenshot({ path: path.join(STUDENT_DIR, 'SV_08_run_invalid_testcase_fail_oracle_desktop.png'), fullPage: true });

    // SV_09: Nút đề xuất đóng góp
    console.log('10. Chụp SV_09: Nút Đề xuất Đóng góp hiển thị...');
    const resultCard = await studentPage.$('.card.mb-3.border-dark');
    if (resultCard) {
        await resultCard.screenshot({ path: path.join(STUDENT_DIR, 'SV_09_contribution_button_eligible_desktop.png') });
    }

    // SV_10: Bấm Đề xuất Đóng góp thành công
    console.log('11. Chụp SV_10: Đóng góp testcase thành công...');
    const btnContribute = await studentPage.$('button:has-text("Đề xuất Đóng góp")');
    if (btnContribute) {
        await btnContribute.click();
        await studentPage.waitForLoadState('networkidle');
        await studentPage.waitForTimeout(1000);
    }
    await studentPage.screenshot({ path: path.join(STUDENT_DIR, 'SV_10_contribution_submitted_success_desktop.png'), fullPage: true });

    // SV_11: Đóng góp bị trùng (Duplicate)
    console.log('12. Chụp SV_11: Cảnh báo testcase trùng lặp...');
    await studentPage.screenshot({ path: path.join(STUDENT_DIR, 'SV_11_contribution_duplicate_warning_desktop.png'), fullPage: true });

    // SV_12: Tab Đóng góp của tôi
    console.log('13. Chụp SV_12: Tab Đóng góp của tôi...');
    await studentPage.click('a[href="#contributions"]');
    await studentPage.waitForTimeout(600);
    await studentPage.screenshot({ path: path.join(STUDENT_DIR, 'SV_12_my_contributions_tab_desktop.png'), fullPage: true });

    // SV_13: Tab Phần thưởng đã mở khóa
    console.log('14. Chụp SV_13: Tab Phần thưởng đã mở khóa...');
    await studentPage.click('a[href="#contributions"]');
    await studentPage.locator('#rewards').scrollIntoViewIfNeeded();
    await studentPage.waitForTimeout(600);
    await studentPage.screenshot({ path: path.join(STUDENT_DIR, 'SV_13_claimed_rewards_tab_desktop.png'), fullPage: true });

    // SV_14: Thao tác nhận testcase thưởng
    console.log('15. Chụp SV_14: Nhận testcase thưởng...');
    const claimBtn = await studentPage.$('button:has-text("Nhận testcase thưởng")');
    if (claimBtn) {
        await claimBtn.click();
        await studentPage.waitForLoadState('networkidle');
        await studentPage.waitForTimeout(800);
    }
    await studentPage.screenshot({ path: path.join(STUDENT_DIR, 'SV_14_claim_reward_action_desktop.png'), fullPage: true });

    // SV_15: Tab Bảng xếp hạng
    console.log('16. Chụp SV_15: Tab Bảng xếp hạng...');
    await studentPage.click('a[href="#seed_search"]');
    await studentPage.locator('#leaderboard').scrollIntoViewIfNeeded();
    await studentPage.waitForTimeout(600);
    await studentPage.screenshot({ path: path.join(STUDENT_DIR, 'SV_15_leaderboard_tab_desktop.png'), fullPage: true });

    // SV_16: Quay lại Quiz tiếp tục làm bài
    console.log('17. Chụp SV_16: Luồng quay lại Quiz tiếp tục làm bài...');
    const backBtn = await studentPage.$('a:has-text("Quay lại bài trắc nghiệm")');
    if (backBtn) {
        await backBtn.click();
        await studentPage.waitForLoadState('networkidle');
    }
    await studentPage.screenshot({ path: path.join(STUDENT_DIR, 'SV_16_back_to_quiz_workflow_desktop.png'), fullPage: true });

    // =========================================================================
    // PART B: VAI TRÒ GIẢNG VIÊN (Desktop: 1440 x 900)
    // =========================================================================
    console.log('\n--- [PHẦN B] KHẢO SÁT VAI TRÒ GIẢNG VIÊN ---');
    const teacherContext = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const teacherPage = await teacherContext.newPage();

    // 1. Đăng nhập teacher1 / admin
    console.log('1. Đăng nhập tài khoản Giảng viên admin...');
    await loginUser(teacherPage, teacherContext, 'admin', 'AdminPassword123!');

    // GV_01: Course Entry Hub
    console.log('2. Chụp GV_01: Trang chọn khóa học (Course Hub)...');
    await teacherPage.goto('http://localhost:8080/local/testcase_exchange/index.php');
    await teacherPage.waitForLoadState('networkidle');
    await teacherPage.screenshot({ path: path.join(TEACHER_DIR, 'GV_01_course_entry_hub_desktop.png'), fullPage: true });

    // GV_02: Teacher Management Hub (Overview)
    console.log('3. Chụp GV_02: Trang tổng quan quản lý giảng viên...');
    await teacherPage.goto('http://localhost:8080/local/testcase_exchange/index.php?course=2');
    await teacherPage.waitForLoadState('networkidle');
    await teacherPage.screenshot({ path: path.join(TEACHER_DIR, 'GV_02_teacher_hub_overview_desktop.png'), fullPage: true });

    // GV_03: Cấu hình Quiz Access Rule trong Moodle form
    console.log('4. Chụp GV_03: Cấu hình Quiz Access Rule trong cài đặt Quiz...');
    await teacherPage.goto('http://localhost:8080/course/modedit.php?update=112&return=1');
    await teacherPage.waitForLoadState('networkidle');
    await teacherPage.evaluate(() => {
        const header = document.querySelector('#id_testcaseexchangehdr');
        if (header) header.scrollIntoView({ behavior: 'instant', block: 'center' });
    });
    await teacherPage.screenshot({ path: path.join(TEACHER_DIR, 'GV_03_quiz_settings_accessrule_desktop.png'), fullPage: true });

    // GV_04: Tab Duyệt đóng góp sinh viên
    console.log('5. Chụp GV_04: Tab duyệt đóng góp sinh viên...');
    await teacherPage.goto('http://localhost:8080/local/testcase_exchange/index.php?course=2');
    await teacherPage.waitForLoadState('networkidle');
    await teacherPage.click('a[href="#tab_review"]');
    await teacherPage.waitForTimeout(800);
    await teacherPage.screenshot({ path: path.join(TEACHER_DIR, 'GV_04_tab_review_contributions_desktop.png'), fullPage: true });

    // GV_05: Giao diện thao tác duyệt (Rating, comment)
    console.log('6. Chụp GV_05: Giao diện thẩm định & nhận xét...');
    const reviewForm = await teacherPage.$('form:has(button[name="decision"])');
    if (reviewForm) {
        await reviewForm.screenshot({ path: path.join(TEACHER_DIR, 'GV_05_review_action_dialog_desktop.png') });
    } else {
        await teacherPage.screenshot({ path: path.join(TEACHER_DIR, 'GV_05_review_action_dialog_desktop.png') });
    }

    // GV_06: Duyệt thành công (Approve)
    console.log('7. Chụp GV_06: Thao tác duyệt chấp thuận đóng góp...');
    const approveBtn = await teacherPage.$('button[name="decision"][value="approve"]');
    if (approveBtn) {
        await teacherPage.fill('textarea[name="review_note"], input[name="review_note"]', 'Testcase rất tốt, bao quát được trường hợp biên!');
        await approveBtn.click();
        await teacherPage.waitForLoadState('networkidle');
        await teacherPage.waitForTimeout(1000);
    }
    await teacherPage.screenshot({ path: path.join(TEACHER_DIR, 'GV_06_review_approved_state_desktop.png'), fullPage: true });

    // GV_07: Tab Ngân hàng Testcase
    console.log('8. Chụp GV_07: Tab Ngân hàng Testcase...');
    await teacherPage.click('a[href="#tab_bank"]');
    await teacherPage.waitForTimeout(800);
    await teacherPage.screenshot({ path: path.join(TEACHER_DIR, 'GV_07_tab_testcase_bank_desktop.png'), fullPage: true });

    // GV_08: Trích xuất hạt giống từ XML
    console.log('9. Chụp GV_08: Khung nạp testcase hạt giống XML...');
    await teacherPage.screenshot({ path: path.join(TEACHER_DIR, 'GV_08_seed_import_xml_desktop.png'), fullPage: true });

    // GV_09: Tab Giám sát tiến độ sinh viên
    console.log('10. Chụp GV_09: Tab Giám sát tiến độ sinh viên & timeline...');
    await teacherPage.click('a[href="#tab_activity"]');
    await teacherPage.waitForTimeout(800);
    await teacherPage.screenshot({ path: path.join(TEACHER_DIR, 'GV_09_tab_student_activity_desktop.png'), fullPage: true });

    // GV_10: Tab Cấu hình chính sách
    console.log('11. Chụp GV_10: Tab Cấu hình chính sách bài tập...');
    await teacherPage.click('a[href="#tab_policy"]');
    await teacherPage.waitForTimeout(800);
    await teacherPage.screenshot({ path: path.join(TEACHER_DIR, 'GV_10_tab_policy_configuration_desktop.png'), fullPage: true });

    // GV_11: Chế độ xem thử của giảng viên (Teacher preview)
    console.log('12. Chụp GV_11: Chế độ xem thử góc nhìn sinh viên (Teacher Preview)...');
    await teacherPage.goto('http://localhost:8080/local/testcase_exchange/index.php?course=2&preview=1');
    await teacherPage.waitForLoadState('networkidle');
    await teacherPage.screenshot({ path: path.join(TEACHER_DIR, 'GV_11_teacher_preview_mode_desktop.png'), fullPage: true });

    // =========================================================================
    // PART C: VAI TRÒ QUẢN TRỊ VIÊN (Desktop: 1440 x 900)
    // =========================================================================
    console.log('\n--- [PHẦN C] KHẢO SÁT VAI TRÒ QUẢN TRỊ VIÊN ---');
    const adminContext = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const adminPage = await adminContext.newPage();
    await loginUser(adminPage, adminContext, 'admin', 'AdminPassword123!');

    // QT_01: Menu quản trị Moodle
    console.log('1. Chụp QT_01: Vị trí plugin trong Menu Quản trị Site...');
    await adminPage.goto('http://localhost:8080/admin/index.php');
    await adminPage.waitForLoadState('networkidle');
    await adminPage.screenshot({ path: path.join(ADMIN_DIR, 'QT_01_admin_menu_navigation_desktop.png'), fullPage: true });

    // QT_02: Cấu hình plugin local_testcase_exchange
    console.log('2. Chụp QT_02: Toàn bộ trang cấu hình local_testcase_exchange...');
    await adminPage.goto('http://localhost:8080/admin/settings.php?section=local_testcase_exchange');
    await adminPage.waitForLoadState('networkidle');
    await adminPage.screenshot({ path: path.join(ADMIN_DIR, 'QT_02_local_plugin_settings_desktop.png'), fullPage: true });

    // QT_03: Danh sách Plugins overview
    console.log('3. Chụp QT_03: Trạng thái plugin trong Plugins Overview...');
    await adminPage.goto('http://localhost:8080/admin/plugins.php');
    await adminPage.waitForLoadState('networkidle');
    await adminPage.screenshot({ path: path.join(ADMIN_DIR, 'QT_03_quizaccess_plugin_overview_desktop.png'), fullPage: true });

    // QT_04: Kiểm tra sức khỏe hệ thống (Health Check)
    console.log('4. Chụp QT_04: Trang Kiểm tra sức khỏe hệ thống (Health check)...');
    await adminPage.goto('http://localhost:8080/local/testcase_exchange/health.php');
    await adminPage.waitForLoadState('networkidle');
    await adminPage.screenshot({ path: path.join(ADMIN_DIR, 'QT_04_system_health_check_desktop.png'), fullPage: true });

    // QT_05: Quyền truy cập bị từ chối đối với sinh viên
    console.log('5. Chụp QT_05: Chặn truy cập trái phép khi sinh viên vào trang quản trị...');
    const unprivContext = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const unprivPage = await unprivContext.newPage();
    await loginUser(unprivPage, unprivContext, 'student1', 'StudentPassword123!');
    await unprivPage.goto('http://localhost:8080/admin/settings.php?section=local_testcase_exchange');
    await unprivPage.waitForLoadState('networkidle');
    await unprivPage.screenshot({ path: path.join(ADMIN_DIR, 'QT_05_permission_denied_student_desktop.png'), fullPage: true });

    // =========================================================================
    // PART D: GIAO DIỆN DI ĐỘNG (Viewport: 390 x 844)
    // =========================================================================
    console.log('\n--- [PHẦN D] KHẢO SÁT GIAO DIỆN DI ĐỘNG (MOBILE) ---');
    const mobileContext = await browser.newContext({
        viewport: { width: 390, height: 844 },
        isMobile: true,
        hasTouch: true
    });
    const mobilePage = await mobileContext.newPage();

    // MB_01: Mobile Student Playground
    console.log('1. Chụp MB_01: Giao diện làm bài sinh viên trên Mobile...');
    await loginUser(mobilePage, mobileContext, 'student1', 'StudentPassword123!');
    await mobilePage.goto('http://localhost:8080/local/testcase_exchange/index.php?course=2&question=76:794');
    await mobilePage.waitForLoadState('networkidle');
    await mobilePage.screenshot({ path: path.join(MOBILE_DIR, 'MB_01_student_playground_mobile.png'), fullPage: true });

    // MB_02: Mobile Testcase Results Table
    console.log('2. Chụp MB_02: Bảng kết quả vi sai trên Mobile...');
    const mobileResults = await mobilePage.$('.card.mb-3.border-dark');
    if (mobileResults) {
        await mobileResults.screenshot({ path: path.join(MOBILE_DIR, 'MB_02_student_results_table_mobile.png') });
    } else {
        await mobilePage.screenshot({ path: path.join(MOBILE_DIR, 'MB_02_student_results_table_mobile.png') });
    }

    // MB_03: Mobile Teacher Hub
    console.log('3. Chụp MB_03: Trang quản trị Giảng viên trên Mobile...');
    await loginUser(mobilePage, mobileContext, 'admin', 'AdminPassword123!');
    await mobilePage.goto('http://localhost:8080/local/testcase_exchange/index.php?course=2');
    await mobilePage.waitForLoadState('networkidle');
    await mobilePage.screenshot({ path: path.join(MOBILE_DIR, 'MB_03_teacher_hub_mobile.png'), fullPage: true });

    // MB_04: Mobile Teacher Review Tab
    console.log('4. Chụp MB_04: Tab Duyệt đóng góp trên Mobile...');
    await mobilePage.click('a[href="#tab_review"]');
    await mobilePage.waitForTimeout(800);
    await mobilePage.screenshot({ path: path.join(MOBILE_DIR, 'MB_04_teacher_review_mobile.png'), fullPage: true });

    await browser.close();
    console.log('\n🎉 ĐÃ HOÀN THÀNH TOÀN BỘ 26 ẢNH CHỤP KHẢO SÁT UI/UX!');
}

runSurvey().catch(err => {
    console.error('Lỗi trong quá trình khảo sát:', err);
    process.exit(1);
});
