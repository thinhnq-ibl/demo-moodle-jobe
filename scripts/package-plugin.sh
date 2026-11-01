#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
LOCAL_DIR="${PROJECT_DIR}/local_testcase_exchange"
QUIZACCESS_DIR="${PROJECT_DIR}/quizaccess_testcaseexchange"
OUTPUT_DIR="${1:-${PROJECT_DIR}/dist}"

LOCAL_RELEASE="$(sed -n "s/.*release[[:space:]]*=[[:space:]]*'\([^']*\)'.*/\1/p" "${LOCAL_DIR}/version.php")"
QUIZ_RELEASE="$(sed -n "s/.*release[[:space:]]*=[[:space:]]*'\([^']*\)'.*/\1/p" "${QUIZACCESS_DIR}/version.php")"

mkdir -p "${OUTPUT_DIR}"
BUILD_TMP="$(mktemp -d)"
trap 'rm -rf "${BUILD_TMP}"' EXIT

echo "=== PACKAGING MOODLE MARKETPLACE RELEASES ==="
echo "Local Plugin Release: ${LOCAL_RELEASE}"
echo "QuizAccess Subplugin Release: ${QUIZ_RELEASE}"

# 1. Package local_testcase_exchange -> root folder in zip MUST be 'testcase_exchange'
LOCAL_ARCHIVE="${OUTPUT_DIR}/moodle-local_testcase_exchange-${LOCAL_RELEASE}.zip"
rm -f "${LOCAL_ARCHIVE}"
mkdir -p "${BUILD_TMP}/testcase_exchange"
rsync -av --exclude='.*' --exclude='tests/.phpunit.result.cache' "${LOCAL_DIR}/" "${BUILD_TMP}/testcase_exchange/" >/dev/null

(
    cd "${BUILD_TMP}"
    zip -qr "${LOCAL_ARCHIVE}" testcase_exchange \
        -x '*/.DS_Store' '*/.git*' '*/__MACOSX*'
)
echo "✅ Packaged: ${LOCAL_ARCHIVE}"

# 2. Package quizaccess_testcaseexchange -> root folder in zip MUST be 'testcaseexchange'
QUIZ_ARCHIVE="${OUTPUT_DIR}/moodle-quizaccess_testcaseexchange-${QUIZ_RELEASE}.zip"
rm -f "${QUIZ_ARCHIVE}"
mkdir -p "${BUILD_TMP}/testcaseexchange"
rsync -av --exclude='.*' "${QUIZACCESS_DIR}/" "${BUILD_TMP}/testcaseexchange/" >/dev/null

(
    cd "${BUILD_TMP}"
    zip -qr "${QUIZ_ARCHIVE}" testcaseexchange \
        -x '*/.DS_Store' '*/.git*' '*/__MACOSX*'
)
echo "✅ Packaged: ${QUIZ_ARCHIVE}"

# 3. Generate Checksums
(
    cd "${OUTPUT_DIR}"
    sha256sum "moodle-local_testcase_exchange-${LOCAL_RELEASE}.zip" "moodle-quizaccess_testcaseexchange-${QUIZ_RELEASE}.zip" > SHA256SUMS 2>/dev/null || \
    shasum -a 256 "moodle-local_testcase_exchange-${LOCAL_RELEASE}.zip" "moodle-quizaccess_testcaseexchange-${QUIZ_RELEASE}.zip" > SHA256SUMS
)
echo "✅ Checksums generated in ${OUTPUT_DIR}/SHA256SUMS"

# 4. Verify archive structure
echo "=== VERIFYING ARCHIVE STRUCTURES ==="
echo "--- local_testcase_exchange root ---"
unzip -l "${LOCAL_ARCHIVE}" | head -n 12
echo "--- quizaccess_testcaseexchange root ---"
unzip -l "${QUIZ_ARCHIVE}" | head -n 12
echo "=== PACKAGING COMPLETE ==="
