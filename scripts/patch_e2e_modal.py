with open('e2e/capture_after_state.js', 'r', encoding='utf-8') as f:
    c = f.read()

# Replace step 7/10 modal interaction
old_step7 = """    // Tìm nút mở review modal
    const openModalBtn = await adminPage.$('.btn-open-review-modal');
    if (openModalBtn) {
        console.log('Tìm thấy nút .btn-open-review-modal, click mở modal...');
        await openModalBtn.click();
        await adminPage.waitForTimeout(600);
        
        // Điền modal review
        const modal = await adminPage.$('#tceReviewModal');
        if (modal) {
            await adminPage.fill('#modal_review_note', 'Dữ liệu kiểm thử rất bao quát, đạt chuẩn chấp thuận!');
            await adminPage.selectOption('#modal_rating', '5');
            await adminPage.screenshot({ path: path.join(OUT_DIR, '06b_review_action_dialog_populated.png') });
            console.log('✅ Đã lưu 06b_review_action_dialog_populated.png');

            // Bấm Chấp thuận trong modal
            const approveBtn = await adminPage.$('#btnModalApprove');
            if (approveBtn) {
                console.log('Bấm nút #btnModalApprove...');
                await Promise.all([
                    adminPage.waitForNavigation({ timeout: 15000 }).catch(() => {}),
                    approveBtn.click()
                ]);
                await adminPage.waitForLoadState('networkidle');
                await adminPage.waitForTimeout(1000);
                await adminPage.screenshot({ path: path.join(OUT_DIR, '06c_review_approved_state_verified.png'), fullPage: true });
                console.log('✅ Đã lưu 06c_review_approved_state_verified.png');
            }
        }
    } else {
        console.log('Không có item pending để mở modal, kiểm tra inline form cũ nếu còn...');
        const inlineForm = await adminPage.$('form:has(button[name="decision"][value="approved"])');
        if (inlineForm) {
            await inlineForm.screenshot({ path: path.join(OUT_DIR, '06b_review_action_dialog_populated.png') });
        }
    }"""

new_step7 = """    // Tìm nút mở review modal theo data-target
    const openModalBtn = await adminPage.$('button[data-target^="#reviewModal_"]');
    if (openModalBtn) {
        console.log('Tìm thấy nút mở review modal, click mở modal...');
        await openModalBtn.click();
        await adminPage.waitForTimeout(800);
        
        // Tìm modal đang mở
        const modal = await adminPage.$('.modal.show, div[id^="reviewModal_"]');
        if (modal) {
            console.log('Modal review đã mở thành công!');
            // Điền quyết định Chấp thuận
            const decisionSelect = await modal.$('select[name="decision"]');
            if (decisionSelect) await decisionSelect.selectOption('approved');

            // Điền 5 sao
            const ratingSelect = await modal.$('select[name="rating"]');
            if (ratingSelect) await ratingSelect.selectOption('5');

            // Điền nhận xét sư phạm
            const noteTextarea = await modal.$('textarea[name="review_note"]');
            if (noteTextarea) await noteTextarea.fill('Testcase rất tốt, dữ liệu kiểm thử bao quát và đạt chuẩn chấp thuận!');

            await adminPage.screenshot({ path: path.join(OUT_DIR, '06b_review_action_dialog_populated.png') });
            console.log('✅ Đã lưu 06b_review_action_dialog_populated.png');

            // Bấm Lưu quyết định thẩm định
            const submitBtn = await modal.$('button[type="submit"]');
            if (submitBtn) {
                console.log('Bấm nút Lưu quyết định thẩm định...');
                await Promise.all([
                    adminPage.waitForNavigation({ timeout: 15000 }).catch(() => {}),
                    submitBtn.click()
                ]);
                await adminPage.waitForLoadState('networkidle');
                await adminPage.waitForTimeout(1000);
                await adminPage.screenshot({ path: path.join(OUT_DIR, '06c_review_approved_state_verified.png'), fullPage: true });
                console.log('✅ Đã lưu 06c_review_approved_state_verified.png');
            }
        }
    } else {
        console.log('Chưa tìm thấy nút mở review modal.');
    }"""

c = c.replace(old_step7, new_step7)

with open('e2e/capture_after_state.js', 'w', encoding='utf-8') as f:
    f.write(c)

print('Updated step 7 modal matching in capture_after_state.js!')
