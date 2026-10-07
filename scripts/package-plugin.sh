#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
PLUGIN_DIR="${PROJECT_DIR}/local_testcase_exchange"
OUTPUT_DIR="${1:-${PROJECT_DIR}/dist}"
VERSION="$(sed -n "s/.*release[[:space:]]*=[[:space:]]*'\([^']*\)'.*/\1/p" "${PLUGIN_DIR}/version.php")"
ARCHIVE="${OUTPUT_DIR}/local_testcase_exchange-${VERSION}.zip"

if [[ -z "${VERSION}" ]]; then
    echo "Unable to read plugin release from version.php" >&2
    exit 1
fi

mkdir -p "${OUTPUT_DIR}"
rm -f "${ARCHIVE}"

(
    cd "${PROJECT_DIR}"
    zip -qr "${ARCHIVE}" local_testcase_exchange \
        -x '*/.DS_Store' '*/.git/*' '*/tests/.phpunit.result.cache'
)

echo "Created ${ARCHIVE}"
unzip -l "${ARCHIVE}"
