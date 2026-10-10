#!/bin/bash
set -e

echo "================================================================="
echo " 📦 XUẤT SAO LƯU TOÀN DIỆN HỆ THỐNG MOODLE + TESTCASE (PHD GRADE)"
echo "================================================================="

BACKUP_SQL="backup_moodle_and_testcase.sql.gz"
BACKUP_MOODLEDATA="moodledata.tar.gz"
MANIFEST_FILE="metadata/manifest.json"

mkdir -p metadata

# 1. Trích xuất CSDL với transaction nhất quán tuyệt đối (InnoDB snapshot)
echo ""
echo "[1/3] Đang sao lưu CSDL MariaDB (moodle & testcase_store) với --single-transaction..."
docker exec moodle_mariadb mariadb-dump -u root -prootpassword \
    --single-transaction \
    --routines \
    --events \
    --triggers \
    --databases moodle testcase_store | gzip -9 > "$BACKUP_SQL"

# Đồng thời tạo file sql giải nén để tương thích ngược nếu cần
gunzip -c "$BACKUP_SQL" > backup_moodle_and_testcase.sql

SQL_SIZE=$(ls -lh "$BACKUP_SQL" | awk '{print $5}')
echo "✓ Đã xuất CSDL thành công: $BACKUP_SQL ($SQL_SIZE)"

# 2. Sao lưu toàn bộ Docker Volume moodledata (chứa file nộp, ảnh, câu hỏi đính kèm)
echo ""
echo "[2/3] Đang sao lưu Docker Volume moodledata..."
docker run --rm \
    -v demo-moodle-jobe_moodledata:/moodledata:ro \
    -v "$(pwd)":/backup \
    alpine tar -czf /backup/"$BACKUP_MOODLEDATA" -C /moodledata .

DATA_SIZE=$(ls -lh "$BACKUP_MOODLEDATA" | awk '{print $5}')
echo "✓ Đã đóng gói moodledata thành công: $BACKUP_MOODLEDATA ($DATA_SIZE)"

# 3. Tạo bản kê khai (Manifest) với checksum SHA-256 cố định phiên bản runtime
echo ""
echo "[3/3] Đang ghi nhận phiên bản runtime và mã băm SHA-256 vào $MANIFEST_FILE..."
python3 -c "
import json, hashlib, time

def sha256_file(path):
    try:
        h = hashlib.sha256()
        with open(path, 'rb') as f:
            while chunk := f.read(8192):
                h.update(chunk)
        return h.hexdigest()
    except Exception:
        return 'N/A'

manifest = {
    'project': 'demo-moodle-jobe',
    'purpose': 'PhD Research Testcase Exchange & Reproducible Sandbox',
    'backup_timestamp_iso': time.strftime('%Y-%m-%dT%H:%M:%SZ', time.gmtime()),
    'git_commit': '$(git rev-parse HEAD 2>/dev/null || echo "uncommitted")',
    'environment_runtime': {
        'moodle_version': '4.4.12+ (Build: 20251212)',
        'php_version': '8.2.33',
        'mariadb_version': 'Bitnami MariaDB 11.x (13.1.1-MariaDB)',
        'jobe_sandbox_python': 'Python 3.12.3',
        'coderunner_commit': 'bab106d480716330d1f3330433652b3c8c8d174d',
        'qbehaviour_adaptive_commit': '73beb92d1d95d5a9ded94c0803871f0d57719f77',
        'plugin_local_testcase_exchange': '2026101001 (v2.2.0)',
        'plugin_quizaccess_testcaseexchange': '2026100501'
    },
    'checksums_sha256': {
        'database_sql_gz': sha256_file('$BACKUP_SQL'),
        'moodledata_tar_gz': sha256_file('$BACKUP_MOODLEDATA'),
        'docker_compose_yml': sha256_file('docker-compose.yml'),
        'dockerfile_moodle': sha256_file('Dockerfile'),
        'dockerfile_jobe': sha256_file('Dockerfile.jobe')
    }
}

with open('$MANIFEST_FILE', 'w') as f:
    json.dump(manifest, f, indent=2)
"
echo "✓ Đã cập nhật $MANIFEST_FILE"

echo ""
echo "================================================================="
echo " 🎉 SAO LƯU HOÀN TẤT VỚI ĐẦY ĐỦ CSDL + MOODLEDATA + MANIFEST!"
echo " Bạn có thể nén cả thư mục dự án này để mang sang máy mới:"
echo "   zip -r demo-moodle-jobe.zip . -x '*.git*'"
echo "================================================================="
