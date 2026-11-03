#!/usr/bin/env bash
# Build a clean, installable zip for wp.org, honouring .distignore.
# Boots via the PSR-4 fallback in autoload.php, so /vendor is not shipped.
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
# The zip's top folder has to match the Text Domain, which is the wp.org slug.
# The checkout directory does not: this one is named `plogins-assets` while the
# plugin is `pagelean`, and a package built from the folder name is a
# TextDomainMismatch the reviewer rejects.
NAME="$(grep -m1 -h "Text Domain:" "$ROOT_DIR"/*.php 2>/dev/null | sed 's/.*Text Domain:[[:space:]]*//; s/[[:space:]]*$//')"
NAME="${NAME:-$(basename "$ROOT_DIR")}"
OUT_DIR="${1:-/tmp/${NAME}-build}"
STAGE="${OUT_DIR}/${NAME}"

rm -rf "${OUT_DIR}"
mkdir -p "${STAGE}"

rsync -a --exclude-from="${ROOT_DIR}/.distignore" \
    --exclude '.git' --exclude 'node_modules' --exclude 'vendor' \
    --exclude '.DS_Store' \
    "${ROOT_DIR}/" "${STAGE}/"

find "${STAGE}" -name '.DS_Store' -delete

VERSION="$(grep -m1 "Version:" "${ROOT_DIR}/${NAME}.php" | tr -dc '0-9.')"
ZIP="/tmp/${NAME}.zip"
rm -f "${ZIP}"
# zip -r adds to an existing archive, so a stale one keeps files the build no longer ships.
rm -f "${ZIP}"
( cd "${OUT_DIR}" && zip -rqX "${ZIP}" "${NAME}" -x '*.DS_Store' )
echo "Built ${ZIP}"
