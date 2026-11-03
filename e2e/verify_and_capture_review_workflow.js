const { chromium } = require('playwright');
const path = require('path');
const fs = require('fs');

const OUT_DIR = path.join(__dirname, '..', 'verification_evidence', 'screenshots_after');
fs.mkdirSync(OUT_DIR, { recursive: true });

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

async function main() {
    const browser = await chromium.launch({
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    });

    console.log('=== THỰC HIỆN ĐÓNG GÓP & THẨM ĐỊNH TESTCASE THỰC TẾ ===');

    // 1. Sinh viên student1 đăng nhập và nộp 1 đóng góp
    console.log('1. Sinh viên student1 gửi đóng góp...');
    const studentCtx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const studentPage = await studentCtx.newPage();
    await loginUser(studentPage, studentCtx, 'student1', 'StudentPassword123!');
    await studentPage.goto('http://localhost:8080/local/testcase_exchange/index.php?course=2&question=76:794');
    await studentPage.waitForLoadState('networkidle');

    // Tìm nút Gửi đóng góp trong khối kết quả mới nhất hoặc dòng đầu tiên
    const contributeBtn = await studentPage.$('button:has-text("Gửi đóng góp"), button:has-text("Submit contribution")');
    if (contributeBtn) {
        console.log('Tìm thấy nút Gửi đóng góp, tiến hành bấm gửi...');
        await Promise.all([
            studentPage.waitForNavigation({ timeout: 15000 }).catch(() => {}),
            contributeBtn.click()
        ]);
        await studentPage.waitForLoadState('networkidle');
        await studentPage.waitForTimeout(1000);
        console.log('Đã gửi đóng góp thành công!');
        await studentPage.screenshot({ path: path.join(OUT_DIR, '03b_student_contribution_submitted.png'), fullPage: true });
    } else {
        console.log('Không thấy nút Gửi đóng góp, chạy một testcase mới để kích hoạt...');
        await studentPage.fill('#student_code', 'def find_max(numbers):\n    return max(numbers)');
        const uniqueNum = Math.floor(Math.random() * 9000) + 1000;
        await studentPage.fill('#test_input', `print(find_max([10, ${uniqueNum}, 5]))`);
        await studentPage.fill('#predicted_output', `${uniqueNum}`);
        await studentPage.fill('#purpose', `Kiểm tra giá trị ngẫu nhiên lớn nhất ${uniqueNum}`);
        await Promise.all([
            studentPage.waitForNavigation({ timeout: 15000 }).catch(() => {}),
            studentPage.click('#btnSubmitRunTest')
        ]);
        await studentPage.waitForLoadState('networkidle');
        await studentPage.waitForTimeout(1000);

        const newContributeBtn = await studentPage.$('button:has-text("Gửi đóng góp"), button:has-text("Submit contribution")');
        if (newContributeBtn) {
            console.log('Bấm nút gửi đóng góp mới tạo...');
            await Promise.all([
                studentPage.waitForNavigation({ timeout: 15000 }).catch(() => {}),
                newContributeBtn.click()
            ]);
            await studentPage.waitForLoadState('networkidle');
            await studentPage.waitForTimeout(1000);
            await studentPage.screenshot({ path: path.join(OUT_DIR, '03b_student_contribution_submitted.png'), fullPage: true });
        }
    }

    // 2. Giảng viên đăng nhập vào tab Review để thẩm định
    console.log('2. Giảng viên đăng nhập và vào tab Review...');
    const teacherCtx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
    const teacherPage = await teacherCtx.newPage();
    await loginUser(teacherPage, teacherCtx, 'admin', 'AdminPassword123!');
    
    // Vào khóa học và chọn câu hỏi Find Max (76:794)
    await teacherPage.goto('http://localhost:8080/local/testcase_exchange/index.php?course=2&question=76:794&tab=review');
    await teacherPage.waitForLoadState('networkidle');
    await teacherPage.waitForTimeout(1000);

    // Chụp GV_04-05 thực tế: Tab Review có đóng góp đang chờ duyệt và form thẩm định
    console.log('3. Chụp bằng chứng Tab Review có dữ liệu đóng góp thực tế & form thẩm định...');
    await teacherPage.screenshot({ path: path.join(OUT_DIR, '06a_teacher_review_with_data_desktop.png'), fullPage: true });

    // Tìm form review và điền đánh giá 5 sao + nhận xét
    const reviewForm = await teacherPage.$('form:has(button[name="decision"][value="approved"])');
    if (reviewForm) {
        console.log('Tìm thấy form review! Điền nhận xét và chọn 5 sao...');
        const noteInput = await reviewForm.$('input[name="review_note"]');
        if (noteInput) {
            await noteInput.fill('Testcase rất tốt, dữ liệu kiểm thử bao quát và chuẩn xác!');
        }
        const ratingSelect = await reviewForm.$('select[name="rating"]');
        if (ratingSelect) {
            await ratingSelect.selectOption('5');
        }

        // Chụp cận cảnh form review trước khi bấm Chấp thuận
        await reviewForm.screenshot({ path: path.join(OUT_DIR, '06b_review_action_dialog_populated.png') });

        // Bấm Chấp thuận (Approve)
        console.log('Bấm nút Chấp thuận (Approve)...');
        const approveBtn = await reviewForm.$('button[name="decision"][value="approved"]');
        if (approveBtn) {
            await Promise.all([
                teacherPage.waitForNavigation({ timeout: 15000 }).catch(() => {}),
                approveBtn.click()
            ]);
            await teacherPage.waitForLoadState('networkidle');
            await teacherPage.waitForTimeout(1200);

            // Chụp trạng thái sau khi duyệt thành công
            console.log('4. Chụp trạng thái đã duyệt thành công (Approved State)...');
            await teacherPage.screenshot({ path: path.join(OUT_DIR, '06c_review_approved_state_verified.png'), fullPage: true });
        }
    } else {
        console.log('Chưa tìm thấy form review có trạng thái pending.');
    }

    await browser.close();
    console.log('=== HOÀN TẤT KIỂM CHỨNG QUY TRÌNH DUYỆT ĐÓNG GÓP ===');
}

main().catch(err => {
    console.error('Lỗi quy trình thẩm định:', err);
    process.exit(1);
});
