import asyncio
import time
from playwright.async_api import async_playwright

async def run_simulation():
    print("🚀 Bắt đầu kịch bản mô phỏng tương tác của Giảng viên trên Moodle...")
    async with async_playwright() as p:
        # Launch headed browser so user can see what's happening
        browser = await p.chromium.launch(headless=False, slow_mo=1200)
        context = await browser.new_context(viewport={"width": 1440, "height": 900})
        page = await context.new_page()

        # Step 1: Đăng nhập với tài khoản Giáo viên / Admin
        print("\n[Bước 1/6] Đăng nhập vào Moodle bằng tài khoản Giảng viên / Quản trị viên...")
        await page.goto("http://localhost:8080/login/index.php")
        await page.fill("#username", "admin")
        await page.fill("#password", "AdminPassword123!")
        await page.click("#loginbtn")
        await page.wait_for_load_state("networkidle")
        print("  ✓ Đăng nhập thành công!")
        await asyncio.sleep(2)

        # Step 2: Truy cập Course Hub và vào Khoá học AI-PY-PILOT
        print("\n[Bước 2/6] Mở Trung tâm Điều phối Testcase (Course Hub)...")
        await page.goto("http://localhost:8080/local/testcase_exchange/index.php")
        await page.wait_for_load_state("networkidle")
        await asyncio.sleep(2)

        print("  → Chọn khoá học mục tiêu: AI-PY-PILOT (ID: 4)...")
        await page.goto("http://localhost:8080/local/testcase_exchange/index.php?course=4")
        await page.wait_for_load_state("networkidle")
        await asyncio.sleep(3)

        # Step 3: Xem Bảng Xếp Hạng & Thống kê Chất lượng của Sinh viên
        print("\n[Bước 3/6] Kiểm tra Bảng Xếp Hạng & Hoạt động đóng góp của Sinh viên trong khoá...")
        leaderboard_tab = page.locator("#tab-leaderboard-tab")
        if await leaderboard_tab.count() > 0:
            await leaderboard_tab.click()
            print("  ✓ Đã chuyển sang tab Bảng Xếp Hạng (Ưu tiên Tổng Sao Đánh Giá ⭐)")
            await asyncio.sleep(4)

        contributions_tab = page.locator("#tab-contributions-tab")
        if await contributions_tab.count() > 0:
            await contributions_tab.click()
            print("  ✓ Đã chuyển sang tab Danh sách Testcase Đóng Góp")
            await asyncio.sleep(3)

        # Step 4: Chuyển sang Trang Thiết lập & Quản lý 3 Tập Testcase
        print("\n[Bước 4/6] Vào trang Thiết lập Chính sách & Phân tầng 3 Tập Testcase...")
        await page.goto("http://localhost:8080/local/testcase_exchange/policy.php?course=4")
        await page.wait_for_load_state("networkidle")
        await asyncio.sleep(3)

        # Cuộn chuột để thấy khu vực Quản lý 3 Tập Testcase
        await page.evaluate("window.scrollTo(0, document.body.scrollHeight / 2)")
        await asyncio.sleep(2)

        print("  → Kiểm tra nút Đồng bộ Ngân hàng Đề 3 Tập (Seed Testcases)...")
        sync_btn = page.locator("button[name='action'][value='sync_seeds']")
        if await sync_btn.count() > 0:
            print("  ✓ Tìm thấy nút 'Đồng bộ Seed Testcase từ CodeRunner'")
            # Giảng viên click đồng bộ
            await sync_btn.click()
            await page.wait_for_load_state("networkidle")
            print("  ✓ Đã thực thi đồng bộ dữ liệu hạt giống Suite 2 (Reward Bank) thành công!")
            await asyncio.sleep(3)

        # Step 5: Vào Trang Duyệt Testcase & Đánh giá Sao (Teacher Review)
        print("\n[Bước 5/6] Vào trang Duyệt Testcase Đóng góp (Review & Feedback)...")
        await page.goto("http://localhost:8080/local/testcase_exchange/review.php?course=4")
        await page.wait_for_load_state("networkidle")
        await asyncio.sleep(3)

        # Step 6: Quay lại Dashboard sinh viên để xem kết quả hiển thị công khai
        print("\n[Bước 6/6] Quay lại Dashboard để quan sát trải nghiệm hiển thị tổng hợp...")
        await page.goto("http://localhost:8080/local/testcase_exchange/index.php?course=4")
        await page.wait_for_load_state("networkidle")
        await asyncio.sleep(4)

        print("\n🎉 Hoàn thành kịch bản mô phỏng quy trình Giảng viên! Trình duyệt sẽ mở thêm 10 giây để bạn quan sát.")
        await asyncio.sleep(10)
        await browser.close()

if __name__ == "__main__":
    asyncio.run(run_simulation())
