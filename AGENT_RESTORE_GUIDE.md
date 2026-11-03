# 🤖 AGENT RESTORE & REPRODUCIBILITY GUIDE
> **Dành cho AI Agents & Hệ thống Tự động hóa**  
> Dự án: **demo-moodle-jobe** (Hệ thống Khảo sát & Tái lập Tiến hóa Test Suite - PhD Research)

---

## 1. TỔNG QUAN HỆ THỐNG (SYSTEM CONTEXT)
Hệ thống này là môi trường nghiên cứu PhD tích hợp bao gồm:
- **Moodle LMS 4.4.12+ (PHP 8.2)** trên cổng `8080`
- **MariaDB 11.x** trên cổng `3306` lưu trữ 2 CSDL độc lập:
  - `moodle`: Chứa người dùng, khóa học (CS101 - id 2, AI-PY-PILOT - id 4), câu hỏi CodeRunner, lịch sử bài nộp (`question_attempts`, `question_attempt_steps`).
  - `testcase_store`: Chứa 18 testcase sinh viên đóng góp, đánh giá sao (`testcase_ratings`), snapshots mã nguồn sinh viên (`student_code_snapshot`), lời giải giáo viên (`oracle_solution_snapshot`) và `runtime_context`.
- **Cụm Jobe Sandbox kép**: `jobe1` (cổng `4001`) và `jobe2` (cổng `4002`), chạy Python 3.12 sandbox cô lập.
- **2 Plugins tùy biến**:
  - `local_testcase_exchange`: Hub hiển thị, bộ lọc 3 cấp (`course_id` -> `quiz_id` -> `question_id`), duyệt và chạy replay testcase độc lập trên Jobe.
  - `quizaccess_testcaseexchange`: Subplugin tích hợp vào bài thi Quiz để hiển thị lời kêu gọi đóng góp sau khi nộp bài.

---

## 2. ĐIỀU KIỆN TIÊN QUYẾT & KHỞI ĐỘNG DOCKER (PRE-FLIGHT & START DOCKER)

### 2.1. Kiểm tra và Khởi động Docker Daemon
Nếu Docker daemon chưa chạy, Agent hãy dùng lệnh tương ứng với hệ điều hành để bật:

- **Trên macOS**:
  ```bash
  open -a Docker
  # Đợi Docker daemon sẵn sàng
  until docker info > /dev/null 2>&1; do sleep 2; done
  echo "✓ Docker daemon macOS đã sẵn sàng!"
  ```
- **Trên Linux (Ubuntu / Debian / RHEL)**:
  ```bash
  sudo systemctl start docker
  until docker info > /dev/null 2>&1; do sleep 2; done
  echo "✓ Docker daemon Linux đã sẵn sàng!"
  ```
- **Trên Windows (WSL2 / PowerShell)**:
  ```powershell
  Start-Process "C:\Program Files\Docker\Docker\Docker Desktop.exe"
  ```

*(Lưu ý: Script `reproduce_and_restore.sh` đã được tích hợp sẵn đoạn mã tự phát hiện và tự động gọi `open -a Docker` hoặc `systemctl start docker` nếu Docker đang tắt).*

### 2.2. Cơ chế Tải và Dựng Docker Images (Image Build & Pull)
Hệ thống **không yêu cầu Agent phải clone bất kỳ repository bên ngoài nào**, vì mọi cấu hình và Dockerfile đã có sẵn trong gói:
1. `Dockerfile`: Tự động kéo base image `moodlehq/moodle-php-apache:8.2`, tải Moodle 4.4 stable và CodeRunner plugin.
2. `Dockerfile.jobe`: Kéo base image `trampgeek/jobeinabox:latest` thiết lập sandbox compiler.
3. `docker-compose.yml`: Kéo `bitnami/mariadb:latest` và điều phối 4 container.

Nếu Agent muốn chủ động tải trước hoặc build image trước khi chạy khôi phục:
```bash
# Tải và build toàn bộ 4 service trước
docker compose build --pull
docker compose pull mariadb
```

### 2.3. Kiểm tra cổng và tệp dữ liệu
```bash
# Kiểm tra cổng 8080, 3306, 4001, 4002 không bị chiếm dụng
for port in 8080 3306 4001 4002; do
  lsof -i :$port > /dev/null 2>&1 && echo "⚠️ Cảnh báo: Cổng $port đang được sử dụng!"
done

# Kiểm tra các tệp dữ liệu cốt lõi có trong thư mục
ls -lh backup_moodle_and_testcase.sql.gz moodledata.tar.gz docker-compose.yml metadata/manifest.json
```

---

## 3. LỆNH KHÔI PHỤC TỰ ĐỘNG (NON-INTERACTIVE EXECUTION)

> [!IMPORTANT]
> **Lưu ý đặc biệt cho AI Agents:**  
> Nếu chạy `./reproduce_and_restore.sh` không có tham số, script sẽ hỏi tương tác `[y/N]` nếu phát hiện DB đã có sẵn dữ liệu.  
> Để script chạy hoàn toàn tự động không chặn luồng (non-blocking), **BẮT BUỘC** truyền cờ `--force` hoặc set `CI=true`.

### Lệnh thực thi chuẩn:
```bash
chmod +x reproduce_and_restore.sh
./reproduce_and_restore.sh --force
```

*(Thời gian hoàn tất ước tính: 45 – 90 giây tùy tốc độ build của máy).*

---

## 4. QUY TRÌNH NỘI TẠI SCRIPT THỰC HIỆN (INTERNAL WORKFLOW)

Agent có thể theo dõi tiến trình 5 bước của script:
1. **Bước 0 (Verify Checksum)**: Đọc `metadata/manifest.json`, kiểm tra mã băm SHA-256 của `backup_moodle_and_testcase.sql.gz`, `moodledata.tar.gz`, `docker-compose.yml`.
2. **Bước 1 (Build & Up)**: Thực thi `docker compose -f docker-compose.yml up -d --build` (tạo 4 container: `moodle_mariadb`, `moodle_jobe1`, `moodle_jobe2`, `moodle_app`).
3. **Bước 2 (Wait MariaDB)**: Lặp kiểm tra `mariadb-admin ping` tối đa 30 lần (60 giây) cho đến khi DB nhận kết nối.
4. **Bước 3 (Import DB & Moodledata)**:
   - Nạp file `backup_moodle_and_testcase.sql.gz` vào MariaDB (nạp cả 2 database `moodle` và `testcase_store`).
   - Dùng container phụ `alpine` giải nén `moodledata.tar.gz` vào volume `demo-moodle-jobe_moodledata` và gán quyền `chown -R www-data:www-data`.
5. **Bước 4 (Sync Plugins & Config)**:
   - Copy `local_testcase_exchange` và `quizaccess_testcaseexchange` vào container.
   - Chạy `admin/cli/upgrade.php` nâng cấp DB schema nếu cần.
   - Cấu hình CodeRunner: `jobe_host="jobe1;jobe2"` và gỡ chặn cURL: `curlsecurityblockedhosts=""`.
   - Làm mới bộ nhớ đệm: `purge_caches.php`.
6. **Bước 5 (Health Check)**: Kiểm tra API của `jobe1`, `jobe2` và số lượng user trong DB.

### 4.1. Cơ chế Cài đặt & Cấu hình Moodle + CodeRunner
> [!NOTE]
> **Không cần cài đặt thủ công qua giao diện web:**
> - Moodle 4.4 Stable và plugin CodeRunner (`qtype_coderunner` + `qbehaviour_adaptive_adapted_for_coderunner`) **đã được tích hợp sẵn 100% trong Dockerfile**.
> - CSDL Moodle và phân quyền đã được import đầy đủ qua script.
> - Nếu Agent muốn cài đặt lại hoặc cấu hình lại CodeRunner bằng lệnh CLI độc lập:
>   ```bash
>   # 1. Cập nhật schema plugin vào Moodle:
>   docker exec moodle_app php /var/www/html/admin/cli/upgrade.php --non-interactive
>
>   # 2. Cấu hình CodeRunner trỏ tới 2 máy chủ Jobe sandbox:
>   docker exec moodle_app php /var/www/html/admin/cli/cfg.php --component=qtype_coderunner --name=jobe_host --set="jobe1;jobe2"
>
>   # 3. Mở chặn cURL nội bộ Docker (Moodle mặc định chặn kết nối đến hostname local):
>   docker exec moodle_app php /var/www/html/admin/cli/cfg.php --name=curlsecurityblockedhosts --set=""
>
>   # 4. Xóa cache Moodle:
>   docker exec moodle_app php /var/www/html/admin/cli/purge_caches.php
>   ```
> - **URL Tự kiểm tra CodeRunner Sandbox (Self-test)**:  
>   `http://localhost:8080/question/type/coderunner/sandboxcheck.php` (Đăng nhập với tài khoản `admin`)

---

## 5. BỘ LỆNH KIỂM THỬ XÁC MINH DÀNH CHO AGENT (VERIFICATION CHECKLIST)

Sau khi script báo hoàn tất, Agent hãy chạy các lệnh sau để tự xác nhận môi trường đã sẵn sàng 100%:

### A. Kiểm tra trạng thái Container
```bash
docker compose ps
# Mong đợi: 4 containers (moodle_mariadb, moodle_jobe1, moodle_jobe2, moodle_app) đều có State: "Up"
```

### B. Kiểm tra số lượng Testcase và Đánh giá (Testcase Store)
```bash
docker exec moodle_mariadb mariadb -u root -prootpassword testcase_store -e "
SELECT 
  (SELECT COUNT(*) FROM testcases) AS total_testcases,
  (SELECT COUNT(*) FROM testcases WHERE is_approved = 1) AS approved_testcases,
  (SELECT COUNT(*) FROM testcase_ratings) AS total_ratings,
  (SELECT COUNT(*) FROM testcase_runs) AS total_runs;
"
# Mong đợi: total_testcases = 18, total_ratings = 11, total_runs >= 2
```

### C. Kiểm tra Moodle Database & Users
```bash
docker exec moodle_app php -r '
define("CLI_SCRIPT", true);
require_once("/var/www/html/config.php");
global $DB;
echo "Users: " . $DB->count_records("user") . "\n";
echo "Courses: " . $DB->count_records("course") . "\n";
echo "Quizzes: " . $DB->count_records("quiz") . "\n";
'
# Mong đợi: Users >= 4 (admin, student1, guest...), Courses >= 3 (Site, CS101, AI-PY-PILOT), Quizzes >= 2
```

### D. Kiểm tra kết nối tới Sandbox Jobe1 & Jobe2
```bash
docker exec moodle_app curl -s http://jobe1/jobe/index.php/restapi/languages
docker exec moodle_app curl -s http://jobe2/jobe/index.php/restapi/languages
# Mong đợi: Cả 2 lệnh đều trả về JSON có chứa [..., ["python3", "..."], ...]
```

### E. Kiểm tra HTTP Status của Web LMS
```bash
curl -I -s http://localhost:8080 | head -n 1
# Mong đợi: HTTP/1.1 200 OK hoặc HTTP/1.1 303 See Other (chuyển hướng login)
```

---

## 6. THÔNG TIN ĐĂNG NHẬP & TÀI NGUYÊN KIỂM THỬ

| Tài nguyên | URL / Thông tin | Tài khoản / Ghi chú |
| :--- | :--- | :--- |
| **Moodle LMS Web** | `http://localhost:8080` | - |
| **Quản trị viên (Admin)** | `http://localhost:8080/login/index.php` | `admin` / `AdminPassword123!` |
| **Sinh viên mẫu (Student)** | `http://localhost:8080/login/index.php` | `student1` / `StudentPassword123!` |
| **Kho Testcase Hub** | `http://localhost:8080/local/testcase_exchange/index.php` | Xem & lọc testcase theo Course/Quiz/Question |
| **Trang Duyệt Testcase (Review)** | `http://localhost:8080/local/testcase_exchange/review.php` | Giáo viên duyệt, chấm sao, test chạy độc lập Jobe |
| **Khóa học CS101** | `http://localhost:8080/course/view.php?id=2` | Khóa học demo cơ bản |
| **Khóa học AI-PY-PILOT** | `http://localhost:8080/course/view.php?id=4` | Khóa học thực nghiệm PhD chính |
| **MariaDB Direct Port** | `localhost:3306` | `root` / `rootpassword` |
| **Jobe 1 Direct Port** | `http://localhost:4001/jobe/index.php/restapi/languages` | REST API |
| **Jobe 2 Direct Port** | `http://localhost:4002/jobe/index.php/restapi/languages` | REST API |

> Trong backup hiện tại, username được tạo là `student1` và `student2`. Mật khẩu hai tài khoản mẫu được thiết lập bởi fixture là `StudentPassword123!`. Nếu triển khai production, hãy đổi các mật khẩu mặc định trước khi mở hệ thống ra mạng.

### 6.1. Hướng dẫn sử dụng plugin Testcase Exchange

Plugin chỉ hiển thị các câu hỏi CodeRunner thuộc Quiz trong khóa học hiện tại. Testcase đóng góp là dữ liệu tham khảo được chia sẻ theo chính sách; chúng không tự động thêm vào bộ test chấm điểm chính thức của CodeRunner.

**Giảng viên: cấu hình khóa học và câu hỏi**
1. Đăng nhập bằng tài khoản có quyền quản lý chính sách, mở **Kho Testcase Hub** trong khóa học (ví dụ khóa AI-PY-PILOT: `http://localhost:8080/local/testcase_exchange/index.php?course=4`).
2. Chọn biểu tượng cấu hình chính sách cho khóa học/câu hỏi cần dùng.
3. Chọn Quiz và câu hỏi CodeRunner, bật tính năng; cấu hình chế độ duyệt (`teacher` để giảng viên duyệt), chính sách trao testcase (`one_for_one` hoặc tắt), hiển thị oracle output, giới hạn số lần chạy và kích thước input.
4. Chọn kiểu input (`stdin`, `testcode` hoặc template có `{{INPUT}}`), cách chuẩn hóa kết quả (`raw`, `trim` hoặc `json`) và các nhóm testcase phù hợp; lưu cấu hình.

**Sinh viên: chạy và đề xuất testcase**
1. Đăng nhập bằng tài khoản sinh viên đã ghi danh vào khóa học, mở Quiz và câu hỏi CodeRunner có bật chính sách đóng góp.
2. Nhập/chỉnh sửa lời giải, testcase input, kết quả dự đoán, mục đích và nhóm testcase.
3. Chạy testcase để xem kết quả của mã sinh viên và lời giải tham chiếu. Lần chạy này riêng tư; dữ liệu chỉ được gửi vào hàng chờ chia sẻ khi sinh viên chọn **Đề xuất chia sẻ**.
4. Theo dõi trạng thái đề xuất trên giao diện plugin. Giảng viên có thể yêu cầu giải thích thêm hoặc từ chối testcase.

**Giảng viên: duyệt và đánh giá**
1. Từ Hub chọn **Duyệt testcase**, hoặc mở `http://localhost:8080/local/testcase_exchange/review.php?course=4` cho khóa AI-PY-PILOT.
2. Lọc theo câu hỏi, kiểm tra input, kết quả oracle, mục đích và nhóm testcase.
3. Chọn duyệt, yêu cầu giải thích hoặc từ chối; có thể thêm nhận xét và đánh giá sao. Testcase đã duyệt được chia sẻ/trao theo chính sách đã cấu hình.

Tài khoản admin dùng để quản trị và duyệt; tài khoản `student1`/`student2` dùng để thử luồng sinh viên. Tài khoản sinh viên cần được ghi danh vào khóa học có Quiz CodeRunner.

### 6.2. Cài đặt/nâng cấp plugin từ gói ZIP

Gói phát hành mới nhất trong workspace: `dist/local_testcase_exchange-2.2.0.zip`.

1. Sao lưu CSDL Moodle và thư mục plugin hiện có trước khi nâng cấp.
2. Đăng nhập Moodle bằng admin, mở **Site administration > Plugins > Install plugins**, tải ZIP lên và chọn **Install plugin from the ZIP file**.
3. Xác nhận component `local_testcase_exchange`, tiếp tục qua trang kiểm tra plugin và chọn **Upgrade Moodle database now**.
4. Sau nâng cấp, mở **Site administration > Plugins > Local plugins > CodeRunner Testcase Exchange** kiểm tra cấu hình kho DB/Jobe; trong Docker Compose, `entrypoint.sh` đồng bộ cấu hình từ các biến `TESTCASE_*` khi container khởi động.
5. Chạy **Purge caches** nếu giao diện chưa cập nhật, rồi kiểm tra Hub và trang Review bằng tài khoản phù hợp.

---

## 7. CẨM NANG XỬ LÝ SỰ CỐ DÀNH CHO AGENT (TROUBLESHOOTING PLAYBOOK)

### Tình huống 1: MariaDB khởi động chậm hơn dự kiến
- **Hiện tượng**: Script báo `Đang đợi MariaDB (x/30)...`
- **Cách xử lý**: Script đã tích hợp tự động thử lại 30 lần (mỗi lần 2s). Nếu quá thời gian, Agent hãy kiểm tra log MariaDB:
  ```bash
  docker logs moodle_mariadb --tail 50
  ```

### Tình huống 2: Xung đột cổng máy chủ (Port already in use)
- **Hiện tượng**: `Bind for 0.0.0.0:8080 failed: port is already allocated`
- **Cách xử lý**: Dừng tiến trình cũ đang chiếm cổng hoặc sửa port ánh xạ ở bên trái trong `docker-compose.yml` (ví dụ `"8081:80"`).

### Tình huống 3: Volume cũ bị lỗi dữ liệu / Cần làm sạch từ đầu (Clean Slate)
- **Cách xử lý**: Dừng toàn bộ và xóa sạch volumes cũ trước khi restore:
  ```bash
  docker compose down -v
  ./reproduce_and_restore.sh --force
  ```

### Tình huống 4: Giao diện Moodle bị trắng hoặc không nhận CSS plugin
- **Cách xử lý**: Chạy lệnh xóa cache Moodle:
  ```bash
  docker exec moodle_app php /var/www/html/admin/cli/purge_caches.php
  ```

---

## 8. CÁCH ĐÓNG GÓI XUẤT NGƯỢC LẠI (EXPORTING BACKUP)
Nếu Agent sau khi thực nghiệm có thêm testcase mới hoặc cập nhật code và muốn xuất bản backup mới:
```bash
chmod +x export_backup.sh
./export_backup.sh
zip -r demo-moodle-jobe.zip . -x "*.git*"
```
Script sẽ tự động dump lại DB, volume moodledata và tính lại SHA-256 vào `metadata/manifest.json`.

---

## 9. CHECKLIST NGẮN CHO AGENT: CÀI PLUGIN ZIP

Áp dụng khi Moodle đã được cài sẵn; không chạy restore database của dự án.

1. Xác nhận Moodle từ 4.3 trở lên và CodeRunner đã cài; sao lưu CSDL Moodle và thư mục `local/testcase_exchange` hiện tại.
2. Dùng `dist/local_testcase_exchange-2.2.0.zip` tại **Site administration > Plugins > Install plugins**; xác nhận component `local_testcase_exchange`.
3. Chạy **Upgrade Moodle database now**, sau đó purge caches.
4. Kiểm tra trang **CodeRunner Testcase Exchange** trong Local plugins, xác nhận cấu hình DB/Jobe và mở Hub/Review để smoke-test quyền truy cập.

Nếu không dùng được giao diện cài plugin, giải nén thư mục `local_testcase_exchange` từ ZIP vào `<MOODLE_ROOT>/local/`, đặt owner theo web server, rồi chạy từ Moodle host/container:

```bash
php <MOODLE_ROOT>/admin/cli/upgrade.php --non-interactive
php <MOODLE_ROOT>/admin/cli/purge_caches.php
```
